(function () {
    'use strict';

    /**
     * Inicializa el showcase cuando el DOM esté listo.
     */
    document.addEventListener('DOMContentLoaded', function () {
        const showcases = document.querySelectorAll('[data-tew-showcase]');
        showcases.forEach(initShowcase);
    });

    /**
     * Inicializa un showcase específico.
     */
    function initShowcase(showcase) {
        const filterButtons = showcase.querySelectorAll('.tew-filter-btn');
        const viewButtons = showcase.querySelectorAll('.tew-view-btn');
        const grid = showcase.querySelector('.tew-showcase__grid');

        // Restaurar vista guardada
        try {
            const savedView = localStorage.getItem('tew-showcase-view');
            if (savedView && viewButtons.length) {
                handleViewToggle(showcase, savedView, viewButtons);
            }
        } catch (e) {
            // Ignorar si localStorage no está disponible
        }

        // Filtros (si existen)
        if (filterButtons.length && grid) {
            filterButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const filter = this.getAttribute('data-filter');
                    handleFilterClick(showcase, filter);
                });
            });
        }

        // Toggle vista mosaico/lista
        if (viewButtons.length) {
            viewButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const view = this.getAttribute('data-view');
                    handleViewToggle(showcase, view, viewButtons);
                });
            });
        }
    }

    /**
     * Maneja el toggle entre vista mosaico y lista.
     */
    function handleViewToggle(showcase, view, buttons) {
        // Actualizar atributo data-view del showcase
        showcase.setAttribute('data-view', view);

        // Actualizar botones activos
        buttons.forEach(function (btn) {
            if (btn.getAttribute('data-view') === view) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        });

        // Guardar preferencia en localStorage
        try {
            localStorage.setItem('tew-showcase-view', view);
        } catch (e) {
            // Ignorar si localStorage no está disponible
        }
    }

    /**
     * Maneja el clic en un filtro.
     */
    function handleFilterClick(showcase, filter) {
        const filterButtons = showcase.querySelectorAll('.tew-filter-btn');
        const cards = showcase.querySelectorAll('.tew-showcase-card');

        // Actualizar botones activos
        filterButtons.forEach(function (btn) {
            if (btn.getAttribute('data-filter') === filter) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        });

        // Filtrar cards con animación
        cards.forEach(function (card) {
            const cardType = card.getAttribute('data-type');
            
            if (filter === 'all') {
                showCard(card);
            } else if (filter === 'success' && cardType === 'success') {
                showCard(card);
            } else if (filter === 'recent' && cardType === 'regular') {
                showCard(card);
            } else {
                hideCard(card);
            }
        });
    }

    /**
     * Muestra una card con animación.
     */
    function showCard(card) {
        card.style.display = 'block';
        setTimeout(function () {
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        }, 10);
    }

    /**
     * Oculta una card con animación.
     */
    function hideCard(card) {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        setTimeout(function () {
            card.style.display = 'none';
        }, 300);
    }

    /**
     * Inicializa las animaciones de los gráficos circulares.
     */
    function initCircularCharts() {
        const charts = document.querySelectorAll('.tew-circular-chart');
        
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.5
            });

            charts.forEach(function (chart) {
                observer.observe(chart);
            });
        } else {
            // Fallback para navegadores sin IntersectionObserver
            charts.forEach(function (chart) {
                chart.classList.add('is-visible');
            });
        }
    }

    // Inicializar animaciones cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCircularCharts);
    } else {
        initCircularCharts();
    }
})();
