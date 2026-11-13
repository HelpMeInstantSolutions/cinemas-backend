<?php
// backend/admin/includes/sidebar.php
$activePage = $activePage ?? 'dashboard';
?>
<!-- MOBILE BACKDROP -->
<div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden md:hidden"></div>

<!-- STICKY SIDEBAR (INDEPENDENT SCROLL) -->
<aside id="sidebar" class="w-64 h-full bg-slate-900 text-slate-100 border-r border-slate-800 flex flex-col shrink-0 z-50 fixed inset-y-0 left-0 -translate-x-full md:static md:translate-x-0 transition-transform duration-200 ease-in-out select-none">
    <!-- Brand / Logo Header (Pinned Top) -->
    <div class="h-16 shrink-0 flex items-center px-6 gap-3 border-b border-slate-800 bg-slate-950">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-rose-600 to-amber-500 flex items-center justify-center shadow-lg shadow-rose-600/30 text-white font-bold shrink-0">
            <i data-lucide="clapperboard" class="w-5 h-5"></i>
        </div>
        <div class="flex-1 truncate">
            <a href="/admin/" class="font-bold text-white tracking-tight text-base hover:text-rose-400 transition block truncate">
                Cinemas Admin
            </a>
            <p class="text-[11px] text-slate-400 font-medium truncate">cinemas.mybhiwani.in</p>
        </div>
    </div>

    <!-- Navigation Links (Independent Scroll) -->
    <nav class="p-4 space-y-1.5 flex-1 overflow-y-auto">
        <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Cinema Library</p>

        <!-- Dashboard Link -->
        <a href="/admin/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'dashboard' ? 'bg-rose-600/20 text-rose-400 font-semibold border-r-4 border-rose-500' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4 <?= $activePage === 'dashboard' ? 'text-rose-400' : 'text-slate-400' ?>"></i>
            <span>Overview</span>
        </a>

        <!-- Movies & Series Link -->
        <a href="/admin/movies/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'movies' ? 'bg-rose-600/20 text-rose-400 font-semibold border-r-4 border-rose-500' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
            <i data-lucide="film" class="w-4 h-4 <?= $activePage === 'movies' ? 'text-rose-400' : 'text-slate-400' ?>"></i>
            <span class="flex-1">Movies &amp; Series</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'movies' ? 'bg-rose-500/20 text-rose-300' : 'bg-slate-800 text-slate-400' ?>">
                <?= $serverStats['movies'] ?? 0 ?>M / <?= $serverStats['series'] ?? 0 ?>S
            </span>
        </a>

        <!-- Seasons Link -->
        <a href="/admin/seasons/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'seasons' ? 'bg-rose-600/20 text-rose-400 font-semibold border-r-4 border-rose-500' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
            <i data-lucide="layers" class="w-4 h-4 <?= $activePage === 'seasons' ? 'text-rose-400' : 'text-slate-400' ?>"></i>
            <span class="flex-1">Seasons</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'seasons' ? 'bg-rose-500/20 text-rose-300' : 'bg-slate-800 text-slate-400' ?>">
                <?= $serverStats['series'] ?? 0 ?>
            </span>
        </a>

        <!-- Episodes Link -->
        <a href="/admin/episodes/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'episodes' ? 'bg-rose-600/20 text-rose-400 font-semibold border-r-4 border-rose-500' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
            <i data-lucide="play-square" class="w-4 h-4 <?= $activePage === 'episodes' ? 'text-rose-400' : 'text-slate-400' ?>"></i>
            <span class="flex-1">Episodes &amp; Streams</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'episodes' ? 'bg-rose-500/20 text-rose-300' : 'bg-slate-800 text-slate-400' ?>">
                <?= $serverStats['episodes'] ?? 0 ?>
            </span>
        </a>

        <!-- Dailymotion Accounts Link -->
        <a href="/admin/dm_accounts/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'dm_accounts' ? 'bg-rose-600/20 text-rose-400 font-semibold border-r-4 border-rose-500' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
            <i data-lucide="cloud-cog" class="w-4 h-4 <?= $activePage === 'dm_accounts' ? 'text-rose-400' : 'text-slate-400' ?>"></i>
            <span class="flex-1">DM Accounts</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'dm_accounts' ? 'bg-rose-500/20 text-rose-300' : 'bg-slate-800 text-slate-400' ?>">
                <?= $serverStats['dm_accounts'] ?? 0 ?>
            </span>
        </a>

        <!-- Users Link -->
        <a href="/admin/users/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'users' ? 'bg-rose-600/20 text-rose-400 font-semibold border-r-4 border-rose-500' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
            <i data-lucide="users" class="w-4 h-4 <?= $activePage === 'users' ? 'text-rose-400' : 'text-slate-400' ?>"></i>
            <span class="flex-1">Subscribers / Users</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'users' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-400' ?>">
                <?= $serverStats['users'] ?? 0 ?>
            </span>
        </a>

        <div class="pt-4 pb-2">
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Shortcuts</p>
            <a href="/admin/movies/?new=1" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-medium text-slate-300 hover:text-rose-400 hover:bg-rose-500/10 transition">
                <i data-lucide="plus-circle" class="w-3.5 h-3.5 text-rose-500"></i>
                <span>Add Movie / Series</span>
            </a>
            <a href="/admin/seasons/?new=1" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-medium text-slate-300 hover:text-amber-400 hover:bg-amber-500/10 transition">
                <i data-lucide="folder-plus" class="w-3.5 h-3.5 text-amber-500"></i>
                <span>Create New Season</span>
            </a>
            <a href="/admin/episodes/?new=1" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-medium text-slate-300 hover:text-emerald-400 hover:bg-emerald-500/10 transition">
                <i data-lucide="tv" class="w-3.5 h-3.5 text-emerald-500"></i>
                <span>Upload Episode Stream</span>
            </a>
        </div>
    </nav>

    <!-- Admin Account Profile & Logout Footer (Pinned Bottom) -->
    <div class="p-4 shrink-0 border-t border-slate-800 bg-slate-950">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-full bg-rose-500/20 border border-rose-500/40 text-rose-400 flex items-center justify-center font-bold text-sm shrink-0">
                <?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="flex-1 truncate">
                <p class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></p>
                <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?></p>
            </div>
        </div>
        <a href="/admin/login/?logout=1" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-slate-800 hover:bg-rose-600/30 text-rose-400 hover:text-rose-300 text-xs font-semibold transition">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            <span>Log Out</span>
        </a>
    </div>
</aside>

