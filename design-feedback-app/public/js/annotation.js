// Hàm hiển thị Toast Notification chuẩn App
window.showToast = function (msg, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.innerHTML = isError
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> ${msg}`
        : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> ${msg}`;

    toast.className = isError ? 'error show' : 'show';
    setTimeout(() => toast.className = toast.className.replace('show', '').trim(), 3000);
};

document.addEventListener('DOMContentLoaded', () => {
    let activeDrawingState = null;
    const style = document.createElement('style');
    style.innerHTML = `.selection-box { position: absolute; border: 2px dashed #1877F2; background: rgba(24, 119, 242, 0.15); pointer-events: none; z-index: 999; }`;
    document.head.appendChild(style);

    document.querySelectorAll('.design-post').forEach(post => {
        const designId = post.dataset.designId;
        const imageContainer = post.querySelector('.image-container');
        const designImage = post.querySelector('.design-image');
        const commentsList = post.querySelector('.comments-list');
        const commentsWrapper = post.querySelector('.comments-section-wrapper');
        const btnLike = post.querySelector('.btn-like');
        const likeCount = post.querySelector('.like-count');
        const iconLike = post.querySelector('.icon-like');
        const btnToggle = post.querySelector('.btn-toggle-comments');
        const commentCountText = post.querySelector('.comment-count-text');

        let commentCount = 0; let isLiked = false;

        fetch(`index.php?action=get_annotations&design_id=${designId}`).then(res => res.json()).then(data => {
            if (data.success && data.data && data.data.length > 0) {
                commentCount = data.data.length;
                commentCountText.innerText = commentCount + ' bình luận';
                data.data.forEach(comment => {
                    const canDelete = (comment.user_id == window.currentUserId || window.userRole === 'designer');
                    commentsList.insertAdjacentHTML('beforeend', `
                        <div class="feedback-card" data-annotation-id="${comment.id}">
                            <div class="avatar"></div>
                            <div style="flex: 1;">
                                <div class="comment-content"><div class="comment-author">${comment.fullname}</div>${comment.cropped_image ? `<img src="${comment.cropped_image}" class="cropped-preview">` : ''}<div style="font-size: 14px; margin-top: 4px;">${comment.content}</div></div>
                                <div class="comment-actions">
                                    <button class="comment-action-btn btn-like-comment ${comment.is_liked > 0 ? 'liked' : ''}">Thích (${comment.like_count})</button>
                                    ${canDelete ? `<button class="comment-action-btn delete-btn btn-delete-comment">Xóa</button>` : ''}
                                </div>
                            </div>
                        </div>`);
                });
            } else commentsList.innerHTML = '<p style="color: #65676B; text-align: center; margin: 20px 0; font-size: 14px;">Chưa có bình luận nào.</p>';
        });

        commentsList.addEventListener('click', (e) => {
            const btnLikeComment = e.target.closest('.btn-like-comment');
            if (btnLikeComment) {
                const card = btnLikeComment.closest('.feedback-card');
                const fd = new FormData(); fd.append('annotation_id', card.dataset.annotationId);
                fetch('index.php?action=toggle_annotation_like', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
                    btnLikeComment.classList.toggle('liked', data.status === 'liked');
                    btnLikeComment.innerText = `Thích (${data.total})`;
                }); return;
            }

            const btnDelete = e.target.closest('.btn-delete-comment');
            if (btnDelete) {
                if (!confirm('Xóa bình luận này?')) return;
                const card = btnDelete.closest('.feedback-card');
                const fd = new FormData(); fd.append('annotation_id', card.dataset.annotationId);
                fetch('index.php?action=delete_annotation', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
                    if (data.success) {
                        card.remove(); commentCount--; commentCountText.innerText = commentCount + ' bình luận';
                        if (commentCount === 0) commentsList.innerHTML = '<p style="color: #65676B; text-align: center; margin: 20px 0; font-size: 14px;">Chưa có bình luận nào.</p>';
                        showToast('Đã xóa bình luận');
                    }
                });
            }
        });

        fetch(`index.php?action=get_likes&design_id=${designId}`).then(res => res.json()).then(data => {
            likeCount.innerText = data.total; isLiked = data.is_liked;
            btnLike.style.color = isLiked ? '#1877F2' : '#65676B';
            iconLike.style.fill = isLiked ? '#1877F2' : 'none'; iconLike.style.stroke = isLiked ? '#1877F2' : 'currentColor';
        });

        btnToggle.addEventListener('click', () => { commentsWrapper.style.display = (commentsWrapper.style.display === 'block') ? 'none' : 'block'; });

        btnLike.addEventListener('click', () => {
            const fd = new FormData(); fd.append('design_id', designId);
            fetch('index.php?action=toggle_like', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
                isLiked = (data.status === 'liked');
                btnLike.style.color = isLiked ? '#1877F2' : '#65676B'; iconLike.style.fill = isLiked ? '#1877F2' : 'none'; iconLike.style.stroke = isLiked ? '#1877F2' : 'currentColor';
                likeCount.innerText = data.total;
            });
        });

        imageContainer.addEventListener('mousedown', function (e) {
            e.preventDefault();
            if (document.getElementById('new-feedback-form')) return showToast('Vui lòng lưu bình luận đang viết!', true);
            const rect = designImage.getBoundingClientRect();
            const startX = e.clientX - rect.left; const startY = e.clientY - rect.top;
            const selectionBox = document.createElement('div'); selectionBox.className = 'selection-box';
            selectionBox.style.left = startX + 'px'; selectionBox.style.top = startY + 'px';
            imageContainer.appendChild(selectionBox);
            activeDrawingState = { post, designImage, imageContainer, selectionBox, startX, startY, designId, commentsList, commentsWrapper, commentCountText };
        });
    });

    window.addEventListener('mousemove', function (e) {
        if (!activeDrawingState) return;
        const { designImage, selectionBox, startX, startY } = activeDrawingState;
        const rect = designImage.getBoundingClientRect();
        let currentX = Math.max(0, Math.min(e.clientX - rect.left, rect.width)); let currentY = Math.max(0, Math.min(e.clientY - rect.top, rect.height));
        selectionBox.style.width = Math.abs(currentX - startX) + 'px'; selectionBox.style.height = Math.abs(currentY - startY) + 'px';
        selectionBox.style.left = Math.min(currentX, startX) + 'px'; selectionBox.style.top = Math.min(currentY, startY) + 'px';
    });

    window.addEventListener('mouseup', function (e) {
        if (!activeDrawingState) return;
        const { designImage, selectionBox, startX, startY, designId, commentsList, commentsWrapper, commentCountText } = activeDrawingState;
        activeDrawingState = null;

        const width = parseFloat(selectionBox.style.width || 0); const height = parseFloat(selectionBox.style.height || 0);
        if (width < 10 || height < 10) { selectionBox.remove(); return; }

        const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
        const rect = designImage.getBoundingClientRect(); const left = parseFloat(selectionBox.style.left); const top = parseFloat(selectionBox.style.top);
        const scaleX = designImage.naturalWidth / rect.width; const scaleY = designImage.naturalHeight / rect.height;
        canvas.width = width * scaleX; canvas.height = height * scaleY;
        ctx.drawImage(designImage, left * scaleX, top * scaleY, width * scaleX, height * scaleY, 0, 0, canvas.width, canvas.height);

        const croppedImageBase64 = canvas.toDataURL('image/jpeg');
        const percentX = (left / rect.width) * 100; const percentY = (top / rect.height) * 100;
        selectionBox.id = 'temp-marker';

        if (commentsList.innerHTML.includes('Chưa có bình luận nào')) commentsList.innerHTML = '';
        commentsWrapper.style.display = 'block';

        commentsList.insertAdjacentHTML('afterbegin', `<div class="feedback-card" id="new-feedback-form"><div class="avatar"></div><div class="comment-content" style="background: transparent; border: 1px solid #CED0D4; padding: 10px;"><div class="comment-author" style="margin-bottom: 8px;">${window.currentUserFullName} <span style="font-weight: 400; font-size: 12px; color: #65676B;">Đang viết...</span></div><img src="${croppedImageBase64}" class="cropped-preview"><textarea id="feedback-text" class="feedback-text" rows="2" placeholder="Viết bình luận..." autofocus></textarea><div class="btn-group"><button class="btn-save" id="btn-save">Đăng</button><button class="btn-cancel" id="btn-cancel">Hủy</button></div></div></div>`);

        document.getElementById('btn-cancel').addEventListener('click', () => { document.getElementById('temp-marker').remove(); document.getElementById('new-feedback-form').remove(); });

        document.getElementById('btn-save').addEventListener('click', (e) => {
            const text = document.getElementById('feedback-text').value;
            if (!text.trim()) return showToast('Vui lòng nhập nội dung!', true);
            e.target.innerText = 'Đang đăng...'; e.target.disabled = true;

            const fd = new FormData(); fd.append('content', text); fd.append('coordinate_x', percentX.toFixed(2)); fd.append('coordinate_y', percentY.toFixed(2)); fd.append('cropped_image', croppedImageBase64); fd.append('design_id', designId);

            fetch('index.php?action=add_annotation', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
                document.getElementById('new-feedback-form').outerHTML = `<div class="feedback-card" data-annotation-id="${data.id}"><div class="avatar"></div><div style="flex: 1;"><div class="comment-content"><div class="comment-author">${window.currentUserFullName}</div><img src="${croppedImageBase64}" class="cropped-preview"><div style="font-size: 14px; margin-top: 4px;">${text}</div></div><div class="comment-actions"><button class="comment-action-btn btn-like-comment">Thích (0)</button><button class="comment-action-btn delete-btn btn-delete-comment">Xóa</button></div></div></div>`;
                document.getElementById('temp-marker').remove(); commentCountText.innerText = (parseInt(commentCountText.innerText) + 1) + ' bình luận';
                showToast('Đã đăng bình luận');
            }).catch(() => { showToast('Lỗi mạng!', true); e.target.innerText = 'Đăng'; e.target.disabled = false; });
        });
    });

    // CÁC TÍNH NĂNG KHÁC (TOP COMMENT, UPLOAD, SETTINGS)
    const btnShowAllTop = document.getElementById('btn-show-all-top-comments');
    const modalTop = document.getElementById('top-comments-modal');
    const selectDesignTop = document.getElementById('top-comment-design-select');
    const topResultDiv = document.getElementById('top-comment-result');

    if (btnShowAllTop && modalTop) {
        btnShowAllTop.addEventListener('click', () => modalTop.style.display = 'flex');
        document.querySelector('.close-top-modal').addEventListener('click', () => modalTop.style.display = 'none');
        modalTop.addEventListener('click', (e) => { if (e.target === modalTop) modalTop.style.display = 'none'; });

        selectDesignTop.addEventListener('change', () => {
            const did = selectDesignTop.value;
            if (!did) { topResultDiv.innerHTML = '<div style="color: #65676B; font-size: 13px; text-align: center; padding: 10px 0;">Vui lòng chọn 1 dự án ở trên.</div>'; return; }
            topResultDiv.innerHTML = '<div style="color: #1877F2; font-size: 13px; text-align: center; padding: 10px 0;">Đang tìm kiếm...</div>';

            fetch(`index.php?action=get_top_comment&design_id=${did}`).then(res => res.json()).then(data => {
                if (data.success && data.data && data.data.like_count > 0) {
                    const c = data.data;
                    topResultDiv.innerHTML = `<div class="top-comment-box" style="margin-top: 0; animation: fadeIn 0.3s;"><div class="author">${c.fullname}</div><div class="content">"${c.content}"</div><div class="likes"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> ${c.like_count} Lượt thích</div></div>`;
                } else topResultDiv.innerHTML = '<div style="color: #E41E3F; font-size: 13px; text-align: center; padding: 10px 0; background: #FFF0F0; border-radius: 8px;">Dự án này chưa có bình luận nào được thích.</div>';
            });
        });
    }

    const dropZone = document.getElementById('drop-zone'); const fileInput = document.getElementById('image-upload');
    if (dropZone && fileInput) {
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.backgroundColor = '#E4E6EB'; });
        dropZone.addEventListener('dragleave', (e) => { e.preventDefault(); dropZone.style.backgroundColor = '#F8F9FA'; });
        dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.style.backgroundColor = '#F8F9FA'; if (e.dataTransfer.files.length > 0) { fileInput.files = e.dataTransfer.files; fileInput.dispatchEvent(new Event('change')); } });
        fileInput.addEventListener('change', (e) => { if (e.target.files.length > 0) { document.getElementById('upload-text').innerText = "Đã chọn: " + e.target.files[0].name; document.getElementById('upload-options').style.display = "flex"; } });

        function handleUpload(type) {
            const activeBtn = type === 'new' ? document.getElementById('btn-new-post') : document.getElementById('btn-update-post');
            const originalText = activeBtn.innerText; activeBtn.innerText = 'Đang tải...'; document.getElementById('btn-new-post').disabled = true; document.getElementById('btn-update-post').disabled = true;
            const formData = new FormData(); formData.append('design_image', fileInput.files[0]); formData.append('upload_type', type);
            if (type === 'update') formData.append('target_design_id', document.getElementById('target-post-select').value);
            fetch('index.php?action=upload_image', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                if (data.success) window.location.reload(); else { showToast('Lỗi: ' + data.message, true); activeBtn.innerText = originalText; document.getElementById('btn-new-post').disabled = false; document.getElementById('btn-update-post').disabled = false; }
            }).catch(err => { showToast('Lỗi mạng!', true); activeBtn.innerText = originalText; document.getElementById('btn-new-post').disabled = false; document.getElementById('btn-update-post').disabled = false; });
        }
        document.getElementById('btn-new-post').addEventListener('click', () => handleUpload('new')); document.getElementById('btn-update-post').addEventListener('click', () => handleUpload('update'));
    }

    const btnPopular = document.getElementById('btn-popular');
    if (btnPopular) {
        btnPopular.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active')); btnPopular.classList.add('active');
            const uploadBox = document.getElementById('upload-box'); if (uploadBox) uploadBox.style.display = 'none';

            let posts = Array.from(document.querySelectorAll('.design-post')); if (posts.length === 0) return;
            document.querySelectorAll('.rank-badge').forEach(b => b.remove());
            posts.sort((a, b) => (parseInt(b.querySelector('.like-count').innerText) || 0) - (parseInt(a.querySelector('.like-count').innerText) || 0));

            const bestPostId = posts[0].dataset.designId; const worstPostId = posts[posts.length - 1].dataset.designId;
            const iconStar = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>`;
            const iconDown = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>`;

            posts.forEach(post => {
                let id = post.dataset.designId;
                if (id === bestPostId) { post.style.display = 'block'; post.style.order = '1'; post.querySelector('.post-header').insertAdjacentHTML('beforeend', `<span class="rank-badge" style="color: #FFF; background: #FFB020; padding: 4px 10px; border-radius: 12px; margin-left: 10px; font-weight: 700; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">${iconStar} Được yêu thích nhất</span>`); }
                else if (id === worstPostId && posts.length > 1 && bestPostId !== worstPostId) { post.style.display = 'block'; post.style.order = '2'; post.querySelector('.post-header').insertAdjacentHTML('beforeend', `<span class="rank-badge" style="color: #FFF; background: #E41E3F; padding: 4px 10px; border-radius: 12px; margin-left: 10px; font-weight: 700; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">${iconDown} Cần cải thiện</span>`); }
                else post.style.display = 'none';
            });
            const mainFeed = document.getElementById('main-feed'); mainFeed.style.display = 'flex'; mainFeed.style.flexDirection = 'column';
        });
    }

    const btnSettings = document.getElementById('btn-settings'); const modal = document.getElementById('settings-modal'); const btnCloseModal = document.querySelector('.close-modal'); const btnSaveProfile = document.getElementById('btn-save-profile');
    if (btnSettings && modal) {
        btnSettings.addEventListener('click', () => modal.style.display = 'flex'); btnCloseModal.addEventListener('click', () => modal.style.display = 'none'); modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
        btnSaveProfile.addEventListener('click', () => {
            const newName = document.getElementById('edit-fullname').value; const newEmail = document.getElementById('edit-email').value; const newPassword = document.getElementById('edit-password').value;
            if (!newName.trim()) return showToast('Tên không được để trống!', true);
            const originalText = btnSaveProfile.innerText; btnSaveProfile.innerText = 'Đang lưu...';
            const fd = new FormData(); fd.append('fullname', newName); fd.append('email', newEmail); fd.append('password', newPassword);
            fetch('index.php?action=update_profile', { method: 'POST', body: fd }).then(res => res.json()).then(data => { if (data.success) window.location.reload(); else { showToast('Lỗi: ' + data.message, true); btnSaveProfile.innerText = originalText; } }).catch(() => { showToast('Lỗi mạng!', true); btnSaveProfile.innerText = originalText; });
        });
    }

    const searchInput = document.getElementById('search-input');
    if (searchInput) searchInput.addEventListener('input', function () { const keyword = this.value.toLowerCase().replace('#', '').trim(); document.querySelectorAll('.design-post').forEach(post => { post.style.display = post.dataset.designId.includes(keyword) ? 'block' : 'none'; }); });

    const themeToggle = document.getElementById('theme-toggle');
    const iconSun = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>`;
    const iconMoon = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>`;
    if (themeToggle) {
        themeToggle.innerHTML = document.body.classList.contains('dark-mode') ? iconSun : iconMoon;
        themeToggle.addEventListener('click', () => {
            const isDark = document.body.classList.toggle('dark-mode');
            themeToggle.innerHTML = isDark ? iconSun : iconMoon;
            const fd = new FormData(); fd.append('theme', isDark ? 'dark' : 'light');
            fetch('index.php?action=toggle_theme', { method: 'POST', body: fd });
        });
    }
});