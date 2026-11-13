<?php
// backend/admin/seasons/index.php
$pageTitle = 'Manage Seasons';
$activePage = 'seasons';
$headerTitle = 'Seasons Management';
$headerSubtitle = 'Create and sequence seasons for multi-part web series';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Filter by Series / Movie
$selectedMediaId = (int)($_GET['media_id'] ?? 0);

// Fetch Series List for dropdown
$seriesList = [];
try {
    $seriesList = $pdo->query("SELECT id, title, type FROM media_items ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

// Query Seasons with episode count and media title
$seasons = [];
try {
    $sql = "SELECT s.*, m.title AS series_title, m.type AS media_type,
            (SELECT COUNT(*) FROM episodes e WHERE e.season_id = s.id) AS episode_count
            FROM seasons s 
            LEFT JOIN media_items m ON s.series_id = m.id 
            WHERE 1=1 ";
    $params = [];
    if ($selectedMediaId > 0) {
        $sql .= " AND s.series_id = ?";
        $params[] = $selectedMediaId;
    }
    $sql .= " ORDER BY s.series_id DESC, s.season_number ASC, s.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $seasons = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Filter and Action Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3 flex-1 max-w-lg">
                <label class="text-xs font-bold text-slate-500 whitespace-nowrap">Filter Series:</label>
                <select id="seriesFilter" onchange="applyFilter()" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-500">
                    <option value="0">All Series / Movies</option>
                    <?php foreach ($seriesList as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $selectedMediaId === (int)$m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['title']) ?> (<?= ucfirst($m['type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button onclick="openSeasonModal()" 
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add New Season</span>
            </button>
        </div>

        <!-- Seasons Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Seasons List</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Showing organized seasons and their attached episode counts</p>
                </div>
                <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-100">
                    <?= count($seasons) ?> Season(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4"># Season</th>
                            <th class="py-3 px-4">Season Title</th>
                            <th class="py-3 px-4">Series / Movie</th>
                            <th class="py-3 px-4">Episodes Attached</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($seasons)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-2">
                                        <i data-lucide="layers" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700">No Seasons Found</p>
                                    <p class="text-[11px] text-slate-400 mt-1">Create a season to bundle episodes for a web series.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($seasons as $s): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-4 font-bold text-slate-800">
                                        Season <?= (int)$s['season_number'] ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        <?= htmlspecialchars($s['title']) ?>
                                        <?php if (!empty($s['description'])): ?>
                                            <p class="text-[11px] text-slate-400 font-normal line-clamp-1 mt-0.5"><?= htmlspecialchars($s['description']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 font-medium">
                                        <?= htmlspecialchars($s['series_title'] ?? 'Unassigned') ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <a href="/admin/episodes/?season_id=<?= $s['id'] ?>" class="inline-flex items-center gap-1 font-semibold text-[11px] text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full hover:bg-rose-100 transition">
                                            <i data-lucide="play-square" class="w-3 h-3"></i>
                                            <span><?= (int)$s['episode_count'] ?> Episode(s)</span>
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="/admin/episodes/?season_id=<?= $s['id'] ?>" title="Manage Episodes" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                                <i data-lucide="list-video" class="w-4 h-4"></i>
                                            </a>
                                            <button onclick='editSeason(<?= json_encode($s) ?>)' title="Edit Season" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="deleteSeason(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['title'])) ?>')" title="Delete Season" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
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

<!-- ADD/EDIT SEASON MODAL -->
<div id="seasonModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="seasonModalTitle" class="text-base font-bold text-slate-900">Add New Season</h3>
            <button onclick="closeSeasonModal()" class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="seasonForm" onsubmit="saveSeason(event)" class="mt-4 space-y-4">
            <input type="hidden" id="seasonId" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Web Series / Movie *</label>
                <select id="seasonSeriesId" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                    <option value="">Select Series / Movie</option>
                    <?php foreach ($seriesList as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Season #</label>
                    <input type="number" id="seasonNumber" value="1" min="1" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Season Title *</label>
                    <input type="text" id="seasonTitle" required placeholder="e.g. Season 1: Rule of Mafia" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Description (Optional)</label>
                <textarea id="seasonDesc" rows="2" placeholder="Brief summary of this season..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeSeasonModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                <button type="submit" id="btnSaveSeason" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow transition">Save Season</button>
            </div>
        </form>
    </div>
</div>

<script>
    function applyFilter() {
        var id = document.getElementById('seriesFilter').value;
        if (id > 0) {
            window.location.href = '/admin/seasons/?media_id=' + id;
        } else {
            window.location.href = '/admin/seasons/';
        }
    }

    function openSeasonModal() {
        document.getElementById('seasonId').value = 0;
        document.getElementById('seasonForm').reset();
        var curFilter = document.getElementById('seriesFilter').value;
        if (curFilter > 0) {
            document.getElementById('seasonSeriesId').value = curFilter;
        }
        document.getElementById('seasonModalTitle').innerText = 'Add New Season';
        document.getElementById('btnSaveSeason').innerText = 'Create Season';
        document.getElementById('seasonModal').classList.remove('hidden');
    }

    function closeSeasonModal() {
        document.getElementById('seasonModal').classList.add('hidden');
    }

    function editSeason(s) {
        document.getElementById('seasonId').value = s.id;
        document.getElementById('seasonSeriesId').value = s.series_id || s.course_id || '';
        document.getElementById('seasonNumber').value = s.season_number || s.sort_order || 1;
        document.getElementById('seasonTitle').value = s.title || '';
        document.getElementById('seasonDesc').value = s.description || '';

        document.getElementById('seasonModalTitle').innerText = 'Edit Season';
        document.getElementById('btnSaveSeason').innerText = 'Update Season';
        document.getElementById('seasonModal').classList.remove('hidden');
    }

    async function saveSeason(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSaveSeason');
        btn.disabled = true;
        btn.innerText = 'Saving...';

        var payload = {
            id: parseInt(document.getElementById('seasonId').value) || 0,
            series_id: parseInt(document.getElementById('seasonSeriesId').value) || 0,
            season_number: parseInt(document.getElementById('seasonNumber').value) || 1,
            title: document.getElementById('seasonTitle').value,
            description: document.getElementById('seasonDesc').value
        };

        try {
            var res = await fetch('/api/admin_crud.php?action=save_season', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Error saving season', 'error');
            }
        } catch (err) {
            showToast('Network error while saving season', 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Save Season';
        }
    }

    async function deleteSeason(id, title) {
        if (!confirm('Are you sure you want to delete season: "' + title + '" and its attached episodes?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_season', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message || 'Deleted successfully', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Failed to delete season', 'error');
            }
        } catch (err) {
            showToast('Error deleting season', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
