<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Error interno — Respaldo de Oficios</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<main class="container py-5" role="main">
    <div class="mx-auto text-center" style="max-width:640px;">
        <div class="mb-4">
            <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size:4rem;" aria-hidden="true"></i>
        </div>
        <h1 class="h3 fw-bold mb-3">Ocurrió un error al procesar su solicitud</h1>
        <p class="text-muted mb-4">
            Intente de nuevo en unos momentos. Si el problema persiste,
            contacte al administrador del sistema.
        </p>
        <?php if (!empty($incident_id)): ?>
            <p class="small text-muted">
                Referencia: <code><?= htmlspecialchars($incident_id) ?></code>
            </p>
        <?php endif; ?>
        <a href="/dashboard" class="btn btn-institucional mt-3">
            <i class="fa-solid fa-house me-2" aria-hidden="true"></i>Volver al inicio
        </a>
    </div>
</main>
</body>
</html>
