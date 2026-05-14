(function () {
  if (!window.replantaPricesQuote) return;

  var cfg = window.replantaPricesQuote;
  var modal = null;

  function buildModal() {
    if (modal) return modal;

    var wrap = document.createElement('div');
    wrap.className = 'rep-quote-modal';
    wrap.innerHTML = '' +
      '<div class="rep-quote-backdrop" data-quote-close="1"></div>' +
      '<div class="rep-quote-dialog" role="dialog" aria-modal="true" aria-label="' + esc(cfg.strings.title) + '">' +
        '<button type="button" class="rep-quote-close" data-quote-close="1" aria-label="' + esc(cfg.strings.close) + '">×</button>' +
        '<h3>' + esc(cfg.strings.title) + '</h3>' +
        '<p class="rep-quote-subtitle">' + esc(cfg.strings.subtitle) + '</p>' +
        '<form class="rep-quote-form">' +
          '<div class="rep-quote-grid">' +
            '<label>' + esc(cfg.strings.name) + '*<input type="text" name="name" required></label>' +
            '<label>' + esc(cfg.strings.email) + '*<input type="email" name="email" required></label>' +
            '<label>' + esc(cfg.strings.phone) + '<input type="text" name="phone"></label>' +
            '<label>' + esc(cfg.strings.website) + '<input type="url" name="website" placeholder="https://"></label>' +
          '</div>' +
          '<label>' + esc(cfg.strings.message) + '<textarea name="message" rows="4"></textarea></label>' +
          '<input type="text" name="fax_number" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;pointer-events:none;" aria-hidden="true">' +
          '<input type="hidden" name="pricing_type" value="">' +
          '<input type="hidden" name="pricing_plan" value="">' +
          '<input type="hidden" name="page_url" value="">' +
          '<div class="rep-quote-status" aria-live="polite"></div>' +
          '<button type="submit" class="rep-quote-submit">' + esc(cfg.strings.submit) + '</button>' +
        '</form>' +
      '</div>';

    document.body.appendChild(wrap);
    modal = wrap;

    wrap.addEventListener('click', function (e) {
      if (e.target && e.target.getAttribute('data-quote-close') === '1') {
        closeModal();
      }
    });

    var form = wrap.querySelector('.rep-quote-form');
    form.addEventListener('submit', onSubmit);

    return modal;
  }

  function esc(str) {
    return String(str || '').replace(/[&<>"']/g, function (s) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[s];
    });
  }

  function openModal(type, plan) {
    var el = buildModal();
    el.classList.add('is-open');

    var form = el.querySelector('form');
    form.reset();
    form.querySelector('input[name="pricing_type"]').value = type || '';
    form.querySelector('input[name="pricing_plan"]').value = plan || '';
    form.querySelector('input[name="page_url"]').value = window.location.href;
    setStatus('');
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('is-open');
  }

  function setStatus(msg, isError) {
    if (!modal) return;
    var box = modal.querySelector('.rep-quote-status');
    box.textContent = msg || '';
    box.classList.toggle('is-error', !!isError);
  }

  function onSubmit(e) {
    e.preventDefault();
    var form = e.currentTarget;

    var name = (form.name.value || '').trim();
    var email = (form.email.value || '').trim();
    if (!name || !email) {
      setStatus(cfg.strings.required, true);
      return;
    }

    var btn = form.querySelector('.rep-quote-submit');
    var oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = cfg.strings.sending;
    setStatus('');

    var payload = new URLSearchParams();
    payload.append('action', 'replanta_prices_quote_lead');
    payload.append('nonce', cfg.nonce || '');
    payload.append('name', name);
    payload.append('email', email);
    payload.append('phone', form.phone.value || '');
    payload.append('website', form.website.value || '');
    payload.append('message', form.message.value || '');
    payload.append('fax_number', form.fax_number.value || '');
    payload.append('pricing_type', form.pricing_type.value || '');
    payload.append('pricing_plan', form.pricing_plan.value || '');
    payload.append('page_url', form.page_url.value || '');

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: payload.toString()
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : cfg.strings.error);
        }
        setStatus(cfg.strings.ok, false);
        setTimeout(closeModal, 1200);
      })
      .catch(function (err) {
        setStatus(err.message || cfg.strings.error, true);
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = oldText;
      });
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-quote-modal="1"]');
    if (!trigger) return;

    e.preventDefault();
    openModal(trigger.getAttribute('data-quote-type'), trigger.getAttribute('data-quote-plan'));
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });
})();
