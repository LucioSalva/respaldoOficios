<?php $pageTitle = 'Editar movimiento de vacaciones'; ?>
<?php $errors = $errors ?? []; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="/vacaciones/empleado/<?= (int)$mov['personal_id'] ?>" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver al empleado
    </a>
    <h1 class="h3 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-pen me-2"></i>Editar movimiento
    </h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger fs-5">
    <strong>Revisa los campos:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="/vacaciones/mov/<?= (int)$mov['id'] ?>/editar" class="card border-0 shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
    <input type="hidden" name="personal_id" value="<?= (int)$mov['personal_id'] ?>">

    <div class="card-body">
        <div class="alert alert-light border fs-5">
            <strong><?= htmlspecialchars($mov['nombre_completo']) ?></strong>
            <small class="text-muted ms-2">N° <?= htmlspecialchars($mov['numero_empleado'] ?? '—') ?></small>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <label for="periodo_id" class="form-label fw-semibold fs-5">Periodo <span class="text-danger">*</span></label>
                <select id="periodo_id" name="periodo_id" class="form-select form-select-lg" required>
                    <?php foreach ($periodos as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"
                        <?= (int)$mov['periodo_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="estatus_id" class="form-label fw-semibold fs-5">Estado <span class="text-danger">*</span></label>
                <select id="estatus_id" name="estatus_id" class="form-select form-select-lg" required>
                    <?php foreach ($estatus as $e): ?>
                    <option value="<?= (int)$e['id'] ?>"
                        <?= ($mov['estatus_clave'] ?? '') === $e['clave'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="fecha_vacaciones" class="form-label fw-semibold fs-5">Fecha del movimiento</label>
                <input type="date" id="fecha_vacaciones" name="fecha_vacaciones"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($mov['fecha_vacaciones'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_inicio" class="form-label fw-semibold fs-5">Inicio</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($mov['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_fin" class="form-label fw-semibold fs-5">Fin</label>
                <input type="date" id="fecha_fin" name="fecha_fin"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($mov['fecha_fin'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_regreso" class="form-label fw-semibold fs-5">Regresa</label>
                <input type="date" id="fecha_regreso" name="fecha_regreso"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($mov['fecha_regreso'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_recibido_coord" class="form-label fw-semibold fs-5">Recibido por coordinación</label>
                <input type="date" id="fecha_recibido_coord" name="fecha_recibido_coord"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($mov['fecha_recibido_coord'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label for="dias_corresponden" class="form-label fw-semibold fs-5">Días que corresponden</label>
                <input type="number" id="dias_corresponden" name="dias_corresponden" min="0" max="366"
                       class="form-control form-control-lg"
                       value="<?= (int)($mov['dias_corresponden'] ?? 0) ?>">
            </div>
            <div class="col-md-4">
                <label for="dias_tomados" class="form-label fw-semibold fs-5">Días otorgados</label>
                <input type="number" id="dias_tomados" name="dias_tomados" min="0" max="366"
                       class="form-control form-control-lg"
                       value="<?= (int)($mov['dias_tomados'] ?? 0) ?>">
            </div>
            <div class="col-md-4">
                <label for="dias_pendientes_excel" class="form-label fw-semibold fs-5">Días pendientes (ref.)</label>
                <input type="number" id="dias_pendientes_excel" name="dias_pendientes_excel" min="0" max="366"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars((string)($mov['dias_pendientes_excel'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label for="folio_tm_vacaciones" class="form-label fw-semibold fs-5">Folio TM</label>
                <input type="text" id="folio_tm_vacaciones" name="folio_tm_vacaciones"
                       maxlength="60"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($mov['folio_tm_vacaciones'] ?? '') ?>">
            </div>

            <div class="col-md-12">
                <label for="observaciones" class="form-label fw-semibold fs-5">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000"
                          class="form-control form-control-lg"><?= htmlspecialchars($mov['observaciones'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2">
        <a href="/vacaciones/empleado/<?= (int)$mov['personal_id'] ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        <button type="submit" class="btn btn-institucional btn-lg">
            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar cambios
        </button>
    </div>
</form>
