<?php
// backend/api/courses.php - Universal Movies & Series API (Dual compatibility: Modern Cinema API + Legacy CourseApp)
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
    case 'movies':
    case 'series':
        $type = $_GET['type'] ?? '';
        if ($action === 'movies') $type = 'movie';
        if ($action === 'series') $type = 'series';

        $genreId = isset($_GET['genre_id']) ? (int)$_GET['genre_id'] : (int)($_GET['category_id'] ?? 0);
        $search = trim($_GET['search'] ?? '');
        $featured = isset($_GET['featured']) ? (int)$_GET['featured'] : 0;

        $sql = "SELECT m.*, g.title as genre_name, g.title as category_name,
                       (SELECT COUNT(*) FROM seasons s WHERE s.series_id = m.id AND s.status = 1) as total_seasons,
                       (SELECT COUNT(*) FROM episodes e WHERE e.series_id = m.id AND e.status = 1) as total_episodes,
                       -- Legacy fields for courseapp compatibility:
                       (SELECT COUNT(*) FROM seasons s WHERE s.series_id = m.id AND s.status = 1) as total_chapters,
                       (SELECT COUNT(*) FROM episodes e WHERE e.series_id = m.id AND e.status = 1) as total_videos,
                       m.poster as thumbnail,
                       0.0 as price
                FROM media_items m
                LEFT JOIN genres g ON m.genre_id = g.id
                WHERE m.status = 1";

        $params = [];
        if (!empty($type)) {
            $sql .= " AND m.type = ?";
            $params[] = $type;
        }
        if ($genreId > 0) {
            $sql .= " AND m.genre_id = ?";
            $params[] = $genreId;
        }
        if ($featured > 0) {
            $sql .= " AND m.is_featured = 1";
        }
        if (!empty($search)) {
            $sql .= " AND (m.title LIKE ? OR m.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY m.is_featured DESC, m.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Also fetch genres for filter tabs
        $genres = $pdo->query("SELECT id, title, icon FROM genres WHERE status = 1 ORDER BY id ASC")->fetchAll();

        // Send response (compatible with both Course list and Cinema list)
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => true,
            'message' => 'Media catalog retrieved successfully',
            'data' => $items,
            'genres' => $genres
        ]);
        exit();
        break;

    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            sendResponse(false, "Invalid Media ID", null, 400);
        }

        $stmt = $pdo->prepare("SELECT m.*, g.title as genre_name, g.title as category_name,
                                      m.poster as thumbnail,
                                      0.0 as price,
                                      (SELECT COUNT(*) FROM seasons s WHERE s.series_id = m.id AND s.status = 1) as total_seasons,
                                      (SELECT COUNT(*) FROM episodes e WHERE e.series_id = m.id AND e.status = 1) as total_episodes,
                                      (SELECT COUNT(*) FROM seasons s WHERE s.series_id = m.id AND s.status = 1) as total_chapters,
                                      (SELECT COUNT(*) FROM episodes e WHERE e.series_id = m.id AND e.status = 1) as total_videos
                               FROM media_items m
                               LEFT JOIN genres g ON m.genre_id = g.id
                               WHERE m.id = ?");
        $stmt->execute([$id]);
        $media = $stmt->fetch();

        if (!$media) {
            sendResponse(false, "Title not found", null, 404);
        }

        // If it's a series, fetch seasons and episodes
        if ($media['type'] === 'series') {
            $sStmt = $pdo->prepare("SELECT id, series_id, season_number, title, description, poster 
                                    FROM seasons 
                                    WHERE series_id = ? AND status = 1 
                                    ORDER BY season_number ASC, id ASC");
            $sStmt->execute([$id]);
            $seasons = $sStmt->fetchAll();

            $epStmt = $pdo->prepare("SELECT id, series_id, season_id, episode_number, title, description, thumbnail, dailymotion_id, duration, is_free_preview 
                                     FROM episodes 
                                     WHERE series_id = ? AND status = 1 
                                     ORDER BY episode_number ASC, id ASC");
            $epStmt->execute([$id]);
            $episodes = $epStmt->fetchAll();

            $epBySeason = [];
            foreach ($episodes as $ep) {
                $ep['chapter_id'] = $ep['season_id'];
                $ep['course_id'] = $ep['series_id'];
                $ep['sort_order'] = $ep['episode_number'];
                $epBySeason[$ep['season_id']][] = $ep;
            }

            $groupedSeasons = [];
            foreach ($seasons as $season) {
                $sId = (int)$season['id'];
                $seasonList = $epBySeason[$sId] ?? [];
                $season['total_episodes'] = count($seasonList);
                $season['episodes'] = $seasonList;
                // Backwards compatibility for ChaptersActivity & PlayerActivity
                $season['course_id'] = $id;
                $season['sort_order'] = $season['season_number'];
                $season['videos'] = $seasonList;
                $season['total_videos'] = count($seasonList);
                $groupedSeasons[] = $season;
            }

            $media['seasons'] = $groupedSeasons;
            $media['chapters'] = $groupedSeasons; // For Kotlin ChapterAdapter
        } else {
            // Standalone Movie: Check if multiple stream links/parts exist in episodes table
            $epStmt = $pdo->prepare("SELECT id, series_id, season_id, episode_number, title, description, thumbnail, dailymotion_id, duration, is_free_preview 
                                     FROM episodes 
                                     WHERE series_id = ? AND status = 1 
                                     ORDER BY episode_number ASC, id ASC");
            $epStmt->execute([$id]);
            $parts = $epStmt->fetchAll();

            $movieEpisodes = [];
            if (!empty($parts)) {
                foreach ($parts as $p) {
                    $p['course_id'] = $p['series_id'];
                    $p['chapter_id'] = 1;
                    $p['sort_order'] = $p['episode_number'];
                    if (empty($p['thumbnail'])) $p['thumbnail'] = $media['poster'];
                    $movieEpisodes[] = $p;
                }
            } else if (!empty($media['dailymotion_id'])) {
                $movieEpisodes[] = [
                    'id' => $media['id'],
                    'course_id' => $media['id'],
                    'chapter_id' => 1,
                    'series_id' => $media['id'],
                    'season_id' => 1,
                    'episode_number' => 1,
                    'sort_order' => 1,
                    'title' => $media['title'] . ' (Full Movie)',
                    'description' => $media['description'],
                    'dailymotion_id' => $media['dailymotion_id'],
                    'stream_url' => $media['stream_url'],
                    'thumbnail' => $media['poster'],
                    'duration' => $media['duration'],
                    'is_free_preview' => 1
                ];
            }

            $movieSeason = [
                'id' => 1,
                'series_id' => $media['id'],
                'course_id' => $media['id'],
                'season_number' => 1,
                'sort_order' => 1,
                'title' => 'Main Feature',
                'description' => 'Full Length Movie',
                'poster' => $media['poster'],
                'total_episodes' => count($movieEpisodes),
                'total_videos' => count($movieEpisodes),
                'episodes' => $movieEpisodes,
                'videos' => $movieEpisodes
            ];

            $media['seasons'] = [$movieSeason];
            $media['chapters'] = [$movieSeason];
        }

        sendResponse(true, "Media details loaded successfully", $media);
        break;

    case 'genres':
    case 'categories':
        $genres = $pdo->query("SELECT * FROM genres WHERE status = 1 ORDER BY id ASC")->fetchAll();
        sendResponse(true, "Genres retrieved successfully", $genres);
        break;

    default:
        sendResponse(false, "Invalid action specified", null, 400);
        break;
}
