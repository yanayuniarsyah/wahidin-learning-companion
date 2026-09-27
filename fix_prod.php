<?php
$c = file_get_contents('prod_dashboard.html');

// 1. Fix overlay null check in setActiveSidebar
$c = str_replace(
    "sidebar.classList.remove('open');\n                overlay.classList.remove('open');",
    "sidebar.classList.remove('open');\n                if (overlay) overlay.classList.remove('open');",
    $c
);

// 2. Add global click handler right before setActiveSidebar
$globalClickHandler = <<<JS
        // Auto-hide sidebar on mobile when any link is clicked
        document.addEventListener('click', function(e) {
            const link = e.target.closest('#ownerSidebar .sidebar-nav a');
            if (link) {
                const sidebar = document.getElementById('ownerSidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (sidebar && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    if (overlay) overlay.classList.remove('open');
                }
            }
        });
JS;

if (strpos($c, 'Auto-hide sidebar on mobile') === false) {
    $c = str_replace("window.setActiveSidebar = function(navId) {", $globalClickHandler . "\n\n        window.setActiveSidebar = function(navId) {", $c);
}

// 3. Hard Delete non-owner DOM in showRoleDashboard
$targetShowRole = "            if (role === 'owner') {";
$replacementShowRole = "            if (role !== 'owner') {
                const toRemove = ['ownerDashboard', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'manageCertDashboard', 'manageSettingsDashboard'];
                toRemove.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.remove();
                });
            }
            if (role === 'owner') {";
if (strpos($c, "toRemove = ['ownerDashboard'") === false) {
    $c = str_replace($targetShowRole, $replacementShowRole, $c);
}

// 4. Remove offline fallback
$c = preg_replace('/if \(isOffline\) \{.*?return simulateOfflineApi.*?\}\n/s', '', $c);

file_put_contents('prod_dashboard.html', $c);
echo "Fixed prod_dashboard.html\n";
