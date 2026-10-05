(function() {
    const fileInput = document.getElementById('fileInput');
    const chooseBtn = document.getElementById('chooseBtn');
    const menuTrigger = document.getElementById('coverPhotoMenuTrigger');
    const menu = document.getElementById('coverPhotoMenu');
    const changePhotoBtn = document.getElementById('changeProfilePhotoBtn');
    const saveBtn = document.getElementById('saveBtn');
    const form = document.getElementById('uploadForm');
    const preview = document.querySelector('.profile-avatar');
    if (!fileInput || !saveBtn || !form || !preview || (!chooseBtn && !changePhotoBtn)) return;

    saveBtn.style.display = 'none';
    const modal = document.createElement('div');
    modal.className = 'photo-crop-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'photoCropTitle');
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '<div class="photo-crop-card">' +
        '<div class="photo-crop-head"><div><div class="photo-crop-title" id="photoCropTitle">Adjust your profile photo</div><div class="photo-crop-subtitle">Center your smile in the circle.</div></div><button type="button" class="photo-crop-close" aria-label="Close photo editor"><i class="fas fa-times"></i></button></div>' +
        '<div class="photo-crop-body"><canvas class="photo-crop-canvas" width="280" height="280" aria-label="Drag to reposition your profile photo"></canvas><div class="photo-crop-tip">Drag to reposition your photo</div><label class="photo-crop-zoom"><i class="fas fa-minus" aria-hidden="true"></i><input type="range" min="1" max="3" step="0.01" value="1" aria-label="Zoom photo"><i class="fas fa-plus" aria-hidden="true"></i></label></div>' +
        '<div class="photo-crop-foot"><button type="button" class="btn btn-secondary photo-crop-cancel">Cancel</button><button type="button" class="btn photo-crop-save"><i class="fas fa-tooth" aria-hidden="true"></i> Save Photo</button></div></div>';
    document.body.appendChild(modal);

    const canvas = modal.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    const zoomControl = modal.querySelector('input[type="range"]');
    const cropSave = modal.querySelector('.photo-crop-save');
    const cropSize = canvas.width;
    let image = null;
    let imageUrl = '';
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragPoint = null;

    function getImageRect() {
        const scale = Math.max(cropSize / image.naturalWidth, cropSize / image.naturalHeight) * zoom;
        const width = image.naturalWidth * scale;
        const height = image.naturalHeight * scale;
        const maxX = Math.max(0, (width - cropSize) / 2);
        const maxY = Math.max(0, (height - cropSize) / 2);
        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));
        return { x:(cropSize-width)/2+offsetX, y:(cropSize-height)/2+offsetY, width:width, height:height };
    }

    function drawCrop() {
        if (!image) return;
        const rect = getImageRect();
        ctx.clearRect(0, 0, cropSize, cropSize);
        ctx.drawImage(image, rect.x, rect.y, rect.width, rect.height);
    }

    function closeCrop(clearFile) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('photo-crop-open');
        cropSave.disabled = false;
        cropSave.innerHTML = '<i class="fas fa-tooth" aria-hidden="true"></i> Save Photo';
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        imageUrl = '';
        image = null;
        if (clearFile !== false) fileInput.value = '';
    }

    function openCrop(file) {
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        imageUrl = URL.createObjectURL(file);
        image = new Image();
        image.onload = function() {
            zoom = 1;
            offsetX = 0;
            offsetY = 0;
            zoomControl.value = '1';
            drawCrop();
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('photo-crop-open');
            cropSave.focus();
        };
        image.onerror = function() {
            alert('Unable to preview this image. Please choose another photo.');
            closeCrop();
        };
        image.src = imageUrl;
    }

    if (chooseBtn) chooseBtn.addEventListener('click', function() { fileInput.click(); });
    if (menuTrigger && menu && changePhotoBtn) {
        menuTrigger.addEventListener('click', function() {
            const isOpen = !menu.hidden;
            menu.hidden = isOpen;
            menuTrigger.setAttribute('aria-expanded', String(!isOpen));
        });
        changePhotoBtn.addEventListener('click', function() {
            menu.hidden = true;
            menuTrigger.setAttribute('aria-expanded', 'false');
            fileInput.click();
        });
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.profile-cover-menu')) {
                menu.hidden = true;
                menuTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, GIF, and WebP images are allowed.');
            this.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be under 2MB.');
            this.value = '';
            return;
        }
        openCrop(file);
    });

    zoomControl.addEventListener('input', function() { zoom = Number(this.value); drawCrop(); });
    canvas.addEventListener('pointerdown', function(event) {
        if (!image) return;
        dragPoint = { x:event.clientX, y:event.clientY };
        canvas.classList.add('dragging');
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', function(event) {
        if (!dragPoint) return;
        const bounds = canvas.getBoundingClientRect();
        offsetX += (event.clientX-dragPoint.x) * (cropSize/bounds.width);
        offsetY += (event.clientY-dragPoint.y) * (cropSize/bounds.height);
        dragPoint = { x:event.clientX, y:event.clientY };
        drawCrop();
    });
    function endDrag() { dragPoint = null; canvas.classList.remove('dragging'); }
    canvas.addEventListener('pointerup', endDrag);
    canvas.addEventListener('pointercancel', endDrag);

    modal.querySelector('.photo-crop-close').addEventListener('click', closeCrop);
    modal.querySelector('.photo-crop-cancel').addEventListener('click', closeCrop);
    modal.addEventListener('click', function(event) { if (event.target === modal) closeCrop(); });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.classList.contains('open')) closeCrop();
    });

    cropSave.addEventListener('click', function() {
        if (!image) return;
        cropSave.disabled = true;
        cropSave.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
        const output = document.createElement('canvas');
        output.width = 512;
        output.height = 512;
        const outputCtx = output.getContext('2d');
        const rect = getImageRect();
        const ratio = output.width / cropSize;
        outputCtx.fillStyle = '#fff';
        outputCtx.fillRect(0, 0, output.width, output.height);
        outputCtx.drawImage(image, rect.x*ratio, rect.y*ratio, rect.width*ratio, rect.height*ratio);
        output.toBlob(function(blob) {
            if (!blob) {
                cropSave.disabled = false;
                cropSave.innerHTML = '<i class="fas fa-tooth" aria-hidden="true"></i> Save Photo';
                alert('Unable to prepare this photo. Please try again.');
                return;
            }
            const croppedFile = new File([blob], 'profile-photo.jpg', { type:'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            fileInput.files = transfer.files;
            const previewUrl = URL.createObjectURL(blob);
            const previewImage = preview.querySelector('img');
            if (previewImage) {
                previewImage.src = previewUrl;
            } else {
                preview.textContent = '';
                const newImage = document.createElement('img');
                newImage.src = previewUrl;
                newImage.alt = 'Profile';
                preview.appendChild(newImage);
            }
            closeCrop(false);
            form.submit();
        }, 'image/jpeg', 0.92);
    });
})();
