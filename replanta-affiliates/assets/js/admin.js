/* Replanta Affiliates — Admin scripts */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        /* Dismiss admin notices */
        document.querySelectorAll('.notice.is-dismissible .notice-dismiss').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.notice').remove();
            });
        });
    });
})();
