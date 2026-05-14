/* Replanta Affiliates — Dashboard scripts */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* ── Copy to clipboard ──────────────────────────── */
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.querySelector(btn.dataset.copy);
                if (!input) return;
                navigator.clipboard.writeText(input.value).then(function () {
                    var orig = btn.textContent;
                    btn.textContent = '¡Copiado!';
                    setTimeout(function () { btn.textContent = orig; }, 1500);
                });
            });
        });

        /* ── Magic-link login form ──────────────────────── */
        var loginForm = document.getElementById('raff-login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var email = loginForm.querySelector('input[type="email"]').value;
                var btn   = loginForm.querySelector('button');
                btn.disabled = true;
                btn.textContent = 'Enviando...';

                var fd = new FormData();
                fd.append('action', 'raff_request_magic_link');
                fd.append('nonce', raffDash.nonce);
                fd.append('email', email);

                fetch(raffDash.ajaxurl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        btn.disabled = false;
                        btn.textContent = 'Enviar enlace de acceso';
                        if (res.success) {
                            window.location.href = window.location.pathname + '?raff_link_sent=1';
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.textContent = 'Enviar enlace de acceso';
                    });
            });
        }

        /* ── Payout request form ────────────────────────── */
        var payoutForm = document.getElementById('raff-payout-form');
        if (payoutForm) {
            payoutForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = payoutForm.querySelector('button');
                var msg = document.getElementById('raff-payout-msg');
                btn.disabled = true;
                btn.textContent = 'Procesando...';

                var fd = new FormData();
                fd.append('action', 'raff_request_payout');
                fd.append('nonce', raffDash.nonce);
                fd.append('method', payoutForm.querySelector('select[name="method"]').value);

                fetch(raffDash.ajaxurl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        btn.disabled = false;
                        btn.textContent = 'Solicitar pago';
                        if (msg) {
                            msg.className = res.success ? 'raff-notice raff-notice--success' : 'raff-notice raff-notice--error';
                            msg.innerHTML = '<p>' + (res.data ? res.data.message : 'Error') + '</p>';
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.textContent = 'Solicitar pago';
                    });
            });
        }

        /* ── Profile: show/hide payment fields ──────────── */
        var methodSelect = document.getElementById('raff-payment-method');
        if (methodSelect) {
            var toggle = function () {
                var val = methodSelect.value;
                var paypal = document.getElementById('raff-paypal-fields');
                var banks  = document.querySelectorAll('.raff-bank-fields');
                if (paypal) paypal.style.display = val === 'paypal' ? '' : 'none';
                banks.forEach(function (el) { el.style.display = val === 'bank' ? '' : 'none'; });
            };
            methodSelect.addEventListener('change', toggle);
            toggle();
        }
    });
})();
