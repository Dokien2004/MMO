/**
 * Affiliate MVP Laptop — Frontend JS
 * Handles AJAX form submissions, toasts, sidebar toggle
 */

(function () {
    'use strict';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function requestJson(url, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.headers['X-Requested-With'] = 'XMLHttpRequest';

        var csrfToken = getCsrfToken();
        if (csrfToken && !options.headers['X-CSRF-Token']) {
            options.headers['X-CSRF-Token'] = csrfToken;
        }

        return fetch(url, options).then(function (res) {
            return res.json();
        });
    }

    /* ═══════ TOAST ═══════ */
    function showToast(message, type) {
        type = type || 'success';
        var container = document.getElementById('toast-container');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = (type === 'success' ? '✓ ' : '✕ ') + escapeHtml(message);
        container.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('removing');
            setTimeout(function () { toast.remove(); }, 300);
        }, 4000);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ═══════ AJAX FORM HANDLER ═══════ */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-ajax')) return;
        e.preventDefault();

        var btn = form.querySelector('button[type="submit"]');
        var originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Đang xử lý...';
        }

        var formData = new FormData(form);
        var csrfToken = getCsrfToken();
        if (csrfToken && !formData.has('csrf_token')) {
            formData.append('csrf_token', csrfToken);
        }

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            showToast(data.message || 'Thành công', data.success ? 'success' : 'error');
            if (data.success && data.redirect) {
                setTimeout(function () { window.location.href = data.redirect; }, 600);
            } else if (data.success) {
                setTimeout(function () { window.location.reload(); }, 800);
            }
        })
        .catch(function () {
            showToast('Có lỗi xảy ra. Vui lòng thử lại.', 'error');
        })
        .finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    });

    /* ═══════ SIDEBAR MOBILE TOGGLE ═══════ */
    var toggle = document.getElementById('mobile-toggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }

    /* ═══════ AUTO-DISMISS ALERTS ═══════ */
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            setTimeout(function () { el.remove(); }, 300);
        }, 5000);
    });

    /* ═══════ CONFIRM ACTIONS ═══════ */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (!btn) return;
        if (!confirm(btn.getAttribute('data-confirm'))) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    });

    /* ═══════ TEXTAREA AUTO-RESIZE ═══════ */
    document.querySelectorAll('textarea.auto-resize').forEach(function (el) {
        el.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });

    /* Expose globally */
    window.CSRF_TOKEN = getCsrfToken();
    window.AffMVP = {
        getCsrfToken: getCsrfToken,
        requestJson: requestJson,
        showToast: showToast
    };
})();
