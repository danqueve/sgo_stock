// ============================================================
// app.js — Imperio Comercial  |  Mobile-First JS
// ============================================================

'use strict';

/* ── PWA: Registro del Service Worker ───────────────────────── */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sgo/sw.js', { scope: '/sgo/' })
            .then(reg => {
                console.log('[SW] Registrado, scope:', reg.scope);
                // Escuchar actualizaciones
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateBanner();
                        }
                    });
                });
            })
            .catch(err => console.warn('[SW] Error:', err));
    });
}

function showUpdateBanner() {
    const banner = document.createElement('div');
    banner.className = 'alert alert-info alert-dismissible position-fixed bottom-0 start-0 end-0 m-3 shadow';
    banner.style.zIndex = '9999';
    banner.innerHTML = `
        <i class="bi bi-arrow-clockwise me-2"></i>
        <strong>Nueva versión disponible.</strong>
        <button class="btn btn-sm btn-primary ms-2" onclick="location.reload()">Actualizar</button>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(banner);
}

/* ── PWA: Prompt "Agregar a pantalla de inicio" ─────────────── */
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    // Mostrar botón de instalación si existe en la página
    const btn = document.getElementById('btn-instalar-pwa');
    if (btn) btn.classList.remove('d-none');
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    const btn = document.getElementById('btn-instalar-pwa');
    if (btn) btn.classList.add('d-none');
});

document.addEventListener('click', e => {
    if (e.target.closest('#btn-instalar-pwa') && deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(choice => {
            console.log('[PWA] Respuesta:', choice.outcome);
            deferredPrompt = null;
        });
    }
});

/* ── Lazy Loading del Stock con IntersectionObserver ────────── */
class StockLazyLoader {
    constructor(options = {}) {
        this.endpoint      = options.endpoint    || '/sgo/api/articulos.php';
        this.container     = document.getElementById(options.containerId || 'stock-grid');
        this.sentinel      = document.getElementById(options.sentinelId  || 'stock-sentinel');
        this.skeletonCount = options.skeletonCount || 6;
        this.page          = 1;
        this.perPage       = options.perPage || 12;
        this.loading       = false;
        this.hasMore       = true;
        this.filters       = {};

        if (!this.container || !this.sentinel) return;

        // Carga inicial directa — no depender sólo del observer
        this.loadMore();

        // Observer para scroll infinito (páginas siguientes)
        this.observer = new IntersectionObserver(
            entries => { if (entries[0].isIntersecting) this.loadMore(); },
            { rootMargin: '150px' }
        );
        this.observer.observe(this.sentinel);
    }

    setFilter(key, value) {
        this.filters[key] = value;
        this.reset();
    }

    reset() {
        this.page    = 1;
        this.hasMore = true;
        this.container.innerHTML = '';
        this.loadMore();
    }

    async loadMore() {
        if (this.loading || !this.hasMore) return;
        this.loading = true;

        const skeletonEls = this.renderSkeletons();
        skeletonEls.forEach(el => this.container.appendChild(el));

        try {
            const params = new URLSearchParams({
                page:     this.page,
                per_page: this.perPage,
                ...this.filters
            });

            const res = await fetch(`${this.endpoint}?${params}`, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            // Detectar redirect a login (devuelve HTML en vez de JSON)
            const ct = res.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                throw new Error(`Respuesta inesperada del servidor (status ${res.status}). Recargá la página.`);
            }

            const data = await res.json();
            skeletonEls.forEach(el => el.remove());

            if (data.error) throw new Error(data.error);

            if (data.items && data.items.length) {
                data.items.forEach(item => this.container.appendChild(this.renderCard(item)));
                this.page++;
                this.hasMore = data.has_more;
            } else {
                this.hasMore = false;
                if (this.page === 1) this.renderEmpty();
            }
        } catch (err) {
            skeletonEls.forEach(el => el.remove());
            this.hasMore = false; // Detener el bucle — el usuario puede recargar
            this.renderError(err.message);
        } finally {
            this.loading = false;
        }
    }

    renderSkeletons() {
        const els = [];
        for (let i = 0; i < this.skeletonCount; i++) {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';
            col.innerHTML = `<div class="skeleton skeleton-card"></div>`;
            els.push(col);
        }
        return els;
    }

    renderCard(item) {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';

        const stockClass = item.stock_actual === 0   ? 'stock-empty'
                         : item.stock_actual <= item.stock_minimo ? 'stock-low'
                         : 'stock-ok';
        const stockText  = item.stock_actual === 0   ? 'Sin stock'
                         : item.stock_actual <= item.stock_minimo ? `¡Últimas ${item.stock_actual}!`
                         : `Stock: ${item.stock_actual}`;

        col.innerHTML = `
            <div class="article-card mb-3" data-id="${item.id}">
                ${item.imagen_url
                    ? `<img src="${item.imagen_url}" class="article-card__img" alt="" loading="lazy">`
                    : `<div class="article-card__img-placeholder">
                           <i class="bi ${item.icono || 'bi-box'}"></i>
                       </div>`}
                <div class="article-card__body">
                    <p class="article-card__name mb-1">${item.nombre}</p>
                    <p class="article-card__price-contado mb-0">${item.precio_contado_fmt}</p>
                    ${item.cuotas > 1
                        ? `<p class="article-card__price-cuota mb-1">${item.cuotas}x ${item.monto_cuota_fmt}</p>`
                        : `<p class="mb-1">&nbsp;</p>`}
                    <span class="badge article-card__stock-badge ${stockClass}">${stockText}</span>
                </div>
            </div>`;
        return col;
    }

    renderEmpty() {
        const div = document.createElement('div');
        div.className = 'col-12 text-center py-5';
        div.innerHTML = `
            <i class="bi bi-box-seam text-muted" style="font-size:3rem"></i>
            <p class="text-muted mt-2">No se encontraron artículos</p>`;
        this.container.appendChild(div);
    }

    renderError(msg) {
        const div = document.createElement('div');
        div.className = 'col-12';
        div.innerHTML = `
            <div class="alert alert-danger d-flex align-items-center gap-2 small">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                <span>${msg}</span>
                <button class="btn btn-sm btn-danger ms-auto" onclick="location.reload()">Reintentar</button>
            </div>`;
        this.container.appendChild(div);
    }
}

/* ── Validación en tiempo real — Formulario de Venta ────────── */
class VentaFormValidator {
    constructor(formId) {
        this.form = document.getElementById(formId);
        if (!this.form) return;
        this.rules = {
            nombre:    { required: true, minLength: 2, label: 'Nombre' },
            apellido:  { required: true, minLength: 2, label: 'Apellido' },
            direccion: { required: true, minLength: 5, label: 'Dirección' },
            localidad: { required: true, minLength: 2, label: 'Localidad' },
            celular:   { required: true, pattern: /^[\d\s\-\+]{8,15}$/, label: 'Celular' },
        };
        this.init();
    }

    init() {
        Object.keys(this.rules).forEach(field => {
            const input = this.form.querySelector(`[name="${field}"]`);
            if (!input) return;
            input.addEventListener('input', () => this.validateField(field, input));
            input.addEventListener('blur',  () => this.validateField(field, input));
        });

        this.form.addEventListener('submit', e => {
            if (!this.validateAll()) {
                e.preventDefault();
                e.stopPropagation();
                // Hacer foco en el primer campo inválido
                const firstInvalid = this.form.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    validateField(field, input) {
        const rule    = this.rules[field];
        const value   = input.value.trim();
        let error     = '';

        if (rule.required && !value) {
            error = `${rule.label} es obligatorio.`;
        } else if (rule.minLength && value.length < rule.minLength) {
            error = `${rule.label} debe tener al menos ${rule.minLength} caracteres.`;
        } else if (rule.pattern && !rule.pattern.test(value)) {
            error = `${rule.label} tiene un formato inválido.`;
        }

        const feedback = input.nextElementSibling;
        if (error) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = error;
            }
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        }
        return !error;
    }

    validateAll() {
        return Object.keys(this.rules).every(field => {
            const input = this.form.querySelector(`[name="${field}"]`);
            return input ? this.validateField(field, input) : true;
        });
    }
}


/* ── Calculadora de cuotas dinámica ─────────────────────────── */
function initCuotaCalculator() {
    const tipoPago   = document.querySelectorAll('input[name="tipo_pago"]');
    const secContado = document.getElementById('sec-contado');
    const secFinanc  = document.getElementById('sec-financiado');
    const selCuotas  = document.getElementById('sel-cuotas');
    const lblMonto   = document.getElementById('lbl-monto-cuota');
    const lblTotal   = document.getElementById('lbl-total');

    if (!tipoPago.length) return;

    tipoPago.forEach(radio => {
        radio.addEventListener('change', () => {
            const isFinanciado = radio.value === 'financiado' && radio.checked;
            if (secContado)  secContado.classList.toggle('d-none', isFinanciado);
            if (secFinanc)   secFinanc.classList.toggle('d-none', !isFinanciado);
        });
    });

    if (selCuotas) {
        selCuotas.addEventListener('change', actualizarCuotas);
    }

    function actualizarCuotas() {
        const articuloId = document.getElementById('articulo_id')?.value;
        const cuotas     = parseInt(selCuotas?.value || 1);
        if (!articuloId || !cuotas) return;

        fetch(`/sgo/api/articulos.php?id=${articuloId}&cuotas=${cuotas}`)
            .then(r => r.json())
            .then(data => {
                if (lblMonto) lblMonto.textContent = data.monto_cuota_fmt;
                if (lblTotal) lblTotal.textContent = data.precio_financiado_fmt;
            });
    }
}


/* ── Init global ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    // Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach(el => new bootstrap.Tooltip(el));

    initCuotaCalculator();

    // Activar validador si existe el formulario
    if (document.getElementById('form-venta')) {
        window.ventaValidator = new VentaFormValidator('form-venta');
        // Limpiar cualquier borrador viejo que haya quedado
        localStorage.removeItem('venta_draft');
    }
});
