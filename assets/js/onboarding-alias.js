(() => {
  const form = document.getElementById('alias-form');
  const input = document.getElementById('alias-input');
  const submitBtn = document.getElementById('alias-submit');
  const statusWrap = document.getElementById('status-display');
  const statusText = document.getElementById('status-text');
  const statusIcon = document.getElementById('status-icon');

  function showStatus(message, type = 'info') {
    if (!statusWrap || !statusText || !statusIcon) return;

    statusWrap.classList.remove('hidden', 'status-success', 'status-error', 'status-info');
    statusWrap.classList.add(`status-${type}`);
    statusText.textContent = message;

    statusIcon.className = 'w-4 h-4 rounded-full';
    if (type === 'success') statusIcon.classList.add('bg-brand-success');
    else if (type === 'error') statusIcon.classList.add('bg-brand-error');
    else statusIcon.classList.add('bg-brand-accent', 'animate-pulse');
  }

  async function saveAlias(event) {
    event.preventDefault();
    if (!input || !submitBtn) return;

    const alias = input.value.trim().toLowerCase();
    if (alias.length < 3 || alias.length > 30) {
      showStatus('El alias debe tener entre 3 y 30 caracteres.', 'error');
      return;
    }

    submitBtn.disabled = true;
    showStatus('Guardando alias...', 'info');

    try {
      const resp = await fetch('/api/auth/set_alias.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ alias }),
      });

      const result = await resp.json();
      if (!resp.ok || !result.success) {
        throw new Error(result.message || 'No se pudo guardar el alias');
      }

      showStatus('Alias guardado correctamente.', 'success');
      window.__thesocialmask_ALIAS__ = result.alias;

      setTimeout(() => {
        window.location.href = '/pages/dashboard.php';
      }, 800);
    } catch (error) {
      showStatus(error.message || 'Error al guardar el alias', 'error');
    } finally {
      submitBtn.disabled = false;
    }
  }

  if (form) {
    form.addEventListener('submit', saveAlias);
  }

  if (!window.__thesocialmask_ALIAS__) {
    showStatus('Define tu alias para continuar.', 'info');
  }
})();
