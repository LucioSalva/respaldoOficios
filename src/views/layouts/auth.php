<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respaldo de Oficios — Acceso</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-body">

<div class="min-vh-100 d-flex align-items-center justify-content-center bg-institucional-light">
    <div class="auth-card card shadow-lg border-0 w-100" style="max-width:480px;">
        <div class="card-header bg-institucional text-white text-center py-4">
            <i class="fa-solid fa-folder-open display-4 mb-2" aria-hidden="true"></i>
            <h1 class="h3 fw-bold mb-0">Respaldo de Oficios</h1>
            <p class="mb-0 opacity-75 mt-1">Tesorería Municipal — STIyC</p>
        </div>
        <div class="card-body p-4 p-md-5">
            <!-- MENSAJES FLASH -->
            <?php foreach (Controller::getFlash() as $flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
            <?php endforeach; ?>

            <?= $__layoutContent ?? $content ?? '' ?>
        </div>
        <div class="card-footer text-center text-muted small py-3">
            Sistema de uso interno &mdash; Acceso restringido
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
