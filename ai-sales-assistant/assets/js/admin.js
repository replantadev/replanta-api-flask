/* Replanta AI Chat — Admin JS */
(function () {
  'use strict';

  // Meta fields — dynamic add/remove
  const metaContainer = document.getElementById('replanta-meta-fields');
  const addMetaBtn    = document.getElementById('replanta-add-meta');

  if (addMetaBtn && metaContainer) {
    let idx = metaContainer.querySelectorAll('.replanta-meta-row').length;

    addMetaBtn.addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'replanta-meta-row';
      row.innerHTML = `
        <input type="text" name="meta_fields[${idx}][key]" placeholder="_meta_key" class="regular-text" />
        <input type="text" name="meta_fields[${idx}][label]" placeholder="Etiqueta" class="regular-text" />
        <button type="button" class="button replanta-remove-meta">&times;</button>
      `;
      row.querySelector('.replanta-remove-meta').addEventListener('click', () => row.remove());
      metaContainer.appendChild(row);
      idx++;
    });

    metaContainer.querySelectorAll('.replanta-remove-meta').forEach(btn => {
      btn.addEventListener('click', () => btn.closest('.replanta-meta-row').remove());
    });
  }

  // Provider tab — check API connectivity
  const checkBtn     = document.getElementById('replanta-check-api');
  const apiStatus    = document.getElementById('replanta-api-status');
  const apiResults   = document.getElementById('replanta-api-results');
  const apiAnthropic = document.getElementById('replanta-api-anthropic');
  const apiOpenai    = document.getElementById('replanta-api-openai');

  if (checkBtn) {
    checkBtn.addEventListener('click', async () => {
      checkBtn.disabled = true;
      if (apiStatus) apiStatus.textContent = 'Comprobando…';
      if (apiResults) apiResults.style.display = 'none';

      const body = new URLSearchParams({
        action: 'replanta_check_api',
        _ajax_nonce: window.replantaAdmin.checkApiNonce,
      });

      try {
        const res  = await fetch(window.replantaAdmin.ajaxUrl, { method: 'POST', body });
        const data = await res.json();

        if (data.success) {
          const fmt = (key, label, r) => {
            const icon = r.ok ? '✅' : '❌';
            const color = r.ok ? '#1a7a3e' : '#b32d2e';
            return `<span style="color:${color}">${icon} <strong>${label}:</strong> ${r.message}</span>`;
          };
          if (apiAnthropic) apiAnthropic.innerHTML = fmt('anthropic', 'Anthropic', data.data.anthropic);
          if (apiOpenai)    apiOpenai.innerHTML    = fmt('openai', 'OpenAI Embeddings', data.data.openai);
          if (apiResults)   apiResults.style.display = 'block';

          const allOk = data.data.anthropic?.ok && data.data.openai?.ok;
          if (apiStatus) apiStatus.textContent = allOk ? '' : '— revisa los errores abajo';
        } else {
          if (apiStatus) apiStatus.textContent = 'Error al comprobar.';
        }
      } catch (e) {
        if (apiStatus) apiStatus.textContent = 'Error de red: ' + e.message;
      } finally {
        checkBtn.disabled = false;
      }
    });
  }

  // Indexing page — full reindex button
  const indexBtn = document.getElementById('replanta-full-index');
  const clearBtn = document.getElementById('replanta-clear-index');
  const logBox   = document.getElementById('replanta-index-log');
  const logPre   = document.getElementById('replanta-log-content');

  if (indexBtn) {
    indexBtn.addEventListener('click', async () => {
      if (!confirm('¿Reindexar todo el catálogo? Puede tardar varios minutos.')) return;

      indexBtn.disabled = true;
      indexBtn.textContent = 'Indexando…';
      logBox.style.display = 'block';
      logPre.textContent = 'Iniciando indexación…\n';

      try {
        const res = await fetch(window.replantaAdmin.apiUrl + 'index', {
          method: 'POST',
          headers: { 'X-WP-Nonce': window.replantaAdmin.nonce },
        });

        const data = await res.json().catch(() => ({}));

        if (res.ok && data.done) {
          logPre.textContent += 'Indexación completada.\n';
          setTimeout(() => location.reload(), 1500);
        } else if (res.status === 202 || (res.ok && data.queued)) {
          logPre.textContent += 'Trabajo de indexación en cola. Actualizando progreso…\n';
          pollStatus();
        } else {
          logPre.textContent += 'Error: ' + (data.error || 'HTTP ' + res.status) + '\n';
          console.error('Index error:', data);
        }
      } finally {
        indexBtn.disabled = false;
        indexBtn.textContent = 'Reindexar todo el catálogo';
      }
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', async () => {
      if (!confirm('¿Limpiar el índice completo? Los productos no podrán ser encontrados por el asistente hasta la próxima indexación.')) return;

      clearBtn.disabled = true;
      const res = await fetch(window.replantaAdmin.apiUrl + 'index', {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': window.replantaAdmin.nonce },
      });

      if (res.ok) {
        alert('Índice limpiado correctamente.');
        location.reload();
      }
      clearBtn.disabled = false;
    });
  }

  // Conversations page — expand/collapse message thread
  document.querySelectorAll('.replanta-expand-conv').forEach(btn => {
    btn.addEventListener('click', async () => {
      const convId    = btn.dataset.convId;
      const detailRow = document.getElementById('replanta-conv-detail-' + convId);
      if (!detailRow) return;

      const isOpen = detailRow.style.display !== 'none';
      if (isOpen) {
        detailRow.style.display = 'none';
        btn.textContent = 'Ver';
        return;
      }

      detailRow.style.display = '';
      btn.textContent = 'Cerrar';

      const wrap = detailRow.querySelector('.replanta-thread-wrap');
      if (wrap.dataset.loaded) return; // already fetched

      const body = new URLSearchParams({
        action:      'replanta_get_conversation',
        _ajax_nonce: window.replantaAdmin.conversationNonce,
        conv_id:     convId,
      });

      try {
        const res  = await fetch(window.replantaAdmin.ajaxUrl, { method: 'POST', body });
        const data = await res.json();
        wrap.dataset.loaded = '1';

        if (!data.success || !data.data.length) {
          wrap.innerHTML = '<p style="padding:8px;color:#6b7280">Sin mensajes.</p>';
          return;
        }

        wrap.innerHTML = '<div class="replanta-thread">' +
          data.data.map(msg => {
            const isUser = msg.role === 'user';
            const ratingHtml = msg.rating !== null
              ? `<span class="replanta-thread-rating">${msg.rating > 0 ? '👍' : '👎'}</span>`
              : '';
            const toolBadge = msg.has_tool
              ? '<span class="replanta-badge replanta-badge--blue" style="margin-left:6px">🛒 tool</span>'
              : '';
            const tokens = msg.tokens_used ? `<span style="color:#9ca3af;font-size:11px">${msg.tokens_used} tok</span>` : '';
            return `
              <div class="replanta-thread-msg replanta-thread-msg--${msg.role}">
                <div class="replanta-thread-meta">
                  <strong>${isUser ? '👤 Cliente' : '🤖 Asistente'}</strong>
                  ${toolBadge} ${tokens} ${ratingHtml}
                  <span style="color:#9ca3af;font-size:11px;margin-left:auto">${msg.created_at}</span>
                </div>
                <div class="replanta-thread-content">${escHtml(msg.content)}</div>
              </div>`;
          }).join('') +
          '</div>';
      } catch (e) {
        wrap.innerHTML = '<p style="color:#b32d2e;padding:8px">Error al cargar los mensajes.</p>';
      }
    });
  });

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>');
  }

  async function pollStatus() {
    const interval = setInterval(async () => {
      try {
        const res  = await fetch(window.replantaAdmin.apiUrl + 'index/status', {
          headers: { 'X-WP-Nonce': window.replantaAdmin.nonce },
        });
        const data = await res.json();

        logPre.textContent = `Estado: ${data.last_job?.status || 'desconocido'}\n`
          + `Indexados: ${data.indexed} / ${data.total} (${data.pct}%)\n\n`
          + (data.last_job?.log || '');

        if (data.last_job?.status === 'completed' || data.last_job?.status === 'failed') {
          clearInterval(interval);
          location.reload();
        }
      } catch (_) {
        clearInterval(interval);
      }
    }, 3000);
  }

})();
