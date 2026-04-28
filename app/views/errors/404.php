<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada — Respaldo de Oficios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
</head>
<body class="bg-institucional-light">
<div class="min-vh-100 d-flex align-items-center justify-content-center">
    <div class="text-center">
        <i class="fa-solid fa-magnifying-glass text-muted" style="font-size:5rem;"></i>
        <h1 class="display-4 fw-bold text-institucional mt-3">404</h1>
        <p class="fs-4 text-muted mb-4">La página o recurso solicitado no fue encontrado.</p>
        <a href="<?= function_exists('url_path') ? url_path('/dashboard') : '/dashboard' ?>" class="btn btn-institucional btn-lg">
            <i class="fa-solid fa-house me-2"></i>Ir al inicio
        </a>
    </div>
</div>
</body>
</html>
