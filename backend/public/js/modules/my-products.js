(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var page = document.getElementById('myProductsPage');
        if (!page) {
            return;
        }

        var baseUrl = page.getAttribute('data-base-url') || '';
        var csrfToken = page.getAttribute('data-csrf-token') || '';
        var archiveTargetId = null;

        var addModal = document.getElementById('addModal');
        var editModal = document.getElementById('editModal');
        var archiveModal = document.getElementById('archiveModal');
        var addForm = document.getElementById('addForm');
        var editForm = document.getElementById('editForm');
        var archiveName = document.getElementById('archiveProductName');
        var editId = document.getElementById('edit_id');
        var editProductName = document.getElementById('edit_product_name');
        var editProductUrl = document.getElementById('edit_product_url');
        var editAffiliateUrl = document.getElementById('edit_affiliate_url');
        var editStatus = document.getElementById('edit_status');
        var editNotes = document.getElementById('edit_notes');
        var confirmArchiveButton = document.getElementById('confirmArchiveButton');

        function showToast(message, type) {
            if (window.AffMVP && typeof window.AffMVP.showToast === 'function') {
                window.AffMVP.showToast(message, type);
                return;
            }
            window.alert(message);
        }

        function openModal(modal) {
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeModal(modal) {
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function bindOverlayClose(modal) {
            if (!modal) {
                return;
            }
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        }

        function createFormData(extraData) {
            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            Object.keys(extraData || {}).forEach(function (key) {
                formData.append(key, extraData[key]);
            });
            return formData;
        }

        function handleJsonResponse(response, reloadDelay) {
            showToast(response.message || 'Đã xử lý.', response.success ? 'success' : 'error');
            if (response.success) {
                window.setTimeout(function () {
                    window.location.reload();
                }, reloadDelay || 800);
            }
        }

        function request(url, options) {
            var requestOptions = options || {};
            requestOptions.headers = requestOptions.headers || {};
            requestOptions.headers['X-CSRF-Token'] = csrfToken;
            requestOptions.headers['X-Requested-With'] = 'XMLHttpRequest';
            return fetch(url, requestOptions).then(function (response) {
                return response.json();
            });
        }

        bindOverlayClose(addModal);
        bindOverlayClose(editModal);
        bindOverlayClose(archiveModal);

        document.querySelectorAll('[data-open-add-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (addForm) {
                    addForm.reset();
                }
                openModal(addModal);
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeModal(document.getElementById(button.getAttribute('data-close-modal')));
            });
        });

        document.querySelectorAll('[data-edit-product-id]').forEach(function (button) {
            button.addEventListener('click', function () {
                var productId = button.getAttribute('data-edit-product-id');
                if (!productId) {
                    return;
                }

                request(baseUrl + '/api/my-products/' + productId, { method: 'GET' })
                    .then(function (response) {
                        if (!response.success) {
                            showToast(response.message || 'Không tải được dữ liệu sản phẩm.', 'error');
                            return;
                        }

                        var product = response.data || {};
                        editId.value = product.id || '';
                        editProductName.value = product.product_name || '';
                        editProductUrl.value = product.product_url || '';
                        editAffiliateUrl.value = product.affiliate_url || '';
                        editStatus.value = product.status || 'pending';
                        editNotes.value = product.notes || '';
                        openModal(editModal);
                    })
                    .catch(function (error) {
                        showToast('Lỗi: ' + error.message, 'error');
                    });
            });
        });

        if (addForm) {
            addForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var formData = new FormData(addForm);
                formData.append('csrf_token', csrfToken);

                request(baseUrl + '/my-products/add', {
                    method: 'POST',
                    body: formData
                }).then(function (response) {
                    handleJsonResponse(response, 800);
                }).catch(function (error) {
                    showToast('Lỗi: ' + error.message, 'error');
                });
            });
        }

        if (editForm) {
            editForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var formData = new FormData(editForm);
                var productId = formData.get('edit_id');
                formData.append('csrf_token', csrfToken);
                formData.delete('edit_id');

                request(baseUrl + '/my-products/update/' + productId, {
                    method: 'POST',
                    body: formData
                }).then(function (response) {
                    handleJsonResponse(response, 800);
                }).catch(function (error) {
                    showToast('Lỗi: ' + error.message, 'error');
                });
            });
        }

        document.querySelectorAll('[data-archive-product-id]').forEach(function (button) {
            button.addEventListener('click', function () {
                archiveTargetId = button.getAttribute('data-archive-product-id');
                if (archiveName) {
                    archiveName.textContent = button.getAttribute('data-archive-product-name') || '';
                }
                openModal(archiveModal);
            });
        });

        if (confirmArchiveButton) {
            confirmArchiveButton.addEventListener('click', function () {
                if (!archiveTargetId) {
                    return;
                }

                request(baseUrl + '/my-products/archive/' + archiveTargetId, {
                    method: 'POST',
                    body: createFormData({})
                }).then(function (response) {
                    closeModal(archiveModal);
                    handleJsonResponse(response, 800);
                }).catch(function (error) {
                    showToast('Lỗi: ' + error.message, 'error');
                });
            });
        }

        document.querySelectorAll('[data-generate-content-id]').forEach(function (button) {
            button.addEventListener('click', function () {
                var productId = button.getAttribute('data-generate-content-id');
                if (!productId) {
                    return;
                }

                request(baseUrl + '/my-products/generate-content', {
                    method: 'POST',
                    body: createFormData({ product_id: productId })
                }).then(function (response) {
                    handleJsonResponse(response, 1200);
                }).catch(function (error) {
                    showToast('Lỗi: ' + error.message, 'error');
                });
            });
        });
    });
})();
