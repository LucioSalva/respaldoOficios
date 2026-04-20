<?php $pageTitle = 'Editar incidencia'; ?>
<?php $errors = $errors ?? []; $old = $old ?? []; ?>
<?php $data = array_merge($inc, $old); ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-pen me-2"></i>Editar incidencia #<?= (int)$inc['id'] ?>
    </h1>
    <a href="/incidencias/<?= (int)$inc['id'] ?>" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Regresar
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info me-1"></i>
            <strong>Empleado:</strong> <?= htmlspecialchars($inc['nombre_completo']) ?>
            — N° <?= htmlspecialchars($inc['numero_empleado'] ?? 's/n') ?>
            (<?= htmlspecialchars($inc['tipo_personal_nombre']) ?>)
            <br>
            <small class="text-muted">
                Capturado por: <?= htmlspecialchars($inc['capturo_nombre'] ?? '—') ?>
                · Creado: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($inc['created_at']))) ?>
                · Última edición: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($inc['updated_at']))) ?>
            </small>
        </div>

        <form method="POST" action="/incidencias/<?= (int)$inc['id'] ?>/editar" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="tipo_incidencia_id" class="form-label fw-semibold">Tipo de incidencia *</label>
                    <select id="tipo_incidencia_id" name="tipo_incidencia_id" required
                            class="form-select form-select-lg <?= isset($errors['tipo_incidencia_id']) ? 'is-invalid' : '' ?>">
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= (int)$data['tipo_incidencia_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="estatus_id" class="form-label fw-semibold">Estatus *</label>
                    <select id="estatus_id" name="estatus_id" required
                            class="form-select form-select-lg <?= isset($errors['estatus_id']) ? 'is-invalid' : '' ?>">
                        <?php foreach ($estatus as $e): ?>
                            <option value="<?= (int)$e['id'] ?>"
                                <?= (int)$data['estatus_id'] === (int)$e['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="fecha_incidencia" class="form-label fw-semibold">Fecha de la incidencia</label>
                    <input type="date" id="fecha_incidencia" name="fecha_incidencia"
                           class="form-control form-control-lg <?= isset($errors['fecha_incidencia']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($data['fecha_incidencia'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="fecha_inicio" class="form-label fw-semibold">Día a justificar (inicio)</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['fecha_inicio'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="fecha_fin" class="form-label fw-semibold">Día a justificar (fin)</label>
                    <input type="date" id="fecha_fin" name="fecha_fin"
                           class="form-control form-control-lg <?= isset($errors['fecha_fin']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($data['fecha_fin'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="fecha_recibido_coord" class="form-label fw-semibold">Recibido por coordinación</label>
                    <input type="date" id="fecha_recibido_coord" name="fecha_recibido_coord"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['fecha_recibido_coord'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label for="periodo" class="form-label fw-semibold">Periodo</label>
                    <input type="text" id="periodo" name="periodo" maxlength="40"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['periodo'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="anio" class="form-label fw-semibold">Año</label>
                    <input type="number" id="anio" name="anio" min="2020" max="2100"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['anio'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="mes" class="form-label fw-semibold">Mes</label>
                    <input type="number" id="mes" name="mes" min="1" max="12"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['mes'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="quincena" class="form-label fw-semibold">Quincena</label>
                    <select id="quincena" name="quincena" class="form-select form-select-lg">
                        <option value="">—</option>
                        <option value="1" <?= (string)($data['quincena'] ?? '') === '1' ? 'selected' : '' ?>>1ª</option>
                        <option value="2" <?= (string)($data['quincena'] ?? '') === '2' ? 'selected' : '' ?>>2ª</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="dias" class="form-label fw-semibold">Días</label>
                    <input type="number" id="dias" name="dias" min="0" max="366"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['dias'] ?? '0') ?>">
                </div>
                <div class="col-md-2">
                    <label for="horas" class="form-label fw-semibold">Horas</label>
                    <input type="number" id="horas" name="horas" min="0" max="23"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['horas'] ?? '0') ?>">
                </div>
                <div class="col-md-2">
                    <label for="minutos" class="form-label fw-semibold">Minutos</label>
                    <input type="number" id="minutos" name="minutos" min="0" max="59"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['minutos'] ?? '0') ?>">
                </div>
                <div class="col-md-6">
                    <label for="folio_justificacion" class="form-label fw-semibold">Folio de justificación</label>
                    <input type="text" id="folio_justificacion" name="folio_justificacion" maxlength="60"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($data['folio_justificacion'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label for="motivo" class="form-label fw-semibold">Motivo</label>
                    <textarea id="motivo" name="motivo" rows="2" maxlength="500"
                              class="form-control form-control-lg"><?= htmlspecialchars($data['motivo'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label for="observaciones" class="form-label fw-semibold">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000"
                              class="form-control form-control-lg"><?= htmlspecialchars($data['observaciones'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label for="justificacion" class="form-label fw-semibold">Justificación formal</label>
                    <textarea id="justificacion" name="justificacion" rows="3" maxlength="2000"
                              class="form-control form-control-lg"><?= htmlspecialchars($data['justificacion'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-institucional btn-lg">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Guardar cambios
                </button>
                <a href="/incidencias/<?= (int)$inc['id'] ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>
