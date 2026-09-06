(function () {
    var menuToggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('patientSidebar') || document.querySelector('.sidebar');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    if (!menuToggle || !sidebar) return;

    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active', open);
        }
        menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';
    }

    function toggleSidebar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        setOpen(!sidebar.classList.contains('open'));
    }

    menuToggle.addEventListener('click', toggleSidebar);
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }

    sidebar.querySelectorAll('.menu-item').forEach(function (item) {
        item.addEventListener('click', function () {
            if (window.innerWidth <= 1024 && sidebar.classList.contains('open')) {
                setOpen(false);
            }
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            setOpen(false);
        }
    });
})();
