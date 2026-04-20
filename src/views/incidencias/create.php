<?php $pageTitle = 'Registrar incidencia'; ?>
<?php $errors = $errors ?? []; $old = $old ?? []; ?>
<?php $preseleccion = (int)($_GET['personal_id'] ?? ($old['personal_id'] ?? 0)); ?>
<?php
    // Mapa id->datos para mostrar num y tipo tras seleccionar
    $mapa = [];
    foreach ($personal as $p) {
        $mapa[(int)$p['id']] = [
            'num'   => $p['numero_empleado'] ?? '',
            'tipo'  => $p['tipo_nombre']     ?? '',
            'clave' => $p['tipo_clave']      ?? '',
        ];
    }
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-plus me-2"></i>Registrar incidencia
    </h1>
    <a href="/incidencias" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Regresar
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="/incidencias/crear" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="personal_id" class="form-label fw-semibold">Buscar empleado *</label>
                    <select id="personal_id" name="personal_id" required
                            class="form-select form-select-lg <?= isset($errors['personal_id']) ? 'is-invalid' : '' ?>"
                            data-personal-map='<?= htmlspecialchars(json_encode($mapa, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                        <option value="">Seleccione un empleado…</option>
                        <?php foreach ($personal as $p): ?>
                            <?php $sel = (int)($old['personal_id'] ?? $preseleccion) === (int)$p['id']; ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre_completo']) ?>
                                — N° <?= htmlspecialchars($p['numero_empleado'] ?? 's/n') ?>
                                (<?= htmlspecialchars($p['tipo_nombre']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['personal_id'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['personal_id']) ?></div>
                    <?php endif; ?>
                    <div id="personal_info" class="form-text text-dark mt-1" aria-live="polite"></div>
                </div>

                <div class="col-md-4">
                    <label for="tipo_incidencia_id" class="form-label fw-semibold">Seleccione el tipo de incidencia *</label>
                    <select id="tipo_incidencia_id" name="tipo_incidencia_id" required
                            class="form-select form-select-lg <?= isset($errors['tipo_incidencia_id']) ? 'is-invalid' : '' ?>">
                        <option value="">Seleccione…</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= (string)($old['tipo_incidencia_id'] ?? '') === (string)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['tipo_incidencia_id'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['tipo_incidencia_id']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="estatus_id" class="form-label fw-semibold">Estatus *</label>
                    <select id="estatus_id" name="estatus_id" required
                            class="form-select form-select-lg <?= isset($errors['estatus_id']) ? 'is-invalid' : '' ?>">
                        <?php foreach ($estatus as $e): ?>
                            <option value="<?= (int)$e['id'] ?>"
                                <?= (string)($old['estatus_id'] ?? '') === (string)$e['id']
                                        ? 'selected'
                                        : ($e['clave'] === 'REGISTRADA' && empty($old) ? 'selected' : '') ?>>
                                <?= htmlspecialchars($e['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="fecha_incidencia" class="form-label fw-semibold">Fecha de la incidencia *</label>
                    <input type="date" id="fecha_incidencia" name="fecha_incidencia"
                           class="form-control form-control-lg <?= isset($errors['fecha_incidencia']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['fecha_incidencia'] ?? '') ?>">
                    <?php if (isset($errors['fecha_incidencia'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['fecha_incidencia']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="fecha_inicio" class="form-label fw-semibold">Día a justificar (inicio)</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio"
                           class="form-control form-control-lg <?= isset($errors['fecha_inicio']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['fecha_inicio'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label for="fecha_fin" class="form-label fw-semibold">Día a justificar (fin)</label>
                    <input type="date" id="fecha_fin" name="fecha_fin"
                           class="form-control form-control-lg <?= isset($errors['fecha_fin']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['fecha_fin'] ?? '') ?>">
                    <?php if (isset($errors['fecha_fin'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['fecha_fin']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="fecha_recibido_coord" class="form-label fw-semibold">Recibido por coordinación</label>
                    <input type="date" id="fecha_recibido_coord" name="fecha_recibido_coord"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($old['fecha_recibido_coord'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label for="periodo" class="form-label fw-semibold">Periodo</label>
                    <input type="text" id="periodo" name="periodo" maxlength="40"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($old['periodo'] ?? '') ?>"
                           placeholder="Ej. 1ER 2026 / ABRIL 2026">
                </div>

                <div class="col-md-2">
                    <label for="anio" class="form-label fw-semibold">Año</label>
                    <input type="number" id="anio" name="anio" min="2020" max="2100"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($old['anio'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="mes" class="form-label fw-semibold">Mes</label>
                    <input type="number" id="mes" name="mes" min="1" max="12"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($old['mes'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="quincena" class="form-label fw-semibold">Quincena</label>
                    <select id="quincena" name="quincena" class="form-select form-select-lg">
                        <option value="">—</option>
                        <option value="1" <?= (string)($old['quincena'] ?? '') === '1' ? 'selected' : '' ?>>1ª (1-15)</option>
                        <option value="2" <?= (string)($old['quincena'] ?? '') === '2' ? 'selected' : '' ?>>2ª (16-fin)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="dias" class="form-label fw-semibold">Días</label>
                    <input type="number" id="dias" name="dias" min="0" max="366"
                           class="form-control form-control-lg <?= isset($errors['dias']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['dias'] ?? '0') ?>">
                </div>
                <div class="col-md-2">
                    <label for="horas" class="form-label fw-semibold">Horas</label>
                    <input type="number" id="horas" name="horas" min="0" max="23"
                           class="form-control form-control-lg <?= isset($errors['horas']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['horas'] ?? '0') ?>">
                </div>
                <div class="col-md-2">
                    <label for="minutos" class="form-label fw-semibold">Minutos</label>
                    <input type="number" id="minutos" name="minutos" min="0" max="59"
                           class="form-control form-control-lg <?= isset($errors['minutos']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['minutos'] ?? '0') ?>">
                </div>
                <div class="col-md-6">
                    <label for="folio_justificacion" class="form-label fw-semibold">Folio de justificación (opcional)</label>
                    <input type="text" id="folio_justificacion" name="folio_justificacion" maxlength="60"
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($old['folio_justificacion'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label for="motivo" class="form-label fw-semibold">Motivo / descripción breve</label>
                    <textarea id="motivo" name="motivo" rows="2" maxlength="500"
                              class="form-control form-control-lg"><?= htmlspecialchars($old['motivo'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <label for="observaciones" class="form-label fw-semibold">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000"
                              class="form-control form-control-lg"><?= htmlspecialchars($old['observaciones'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <label for="justificacion" class="form-label fw-semibold">Justificación formal (opcional)</label>
                    <textarea id="justificacion" name="justificacion" rows="3" maxlength="2000"
                              class="form-control form-control-lg"><?= htmlspecialchars($old['justificacion'] ?? '') ?></textarea>
                    <small class="text-muted">Si captura una justificación, el estatus se cambia normalmente a "Justificada".</small>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-institucional btn-lg">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Guardar incidencia
                </button>
                <a href="/incidencias" class="btn btn-outline-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Al seleccionar empleado, mostrar num y tipo.
(function () {
    const sel = document.getElementById('personal_id');
    const info = document.getElementById('personal_info');
    if (!sel || !info) return;
    let mapa = {};
    try { mapa = JSON.parse(sel.dataset.personalMap || '{}'); } catch (e) { mapa = {}; }
    function paint() {
        const id = parseInt(sel.value, 10);
        if (!id || !mapa[id]) { info.innerHTML = ''; return; }
        const d = mapa[id];
        info.innerHTML = '<i class="fa-solid fa-id-card me-1"></i>' +
            'N° <strong>' + (d.num || 's/n') + '</strong> · ' +
            'Tipo: <strong>' + (d.tipo || '—') + '</strong>';
    }
    sel.addEventListener('change', paint);
    paint();
})();
</script>
