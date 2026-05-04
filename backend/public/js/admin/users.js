(function () {
    'use strict';

    var modal = document.getElementById('userModal');
    var form = document.getElementById('userForm');
    if (!modal || !form || !window.AffMVP) return;

    function field(id) {
        return document.getElementById(id);
    }

    function openModal(mode, data) {
        data = data || {};

        field('modalTitle').textContent = mode === 'edit' ? 'Sửa người dùng' : 'Tạo người dùng';
        field('modalSubmitBtn').textContent = mode === 'edit' ? 'Cập nhật' : 'Tạo';
        field('passHint').textContent = mode === 'edit' ? '(để trống nếu không đổi)' : '';

        form.reset();
        field('f_user_id').value = '';
        field('f_password').setAttribute('required', '');

        if (mode === 'edit') {
            field('f_user_id').value = data.id || '';
            field('f_full_name').value = data.full_name || '';
            field('f_username').value = data.username || '';
            field('f_email').value = data.email || '';
            field('f_role_id').value = data.role_id || '';
            field('f_site_id').value = data.site_id || '1';
            field('f_password').removeAttribute('required');
        }

        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    function postAction(url, data) {
        var formData = new FormData();
        formData.append('csrf_token', window.AffMVP.getCsrfToken());

        Object.keys(data).forEach(function (key) {
            formData.append(key, data[key]);
        });

        return window.AffMVP.requestJson(url, {
            method: 'POST',
            body: formData
        });
    }

    document.addEventListener('click', function (event) {
        var openButton = event.target.closest('.js-user-modal');
        if (openButton) {
            var data = {};
            if (openButton.dataset.user) {
                try {
                    data = JSON.parse(openButton.dataset.user);
                } catch (error) {
                    data = {};
                }
            }
            openModal(openButton.dataset.mode || 'create', data);
            return;
        }

        if (event.target.closest('.js-user-modal-close')) {
            closeModal();
            return;
        }

        var toggleButton = event.target.closest('.js-user-toggle');
        if (toggleButton) {
            var active = toggleButton.dataset.active === '1';
            if (!confirm(active ? 'Mở khóa người dùng này?' : 'Khóa người dùng này?')) return;

            postAction(toggleButton.dataset.url, {
                user_id: toggleButton.dataset.userId,
                active: active ? '1' : '0'
            })
            .then(function (response) {
                if (response.success) window.location.reload();
                else window.AffMVP.showToast(response.message || 'Không thể cập nhật người dùng.', 'error');
            })
            .catch(function () {
                window.AffMVP.showToast('Lỗi kết nối server.', 'error');
            });
            return;
        }

        var unlockButton = event.target.closest('.js-user-unlock');
        if (unlockButton) {
            postAction(unlockButton.dataset.url, {
                user_id: unlockButton.dataset.userId
            })
            .then(function (response) {
                if (response.success) window.location.reload();
                else window.AffMVP.showToast(response.message || 'Không thể gỡ lock.', 'error');
            })
            .catch(function () {
                window.AffMVP.showToast('Lỗi kết nối server.', 'error');
            });
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(form);
        var userId = formData.get('user_id');
        var endpoint = userId ? form.dataset.updateUrl : form.dataset.storeUrl;

        window.AffMVP.requestJson(endpoint, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (response.success) {
                window.location.reload();
                return;
            }

            window.AffMVP.showToast(response.message || 'Không thể lưu người dùng.', 'error');
        })
        .catch(function () {
            window.AffMVP.showToast('Lỗi kết nối server.', 'error');
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
})();
