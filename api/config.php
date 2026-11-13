<?php
// backend/api/config.php - Cinemas & Web Series Streaming Engine
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db_dir = __DIR__ . '/../database';
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0777, true);
}

$db_file = $db_dir . '/cinemas.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 30
    ]);

    $pdo->exec("PRAGMA foreign_keys = ON;");
    $pdo->exec("PRAGMA journal_mode = WAL;");

    initDatabase($pdo);

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => false,
        'message' => 'SQLite DB connection error: ' . $e->getMessage()
    ]);
    exit();
}

function initDatabase($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            name TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            phone TEXT,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS genres (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            icon TEXT DEFAULT 'bi-film',
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS dm_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            channel_name TEXT,
            uid TEXT,
            email TEXT,
            client_id TEXT NOT NULL,
            client_secret TEXT NOT NULL,
            refresh_token TEXT NOT NULL,
            access_token TEXT,
            expires_at INTEGER DEFAULT 0,
            is_default INTEGER DEFAULT 0,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Media Catalog (Movies and Web Series)
        CREATE TABLE IF NOT EXISTS media_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL DEFAULT 'movie', -- 'movie' or 'series'
            genre_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            poster TEXT,            -- Portrait poster
            backdrop TEXT,          -- Landscape banner / cover
            release_year TEXT,      -- e.g. '2024'
            duration TEXT,          -- e.g. '2h 15m' for movie, or '3 Seasons' for series
            rating TEXT DEFAULT '8.5',
            language TEXT DEFAULT 'Hindi',
            dailymotion_id TEXT,    -- For standalone movie stream
            stream_url TEXT,        -- Direct MP4/HLS/M3U8 (optional)
            is_featured INTEGER DEFAULT 0,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL
        );

        -- Seasons for Series (Parent: media_items where type='series')
        CREATE TABLE IF NOT EXISTS seasons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            series_id INTEGER NOT NULL,
            season_number INTEGER NOT NULL DEFAULT 1,
            title TEXT NOT NULL,
            description TEXT,
            poster TEXT,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (series_id) REFERENCES media_items(id) ON DELETE CASCADE
        );

        -- Episodes (Parent: seasons)
        CREATE TABLE IF NOT EXISTS episodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            series_id INTEGER NOT NULL,
            season_id INTEGER NOT NULL,
            dm_account_id INTEGER DEFAULT 0,
            episode_number INTEGER NOT NULL DEFAULT 1,
            title TEXT NOT NULL,
            description TEXT,
            thumbnail TEXT,
            dailymotion_id TEXT NOT NULL,
            stream_url TEXT,
            duration TEXT DEFAULT '45:00',
            is_free_preview INTEGER DEFAULT 0,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (series_id) REFERENCES media_items(id) ON DELETE CASCADE,
            FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
        );

        -- Legacy Views for backwards-compatibility
        CREATE VIEW IF NOT EXISTS categories AS SELECT id, title, icon, status, created_at FROM genres;
        CREATE VIEW IF NOT EXISTS courses AS SELECT id, genre_id as category_id, title, description, poster as thumbnail, 0.0 as price, status, created_at FROM media_items;
        CREATE VIEW IF NOT EXISTS chapters AS SELECT id, series_id as course_id, title, description, season_number as sort_order, status, created_at FROM seasons;
        CREATE VIEW IF NOT EXISTS videos AS SELECT id, series_id as course_id, season_id as chapter_id, dm_account_id, title, description, dailymotion_id, duration, episode_number as sort_order, is_free_preview, status, created_at FROM episodes;
    ");

    // Safe schema migrations for existing database without losing data
    try { $pdo->exec("ALTER TABLE users ADD COLUMN plain_password TEXT;"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_verified INTEGER DEFAULT 0;"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN email_verified INTEGER DEFAULT 0;"); } catch (Exception $e) {}

    // Insert Default Admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO admins (username, password, name) VALUES ('admin', ?, 'Cinema Master Admin')")->execute([$hash]);
    }

    // Seed default Dailymotion account if empty
    try {
        $dmCount = $pdo->query("SELECT COUNT(*) FROM dm_accounts")->fetchColumn();
        if ($dmCount == 0) {
            $pdo->prepare("INSERT INTO dm_accounts (id, name, channel_name, uid, email, client_id, client_secret, refresh_token, is_default, status) 
                           VALUES (1, 'Cinemas Master Streamer', 'neniyo7267', 'x6mtvoi', 'neniyo7267@artegin.com', '20339e78eaa0487f12ea', 'd416efc0eddc6d29e1900833176cbddb5373a1e3', 'd84f059ff876d79d93e091c2ffab5fc390a7dce7', 1, 1)")->execute();
        }
    } catch (Exception $e) {}
}

function sendResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

function requireAdminAuth($pdo) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Accept either admin_logged session or admin_id
    if (!isset($_SESSION['admin_logged']) && !isset($_SESSION['admin_id'])) {
        sendResponse(false, 'Unauthorized. Please login.', null, 401);
    }
}

function httpPostDailymotion($url, $postFields, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function httpGetDailymotion($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

/**
 * Refresh an individual Dailymotion account's access token using its refresh_token.
 */
function refreshDmAccount($pdo, &$account, $forceRefresh = false) {
    if (!$account || empty($account['refresh_token'])) {
        return null;
    }

    $now = time();
    $expiresAt = (int)($account['expires_at'] ?? 0);
    $needsRefresh = $forceRefresh || ($expiresAt === 0) || ($now >= ($expiresAt - 300));

    if (!$needsRefresh && !empty($account['access_token'])) {
        return $account['access_token'];
    }

    $postData = http_build_query([
        'grant_type' => 'refresh_token',
        'client_id' => $account['client_id'],
        'client_secret' => $account['client_secret'],
        'refresh_token' => $account['refresh_token']
    ]);

    $res = httpPostDailymotion('https://api.dailymotion.com/oauth/token', $postData);

    if ($res) {
        $newTokens = json_decode($res, true);
        if (!empty($newTokens['access_token'])) {
            $account['access_token'] = $newTokens['access_token'];
            if (!empty($newTokens['refresh_token'])) {
                $account['refresh_token'] = $newTokens['refresh_token'];
            }
            $expiresIn = (int)($newTokens['expires_in'] ?? 36000);
            $account['expires_at'] = $now + $expiresIn;

            if (isset($account['id']) && $pdo) {
                try {
                    $up = $pdo->prepare("UPDATE dm_accounts SET access_token = ?, refresh_token = ?, expires_at = ? WHERE id = ?");
                    $up->execute([$account['access_token'], $account['refresh_token'], $account['expires_at'], $account['id']]);
                } catch (Exception $e) {}
            }

            if (!empty($account['is_default'])) {
                $tokenFile = __DIR__ . '/../database/dm_user_token.json';
                @file_put_contents($tokenFile, json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return $account['access_token'];
        }
    }

    return $account['access_token'] ?? null;
}

/**
 * Get access token for a specific account or default account
 */
function getDmAccountToken($pdo, $accountId = null, $forceRefresh = false) {
    if (!$pdo) return null;

    try {
        if ($accountId && $accountId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM dm_accounts WHERE id = ? AND status = 1");
            $stmt->execute([$accountId]);
            $acc = $stmt->fetch();
            if ($acc) {
                return refreshDmAccount($pdo, $acc, $forceRefresh);
            }
        }

        // Get default or first active account
        $stmt = $pdo->query("SELECT * FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
        $acc = $stmt ? $stmt->fetch() : null;
        if ($acc) {
            return refreshDmAccount($pdo, $acc, $forceRefresh);
        }
    } catch (Exception $e) {}

    return null;
}

/**
 * Query Dailymotion across connected accounts for video details (title, duration, thumbnail, embed_url, etc.)
 */
function findDmAccountForVideo($pdo, $videoId, $preferredAccountId = 0) {
    $cleanId = $videoId;
    if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $videoId, $m)) {
        $cleanId = $m[1];
    }

    $accounts = [];
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT * FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC");
            $accounts = $stmt->fetchAll();
        } catch (Exception $e) {}
    }

    if ($preferredAccountId > 0) {
        usort($accounts, function($a, $b) use ($preferredAccountId) {
            if ($a['id'] == $preferredAccountId) return -1;
            if ($b['id'] == $preferredAccountId) return 1;
            return 0;
        });
    }

    $queryOne = function($vid, $token) {
        $url = "https://api.dailymotion.com/video/" . $vid . "?fields=id,title,description,duration,thumbnail_720_url,thumbnail_large_url,thumbnail_url,private,embed_url";
        $headers = $token ? ["Authorization: Bearer " . $token] : [];
        $res = httpGetDailymotion($url, $headers);
        return $res ? json_decode($res, true) : null;
    };

    // Try each account in pool
    foreach ($accounts as $acc) {
        $token = refreshDmAccount($pdo, $acc);
        $vJson = $queryOne($cleanId, $token);
        if ($vJson && isset($vJson['id']) && !isset($vJson['error'])) {
            return [
                'account' => $acc,
                'video' => $vJson,
                'token' => $token
            ];
        }
    }

    // Fallback: try unauthenticated (public video)
    $pubJson = $queryOne($cleanId, null);
    if ($pubJson && isset($pubJson['id']) && !isset($pubJson['error'])) {
        return [
            'account' => null,
            'video' => $pubJson,
            'token' => null
        ];
    }

    return null;
}

function getAppStats($pdo) {
    try {
        $genres = $pdo->query("SELECT COUNT(*) FROM genres WHERE status = 1")->fetchColumn();
        $movies = $pdo->query("SELECT COUNT(*) FROM media_items WHERE type = 'movie' AND status = 1")->fetchColumn();
        $series = $pdo->query("SELECT COUNT(*) FROM media_items WHERE type = 'series' AND status = 1")->fetchColumn();
        $episodes = $pdo->query("SELECT COUNT(*) FROM episodes WHERE status = 1")->fetchColumn();
        $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $dmAccounts = $pdo->query("SELECT COUNT(*) FROM dm_accounts WHERE status = 1")->fetchColumn();

        return [
            'genres' => (int)$genres,
            'movies' => (int)$movies,
            'series' => (int)$series,
            'episodes' => (int)$episodes,
            'users' => (int)$users,
            'dm_accounts' => (int)$dmAccounts,
            // Backwards compatibility for templates
            'courses' => (int)($movies + $series),
            'chapters' => (int)$series,
            'videos' => (int)($movies + $episodes)
        ];
    } catch (Exception $e) {
        return ['genres' => 0, 'movies' => 0, 'series' => 0, 'episodes' => 0, 'users' => 0, 'dm_accounts' => 0, 'courses' => 0, 'chapters' => 0, 'videos' => 0];
    }
}
