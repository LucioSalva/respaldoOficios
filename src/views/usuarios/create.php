<?php
$pageTitle = 'Nuevo Usuario';
$errors    = $errors ?? [];
$old       = $old    ?? [];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h2 fw-bold text-institucional mb-1">
            <i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Nuevo Usuario
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/usuarios">Usuarios</a></li>
                <li class="breadcrumb-item active">Nuevo</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:700px;">
    <div class="card-body p-4 p-md-5">
        <form method="POST" action="/usuarios/crear" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <div class="mb-4">
                <label for="nombre" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-id-card"></i> Nombre Completo <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control form-control-lg <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>"
                       id="nombre" name="nombre"
                       value="<?= htmlspecialchars($old['nombre'] ?? '') ?>"
                       placeholder="Nombre y apellidos" required>
                <?php if (isset($errors['nombre'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['nombre']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="username" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-user"></i> Nombre de Usuario <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control form-control-lg <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                       id="username" name="username"
                       value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                       placeholder="Ej: jlopez" required autocomplete="off">
                <?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['username']) ?></div>
                <?php else: ?>
                <small class="text-muted">Solo letras, números y guiones. Sin espacios.</small>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-bold fs-5">
                    Contraseña <span class="text-danger">*</span>
                </label>
                <input type="password" class="form-control form-control-lg <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       id="password" name="password"
                       placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                <?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                <?php else: ?>
                <small class="text-muted">La contraseña debe tener al menos 8 caracteres.</small>
                <?php endif; ?>
            </div>

            <div class="mb-5">
                <label for="rol_id" class="form-label fw-bold fs-5">
                    Rol <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-lg <?= isset($errors['rol_id']) ? 'is-invalid' : '' ?>"
                        id="rol_id" name="rol_id" required>
                    <option value="">-- Selecciona rol --</option>
                    <?php foreach ($roles as $r): ?>
                    <?php if ($r['id'] == ROL_GOD && !Auth::hasRole(ROL_GOD)) continue; ?>
                    <option value="<?= $r['id'] ?>"
                        <?= ($old['rol_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['rol_id'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['rol_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-institucional btn-lg px-5">
                    <i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>Crear Usuario
                </button>
                <a href="/usuarios" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fa-solid fa-circle-xmark me-2" aria-hidden="true"></i>Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
