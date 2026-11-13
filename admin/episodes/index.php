<?php
// backend/admin/episodes/index.php
$pageTitle = 'Episodes & Video Streams';
$activePage = 'episodes';
$headerTitle = 'Episodes & Video Streams';
$headerSubtitle = 'Dailymotion private streaming engine for movies, multi-part links, and series episodes';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Filter by Media or Season or DM Account
$selectedMediaId = (int)($_GET['media_id'] ?? 0);
$selectedSeasonId = (int)($_GET['season_id'] ?? 0);
$selectedAccountId = (int)($_GET['account_id'] ?? 0);

// Fetch Media Items for dropdown
$mediaList = [];
try {
    $mediaList = $pdo->query("SELECT id, title, type FROM media_items ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

// Fetch Seasons for dropdown
$seasonsList = [];
try {
    $seasonsList = $pdo->query("SELECT id, series_id, season_number, title FROM seasons ORDER BY series_id DESC, season_number ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch DM Accounts for dropdown
$dmAccounts = [];
try {
    $dmAccounts = $pdo->query("SELECT id, name, channel_name, is_default FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC")->fetchAll();
} catch (Exception $e) {}

// Query Episodes
$episodes = [];
try {
    $sql = "SELECT e.*, m.title AS media_title, m.type AS media_type,
            s.title AS season_title, s.season_number,
            a.name AS dm_account_name, a.channel_name AS dm_channel_name
            FROM episodes e 
            LEFT JOIN media_items m ON e.series_id = m.id 
            LEFT JOIN seasons s ON e.season_id = s.id 
            LEFT JOIN dm_accounts a ON e.dm_account_id = a.id 
            WHERE 1=1 ";
    $params = [];
    if ($selectedMediaId > 0) {
        $sql .= " AND e.series_id = ?";
        $params[] = $selectedMediaId;
    }
    if ($selectedSeasonId > 0) {
        $sql .= " AND e.season_id = ?";
        $params[] = $selectedSeasonId;
    }
    if ($selectedAccountId > 0) {
        $sql .= " AND e.dm_account_id = ?";
        $params[] = $selectedAccountId;
    }
    $sql .= " ORDER BY e.series_id DESC, e.season_id ASC, e.episode_number ASC, e.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $episodes = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Filter and Action Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3 flex-1 flex-wrap">
                <div class="min-w-[200px] flex-1">
                    <select id="mediaFilter" onchange="applyFilters()" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-500">
                        <option value="0">All Movies & Series</option>
                        <?php foreach ($mediaList as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $selectedMediaId === (int)$m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['title']) ?> (<?= ucfirst($m['type']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button onclick="openEpisodeModal()" 
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add Video Episode / Movie Part</span>
            </button>
        </div>

        <!-- Episodes Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Episodes & Video Streams</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Stream links and private Dailymotion videos linked to your titles</p>
                </div>
                <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-100">
                    <?= count($episodes) ?> Video(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4"># Part / Ep</th>
                            <th class="py-3 px-4">Video Title</th>
                            <th class="py-3 px-4">Movie / Series</th>
                            <th class="py-3 px-4">Season</th>
                            <th class="py-3 px-4">Dailymotion ID</th>
                            <th class="py-3 px-4">Duration</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($episodes)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-2">
                                        <i data-lucide="play-circle" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700">No Video Streams Found</p>
                                    <p class="text-[11px] text-slate-400 mt-1">Add movie stream parts or series episodes to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($episodes as $e): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-4 font-bold text-slate-800">
                                        <?= (int)$e['episode_number'] ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center">
                                                <i data-lucide="play" class="w-3.5 h-3.5 fill-rose-600"></i>
                                            </div>
                                            <div>
                                                <?= htmlspecialchars($e['title']) ?>
                                                <?php if (!empty($e['dm_channel_name'])): ?>
                                                    <span class="text-[10px] text-slate-400 block font-normal">Channel: <?= htmlspecialchars($e['dm_channel_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 font-medium">
                                        <?= htmlspecialchars($e['media_title'] ?? 'Unassigned') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500">
                                        <?= !empty($e['season_title']) ? htmlspecialchars($e['season_title']) : '<span class="text-slate-400">Main / Movie</span>' ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-mono text-[11px] bg-slate-100 px-2 py-0.5 rounded text-slate-700 font-semibold">
                                            <?= htmlspecialchars($e['dailymotion_id']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500">
                                        <?= htmlspecialchars($e['duration'] ?: '45:00') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="https://www.dailymotion.com/video/<?= urlencode($e['dailymotion_id']) ?>" target="_blank" title="Preview on Dailymotion" class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
                                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                            </a>
                                            <button onclick='editEpisode(<?= json_encode($e) ?>)' title="Edit Stream" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="deleteEpisode(<?= $e['id'] ?>, '<?= addslashes(htmlspecialchars($e['title'])) ?>')" title="Delete Episode" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- ADD/EDIT EPISODE MODAL -->
<div id="episodeModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="epModalTitle" class="text-base font-bold text-slate-900">Add Video Episode / Movie Part</h3>
            <button onclick="closeEpisodeModal()" class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="episodeForm" onsubmit="saveEpisode(event)" class="mt-4 space-y-4">
            <input type="hidden" id="episodeId" value="0">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Movie / Series *</label>
                    <select id="epMediaId" required onchange="filterSeasonsDropdown()" 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                        <option value="">Select Movie / Series</option>
                        <?php foreach ($mediaList as $m): ?>
                            <option value="<?= $m['id'] ?>" data-type="<?= $m['type'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= ucfirst($m['type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Season (For Series)</label>
                    <select id="epSeasonId" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                        <option value="0">None / Movie Part</option>
                        <?php foreach ($seasonsList as $s): ?>
                            <option value="<?= $s['id'] ?>" data-series="<?= $s['series_id'] ?>">Season <?= $s['season_number'] ?>: <?= htmlspecialchars($s['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Part / Ep #</label>
                    <input type="number" id="epNumber" value="1" min="1" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Title *</label>
                    <input type="text" id="epTitle" required placeholder="e.g. Part 1: Interval or Episode 1: Pilot" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                    <span>Dailymotion Video ID / URL *</span>
                    <span id="fetchStatusBadge" class="text-[11px] font-semibold text-emerald-600 hidden"></span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="text" id="epDmId" required onblur="autoFetchIfEmpty()" placeholder="e.g. xbwbkmm or https://www.dailymotion.com/video/xbwbkmm" 
                           class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500 font-mono">
                    <button type="button" id="btnFetchDm" onclick="fetchDailymotionDetails()" 
                            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 shadow-sm">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-400"></i>
                        <span>Auto-Fetch</span>
                    </button>
                </div>
                <span class="text-[11px] text-slate-400 mt-1 block">💡 Enter ID (e.g. xbwbkmm) and click <strong>Auto-Fetch</strong> to automatically sync Video Title, Duration &amp; Account from Dailymotion.</span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Dailymotion Account</label>
                    <select id="epDmAccountId" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                        <?php foreach ($dmAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['channel_name'] ?: 'Default') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Duration</label>
                    <input type="text" id="epDuration" value="45:00" placeholder="1h 15m or 45:00" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeEpisodeModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                <button type="submit" id="btnSaveEp" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow transition">Save Stream</button>
            </div>
        </form>
    </div>
</div>

<script>
    function applyFilters() {
        var mId = document.getElementById('mediaFilter').value;
        if (mId > 0) {
            window.location.href = '/admin/episodes/?media_id=' + mId;
        } else {
            window.location.href = '/admin/episodes/';
        }
    }

    function filterSeasonsDropdown() {
        var mId = document.getElementById('epMediaId').value;
        var sSelect = document.getElementById('epSeasonId');
        for (var i = 1; i < sSelect.options.length; i++) {
            var opt = sSelect.options[i];
            var sMedia = opt.getAttribute('data-series');
            if (!mId || sMedia === mId) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        }
    }

    function openEpisodeModal() {
        document.getElementById('episodeId').value = 0;
        document.getElementById('episodeForm').reset();
        var badge = document.getElementById('fetchStatusBadge');
        if (badge) badge.classList.add('hidden');
        var curMedia = document.getElementById('mediaFilter').value;
        if (curMedia > 0) {
            document.getElementById('epMediaId').value = curMedia;
            filterSeasonsDropdown();
        }
        document.getElementById('epModalTitle').innerText = 'Add Video Episode / Movie Part';
        document.getElementById('btnSaveEp').innerText = 'Create Stream';
        document.getElementById('episodeModal').classList.remove('hidden');
    }

    function closeEpisodeModal() {
        document.getElementById('episodeModal').classList.add('hidden');
    }

    function autoFetchIfEmpty() {
        var dmId = document.getElementById('epDmId').value.trim();
        var curTitle = document.getElementById('epTitle').value.trim();
        if (dmId.length >= 5 && (!curTitle || curTitle === '')) {
            fetchDailymotionDetails();
        }
    }

    async function fetchDailymotionDetails() {
        var rawDm = document.getElementById('epDmId').value.trim();
        if (!rawDm) {
            showToast('Please enter a Dailymotion Video ID or URL first', 'error');
            return;
        }

        var btn = document.getElementById('btnFetchDm');
        var badge = document.getElementById('fetchStatusBadge');
        var originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block animate-spin mr-1">⌛</span><span>Fetching...</span>';

        var accId = document.getElementById('epDmAccountId') ? document.getElementById('epDmAccountId').value : 0;

        try {
            var url = '/api/admin_crud.php?action=fetch_dm_details&video_id=' + encodeURIComponent(rawDm) + '&account_id=' + accId;
            var res = await fetch(url);
            var json = await res.json();

            if (json.status && json.data) {
                var d = json.data;
                if (d.id) document.getElementById('epDmId').value = d.id;
                if (d.title) document.getElementById('epTitle').value = d.title;
                if (d.duration) document.getElementById('epDuration').value = d.duration;
                if (d.account_id && document.getElementById('epDmAccountId')) {
                    document.getElementById('epDmAccountId').value = d.account_id;
                }
                showToast('✓ Synced with Dailymotion: ' + d.title + ' (' + d.duration + ')', 'success');
                if (badge) {
                    badge.classList.remove('hidden');
                    badge.innerText = '✓ Synced (' + d.duration + ')';
                }
            } else {
                showToast(json.message || 'Could not fetch video details from Dailymotion', 'error');
            }
        } catch (err) {
            showToast('Error querying Dailymotion API: ' + (err.message || err), 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
            if (window.lucide) lucide.createIcons();
        }
    }

    function editEpisode(e) {
        document.getElementById('episodeId').value = e.id;
        document.getElementById('epMediaId').value = e.series_id || e.course_id || '';
        filterSeasonsDropdown();
        document.getElementById('epSeasonId').value = e.season_id || e.chapter_id || 0;
        document.getElementById('epNumber').value = e.episode_number || e.sort_order || 1;
        document.getElementById('epTitle').value = e.title || '';
        document.getElementById('epDmId').value = e.dailymotion_id || '';
        document.getElementById('epDmAccountId').value = e.dm_account_id || 1;
        document.getElementById('epDuration').value = e.duration || '45:00';

        var badge = document.getElementById('fetchStatusBadge');
        if (badge) badge.classList.add('hidden');

        document.getElementById('epModalTitle').innerText = 'Edit Video Stream';
        document.getElementById('btnSaveEp').innerText = 'Update Stream';
        document.getElementById('episodeModal').classList.remove('hidden');
    }

    async function saveEpisode(ev) {
        ev.preventDefault();
        var btn = document.getElementById('btnSaveEp');
        btn.disabled = true;
        btn.innerText = 'Saving...';

        var rawDm = document.getElementById('epDmId').value.trim();
        var m = rawDm.match(/video\/([a-zA-Z0-9]+)/);
        var dmId = m ? m[1] : rawDm;

        var payload = {
            id: parseInt(document.getElementById('episodeId').value) || 0,
            series_id: parseInt(document.getElementById('epMediaId').value) || 0,
            season_id: parseInt(document.getElementById('epSeasonId').value) || 0,
            episode_number: parseInt(document.getElementById('epNumber').value) || 1,
            title: document.getElementById('epTitle').value,
            dailymotion_id: dmId,
            dm_account_id: parseInt(document.getElementById('epDmAccountId').value) || 0,
            duration: document.getElementById('epDuration').value
        };

        try {
            var res = await fetch('/api/admin_crud.php?action=save_episode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var json = await res.json();
            if (res.status === 401 || (json && json.message && json.message.indexOf('Unauthorized') !== -1)) {
                showToast('Admin session expired. Please sign in.', 'error');
                setTimeout(() => window.location.href = '/admin/login/?redirect=' + encodeURIComponent(window.location.pathname), 1000);
                return;
            }
            if (json.status) {
                showToast(json.message || 'Stream saved successfully', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Error saving video', 'error');
            }
        } catch (err) {
            showToast('Save error: ' + (err.message || 'Network error while saving video'), 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Save Stream';
        }
    }

    async function deleteEpisode(id, title) {
        if (!confirm('Are you sure you want to delete video: "' + title + '"?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_episode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message || 'Deleted successfully', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Failed to delete video', 'error');
            }
        } catch (err) {
            showToast('Error deleting video', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
