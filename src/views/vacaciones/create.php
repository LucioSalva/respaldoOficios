<?php $pageTitle = 'Registrar movimiento de vacaciones'; ?>
<?php
$errors = $errors ?? [];
$old    = $old    ?? [];
$preseleccionId = (int)($_GET['personal_id'] ?? ($old['personal_id'] ?? 0));
$prePersona = null;
if ($preseleccionId > 0) {
    $stmt = Database::pdo()->prepare(
        "SELECT p.id, p.numero_empleado, p.nombre_completo, tp.nombre AS tipo_nombre
           FROM personal p
           JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
          WHERE p.id = :id"
    );
    $stmt->execute([':id' => $preseleccionId]);
    $prePersona = $stmt->fetch();
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="/vacaciones" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver
    </a>
    <h1 class="h3 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-plus me-2"></i>Registrar movimiento de vacaciones
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

<form method="POST" action="/vacaciones/crear" class="card border-0 shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
    <div class="card-body">
        <div class="row g-4">
            <!-- Empleado -->
            <div class="col-md-12">
                <label for="empleado_busqueda" class="form-label fw-semibold fs-5">
                    Empleado <span class="text-danger">*</span>
                </label>
                <input type="hidden" name="personal_id" id="personal_id"
                       value="<?= htmlspecialchars((string)($prePersona['id'] ?? $old['personal_id'] ?? '')) ?>">
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="empleado_busqueda" class="form-control form-control-lg"
                           placeholder="Escribe número o nombre del empleado"
                           autocomplete="off"
                           value="<?= $prePersona ? htmlspecialchars(($prePersona['numero_empleado'] ?? '') . ' - ' . $prePersona['nombre_completo']) : '' ?>">
                </div>
                <div id="empleado_resultados" class="list-group mt-1" style="max-height:240px;overflow:auto;"></div>
                <small id="empleado_seleccionado" class="text-success fs-6 mt-1 d-block">
                    <?php if ($prePersona): ?>
                        <i class="fa-solid fa-check-circle"></i>
                        Seleccionado: <?= htmlspecialchars($prePersona['nombre_completo']) ?>
                    <?php endif; ?>
                </small>
            </div>

            <div class="col-md-6">
                <label for="periodo_id" class="form-label fw-semibold fs-5">Periodo <span class="text-danger">*</span></label>
                <select id="periodo_id" name="periodo_id" class="form-select form-select-lg" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($periodos as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"
                        <?= (string)($old['periodo_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="estatus_id" class="form-label fw-semibold fs-5">Estado <span class="text-danger">*</span></label>
                <select id="estatus_id" name="estatus_id" class="form-select form-select-lg" required>
                    <?php foreach ($estatus as $e): ?>
                        <?php if ($e['clave'] === 'CANCELADA') continue; ?>
                        <option value="<?= (int)$e['id'] ?>"
                            <?= (string)($old['estatus_id'] ?? '') === (string)$e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fechas -->
            <div class="col-md-4">
                <label for="fecha_vacaciones" class="form-label fw-semibold fs-5">Fecha del movimiento</label>
                <input type="date" id="fecha_vacaciones" name="fecha_vacaciones"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['fecha_vacaciones'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_inicio" class="form-label fw-semibold fs-5">Inicio de vacaciones</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_fin" class="form-label fw-semibold fs-5">Fin de vacaciones</label>
                <input type="date" id="fecha_fin" name="fecha_fin"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['fecha_fin'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_regreso" class="form-label fw-semibold fs-5">Regresa a laborar</label>
                <input type="date" id="fecha_regreso" name="fecha_regreso"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['fecha_regreso'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_recibido_coord" class="form-label fw-semibold fs-5">Recibido por coordinación</label>
                <input type="date" id="fecha_recibido_coord" name="fecha_recibido_coord"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['fecha_recibido_coord'] ?? '') ?>">
            </div>

            <!-- Dias -->
            <div class="col-md-4">
                <label for="dias_corresponden" class="form-label fw-semibold fs-5">Días que corresponden</label>
                <input type="number" id="dias_corresponden" name="dias_corresponden"
                       min="0" max="366"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['dias_corresponden'] ?? '0') ?>">
            </div>
            <div class="col-md-4">
                <label for="dias_tomados" class="form-label fw-semibold fs-5">Días otorgados</label>
                <input type="number" id="dias_tomados" name="dias_tomados"
                       min="0" max="366"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['dias_tomados'] ?? '0') ?>">
            </div>
            <div class="col-md-4">
                <label for="dias_pendientes_excel" class="form-label fw-semibold fs-5">Días pendientes (referencia)</label>
                <input type="number" id="dias_pendientes_excel" name="dias_pendientes_excel"
                       min="0" max="366"
                       class="form-control form-control-lg"
                       value="<?= htmlspecialchars($old['dias_pendientes_excel'] ?? '') ?>">
                <small class="text-muted">Si lo llenas y no coincide con el cálculo, se marcará como inconsistencia.</small>
            </div>

            <div class="col-md-6">
                <label for="folio_tm_vacaciones" class="form-label fw-semibold fs-5">Folio TM (vacaciones)</label>
                <input type="text" id="folio_tm_vacaciones" name="folio_tm_vacaciones"
                       maxlength="60"
                       class="form-control form-control-lg"
                       placeholder="Ej. TM/ECA/CA/053/2026"
                       value="<?= htmlspecialchars($old['folio_tm_vacaciones'] ?? '') ?>">
                <small class="text-muted">Formato libre, distinto al folio de oficios.</small>
            </div>

            <div class="col-md-12">
                <label for="observaciones" class="form-label fw-semibold fs-5">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="3"
                          maxlength="1000"
                          class="form-control form-control-lg"
                          placeholder="Notas opcionales"><?= htmlspecialchars($old['observaciones'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2">
        <a href="/vacaciones" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        <button type="submit" class="btn btn-institucional btn-lg">
            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar
        </button>
    </div>
</form>

<script>
(function () {
    const inp   = document.getElementById('empleado_busqueda');
    const hid   = document.getElementById('personal_id');
    const list  = document.getElementById('empleado_resultados');
    const tag   = document.getElementById('empleado_seleccionado');
    let t = null;
    function hide(){ list.innerHTML=''; }
    inp.addEventListener('input', function(){
        const q = inp.value.trim();
        hid.value = '';
        tag.innerHTML = '';
        if (t) clearTimeout(t);
        if (q.length < 2) { hide(); return; }
        t = setTimeout(function(){
            fetch('/vacaciones/buscar-personal?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(j => {
                    list.innerHTML = '';
                    (j.data || []).forEach(function(r){
                        const a = document.createElement('button');
                        a.type = 'button';
                        a.className = 'list-group-item list-group-item-action';
                        a.innerHTML = '<strong>' + (r.numero_empleado || '—') + '</strong> — ' +
                                      (r.nombre_completo || '') +
                                      ' <span class="badge bg-secondary ms-2">' + (r.tipo_clave || '') + '</span>';
                        a.addEventListener('click', function(){
                            hid.value = r.id;
                            inp.value = (r.numero_empleado || '') + ' - ' + (r.nombre_completo || '');
                            tag.innerHTML = '<i class="fa-solid fa-check-circle"></i> Seleccionado: ' + r.nombre_completo;
                            hide();
                        });
                        list.appendChild(a);
                    });
                })
                .catch(function(){ hide(); });
        }, 250);
    });
    document.addEventListener('click', function(e){
        if (!list.contains(e.target) && e.target !== inp) hide();
    });
})();
</script>
