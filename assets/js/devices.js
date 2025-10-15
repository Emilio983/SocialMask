(() => {
  const generateBtn = document.getElementById('generate-link-btn');
  const linkDetailsWrap = document.getElementById('link-details');
  const linkCodeEl = document.getElementById('link-code');
  const linkExpiryEl = document.getElementById('link-expiry');
  const linkQrImg = document.getElementById('link-qr');
  const linkStatusWrap = document.getElementById('link-status');
  const linkStatusText = document.getElementById('link-status-text');
  const linkStatusIcon = document.getElementById('link-status-icon');

  const devicesList = document.getElementById('devices-list');
  const devicesEmpty = document.getElementById('devices-empty');
  const refreshDevicesBtn = document.getElementById('refresh-devices');

  let pollTimer = null;
  let currentLink = null;

  function showLinkStatus(message, type = 'info') {
    if (!linkStatusWrap || !linkStatusText || !linkStatusIcon) return;
    linkStatusWrap.classList.remove('hidden', 'status-success', 'status-error', 'status-info');
    linkStatusWrap.classList.add(`status-${type}`);
    linkStatusText.textContent = message;

    linkStatusIcon.className = 'w-4 h-4 rounded-full';
    if (type === 'success') linkStatusIcon.classList.add('bg-brand-success');
    else if (type === 'error') linkStatusIcon.classList.add('bg-brand-error');
    else linkStatusIcon.classList.add('bg-brand-accent', 'animate-pulse');
  }

  function formatExpiry(isoString) {
    if (!isoString) return '';
    try {
      const date = new Date(isoString);
      return date.toLocaleString();
    } catch {
      return isoString;
    }
  }

  function renderDevices(devices) {
    if (!devicesList || !devicesEmpty) return;
    devicesList.innerHTML = '';

    if (!devices || devices.length === 0) {
      devicesEmpty.classList.remove('hidden');
      return;
    }

    devicesEmpty.classList.add('hidden');

    devices.forEach((device) => {
      const card = document.createElement('div');
      card.className = 'border border-brand-border rounded-xl p-4 bg-brand-bg-primary';

      const title = device.device_label || (device.is_primary ? 'Dispositivo principal' : 'Dispositivo sin alias');
      const platform = device.platform || 'Desconocido';
      const lastUsed = device.last_used_at ? new Date(device.last_used_at).toLocaleString() : '---';

      card.innerHTML = `
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-brand-text-primary">${title}</h3>
                <p class="text-sm text-brand-text-secondary">Plataforma: ${platform}</p>
                <p class="text-xs text-brand-text-secondary mt-1">Último uso: ${lastUsed}</p>
            </div>
            ${device.is_primary ? '<span class="px-3 py-1 text-xs rounded-full bg-brand-accent/10 text-brand-accent">Principal</span>' : ''}
        </div>
        ${!device.is_primary ? `<button data-device-id="${device.id}" class="revoke-btn mt-4 text-xs text-brand-error hover:underline">Revocar acceso</button>` : ''}
      `;

      devicesList.appendChild(card);
    });

    devicesList.querySelectorAll('.revoke-btn').forEach((btn) => {
      btn.addEventListener('click', async (event) => {
        const id = Number(event.currentTarget.getAttribute('data-device-id'));
        if (!id || !confirm('¿Revocar acceso a este dispositivo?')) {
          return;
        }
        try {
          await revokeDevice(id);
          await loadDevices();
          showLinkStatus('Dispositivo revocado', 'success');
        } catch (error) {
          showLinkStatus(error.message || 'Error al revocar', 'error');
        }
      });
    });
  }

  async function loadDevices() {
    try {
      const resp = await fetch('/api/devices/list.php');
      const data = await resp.json();
      if (!resp.ok || !data.success) {
        throw new Error(data.message || 'No se pudo cargar la lista de dispositivos');
      }
      renderDevices(data.devices || []);
    } catch (error) {
      showLinkStatus(error.message || 'Error al cargar dispositivos', 'error');
    }
  }

  async function revokeDevice(deviceId) {
    const resp = await fetch('/api/devices/revoke.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ device_id: deviceId }),
    });
    const data = await resp.json();
    if (!resp.ok || !data.success) {
      throw new Error(data.message || 'No se pudo revocar el dispositivo');
    }
  }

  function buildQrUrl(token) {
    if (!token) return '';
    const origin = window.location.origin;
    const url = `${origin}/pages/login.php?qr_token=${encodeURIComponent(token)}`;
    return `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`;
  }

  async function pollLink() {
    if (!currentLink) return;
    try {
      const resp = await fetch('/api/devices/link_status.php');
      const data = await resp.json();
      if (!resp.ok || !data.success) {
        throw new Error(data.message || 'No se pudo verificar el estado');
      }

      const links = data.links || [];
      const active = links.find((item) => item.link_code === currentLink.linkCode);
      if (active?.status === 'consumed') {
        showLinkStatus('Nuevo dispositivo vinculado correctamente.', 'success');
        clearInterval(pollTimer);
        pollTimer = null;
        currentLink = null;
        linkDetailsWrap?.classList.add('hidden');
        await loadDevices();
      }
    } catch (error) {
      console.error(error);
    }
  }

  async function generateLink() {
    if (!generateBtn) return;
    generateBtn.disabled = true;
    showLinkStatus('Generando código...', 'info');

    try {
      const resp = await fetch('/api/devices/link_start.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
      });
      const data = await resp.json();
      if (!resp.ok || !data.success) {
        throw new Error(data.message || 'No se pudo generar el código');
      }

      currentLink = {
        linkCode: data.link_code,
        qrToken: data.qr_token,
      };

      if (linkDetailsWrap) {
        linkDetailsWrap.classList.remove('hidden');
      }
      if (linkCodeEl) linkCodeEl.textContent = data.link_code;
      if (linkExpiryEl) linkExpiryEl.textContent = formatExpiry(data.expires_at);
      if (linkQrImg) linkQrImg.src = buildQrUrl(data.qr_token);

      showLinkStatus('Código generado. Usa el QR o agrega ?link_code= al iniciar sesión desde el nuevo dispositivo.', 'info');

      if (pollTimer) {
        clearInterval(pollTimer);
      }
      pollTimer = setInterval(pollLink, 4000);
    } catch (error) {
      showLinkStatus(error.message || 'Error al generar código', 'error');
    } finally {
      generateBtn.disabled = false;
    }
  }

  function init() {
    loadDevices();
    if (generateBtn) {
      generateBtn.addEventListener('click', generateLink);
    }
    if (refreshDevicesBtn) {
      refreshDevicesBtn.addEventListener('click', loadDevices);
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
