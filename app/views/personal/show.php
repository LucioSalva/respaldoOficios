<?php $pageTitle = 'Detalle de persona'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-id-badge me-2"></i><?= htmlspecialchars($persona['nombre_completo']) ?>
    </h1>
    <div class="d-flex gap-2">
        <?php if (Auth::hasRole([ROL_GOD, ROL_ADMIN])): ?>
        <a href="<?= url_path('/personal/'.(int)$persona['id'].'/editar') ?>" class="btn btn-outline-secondary btn-lg">
            <i class="fa-solid fa-pen-to-square me-1"></i>Editar
        </a>
        <?php endif; ?>
        <a href="<?= url_path('/personal') ?>" class="btn btn-outline-secondary btn-lg">
            <i class="fa-solid fa-arrow-left me-1"></i>Regresar
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">NÃºmero de empleado</p>
                <p class="h4 fw-bold mb-0"><?= htmlspecialchars($persona['numero_empleado'] ?? 'â€”') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Tipo</p>
                <span class="badge bg-<?= $persona['tipo_clave'] === 'SINDICALIZADO' ? 'primary' : 'warning text-dark' ?> fs-6">
                    <?= htmlspecialchars($persona['tipo_nombre']) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">CategorÃ­a</p>
                <p class="h5 mb-0"><?= htmlspecialchars($persona['categoria'] ?? 'â€”') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Horario</p>
                <p class="h6 mb-0"><?= htmlspecialchars($persona['horario'] ?? 'â€”') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h2 class="h4 mb-0"><i class="fa-solid fa-umbrella-beach me-2"></i>Historial de vacaciones</h2>
        <a href="<?= url_path('/vacaciones/crear?personal_id='.(int)$persona['id']) ?>" class="btn btn-institucional">
            <i class="fa-solid fa-plus me-1"></i>Registrar vacaciones
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-lg mb-0">
            <thead class="table-light">
                <tr>
                    <th>Periodo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Regreso</th>
                    <th>DÃ­as</th>
                    <th>Pend.</th>
                    <th>Estatus</th>
                    <th class="text-center">Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                <tr><td colspan="8" class="text-center p-4 text-muted">Sin registros de vacaciones.</td></tr>
                <?php endif; ?>
                <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= htmlspecialchars($h['periodo'] ?? 'â€”') ?></td>
                    <td><?= $h['fecha_inicio'] ? date('d/m/Y', strtotime($h['fecha_inicio'])) : 'â€”' ?></td>
                    <td><?= $h['fecha_fin']    ? date('d/m/Y', strtotime($h['fecha_fin']))    : 'â€”' ?></td>
                    <td><?= $h['fecha_regreso']? date('d/m/Y', strtotime($h['fecha_regreso'])): 'â€”' ?></td>
                    <td><?= (int)$h['dias_disfrutados'] ?>/<?= (int)$h['dias_solicitados'] ?></td>
                    <td><?= (int)$h['dias_pendientes'] ?></td>
                    <td>
                        <span class="badge bg-<?= htmlspecialchars($h['estatus_color']) ?>">
                            <?= htmlspecialchars($h['estatus_nombre']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="<?= url_path('/vacaciones/'.(int)$h['id']) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
    $incidencias        = $incidencias        ?? [];
    $incidenciasResumen = $incidenciasResumen ?? [];
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h4 mb-0">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>Historial de incidencias
            <?php if (!empty($incidenciasResumen['total'])): ?>
                <span class="badge bg-secondary ms-2"><?= (int)$incidenciasResumen['total'] ?></span>
            <?php endif; ?>
        </h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url_path('/incidencias?q='.urlencode($persona['numero_empleado'] ?? '')) ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-list me-1"></i>Ver todas
            </a>
            <a href="<?= url_path('/incidencias/crear?personal_id='.(int)$persona['id']) ?>" class="btn btn-institucional">
                <i class="fa-solid fa-plus me-1"></i>Registrar incidencia
            </a>
        </div>
    </div>

    <?php if (!empty($incidenciasResumen)): ?>
    <div class="card-body bg-light border-bottom">
        <div class="row g-2 text-center">
            <div class="col-6 col-md-3 col-lg-2">
                <div class="p-2 rounded bg-white border">
                    <small class="text-muted d-block">Faltas</small>
                    <strong class="h5 mb-0 text-danger"><?= (int)($incidenciasResumen['faltas'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="p-2 rounded bg-white border">
                    <small class="text-muted d-block">Retardos</small>
                    <strong class="h5 mb-0 text-warning"><?= (int)($incidenciasResumen['retardos'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="p-2 rounded bg-white border">
                    <small class="text-muted d-block">OmisiÃ³n entrada</small>
                    <strong class="h5 mb-0"><?= (int)($incidenciasResumen['omision_entrada'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="p-2 rounded bg-white border">
                    <small class="text-muted d-block">OmisiÃ³n salida</small>
                    <strong class="h5 mb-0"><?= (int)($incidenciasResumen['omision_salida'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="p-2 rounded bg-white border">
                    <small class="text-muted d-block">Comisiones</small>
                    <strong class="h5 mb-0 text-primary"><?= (int)($incidenciasResumen['comisiones'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="p-2 rounded bg-white border">
                    <small class="text-muted d-block">Justificadas</small>
                    <strong class="h5 mb-0 text-success"><?= (int)($incidenciasResumen['justificadas'] ?? 0) ?></strong>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover table-lg mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha incidencia</th>
                    <th>Tipo</th>
                    <th>DÃ­a a justificar</th>
                    <th>Estatus</th>
                    <th>Motivo</th>
                    <th class="text-center">Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incidencias)): ?>
                <tr><td colspan="6" class="text-center p-4 text-muted">Sin incidencias registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($incidencias as $i): ?>
                <tr>
                    <td><?= $i['fecha_incidencia'] ? date('d/m/Y', strtotime($i['fecha_incidencia'])) : 'â€”' ?></td>
                    <td>
                        <span class="badge bg-<?= htmlspecialchars($i['tipo_color']) ?>">
                            <?php if (!empty($i['tipo_icono'])): ?>
                                <i class="fa-solid <?= htmlspecialchars($i['tipo_icono']) ?> me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($i['tipo_nombre']) ?>
                        </span>
                    </td>
                    <td><?= $i['fecha_inicio'] ? date('d/m/Y', strtotime($i['fecha_inicio'])) : 'â€”' ?></td>
                    <td>
                        <span class="badge bg-<?= htmlspecialchars($i['estatus_color']) ?>">
                            <?= htmlspecialchars($i['estatus_nombre']) ?>
                        </span>
                    </td>
                    <td class="text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($i['motivo'] ?? '') ?>">
                        <?= htmlspecialchars(mb_strimwidth($i['motivo'] ?? '', 0, 80, 'â€¦')) ?>
                    </td>
                    <td class="text-center">
                        <a href="<?= url_path('/incidencias/'.(int)$i['id']) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

