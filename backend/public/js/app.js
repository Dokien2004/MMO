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

    function initAjaxForms() {
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
    }

    function initSidebar() {
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
    }

    function initPostActionButtons() {
        document.querySelectorAll('[data-post-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-post-action');
                if (!action) return;

                var originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner"></span> Đang kiểm tra...';

                var formData = new FormData();
                var csrfToken = getCsrfToken();
                if (csrfToken) formData.append('csrf_token', csrfToken);

                fetch(action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    }
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    showToast(data.message || 'Đã xử lý', data.success ? 'success' : 'error');
                })
                .catch(function () {
                    showToast('Có lỗi xảy ra.', 'error');
                })
                .finally(function () {
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            });
        });
    }

    function initCopyButtons() {
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var text = button.getAttribute('data-copy') || '';
                if (!text) return;
                if (!navigator.clipboard) {
                    showToast('Trình duyệt không hỗ trợ copy tự động.', 'error');
                    return;
                }
                navigator.clipboard.writeText(text).then(function () {
                    showToast('Đã copy vào clipboard.', 'success');
                }).catch(function () {
                    showToast('Không copy được, hãy copy thủ công.', 'error');
                });
            });
        });
    }

    function initAutoDismissAlerts() {
        document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
            setTimeout(function () {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                setTimeout(function () { el.remove(); }, 300);
            }, 5000);
        });
    }

    function initBulkCheckboxToggle() {
        document.querySelectorAll('[data-toggle-checks]').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var selector = toggle.getAttribute('data-toggle-checks');
                if (!selector) return;
                document.querySelectorAll(selector).forEach(function (checkbox) {
                    if (!checkbox.disabled) checkbox.checked = toggle.checked;
                });
            });
        });
    }

    function initConfirmActions() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-confirm]');
            if (!btn) return;
            if (!confirm(btn.getAttribute('data-confirm'))) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
    }

    function initAutoResizeTextareas() {
        document.querySelectorAll('textarea.auto-resize').forEach(function (el) {
            el.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    }

    function initSiteSwitcher() {
        var switcher = document.getElementById('siteSwitcher');
        var button = document.getElementById('siteSwitcherButton');
        var menu = document.getElementById('siteSwitcherMenu');

        if (!switcher || !button || !menu || button.disabled) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.stopPropagation();
            var expanded = switcher.classList.toggle('open');
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function () {
            switcher.classList.remove('open');
            button.setAttribute('aria-expanded', 'false');
        });
    }

    function initThemeToggle() {
        var btn = document.getElementById('themeToggle');
        if (!btn) {
            return;
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            try {
                localStorage.setItem('theme', theme);
            } catch (error) {
                // Ignore storage failures, keep UI usable.
            }
            btn.textContent = theme === 'light' ? '☀️' : '🌙';
            btn.title = theme === 'light' ? 'Chuyển sang giao diện tối' : 'Chuyển sang giao diện sáng';
        }

        var currentTheme = 'dark';
        try {
            currentTheme = localStorage.getItem('theme') || document.documentElement.getAttribute('data-theme') || 'dark';
        } catch (error) {
            currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        }

        applyTheme(currentTheme);
        btn.addEventListener('click', function () {
            var theme = document.documentElement.getAttribute('data-theme') || 'dark';
            applyTheme(theme === 'dark' ? 'light' : 'dark');
        });
    }

    initAjaxForms();
    initSidebar();
    initPostActionButtons();
    initCopyButtons();
    initAutoDismissAlerts();
    initBulkCheckboxToggle();
    initConfirmActions();
    initAutoResizeTextareas();
    initSiteSwitcher();
    initThemeToggle();

    window.CSRF_TOKEN = getCsrfToken();
    window.AffMVP = {
        getCsrfToken: getCsrfToken,
        requestJson: requestJson,
        showToast: showToast
    };
})();
