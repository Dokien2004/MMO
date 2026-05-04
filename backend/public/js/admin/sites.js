(function () {
    'use strict';

    var modal = document.getElementById('siteModal');
    var form = document.getElementById('siteForm');
    if (!modal || !form || !window.AffMVP) return;

    function field(id) {
        return document.getElementById(id);
    }

    function checked(id, value) {
        field(id).checked = value === 1 || value === '1' || value === true;
    }

    function openModal(mode, data) {
        data = data || {};
        form.reset();
        field('f_site_id').value = '';
        field('f_is_active').checked = true;
        field('siteModalTitle').textContent = mode === 'edit' ? 'Cập nhật site' : 'Thêm site';
        field('siteModalSubmitBtn').textContent = mode === 'edit' ? 'Cập nhật' : 'Tạo';

        if (mode === 'edit') {
            field('f_site_id').value = data.id || '';
            field('f_site_code').value = data.code || '';
            field('f_site_name').value = data.name || '';
            field('f_site_address').value = data.address || '';
            field('f_parent_site_id').value = data.parent_site_id || '';
            checked('f_is_master', data.is_master);
            checked('f_is_active', data.is_active);
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
        var modalButton = event.target.closest('.js-site-modal');
        if (modalButton) {
            var data = {};
            if (modalButton.dataset.site) {
                try {
                    data = JSON.parse(modalButton.dataset.site);
                } catch (error) {
                    data = {};
                }
            }
            openModal(modalButton.dataset.mode || 'create', data);
            return;
        }

        if (event.target.closest('.js-site-modal-close')) {
            closeModal();
            return;
        }

        var toggleButton = event.target.closest('.js-site-toggle');
        if (toggleButton) {
            var active = toggleButton.dataset.active === '1';
            if (!confirm(active ? 'Bật site này?' : 'Tắt site này?')) return;

            postAction(toggleButton.dataset.url, {
                site_id: toggleButton.dataset.siteId,
                active: active ? '1' : '0'
            })
            .then(function (response) {
                if (response.success) window.location.reload();
                else window.AffMVP.showToast(response.message || 'Không thể cập nhật site.', 'error');
            })
            .catch(function () {
                window.AffMVP.showToast('Lỗi kết nối server.', 'error');
            });
            return;
        }

        var currentButton = event.target.closest('.js-site-current');
        if (currentButton) {
            postAction(currentButton.dataset.url, {
                site_id: currentButton.dataset.siteId
            })
            .then(function (response) {
                if (response.success) window.location.reload();
                else window.AffMVP.showToast(response.message || 'Không thể chuyển site.', 'error');
            })
            .catch(function () {
                window.AffMVP.showToast('Lỗi kết nối server.', 'error');
            });
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(form);
        var siteId = formData.get('site_id');
        var endpoint = siteId ? form.dataset.updateUrl : form.dataset.storeUrl;

        if (!formData.has('is_master')) formData.append('is_master', '0');
        if (!formData.has('is_active')) formData.append('is_active', '0');

        window.AffMVP.requestJson(endpoint, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (response.success) {
                window.location.reload();
                return;
            }

            window.AffMVP.showToast(response.message || 'Không thể lưu site.', 'error');
        })
        .catch(function () {
            window.AffMVP.showToast('Lỗi kết nối server.', 'error');
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
})();
