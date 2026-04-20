<?php $pageTitle = 'Importar datos'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-file-import me-2"></i>Importar datos
    </h1>
</div>

<div class="alert alert-warning">
    <strong>Importante:</strong> La importación real se realiza desde la terminal con los
    scripts Python del directorio <code>tools/</code>. Esta pantalla solo permite
    <em>subir</em> el archivo Excel al servidor y ver el estado de las tablas de
    <em>staging</em>.
</div>

<!-- Subida -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-3">
        <h2 class="h5 mb-0"><i class="fa-solid fa-upload me-2"></i>Subir archivo Excel</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="/importar/subir" enctype="multipart/form-data" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
            <div class="col-md-8">
                <label for="excel" class="form-label fw-semibold">Archivo Excel (.xlsx, máximo 12 MB)</label>
                <input type="file" id="excel" name="excel" accept=".xlsx,.xls" required
                       class="form-control form-control-lg">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-institucional btn-lg w-100">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Subir archivo
                </button>
            </div>
            <div class="col-12 small text-muted">
                Al subir el archivo queda guardado en <code>uploads/imports/</code> con nombre seguro.
                Después, ejecute el script Python correspondiente en el servidor para procesarlo.
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas staging -->
<div class="row g-3 mb-3">
    <?php
    $cards = [
        'personal'             => ['Personal',             'fa-id-badge',         'primary'],
        'vacaciones'           => ['Vacaciones',           'fa-umbrella-beach',   'warning'],
        'folios_interno_sub'   => ['Folios Interno Sub',   'fa-folder-tree',      'info'],
        'incidencias'          => ['Incidencias',          'fa-triangle-exclamation', 'danger'],
        'oficios_conocimiento' => ['Oficios Conocimiento', 'fa-envelope-open-text',   'secondary'],
    ];
    ?>
    <?php foreach ($cards as $k => $c): $s = $stats[$k] ?? null; if (!$s) continue; ?>
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h5 mb-3">
                    <i class="fa-solid <?= $c[1] ?> text-<?= $c[2] ?> me-2"></i>
                    Staging <?= $c[0] ?>
                </h3>
                <div class="row g-2 small">
                    <div class="col-6">
                        <div class="text-muted">Leídos</div>
                        <div class="fw-bold text-dark fs-5"><?= (int)$s['total'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Procesados</div>
                        <div class="fw-bold text-success fs-5"><?= (int)$s['procesados'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Pendientes</div>
                        <div class="fw-bold text-warning"><?= (int)$s['pendientes'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Creados</div>
                        <div class="fw-bold text-primary"><?= (int)$s['creados'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Actualizados</div>
                        <div class="fw-bold text-info"><?= (int)$s['actualizados'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Duplicados</div>
                        <div class="fw-bold text-secondary"><?= (int)$s['duplicados'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">En revisión</div>
                        <div class="fw-bold text-warning"><?= (int)$s['en_revision'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Con error</div>
                        <div class="fw-bold text-danger"><?= (int)$s['con_error'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Comandos recomendados -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-3">
        <h2 class="h5 mb-0"><i class="fa-solid fa-terminal me-2"></i>Comandos para ejecutar en el servidor</h2>
    </div>
    <div class="card-body">
        <p class="mb-2">Desde la raíz del proyecto, y con acceso a la base de datos PostgreSQL:</p>
        <pre class="bg-light p-3 rounded small mb-0"><code># Personal (desde BASE VACACIONES 2026.xlsx / BASE GENERAL.xlsx)
python tools/importar_personal.py --staging
python tools/importar_personal.py --importar

# Vacaciones (3 hojas: VACACIONES, VACACIONES 2025, VACACIONES SINDICALIZADOS 2026)
python tools/importar_vacaciones.py --staging
python tools/importar_vacaciones.py --importar

# Folios Interno Sub (reproceso)
python tools/importar_internos.py --staging
python tools/importar_internos.py --importar

# Incidencias (hojas de reporte de incidencias)
python tools/importar_incidencias.py --staging
python tools/importar_incidencias.py --importar

# Oficios de Conocimiento (buzón interno)
python tools/importar_conocimiento.py --staging
python tools/importar_conocimiento.py --importar
</code></pre>
    </div>
</div>

<!-- Errores -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h2 class="h5 mb-0"><i class="fa-solid fa-circle-exclamation me-2"></i>Últimos errores / pendientes de revisión</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Origen</th>
                    <th>Fuente</th>
                    <th>Fila Excel</th>
                    <th>Estado</th>
                    <th>Mensaje</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($errores)): ?>
                <tr><td colspan="6" class="text-center p-3 text-muted">Sin errores pendientes.</td></tr>
                <?php endif; ?>
                <?php foreach ($errores as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['origen']) ?></td>
                    <td class="small"><?= htmlspecialchars($e['fuente'] ?? '—') ?></td>
                    <td><?= (int)$e['fila_excel'] ?></td>
                    <td>
                        <span class="badge bg-<?= $e['estado_revision'] === 'PENDIENTE_REVISION' ? 'warning text-dark' : 'danger' ?>">
                            <?= htmlspecialchars($e['estado_revision']) ?>
                        </span>
                    </td>
                    <td class="small"><?= htmlspecialchars($e['mensaje'] ?? '') ?></td>
                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
