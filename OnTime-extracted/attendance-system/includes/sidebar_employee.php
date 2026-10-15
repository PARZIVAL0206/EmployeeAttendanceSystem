<?php
require_once __DIR__ . '/icons.php';

/**
 * Employee sidebar navigation.
 * Include this after starting the session and requiring config files.
 * Expects $_SESSION['full_name'], $_SESSION['employee_code'], $_SESSION['role'].
 */

// Count pending leave requests for badge
$pendingLeaveCount = 0;
try {
    $sidebarDb = getDB();
    $stmt = $sidebarDb->prepare("
        SELECT COUNT(*) FROM leave_requests
        WHERE employee_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['employee_id'] ?? 0]);
    $pendingLeaveCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // Non-fatal
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Map internal role value to a readable label
$roleLabel = match($_SESSION['role'] ?? '') {
    'admin'    => 'Administrator',
    'employee' => 'Employee',
    default    => ucfirst($_SESSION['role'] ?? 'Employee'),
};

function empSidebarLink(string $href, string $icon, string $label, string $currentPage, string $badgeHtml = ''): string {
    $fileName = basename($href);
    $active   = ($fileName === $currentPage) ? ' active' : '';
    return "<a href=\"{$href}\" class=\"sidebar-link{$active}\">"
         . "<span class=\"sidebar-icon\">" . uiIcon($icon) . "</span>"
         . "<span class=\"sidebar-label\">{$label}</span>"
         . $badgeHtml
         . "</a>";
}

function empSidebarBadge(int $count): string {
    if ($count <= 0) return '';
    return "<span class=\"sidebar-badge\">{$count}</span>";
}
?>

<nav class="sidebar" id="empSidebar">
    <!-- Mobile close button -->
    <button class="sidebar-close" id="sidebarClose" aria-label="Close menu"><?= uiIcon('close') ?></button>

    <div class="sidebar-brand">
        <div class="sidebar-brand-head">
            <div class="sidebar-brand-name">OnTime</div>
            <button class="sidebar-collapse" id="sidebarCollapse" type="button" aria-label="Collapse navigation" aria-pressed="false" title="Collapse navigation">
                <?= uiIcon('chevron-left') ?>
            </button>
        </div>
        <div class="sidebar-brand-tagline">Attendance Management · AICS</div>
    </div>

    <!-- Employee identity -->
    <div class="sidebar-user">
        <div class="sidebar-avatar">
            <?= strtoupper(substr($_SESSION['full_name'] ?? 'E', 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Employee') ?></div>
            <div class="sidebar-user-code"><?= htmlspecialchars($_SESSION['employee_code'] ?? '') ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($roleLabel) ?></div>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Navigation links -->
    <div class="sidebar-nav">
        <?= empSidebarLink(appUrl('employee/dashboard.php'),       'home',     'Dashboard',          $currentPage) ?>
        <?= empSidebarLink(appUrl('employee/attendance.php'),      'clock',    'My Attendance',      $currentPage) ?>
        <?= empSidebarLink(appUrl('employee/history.php'),         'file',     'Attendance History', $currentPage) ?>
        <?= empSidebarLink(appUrl('employee/schedule.php'),        'calendar', 'My Schedule',       $currentPage) ?>
        <?= empSidebarLink(appUrl('employee/leave_request.php'),   'clipboard','Leave Request',     $currentPage, empSidebarBadge($pendingLeaveCount)) ?>
        <?= empSidebarLink(appUrl('employee/leave_history.php'),   'file',     'Leave History',      $currentPage) ?>
        <?= empSidebarLink(appUrl('employee/profile.php'),         'user',     'My Profile',         $currentPage) ?>
        <?= empSidebarLink(appUrl('employee/change_password.php'), 'lock',     'Change Password',    $currentPage) ?>
    </div>

    <div class="sidebar-divider"></div>

    <div class="sidebar-nav">
        <a href="<?= htmlspecialchars(appUrl('logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="sidebar-link sidebar-logout">
            <span class="sidebar-icon"><?= uiIcon('log-out') ?></span>
            <span class="sidebar-label">Logout</span>
        </a>
    </div>
</nav>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
/* ── Employee sidebar styles ─────────────────────────────────── */
.sidebar {
    width: 240px;
    height: 100vh;
    min-height: 100vh;
    background: linear-gradient(180deg, #102f2b 0%, #17473f 62%, #1d5b4f 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    z-index: 1000;
    transition: transform 0.28s cubic-bezier(.4,0,.2,1);
    box-shadow: 3px 0 16px rgba(0,0,0,0.18);
    overflow-y: auto;
}
.sidebar-close {
    display: none;
    background: none; border: none; color: #d5e5a3;
    font-size: 1.3rem; cursor: pointer; padding: 0.6rem 1rem;
    text-align: right; width: 100%;
}
.sidebar-user {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.4rem 1.2rem 1rem;
}
.sidebar-brand { padding: 1rem 1.2rem 0.7rem; }
.sidebar-brand-name {
    color: #d5e5a3;
    font-family: Georgia, 'Palatino Linotype', 'Book Antiqua', serif;
    font-size: 1.8rem;
    font-weight: 800;
    letter-spacing: -.045em;
    line-height: 1.1;
}
.sidebar-brand-tagline { color: rgba(255,255,255,.72); font-size: .68rem; margin-top: .2rem; }
.sidebar-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; font-weight: 700; flex-shrink: 0;
}
.sidebar-user-name  { font-size: 0.88rem; font-weight: 700; line-height: 1.2; }
.sidebar-user-code  { font-size: 0.7rem; color: #d5e5a3; letter-spacing: 0.05em; }
.sidebar-user-role  { font-size: 0.7rem; color: #dce9d9; margin-top: 0.05rem; }
.sidebar-divider    { height: 1px; background: rgba(255,255,255,0.12); margin: 0.5rem 1rem; }
.sidebar-nav        { display: flex; flex-direction: column; padding: 0.4rem 0.6rem; }
.sidebar-link {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.65rem 0.9rem; border-radius: 8px;
    color: rgba(255,253,247,0.82); text-decoration: none;
    font-size: 0.87rem; font-weight: 500;
    transition: background 0.18s, color 0.18s;
    position: relative; margin-bottom: 0.1rem;
}
.sidebar-link:hover  { background: rgba(255,255,255,0.12); color: #fff; }
.sidebar-link.active { background: rgba(213,229,163,0.18); color: #fff; font-weight: 700; }
.sidebar-icon  { font-size: 1rem; width: 22px; text-align: center; flex-shrink: 0; }
.sidebar-label { flex: 1; }
.sidebar-badge {
    background: #ef5350; color: #fff;
    font-size: 0.68rem; font-weight: 700;
    min-width: 18px; height: 18px;
    border-radius: 50px; display: inline-flex;
    align-items: center; justify-content: center;
    padding: 0 4px;
}
.sidebar-logout { color: #ef9a9a; }
.sidebar-logout:hover { background: rgba(239,83,80,0.18); color: #ef5350; }

/* Overlay */
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.45); z-index: 999;
}

/* Content offset */
.main-content { margin-left: 240px; }

/* Mobile responsive */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .sidebar-close { display: block; }
    .sidebar-overlay.open { display: block; }
    .main-content { margin-left: 0; }
}
</style>

<script>
(function () {
    var sidebar  = document.getElementById('empSidebar');
    var overlay  = document.getElementById('sidebarOverlay');
    var closeBtn = document.getElementById('sidebarClose');
    var openBtn  = document.getElementById('hamburgerBtn');
    var collapseBtn = document.getElementById('sidebarCollapse');
    var collapsed = false;

    try {
        collapsed = localStorage.getItem('ontimeSidebarCollapsed') === 'true'
            && window.matchMedia('(min-width: 769px)').matches;
    } catch (error) {}

    function setCollapsed(value, persist) {
        document.body.classList.toggle('sidebar-collapsed', value);
        if (collapseBtn) {
            collapseBtn.setAttribute('aria-pressed', String(value));
            collapseBtn.setAttribute('aria-label', value ? 'Expand navigation' : 'Collapse navigation');
            collapseBtn.title = value ? 'Expand navigation' : 'Collapse navigation';
        }
        if (persist) {
            try { localStorage.setItem('ontimeSidebarCollapsed', String(value)); } catch (error) {}
        }
    }

    function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('open'); if (openBtn) openBtn.setAttribute('aria-expanded', 'true'); }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); if (openBtn) openBtn.setAttribute('aria-expanded', 'false'); }

    setCollapsed(collapsed, false);
    if (openBtn)  openBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay)  overlay.addEventListener('click', closeSidebar);
    document.addEventListener('click', function (event) {
        var target = event.target;
        var button = target && target.closest ? target.closest('#sidebarCollapse') : null;
        if (!button) return;
        event.preventDefault();
        setCollapsed(!document.body.classList.contains('sidebar-collapsed'), true);
    });
})();
</script>
