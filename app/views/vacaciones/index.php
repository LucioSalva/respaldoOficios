<?php $pageTitle = 'Vacaciones'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-umbrella-beach me-2"></i>Vacaciones
    </h1>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($puedeAdmin)): ?>
        <a href="<?= url_path('/vacaciones/inconsistencias') ?>" class="btn btn-outline-danger btn-lg">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>Revisar inconsistencias
            <?php if (!empty($resumen['inconsistencias'])): ?>
                <span class="badge bg-danger ms-1"><?= (int)$resumen['inconsistencias'] ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="<?= url_path('/vacaciones/crear') ?>" class="btn btn-institucional btn-lg">
            <i class="fa-solid fa-plus me-2"></i>Registrar vacaciones
        </a>
    </div>
</div>

<!-- Resumen -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Empleados con saldo</p>
                <p class="h3 fw-bold text-institucional mb-0"><?= (int)($resumen['empleados_con_saldo'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Movimientos totales</p>
                <p class="h3 fw-bold text-info mb-0"><?= (int)($resumen['total_movimientos'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Pendientes de revisar</p>
                <p class="h3 fw-bold text-warning mb-0"><?= (int)($resumen['pendientes_revision'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Inconsistencias</p>
                <p class="h3 fw-bold text-danger mb-0"><?= (int)($resumen['inconsistencias'] ?? 0) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= url_path('/vacaciones') ?>" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="f_q" class="form-label fw-semibold fs-6">Buscar empleado</label>
                <input type="text" id="f_q" name="q" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['q']) ?>"
                       placeholder="Nombre o nÃºmero de empleado">
            </div>
            <div class="col-md-2">
                <label for="f_num" class="form-label fw-semibold fs-6">NÃºmero</label>
                <input type="text" id="f_num" name="num" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['num']) ?>"
                       placeholder="Ej. 92048">
            </div>
            <div class="col-md-2">
                <label for="f_periodo" class="form-label fw-semibold fs-6">Periodo con dÃ­as</label>
                <select id="f_periodo" name="periodo" class="form-select form-select-lg">
                    <option value="">Cualquiera</option>
                    <option value="1" <?= $filtros['periodo'] === '1' ? 'selected' : '' ?>>1er periodo 2025</option>
                    <option value="2" <?= $filtros['periodo'] === '2' ? 'selected' : '' ?>>2do periodo 2025</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="f_orden" class="form-label fw-semibold fs-6">Ordenar por</label>
                <select id="f_orden" name="orden" class="form-select form-select-lg">
                    <option value="nombre"       <?= $filtros['orden'] === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                    <option value="num"          <?= $filtros['orden'] === 'num' ? 'selected' : '' ?>>NÃºmero de empleado</option>
                    <option value="p1_restantes" <?= $filtros['orden'] === 'p1_restantes' ? 'selected' : '' ?>>Restantes 1er periodo</option>
                    <option value="p2_restantes" <?= $filtros['orden'] === 'p2_restantes' ? 'selected' : '' ?>>Restantes 2do periodo</option>
                    <option value="incons"       <?= $filtros['orden'] === 'incons' ? 'selected' : '' ?>>Con inconsistencias</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" id="f_bajos"
                           name="bajos" value="1" <?= $filtros['bajos'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="f_bajos">Pocos dÃ­as (â‰¤ 2)</label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" id="f_inc"
                           name="inconsistencias" value="1" <?= $filtros['inconsistencias'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="f_inc">Solo con inconsistencias</label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-institucional btn-lg">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Buscar
                </button>
                <a href="<?= url_path('/vacaciones') ?>" class="btn btn-outline-secondary btn-lg">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla pivote -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 fs-5">
        Empleados con saldo: <strong><?= (int)$total ?></strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:1.05rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">NÂ° empleado</th>
                    <th>Nombre</th>
                    <th class="text-center" title="1er periodo 2025">1er<br><small class="fw-normal">Asig</small></th>
                    <th class="text-center"><small class="fw-normal">Usados</small></th>
                    <th class="text-center"><small class="fw-normal">Restan</small></th>
                    <th class="text-center" title="2do periodo 2025">2do<br><small class="fw-normal">Asig</small></th>
                    <th class="text-center"><small class="fw-normal">Usados</small></th>
                    <th class="text-center"><small class="fw-normal">Restan</small></th>
                    <th class="text-center">Estado</th>
                    <th class="pe-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="text-center p-4 text-muted fs-5">No hay empleados con estos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="ps-4"><strong><?= htmlspecialchars($r['numero_empleado'] ?? 'â€”') ?></strong></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['nombre_completo']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($r['tipo_personal_nombre']) ?></small>
                    </td>
                    <td class="text-center"><?= (int)$r['p1_asignados'] ?></td>
                    <td class="text-center"><?= (int)$r['p1_usados'] ?></td>
                    <td class="text-center <?= ((int)$r['p1_asignados'] > 0 && (int)$r['p1_restantes'] <= 0) ? 'text-danger fw-bold' : 'fw-bold' ?>">
                        <?= (int)$r['p1_restantes'] ?>
                    </td>
                    <td class="text-center"><?= (int)$r['p2_asignados'] ?></td>
                    <td class="text-center"><?= (int)$r['p2_usados'] ?></td>
                    <td class="text-center <?= ((int)$r['p2_asignados'] > 0 && (int)$r['p2_restantes'] <= 0) ? 'text-danger fw-bold' : 'fw-bold' ?>">
                        <?= (int)$r['p2_restantes'] ?>
                    </td>
                    <td class="text-center">
                        <?php if (!empty($r['tiene_inconsistencia_global'])): ?>
                            <span class="badge bg-danger fs-6 py-2 px-3">Saldo incorrecto</span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6 py-2 px-3">Sin inconsistencias</span>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4 text-center">
                        <a href="<?= url_path('/vacaciones/empleado/'.(int)$r['personal_id']) ?>"
                           class="btn btn-outline-secondary btn-lg" title="Ver detalle">
                            <i class="fa-solid fa-eye me-1"></i>Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($paginas > 1): ?>
    <div class="card-footer bg-white py-3 d-flex justify-content-center">
        <nav>
            <ul class="pagination pagination-lg mb-0">
                <?php
                $qs = $_GET; unset($qs['pagina']);
                $base = '?' . http_build_query($qs);
                $prefix = $base === '?' ? '?' : $base . '&';
                for ($i = 1; $i <= $paginas; $i++): ?>
                    <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($prefix . 'pagina=' . $i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

