/* Replanta Affiliates — Registration scripts */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.raff-register-form');
        if (!form) return;

        /* Client-side validation before submit */
        form.addEventListener('submit', function (e) {
            var email = form.querySelector('input[name="email"]');
            if (email && !email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                e.preventDefault();
                email.focus();
                return;
            }

            var file = form.querySelector('input[type="file"]');
            if (file && file.files.length > 0) {
                var f   = file.files[0];
                var max = 5 * 1024 * 1024; /* 5 MB */
                var ok  = ['application/pdf', 'image/jpeg', 'image/png'];
                if (f.size > max || ok.indexOf(f.type) === -1) {
                    e.preventDefault();
                    file.focus();
                    return;
                }
            }

            /* Disable button to prevent double submit */
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Enviando...';
            }
        });
    });
})();
