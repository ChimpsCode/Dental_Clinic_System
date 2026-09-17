(function () {
    'use strict';

    function viewer() {
        var el = document.getElementById('profilePhotoViewer');
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = 'profilePhotoViewer';
        el.className = 'profile-photo-viewer';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'Profile photo');
        el.innerHTML = '<button type="button" class="profile-photo-viewer-close" aria-label="Close">&times;</button>' +
            '<div class="profile-photo-viewer-frame"><img alt="Profile photo"></div>';
        el.addEventListener('click', function (e) {
            if (e.target === el || e.target.classList.contains('profile-photo-viewer-close')) {
                closeViewer();
            }
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
        if (!el) {
            return;
        }
        el.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var avatar = e.target.closest && e.target.closest('.profile-intro-section .profile-avatar');
        if (!avatar) {
            return;
        }
        var img = avatar.querySelector('img');
        if (!img || !img.getAttribute('src')) {
            return;
        }
        openViewer(img.src);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeViewer();
        }
    });
})();
