/**
 * Replanta AI Chat — Web Component
 * Vanilla JS, no dependencies, ~8KB minified.
 */

(function () {
  'use strict';

  const cfg = window.replantaAiChat || {};

  class ReplantaAiChat extends HTMLElement {
    constructor() {
      super();
      this._sessionId = null;
      this._messages  = [];
      this._open      = false;
    }

    connectedCallback() {
      this._apiUrl       = this.dataset.apiUrl  || cfg.apiUrl   || '/wp-json/replanta/v1';
      this._nonce        = this.dataset.nonce   || cfg.nonce    || '';
      this._assistantName= this.dataset.assistantName || cfg.assistantName || 'Asistente';
      this._primaryColor = cfg.primaryColor || '#2d6a4f';
      this._position     = cfg.position     || 'bottom-right';
      this._welcome      = cfg.welcomeMsg   || '¡Hola! ¿En qué puedo ayudarte?';
      this._i18n         = cfg.i18n         || {};

      this._render();
      this._bindEvents();
      this._applyPosition();
      this._applyColor();

      // Restore session
      this._sessionId = sessionStorage.getItem('replanta_session_id') || null;

      this._startPulseTimer();
    }

    // ── Render ─────────────────────────────────────────────────────────────

    _render() {
      this.innerHTML = `
        <button class="rac-bubble" aria-label="${this._assistantName}" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          <svg class="rac-close-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" style="display:none">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
          <span class="rac-badge" style="display:none">1</span>
        </button>

        <div class="rac-panel" role="dialog" aria-label="${this._assistantName}" aria-hidden="true">
          <div class="rac-header">
            <div class="rac-avatar" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
            </div>
            <div class="rac-header-info">
              <span class="rac-name">${this._assistantName}</span>
              <span class="rac-status">En línea</span>
            </div>
            <button class="rac-close-btn" aria-label="Cerrar chat">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

          <div class="rac-messages" role="log" aria-live="polite" aria-atomic="false"></div>

          <div class="rac-footer">
            <form class="rac-form" novalidate>
              <textarea
                class="rac-input"
                placeholder="${this._i18n.placeholder || 'Escribe tu pregunta...'}"
                rows="1"
                maxlength="2000"
                aria-label="${this._i18n.placeholder || 'Escribe tu pregunta...'}"
              ></textarea>
              <button type="submit" class="rac-send" aria-label="${this._i18n.send || 'Enviar'}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="22" y1="2" x2="11" y2="13"/>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
              </button>
            </form>
            <p class="rac-powered">Powered by <a href="https://replanta.dev" target="_blank" rel="noopener">Replanta</a></p>
          </div>
        </div>
      `;

      // Show welcome after a short delay
      requestAnimationFrame(() => {
        this._showWelcome();
      });
    }

    _applyPosition() {
      const pos = this._position;
      this.style.setProperty('--rac-bottom', '24px');
      if (pos === 'bottom-left') {
        this.style.setProperty('--rac-right', 'auto');
        this.style.setProperty('--rac-left', '24px');
      } else {
        this.style.setProperty('--rac-right', '24px');
        this.style.setProperty('--rac-left', 'auto');
      }
    }

    _applyColor() {
      this.style.setProperty('--rac-primary', this._primaryColor);
      // Compute a readable text color (black or white) based on luminance
      const r = parseInt(this._primaryColor.slice(1, 3), 16);
      const g = parseInt(this._primaryColor.slice(3, 5), 16);
      const b = parseInt(this._primaryColor.slice(5, 7), 16);
      const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
      this.style.setProperty('--rac-primary-text', lum > 0.5 ? '#000' : '#fff');
    }

    // ── Events ─────────────────────────────────────────────────────────────

    _bindEvents() {
      const bubble   = this.querySelector('.rac-bubble');
      const closeBtn = this.querySelector('.rac-close-btn');
      const form     = this.querySelector('.rac-form');
      const input    = this.querySelector('.rac-input');

      bubble.addEventListener('click', () => this._togglePanel());
      closeBtn.addEventListener('click', () => this._closePanel());

      form.addEventListener('submit', (e) => {
        e.preventDefault();
        this._sendMessage();
      });

      // Auto-resize textarea
      input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
      });

      // Enter = submit, Shift+Enter = newline
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this._sendMessage();
        }
      });

      // Close on overlay click (outside panel)
      this.addEventListener('click', (e) => {
        if (this._open && !e.target.closest('.rac-panel') && !e.target.closest('.rac-bubble')) {
          this._closePanel();
        }
      });
    }

    _togglePanel() {
      this._open ? this._closePanel() : this._openPanel();
    }

    _openPanel() {
      this._open = true;
      const panel  = this.querySelector('.rac-panel');
      const bubble = this.querySelector('.rac-bubble');
      const badge  = this.querySelector('.rac-badge');
      const chatIcon = bubble.querySelector('svg:not(.rac-close-icon)');
      const closeIcon = bubble.querySelector('.rac-close-icon');

      this._clearPulse();
      panel.setAttribute('aria-hidden', 'false');
      panel.classList.add('rac-panel--open');
      bubble.setAttribute('aria-expanded', 'true');
      chatIcon.style.display = 'none';
      closeIcon.style.display = '';
      badge.style.display = 'none';

      this.querySelector('.rac-input').focus();
    }

    _closePanel() {
      this._open = false;
      const panel  = this.querySelector('.rac-panel');
      const bubble = this.querySelector('.rac-bubble');
      const chatIcon = bubble.querySelector('svg:not(.rac-close-icon)');
      const closeIcon = bubble.querySelector('.rac-close-icon');

      panel.setAttribute('aria-hidden', 'true');
      panel.classList.remove('rac-panel--open');
      bubble.setAttribute('aria-expanded', 'false');
      chatIcon.style.display = '';
      closeIcon.style.display = 'none';
    }

    // ── Messages ───────────────────────────────────────────────────────────

    _showWelcome() {
      this._appendMessage('assistant', this._welcome);
    }

    _appendMessage(role, text, options = {}) {
      const container = this.querySelector('.rac-messages');
      const el        = document.createElement('div');
      el.className    = `rac-msg rac-msg--${role}`;

      const bubble = document.createElement('div');
      bubble.className = 'rac-msg-bubble';

      if (options.loading) {
        bubble.innerHTML = '<span class="rac-typing"><span></span><span></span><span></span></span>';
      } else {
        bubble.innerHTML = this._formatText(text);
      }

      el.appendChild(bubble);

      // Feedback buttons for assistant messages
      if (role === 'assistant' && !options.loading && options.messageId) {
        const fbRow = document.createElement('div');
        fbRow.className = 'rac-feedback';
        fbRow.innerHTML = `
          <button class="rac-fb-btn rac-fb-up" data-mid="${options.messageId}" aria-label="Respuesta útil">👍</button>
          <button class="rac-fb-btn rac-fb-down" data-mid="${options.messageId}" aria-label="Respuesta no útil">👎</button>
        `;
        fbRow.querySelectorAll('.rac-fb-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            const rating = btn.classList.contains('rac-fb-up') ? 1 : -1;
            this._sendFeedback(options.messageId, rating);
            fbRow.innerHTML = '<span class="rac-fb-thanks">Gracias por tu valoración</span>';
          });
        });
        el.appendChild(fbRow);
      }

      container.appendChild(el);
      container.scrollTop = container.scrollHeight;
      return el;
    }

    _updateMessage(el, text) {
      const bubble = el.querySelector('.rac-msg-bubble');
      bubble.innerHTML = this._formatText(text);
      const container = this.querySelector('.rac-messages');
      container.scrollTop = container.scrollHeight;
    }

    _appendProductCards(products) {
      if (!products || products.length === 0) return;

      const container = this.querySelector('.rac-messages');
      const row = document.createElement('div');
      row.className = 'rac-product-cards';

      products.forEach(p => {
        const card = document.createElement('div');
        card.className = 'rac-product-card';

        const link = document.createElement('a');
        link.className = 'rac-card-link';
        link.href = p.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.innerHTML = `
          ${p.image ? `<img src="${this._esc(p.image)}" alt="${this._esc(p.name)}" loading="lazy">` : '<div class="rac-card-no-img"></div>'}
          <div class="rac-card-info">
            <span class="rac-card-name">${this._esc(p.name)}</span>
            <span class="rac-card-price">${p.price}</span>
            <span class="rac-card-stock ${p.in_stock ? 'rac-in-stock' : 'rac-out-stock'}">${p.in_stock ? 'En stock' : 'Sin stock'}</span>
          </div>
        `;
        card.appendChild(link);

        if (p.in_stock) {
          const cta = document.createElement('button');
          cta.className = 'rac-card-cta';
          cta.textContent = this._i18n.addToCart || 'Añadir al carrito';
          cta.addEventListener('click', (e) => {
            e.stopPropagation();
            cta.disabled = true;
            cta.textContent = '…';
            this._addProductToCart(p);
            setTimeout(() => { cta.textContent = '✓ Añadido'; }, 1400);
          });
          card.appendChild(cta);
        }

        row.appendChild(card);
      });

      container.appendChild(row);
      container.scrollTop = container.scrollHeight;
    }

    _handleToolActions(actions) {
      if (!actions || actions.length === 0) return;

      actions.forEach(action => {
        if (action.action === 'add_to_cart') {
          this._executeAddToCart(action);
        } else if (action.action === 'prepare_order') {
          this._showOrderSummary(action);
        } else if (action.action === 'escalate') {
          this._showEscalationMessage(action);
        }
      });
    }

    _executeAddToCart(action) {
      // Use WooCommerce AJAX add to cart
      const formData = new FormData();
      formData.append('action', 'woocommerce_ajax_add_to_cart');
      formData.append('product_id', action.product_id);
      formData.append('quantity', action.quantity || 1);
      if (action.variation_id) {
        formData.append('variation_id', action.variation_id);
      }
      if (action.variation) {
        Object.entries(action.variation).forEach(([k, v]) => formData.append(k, v));
      }

      fetch(window.wc_add_to_cart_params?.ajax_url || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
      })
        .then(() => {
          const container = this.querySelector('.rac-messages');
          const el = document.createElement('div');
          el.className = 'rac-cart-confirm';
          el.innerHTML = `
            <span>${this._i18n.addedToCart || 'Añadido al carrito'} ✓</span>
            <a href="${this._esc(action.cart_url || cfg.cartUrl || '/carrito')}" class="rac-btn-cart">
              ${this._i18n.viewCart || 'Ver carrito'}
            </a>
            <a href="${this._esc(action.checkout_url || cfg.checkoutUrl || '/finalizar-compra')}" class="rac-btn-checkout">
              ${this._i18n.checkout || 'Ir a pagar'}
            </a>
          `;
          container.appendChild(el);
          container.scrollTop = container.scrollHeight;

          // Refresh cart fragments
          document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh'));
        })
        .catch(() => {});
    }

    _showOrderSummary(action) {
      const container = this.querySelector('.rac-messages');
      const el = document.createElement('div');
      el.className = 'rac-order-summary';

      const itemsHtml = (action.items || []).map(item =>
        `<li>${this._esc(item.name)} × ${item.quantity} — ${item.price}€</li>`
      ).join('');

      el.innerHTML = `
        <div class="rac-order-box">
          <ul>${itemsHtml}</ul>
          <p class="rac-order-total"><strong>Total aprox.: ${action.total_approx}</strong></p>
          <a href="${this._esc(action.checkout_url || cfg.checkoutUrl || '/finalizar-compra')}" class="rac-btn-checkout">
            ${this._i18n.checkout || 'Ir a pagar'}
          </a>
        </div>
      `;
      container.appendChild(el);
      container.scrollTop = container.scrollHeight;

      // Fill cart via individual add-to-cart calls
      if (action.items) {
        action.items.forEach(item => this._executeAddToCart(item));
      }
    }

    _showEscalationMessage() {
      const container = this.querySelector('.rac-messages');
      const el = document.createElement('div');
      el.className = 'rac-escalation-notice';
      el.textContent = 'Un asesor se pondrá en contacto contigo en breve.';
      container.appendChild(el);
      container.scrollTop = container.scrollHeight;
    }

    // ── API ────────────────────────────────────────────────────────────────

    async _sendMessage() {
      const input = this.querySelector('.rac-input');
      const text  = input.value.trim();
      if (!text) return;

      if (!this._open) this._openPanel();

      input.value = '';
      input.style.height = 'auto';
      this.querySelector('.rac-send').disabled = true;

      // Append user message
      this._appendMessage('user', text);

      // Show loading indicator
      const loadingEl = this._appendMessage('assistant', '', { loading: true });

      try {
        const headers = {
          'Content-Type': 'application/json',
          'X-WP-Nonce': this._nonce,
        };
        if (this._sessionId) {
          headers['X-Replanta-Session'] = this._sessionId;
        }

        const res = await fetch(`${this._apiUrl}/chat`, {
          method: 'POST',
          headers,
          body: JSON.stringify({ message: text, session_id: this._sessionId }),
        });

        const data = await res.json();

        if (!res.ok) {
          this._updateMessage(loadingEl, data.error || (this._i18n.errorGeneric || 'Error al conectar.'));
          return;
        }

        // Persist session
        if (data.session_id) {
          this._sessionId = data.session_id;
          sessionStorage.setItem('replanta_session_id', data.session_id);
        }

        // Update loading bubble with response
        loadingEl.querySelector('.rac-msg-bubble').innerHTML = this._formatText(data.text);

        // Add feedback buttons
        if (data.conversation_id) {
          const fbRow = document.createElement('div');
          fbRow.className = 'rac-feedback';
          fbRow.innerHTML = `
            <button class="rac-fb-btn rac-fb-up" aria-label="Útil">👍</button>
            <button class="rac-fb-btn rac-fb-down" aria-label="No útil">👎</button>
          `;
          fbRow.querySelectorAll('.rac-fb-btn').forEach(btn => {
            btn.addEventListener('click', () => {
              const rating = btn.classList.contains('rac-fb-up') ? 1 : -1;
              this._sendFeedback(data.conversation_id, rating);
              fbRow.innerHTML = '<span class="rac-fb-thanks">Gracias ✓</span>';
            });
          });
          loadingEl.appendChild(fbRow);
        }

        // Show product cards if relevant
        if (data.products && data.products.length > 0) {
          this._appendProductCards(data.products);
          this._appendQuickReplies(data.products);
        }

        // Execute frontend actions
        if (data.tool_actions && data.tool_actions.length > 0) {
          this._handleToolActions(data.tool_actions);
        }

      } catch (err) {
        this._updateMessage(loadingEl, this._i18n.errorGeneric || 'Error al conectar con el asistente.');
      } finally {
        this.querySelector('.rac-send').disabled = false;
        input.focus();
      }
    }

    // ── Pulse & attention ──────────────────────────────────────────────────

    _startPulseTimer() {
      const pulse = () => {
        if (this._open) return;
        const bubble = this.querySelector('.rac-bubble');
        if (!bubble) return;
        bubble.classList.remove('rac-bubble--pulse');
        void bubble.offsetWidth; // reflow to restart animation
        bubble.classList.add('rac-bubble--pulse');
        bubble.addEventListener('animationend', () => {
          bubble.classList.remove('rac-bubble--pulse');
        }, { once: true });
      };
      this._pulseTimeout = setTimeout(() => {
        pulse();
        this._pulseInterval = setInterval(pulse, 60000);
      }, 6000);
    }

    _clearPulse() {
      clearTimeout(this._pulseTimeout);
      clearInterval(this._pulseInterval);
      const bubble = this.querySelector('.rac-bubble');
      if (bubble) bubble.classList.remove('rac-bubble--pulse');
    }

    disconnectedCallback() {
      this._clearPulse();
    }

    // ── Quick replies ─────────────────────────────────────────────────────

    _appendQuickReplies(products) {
      const inStock = products.filter(p => p.in_stock);
      if (!inStock.length) return;

      const container = this.querySelector('.rac-messages');
      const chips = document.createElement('div');
      chips.className = 'rac-chips';

      const remove = () => chips.remove();

      inStock.slice(0, 2).forEach(p => {
        const btn = document.createElement('button');
        btn.className = 'rac-chip';
        const label = inStock.length === 1
          ? (this._i18n.addToCart || '🛒 Añadir al carrito')
          : `🛒 ${p.name.length > 22 ? p.name.slice(0, 20) + '…' : p.name}`;
        btn.textContent = label;
        btn.addEventListener('click', () => {
          remove();
          this._addProductToCart(p);
        });
        chips.appendChild(btn);
      });

      const moreBtn = document.createElement('button');
      moreBtn.className = 'rac-chip';
      moreBtn.textContent = 'Seguir explorando';
      moreBtn.addEventListener('click', () => {
        remove();
        this.querySelector('.rac-input').focus();
      });
      chips.appendChild(moreBtn);

      container.appendChild(chips);
      container.scrollTop = container.scrollHeight;
    }

    _addProductToCart(product) {
      this._executeAddToCart({
        action:       'add_to_cart',
        product_id:   product.id,
        quantity:     1,
        cart_url:     cfg.cartUrl     || '/carrito',
        checkout_url: cfg.checkoutUrl || '/finalizar-compra',
      });
    }

    async _sendFeedback(conversationId, rating) {
      try {
        await fetch(`${this._apiUrl}/feedback`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this._nonce },
          body: JSON.stringify({ message_id: conversationId, rating }),
        });
      } catch (_) {}
    }

    // ── Utils ──────────────────────────────────────────────────────────────

    _formatText(text) {
      if (!text) return '';
      return text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^- (.+)$/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>')
        .replace(/\n{2,}/g, '</p><p>')
        .replace(/\n/g, '<br>')
        .replace(/^(.+)$/, '<p>$1</p>');
    }

    _esc(str) {
      return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
  }

  customElements.define('replanta-ai-chat', ReplantaAiChat);

})();
