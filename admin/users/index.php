<?php
// backend/admin/users/index.php - Subscribers & User Access Manager
$pageTitle = 'Subscribers & Users';
$activePage = 'users';
$headerTitle = 'Subscribers & User Management';
$headerSubtitle = 'Manage mobile app user accounts, OTP/Email verification, passwords, and activation';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Fetch users with verification and password fields
$users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email, phone, password, plain_password, status, otp_verified, email_verified, created_at FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="relative flex-1 max-w-md w-full">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="userSearch" oninput="filterUsers()" 
                       placeholder="Search by user name, 10-digit mobile, or email..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-500 focus:bg-white transition">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-3.5 py-2 rounded-xl">
                    Total Subscribers: <strong class="text-slate-900"><?= count($users) ?></strong>
                </span>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="usersTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">ID</th>
                            <th class="py-3.5 px-4">User</th>
                            <th class="py-3.5 px-4">Mobile (10-Digit)</th>
                            <th class="py-3.5 px-4">Email</th>
                            <th class="py-3.5 px-4">Password</th>
                            <th class="py-3.5 px-4">OTP Status</th>
                            <th class="py-3.5 px-4">Email Status</th>
                            <th class="py-3.5 px-4">Account</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="users" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No registered subscribers yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Users will appear here once they register via the mobile application.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-50/70 transition user-row" 
                                    data-name="<?= strtolower(htmlspecialchars($u['name'] ?? '')) ?>"
                                    data-email="<?= strtolower(htmlspecialchars($u['email'] ?? '')) ?>"
                                    data-phone="<?= strtolower(htmlspecialchars($u['phone'] ?? '')) ?>">
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400">
                                        #<?= $u['id'] ?>
                                    </td>
                                    
                                    <!-- User Avatar & Name -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-rose-500 to-amber-500 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm shrink-0">
                                                <?= substr($u['name'] ?? 'U', 0, 1) ?>
                                            </div>
                                            <div class="truncate">
                                                <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($u['name']) ?></p>
                                                <span class="text-[10px] text-slate-400"><?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 10)) ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Phone -->
                                    <td class="py-3.5 px-4">
                                        <span class="font-mono font-semibold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">
                                            <?= htmlspecialchars($u['phone'] ?: 'N/A') ?>
                                        </span>
                                    </td>

                                    <!-- Email -->
                                    <td class="py-3.5 px-4 text-slate-700">
                                        <span class="font-mono text-slate-600"><?= htmlspecialchars($u['email']) ?></span>
                                    </td>

                                    <!-- Password (Show / Change) -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-1.5">
                                            <span id="pass_<?= $u['id'] ?>" class="font-mono text-[11px] bg-slate-100 px-2 py-0.5 rounded text-slate-600 font-semibold" data-plain="<?= htmlspecialchars($u['plain_password'] ?: 'Protected') ?>">
                                                ••••••••
                                            </span>
                                            <button onclick="togglePasswordVisibility(<?= $u['id'] ?>)" title="Show/Hide Password" class="p-1 text-slate-400 hover:text-slate-700 rounded hover:bg-slate-100">
                                                <i data-lucide="eye" id="eye_<?= $u['id'] ?>" class="w-3.5 h-3.5"></i>
                                            </button>
                                            <button onclick="openChangePasswordModal(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')" title="Change User Password" class="p-1 text-slate-400 hover:text-rose-600 rounded hover:bg-rose-50">
                                                <i data-lucide="key" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- OTP Status Toggle -->
                                    <td class="py-3.5 px-4">
                                        <?php if (!empty($u['otp_verified'])): ?>
                                            <button onclick="toggleOtp(<?= $u['id'] ?>)" title="Click to Unverify OTP" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold hover:bg-emerald-100 transition">
                                                <i data-lucide="check" class="w-3 h-3"></i> Verified
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleOtp(<?= $u['id'] ?>)" title="Click to Verify OTP" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold hover:bg-amber-100 transition">
                                                <i data-lucide="clock" class="w-3 h-3"></i> Verify OTP
                                            </button>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Email Status Toggle -->
                                    <td class="py-3.5 px-4">
                                        <?php if (!empty($u['email_verified'])): ?>
                                            <button onclick="toggleEmail(<?= $u['id'] ?>)" title="Click to Unverify Email" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold hover:bg-emerald-100 transition">
                                                <i data-lucide="check" class="w-3 h-3"></i> Verified
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleEmail(<?= $u['id'] ?>)" title="Click to Verify Email" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 text-[10px] font-bold hover:bg-slate-200 transition">
                                                <i data-lucide="mail" class="w-3 h-3"></i> Verify Email
                                            </button>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status Toggle (Active / Inactive) -->
                                    <td class="py-3.5 px-4">
                                        <?php if ((int)$u['status'] === 1): ?>
                                            <button onclick="toggleStatus(<?= $u['id'] ?>)" title="Click to Deactivate" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition group">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleStatus(<?= $u['id'] ?>)" title="Click to Activate" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Deactivated
                                            </button>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Delete Action -->
                                    <td class="py-3.5 px-4 text-right">
                                        <button onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')" 
                                                title="Delete User Account" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
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

<!-- CHANGE PASSWORD MODAL -->
<div id="passwordModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-bold text-slate-900">Change Password</h3>
                <p id="pwModalUserName" class="text-xs text-slate-500 mt-0.5"></p>
            </div>
            <button onclick="closePasswordModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="passwordForm" onsubmit="submitPasswordChange(event)" class="mt-4 space-y-4">
            <input type="hidden" id="pwUserId" value="0">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">New Password *</label>
                <input type="text" id="newPasswordInput" required minlength="6" placeholder="Enter new password (min 6 chars)" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500 font-mono">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closePasswordModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                <button type="submit" id="btnSavePw" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow transition">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterUsers() {
        var query = document.getElementById('userSearch').value.toLowerCase();
        var rows = document.querySelectorAll('.user-row');
        rows.forEach(function(row) {
            var name = row.getAttribute('data-name') || '';
            var email = row.getAttribute('data-email') || '';
            var phone = row.getAttribute('data-phone') || '';
            if (name.includes(query) || email.includes(query) || phone.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function togglePasswordVisibility(userId) {
        var el = document.getElementById('pass_' + userId);
        var plain = el.getAttribute('data-plain') || '';
        if (el.innerText === '••••••••') {
            el.innerText = plain;
        } else {
            el.innerText = '••••••••';
        }
    }

    function openChangePasswordModal(userId, name) {
        document.getElementById('pwUserId').value = userId;
        document.getElementById('pwModalUserName').innerText = 'User: ' + name + ' (#' + userId + ')';
        document.getElementById('newPasswordInput').value = '';
        document.getElementById('passwordModal').classList.remove('hidden');
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').classList.add('hidden');
    }

    async function submitPasswordChange(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSavePw');
        var userId = document.getElementById('pwUserId').value;
        var newPass = document.getElementById('newPasswordInput').value.trim();

        if (newPass.length < 6) {
            showToast('Password must be at least 6 characters', 'error');
            return;
        }

        btn.disabled = true;
        btn.innerText = 'Updating...';

        try {
            var res = await fetch('/api/admin_crud.php?action=update_user_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId, password: newPass })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                closePasswordModal();
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Error updating password', 'error');
            }
        } catch (err) {
            showToast('Network error while updating password', 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Update Password';
        }
    }

    async function toggleStatus(userId) {
        try {
            var res = await fetch('/api/admin_crud.php?action=toggle_user_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 500);
            }
        } catch (err) {
            showToast('Error updating status', 'error');
        }
    }

    async function toggleOtp(userId) {
        try {
            var res = await fetch('/api/admin_crud.php?action=toggle_user_otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 500);
            }
        } catch (err) {
            showToast('Error updating OTP status', 'error');
        }
    }

    async function toggleEmail(userId) {
        try {
            var res = await fetch('/api/admin_crud.php?action=toggle_user_email', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 500);
            }
        } catch (err) {
            showToast('Error updating email verification', 'error');
        }
    }

    async function deleteUser(id, name) {
        if (!confirm('Are you sure you want to remove user account "' + name + '"?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_user', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Failed to delete user', 'error');
            }
        } catch (e) {
            showToast('Network error while deleting user', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
