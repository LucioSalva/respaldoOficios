<?php $pageTitle = 'Vacaciones: ' . ($persona['nombre_completo'] ?? ''); ?>

<?php
function vac_fmtFecha($d) {
    if (!$d) return '—';
    return date('d/m/Y', strtotime($d));
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="/vacaciones" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver
    </a>
    <h1 class="h3 fw-bold text-institucional mb-0 flex-grow-1">
        <i class="fa-solid fa-user me-2"></i><?= htmlspecialchars($persona['nombre_completo']) ?>
    </h1>
    <a href="/vacaciones/crear?personal_id=<?= (int)$persona['id'] ?>"
       class="btn btn-institucional btn-lg">
        <i class="fa-solid fa-plus me-2"></i>Registrar movimiento
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body fs-5">
        <div class="row g-3">
            <div class="col-md-4">
                <small class="text-muted d-block">Número de empleado</small>
                <strong><?= htmlspecialchars($persona['numero_empleado'] ?? '—') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Tipo</small>
                <span class="badge bg-<?= $persona['tipo_clave'] === 'SINDICALIZADO' ? 'primary' : 'warning text-dark' ?> fs-6 py-2 px-3">
                    <?= htmlspecialchars($persona['tipo_nombre']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Saldos por periodo -->
<h2 class="h4 fw-bold text-institucional mb-3">Saldos de vacaciones</h2>
<div class="row g-3 mb-4">
    <?php if (empty($saldos)): ?>
        <div class="col-12">
            <div class="alert alert-light border fs-5">Este empleado aún no tiene saldos registrados.</div>
        </div>
    <?php endif; ?>
    <?php foreach ($saldos as $s): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h3 class="h5 fw-bold mb-0"><?= htmlspecialchars($s['periodo_nombre']) ?></h3>
                    <?php if (!empty($s['tiene_inconsistencia'])): ?>
                        <span class="badge bg-danger fs-6 py-2 px-3">Saldo incorrecto</span>
                    <?php else: ?>
                        <span class="badge bg-success fs-6 py-2 px-3">Sin inconsistencias</span>
                    <?php endif; ?>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block">Asignados</small>
                            <span class="h3 fw-bold mb-0 d-block"><?= (int)$s['dias_asignados'] ?></span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block">Usados</small>
                            <span class="h3 fw-bold mb-0 d-block text-info"><?= (int)$s['dias_usados'] ?></span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 <?= (int)$s['dias_restantes'] < 0 ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10' ?> rounded">
                            <small class="text-muted d-block">Restantes</small>
                            <span class="h3 fw-bold mb-0 d-block <?= (int)$s['dias_restantes'] < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= (int)$s['dias_restantes'] ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($s['observaciones'])): ?>
                <div class="alert alert-warning mt-3 mb-0 fs-6">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= htmlspecialchars($s['observaciones']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Historial de movimientos -->
<h2 class="h4 fw-bold text-institucional mb-3">Historial de movimientos</h2>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:1.05rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Periodo</th>
                    <th>Fecha mov.</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Regreso</th>
                    <th class="text-center">Corresp.</th>
                    <th class="text-center">Tomados</th>
                    <th>Folio TM</th>
                    <th>Estado</th>
                    <th class="pe-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movimientos)): ?>
                <tr><td colspan="10" class="text-center p-4 text-muted fs-5">Sin movimientos registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td class="ps-4"><?= htmlspecialchars($m['periodo_nombre']) ?></td>
                    <td><?= vac_fmtFecha($m['fecha_vacaciones']) ?></td>
                    <td><?= vac_fmtFecha($m['fecha_inicio']) ?></td>
                    <td><?= vac_fmtFecha($m['fecha_fin']) ?></td>
                    <td><?= vac_fmtFecha($m['fecha_regreso']) ?></td>
                    <td class="text-center"><?= (int)$m['dias_corresponden'] ?></td>
                    <td class="text-center"><?= (int)$m['dias_tomados'] ?></td>
                    <td><code class="fs-6"><?= htmlspecialchars($m['folio_tm_vacaciones'] ?? '—') ?></code></td>
                    <td>
                        <?php
                        $clave = $m['estatus_clave'];
                        $texto = $m['estatus_nombre'];
                        $color = $m['estatus_color'] ?: 'secondary';
                        ?>
                        <span class="badge bg-<?= htmlspecialchars($color) ?> fs-6 py-2 px-3">
                            <?= htmlspecialchars($texto) ?>
                        </span>
                    </td>
                    <td class="pe-4 text-center">
                        <?php if (!empty($puedeAdmin) && $clave !== 'CANCELADA'): ?>
                        <div class="btn-group">
                            <a href="/vacaciones/mov/<?= (int)$m['id'] ?>/editar"
                               class="btn btn-outline-primary btn-lg" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST"
                                  action="/vacaciones/mov/<?= (int)$m['id'] ?>/cancelar"
                                  class="d-inline"
                                  data-confirm="¿Cancelar este movimiento? El saldo se recalculará."
                                  data-confirm-title="Cancelar movimiento"
                                  data-confirm-btn="Sí, cancelar"
                                  data-confirm-cancel="No">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-lg" title="Cancelar">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($m['observaciones'])): ?>
                <tr>
                    <td colspan="10" class="ps-4 bg-light border-0 small text-muted">
                        <i class="fa-solid fa-comment-dots me-1"></i>
                        <?= htmlspecialchars($m['observaciones']) ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
