<?php $pageTitle = 'Incidencias'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>Incidencias
    </h1>
    <div class="d-flex gap-2">
        <a href="/incidencias?pendientes=1" class="btn btn-outline-warning btn-lg">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            Pendientes de revisión (<?= (int)($resumen['pendientes'] ?? 0) ?>)
        </a>
        <a href="/incidencias/crear" class="btn btn-institucional btn-lg">
            <i class="fa-solid fa-plus me-2"></i>Registrar incidencia
        </a>
    </div>
</div>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Total en sistema</p>
                <p class="h3 fw-bold text-institucional mb-0"><?= (int)($resumen['total'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Mes actual</p>
                <p class="h3 fw-bold text-primary mb-0"><?= (int)($resumen['mes_actual'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Registradas</p>
                <p class="h3 fw-bold text-info mb-0"><?= (int)($resumen['registradas'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Justificadas</p>
                <p class="h3 fw-bold text-success mb-0"><?= (int)($resumen['justificadas'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">No justificadas</p>
                <p class="h3 fw-bold text-danger mb-0"><?= (int)($resumen['no_justificadas'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Pendientes</p>
                <p class="h3 fw-bold text-warning mb-0"><?= (int)($resumen['pendientes'] ?? 0) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Resumen por tipo -->
<?php if (!empty($porTipo)): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom py-3 fw-semibold">
        <i class="fa-solid fa-chart-pie me-2"></i>Resumen por tipo
    </div>
    <div class="card-body">
        <div class="row g-2">
            <?php foreach ($porTipo as $t): ?>
                <?php if ((int)$t['total'] === 0) continue; ?>
                <div class="col-md-3 col-sm-6">
                    <a href="/incidencias?tipo_id=<?= (int)$t['tipo_id'] ?>"
                       class="text-decoration-none">
                        <div class="d-flex align-items-center gap-2 p-2 rounded bg-light">
                            <span class="badge bg-<?= htmlspecialchars($t['color']) ?> fs-6">
                                <?php if (!empty($t['icono'])): ?>
                                    <i class="fa-solid <?= htmlspecialchars($t['icono']) ?> me-1"></i>
                                <?php endif; ?>
                                <?= (int)$t['total'] ?>
                            </span>
                            <div>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($t['nombre']) ?></div>
                                <small class="text-muted">Mes actual: <?= (int)$t['mes_actual'] ?></small>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="/incidencias" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="f_q" class="form-label fw-semibold">Buscar empleado</label>
                <input type="text" id="f_q" name="q" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['q']) ?>"
                       placeholder="Nombre o número de empleado">
            </div>
            <div class="col-md-2">
                <label for="f_tp" class="form-label fw-semibold">Tipo personal</label>
                <select id="f_tp" name="tipo_personal" class="form-select form-select-lg">
                    <option value="">Todos</option>
                    <option value="SINDICALIZADO" <?= $filtros['tipo_personal'] === 'SINDICALIZADO' ? 'selected' : '' ?>>Sindicalizado</option>
                    <option value="CONFIANZA"     <?= $filtros['tipo_personal'] === 'CONFIANZA'     ? 'selected' : '' ?>>Confianza</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="f_tipo" class="form-label fw-semibold">Tipo incidencia</label>
                <select id="f_tipo" name="tipo_id" class="form-select form-select-lg">
                    <option value="">Todas</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (string)$filtros['tipo_id'] === (string)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="f_est" class="form-label fw-semibold">Estatus</label>
                <select id="f_est" name="estatus_id" class="form-select form-select-lg">
                    <option value="">Todos</option>
                    <?php foreach ($estatus as $e): ?>
                        <option value="<?= (int)$e['id'] ?>" <?= (string)$filtros['estatus_id'] === (string)$e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="f_fecha" class="form-label fw-semibold">Fecha exacta</label>
                <input type="date" id="f_fecha" name="fecha" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['fecha']) ?>">
            </div>

            <div class="col-md-2">
                <label for="f_desde" class="form-label fw-semibold">Desde</label>
                <input type="date" id="f_desde" name="desde" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['desde']) ?>">
            </div>
            <div class="col-md-2">
                <label for="f_hasta" class="form-label fw-semibold">Hasta</label>
                <input type="date" id="f_hasta" name="hasta" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['hasta']) ?>">
            </div>
            <div class="col-md-2">
                <label for="f_anio" class="form-label fw-semibold">Año</label>
                <select id="f_anio" name="anio" class="form-select form-select-lg">
                    <option value="">Todos</option>
                    <?php foreach ($anios as $a): ?>
                        <option value="<?= (int)$a ?>" <?= (string)$filtros['anio'] === (string)$a ? 'selected' : '' ?>><?= (int)$a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="f_mes" class="form-label fw-semibold">Mes</label>
                <select id="f_mes" name="mes" class="form-select form-select-lg">
                    <option value="">Todos</option>
                    <?php $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']; ?>
                    <?php for ($m=1; $m<=12; $m++): ?>
                        <option value="<?= $m ?>" <?= (string)$filtros['mes'] === (string)$m ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="f_quin" class="form-label fw-semibold">Quincena</label>
                <select id="f_quin" name="quincena" class="form-select form-select-lg">
                    <option value="">Ambas</option>
                    <option value="1" <?= (string)$filtros['quincena'] === '1' ? 'selected' : '' ?>>1ª (1-15)</option>
                    <option value="2" <?= (string)$filtros['quincena'] === '2' ? 'selected' : '' ?>>2ª (16-fin)</option>
                </select>
            </div>
            <div class="col-md-2 form-check mt-4">
                <input type="checkbox" class="form-check-input" id="f_pend" name="pendientes" value="1"
                       <?= !empty($filtros['pendientes']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="f_pend">Solo pendientes</label>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-institucional btn-lg">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Buscar
                </button>
                <a href="/incidencias" class="btn btn-outline-secondary btn-lg">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        Registros encontrados: <strong><?= (int)$total ?></strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-lg mb-0" aria-label="Incidencias">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Empleado</th>
                    <th>Tipo personal</th>
                    <th>Tipo incidencia</th>
                    <th>Fecha incidencia</th>
                    <th>Día a justificar</th>
                    <th>Estatus</th>
                    <th>Motivo</th>
                    <th class="pe-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incidencias)): ?>
                <tr><td colspan="8" class="text-center p-4 text-muted">No hay incidencias con estos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($incidencias as $i): ?>
                <tr>
                    <td class="ps-4">
                        <a href="/personal/<?= (int)$i['personal_id'] ?>" class="text-decoration-none">
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($i['nombre_completo']) ?></div>
                            <small class="text-muted">N° <?= htmlspecialchars($i['numero_empleado'] ?? '—') ?></small>
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-<?= $i['tipo_personal_clave'] === 'SINDICALIZADO' ? 'primary' : 'warning text-dark' ?>">
                            <?= htmlspecialchars($i['tipo_personal_nombre']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= htmlspecialchars($i['tipo_color']) ?>">
                            <?php if (!empty($i['tipo_icono'])): ?>
                                <i class="fa-solid <?= htmlspecialchars($i['tipo_icono']) ?> me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($i['tipo_nombre']) ?>
                        </span>
                    </td>
                    <td><?= $i['fecha_incidencia'] ? date('d/m/Y', strtotime($i['fecha_incidencia'])) : '—' ?></td>
                    <td><?= $i['fecha_inicio']     ? date('d/m/Y', strtotime($i['fecha_inicio']))     : '—' ?></td>
                    <td>
                        <span class="badge bg-<?= htmlspecialchars($i['estatus_color']) ?>">
                            <?= htmlspecialchars($i['estatus_nombre']) ?>
                        </span>
                    </td>
                    <td class="text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($i['motivo'] ?? '') ?>">
                        <?= htmlspecialchars(mb_strimwidth($i['motivo'] ?? '', 0, 80, '…')) ?>
                    </td>
                    <td class="pe-4 text-center">
                        <a href="/incidencias/<?= (int)$i['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <?php if (Auth::hasRole([ROL_GOD, ROL_ADMIN])): ?>
                        <a href="/incidencias/<?= (int)$i['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($paginas > 1): ?>
    <div class="card-footer bg-white py-3 d-flex justify-content-center">
        <nav>
            <ul class="pagination mb-0">
                <?php
                $qs = $_GET; unset($qs['pagina']);
                $base = '?' . http_build_query($qs);
                $prefix = $base === '?' ? '?' : $base . '&';
                $desde_p = max(1, $pagina - 3);
                $hasta_p = min($paginas, $pagina + 3);
                ?>
                <?php if ($desde_p > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($prefix . 'pagina=1') ?>">1</a></li>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <?php for ($i = $desde_p; $i <= $hasta_p; $i++): ?>
                    <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($prefix . 'pagina=' . $i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($hasta_p < $paginas): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                    <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($prefix . 'pagina=' . $paginas) ?>"><?= $paginas ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
