// ============================================================
//  GeoUTC — assets/js/app.js
//  Módulo compartido: API, notificaciones, modo oscuro, modal
// ============================================================

const API = 'api/api.php';

// ── Fetch helpers ────────────────────────────────────────────

export async function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const r  = await fetch(`${API}?${qs}`);
    return r.json();
}

export async function apiPost(action, body = {}) {
    const r = await fetch(`${API}?action=${action}`, {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify(body),
    });
    return r.json();
}

// ── Notificaciones toast ─────────────────────────────────────

const TOAST_COLORS = {
    success: 'linear-gradient(135deg,#4caf50,#45a049)',
    warning: 'linear-gradient(135deg,#ff9800,#f57c00)',
    error  : 'linear-gradient(135deg,#dc3545,#c82333)',
    info   : 'linear-gradient(135deg,#0066cc,#0052a3)',
};
const TOAST_ICONS = {
    success: 'check-circle',
    warning: 'exclamation-triangle',
    error  : 'times-circle',
    info   : 'info-circle',
};

export function toast(msg, tipo = 'info') {
    const el = document.createElement('div');
    el.style.cssText = `
        position:fixed;top:100px;right:20px;
        background:${TOAST_COLORS[tipo]};color:white;
        padding:15px 20px;border-radius:12px;
        box-shadow:0 4px 15px rgba(0,0,0,0.3);
        z-index:10000;font-weight:600;max-width:350px;
        animation:slideInRight 0.5s ease;font-family:'Poppins',sans-serif;
    `;
    el.innerHTML = `<i class="fas fa-${TOAST_ICONS[tipo]}"></i> ${msg}`;
    document.body.appendChild(el);
    setTimeout(() => {
        el.style.animation = 'none';
        el.style.opacity   = '0';
        el.style.transition = 'opacity 0.5s ease';
        setTimeout(() => el.remove(), 500);
    }, 4000);
}

// ── Modal de logro ───────────────────────────────────────────

export function mostrarLogro(mision) {
    const modal   = document.getElementById('logros-modal');
    const overlay = document.getElementById('modal-overlay');
    document.getElementById('logro-contenido').innerHTML = `
        <div style="text-align:center;padding:20px;">
            <div style="font-size:5rem;margin:20px 0;animation:bounce 1s ease;">🏆</div>
            <p style="font-size:1.6rem;margin:15px 0;font-weight:800;color:#1D462E;">${mision.nombre}</p>
            <p style="font-size:1.3rem;margin:20px 0;">
                <i class="fas fa-coins" style="color:#FFD700;font-size:1.5rem;"></i>
                <strong style="color:#1D462E;">+${mision.recompensa} puntos</strong>
            </p>
            <div style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);padding:15px;border-radius:12px;margin-top:20px;">
                <p style="color:#2e7d32;font-weight:600;margin:0;"><i class="fas fa-star"></i> ¡Excelente trabajo!</p>
            </div>
        </div>`;
    modal.style.display   = 'block';
    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

export function cerrarModal() {
    document.getElementById('logros-modal').style.display   = 'none';
    document.getElementById('modal-overlay').style.display  = 'none';
    document.body.style.overflow = 'auto';
}

// ── Modo oscuro ──────────────────────────────────────────────

export function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const icon = document.querySelector('#dark-mode-toggle i');
    const isDark = document.body.classList.contains('dark-mode');
    if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    localStorage.setItem('darkMode', isDark);
}

export function initDarkMode() {
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
        const icon = document.querySelector('#dark-mode-toggle i');
        if (icon) icon.className = 'fas fa-sun';
    }
}

// ── Panel de usuario ─────────────────────────────────────────

export async function refreshUserPanel() {
    try {
        const data = await apiGet('usuario');
        const pts  = document.getElementById('puntos-valor');
        const bar  = document.getElementById('progreso');
        const txt  = document.getElementById('progreso-text');
        if (pts) pts.textContent = data.puntos;
        if (bar) bar.value = data.progreso;
        if (txt) txt.textContent = data.progreso + '%';
    } catch (e) { /* silencioso */ }
}
