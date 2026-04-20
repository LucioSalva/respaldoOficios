<?php $pageTitle = 'Agregar personal'; ?>
<?php $errors = $errors ?? []; $old = $old ?? []; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-user-plus me-2"></i>Agregar persona
    </h1>
    <a href="/personal" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Regresar
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="/personal/crear" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="nombre_completo" class="form-label fw-semibold">Nombre completo *</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="180"
                           class="form-control form-control-lg <?= isset($errors['nombre_completo']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['nombre_completo'] ?? '') ?>">
                    <?php if (isset($errors['nombre_completo'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['nombre_completo']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="numero_empleado" class="form-label fw-semibold">Número de empleado</label>
                    <input type="text" id="numero_empleado" name="numero_empleado" maxlength="20"
                           class="form-control form-control-lg <?= isset($errors['numero_empleado']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['numero_empleado'] ?? '') ?>"
                           placeholder="Ej. 92300">
                    <?php if (isset($errors['numero_empleado'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['numero_empleado']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="tipo_personal_id" class="form-label fw-semibold">Tipo *</label>
                    <select id="tipo_personal_id" name="tipo_personal_id" required
                            class="form-select form-select-lg <?= isset($errors['tipo_personal_id']) ? 'is-invalid' : '' ?>">
                        <option value="">Selecciona…</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= (string)($old['tipo_personal_id'] ?? '') === (string)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['tipo_personal_id'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['tipo_personal_id']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="categoria" class="form-label fw-semibold">Categoría</label>
                    <input type="text" id="categoria" name="categoria" maxlength="120"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($old['categoria'] ?? '') ?>"
                           placeholder="Ej. Auxiliar, Coordinador L…">
                </div>

                <div class="col-md-4">
                    <label for="horario" class="form-label fw-semibold">Horario</label>
                    <input type="text" id="horario" name="horario" maxlength="120"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($old['horario'] ?? '') ?>"
                           placeholder="Ej. 09:00 a 16:00 L-V">
                </div>

                <div class="col-12">
                    <label for="observaciones" class="form-label fw-semibold">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="3"
                              class="form-control form-control-lg"
                              maxlength="1000"><?= htmlspecialchars($old['observaciones'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-institucional btn-lg">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Guardar
                </button>
                <a href="/personal" class="btn btn-outline-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>
