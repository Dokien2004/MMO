(function () {
    'use strict';

    var container = document.querySelector('[data-module-toggle-url]');
    if (!container || !window.AffMVP) return;

    container.addEventListener('change', function (event) {
        var checkbox = event.target.closest('.module-toggle');
        if (!checkbox) return;

        var previousValue = !checkbox.checked;
        var formData = new FormData();
        formData.append('csrf_token', window.AffMVP.getCsrfToken());
        formData.append('module_id', checkbox.dataset.moduleId || '');
        formData.append('enabled', checkbox.checked ? '1' : '0');

        checkbox.disabled = true;
        window.AffMVP.requestJson(container.dataset.moduleToggleUrl, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (response.success) {
                window.AffMVP.showToast(response.message || 'Đã cập nhật module.');
                setTimeout(function () { window.location.reload(); }, 500);
                return;
            }

            checkbox.checked = previousValue;
            window.AffMVP.showToast(response.message || 'Không thể cập nhật module.', 'error');
        })
        .catch(function () {
            checkbox.checked = previousValue;
            window.AffMVP.showToast('Lỗi kết nối server.', 'error');
        })
        .finally(function () {
            checkbox.disabled = false;
        });
    });
})();
