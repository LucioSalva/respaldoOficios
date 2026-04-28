<?php $pageTitle = 'Inconsistencias de vacaciones'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="<?= url_path('/vacaciones') ?>" class="btn btn-outline-secondary btn-lg">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver
    </a>
    <h1 class="h3 fw-bold text-danger mb-0">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        Inconsistencias detectadas
    </h1>
</div>

<div class="alert alert-warning fs-5">
    <i class="fa-solid fa-circle-info me-2"></i>
    Un saldo se marca como <strong>inconsistente</strong> cuando los dÃ­as pendientes reportados en el Excel
    difieren del cÃ¡lculo derivado (asignados âˆ’ usados).
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 fs-5">
        Total: <strong><?= count($lista) ?></strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:1.05rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">NÂ°</th>
                    <th>Empleado</th>
                    <th>Periodo</th>
                    <th class="text-center">Asignados</th>
                    <th class="text-center">Usados</th>
                    <th class="text-center">Restantes</th>
                    <th>ObservaciÃ³n</th>
                    <th class="pe-4 text-center">AcciÃ³n</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lista)): ?>
                <tr><td colspan="8" class="text-center p-4 text-success fs-5">
                    <i class="fa-solid fa-check-circle me-2"></i>No hay inconsistencias. Todos los saldos cuadran.
                </td></tr>
                <?php endif; ?>
                <?php foreach ($lista as $s): ?>
                <tr>
                    <td class="ps-4"><strong><?= htmlspecialchars($s['numero_empleado'] ?? 'â€”') ?></strong></td>
                    <td><?= htmlspecialchars($s['nombre_completo']) ?></td>
                    <td><?= htmlspecialchars($s['periodo_nombre']) ?></td>
                    <td class="text-center"><?= (int)$s['dias_asignados'] ?></td>
                    <td class="text-center"><?= (int)$s['dias_usados'] ?></td>
                    <td class="text-center fw-bold <?= (int)$s['dias_restantes'] < 0 ? 'text-danger' : '' ?>">
                        <?= (int)$s['dias_restantes'] ?>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($s['observaciones'] ?? 'â€”') ?></td>
                    <td class="pe-4 text-center">
                        <a href="<?= url_path('/vacaciones/empleado/'.(int)$s['personal_id']) ?>"
                           class="btn btn-outline-primary btn-lg">
                            <i class="fa-solid fa-eye me-1"></i>Revisar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

