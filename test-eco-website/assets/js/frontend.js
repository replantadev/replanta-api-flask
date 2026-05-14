// Funciones globales para el botón de compartir
function tewToggleShareMenu(e) {
    e.stopPropagation();
    const wrapper = document.getElementById('tewShareWrapper');
    if (wrapper) {
        wrapper.classList.toggle('active');
    }
}

function tewCopyLink(url, btn) {
    navigator.clipboard.writeText(url).then(function() {
        const textSpan = btn.querySelector('.tew-copy-text');
        if (textSpan) {
            const originalText = textSpan.textContent;
            textSpan.textContent = '¡Copiado!';
            btn.classList.add('tew-copy-success');
            setTimeout(function() {
                textSpan.textContent = originalText;
                btn.classList.remove('tew-copy-success');
            }, 2000);
        }
    });
}

// Cerrar menú de compartir al hacer clic fuera
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('tewShareWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        wrapper.classList.remove('active');
    }
});

(function () {
    'use strict';

    console.log('TEW - Frontend script loading...');
    const config = window.TEWAudit || {};

    function $(selector, context = document) {
        return context.querySelector(selector);
    }

    function createEl(tag, options = {}) {
        const el = document.createElement(tag);
        if (options.className) {
            el.className = options.className;
        }
        if (options.text) {
            el.textContent = options.text;
        }
        if (options.html) {
            el.innerHTML = options.html;
        }
        if (options.attrs) {
            Object.entries(options.attrs).forEach(([key, value]) => {
                el.setAttribute(key, value);
            });
        }
        return el;
    }

    function showElement(el) {
        if (!el) {
            console.log('TEW - showElement: element is null');
            return;
        }
        console.log('TEW - showElement:', el);
        el.hidden = false;
        el.style.display = '';
    }

    function hideElement(el) {
        if (!el) {
            console.log('TEW - hideElement: element is null');
            return;
        }
        console.log('TEW - hideElement:', el);
        el.hidden = true;
        el.style.display = 'none';
    }

    function formatNumber(value, options = {}) {
        if (value === undefined || value === null) {
            return '—';
        }
        return new Intl.NumberFormat('es-ES', options).format(value);
    }

    function formatDate(date) {
        return new Intl.DateTimeFormat('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    function getTechnicalDescription(metricKey) {
        const descriptions = {
            mobile_performance: `
                <div class="tew-tech-desc">
                    <h5><i class="ph-bold ph-device-mobile"></i> Rendimiento móvil</h5>
                    <p>El <strong>Lighthouse Performance Score</strong> evalúa la velocidad de carga en dispositivos móviles mediante métricas Core Web Vitals. Google utiliza estas métricas como factor de ranking desde 2021.</p>
                    <ul>
                        <li><strong>LCP (Largest Contentful Paint):</strong> Tiempo hasta que el contenido principal es visible. Ideal &lt; 2.5s.</li>
                        <li><strong>TBT (Total Blocking Time):</strong> Suma del tiempo que el hilo principal está bloqueado. Ideal &lt; 200ms.</li>
                        <li><strong>CLS (Cumulative Layout Shift):</strong> Medida de estabilidad visual. Ideal &lt; 0.1.</li>
                    </ul>
                    <p><em>Referencia: <a href="https://web.dev/performance-scoring/" target="_blank">Web.dev Performance Scoring</a></em></p>
                </div>
            `,
            desktop_performance: `
                <div class="tew-tech-desc">
                    <h5><i class="ph-bold ph-desktop"></i> Rendimiento escritorio</h5>
                    <p>El rendimiento en escritorio suele ser superior al móvil debido a mayor potencia de procesamiento y conexiones más estables. Sin embargo, sigue siendo crucial para la experiencia del usuario.</p>
                    <ul>
                        <li><strong>Conexiones más rápidas:</strong> WiFi vs. datos móviles impacta directamente en el Time to First Byte (TTFB).</li>
                        <li><strong>Mayor capacidad de procesamiento:</strong> CPUs más potentes ejecutan JavaScript más rápidamente.</li>
                        <li><strong>Pantallas más grandes:</strong> El LCP puede variar significativamente vs. móvil.</li>
                    </ul>
                    <p><em>Fuente: <a href="https://developers.google.com/web/tools/lighthouse" target="_blank">Google Lighthouse</a></em></p>
                </div>
            `,
            carbon_footprint: `
                <div class="tew-tech-desc">
                    <h5><i class="ph-bold ph-cloud"></i> Huella de carbono</h5>
                    <p>Cada byte transferido consume energía en servidores, redes y dispositivos finales. <strong>Website Carbon Calculator</strong> estima las emisiones basándose en el modelo Sustainable Web Design.</p>
                    <ul>
                        <li><strong>Transferencia de datos:</strong> ~1.8g CO₂ por GB según estudios de la IEA.</li>
                        <li><strong>Energía del dispositivo:</strong> Procesamiento local y renderizado.</li>
                        <li><strong>Infraestructura de red:</strong> Routers, CDN y centros de datos.</li>
                    </ul>
                    <p>Un sitio promedio emite <strong>4.6g CO₂ por visita</strong>. Optimizar imágenes y código reduce significativamente este impacto.</p>
                    <p><em>Metodología: <a href="https://sustainablewebdesign.org/" target="_blank">Sustainable Web Design</a></em></p>
                </div>
            `,
            carbon_intensity: `
                <div class="tew-tech-desc">
                    <h5><i class="ph-bold ph-leaf"></i> Intensidad de carbono</h5>
                    <p>Esta métrica combina <strong>dos factores críticos</strong> para medir el impacto ambiental real de tu sitio web:</p>
                    <ul>
                        <li><strong>Gramos de CO₂ por visita:</strong> Calculado según el peso de la página, eficiencia del código y transferencia de datos. Un sitio promedio emite 1.76g CO₂/visita, mientras que los mejores están por debajo de 0.5g.</li>
                        <li><strong>Uso de energía renovable:</strong> Si tu hosting utiliza energía 100% renovable certificada, tu huella se reduce hasta un 70%. Green Web Foundation verifica más de 400 proveedores a nivel mundial.</li>
                    </ul>
                    <p><strong>¿Cómo mejorarlo?</strong></p>
                    <ul>
                        <li>✓ Optimiza imágenes (usa WebP, compresión, lazy loading)</li>
                        <li>✓ Reduce JavaScript innecesario y CSS no utilizado</li>
                        <li>✓ Implementa caché eficiente y CDN</li>
                        <li>✓ <strong>Migra a hosting verde</strong> (acción más impactante)</li>
                    </ul>
                    <p><em>Datos: <a href="https://www.websitecarbon.com/" target="_blank">Website Carbon</a> + <a href="https://www.thegreenwebfoundation.org/" target="_blank">Green Web Foundation</a></em></p>
                </div>
            `,
            green_hosting: `
                <div class="tew-tech-desc">
                    <h5><i class="ph-bold ph-leaf"></i> Energía del hosting</h5>
                    <p><strong>The Green Web Foundation</strong> mantiene el directorio más completo de proveedores que utilizan energía renovable certificada para alimentar sus centros de datos.</p>
                    <ul>
                        <li><strong>Energía renovable:</strong> Solar, eólica, hidroeléctrica o geotérmica verificada.</li>
                        <li><strong>Certificados de origen:</strong> RECs (Renewable Energy Certificates) o equivalentes.</li>
                        <li><strong>PUE optimizado:</strong> Ratio de eficiencia energética de los centros de datos.</li>
                    </ul>
                    <p>Los centros de datos consumen el <strong>1% de la electricidad mundial</strong>. Elegir hosting verde reduce la huella directamente.</p>
                    <p><em>Datos: <a href="https://www.thegreenwebfoundation.org/" target="_blank">Green Web Foundation</a></em></p>
                </div>
            `
        };

        return descriptions[metricKey] || '<p>Descripción técnica no disponible.</p>';
    }

    function getTechnicalIcon(finding) {
        // Iconos SVG mejorados con mejor definición y viewBox correcto
        if (finding.includes('LCP')) {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 2v11h3v9l7-12h-4l4-8z" fill="currentColor"/></svg>';
        }
        if (finding.includes('CO₂') || finding.includes('carbono')) {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z" fill="currentColor"/><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z" fill="currentColor"/></svg>';
        }
        if (finding.includes('hosting') || finding.includes('verde')) {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L6.5 11h3.25L8.5 22l5.5-11h-3.25L12 2z" fill="currentColor" fill-rule="evenodd"/></svg>';
        }
        if (finding.includes('Score') || finding.includes('grado')) {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" fill="currentColor"/></svg>';
        }
        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="3" fill="currentColor"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="currentColor"/></svg>';
    }

    function impactBadge(impact) {
        const impactMap = {
            alto: 'impact-high',
            medio: 'impact-medium',
            bajo: 'impact-low',
        };
        const labelMap = {
            alto: 'Impacto alto',
            medio: 'Impacto medio',
            bajo: 'Impacto bajo',
        };
        const span = createEl('span', {
            className: `tew-action__badge ${impactMap[impact] || 'impact-medium'}`,
            text: labelMap[impact] || impact,
        });
        span.setAttribute('aria-label', labelMap[impact] || impact);
        return span;
    }

    function effortBadge(effort) {
        const effortMap = {
            alto: 'effort-high',
            medio: 'effort-medium',
            bajo: 'effort-low',
        };
        const labelMap = {
            alto: 'Esfuerzo alto',
            medio: 'Esfuerzo medio',
            bajo: 'Esfuerzo bajo',
        };
        const span = createEl('span', {
            className: `tew-action__badge ${effortMap[effort] || 'effort-medium'}`,
            text: labelMap[effort] || effort,
        });
        span.setAttribute('aria-label', labelMap[effort] || effort);
        return span;
    }

    const statusLabels = {
        excellent: 'Impacto sobresaliente',
        good: 'Base sólida',
        attention: 'Requiere atención',
        critical: 'Acción urgente',
        unknown: 'Pendiente de datos',
    };

    function statusBadge(status = 'unknown') {
        const pill = createEl('span', {
            className: `tew-status-pill tew-status-pill--${status}`,
            text: statusLabels[status] || statusLabels.unknown,
        });
        pill.setAttribute('aria-label', statusLabels[status] || statusLabels.unknown);
        return pill;
    }

    function scoreProgress(component = {}) {
        const wrapper = createEl('div', { className: 'tew-score-progress' });
        const header = createEl('div', { className: 'tew-score-progress__header' });
        header.appendChild(createEl('span', {
            className: 'tew-score-progress__label',
            text: component.label || 'Factor',
        }));
        header.appendChild(createEl('span', {
            className: 'tew-score-progress__value',
            text: `${formatNumber(component.score, { maximumFractionDigits: 1 })}`,
        }));
        wrapper.appendChild(header);

        const bar = createEl('div', { className: 'tew-score-progress__bar' });
        const fill = createEl('div', {
            className: `tew-score-progress__fill is-${component.status || 'unknown'}`,
        });
        const score = Number(component.score) || 0;
        fill.style.width = `${Math.max(0, Math.min(100, score))}%`;
        bar.appendChild(fill);
        wrapper.appendChild(bar);

        const meta = createEl('div', { className: 'tew-score-progress__meta' });
        meta.appendChild(statusBadge(component.status || 'unknown'));
        if (component.weight_percent) {
            meta.appendChild(createEl('span', {
                className: 'tew-score-progress__weight',
                text: `${formatNumber(component.weight_percent, { maximumFractionDigits: 0 })}% peso`,
            }));
        }
        wrapper.appendChild(meta);

        if (component.description) {
            wrapper.appendChild(createEl('p', {
                className: 'tew-score-progress__description',
                text: component.description,
            }));
        }

        return wrapper;
    }

    function renderSummary(container, summary = {}) {
        container.innerHTML = '';

        // Hero card condensada con métricas en círculos
        const hero = createEl('article', { className: 'tew-summary-card tew-summary-card--hero' });
        
        // Header con score y fecha
        const header = createEl('div', { className: 'tew-summary-card__header' });
        const scoreSection = createEl('div', { className: 'tew-summary-card__score-section' });
        
        // Círculo principal grande estilo Lighthouse
        const mainCircle = createEl('div', { className: 'tew-main-score-circle' });
        const scoreValue = Math.round(summary.score || 0);
        const grade = summary.grade?.toLowerCase() || 'unknown';
        
        // Determinar status para color
        let status = 'unknown';
        if (scoreValue >= 90) status = 'excellent';
        else if (scoreValue >= 70) status = 'good';
        else if (scoreValue >= 50) status = 'attention';
        else status = 'critical';
        
        const mainCircleSvg = `
            <svg viewBox="0 0 120 120" class="tew-main-circle-svg">
                <path class="tew-main-circle-bg" 
                      d="M60 10 a 50 50 0 0 1 0 100 a 50 50 0 0 1 0 -100"/>
                <path class="tew-main-circle-fill tew-main-circle-fill--${status}" 
                      stroke-dasharray="${(scoreValue * 314.159) / 100}, 314.159" 
                      d="M60 10 a 50 50 0 0 1 0 100 a 50 50 0 0 1 0 -100"/>
                <text x="60" y="55" class="tew-main-circle-score">${scoreValue}</text>
                <text x="60" y="75" class="tew-main-circle-grade">${summary.grade || '—'}</text>
            </svg>
        `;
        mainCircle.innerHTML = mainCircleSvg;
        scoreSection.appendChild(mainCircle);

        const info = createEl('div', { className: 'tew-summary-card__info' });
        const statusKey = summary.status?.overall || 'unknown';
        info.innerHTML = `
            <h3>${statusLabels[statusKey] || statusLabels.unknown}</h3>
            <p>${summary.url || ''}</p>
            <time class="tew-summary-card__date">${formatDate(new Date())}</time>
        `;
        scoreSection.appendChild(info);
        header.appendChild(scoreSection);

        // Sistema de tabs interactivos para métricas
        if (Array.isArray(summary.score_breakdown) && summary.score_breakdown.length) {
            const tabsSystem = createEl('div', { className: 'tew-metrics-tabs' });
            
            // Navegación de tabs (círculos)
            const tabsNav = createEl('div', { className: 'tew-tabs-nav' });
            const tabsContent = createEl('div', { className: 'tew-tabs-content' });
            
            summary.score_breakdown.forEach((component, index) => {
                const tabId = `tab-${component.key || index}`;
                
                // Tab navegador (chip/badge minimalista)
                const tabButton = createEl('button', { 
                    className: `tew-tab-btn ${index === 0 ? 'active' : ''}`,
                    attrs: { 
                        'data-tab': tabId,
                        'aria-controls': tabId,
                        'aria-selected': index === 0 ? 'true' : 'false'
                    }
                });
                
                // Score como número simple
                const scoreSpan = createEl('span', { className: 'tew-tab-score' });
                scoreSpan.textContent = Math.round(component.score || 0);
                
                const label = createEl('div', { className: 'tew-tab-label' });
                label.innerHTML = `<h4>${component.label || 'Métrica'}</h4>`;
                
                tabButton.appendChild(scoreSpan);
                tabButton.appendChild(label);
                tabsNav.appendChild(tabButton);
                
                // Contenido del tab
                const tabPane = createEl('div', { 
                    className: `tew-tab-pane ${index === 0 ? 'active' : ''}`,
                    attrs: { 
                        'id': tabId,
                        'aria-hidden': index === 0 ? 'false' : 'true'
                    }
                });
                
                tabPane.innerHTML = `
                    <div class="tew-tab-content">
                        <div class="tew-metric-summary">
                            <div class="tew-metric-score">
                                <span class="tew-score-value">${Math.round(component.score || 0)}</span>
                                <span class="tew-score-status ${component.status || 'unknown'}">${statusLabels[component.status] || 'Desconocido'}</span>
                            </div>
                            <div class="tew-metric-meta">
                                <h5>${component.label || 'Métrica'}</h5>
                                <p class="tew-metric-desc">${component.description || 'Puntuación calculada basada en múltiples factores de rendimiento.'}</p>
                                <span class="tew-metric-weight">Peso en el cálculo final: ${component.weight_percent || 0}%</span>
                            </div>
                        </div>
                        <div class="tew-metric-details">
                            ${getTechnicalDescription(component.key)}
                        </div>
                    </div>
                `;
                
                tabsContent.appendChild(tabPane);
            });
            
            // Event listeners para tabs
            tabsNav.addEventListener('click', (e) => {
                const tabBtn = e.target.closest('.tew-tab-btn');
                if (!tabBtn) return;
                
                const targetTab = tabBtn.getAttribute('data-tab');
                
                // Actualizar botones
                tabsNav.querySelectorAll('.tew-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-selected', 'false');
                });
                tabBtn.classList.add('active');
                tabBtn.setAttribute('aria-selected', 'true');
                
                // Actualizar contenido
                tabsContent.querySelectorAll('.tew-tab-pane').forEach(pane => {
                    pane.classList.remove('active');
                    pane.setAttribute('aria-hidden', 'true');
                });
                const targetPane = tabsContent.querySelector(`#${targetTab}`);
                if (targetPane) {
                    targetPane.classList.add('active');
                    targetPane.setAttribute('aria-hidden', 'false');
                }
            });
            
            tabsSystem.appendChild(tabsNav);
            tabsSystem.appendChild(tabsContent);
            header.appendChild(tabsSystem);
        }

        hero.appendChild(header);

        // Resumen técnico condensado con iconos
        if (Array.isArray(summary.key_findings) && summary.key_findings.length) {
            const techSummary = createEl('div', { className: 'tew-tech-summary' });
            techSummary.innerHTML = `
                <div class="tew-tech-summary__icon">
                    <i class="ph-bold ph-chart-bar"></i>
                </div>
                <div class="tew-tech-summary__content">
                    <h4>Resumen técnico</h4>
                    <ul class="tew-tech-findings">
                        ${summary.key_findings.map(finding => `
                            <li class="tew-tech-finding">
                                <span class="tew-tech-finding__icon">${getTechnicalIcon(finding)}</span>
                                <span class="tew-tech-finding__text">${finding}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;
            hero.appendChild(techSummary);
        }

        container.appendChild(hero);

        // Narrativas como cards separadas (mantengo las existentes)
        if (summary.narratives) {
            const narrativesGrid = createEl('div', { className: 'tew-narratives-grid' });

            Object.entries(summary.narratives).forEach(([key, narrative]) => {
                if (!narrative) {
                    return;
                }

                const card = createEl('article', { className: `tew-narrative-card tew-narrative-card--${key}` });
                const head = createEl('header', { className: 'tew-narrative-card__header' });
                head.appendChild(createEl('h4', { text: narrative.title || '' }));
                if (key === 'technical') {
                    head.appendChild(statusBadge(statusKey));
                }
                card.appendChild(head);

                if (narrative.summary) {
                    const summaryEl = createEl('div', { className: 'tew-narrative-card__summary' });
                    summaryEl.innerHTML = `<p>${narrative.summary}</p>`;
                    card.appendChild(summaryEl);
                }

                if (Array.isArray(narrative.bullets) && narrative.bullets.length) {
                    const list = createEl('ul', { className: 'tew-narrative-card__list' });
                    narrative.bullets.forEach((bullet) => {
                        const li = createEl('li');
                        
                        // Detectar formato "Label: Valor" para aplicar negritas
                        let formattedBullet = bullet;
                        if (bullet.includes(':')) {
                            const colonIndex = bullet.indexOf(':');
                            if (colonIndex > 0 && colonIndex < bullet.length - 1) {
                                const label = bullet.substring(0, colonIndex);
                                const value = bullet.substring(colonIndex + 1);
                                formattedBullet = `<strong>${label}:</strong>${value}`;
                            }
                        }
                        
                        li.innerHTML = `
                            <svg class="tew-bullet-check" width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="tew-bullet-text">${formattedBullet}</span>
                        `;
                        list.appendChild(li);
                    });
                    card.appendChild(list);
                }

                narrativesGrid.appendChild(card);
            });

            container.appendChild(narrativesGrid);
        }
    }

    function renderMetrics(container, metrics, errors) {
        container.innerHTML = '';

        const sections = {
            scorecard: renderScorecard,
            pagespeed: renderPageSpeed,
            websitecarbon: renderWebsiteCarbon,
            greenweb: renderGreenWeb,
        };

        Object.entries(sections).forEach(([key, renderer]) => {
            const data = metrics?.[key];
            const card = renderer(data, errors?.[key]);
            container.appendChild(card);
        });
    }

    function renderPageSpeed(data = {}, error) {
        const card = createEl('article', { className: 'tew-metric-card tew-metric-card--pagespeed' });
        card.innerHTML = `<header><h3>PageSpeed Insights</h3><p>Lighthouse score y métricas core</p></header>`;

        if (error) {
            card.appendChild(renderErrorState(error));
            return card;
        }

        const table = createEl('div', { className: 'tew-metric-card__table' });
        ['mobile', 'desktop'].forEach((variant) => {
            if (!data?.[variant]) {
                return;
            }
            const block = createEl('div', { className: 'tew-metric-card__block' });
            block.innerHTML = `
                <div class="tew-metric-card__block-header">
                    <span class="tew-chip">${variant === 'mobile' ? 'Móvil' : 'Escritorio'}</span>
                    <strong>${formatNumber(data[variant].score)}</strong>
                </div>
                <ul>
                    <li><span>LCP</span><strong>${formatNumber(data[variant].lcp_ms ? data[variant].lcp_ms / 1000 : null, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} s</strong></li>
                    <li><span>TBT / INP</span><strong>${formatNumber(data[variant].tbt_ms || data[variant].inp_ms, { maximumFractionDigits: 0 })} ms</strong></li>
                    <li><span>CLS</span><strong>${formatNumber(data[variant].cls, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></li>
                </ul>
            `;
            if (data[variant].report_url) {
                const link = createEl('a', {
                    className: 'tew-metric-card__link',
                    text: 'Abrir reporte Lighthouse',
                    attrs: { href: data[variant].report_url, target: '_blank', rel: 'noopener noreferrer' },
                });
                block.appendChild(link);
            }
            table.appendChild(block);
        });

        card.appendChild(table);
        return card;
    }

    function renderScorecard(data = {}, error) {
        const card = createEl('article', { className: 'tew-metric-card tew-metric-card--scorecard' });
        card.innerHTML = `<header><h3>¿Tu sitio web es sostenible?</h3><p>Puntuación ambiental y huella de carbono</p></header>`;

        if (error) {
            card.appendChild(renderErrorState(error));
            return card;
        }

        const hasComponents = Array.isArray(data.components) && data.components.length;
        const hasScore = Number.isFinite(Number(data.global_score));

        if (!hasComponents && !hasScore) {
            card.appendChild(createEl('p', {
                className: 'tew-scorecard__empty',
                text: 'Calculamos la sostenibilidad de tu sitio web con PageSpeed Insights y Website Carbon.',
            }));
            return card;
        }

        const overview = createEl('div', { className: 'tew-scorecard-overview' });
        const score = createEl('div', { className: 'tew-scorecard-overview__score' });
        score.innerHTML = `<strong>${formatNumber(data.global_score, { maximumFractionDigits: 1 })}</strong><span>/100</span>`;
        overview.appendChild(score);
        overview.appendChild(statusBadge(data.status || 'unknown'));
        card.appendChild(overview);

        const body = createEl('div', { className: 'tew-scorecard__body' });

        if (Array.isArray(data.components) && data.components.length) {
            const componentsGrid = createEl('div', { className: 'tew-performance-grid' });
            data.components.forEach((component) => {
                const metricCard = createEl('div', { className: 'tew-performance-card' });
                
                metricCard.innerHTML = `
                    <div class="tew-performance-score">
                        <span class="tew-perf-value">${Math.round(component.score || 0)}</span>
                        <span class="tew-perf-status ${component.status || 'unknown'}">${statusLabels[component.status] || 'Desconocido'}</span>
                    </div>
                    <div class="tew-performance-info">
                        <h4>${component.label || 'Métrica'}</h4>
                        <span class="tew-perf-weight">${component.weight_percent || 0}% peso</span>
                        <p class="tew-perf-desc">${component.description || 'Puntuación calculada basada en múltiples factores.'}</p>
                    </div>
                `;
                
                componentsGrid.appendChild(metricCard);
            });
            body.appendChild(componentsGrid);
        }

        const carbon = data.carbon || {};
        if (
            carbon && (
                carbon.co2_per_view !== undefined ||
                carbon.co2_per_1000_views_kg !== undefined ||
                carbon.co2_per_10000_views_kg !== undefined ||
                carbon.cleaner_than !== undefined
            )
        ) {
            const carbonBlock = createEl('div', { className: 'tew-scorecard__carbon' });
            carbonBlock.innerHTML = '<h4>Huella destacada</h4>';
            const list = createEl('ul');

            list.appendChild(createEl('li', {
                html: `<span>CO₂ por visita</span><strong>${formatNumber(carbon.co2_per_view, { maximumFractionDigits: 2 })} g</strong>`,
            }));
            list.appendChild(createEl('li', {
                html: `<span>1.000 visitas</span><strong>${formatNumber(carbon.co2_per_1000_views_kg, { maximumFractionDigits: 2 })} kg</strong>`,
            }));
            list.appendChild(createEl('li', {
                html: `<span>10.000 visitas</span><strong>${formatNumber(carbon.co2_per_10000_views_kg, { maximumFractionDigits: 2 })} kg</strong>`,
            }));

            if (carbon.cleaner_than !== undefined) {
                list.appendChild(createEl('li', {
                    html: `<span>Más limpio que</span><strong>${formatNumber(carbon.cleaner_than, { maximumFractionDigits: 0 })}%</strong>`,
                }));
            }

            if (carbon.trees_for_10000_views) {
                list.appendChild(createEl('li', {
                    html: `<span>Equivalente árboles</span><strong>${formatNumber(carbon.trees_for_10000_views, { maximumFractionDigits: 2 })} / 10k visitas</strong>`,
                }));
            }

            carbonBlock.appendChild(list);
            body.appendChild(carbonBlock);
        }

        if (data.green && (data.green.provider || data.green.is_green !== undefined)) {
            const hosting = createEl('div', { className: 'tew-scorecard__hosting' });
            hosting.innerHTML = `
                <h4>Hosting</h4>
                <p>${data.green.is_green ? 'Proveedor con energía renovable' : 'Pendiente de migrar a proveedor verde'}</p>
                ${data.green.provider ? `<span>${data.green.provider}</span>` : ''}
            `;
            body.appendChild(hosting);
        }

        if (body.children.length) {
            card.appendChild(body);
        }

        return card;
    }

    function renderWebsiteCarbon(data = {}, error) {
        const card = createEl('article', { className: 'tew-metric-card tew-metric-card--websitecarbon' });
        card.innerHTML = `<header><h3>Huella de carbono</h3><p>Comparación con sitios web a nivel mundial</p></header>`;

        if (error) {
            card.appendChild(renderErrorState(error));
            return card;
        }

        const body = createEl('div', { className: 'tew-metric-card__body' });
        
        // Destacado principal con contexto
        const cleanerPercentage = formatNumber(data.cleaner_than, { maximumFractionDigits: 0 });
        const highlight = createEl('div', { className: 'tew-carbon-highlight' });
        highlight.innerHTML = `
            <div class="tew-carbon-highlight__stat">
                <span class="tew-carbon-highlight__label">Tu sitio es más limpio que</span>
                <div class="tew-carbon-highlight__value">${cleanerPercentage}<span class="tew-carbon-highlight__unit">%</span></div>
                <span class="tew-carbon-highlight__context">de los sitios web analizados</span>
            </div>
            <div class="tew-carbon-highlight__visual">
                <div class="tew-carbon-bar">
                    <div class="tew-carbon-bar__fill" style="width: ${cleanerPercentage}%"></div>
                    <span class="tew-carbon-bar__marker">Tú</span>
                </div>
                <div class="tew-carbon-bar__labels">
                    <span>Más contaminante</span>
                    <span>Más limpio</span>
                </div>
            </div>
        `;
        body.appendChild(highlight);

        // Grid de métricas con tooltips
        const metricsGrid = createEl('div', { className: 'tew-metric-card__grid tew-metric-card__grid--carbon' });
        metricsGrid.innerHTML = `
            <div class="tew-stat tew-stat--with-tooltip">
                <span class="tew-stat__label">
                    CO₂ por visita
                    <span class="tew-tooltip-icon" data-tooltip="Emisiones de CO₂ generadas cada vez que alguien visita tu página. Incluye transferencia de datos, procesamiento del servidor y renderizado en el dispositivo del usuario.">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4M12 8h.01"></path>
                        </svg>
                    </span>
                </span>
                <strong class="tew-stat__value">${formatNumber(data.co2_per_view, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} g</strong>
            </div>
            <div class="tew-stat tew-stat--with-tooltip">
                <span class="tew-stat__label">
                    Energía renovable
                    <span class="tew-tooltip-icon" data-tooltip="Indica si tu proveedor de hosting utiliza energía 100% renovable o compensa sus emisiones mediante certificados verdes verificados.">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4M12 8h.01"></path>
                        </svg>
                    </span>
                </span>
                <strong class="tew-stat__value">${data.is_green ? '✓ Sí' : '⚠ No verificado'}</strong>
            </div>
        `;
        body.appendChild(metricsGrid);

        // Contexto amigable
        const context = createEl('div', { className: 'tew-carbon-context' });
        const co2Value = parseFloat(data.co2_per_view);
        let contextMessage = '';
        let contextClass = '';
        
        if (co2Value < 0.5) {
            contextMessage = '¡Excelente! Tu sitio tiene una huella muy baja. Estás en el camino correcto hacia la sostenibilidad digital.';
            contextClass = 'tew-carbon-context--excellent';
        } else if (co2Value < 1.0) {
            contextMessage = 'Tu sitio está por debajo del promedio. Con algunas optimizaciones adicionales, podrías reducir aún más tu impacto.';
            contextClass = 'tew-carbon-context--good';
        } else if (co2Value < 2.0) {
            contextMessage = 'Hay margen de mejora. Optimizar imágenes, reducir scripts y migrar a hosting verde pueden marcar una gran diferencia.';
            contextClass = 'tew-carbon-context--average';
        } else {
            contextMessage = 'Tu sitio tiene una huella alta. Es momento de actuar: optimiza recursos, elimina código innecesario y considera hosting verde.';
            contextClass = 'tew-carbon-context--needs-improvement';
        }
        
        context.innerHTML = `
            <svg class="tew-carbon-context__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            <p>${contextMessage}</p>
        `;
        context.className = `tew-carbon-context ${contextClass}`;
        body.appendChild(context);

        if (data.report_url) {
            body.appendChild(createEl('a', {
                className: 'tew-metric-card__link',
                text: 'Ver informe completo en Website Carbon',
                attrs: { href: data.report_url, target: '_blank', rel: 'noopener noreferrer' },
            }));
        }

        card.appendChild(body);
        return card;
    }

    function renderGreenWeb(data = {}, error) {
        const card = createEl('article', { className: 'tew-metric-card tew-metric-card--greenweb' });
        
        // Header con logo de Green Web Foundation
        const header = createEl('header', { className: 'tew-metric-card__header-gwf' });
        header.innerHTML = `
            <img src="${TEWAudit.pluginUrl}assets/img/Green-Web-Foundation-logo.svg" alt="Green Web Foundation" class="tew-gwf-logo" />
        `;
        card.appendChild(header);

        if (error) {
            card.appendChild(renderErrorState(error));
            return card;
        }

        const body = createEl('div', { className: 'tew-gwf-body' });
        
        // Estado de verificación con diseño pro
        const status = createEl('div', { className: 'tew-green-status ' + (data.is_green ? 'is-green' : 'is-unknown') });
        status.innerHTML = `
            <div class="tew-green-status__icon">
                <i class="ph-bold ${data.is_green ? 'ph-seal-check' : 'ph-question'}" aria-hidden="true"></i>
            </div>
            <div class="tew-green-status__content">
                <strong class="tew-green-status__title">${data.is_green ? 'Hosting verde verificado' : 'Estado no verificado'}</strong>
                ${data.hosted_by ? `<p class="tew-green-status__provider"><span class="tew-green-status__label">Proveedor:</span> ${data.hosted_by}</p>` : ''}
                <p class="tew-green-status__description">
                    ${data.is_green 
                        ? 'Tu proveedor utiliza energía 100% renovable o compensa sus emisiones con certificados verificados.' 
                        : 'No hemos podido verificar si tu hosting utiliza energía renovable. Considera migrar a un proveedor certificado como verde.'}
                </p>
            </div>
        `;
        body.appendChild(status);

        // Info adicional sobre la importancia del hosting verde
        const info = createEl('div', { className: 'tew-gwf-info' });
        info.innerHTML = `
            <h4 class="tew-gwf-info__title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                ¿Por qué importa?
            </h4>
            <ul class="tew-gwf-info__list">
                <li>
                    <svg class="tew-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Los centros de datos consumen el <strong>1-2% de la electricidad mundial</strong>
                </li>
                <li>
                    <svg class="tew-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Cambiar a hosting verde reduce <strong>hasta un 70% de la huella</strong> de tu web
                </li>
                <li>
                    <svg class="tew-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Es una de las acciones más <strong>fáciles y rápidas</strong> para mejorar tu impacto
                </li>
            </ul>
        `;
        body.appendChild(info);

        // Link condicional: solo mostrar si NO es verde
        if (!data.is_green) {
            const link = createEl('a', {
                className: 'tew-gwf-link',
                text: 'Transfiere tu website a Replanta',
                attrs: { 
                    href: 'https://replanta.net/transfiere-tu-dominio/', 
                    target: '_blank', 
                    rel: 'noopener noreferrer' 
                },
            });
            body.appendChild(link);
        }

        card.appendChild(body);
        return card;
    }

    function renderActions(container, actions = []) {
        container.innerHTML = '';

        if (!Array.isArray(actions) || !actions.length) {
            // Recomendaciones generosas por defecto
            const defaultActions = [
                {
                    title: 'Optimizar imágenes',
                    impact: 'alto',
                    effort: 'medio',
                    description: 'Comprime y convierte las imágenes a formatos modernos como WebP para reducir significativamente el peso de tu página.',
                    category: 'Rendimiento'
                },
                {
                    title: 'Migrar a hosting verde',
                    impact: 'alto',
                    effort: 'bajo',
                    description: 'Cambia a un proveedor de hosting que utilice energía 100% renovable. Reduce tu huella de carbono sin afectar el rendimiento.',
                    category: 'Sostenibilidad'
                },
                {
                    title: 'Minimizar CSS y JavaScript',
                    impact: 'medio',
                    effort: 'medio',
                    description: 'Elimina espacios, comentarios y código innecesario para reducir el tamaño de tus archivos y acelerar la carga.',
                    category: 'Optimización'
                },
                {
                    title: 'Implementar caché del navegador',
                    impact: 'alto',
                    effort: 'medio',
                    description: 'Configura headers de caché para que los recursos se almacenen localmente y reduzcan las peticiones al servidor.',
                    category: 'Performance'
                },
                {
                    title: 'Lazy loading para imágenes',
                    impact: 'medio',
                    effort: 'bajo',
                    description: 'Carga las imágenes solo cuando el usuario las necesita, mejorando los tiempos de carga inicial.',
                    category: 'Experiencia'
                },
                {
                    title: 'Comprimir recursos con Gzip',
                    impact: 'medio',
                    effort: 'bajo',
                    description: 'Habilita la compresión Gzip en tu servidor para reducir el tamaño de transferencia hasta un 70%.',
                    category: 'Optimización'
                }
            ];
            actions = defaultActions;
        }

        const list = createEl('div', { className: 'tew-actions-grid' });
        actions.forEach((action, index) => {
            const item = createEl('article', { className: 'tew-action-card' });
            
            // Header mejorado con categoría
            const header = createEl('header', { className: 'tew-action-card__header' });
            const titleGroup = createEl('div', { className: 'tew-action-title-group' });
            
            if (action.category) {
                titleGroup.appendChild(createEl('span', { 
                    className: 'tew-action-category',
                    text: action.category 
                }));
            }
            
            titleGroup.appendChild(createEl('h4', { text: action.title }));
            header.appendChild(titleGroup);
            
            const badges = createEl('div', { className: 'tew-action-badges' });
            badges.appendChild(impactBadge(action.impact));
            badges.appendChild(effortBadge(action.effort));
            header.appendChild(badges);
            
            item.appendChild(header);
            
            // Descripción mejorada
            const content = createEl('div', { className: 'tew-action-content' });
            content.appendChild(createEl('p', { 
                className: 'tew-action-description',
                text: action.description 
            }));
            
            // Métricas adicionales si están disponibles
            if (action.potential_savings || action.co2_reduction) {
                const metrics = createEl('div', { className: 'tew-action-metrics' });
                
                if (action.potential_savings) {
                    metrics.appendChild(createEl('span', {
                        className: 'tew-action-metric',
                        html: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M13 3L4 14h6.5l-.5 4 9-11h-6.5L13 3z" fill="currentColor"/></svg> ${action.potential_savings}`
                    }));
                }
                
                if (action.co2_reduction) {
                    metrics.appendChild(createEl('span', {
                        className: 'tew-action-metric',
                        html: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.9.66C7.58 17.19 9.54 12.29 17 11c1.66-.34 3 .65 3 2.5 0 2.21-2.24 4.5-5 4.5-1.4 0-2.6-.83-3.2-2h-2.1c.7 2.15 2.77 3.5 5.3 3.5 3.45 0 7-2.19 7-6.5C22 9.64 20.09 8 17 8z" fill="currentColor"/></svg> -${action.co2_reduction}`
                    }));
                }
                
                content.appendChild(metrics);
            }
            
            item.appendChild(content);
            list.appendChild(item);
        });

        // Header de la sección mejorado
        const sectionHeader = createEl('div', { className: 'tew-actions-header' });
        sectionHeader.innerHTML = `
            <div class="tew-actions-intro">
                <h3>Recomendaciones sostenibles</h3>
                <p>Acciones priorizadas para mejorar el rendimiento y reducir la huella ambiental de tu sitio web.</p>
            </div>
            <div class="tew-actions-legend">
                <span class="tew-legend-item">
                    <span class="tew-legend-dot tew-legend-dot--impact"></span>
                    Impacto ambiental
                </span>
                <span class="tew-legend-item">
                    <span class="tew-legend-dot tew-legend-dot--effort"></span>
                    Esfuerzo técnico
                </span>
            </div>
        `;
        
        container.appendChild(sectionHeader);
        container.appendChild(list);
    }

    function renderSnapshots(container, snapshots = []) {
        container.innerHTML = '';

        if (!Array.isArray(snapshots) || !snapshots.length) {
            container.hidden = true;
            return;
        }

        container.hidden = false;
        const grid = createEl('div', { className: 'tew-gallery-grid' });

        snapshots.forEach((snapshot) => {
            const card = createEl('article', { className: 'tew-gallery-card' });
            if (snapshot.image) {
                const img = createEl('img', {
                    attrs: {
                        src: snapshot.image,
                        alt: snapshot.label || 'Captura del informe',
                        loading: 'lazy',
                    },
                });
                card.appendChild(img);
            } else {
                const placeholder = createEl('div', { className: 'tew-gallery-placeholder' });
                
                // Iconos específicos según el servicio (Phosphor Icons)
                let icon = 'ph-image';
                if (snapshot.service === 'websitecarbon') icon = 'ph-cloud';
                if (snapshot.service === 'greenweb') icon = 'ph-leaf';
                if (snapshot.service?.includes('pagespeed')) icon = 'ph-gauge';
                
                placeholder.innerHTML = `<i class="ph-bold ${icon}" aria-hidden="true"></i>`;
                card.appendChild(placeholder);
            }

            const footer = createEl('footer', { className: 'tew-gallery-card__footer' });
            footer.innerHTML = `<h4>${snapshot.label || 'Recurso'}</h4>`;
            if (snapshot.url) {
                const link = createEl('a', {
                    text: 'Abrir informe',
                    className: 'tew-gallery-card__link',
                    attrs: { 
                        href: snapshot.url, 
                        target: '_blank', 
                        rel: 'noopener noreferrer',
                        'aria-label': `Abrir ${snapshot.label || 'informe'} en nueva pestaña`
                    },
                });
                
                // Asegurar que el enlace funcione correctamente
                link.addEventListener('click', (e) => {
                    e.stopPropagation();
                    // Verificar que la URL es válida antes de abrir
                    if (snapshot.url && (snapshot.url.startsWith('http://') || snapshot.url.startsWith('https://'))) {
                        window.open(snapshot.url, '_blank', 'noopener,noreferrer');
                    }
                });
                
                footer.appendChild(link);
            }
            card.appendChild(footer);

            grid.appendChild(card);
        });

        container.appendChild(grid);
    }

    function renderErrorState(message) {
        const div = createEl('div', { className: 'tew-error-state' });
        div.innerHTML = `
            <i class="ph-bold ph-warning-circle" aria-hidden="true"></i>
            <div>
                <strong>Servicio no disponible</strong>
                <p>${message || 'No fue posible obtener datos en este momento.'}</p>
            </div>
        `;
        return div;
    }

    function parseTimestamp(value) {
        if (!value) {
            return null;
        }

        const iso = value.includes('T') ? value : `${value.replace(' ', 'T')}Z`;
        const date = new Date(iso);

        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date;
    }

    function formatDate(value) {
        const date = typeof value === 'string' ? parseTimestamp(value) : value;
        if (!date) {
            return '—';
        }

        return new Intl.DateTimeFormat('es-ES', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(date);
    }

    function renderHistory(container, history = []) {
        if (!container) {
            return;
        }

        const list = container.querySelector('.tew-report-history__list');
        if (!list) {
            return;
        }

        list.innerHTML = '';

        if (!Array.isArray(history) || !history.length) {
            container.hidden = true;
            return;
        }

        history.forEach((item) => {
            const li = createEl('li', { className: 'tew-report-history__item' });
            const link = createEl('a', {
                className: 'tew-report-history__link',
                attrs: {
                    href: item.permalink,
                },
            });

            const label = createEl('span', {
                className: 'tew-report-history__date',
                text: formatDate(item.generated),
            });

            const score = createEl('span', {
                className: 'tew-report-history__score',
                text: item.score ? `${formatNumber(item.score)} / 100` : '—',
            });

            link.appendChild(label);
            link.appendChild(score);
            li.appendChild(link);
            list.appendChild(li);
        });

        container.hidden = false;
    }

    function renderShare(container, metadata = {}) {
        if (!container) {
            return;
        }

        const link = container.querySelector('[data-tew-share-link]');
        const copyButton = container.querySelector('[data-tew-copy-button]');
        const shareUrl = metadata.share_url || window.location.href;

        if (!shareUrl) {
            container.hidden = true;
            return;
        }

        if (link) {
            link.href = shareUrl;
            const urlSpan = link.querySelector('.tew-share-link__url');
            if (urlSpan) {
                urlSpan.textContent = shareUrl;
            }
        }

        if (copyButton) {
            copyButton.dataset.shareUrl = shareUrl;
        }

        container.hidden = false;
    }

    /**
     * Renderiza formulario de captura de email (opcional, no intrusivo).
     */
    function renderEmailCapture(container, reportId) {
        if (!container || !reportId) {
            return;
        }

        container.innerHTML = `
            <div class="tew-email-capture">
                <div class="tew-email-capture__header">
                    <i class="ph-bold ph-envelope tew-email-icon"></i>
                    <div class="tew-email-capture__text">
                        <h3>¿Quieres mejorar estos números?</h3>
                        <p>Recibe un plan de acción personalizado <strong>100% GRATIS</strong> para reducir tu huella digital y mejorar el rendimiento.</p>
                    </div>
                </div>
                
                <form class="tew-email-form" data-report-id="${reportId}">
                    <div class="tew-email-form__group">
                        <input 
                            type="email" 
                            name="email" 
                            placeholder="tu@email.com" 
                            required 
                            class="tew-email-input"
                            autocomplete="email"
                        >
                        <button type="submit" class="tew-email-submit">
                            <i class="ph-bold ph-paper-plane-tilt"></i>
                            Recibir recomendaciones
                        </button>
                    </div>
                    
                    <!-- Cloudflare Turnstile -->
                    <div class="cf-turnstile tew-turnstile" data-sitekey="${config.turnstileSiteKey || '0x4AAAAAAAg_eSample'}"></div>
                    
                    <p class="tew-email-privacy">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Sin spam
                        <span class="tew-privacy-separator">·</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Solo consejos útiles
                        <span class="tew-privacy-separator">·</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Puedes darte de baja cuando quieras
                    </p>

                    <div class="tew-email-feedback" hidden></div>
                </form>

                <div class="tew-email-success" hidden>
                    <i class="ph-bold ph-check-circle tew-success-icon"></i>
                    <div>
                        <h4>¡Perfecto! Revisa tu email</h4>
                        <p>Te hemos enviado tu plan de acción personalizado. Si no lo ves, revisa la carpeta de spam.</p>
                    </div>
                </div>
            </div>
        `;

        // Cargar script de Cloudflare Turnstile
        if (!document.querySelector('script[src*="turnstile"]')) {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        // Manejar envío del formulario
        const form = container.querySelector('.tew-email-form');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const emailInput = form.querySelector('input[name="email"]');
                const submitBtn = form.querySelector('.tew-email-submit');
                const feedbackEl = form.querySelector('.tew-email-feedback');
                const successEl = container.querySelector('.tew-email-success');
                const turnstileResponse = form.querySelector('.cf-turnstile textarea')?.value;

                if (!turnstileResponse) {
                    showEmailFeedback(feedbackEl, 'error', 'Por favor, completa la verificación de seguridad.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="tew-spinner"></span> Enviando...';

                try {
                    const response = await fetch(config.emailEndpoint || '/wp-json/tew/v1/save-email', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': config.nonce || '',
                        },
                        body: JSON.stringify({
                            report_id: reportId,
                            email: emailInput.value,
                            turnstile_token: turnstileResponse,
                        }),
                    });

                    const data = await response.json();

                    if (data.success) {
                        form.hidden = true;
                        successEl.hidden = false;
                    } else {
                        throw new Error(data.message || 'Error al guardar el email');
                    }
                } catch (error) {
                    showEmailFeedback(feedbackEl, 'error', error.message || 'Error al enviar. Inténtalo de nuevo.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Recibir recomendaciones';
                }
            });
        }
    }

    function showEmailFeedback(feedbackEl, type, message) {
        if (!feedbackEl) return;
        
        feedbackEl.className = `tew-email-feedback tew-email-feedback--${type}`;
        feedbackEl.textContent = message;
        feedbackEl.hidden = false;

        if (type === 'success') {
            setTimeout(() => {
                feedbackEl.hidden = true;
            }, 5000);
        }
    }

    function initShareInteractions(section) {
        const copyButtons = section.querySelectorAll('[data-tew-copy-button]');
        if (!copyButtons.length) {
            return;
        }

        copyButtons.forEach((button) => {
            if (button.dataset.copyBound) {
                return;
            }

            button.dataset.copyBound = '1';
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const shareUrl = button.dataset.shareUrl || button.href;

                if (!shareUrl) {
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        button.classList.add('is-copied');
                        setTimeout(() => button.classList.remove('is-copied'), 1800);
                    }).catch(() => {
                        window.open(shareUrl, '_blank', 'noopener');
                    });
                } else {
                    window.open(shareUrl, '_blank', 'noopener');
                }
            });
        });
    }

    function getLoadingMessage() {
        const naturalistQuotes = [
            {
                text: "En la naturaleza salvaje está la preservación del mundo.",
                author: "Henry David Thoreau",
                context: "Analizando métricas con la calma de Walden Pond..."
            },
            {
                text: "En cada paseo por la naturaleza, uno recibe mucho más de lo que busca.",
                author: "John Muir",
                context: "Recopilando datos de rendimiento y carbono..."
            },
            {
                text: "Una brizna de hierba es una obra tan perfecta como una estrella.",
                author: "Walt Whitman", 
                context: "Examinando cada byte con precisión cósmica..."
            },
            {
                text: "La naturaleza nunca está apurada; átomo por átomo, poco a poco, hace su trabajo.",
                author: "Ralph Waldo Emerson",
                context: "Consultando PageSpeed y Website Carbon..."
            },
            {
                text: "Solo podemos estar seguros de que la ética de la tierra cambia el rol del Homo sapiens de conquistador de la comunidad terrestre a simple miembro de ella.",
                author: "Aldo Leopold",
                context: "Midiendo el impacto para las próximas generaciones..."
            },
            {
                text: "Las montañas están llamando y debo ir.",
                author: "John Muir",
                context: "Escalando las APIs de sostenibilidad web..."
            },
            {
                text: "En cada caminar con la naturaleza, uno recibe mucho más de lo que busca.",
                author: "Rachel Carson",
                context: "Calculando la huella digital de tu sitio..."
            },
            {
                text: "Fui al bosque porque deseaba vivir deliberadamente.",
                author: "Henry David Thoreau",
                context: "Navegando los datos con intención consciente..."
            }
        ];

        const quote = naturalistQuotes[Math.floor(Math.random() * naturalistQuotes.length)];
        return {
            main: quote.context,
            quote: `"${quote.text}" — ${quote.author}`
        };
    }

    function setFeedback(feedbackEl, type, message) {
        if (!feedbackEl) {
            return;
        }
        feedbackEl.dataset.state = type;
        const text = $('p', feedbackEl);
        
        if (type === 'loading') {
            const loadingMsg = getLoadingMessage();
            if (text) {
                text.innerHTML = `
                    <strong>${loadingMsg.main}</strong>
                    <em class="tew-quote">${loadingMsg.quote}</em>
                `;
            }
        } else {
            if (text) {
                text.textContent = message;
            }
        }
    }

    async function fetchAudit(url, refresh = false, bypass = false, turnstileToken = null, fromCampaign = false) {
        if (!config.endpoint) {
            throw new Error('Sin endpoint configurado. Revisa el shortcode.');
        }

        const body = { url, refresh };
        if (bypass) {
            body.bypass_validation = true;
        }
        if (turnstileToken) {
            body.cf_turnstile_response = turnstileToken;
        }
        if (fromCampaign) {
            body.from_campaign = true;
        }

        const response = await fetch(config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce || '',
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload?.message || config.messages?.error || 'No se pudo completar la auditoría.');
        }

        return response.json();
    }

    async function fetchReport(reportId, endpoint) {
        const url = `${endpoint.replace(/\/$/, '')}/${reportId}`;
        const response = await fetch(url);
        const isJson = response.headers.get('content-type')?.includes('json');
        const payload = isJson ? await response.json().catch(() => null) : null;

        if (!response.ok) {
            throw new Error(payload?.message || config.messages?.error || 'No se pudo cargar el informe.');
        }

        return payload;
    }

    function parseInitialReport(section) {
        console.log('TEW - parseInitialReport called with section:', section);
        const raw = section.dataset.initialReport;
        console.log('TEW - Raw data length:', raw ? raw.length : 'null');
        
        if (!raw) {
            console.log('TEW - No raw data found in dataset.initialReport');
            return null;
        }

        try {
            console.log('TEW - Attempting to parse JSON...');
            const parsed = JSON.parse(raw);
            console.log('TEW - JSON parsed successfully, type:', typeof parsed);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (error) {
            console.error('TEW - Error parsing initial report:', error);
            return null;
        }
    }

    function initReportView(section) {
        console.log('TEW - initReportView called with section:', section);
        
        if (!section) {
            console.log('TEW - No section provided to initReportView');
            return;
        }

        const reportId = section.dataset.reportId;
        if (!reportId) {
            console.log('TEW - No reportId found in section');
            return;
        }
        
        console.log('TEW - Processing report:', reportId);

        let endpoint, feedback, results, summary, metrics, gallery, actions, history, shareBlocks, emailCapture;

        try {
            endpoint = section.dataset.endpoint || config.reportEndpoint;
            feedback = $('.tew-snapshot__feedback', section);
            results = $('.tew-snapshot__results', section);
            summary = $('[data-tew-summary]', section);
            metrics = $('[data-tew-metrics]', section);
            gallery = $('[data-tew-gallery]', section);
            actions = $('[data-tew-actions]', section);
            history = section.querySelector('[data-tew-history]');
            shareBlocks = section.querySelectorAll('[data-tew-share]');
            emailCapture = section.querySelector('[data-tew-email-capture]');
            
            console.log('TEW - All DOM queries completed successfully');
        } catch (error) {
            console.error('TEW - Error during DOM queries:', error);
            return;
        }

        console.log('TEW - DOM elements found:', {
            feedback: !!feedback,
            results: !!results,
            summary: !!summary,
            metrics: !!metrics,
            gallery: !!gallery,
            actions: !!actions,
            history: !!history,
            shareBlocks: shareBlocks.length,
            emailCapture: !!emailCapture
        });

        function renderPayload(payload) {
            console.log('TEW - renderPayload called with payload:', payload);
            
            if (!payload || typeof payload !== 'object') {
                console.log('TEW - Invalid payload, returning');
                return;
            }

            console.log('TEW - Starting to render components...');
            
            try {
                console.log('TEW - Rendering summary...');
                renderSummary(summary, payload.summary || {});
                console.log('TEW - Summary rendered successfully');
            } catch (error) {
                console.error('TEW - Error rendering summary:', error);
            }
            
            try {
                console.log('TEW - Rendering metrics...');
                renderMetrics(metrics, payload.metrics || {}, payload.errors || {});
                console.log('TEW - Metrics rendered successfully');
            } catch (error) {
                console.error('TEW - Error rendering metrics:', error);
            }
            
            try {
                console.log('TEW - Rendering snapshots...');
                renderSnapshots(gallery, payload.snapshots || []);
                console.log('TEW - Snapshots rendered successfully');
            } catch (error) {
                console.error('TEW - Error rendering snapshots:', error);
            }
            
            try {
                console.log('TEW - Rendering actions...');
                renderActions(actions, payload.summary?.prioritized_actions || []);
                console.log('TEW - Actions rendered successfully');
            } catch (error) {
                console.error('TEW - Error rendering actions:', error);
            }
            
            try {
                console.log('TEW - Rendering history...');
                renderHistory(history, payload.metadata?.history || []);
                console.log('TEW - History rendered successfully');
            } catch (error) {
                console.error('TEW - Error rendering history:', error);
            }
            
            try {
                console.log('TEW - Rendering share blocks...');
                shareBlocks.forEach((share) => renderShare(share, payload.metadata || {}));
                console.log('TEW - Share blocks rendered successfully');
            } catch (error) {
                console.error('TEW - Error rendering share blocks:', error);
            }
            
            // Renderizar form de email si hay report_id
            try {
                if (emailCapture && payload.metadata?.report_id) {
                    console.log('TEW - Rendering email capture...');
                    renderEmailCapture(emailCapture, payload.metadata.report_id);
                    console.log('TEW - Email capture rendered successfully');
                }
            } catch (error) {
                console.error('TEW - Error rendering email capture:', error);
            }

            console.log('TEW - Hiding feedback and showing results...');
            hideElement(feedback);
            showElement(results);
            console.log('TEW - Initializing share interactions...');
            initShareInteractions(section);
            console.log('TEW - renderPayload completed successfully!');
        }

        console.log('TEW - renderPayload function defined, continuing...');
        
        console.log('TEW - About to parse initial report...');
        const initial = parseInitialReport(section);
        console.log('TEW - parseInitialReport result:', initial);
        
        if (initial) {
            console.log('TEW - Initial report found, calling renderPayload...');
            renderPayload(initial);
        } else {
            console.log('TEW - No initial report, showing loading state...');
            setFeedback(feedback, 'loading', config.messages?.loading || 'Cargando informe...');
            showElement(feedback);
            hideElement(results);
        }

        // Solo hacer fetch si no hay reporte inicial
        if (!endpoint || initial) {
            return;
        }

        fetchReport(reportId, endpoint)
            .then((payload) => {
                renderPayload(payload);
            })
            .catch((error) => {
                setFeedback(feedback, 'error', error.message || config.messages?.error);
                showElement(feedback);
                if (!initial) {
                    hideElement(results);
                }
            });
    }

    function initSnapshot(root) {
        if (!root) {
            return;
        }

        const form = $('.tew-snapshot__form', root);
        const input = $('#tew-snapshot-url', root);
        const feedback = $('.tew-snapshot__feedback', root);
        const results = $('.tew-snapshot__results', root);
        const summary = $('[data-tew-summary]', root);
        const metrics = $('[data-tew-metrics]', root);
        const gallery = $('[data-tew-gallery]', root);
        const actions = $('[data-tew-actions]', root);

        // Verificar auto-start si viene de redirección
        const autoStartUrl = root.dataset.autoStart;
        const skipTurnstile = root.dataset.skipTurnstile === '1';
        if (autoStartUrl) {
            console.log('TEW - Auto-starting analysis for URL:', autoStartUrl);
            console.log('TEW - Skip Turnstile:', skipTurnstile);
            input.value = autoStartUrl;
            // Marcar el formulario para saltar Turnstile
            if (skipTurnstile) {
                form.dataset.skipTurnstile = 'true';
            }
            // Iniciar análisis automáticamente después de un breve delay
            setTimeout(() => {
                form.dispatchEvent(new Event('submit'));
            }, 500);
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const url = input.value.trim();
            if (!url) {
                setFeedback(feedback, 'error', 'Introduce una URL válida.');
                showElement(feedback);
                hideElement(results);
                return;
            }

            // Detectar si es shortcode (tiene formulario inline) o desde campaña
            const isShortcode = root && root.dataset.formOnly === undefined; // Si NO tiene data-tew-form-only, es el shortcode principal
            const isFromCampaign = form.dataset.fromCampaign === '1' || root.dataset.fromCampaign === '1';

            // Verificar Turnstile si está presente Y NO viene de redirect Y NO viene de campaña
            const shouldSkipTurnstile = form.dataset.skipTurnstile === 'true' || form.dataset.fromRedirect === '1' || isFromCampaign;
            const turnstileWidget = form.querySelector('.cf-turnstile');
            if (turnstileWidget && typeof window.turnstile !== 'undefined' && !shouldSkipTurnstile) {
                const turnstileResponse = window.turnstile.getResponse(turnstileWidget);
                if (!turnstileResponse) {
                    setFeedback(feedback, 'error', '🔒 Completa la verificación de seguridad antes de continuar.');
                    showElement(feedback);
                    hideElement(results);
                    return;
                }
            }

            // Verificar si está en modo bypass
            const bypassMode = form.dataset.bypass === 'true';
            
            // Fase 1: Validación inicial (skip si bypass activo)
            if (!bypassMode) {
                setFeedback(feedback, 'loading', '🔍 Verificando que el sitio web existe...');
            } else {
                setFeedback(feedback, 'loading', '🔍 Analizando sitio protegido (bypass activado)...');
                // Limpiar el bypass después de usar
                delete form.dataset.bypass;
            }
            showElement(feedback);
            hideElement(results);
            form.classList.add('is-loading');

            try {
                // Obtener token de Turnstile si está presente
                let turnstileToken = null;
                const turnstileWidget = form.querySelector('.cf-turnstile');
                if (turnstileWidget && typeof window.turnstile !== 'undefined') {
                    turnstileToken = window.turnstile.getResponse(turnstileWidget);
                }
                
                const audit = await fetchAudit(url, false, bypassMode, turnstileToken, isFromCampaign);
                
                // Si es SHORTCODE y el informe se guardó: redirigir a la página de informe
                if (isShortcode && audit.metadata && audit.metadata.report_id && audit.metadata.share_url) {
                    setFeedback(feedback, 'loading', '✅ Informe generado. Redirigiendo...');
                    setTimeout(() => {
                        window.location.href = audit.metadata.share_url;
                    }, 800);
                    return;
                }
                
                // Si es desde CAMPAÑA: mostrar inline (no redirigir)
                // Fase 2: Si llegamos aquí, la validación pasó
                setFeedback(feedback, 'loading', '📊 Analizando rendimiento y sostenibilidad...');
                
                renderSummary(summary, audit.summary);
                renderMetrics(metrics, audit.metrics, audit.errors);
                renderSnapshots(gallery, audit.snapshots);
                renderActions(actions, audit.summary?.prioritized_actions);
                
                if (audit.errors && Object.keys(audit.errors).length) {
                    setFeedback(feedback, 'warning', config.messages?.partial || 'Informe parcial. Algunos servicios no respondieron.');
                } else {
                    hideElement(feedback);
                }
                showElement(results);
            } catch (error) {
                // Mejorar mensajes de error según el código
                let errorMessage = error.message || config.messages?.error || 'Error al analizar el sitio web.';
                
                // Si el error viene de la pre-validación, mostrará un mensaje específico
                if (error.code === 'tew_url_not_accessible' || error.data?.details) {
                    // Crear mensaje con botón de bypass para dominios protegidos
                    const details = error.data?.details || {};
                    if (details.status_code === 403) {
                        errorMessage = `
                            <div class="tew-error-403">
                                <p><strong>🛡️ Sitio protegido detectado</strong></p>
                                <p>${error.message}</p>
                                <p><small>El firewall de tu sitio está bloqueando el análisis automatizado. Esto es normal en sitios con buena seguridad.</small></p>
                                <button class="tew-bypass-btn" onclick="this.closest('form').dataset.bypass='true'; this.closest('form').querySelector('.tew-submit').click(); this.style.display='none';">
                                    <i class="ph-bold ph-shield-check"></i>
                                    Analizar de todas formas (bypass)
                                </button>
                            </div>
                        `;
                    } else {
                        errorMessage = error.message;
                    }
                } else if (errorMessage.includes('404')) {
                    errorMessage = '❌ La página no existe. Verifica la URL.';
                } else if (errorMessage.includes('timeout')) {
                    errorMessage = '⏱️ El sitio tardó demasiado en responder. Intenta de nuevo.';
                } else if (errorMessage.includes('DNS') || errorMessage.includes('resolve')) {
                    errorMessage = '🌐 No se puede encontrar el dominio. Verifica que el sitio existe.';
                } else if (errorMessage.includes('Verificación de seguridad fallida')) {
                    errorMessage = '🔒 Verificación de seguridad fallida. Por favor, completa el captcha de nuevo.';
                }
                
                setFeedback(feedback, 'error', errorMessage);
                showElement(feedback);
                hideElement(results);
                
                // Resetear Turnstile después de un error para permitir nuevo intento
                const turnstileWidget = form.querySelector('.cf-turnstile');
                if (turnstileWidget && typeof window.turnstile !== 'undefined') {
                    try {
                        window.turnstile.reset(turnstileWidget);
                    } catch (e) {
                        console.warn('TEW - No se pudo resetear Turnstile:', e);
                    }
                }
            } finally {
                form.classList.remove('is-loading');
                
                // También resetear Turnstile después de cada análisis
                // para permitir análisis adicionales sin recargar la página
                const turnstileWidget = form.querySelector('.cf-turnstile');
                if (turnstileWidget && typeof window.turnstile !== 'undefined') {
                    setTimeout(() => {
                        try {
                            window.turnstile.reset(turnstileWidget);
                            console.log('TEW - Turnstile reseteado para nuevo análisis');
                        } catch (e) {
                            console.warn('TEW - No se pudo resetear Turnstile:', e);
                        }
                    }, 1000); // Delay de 1 segundo para permitir que se complete el análisis
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        console.log('TEW - DOM loaded, initializing...');
        
        const snapshots = document.querySelectorAll('[data-tew-snapshot]');
        const reportViews = document.querySelectorAll('[data-tew-report-view]');
        
        console.log('TEW - Found snapshots:', snapshots.length);
        console.log('TEW - Found report views:', reportViews.length);
        
        snapshots.forEach(initSnapshot);
        reportViews.forEach(initReportView);
        
        // Inicializar botón compartir mejorado
        console.log('TEW - Initializing share button...');
        initShareButton();
        initPdfDownload();
    });
    
    // Función para manejar el botón compartir mejorado
    function initShareButton() {
        console.log('TEW - initShareButton called');
        
        const shareTrigger = document.querySelector('[data-tew-share-trigger]');
        const shareModal = document.querySelector('[data-tew-share]');
        
        console.log('TEW - Share elements found:', {
            shareTrigger: !!shareTrigger,
            shareModal: !!shareModal,
            shareTriggerElement: shareTrigger
        });
        
        if (!shareTrigger || !shareModal) {
            console.log('TEW - Share elements not found, skipping share button init');
            return;
        }
        
        // Crear estructura del modal
        shareModal.innerHTML = `
            <div class="tew-share-modal">
                <div class="tew-share-modal__header">
                    <h3>Compartir informe</h3>
                    <button class="tew-share-modal__close" aria-label="Cerrar">
                        <i class="ph-bold ph-x"></i>
                    </button>
                </div>
                <div class="tew-share-modal__content">
                    <p>Comparte este análisis eco-performance con tu equipo o clientes:</p>
                    <div class="tew-share-input">
                        <input type="text" value="${window.location.href}" readonly class="tew-share-url" aria-label="URL del informe">
                        <button class="tew-share-copy" data-url="${window.location.href}">
                            <i class="ph-bold ph-copy"></i>
                            Copiar
                        </button>
                    </div>
                    <div class="tew-share-social">
                        <a href="https://twitter.com/intent/tweet?url=${encodeURIComponent(window.location.href)}&text=${encodeURIComponent('Mi informe eco-performance:')}" target="_blank" rel="noopener" class="tew-share-social__btn" aria-label="Compartir en X / Twitter">
                            <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" width="18" height="18"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(window.location.href)}" target="_blank" rel="noopener" class="tew-share-social__btn" aria-label="Compartir en LinkedIn">
                            <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" width="18" height="18"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                        <a href="https://api.whatsapp.com/send?text=${encodeURIComponent('Mira mi informe eco-performance: ' + window.location.href)}" target="_blank" rel="noopener" class="tew-share-social__btn" aria-label="Compartir en WhatsApp">
                            <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" width="18" height="18"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        // Eventos para abrir/cerrar modal
        shareTrigger.addEventListener('click', (e) => {
            e.preventDefault();
            shareModal.classList.add('is-visible');
        });
        
        // Cerrar modal
        const closeButton = shareModal.querySelector('.tew-share-modal__close');
        closeButton.addEventListener('click', () => {
            shareModal.classList.remove('is-visible');
        });
        
        // Cerrar al hacer click fuera del modal
        shareModal.addEventListener('click', (e) => {
            if (e.target === shareModal) {
                shareModal.classList.remove('is-visible');
            }
        });

        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && shareModal.classList.contains('is-visible')) {
                shareModal.classList.remove('is-visible');
                shareTrigger.focus();
            }
        });
        
        // Función copiar URL
        const copyButton = shareModal.querySelector('.tew-share-copy');
        copyButton.addEventListener('click', async () => {
            const url = copyButton.dataset.url;
            
            try {
                await navigator.clipboard.writeText(url);
                copyButton.innerHTML = `
                    <i class="ph-bold ph-check"></i>
                    ¡Copiado!
                `;
                copyButton.classList.add('is-copied');
                
                setTimeout(() => {
                    copyButton.innerHTML = `
                        <i class="ph-bold ph-copy"></i>
                        Copiar
                    `;
                    copyButton.classList.remove('is-copied');
                }, 2000);
            } catch (err) {
                // Fallback para navegadores sin clipboard API
                const input = shareModal.querySelector('.tew-share-url');
                input.select();
                document.execCommand('copy');
                
                copyButton.textContent = '¡Copiado!';
                setTimeout(() => {
                    copyButton.innerHTML = `
                        <i class="ph-bold ph-copy"></i>
                        Copiar
                    `;
                }, 2000);
            }
        });
    }

    // =========================================================================
    //  PDF Download — html2pdf.js (loaded on demand from CDN)
    // =========================================================================
    function initPdfDownload() {
        const pdfTrigger = document.querySelector('[data-tew-pdf-trigger]');
        if (!pdfTrigger) return;

        pdfTrigger.addEventListener('click', async (e) => {
            e.preventDefault();
            
            // Disable button while generating
            pdfTrigger.disabled = true;
            const originalHTML = pdfTrigger.innerHTML;
            pdfTrigger.innerHTML = '<i class="ph-bold ph-spinner" aria-hidden="true"></i><span class="tew-toolbar-btn__label">Generando…</span>';
            pdfTrigger.classList.add('is-loading');

            try {
                // Load html2pdf.js on demand if not already loaded
                if (typeof html2pdf === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.2/html2pdf.bundle.min.js';
                        script.integrity = 'sha512-MpDFIChbcXl2QgipQrt1VcPHMldRILetapBl5MPCA9Y8r7qvlwx1/Mc9hNTzY+kS5kX6PdoDq41ws1HiVNLdZA==';
                        script.crossOrigin = 'anonymous';
                        script.onload = resolve;
                        script.onerror = () => reject(new Error('No se pudo cargar la librería PDF'));
                        document.head.appendChild(script);
                    });
                }

                // Build PDF from the report container
                const reportContainer = document.querySelector('[data-tew-report-container]');
                if (!reportContainer) throw new Error('Contenedor del informe no encontrado');

                // Extract domain for filename
                const titleEl = reportContainer.querySelector('.tew-report-view__title');
                const titleText = titleEl ? titleEl.textContent.trim() : 'informe-eco';
                const filename = titleText
                    .toLowerCase()
                    .replace(/[^a-z0-9áéíóúñ]+/g, '-')
                    .replace(/(^-|-$)/g, '') + '.pdf';

                // Activate print-optimised flat layout via CSS class
                reportContainer.classList.add('tew-printing');

                // Allow a frame for the layout reflow before html2canvas captures
                await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

                const opt = {
                    margin:       [10, 10, 10, 10],
                    filename:     filename,
                    image:        { type: 'jpeg', quality: 0.92 },
                    html2canvas:  {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        scrollY: -window.scrollY,
                        windowWidth: reportContainer.scrollWidth
                    },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak:    { mode: ['css', 'legacy'], avoid: ['.tew-metric-card', '.tew-narrative-card', '.tew-performance-card', '.tew-tech-summary', '.tew-gallery-card', '.tew-action-card', '.tew-scorecard-overview', '.tew-scorecard__carbon'] }
                };

                await html2pdf().set(opt).from(reportContainer).save();

                // Remove print class to restore interactive layout
                reportContainer.classList.remove('tew-printing');
            } catch (err) {
                console.error('TEW PDF Error:', err);
                alert('Error al generar el PDF. Inténtalo de nuevo.');
            } finally {
                // Always restore interactive layout
                const rc = document.querySelector('[data-tew-report-container]');
                if (rc) rc.classList.remove('tew-printing');
                pdfTrigger.disabled = false;
                pdfTrigger.innerHTML = originalHTML;
                pdfTrigger.classList.remove('is-loading');
            }
        });
    }
})();
