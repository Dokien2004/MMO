(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var page = document.getElementById('productsPage');
        if (!page) {
            return;
        }

        var selectModal = document.getElementById('selectModal');
        var selectProductId = document.getElementById('select_product_id');
        var productName = document.getElementById('edit_product_name');
        var productUrl = document.getElementById('edit_product_url');
        var affiliateUrl = document.getElementById('edit_affiliate_url');
        var status = document.getElementById('edit_status');
        var notes = document.getElementById('edit_notes');

        function openModal() {
            if (selectModal) {
                selectModal.style.display = 'flex';
            }
        }

        function closeModal() {
            if (selectModal) {
                selectModal.style.display = 'none';
            }
        }

        document.querySelectorAll('[data-select-product-trigger]').forEach(function (button) {
            button.addEventListener('click', function () {
                selectProductId.value = button.getAttribute('data-product-id') || '';
                productName.value = button.getAttribute('data-product-name') || '';
                productUrl.value = button.getAttribute('data-product-url') || '';
                affiliateUrl.value = button.getAttribute('data-affiliate-url') || '';
                status.value = button.getAttribute('data-status') || 'pending';
                notes.value = button.getAttribute('data-notes') || '';
                openModal();
            });
        });

        document.querySelectorAll('[data-close-select-modal]').forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        if (selectModal) {
            selectModal.addEventListener('click', function (event) {
                if (event.target === selectModal) {
                    closeModal();
                }
            });
        }
    });
})();
