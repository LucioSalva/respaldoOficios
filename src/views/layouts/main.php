<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Respaldo de Oficios</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
    <!-- Estilos propios -->
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<!-- NAVBAR SUPERIOR -->
<nav class="navbar navbar-dark bg-institucional navbar-expand-lg fixed-top shadow-sm" role="navigation" aria-label="Navegación principal">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/dashboard">
            <i class="fa-solid fa-folder-open fs-4" aria-hidden="true"></i>
            <span>Respaldo de Oficios</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/dashboard') ? 'active' : '' ?>"
                       href="/dashboard">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/oficios') ? 'active' : '' ?>"
                       href="/oficios">
                        <i class="fa-solid fa-folder-open" aria-hidden="true"></i> Oficios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/vacaciones') ? 'active' : '' ?>"
                       href="/vacaciones">
                        <i class="fa-solid fa-umbrella-beach" aria-hidden="true"></i> Vacaciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/incidencias') ? 'active' : '' ?>"
                       href="/incidencias">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Incidencias
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/personal') ? 'active' : '' ?>"
                       href="/personal">
                        <i class="fa-solid fa-id-badge" aria-hidden="true"></i> Personal
                    </a>
                </li>
                <?php if (Auth::hasRole([ROL_GOD, ROL_ADMIN])): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/usuarios') ? 'active' : '' ?>"
                       href="/usuarios">
                        <i class="fa-solid fa-users" aria-hidden="true"></i> Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/catalogos') ? 'active' : '' ?>"
                       href="/catalogos">
                        <i class="fa-solid fa-table-list" aria-hidden="true"></i> Catálogos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-lg <?= str_starts_with($_SERVER['REQUEST_URI'], '/importar') ? 'active' : '' ?>"
                       href="/importar">
                        <i class="fa-solid fa-file-import" aria-hidden="true"></i> Importar
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Usuario y logout -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1 nav-link-lg"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-circle-user fs-5" aria-hidden="true"></i>
                        <span><?= htmlspecialchars(Auth::userName()) ?></span>
                        <span class="badge bg-warning text-dark ms-1 small"><?= htmlspecialchars(Auth::userRol()) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <a class="dropdown-item py-2" href="/logout">
                                <i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>
                                Cerrar sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- CONTENIDO PRINCIPAL -->
<main class="main-content container-fluid px-4 py-4" role="main">

    <!-- Los flash se renderizan como toasts de SweetAlert2 (ver <script> al final). -->
    <?php $__flashes = Controller::getFlash(); ?>

    <!-- CONTENIDO DE LA VISTA -->
    <?= $__layoutContent ?? $content ?? '' ?>

</main>

<!-- PIE DE PÁGINA -->
<footer class="footer-institucional py-3 mt-4" role="contentinfo">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-muted small">
                Respaldo de Oficios &mdash; Tesorería Municipal &mdash; STIyC
            </div>
            <div class="col-md-6 text-end text-muted small">
                <?= date('d/m/Y H:i') ?>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
<script>
(function () {
    if (typeof Swal === 'undefined') return;

    // Toast reutilizable
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    window.AppToast = (type, msg) => Toast.fire({ icon: type, title: msg });

    // Flash messages del servidor → toasts
    const flashes = <?= json_encode(array_map(function($f){
        return ['type' => $f['type'], 'msg' => $f['msg']];
    }, $__flashes ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const MAP_ICON = { success:'success', danger:'error', warning:'warning', info:'info' };
    flashes.forEach(f => Toast.fire({ icon: MAP_ICON[f.type] || 'info', title: f.msg }));

    // Confirmación declarativa en forms: data-confirm="mensaje" (opcional data-confirm-icon, data-confirm-double)
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.dataset.confirm) return;
        if (form.dataset.confirmed === '1') return;

        e.preventDefault();
        const icon    = form.dataset.confirmIcon    || 'warning';
        const confirm = form.dataset.confirmBtn     || 'Sí, continuar';
        const cancel  = form.dataset.confirmCancel  || 'Cancelar';
        const title   = form.dataset.confirmTitle   || '¿Confirmar acción?';
        const color   = form.dataset.confirmColor   || '#7A1B1B';

        Swal.fire({
            title, html: form.dataset.confirm, icon,
            showCancelButton: true,
            confirmButtonText: confirm,
            cancelButtonText:  cancel,
            confirmButtonColor: color,
            reverseButtons: true,
            focusCancel: true,
        }).then((r) => {
            if (!r.isConfirmed) return;
            if (form.dataset.confirmDouble === '1') {
                Swal.fire({
                    title: '¿Estás COMPLETAMENTE seguro?',
                    html:  'Esta acción no se puede deshacer.',
                    icon:  'error',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText:  'No, cancelar',
                    confirmButtonColor: '#b91c1c',
                    reverseButtons: true,
                    focusCancel: true,
                }).then((r2) => {
                    if (r2.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); }
                });
            } else {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });
})();
</script>
<!-- App JS -->
<script src="/assets/js/app.js"></script>
</body>
</html>
