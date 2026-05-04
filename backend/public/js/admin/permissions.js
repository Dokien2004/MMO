(function () {
    'use strict';

    var form = document.getElementById('permForm');
    if (!form || !window.AffMVP) return;

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.select-all-btn');
        if (!button) return;

        var roleId = button.dataset.role;
        var checkboxes = document.querySelectorAll('.perm-checkbox[data-role="' + roleId + '"]:not(:disabled)');
        var allChecked = Array.prototype.every.call(checkboxes, function (checkbox) {
            return checkbox.checked;
        });

        Array.prototype.forEach.call(checkboxes, function (checkbox) {
            checkbox.checked = !allChecked;
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(form);
        if (!formData.has('csrf_token')) {
            formData.append('csrf_token', window.AffMVP.getCsrfToken());
        }

        window.AffMVP.requestJson(form.action, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (response.success) {
                var message = document.createElement('div');
                message.className = 'alert alert-success';
                message.textContent = response.message || 'Đã lưu phân quyền.';
                form.parentElement.insertBefore(message, form);
                setTimeout(function () { message.remove(); }, 3000);
                return;
            }

            window.AffMVP.showToast(response.message || 'Lỗi khi lưu.', 'error');
        })
        .catch(function () {
            window.AffMVP.showToast('Lỗi kết nối server.', 'error');
        });
    });
})();
