(function ($) {
    'use strict';

    const toast = {
        el: null,
        message: null,
        icon: null,
        closeBtn: null,
        init() {
            this.el = document.querySelector('.tew-toast');
            if (!this.el) {
                return;
            }
            this.message = this.el.querySelector('.tew-toast__message');
            this.icon = this.el.querySelector('.tew-toast__icon');
            this.closeBtn = this.el.querySelector('.tew-toast__close');
            this.closeBtn?.addEventListener('click', () => this.hide());
        },
        show(type, text) {
            if (!this.el || !this.message) {
                return;
            }
            const icons = {
                info: 'info',
                success: 'check_circle',
                error: 'error',
            };
            this.icon.textContent = icons[type] || 'info';
            this.el.dataset.variant = type;
            this.message.textContent = text;
            this.el.hidden = false;
            this.el.classList.add('is-visible');
            clearTimeout(this._timeout);
            this._timeout = setTimeout(() => this.hide(), 4600);
        },
        hide() {
            if (!this.el) {
                return;
            }
            this.el.classList.remove('is-visible');
            this.el.addEventListener('transitionend', () => {
                this.el.hidden = true;
            }, { once: true });
        }
    };

    function setButtonState($button, state) {
        const states = {
            idle: () => {
                $button.prop('disabled', false);
                $button.removeClass('is-loading is-success is-error');
                $button.find('.material-icons-round').text('bolt');
            },
            loading: () => {
                $button.prop('disabled', true);
                $button.addClass('is-loading');
                $button.find('.material-icons-round').text('hourglass_empty');
            },
            success: () => {
                $button.addClass('is-success').removeClass('is-loading');
                $button.find('.material-icons-round').text('check');
                setTimeout(() => setButtonState($button, 'idle'), 2200);
            },
            error: () => {
                $button.addClass('is-error').removeClass('is-loading');
                $button.find('.material-icons-round').text('error_outline');
                setTimeout(() => setButtonState($button, 'idle'), 3400);
            },
        };

        (states[state] || states.idle)();
    }

    async function testService(service) {
        const endpoint = TEWSettings?.testEndpoint;
        if (!endpoint) {
            throw new Error('missing-endpoint');
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': TEWSettings?.nonce || '',
            },
            body: JSON.stringify({ service }),
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error?.message || 'request-failed');
        }

        return response.json();
    }

    $(function () {
        toast.init();

        $('#tew-preview-shortcode').on('click', function () {
            const shortcode = '[eco_performance_snapshot]';
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(shortcode)
                    .then(() => toast.show('success', 'Shortcode copiado al portapapeles'))
                    .catch(() => toast.show('info', 'Shortcode: ' + shortcode));
            } else {
                toast.show('info', 'Shortcode: ' + shortcode);
            }
        });

        $('.tew-status-list__button').on('click', async function () {
            const $btn = $(this);
            const service = $btn.data('service');
            setButtonState($btn, 'loading');
            toast.show('info', TEWSettings?.messages?.testing || 'Comprobando...');

            try {
                const result = await testService(service);
                const message = result?.message || TEWSettings?.messages?.success || 'Conexión verificada';
                setButtonState($btn, 'success');
                toast.show('success', message);
            } catch (error) {
                const message = error?.message || TEWSettings?.messages?.error || 'No se pudo validar';
                setButtonState($btn, 'error');
                toast.show('error', message);
            }
        });

        // Eliminar caso de éxito
        $('.tew-delete-success-case').on('click', async function () {
            const $btn = $(this);
            const caseId = $btn.data('case-id');
            const nonce = $btn.data('nonce');

            if (!confirm('¿Estás seguro de que deseas eliminar este caso de éxito? Esta acción no se puede deshacer.')) {
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('.material-icons-round').text('hourglass_empty');

            // DEBUG: Log datos enviados
            console.log('🔍 TEW DELETE - Datos enviados:', {
                action: 'tew_delete_success_case',
                case_id: caseId,
                nonce: nonce,
                ajaxurl: ajaxurl
            });

            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'tew_delete_success_case',
                        case_id: caseId,
                        nonce: nonce,
                    }),
                });

                console.log('🔍 TEW DELETE - Response status:', response.status);
                console.log('🔍 TEW DELETE - Response headers:', response.headers);
                
                const responseText = await response.text();
                console.log('🔍 TEW DELETE - Response text:', responseText);
                
                const result = JSON.parse(responseText);

                if (result.success) {
                    toast.show('success', result.data.message || 'Caso de éxito eliminado');
                    // Eliminar la card del DOM con animación
                    $btn.closest('.tew-success-card-admin').fadeOut(400, function () {
                        $(this).remove();
                        // Si no quedan más casos, mostrar el mensaje vacío
                        if ($('.tew-success-card-admin').length === 0) {
                            $('.tew-success-cards').html(`
                                <p class="tew-empty-state">
                                    <span class="material-icons-round">info</span>
                                    No hay casos de éxito todavía. Crea uno usando el formulario de arriba.
                                </p>
                            `);
                        }
                    });
                } else {
                    toast.show('error', result.data.message || 'No se pudo eliminar el caso');
                    $btn.prop('disabled', false);
                    $btn.find('.material-icons-round').text('delete');
                }
            } catch (error) {
                toast.show('error', 'Error al eliminar el caso de éxito');
                $btn.prop('disabled', false);
                $btn.find('.material-icons-round').text('delete');
            }
        });
    });

})(jQuery);
