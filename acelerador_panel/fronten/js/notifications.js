// --- SISTEMA DE NOTIFICACIONES GLOBAL ACELERADOR ---

function showNotification(message, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `custom-toast ${type}`;
  const icon = type === 'success' ? 'bi-check-circle-fill' : (type === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill');
  toast.innerHTML = `<i class="bi ${icon} me-3 fs-5"></i><div>${message}</div><div class="toast-progress"></div>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('fade-out');
    setTimeout(() => toast.remove(), 500);
  }, 4000);
}

function customConfirm(message, onConfirm) {
  const overlay = document.createElement('div');
  overlay.className = 'custom-confirm-overlay';
  overlay.innerHTML = `
    <div class="custom-confirm-box">
      <h5 class="text-white mb-3 fw-bold"><i class="bi bi-question-circle me-2 text-info"></i>Confirmación</h5>
      <p class="text-white-50 mb-4">${message}</p>
      <div class="d-flex justify-content-end gap-3">
        <button class="btn btn-outline-light rounded-pill px-4" id="btnCancel">Cancelar</button>
        <button class="btn btn-info rounded-pill px-4 fw-bold" id="btnOk">Confirmar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
  document.getElementById('btnCancel').onclick = () => overlay.remove();
  document.getElementById('btnOk').onclick = () => { overlay.remove(); onConfirm(); };
}

// Interceptor para enlaces con confirmación nativa
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a[onclick*="confirm"]').forEach(link => {
        const originalOnClick = link.getAttribute('onclick');
        if (originalOnClick && originalOnClick.includes('return confirm')) {
            const match = originalOnClick.match(/confirm\(['"](.*)['"]\)/);
            if (match) {
                const message = match[1];
                link.setAttribute('onclick', `event.preventDefault(); customConfirm('${message}', () => window.location.href = '${link.href}');`);
            }
        }
    });
});
