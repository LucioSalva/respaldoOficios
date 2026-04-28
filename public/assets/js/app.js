/**
 * Respaldo de Oficios - JavaScript principal
 * Vanilla JS, sin dependencias externas más allá de Bootstrap.
 *
 * NOTA: la confirmación de acciones destructivas está implementada con
 * SweetAlert2 en el layout principal (views/layouts/main.php). Aquí NO
 * se usa window.confirm() por accesibilidad ni por consistencia visual.
 */

'use strict';

/**
 * Helper global para construir rutas internas que respeten APP_BASE_PATH.
 * Uso: fetch(appPath('/vacaciones/buscar-personal?q=...'))
 *
 * Lee window.APP_BASE_PATH inyectado por el layout principal/auth.
 * Si APP_BASE_PATH esta vacio, devuelve la ruta tal cual.
 * Si la ruta ya empieza con el base path, no se duplica.
 * Si la ruta es absoluta (http://, https://, //), se respeta tal cual.
 */
window.appPath = function (path) {
    if (typeof path !== 'string' || path.length === 0) {
        return path;
    }
    if (path.indexOf('http://') === 0 || path.indexOf('https://') === 0 || path.indexOf('//') === 0) {
        return path;
    }
    var base = (typeof window.APP_BASE_PATH === 'string') ? window.APP_BASE_PATH : '';
    if (path.charAt(0) !== '/') {
        path = '/' + path;
    }
    if (base === '') {
        return path;
    }
    return base + path;
};

(function () {
    // Registry de instancias de Bootstrap Tooltip para permitir dispose()
    // cuando DOMContentLoaded corra más de una vez (evita memory leak al
    // reinstanciar tooltips sobre el mismo nodo).
    const tooltipRegistry = new WeakMap();

    function initFlashAutoclose() {
        document.querySelectorAll('.alert-flash').forEach(function (alert) {
            setTimeout(function () {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) bsAlert.close();
            }, 6000);
        });
    }

    function initFolioPreview() {
        const numInput  = document.getElementById('numero_folio');
        const anioInput = document.getElementById('anio_folio');
        const preview   = document.getElementById('folioTexto');
        if (!numInput || !anioInput || !preview) return;

        function updatePreview() {
            const numRaw = numInput.value.trim();
            const anio   = anioInput.value.trim();
            if (!numRaw || !anio) return;
            const num = parseInt(numRaw, 10);
            if (isNaN(num)) return;
            // Misma regla que FolioService::formatear: LPAD solo si < 10000
            const numTxt = num < 10000 ? String(num).padStart(4, '0') : String(num);
            preview.textContent = `TM/ECA/STIyC/${numTxt}/${anio}`;
        }
        numInput.addEventListener('input',  updatePreview);
        anioInput.addEventListener('input', updatePreview);
    }

    function initFormSubmitLoader() {
        document.querySelectorAll('form[method="POST"]').forEach(function (form) {
            if (form.classList.contains('no-loading')) return;
            form.addEventListener('submit', function () {
                form.querySelectorAll('[type="submit"]').forEach(function (btn) {
                    if (btn.classList.contains('no-loading')) return;
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.setAttribute('data-original', originalText);
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
                });
            });
        });
    }

    function initFechaCompromiso() {
        const fechaRec  = document.getElementById('fecha_recepcion');
        const fechaComp = document.getElementById('fecha_compromiso');
        if (!fechaRec || !fechaComp) return;

        fechaRec.addEventListener('change', function () {
            if (fechaComp.value && fechaComp.value < fechaRec.value) {
                fechaComp.value = '';
                fechaComp.focus();
            }
            fechaComp.min = fechaRec.value;
        });
        if (fechaRec.value) fechaComp.min = fechaRec.value;
    }

    function initTabsHash() {
        document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
            if (anchor.hasAttribute('data-bs-toggle')) return;
            anchor.addEventListener('click', function (e) {
                const hash = anchor.getAttribute('href');
                const tabEl = document.querySelector(`[data-bs-toggle="tab"][href="${hash}"]`);
                if (tabEl) {
                    e.preventDefault();
                    bootstrap.Tab.getOrCreateInstance(tabEl).show();
                    window.location.hash = hash;
                }
            });
        });
    }

    function initTooltips() {
        document.querySelectorAll('[title]:not([data-bs-toggle])').forEach(function (el) {
            const previous = tooltipRegistry.get(el);
            if (previous) {
                try { previous.dispose(); } catch (e) { /* ignore */ }
            }
            const tip = new bootstrap.Tooltip(el, { trigger: 'hover focus', placement: 'top' });
            tooltipRegistry.set(el, tip);
        });
    }

    function initHashScrolls() {
        if (window.location.hash === '#evidencias') {
            const el = document.getElementById('evidencias');
            if (el) {
                setTimeout(function () {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 200);
            }
        }
    }

    // Único DOMContentLoaded — consolidado.
    document.addEventListener('DOMContentLoaded', function () {
        initFlashAutoclose();
        initFolioPreview();
        initFormSubmitLoader();
        initFechaCompromiso();
        initTabsHash();
        initTooltips();
        initHashScrolls();
    });
})();
