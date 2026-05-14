/**
 * Replanta Affiliates — Landing page JS.
 * Magic-link AJAX + smooth scroll + form UX.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        setupMagicLink();
        setupSmoothScroll();
    }

    /* ======================================================================
       MAGIC LINK — AJAX request
       ====================================================================== */
    function setupMagicLink() {
        var form = document.getElementById('raff-magic-link-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var emailInput = document.getElementById('raff-login-email');
            var email = emailInput.value.trim();
            var msgEl = document.getElementById('raff-login-message');
            var btn = form.querySelector('button[type="submit"]');

            if (!email) return;

            // Disable
            btn.disabled = true;
            btn.textContent = 'Enviando…';
            msgEl.style.display = 'none';

            var data = new FormData();
            data.append('action', 'raff_request_magic_link');
            data.append('email', email);
            data.append('nonce', raffLanding.nonce);

            fetch(raffLanding.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    msgEl.style.display = 'block';
                    if (res.success) {
                        msgEl.className = 'raff-landing__message raff-landing__message--success';
                        msgEl.textContent = res.data.message || '✅ Enlace enviado. Revisa tu bandeja de entrada.';
                        emailInput.value = '';
                    } else {
                        msgEl.className = 'raff-landing__message raff-landing__message--error';
                        msgEl.textContent = res.data.message || 'No se pudo enviar el enlace. Verifica el email.';
                    }
                })
                .catch(function () {
                    msgEl.style.display = 'block';
                    msgEl.className = 'raff-landing__message raff-landing__message--error';
                    msgEl.textContent = 'Error de conexión. Inténtalo de nuevo.';
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Enviar enlace';
                });
        });
    }

    /* ======================================================================
       SMOOTH SCROLL — anchor links
       ====================================================================== */
    function setupSmoothScroll() {
        var links = document.querySelectorAll('.raff-landing a[href^="#"]');
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.querySelector(link.getAttribute('href'));
                if (!target) return;
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }
})();
