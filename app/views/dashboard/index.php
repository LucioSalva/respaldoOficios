<?php $pageTitle = 'Dashboard'; ?>

<!-- Encabezado -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h2 fw-bold text-institucional mb-1">
            <i class="fa-solid fa-gauge-high me-2" aria-hidden="true"></i>Dashboard
        </h1>
        <p class="text-muted mb-0">Bienvenido, <?= htmlspecialchars(Auth::userName()) ?></p>
    </div>
    <div class="text-muted small">
        <i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i>
        <?= date('d \d\e F \d\e Y') ?>
    </div>
</div>

<!-- TARJETAS DE RESUMEN -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card card-stat border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body text-center py-4">
                <div class="stat-icon text-primary mb-2">
                    <i class="fa-solid fa-folder" aria-hidden="true"></i>
                </div>
                <div class="stat-number text-primary"><?= number_format($resumen['total_oficios'] ?? 0) ?></div>
                <div class="stat-label">Total Oficios</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card card-stat border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body text-center py-4">
                <div class="stat-icon text-primary mb-2">
                    <i class="fa-solid fa-envelope-open" aria-hidden="true"></i>
                </div>
                <div class="stat-number text-primary"><?= number_format($resumen['recibidos'] ?? 0) ?></div>
                <div class="stat-label">Recibidos</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card card-stat border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body text-center py-4">
                <div class="stat-icon text-warning mb-2">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                </div>
                <div class="stat-number text-warning"><?= number_format($resumen['en_proceso'] ?? 0) ?></div>
                <div class="stat-label">En Proceso</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card card-stat border-0 shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body text-center py-4">
                <div class="stat-icon text-info mb-2">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </div>
                <div class="stat-number text-info"><?= number_format($resumen['en_revision'] ?? 0) ?></div>
                <div class="stat-label">En Revisión</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card card-stat border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body text-center py-4">
                <div class="stat-icon text-success mb-2">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </div>
                <div class="stat-number text-success"><?= number_format($resumen['contestados'] ?? 0) ?></div>
                <div class="stat-label">Contestados</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card card-stat border-0 shadow-sm h-100 border-start border-4 border-dark">
            <div class="card-body text-center py-4">
                <div class="stat-icon text-dark mb-2">
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                </div>
                <div class="stat-number text-dark"><?= number_format($resumen['archivados'] ?? 0) ?></div>
                <div class="stat-label">Archivados</div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($conocimiento)): ?>
<!-- TARJETA CONOCIMIENTO -->
<div class="card border-0 shadow-sm mb-4" style="border-left:6px solid #495057 !important;">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
        <i class="fa-solid fa-eye fa-3x" style="color:#495057"></i>
        <div class="flex-grow-1">
            <h2 class="h5 fw-bold mb-1" style="color:#495057">
                Oficios de Conocimiento
            </h2>
            <p class="text-muted mb-0">
                Recibidos solo para enterar. No requieren contestación.
            </p>
        </div>
        <div class="d-flex gap-4 flex-wrap">
            <div class="text-center">
                <div class="fs-3 fw-bold" style="color:#495057">
                    <?= number_format((int)($conocimiento['total'] ?? 0)) ?>
                </div>
                <div class="text-muted small">Total</div>
            </div>
            <div class="text-center">
                <div class="fs-3 fw-bold text-secondary">
                    <?= number_format((int)($conocimiento['de_conocimiento'] ?? 0)) ?>
                </div>
                <div class="text-muted small">De Conocimiento</div>
            </div>
            <div class="text-center">
                <div class="fs-3 fw-bold text-dark">
                    <?= number_format((int)($conocimiento['archivados'] ?? 0)) ?>
                </div>
                <div class="text-muted small">Archivados</div>
            </div>
        </div>
        <a href="<?= url_path('/oficios?tipo=CONOCIMIENTO') ?>" class="btn btn-outline-dark btn-lg">
            <i class="fa-solid fa-list me-2"></i>Ver listado
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ACCESOS DIRECTOS -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <h2 class="h5 fw-bold text-muted mb-3">
            <i class="fa-solid fa-bolt me-2" aria-hidden="true"></i>Acciones Rápidas
        </h2>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <a href="<?= url_path('/oficios/crear') ?>" class="btn btn-institucional btn-accion-rapida w-100 py-4">
            <i class="fa-solid fa-circle-plus display-6 d-block mb-2" aria-hidden="true"></i>
            <span class="fw-bold fs-5">Nuevo Oficio</span>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <a href="<?= url_path('/oficios') ?>" class="btn btn-outline-institucional btn-accion-rapida w-100 py-4">
            <i class="fa-solid fa-magnifying-glass display-6 d-block mb-2" aria-hidden="true"></i>
            <span class="fw-bold fs-5">Buscar Oficios</span>
        </a>
    </div>
    <?php if (Auth::hasRole([ROL_GOD, ROL_ADMIN])): ?>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <a href="<?= url_path('/usuarios') ?>" class="btn btn-outline-secondary btn-accion-rapida w-100 py-4">
            <i class="fa-solid fa-users display-6 d-block mb-2" aria-hidden="true"></i>
            <span class="fw-bold fs-5">Gestionar Usuarios</span>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <a href="<?= url_path('/catalogos') ?>" class="btn btn-outline-secondary btn-accion-rapida w-100 py-4">
            <i class="fa-solid fa-table-list display-6 d-block mb-2" aria-hidden="true"></i>
            <span class="fw-bold fs-5">Catálogos</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ÚLTIMOS OFICIOS -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
        <h2 class="h5 fw-bold mb-0">
            <i class="fa-solid fa-clock-rotate-left me-2" aria-hidden="true"></i>
            Últimos Oficios Registrados
        </h2>
        <a href="<?= url_path('/oficios') ?>" class="btn btn-sm btn-outline-institucional">
            Ver todos <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($ultimos_oficios)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fa-solid fa-inbox display-4 d-block mb-3" aria-hidden="true"></i>
            <p class="fs-5">No hay oficios registrados todavía.</p>
            <a href="<?= url_path('/oficios/crear') ?>" class="btn btn-institucional">Registrar primer oficio</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-lg mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Folio Tesorería</th>
                        <th scope="col">Dependencia</th>
                        <th scope="col">Asunto</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Fecha Recepción</th>
                        <th scope="col" class="pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ultimos_oficios as $oficio): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="folio-badge fw-bold">
                                <?= htmlspecialchars($oficio['folio_tesoreria']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($oficio['dependencia'] ?? '—') ?></td>
                        <td class="text-truncate" style="max-width:220px;" title="<?= htmlspecialchars($oficio['asunto']) ?>">
                            <?= htmlspecialchars($oficio['asunto']) ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= htmlspecialchars($oficio['estado_color']) ?> badge-estado">
                                <?= htmlspecialchars($oficio['estado']) ?>
                            </span>
                        </td>
                        <td><?= $oficio['fecha_recepcion'] ? date('d/m/Y', strtotime($oficio['fecha_recepcion'])) : '—' ?></td>
                        <td class="pe-4">
                            <a href="<?= url_path('/oficios/' . $oficio['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Ver
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($incidenciasMini)): ?>
<!-- MINI-RESUMEN INCIDENCIAS -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h2 class="h4 mb-0">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>Incidencias
        </h2>
        <a href="<?= url_path('/incidencias') ?>" class="btn btn-outline-institucional">Ver todas</a>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-2">
                <small class="text-muted d-block">Total</small>
                <strong class="h4 mb-0 text-institucional"><?= (int)($incidenciasMini['total'] ?? 0) ?></strong>
            </div>
            <div class="col-6 col-md-2">
                <small class="text-muted d-block">Este mes</small>
                <strong class="h4 mb-0 text-primary"><?= (int)($incidenciasMini['mes_actual'] ?? 0) ?></strong>
            </div>
            <div class="col-6 col-md-2">
                <small class="text-muted d-block">Registradas</small>
                <strong class="h4 mb-0 text-info"><?= (int)($incidenciasMini['registradas'] ?? 0) ?></strong>
            </div>
            <div class="col-6 col-md-2">
                <small class="text-muted d-block">Justificadas</small>
                <strong class="h4 mb-0 text-success"><?= (int)($incidenciasMini['justificadas'] ?? 0) ?></strong>
            </div>
            <div class="col-6 col-md-2">
                <small class="text-muted d-block">No justificadas</small>
                <strong class="h4 mb-0 text-danger"><?= (int)($incidenciasMini['no_justificadas'] ?? 0) ?></strong>
            </div>
            <div class="col-6 col-md-2">
                <small class="text-muted d-block">Pendientes</small>
                <strong class="h4 mb-0 text-warning"><?= (int)($incidenciasMini['pendientes'] ?? 0) ?></strong>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
