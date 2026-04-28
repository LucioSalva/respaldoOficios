<?php $pageTitle = 'Editar personal'; ?>
<?php $errors = $errors ?? []; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-pen-to-square me-2"></i>Editar persona
    </h1>
    <a href="<?= url_path('/personal/'.(int)$persona['id']) ?>" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Regresar
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="<?= url_path('/personal/'.(int)$persona['id'].'/editar') ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="nombre_completo" class="form-label fw-semibold">Nombre completo *</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="180"
                           class="form-control form-control-lg <?= isset($errors['nombre_completo']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($persona['nombre_completo']) ?>">
                    <?php if (isset($errors['nombre_completo'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['nombre_completo']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="numero_empleado" class="form-label fw-semibold">NÃºmero de empleado</label>
                    <input type="text" id="numero_empleado" name="numero_empleado" maxlength="20"
                           class="form-control form-control-lg <?= isset($errors['numero_empleado']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($persona['numero_empleado'] ?? '') ?>">
                    <?php if (isset($errors['numero_empleado'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['numero_empleado']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="tipo_personal_id" class="form-label fw-semibold">Tipo *</label>
                    <select id="tipo_personal_id" name="tipo_personal_id" required
                            class="form-select form-select-lg <?= isset($errors['tipo_personal_id']) ? 'is-invalid' : '' ?>">
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= (int)($persona['tipo_personal_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="categoria" class="form-label fw-semibold">CategorÃ­a</label>
                    <input type="text" id="categoria" name="categoria" maxlength="120"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($persona['categoria'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label for="horario" class="form-label fw-semibold">Horario</label>
                    <input type="text" id="horario" name="horario" maxlength="120"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($persona['horario'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label for="observaciones" class="form-label fw-semibold">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="3"
                              class="form-control form-control-lg"
                              maxlength="1000"><?= htmlspecialchars($persona['observaciones'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1"
                               <?= !empty($persona['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">
                            Persona activa
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-institucional btn-lg">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Guardar cambios
                </button>
                <a href="<?= url_path('/personal/'.(int)$persona['id']) ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>

