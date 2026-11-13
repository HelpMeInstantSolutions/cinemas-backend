<?php
// backend/admin/movies/index.php
$pageTitle = 'Movies & Web Series';
$activePage = 'movies';
$headerTitle = 'Movies & Series Catalog';
$headerSubtitle = 'Create, organize, and manage blockbuster movies, multi-part film links, and web series';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Fetch All Movies & Series with season & episode counts
$mediaItems = [];
try {
    $stmt = $pdo->query("SELECT m.*, 
                         (SELECT COUNT(*) FROM seasons s WHERE s.series_id = m.id) AS season_count,
                         (SELECT COUNT(*) FROM episodes e WHERE e.series_id = m.id) AS episode_count,
                         g.title AS genre_title
                         FROM media_items m 
                         LEFT JOIN genres g ON m.genre_id = g.id 
                         ORDER BY m.id DESC");
    $mediaItems = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch genres for dropdown
$genres = [];
try {
    $genres = $pdo->query("SELECT * FROM genres ORDER BY title ASC")->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Toolbar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="relative flex-1 max-w-md w-full">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="movieSearch" oninput="filterMovies()" 
                       placeholder="Search movies & series by title or genre..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-500 focus:bg-white transition">
            </div>
            
            <div class="flex items-center gap-2">
                <button onclick="deleteAllMedia()" 
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold rounded-xl border border-rose-200 transition"
                        title="Delete all demo movies and start clean">
                    <i data-lucide="trash" class="w-3.5 h-3.5 text-rose-600"></i>
                    <span>Purge Sample Data</span>
                </button>
                <button onclick="openMovieModal()" 
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Add Movie / Series</span>
                </button>
            </div>
        </div>

        <!-- Media Cards Grid -->
        <div id="mediaGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($mediaItems)): ?>
                <div class="col-span-full bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
                    <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-inner">
                        <i data-lucide="film" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">No Movies or Series Added Yet</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">Your catalog is completely clean. Click the button below to add your first movie or web series with Dailymotion streaming.</p>
                    <button onclick="openMovieModal()" class="mt-4 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow transition">
                        + Add First Movie Now
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($mediaItems as $c): ?>
                    <div class="media-card bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col"
                         data-title="<?= strtolower(htmlspecialchars($c['title'])) ?>"
                         data-genre="<?= strtolower(htmlspecialchars($c['genre_title'] ?? '')) ?>">
                        <!-- Media Poster / Backdrop -->
                        <div class="relative h-44 bg-slate-900 overflow-hidden group">
                            <?php 
                                $coverImg = !empty($c['backdrop']) ? $c['backdrop'] : (!empty($c['poster']) ? $c['poster'] : '');
                            ?>
                            <?php if (!empty($coverImg)): ?>
                                <img src="<?= htmlspecialchars($coverImg) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition duration-300 opacity-90">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-tr from-slate-900 to-rose-950 text-rose-400">
                                    <i data-lucide="clapperboard" class="w-12 h-12 stroke-[1.5]"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badges -->
                            <div class="absolute top-3 left-3 flex items-center gap-1.5">
                                <span class="bg-black/75 backdrop-blur-md px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide <?= ($c['type'] === 'series') ? 'text-amber-400 border border-amber-500/40' : 'text-rose-400 border border-rose-500/40' ?>">
                                    <?= ($c['type'] === 'series') ? 'Web Series' : 'Movie' ?>
                                </span>
                                <?php if (!empty($c['genre_title'])): ?>
                                    <span class="bg-white/90 backdrop-blur-md px-2.5 py-1 rounded-full text-[10px] font-bold text-slate-800 shadow-sm">
                                        <?= htmlspecialchars($c['genre_title']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="absolute top-3 right-3 bg-rose-600 text-white px-2.5 py-0.5 rounded-full text-[11px] font-black shadow">
                                ★ <?= htmlspecialchars($c['rating'] ?: '8.5') ?>
                            </div>

                            <div class="absolute bottom-2 right-3 text-[11px] font-medium text-slate-200 drop-shadow">
                                <?= htmlspecialchars($c['release_year'] ?: date('Y')) ?> • <?= htmlspecialchars($c['language'] ?: 'Hindi') ?>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 line-clamp-1"><?= htmlspecialchars($c['title']) ?></h3>
                                <p class="text-xs text-slate-500 mt-1.5 line-clamp-2"><?= htmlspecialchars($c['description'] ?: 'High quality streaming release.') ?></p>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <?php if ($c['type'] === 'series'): ?>
                                        <a href="/admin/seasons/?media_id=<?= $c['id'] ?>" 
                                           title="Manage Seasons"
                                           class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 px-2.5 py-1 rounded-lg border border-rose-100 transition">
                                            <i data-lucide="layers" class="w-3.5 h-3.5 text-rose-500"></i>
                                            <span><?= (int)($c['season_count'] ?? 0) ?> Seasons</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="/admin/episodes/?media_id=<?= $c['id'] ?>" 
                                       title="Manage Episodes & Multi-part Links"
                                       class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg transition">
                                        <i data-lucide="play-square" class="w-3.5 h-3.5 text-slate-600"></i>
                                        <span><?= (int)$c['episode_count'] ?> <?= ($c['type'] === 'movie') ? 'Stream Part(s)' : 'Episodes' ?></span>
                                    </a>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button onclick='editMovie(<?= json_encode($c) ?>)' 
                                            title="Edit Title Details" 
                                            class="p-2 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="deleteMovie(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['title'])) ?>')" 
                                            title="Delete Media" 
                                            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ADD/EDIT MOVIE MODAL -->
<div id="movieModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 md:p-8 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Add New Movie / Web Series</h3>
                <p class="text-xs text-slate-500 mt-0.5">Stream seamlessly on the Cinemas Android App</p>
            </div>
            <button onclick="closeMovieModal()" class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="movieForm" onsubmit="saveMovie(event)" class="mt-6 space-y-4">
            <input type="hidden" id="movieId" value="0">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Type</label>
                    <select id="movieType" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                        <option value="movie">Movie (Single / Multi-part Links)</option>
                        <option value="series">Web Series (Multi-Season)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Genre</label>
                    <select id="movieGenre" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                        <option value="">Select Genre</option>
                        <?php foreach ($genres as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Title *</label>
                <input type="text" id="movieTitle" required placeholder="e.g. Jawan, Kalki 2898 AD, Mirzapur" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Description</label>
                <textarea id="movieDesc" rows="3" placeholder="Synopsis, plot summary or storyline..." 
                          class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Poster URL (Portrait)</label>
                    <input type="url" id="moviePoster" placeholder="https://image... (vertical)" 
                           class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Backdrop URL (Banner)</label>
                    <input type="url" id="movieBackdrop" placeholder="https://image... (landscape)" 
                           class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Release Year</label>
                    <input type="text" id="movieYear" value="<?= date('Y') ?>" placeholder="2024" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Duration</label>
                    <input type="text" id="movieDuration" placeholder="2h 30m" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Rating</label>
                    <input type="text" id="movieRating" value="8.5" placeholder="8.5" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Dailymotion Video ID (Optional single-link)</label>
                <input type="text" id="movieDmId" placeholder="e.g. x8n0001 (or leave blank and add multi-part links in Episodes)" 
                       class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                <span class="text-[11px] text-slate-400 mt-1 block">💡 If your movie is split into 2 or more Dailymotion links, leave this blank and add them inside "Episodes & Streams" — the app will play them as one seamless movie automatically!</span>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeMovieModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                <button type="submit" id="btnSaveMovie" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow transition">Save Movie / Series</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterMovies() {
        var query = document.getElementById('movieSearch').value.toLowerCase();
        var cards = document.querySelectorAll('.media-card');
        cards.forEach(function(card) {
            var title = card.getAttribute('data-title') || '';
            var genre = card.getAttribute('data-genre') || '';
            if (title.includes(query) || genre.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function openMovieModal() {
        document.getElementById('movieId').value = 0;
        document.getElementById('movieForm').reset();
        document.getElementById('modalTitle').innerText = 'Add New Movie / Web Series';
        document.getElementById('btnSaveMovie').innerText = 'Create Movie / Series';
        document.getElementById('movieModal').classList.remove('hidden');
    }

    function closeMovieModal() {
        document.getElementById('movieModal').classList.add('hidden');
    }

    function editMovie(item) {
        document.getElementById('movieId').value = item.id;
        document.getElementById('movieType').value = item.type || 'movie';
        document.getElementById('movieGenre').value = item.genre_id || item.category_id || '';
        document.getElementById('movieTitle').value = item.title || '';
        document.getElementById('movieDesc').value = item.description || '';
        document.getElementById('moviePoster').value = item.poster || item.thumbnail || '';
        document.getElementById('movieBackdrop').value = item.backdrop || '';
        document.getElementById('movieYear').value = item.release_year || '<?= date("Y") ?>';
        document.getElementById('movieDuration').value = item.duration || '';
        document.getElementById('movieRating').value = item.rating || '8.5';
        document.getElementById('movieDmId').value = item.dailymotion_id || '';

        document.getElementById('modalTitle').innerText = 'Edit ' + (item.type === 'series' ? 'Web Series' : 'Movie');
        document.getElementById('btnSaveMovie').innerText = 'Update Changes';
        document.getElementById('movieModal').classList.remove('hidden');
    }

    async function saveMovie(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSaveMovie');
        btn.disabled = true;
        btn.innerText = 'Saving...';

        var payload = {
            id: parseInt(document.getElementById('movieId').value) || 0,
            type: document.getElementById('movieType').value,
            genre_id: parseInt(document.getElementById('movieGenre').value) || null,
            title: document.getElementById('movieTitle').value,
            description: document.getElementById('movieDesc').value,
            poster: document.getElementById('moviePoster').value,
            backdrop: document.getElementById('movieBackdrop').value,
            release_year: document.getElementById('movieYear').value,
            duration: document.getElementById('movieDuration').value,
            rating: document.getElementById('movieRating').value,
            dailymotion_id: document.getElementById('movieDmId').value
        };

        try {
            var res = await fetch('/api/admin_crud.php?action=save_media', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Error saving movie', 'error');
            }
        } catch (err) {
            showToast('Network error while saving movie', 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Save Movie / Series';
        }
    }

    async function deleteMovie(id, title) {
        if (!confirm('Are you sure you want to delete "' + title + '" and all associated seasons and episodes?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_media', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message || 'Deleted successfully', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Failed to delete title', 'error');
            }
        } catch (err) {
            showToast('Error deleting title', 'error');
        }
    }

    async function deleteAllMedia() {
        if (!confirm('⚠️ WARNING: This will permanently delete ALL movies, series, seasons, and episodes from the catalog!\n\nAre you sure you want to clean everything?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_all_media', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message || 'All sample media deleted', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Failed to purge data', 'error');
            }
        } catch (err) {
            showToast('Network error', 'error');
        }
    }

    if (window.location.search.includes('new=1')) {
        openMovieModal();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
