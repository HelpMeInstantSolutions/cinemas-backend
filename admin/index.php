<?php
// backend/admin/index.php
$pageTitle = 'Cinemas Dashboard';
$activePage = 'dashboard';
$headerTitle = 'Cinemas & Series Portal';
$headerSubtitle = 'Control center for movies, multi-season web series, episodes, and private Dailymotion streaming';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch recent episodes
$recentEpisodes = [];
try {
    $stmt = $pdo->query("SELECT e.*, m.title AS series_title, s.title AS season_title 
                         FROM episodes e 
                         LEFT JOIN media_items m ON e.series_id = m.id 
                         LEFT JOIN seasons s ON e.season_id = s.id 
                         ORDER BY e.id DESC LIMIT 6");
    $recentEpisodes = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch recent movies & series
$recentMedia = [];
try {
    $stmt = $pdo->query("SELECT m.*, g.title as genre_name,
                                (SELECT COUNT(*) FROM seasons s WHERE s.series_id = m.id) AS season_count,
                                (SELECT COUNT(*) FROM episodes e WHERE e.series_id = m.id) AS episode_count
                         FROM media_items m 
                         LEFT JOIN genres g ON m.genre_id = g.id
                         ORDER BY m.id DESC LIMIT 6");
    $recentMedia = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-950 text-slate-100 relative">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-8 max-w-7xl">
        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Card 1: Movies -->
            <a href="/admin/movies/" class="bg-slate-900 rounded-2xl p-5 border border-slate-800 shadow-sm hover:border-rose-500/50 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Movies</span>
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-500 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="film" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white"><?= $serverStats['movies'] ?? 0 ?></span>
                    <span class="text-xs font-semibold text-rose-400 flex items-center gap-0.5">
                        Manage <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>

            <!-- Card 2: Web Series -->
            <a href="/admin/movies/" class="bg-slate-900 rounded-2xl p-5 border border-slate-800 shadow-sm hover:border-amber-500/50 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Web Series</span>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="tv" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white"><?= $serverStats['series'] ?? 0 ?></span>
                    <span class="text-xs font-semibold text-amber-400 flex items-center gap-0.5">
                        Shows <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>

            <!-- Card 3: Episodes -->
            <a href="/admin/episodes/" class="bg-slate-900 rounded-2xl p-5 border border-slate-800 shadow-sm hover:border-emerald-500/50 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Episodes</span>
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="play-square" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white"><?= $serverStats['episodes'] ?? 0 ?></span>
                    <span class="text-xs font-semibold text-emerald-400 flex items-center gap-0.5">
                        Streams <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>

            <!-- Card 4: DM Accounts -->
            <a href="/admin/dm_accounts/" class="bg-slate-900 rounded-2xl p-5 border border-slate-800 shadow-sm hover:border-sky-500/50 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">DM Accounts</span>
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="cloud" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white"><?= $serverStats['dm_accounts'] ?? 0 ?></span>
                    <span class="text-xs font-semibold text-sky-400 flex items-center gap-0.5">
                        Cloud API <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>

            <!-- Card 5: Users -->
            <a href="/admin/users/" class="bg-slate-900 rounded-2xl p-5 border border-slate-800 shadow-sm hover:border-purple-500/50 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Subscribers</span>
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white"><?= $serverStats['users'] ?? 0 ?></span>
                    <span class="text-xs font-semibold text-purple-400 flex items-center gap-0.5">
                        Audience <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>
        </div>

        <!-- Recent Titles Section -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-white">Featured Movies &amp; Series</h3>
                    <p class="text-xs text-slate-400">Latest media entries available on the mobile application</p>
                </div>
                <a href="/admin/movies/?new=1" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-rose-600/30">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add Title
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($recentMedia as $m): ?>
                    <div class="bg-slate-950 border border-slate-800 rounded-xl overflow-hidden flex flex-col">
                        <div class="h-44 bg-slate-900 relative">
                            <?php if (!empty($m['backdrop']) || !empty($m['poster'])): ?>
                                <img src="<?= htmlspecialchars($m['backdrop'] ?: $m['poster']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-slate-700">
                                    <i data-lucide="image" class="w-8 h-8"></i>
                                </div>
                            <?php endif; ?>
                            <span class="absolute top-3 left-3 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $m['type'] === 'series' ? 'bg-amber-500 text-slate-950' : 'bg-rose-600 text-white' ?>">
                                <?= strtoupper($m['type']) ?>
                            </span>
                            <span class="absolute top-3 right-3 px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-900/80 backdrop-blur-md text-amber-400">
                                ★ <?= htmlspecialchars($m['rating'] ?? '8.5') ?>
                            </span>
                        </div>
                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="font-bold text-white text-sm line-clamp-1"><?= htmlspecialchars($m['title']) ?></h4>
                                <p class="text-xs text-slate-400 mt-1 line-clamp-2"><?= htmlspecialchars($m['description']) ?></p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
                                <span><?= htmlspecialchars($m['genre_name'] ?? 'General') ?> • <?= htmlspecialchars($m['release_year'] ?? '2024') ?></span>
                                <span class="font-medium text-slate-300">
                                    <?= $m['type'] === 'series' ? $m['season_count'] . ' Seasons' : ($m['duration'] ?? '2h') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

