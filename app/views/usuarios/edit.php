<?php
$pageTitle = 'Editar Usuario';
$errors    = $errors ?? [];
$old_vals  = $old    ?? $usuario;
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h2 fw-bold text-institucional mb-1">
            <i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Editar Usuario
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url_path('/usuarios') ?>">Usuarios</a></li>
                <li class="breadcrumb-item active">Editar</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:700px;">
    <div class="card-body p-4 p-md-5">
        <form method="POST" action="<?= url_path('/usuarios/'.$usuario['id'].'/editar') ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <div class="mb-4">
                <label for="nombre" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-id-card"></i> Nombre Completo <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control form-control-lg <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>"
                       id="nombre" name="nombre"
                       value="<?= htmlspecialchars($old_vals['nombre'] ?? '') ?>" required>
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
                       value="<?= htmlspecialchars($old_vals['username'] ?? '') ?>" required autocomplete="off">
                <?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['username']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-bold fs-5">Nueva ContraseÃ±a</label>
                <input type="password" class="form-control form-control-lg <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       id="password" name="password"
                       placeholder="Dejar vacÃ­o para no cambiar" autocomplete="new-password">
                <?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                <?php else: ?>
                <small class="text-muted">Dejar vacÃ­o para mantener la contraseÃ±a actual.</small>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="rol_id" class="form-label fw-bold fs-5">
                    Rol <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-lg <?= isset($errors['rol_id']) ? 'is-invalid' : '' ?>"
                        id="rol_id" name="rol_id" required>
                    <?php foreach ($roles as $r): ?>
                    <?php if ($r['id'] == ROL_GOD && !Auth::hasRole(ROL_GOD)) continue; ?>
                    <option value="<?= $r['id'] ?>"
                        <?= ($old_vals['rol_id'] ?? $usuario['rol_id']) == $r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['rol_id'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['rol_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-5">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo"
                           <?= ($old_vals['activo'] ?? $usuario['activo']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="activo">Usuario Activo</label>
                </div>
                <?php if ($usuario['id'] === Auth::userId()): ?>
                <small class="text-warning">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    No puedes desactivar tu propia cuenta.
                </small>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-institucional btn-lg px-5">
                    <i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>Guardar Cambios
                </button>
                <a href="<?= url_path('/usuarios') ?>" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fa-solid fa-circle-xmark me-2" aria-hidden="true"></i>Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

