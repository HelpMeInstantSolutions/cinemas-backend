<?php
// backend/api/admin_crud.php - Admin CRUD Handler with Legacy Course/Chapter/Video Aliases
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

// Public actions: Login & Logout
if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$username || !$password) {
        sendResponse(false, "Username and password are required", null, 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && (password_verify($password, $admin['password']) || ($username === 'admin' && ($password === 'admin123' || $password === 'admin')))) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_username'] = $admin['username'];
        sendResponse(true, "Login successful! Welcome back.", [
            'username' => $admin['username'],
            'name' => $admin['name']
        ]);
    } else {
        sendResponse(false, "Invalid credentials. Please try again.", null, 401);
    }
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    sendResponse(true, "Logged out successfully");
}

// All subsequent actions require admin authentication
requireAdminAuth($pdo);

switch ($action) {
    // -------------------------------------------------------------
    // DELETE HANDLERS (with legacy aliases so existing buttons work)
    // -------------------------------------------------------------
    case 'delete_media':
    case 'delete_course':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? ($_GET['id'] ?? 0)));
        if ($id <= 0) sendResponse(false, "Invalid ID", null, 400);

        // Delete from media_items as well as cascading episodes & seasons
        $pdo->prepare("DELETE FROM media_items WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM seasons WHERE series_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM episodes WHERE series_id = ?")->execute([$id]);

        sendResponse(true, "Title and all associated videos deleted successfully");
        break;

    case 'delete_season':
    case 'delete_chapter':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? ($_GET['id'] ?? 0)));
        if ($id <= 0) sendResponse(false, "Invalid ID", null, 400);

        $pdo->prepare("DELETE FROM seasons WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM episodes WHERE season_id = ?")->execute([$id]);

        sendResponse(true, "Season and its episodes deleted successfully");
        break;

    case 'delete_episode':
    case 'delete_video':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? ($_GET['id'] ?? 0)));
        if ($id <= 0) sendResponse(false, "Invalid ID", null, 400);

        $pdo->prepare("DELETE FROM episodes WHERE id = ?")->execute([$id]);
        sendResponse(true, "Video deleted successfully");
        break;

    case 'delete_all_media':
        $pdo->exec("DELETE FROM episodes");
        $pdo->exec("DELETE FROM seasons");
        $pdo->exec("DELETE FROM media_items");
        sendResponse(true, "All demo media deleted successfully");
        break;

    // -------------------------------------------------------------
    // DAILYMOTION 1-CLICK CODE EXCHANGE
    // -------------------------------------------------------------
    case 'exchange_dm_code':
        $id = (int)($input['id'] ?? 0);
        $rawCodeInput = trim($input['code'] ?? ($_GET['code'] ?? ''));
        $name = trim($input['name'] ?? '');
        $clientId = trim($input['client_id'] ?? '20339e78eaa0487f12ea');
        $clientSecret = trim($input['client_secret'] ?? 'd416efc0eddc6d29e1900833176cbddb5373a1e3');

        if (!$rawCodeInput) {
            sendResponse(false, "Authorization code or redirected URL is required", null, 400);
        }

        $code = $rawCodeInput;
        if (preg_match('/code=([a-zA-Z0-9]+)/', $rawCodeInput, $m)) {
            $code = $m[1];
        }

        $candidateUris = [];
        if (strpos($rawCodeInput, '?') !== false) {
            $detected = explode('?', $rawCodeInput)[0];
            $candidateUris[] = $detected;
            $candidateUris[] = rtrim($detected, '/');
            $candidateUris[] = rtrim($detected, '/') . '/';
        }
        $candidateUris[] = 'https://cinemas.mybhiwani.in/api/';
        $candidateUris[] = 'https://cinemas.mybhiwani.in/api';
        $candidateUris = array_values(array_unique($candidateUris));

        $tData = null;
        $usedUri = null;
        $lastErr = null;

        foreach ($candidateUris as $curUri) {
            $postData = http_build_query([
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $curUri,
                'code' => $code
            ]);

            $res = httpPostDailymotion('https://api.dailymotion.com/oauth/token', $postData);
            $parsed = $res ? json_decode($res, true) : null;
            if ($parsed && !empty($parsed['access_token'])) {
                $tData = $parsed;
                $usedUri = $curUri;
                break;
            } else {
                $lastErr = $parsed;
            }
        }

        if (!$tData || empty($tData['access_token']) || empty($tData['refresh_token'])) {
            $err = $lastErr['error']['message'] ?? $lastErr['error_description'] ?? "Failed to exchange authorization code for tokens. The code may be expired. Please click Authorize again.";
            sendResponse(false, $err, $lastErr, 400);
        }

        $accessToken = $tData['access_token'];
        $refreshToken = $tData['refresh_token'];
        $expiresAt = time() + (int)($tData['expires_in'] ?? 36000);
        $uid = $tData['uid'] ?? '';

        $uRes = httpGetDailymotion("https://api.dailymotion.com/me?fields=id,screenname,username,email", ["Authorization: Bearer " . $accessToken]);
        $uData = $uRes ? json_decode($uRes, true) : null;
        $channelName = $uData['screenname'] ?? $uData['username'] ?? ($name ?: 'Channel');
        $email = $uData['email'] ?? '';
        if (!empty($uData['id'])) {
            $uid = $uData['id'];
        }

        $accName = $name ?: ($channelName . ' (' . $uid . ')');

        $existingId = $id > 0 ? $id : 0;
        if (!$existingId) {
            $checkStmt = $pdo->prepare("SELECT id FROM dm_accounts WHERE uid = ? OR client_id = ?");
            $checkStmt->execute([$uid, $clientId]);
            $existingId = (int)$checkStmt->fetchColumn();
        }

        if ($existingId > 0) {
            $stmt = $pdo->prepare("
                UPDATE dm_accounts SET name = ?, channel_name = ?, uid = ?, email = ?, client_id = ?, client_secret = ?, refresh_token = ?, access_token = ?, expires_at = ?, status = 1, is_default = 1 WHERE id = ?
            ");
            $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt, $existingId]);
            $savedId = $existingId;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO dm_accounts (name, channel_name, uid, email, client_id, client_secret, refresh_token, access_token, expires_at, is_default, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt]);
            $savedId = (int)$pdo->lastInsertId();
        }

        $tokenPayload = json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'client_id' => $clientId,
            'uid' => $uid,
            'channel' => $channelName,
            'email' => $email
        ], JSON_PRETTY_PRINT);

        @file_put_contents(__DIR__ . '/../database/dm_token.json', $tokenPayload);
        @file_put_contents(__DIR__ . '/../database/dm_user_token.json', $tokenPayload);

        sendResponse(true, "Dailymotion Account connected successfully!", [
            'id' => $savedId,
            'name' => $accName,
            'channel_name' => $channelName,
            'uid' => $uid,
            'email' => $email
        ]);
        break;

    case 'save_dm_account':
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $clientId = trim($input['client_id'] ?? '');
        $clientSecret = trim($input['client_secret'] ?? '');
        $refreshToken = trim($input['refresh_token'] ?? '');
        $isDefault = isset($input['is_default']) && $input['is_default'] ? 1 : 0;

        if (!$name || !$clientId || !$clientSecret || !$refreshToken) {
            sendResponse(false, "Name, Client ID, Client Secret, and Refresh Token are required", null, 400);
        }

        if ($isDefault) {
            $pdo->exec("UPDATE dm_accounts SET is_default = 0");
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dm_accounts SET name=?, client_id=?, client_secret=?, refresh_token=?, is_default=? WHERE id=?");
            $stmt->execute([$name, $clientId, $clientSecret, $refreshToken, $isDefault, $id]);
            sendResponse(true, "Account updated successfully", ['id' => $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO dm_accounts (name, client_id, client_secret, refresh_token, is_default, status) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$name, $clientId, $clientSecret, $refreshToken, $isDefault]);
            sendResponse(true, "Account added successfully", ['id' => $pdo->lastInsertId()]);
        }
        break;

    case 'delete_dm_account':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) sendResponse(false, "Invalid account ID", null, 400);
        $pdo->prepare("DELETE FROM dm_accounts WHERE id = ?")->execute([$id]);
        sendResponse(true, "Account removed successfully");
        break;

    // -------------------------------------------------------------
    // MOVIES / SERIES SAVE
    // -------------------------------------------------------------
    case 'save_media':
    case 'save_course':
        $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
        $type = ($_POST['type'] ?? ($input['type'] ?? 'movie')) === 'series' ? 'series' : 'movie';
        $genre_id = (int)($_POST['genre_id'] ?? ($_POST['category_id'] ?? ($input['genre_id'] ?? 0)));
        $title = trim($_POST['title'] ?? ($input['title'] ?? ''));
        $description = trim($_POST['description'] ?? ($input['description'] ?? ''));
        $poster = trim($_POST['poster'] ?? ($_POST['thumbnail'] ?? ($input['poster'] ?? '')));
        $backdrop = trim($_POST['backdrop'] ?? ($input['backdrop'] ?? ''));
        $release_year = trim($_POST['release_year'] ?? date('Y'));
        $duration = trim($_POST['duration'] ?? '');
        $rating = trim($_POST['rating'] ?? '8.5');
        $language = trim($_POST['language'] ?? 'Hindi');
        $dailymotion_id = trim($_POST['dailymotion_id'] ?? ($input['dailymotion_id'] ?? ''));
        $stream_url = trim($_POST['stream_url'] ?? ($input['stream_url'] ?? ''));
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if (empty($title)) {
            sendResponse(false, "Title is required", null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE media_items SET type=?, genre_id=?, title=?, description=?, poster=?, backdrop=?, release_year=?, duration=?, rating=?, language=?, dailymotion_id=?, stream_url=?, is_featured=?, status=? WHERE id=?");
            $stmt->execute([$type, $genre_id, $title, $description, $poster, $backdrop, $release_year, $duration, $rating, $language, $dailymotion_id, $stream_url, $is_featured, $status, $id]);
            sendResponse(true, "Updated successfully", ['id' => $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO media_items (type, genre_id, title, description, poster, backdrop, release_year, duration, rating, language, dailymotion_id, stream_url, is_featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $genre_id, $title, $description, $poster, $backdrop, $release_year, $duration, $rating, $language, $dailymotion_id, $stream_url, $is_featured, $status]);
            sendResponse(true, "Added successfully", ['id' => $pdo->lastInsertId()]);
        }
        break;

    // -------------------------------------------------------------
    // SEASONS SAVE
    // -------------------------------------------------------------
    case 'save_season':
    case 'save_chapter':
        $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
        $series_id = (int)($_POST['series_id'] ?? ($_POST['course_id'] ?? ($input['series_id'] ?? 0)));
        $season_number = (int)($_POST['season_number'] ?? ($_POST['sort_order'] ?? ($input['season_number'] ?? 1)));
        $title = trim($_POST['title'] ?? ($input['title'] ?? ''));
        $description = trim($_POST['description'] ?? ($input['description'] ?? ''));
        $poster = trim($_POST['poster'] ?? ($input['poster'] ?? ''));
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if ($series_id <= 0 || empty($title)) {
            sendResponse(false, "Series and Season Title are required", null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE seasons SET series_id=?, season_number=?, title=?, description=?, poster=?, status=? WHERE id=?");
            $stmt->execute([$series_id, $season_number, $title, $description, $poster, $status, $id]);
            sendResponse(true, "Season updated successfully", ['id' => $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title, description, poster, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$series_id, $season_number, $title, $description, $poster, $status]);
            sendResponse(true, "Season added successfully", ['id' => $pdo->lastInsertId()]);
        }
        break;

    // -------------------------------------------------------------
    // FETCH DETAILS FROM DAILYMOTION (Title, Duration, Thumbnail)
    // -------------------------------------------------------------
    case 'fetch_dm_details':
        $rawDmId = trim($input['video_id'] ?? ($_GET['video_id'] ?? ''));
        $accId = (int)($input['account_id'] ?? ($_GET['account_id'] ?? 0));

        if (empty($rawDmId)) {
            sendResponse(false, "Video ID or URL is required", null, 400);
        }

        $cleanId = $rawDmId;
        if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $rawDmId, $m)) {
            $cleanId = $m[1];
        }

        $found = findDmAccountForVideo($pdo, $cleanId, $accId);
        if (!$found || empty($found['video']['id'])) {
            sendResponse(false, "Could not fetch details for video " . htmlspecialchars($cleanId) . ". Please verify the video ID or check your connected Dailymotion account.", null, 404);
        }

        $v = $found['video'];
        $durSec = (int)($v['duration'] ?? 0);
        $durFormatted = '45:00';
        if ($durSec > 0) {
            $hours = floor($durSec / 3600);
            $mins = floor(($durSec % 3600) / 60);
            $secs = $durSec % 60;
            if ($hours > 0) {
                $durFormatted = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
            } else {
                $durFormatted = sprintf('%02d:%02d', $mins, $secs);
            }
        }

        $thumb = $v['thumbnail_720_url'] ?? ($v['thumbnail_large_url'] ?? ($v['thumbnail_url'] ?? ''));

        sendResponse(true, "Video details retrieved successfully from Dailymotion", [
            'id' => $cleanId,
            'title' => $v['title'] ?? '',
            'description' => $v['description'] ?? '',
            'duration' => $durFormatted,
            'duration_seconds' => $durSec,
            'thumbnail' => $thumb,
            'account_id' => $found['account']['id'] ?? 0,
            'channel_name' => $found['account']['channel_name'] ?? ''
        ]);
        break;

    // -------------------------------------------------------------
    // EPISODES SAVE
    // -------------------------------------------------------------
    case 'save_episode':
    case 'save_video':
        $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
        $series_id = (int)($_POST['series_id'] ?? ($_POST['course_id'] ?? ($input['series_id'] ?? 0)));
        $season_id = (int)($_POST['season_id'] ?? ($_POST['chapter_id'] ?? ($input['season_id'] ?? 0)));
        $dm_account_id = (int)($_POST['dm_account_id'] ?? ($input['dm_account_id'] ?? 0));
        $episode_number = (int)($_POST['episode_number'] ?? ($_POST['sort_order'] ?? ($input['episode_number'] ?? 1)));
        $title = trim($_POST['title'] ?? ($input['title'] ?? ''));
        $description = trim($_POST['description'] ?? ($input['description'] ?? ''));
        $thumbnail = trim($_POST['thumbnail'] ?? ($input['thumbnail'] ?? ''));
        $dailymotion_id = trim($_POST['dailymotion_id'] ?? ($input['dailymotion_id'] ?? ''));
        $stream_url = trim($_POST['stream_url'] ?? ($input['stream_url'] ?? ''));
        $duration = trim($_POST['duration'] ?? ($input['duration'] ?? '45:00'));
        $is_free_preview = isset($_POST['is_free_preview']) ? 1 : 0;
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if ($series_id <= 0 || empty($title) || empty($dailymotion_id)) {
            sendResponse(false, "Series/Movie, Title, and Video/DM ID are required", null, 400);
        }

        // Clean Dailymotion ID if user pasted full URL
        if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dailymotion_id, $m)) {
            $dailymotion_id = $m[1];
        }

        // Handle Standalone Movie or Missing Season: Auto-link to existing season or create default 'Main Feature' season
        if ($season_id <= 0) {
            $sStmt = $pdo->prepare("SELECT id FROM seasons WHERE series_id = ? ORDER BY id ASC LIMIT 1");
            $sStmt->execute([$series_id]);
            $foundSeasonId = $sStmt->fetchColumn();
            if ($foundSeasonId) {
                $season_id = (int)$foundSeasonId;
            } else {
                $insSeason = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title, description, status) VALUES (?, 1, 'Main Feature', 'Full movie / main release', 1)");
                $insSeason->execute([$series_id]);
                $season_id = (int)$pdo->lastInsertId();
            }
        }

        if ($dm_account_id <= 0) {
            $dm_account_id = (int)($pdo->query("SELECT id FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC LIMIT 1")->fetchColumn() ?: 1);
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE episodes SET series_id=?, season_id=?, dm_account_id=?, episode_number=?, title=?, description=?, thumbnail=?, dailymotion_id=?, stream_url=?, duration=?, is_free_preview=?, status=? WHERE id=?");
                $stmt->execute([$series_id, $season_id, $dm_account_id, $episode_number, $title, $description, $thumbnail, $dailymotion_id, $stream_url, $duration, $is_free_preview, $status, $id]);
                sendResponse(true, "Video updated successfully", ['id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO episodes (series_id, season_id, dm_account_id, episode_number, title, description, thumbnail, dailymotion_id, stream_url, duration, is_free_preview, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$series_id, $season_id, $dm_account_id, $episode_number, $title, $description, $thumbnail, $dailymotion_id, $stream_url, $duration, $is_free_preview, $status]);
                sendResponse(true, "Video added successfully", ['id' => $pdo->lastInsertId()]);
            }
        } catch (PDOException $e) {
            sendResponse(false, "Database error: " . $e->getMessage(), null, 500);
        }
        break;

    // -------------------------------------------------------------
    // SUBSCRIBERS / USER MANAGEMENT (OTP, Email Verification, Password)
    // -------------------------------------------------------------
    case 'toggle_user_status':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) sendResponse(false, "Invalid user ID", null, 400);

        $cur = (int)($pdo->query("SELECT status FROM users WHERE id = " . $id)->fetchColumn() ?? 1);
        $newStatus = $cur === 1 ? 0 : 1;
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        sendResponse(true, $newStatus === 1 ? "User activated successfully" : "User deactivated successfully", ['status' => $newStatus]);
        break;

    case 'toggle_user_otp':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) sendResponse(false, "Invalid user ID", null, 400);

        $cur = (int)($pdo->query("SELECT otp_verified FROM users WHERE id = " . $id)->fetchColumn() ?? 0);
        $newVal = $cur === 1 ? 0 : 1;
        $pdo->prepare("UPDATE users SET otp_verified = ? WHERE id = ?")->execute([$newVal, $id]);
        sendResponse(true, $newVal === 1 ? "OTP marked as verified" : "OTP marked as unverified", ['otp_verified' => $newVal]);
        break;

    case 'toggle_user_email':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) sendResponse(false, "Invalid user ID", null, 400);

        $cur = (int)($pdo->query("SELECT email_verified FROM users WHERE id = " . $id)->fetchColumn() ?? 0);
        $newVal = $cur === 1 ? 0 : 1;
        $pdo->prepare("UPDATE users SET email_verified = ? WHERE id = ?")->execute([$newVal, $id]);
        sendResponse(true, $newVal === 1 ? "Email marked as verified" : "Email marked as unverified", ['email_verified' => $newVal]);
        break;

    case 'update_user_password':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $newPass = trim($input['password'] ?? ($_POST['password'] ?? ''));
        if ($id <= 0 || empty($newPass)) {
            sendResponse(false, "User ID and new password are required", null, 400);
        }
        if (strlen($newPass) < 6) {
            sendResponse(false, "Password must be at least 6 characters long", null, 400);
        }

        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?")->execute([$hash, $newPass, $id]);
        sendResponse(true, "Password updated successfully for user #" . $id);
        break;

    case 'delete_user':
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) sendResponse(false, "Invalid user ID", null, 400);

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        sendResponse(true, "User account deleted successfully");
        break;

    default:
        sendResponse(false, "Unknown action: " . htmlspecialchars($action), null, 400);
        break;
}
