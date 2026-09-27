<?php
$c = file_get_contents('script0.js');

// Fix setActiveSidebar null check
$c = str_replace(
    "sidebar.classList.remove('open');\n                overlay.classList.remove('open');",
    "sidebar.classList.remove('open');\n                if (overlay) overlay.classList.remove('open');",
    $c
);

// Add global click handler for sidebar links to auto-hide
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

file_put_contents('script0.js', $c);
