<?php
// backend/api/admin_crud.php - Admin CRUD Handler for Movies, Series, Seasons & Episodes
require_once __DIR__ . '/config.php';
requireAdminAuth($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // -------------------------------------------------------------
    // MOVIES / SERIES CRUD
    // -------------------------------------------------------------
    case 'save_media':
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] === 'series' ? 'series' : 'movie';
        $genre_id = (int)($_POST['genre_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $poster = trim($_POST['poster'] ?? '');
        $backdrop = trim($_POST['backdrop'] ?? '');
        $release_year = trim($_POST['release_year'] ?? date('Y'));
        $duration = trim($_POST['duration'] ?? '');
        $rating = trim($_POST['rating'] ?? '8.5');
        $language = trim($_POST['language'] ?? 'Hindi');
        $dailymotion_id = trim($_POST['dailymotion_id'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if (empty($title)) {
            sendResponse(false, "Title is required", null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE media_items SET type=?, genre_id=?, title=?, description=?, poster=?, backdrop=?, release_year=?, duration=?, rating=?, language=?, dailymotion_id=?, stream_url=?, is_featured=?, status=? WHERE id=?");
            $stmt->execute([$type, $genre_id, $title, $description, $poster, $backdrop, $release_year, $duration, $rating, $language, $dailymotion_id, $stream_url, $is_featured, $status, $id]);
            sendResponse(true, ucfirst($type) . " updated successfully", ['id' => $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO media_items (type, genre_id, title, description, poster, backdrop, release_year, duration, rating, language, dailymotion_id, stream_url, is_featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $genre_id, $title, $description, $poster, $backdrop, $release_year, $duration, $rating, $language, $dailymotion_id, $stream_url, $is_featured, $status]);
            sendResponse(true, ucfirst($type) . " added successfully", ['id' => $pdo->lastInsertId()]);
        }
        break;

    case 'delete_media':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) sendResponse(false, "Invalid ID", null, 400);

        $pdo->prepare("DELETE FROM media_items WHERE id = ?")->execute([$id]);
        sendResponse(true, "Title and associated content deleted successfully");
        break;

    // -------------------------------------------------------------
    // SEASONS CRUD (FOR SERIES)
    // -------------------------------------------------------------
    case 'save_season':
        $id = (int)($_POST['id'] ?? 0);
        $series_id = (int)($_POST['series_id'] ?? 0);
        $season_number = (int)($_POST['season_number'] ?? 1);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $poster = trim($_POST['poster'] ?? '');
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

    case 'delete_season':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) sendResponse(false, "Invalid season ID", null, 400);
        $pdo->prepare("DELETE FROM seasons WHERE id = ?")->execute([$id]);
        sendResponse(true, "Season and all its episodes deleted successfully");
        break;

    // -------------------------------------------------------------
    // EPISODES CRUD
    // -------------------------------------------------------------
    case 'save_episode':
        $id = (int)($_POST['id'] ?? 0);
        $series_id = (int)($_POST['series_id'] ?? 0);
        $season_id = (int)($_POST['season_id'] ?? 0);
        $dm_account_id = (int)($_POST['dm_account_id'] ?? 0);
        $episode_number = (int)($_POST['episode_number'] ?? 1);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $dailymotion_id = trim($_POST['dailymotion_id'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $duration = trim($_POST['duration'] ?? '45:00');
        $is_free_preview = isset($_POST['is_free_preview']) ? 1 : 0;
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if ($series_id <= 0 || $season_id <= 0 || empty($title) || empty($dailymotion_id)) {
            sendResponse(false, "Series, Season, Title, and Video/DM ID are required", null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE episodes SET series_id=?, season_id=?, dm_account_id=?, episode_number=?, title=?, description=?, thumbnail=?, dailymotion_id=?, stream_url=?, duration=?, is_free_preview=?, status=? WHERE id=?");
            $stmt->execute([$series_id, $season_id, $dm_account_id, $episode_number, $title, $description, $thumbnail, $dailymotion_id, $stream_url, $duration, $is_free_preview, $status, $id]);
            sendResponse(true, "Episode updated successfully", ['id' => $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO episodes (series_id, season_id, dm_account_id, episode_number, title, description, thumbnail, dailymotion_id, stream_url, duration, is_free_preview, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$series_id, $season_id, $dm_account_id, $episode_number, $title, $description, $thumbnail, $dailymotion_id, $stream_url, $duration, $is_free_preview, $status]);
            sendResponse(true, "Episode added successfully", ['id' => $pdo->lastInsertId()]);
        }
        break;

    case 'delete_episode':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) sendResponse(false, "Invalid episode ID", null, 400);
        $pdo->prepare("DELETE FROM episodes WHERE id = ?")->execute([$id]);
        sendResponse(true, "Episode deleted successfully");
        break;

    // -------------------------------------------------------------
    // GENRES CRUD
    // -------------------------------------------------------------
    case 'save_genre':
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-film');
        if (empty($title)) sendResponse(false, "Genre title is required", null, 400);

        if ($id > 0) {
            $pdo->prepare("UPDATE genres SET title=?, icon=? WHERE id=?")->execute([$title, $icon, $id]);
            sendResponse(true, "Genre updated", ['id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO genres (title, icon) VALUES (?, ?)")->execute([$title, $icon]);
            sendResponse(true, "Genre created", ['id' => $pdo->lastInsertId()]);
        }
        break;

    case 'delete_genre':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM genres WHERE id=?")->execute([$id]);
        sendResponse(true, "Genre removed");
        break;

    default:
        sendResponse(false, "Unknown action", null, 400);
        break;
}
