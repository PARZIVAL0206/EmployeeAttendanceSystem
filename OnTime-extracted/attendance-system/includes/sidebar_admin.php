<?php
require_once __DIR__ . '/icons.php';

/**
 * Admin sidebar navigation.
 * Include this after starting the session and requiring config files.
 * Expects $_SESSION['full_name'] and $_SESSION['role'] = 'admin'.
 */

// Count pending registrations for badge
$pendingRegistrations = 0;
$pendingLeaves        = 0;
try {
    $sidebarDb = getDB();
    $r = $sidebarDb->query("SELECT COUNT(*) FROM registrations WHERE status = 'pending'");
    $pendingRegistrations = (int)$r->fetchColumn();

    $l = $sidebarDb->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $pendingLeaves = (int)$l->fetchColumn();
} catch (Exception $e) {
    // Non-fatal; badges just show 0
}

// Determine current page for active-link highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function sidebarLink(string $href, string $icon, string $label, string $currentPage, string $badgeHtml = ''): string {
    $fileName = basename($href);
    $active   = ($fileName === $currentPage) ? ' active' : '';
    return "<a href=\"{$href}\" class=\"sidebar-link{$active}\">"
         . "<span class=\"sidebar-icon\">" . uiIcon($icon) . "</span>"
         . "<span class=\"sidebar-label\">{$label}</span>"
         . $badgeHtml
         . "</a>";
}

function sidebarBadge(int $count): string {
    if ($count <= 0) return '';
    return "<span class=\"sidebar-badge\">{$count}</span>";
}
?>

<nav class="sidebar" id="adminSidebar">
    <!-- Hamburger close button (mobile) -->
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

    <!-- Admin identity -->
    <div class="sidebar-user">
        <div class="sidebar-avatar">
            <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?></div>
            <div class="sidebar-user-role">Administrator</div>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Navigation links -->
    <div class="sidebar-nav">
        <?= sidebarLink(appUrl('admin/dashboard.php'),      'home',        'Dashboard',             $currentPage) ?>
        <?= sidebarLink(appUrl('admin/registrations.php'),  'clipboard',   'Pending Registrations', $currentPage, sidebarBadge($pendingRegistrations)) ?>
        <?= sidebarLink(appUrl('admin/employees.php'),      'users',       'Employee Management',   $currentPage) ?>
        <?= sidebarLink(appUrl('admin/attendance.php'),     'clock',       'Attendance Monitoring', $currentPage) ?>
        <?= sidebarLink(appUrl('admin/records.php'),        'file',        'Attendance Records',    $currentPage) ?>
        <?= sidebarLink(appUrl('admin/leaves.php'),         'calendar',    'Leave Management',      $currentPage, sidebarBadge($pendingLeaves)) ?>
        <?= sidebarLink(appUrl('admin/schedules.php'),      'calendar',    'Schedule Management',   $currentPage) ?>
        <?= sidebarLink(appUrl('admin/reports.php'),        'chart',       'Reports',               $currentPage) ?>
        <?= sidebarLink(appUrl('admin/audit_logs.php'),     'list-checks', 'Audit Logs',            $currentPage) ?>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Logout -->
    <div class="sidebar-nav">
        <a href="<?= htmlspecialchars(appUrl('logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="sidebar-link sidebar-logout">
            <span class="sidebar-icon"><?= uiIcon('log-out') ?></span>
            <span class="sidebar-label">Logout</span>
        </a>
    </div>
</nav>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
/* ── Sidebar styles ─────────────────────────────────────────── */
.sidebar {
    width: 240px;
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
.sidebar-user-name { font-size: 0.9rem; font-weight: 700; line-height: 1.2; }
.sidebar-user-role { font-size: 0.72rem; color: #90caf9; margin-top: 0.1rem; }
.sidebar-divider { height: 1px; background: rgba(255,255,255,0.12); margin: 0.5rem 1rem; }
.sidebar-nav { display: flex; flex-direction: column; padding: 0.4rem 0.6rem; }
.sidebar-link {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.65rem 0.9rem; border-radius: 8px;
    color: rgba(255,253,247,0.82); text-decoration: none;
    font-size: 0.87rem; font-weight: 500;
    transition: background 0.18s, color 0.18s;
    position: relative; margin-bottom: 0.1rem;
}
.sidebar-link:hover { background: rgba(255,255,255,0.12); color: #fff; }
.sidebar-link.active { background: rgba(213,229,163,0.18); color: #fff; font-weight: 700; }
.sidebar-icon { font-size: 1rem; width: 22px; text-align: center; flex-shrink: 0; }
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

/* Content offset for sidebar */
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
    // Hamburger open (triggered from page layout)
    var sidebar  = document.getElementById('adminSidebar');
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
