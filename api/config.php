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

    // Insert Default Admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO admins (username, password, name) VALUES ('admin', ?, 'Cinema Master Admin')")->execute([$hash]);
    }

    // Insert Default Genres
    $gCount = $pdo->query("SELECT COUNT(*) FROM genres")->fetchColumn();
    if ($gCount == 0) {
        $genres = [
            ['Action & Thriller', 'bi-lightning-charge'],
            ['Bollywood Blockbusters', 'bi-camera-reels'],
            ['Hollywood Hindi Dubbed', 'bi-globe-americas'],
            ['Web Series & Crime', 'bi-tv'],
            ['Comedy & Family', 'bi-emoji-smile'],
            ['Sci-Fi & Fantasy', 'bi-stars']
        ];
        $gStmt = $pdo->prepare("INSERT INTO genres (title, icon) VALUES (?, ?)");
        foreach ($genres as $g) {
            $gStmt->execute([$g[0], $g[1]]);
        }
    }

    // Insert Sample Movie and Web Series if empty
    $mCount = $pdo->query("SELECT COUNT(*) FROM media_items")->fetchColumn();
    if ($mCount == 0) {
        // Sample Movie
        $insMovie = $pdo->prepare("INSERT INTO media_items (type, genre_id, title, description, poster, backdrop, release_year, duration, rating, language, dailymotion_id, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insMovie->execute([
            'movie',
            1,
            'Jawan: Extended Cut',
            'A high-octane action thriller detailing an emotional journey of a man who is set to rectify the wrongs in the society.',
            'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=600&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1574267432553-4b4628081c31?w=1200&auto=format&fit=crop&q=80',
            '2023',
            '2h 45m',
            '8.8',
            'Hindi',
            'x8n0001',
            1
        ]);

        // Sample Web Series
        $insMovie->execute([
            'series',
            4,
            'Mirzapur: Season of Vengeance',
            'A shocking incident at a wedding procession ignites a series of events entangling the lives of two families in the lawless city of Mirzapur.',
            'https://images.unsplash.com/photo-1616530940355-351fabd9524b?w=600&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=1200&auto=format&fit=crop&q=80',
            '2024',
            '3 Seasons',
            '9.1',
            'Hindi',
            '',
            1
        ]);
        $seriesId = $pdo->lastInsertId();

        // Sample Seasons
        $insSeason = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title, description, poster) VALUES (?, ?, ?, ?, ?)");
        $insSeason->execute([$seriesId, 1, 'Season 1: Rule of Akhandanand', 'The rise of Kaleen Bhaiya and the conflict with Guddu & Bablu.', 'https://images.unsplash.com/photo-1616530940355-351fabd9524b?w=600&auto=format&fit=crop&q=80']);
        $season1Id = $pdo->lastInsertId();

        $insSeason->execute([$seriesId, 2, 'Season 2: Retribution', 'Blood boils and empires clash for total control of Purvanchal.', 'https://images.unsplash.com/photo-1616530940355-351fabd9524b?w=600&auto=format&fit=crop&q=80']);
        $season2Id = $pdo->lastInsertId();

        // Sample Episodes
        $insEp = $pdo->prepare("INSERT INTO episodes (series_id, season_id, episode_number, title, description, thumbnail, dailymotion_id, duration, is_free_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insEp->execute([$seriesId, $season1Id, 1, 'Episode 1: Bhaukaal', 'Akhandanand Tripathi is a carpet exporter and the mafia don of Mirzapur.', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=600&auto=format&fit=crop&q=80', 'x8n0002', '48:10', 1]);
        $insEp->execute([$seriesId, $season1Id, 2, 'Episode 2: Gaddi', 'Guddu and Bablu must decide where their true loyalty lies.', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=600&auto=format&fit=crop&q=80', 'x8n0003', '52:15', 0]);
        $insEp->execute([$seriesId, $season2Id, 1, 'Episode 1: Dhenkul', 'The war begins anew as surviving factions regroup for vengeance.', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=600&auto=format&fit=crop&q=80', 'x8n0004', '56:00', 1]);
    }
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
    if (!isset($_SESSION['admin_id'])) {
        sendResponse(false, 'Unauthorized. Please login.', null, 401);
    }
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
