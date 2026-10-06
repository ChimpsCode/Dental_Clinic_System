(function () {
    'use strict';

    var menu = null;
    var activeAvatar = null;

    function getMenu() {
        if (menu) return menu;
        menu = document.createElement('div');
        menu.id = 'profilePhotoMenu';
        menu.className = 'profile-photo-menu';
        menu.hidden = true;
        menu.innerHTML = '<button type="button" data-action="change"><i class="fas fa-camera" aria-hidden="true"></i><span data-photo-change-label>Add photo</span></button>' +
            '<button type="button" data-action="view"><span class="profile-photo-menu-icon" aria-hidden="true">◉</span>See profile picture</button>' +
            '<button type="button" data-action="remove"><span class="profile-photo-menu-icon" aria-hidden="true">×</span>Remove photo</button>';
        document.body.appendChild(menu);
        menu.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-action]');
            if (!button || !activeAvatar) return;
            if (button.dataset.action === 'change') {
                closeMenu();
                var fileInput = document.getElementById('fileInput');
                if (fileInput) fileInput.click();
                return;
            } else if (button.dataset.action === 'view') {
                var img = activeAvatar.querySelector('img');
                if (img) openViewer(img.src);
            } else if (button.dataset.action === 'remove' && window.confirm('Remove your profile picture?')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.action = '../shared/profile_picture_actions.php';
                form.innerHTML = '<input type="hidden" name="action" value="delete">';
                document.body.appendChild(form);
                form.submit();
            }
            closeMenu();
        });
        return menu;
    }

    function openMenu(avatar) {
        var img = avatar.querySelector('img');
        var hasPhoto = !!(img && img.getAttribute('src'));
        var editable = avatar.dataset.photoEditable === 'true';
        if (!hasPhoto && !editable) return;
        activeAvatar = avatar;
        var el = getMenu();
        el.querySelector('[data-action="change"]').hidden = !editable;
        el.querySelector('[data-photo-change-label]').textContent = hasPhoto ? 'Change photo' : 'Add photo';
        el.querySelector('[data-action="view"]').hidden = !hasPhoto;
        el.querySelector('[data-action="remove"]').hidden = !hasPhoto;
        var rect = avatar.getBoundingClientRect();
        el.hidden = false;
        avatar.setAttribute('aria-expanded', 'true');
        el.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - el.offsetWidth - 12)) + 'px';
        el.style.top = Math.min(rect.bottom + 8, window.innerHeight - el.offsetHeight - 12) + 'px';
    }

    function closeMenu() {
        if (menu) menu.hidden = true;
        if (activeAvatar) activeAvatar.setAttribute('aria-expanded', 'false');
        activeAvatar = null;
    }

    function viewer() {
        var el = document.getElementById('profilePhotoViewer');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'profilePhotoViewer';
        el.className = 'profile-photo-viewer';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'Profile picture');
        el.innerHTML = '<button type="button" class="profile-photo-viewer-close" aria-label="Close">&times;</button><div class="profile-photo-viewer-frame"><img alt="Profile picture"></div>';
        el.addEventListener('click', function (event) {
            if (event.target === el || event.target.classList.contains('profile-photo-viewer-close')) closeViewer();
        });
        document.body.appendChild(el);
        return el;
    }

    function openViewer(src) {
        var el = viewer();
        el.querySelector('img').src = src;
        el.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeViewer() {
        var el = document.getElementById('profilePhotoViewer');
        if (el) el.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (event) {
        var avatar = event.target.closest && event.target.closest('.profile-intro-section .profile-avatar');
        if (avatar) {
            event.preventDefault();
            event.stopPropagation();
            if (menu && !menu.hidden && activeAvatar === avatar) closeMenu();
            else openMenu(avatar);
            return;
        }
        if (menu && !menu.hidden && !event.target.closest('.profile-photo-menu')) closeMenu();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenu();
            closeViewer();
        }
    });

    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);
})();
