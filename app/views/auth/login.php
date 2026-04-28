<?php $pageTitle = 'Iniciar Sesión'; ?>

<form method="POST" action="<?= url_path('/login') ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

    <div class="mb-4">
        <label for="username" class="form-label fw-bold fs-5">
            <i class="fa-solid fa-user me-2" aria-hidden="true"></i>Usuario o correo
        </label>
        <input
            type="text"
            class="form-control form-control-lg"
            id="username"
            name="username"
            placeholder="usuario o correo@dominio"
            autocomplete="username"
            required
            autofocus
        >
        <div class="form-text">Puedes ingresar con tu nombre de usuario o con tu correo electrónico.</div>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label fw-bold fs-5">
            <i class="fa-solid fa-lock me-2" aria-hidden="true"></i>Contraseña
        </label>
        <div class="input-group input-group-lg">
            <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                placeholder="Ingresa tu contraseña"
                autocomplete="current-password"
                required
            >
            <button
                class="btn btn-outline-secondary"
                type="button"
                id="togglePassword"
                aria-label="Mostrar u ocultar contraseña"
            >
                <i class="fa-solid fa-eye" id="toggleIcon" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="d-grid mt-4">
        <button type="submit" class="btn btn-institucional btn-lg py-3 fs-5 fw-bold">
            <i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>
            Entrar al Sistema
        </button>
    </div>
</form>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fa-solid fa-eye';
    }
});
</script>
