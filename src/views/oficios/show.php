<?php
$tipo_clave      = strtoupper($oficio['tipo_oficio_clave'] ?? 'EXTERNO');
$es_interno      = ($tipo_clave === 'INTERNO');
$es_conocimiento = ($tipo_clave === 'CONOCIMIENTO');

// Flags: vienen del controlador. Fallback tolerante si la vista se invoca sin ellos.
$tipo_flags = $tipo_flags ?? [
    'clave'                    => $tipo_clave,
    'requiere_respuesta'       => !$es_conocimiento,
    'requiere_pdf_contestado'  => !$es_conocimiento && !$es_interno,
];
$flag_requiere_respuesta = !empty($tipo_flags['requiere_respuesta']);

$folio_display = $oficio['folio_display']
    ?? ($es_interno ? ($oficio['folio_interno_texto'] ?: $oficio['folio_tesoreria']) : $oficio['folio_tesoreria']);

$pageTitle = 'Oficio ' . htmlspecialchars($folio_display);
$mov_errors = $_SESSION['mov_errors'] ?? [];
$mov_old    = $_SESSION['mov_old']    ?? [];
unset($_SESSION['mov_errors'], $_SESSION['mov_old']);
?>

<!-- Encabezado con folio prominente -->
<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/oficios">Oficios</a></li>
                <li class="breadcrumb-item active">Detalle</li>
            </ol>
        </nav>
        <h1 class="h2 fw-bold text-institucional mb-1">
            <i class="fa-solid fa-file-lines me-2" aria-hidden="true"></i>
            <?= htmlspecialchars($folio_display) ?>
        </h1>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php if ($es_conocimiento): ?>
            <span class="badge" style="background:#495057;color:#fff;font-size:1rem;padding:.5rem .9rem;">
                <i class="fa-solid fa-eye me-1"></i>CONOCIMIENTO
            </span>
            <?php elseif ($es_interno): ?>
            <span class="badge" style="background:var(--dorado-oscuro);color:#fff;font-size:1rem;padding:.5rem .9rem;">
                <i class="fa-solid fa-house-chimney me-1"></i>INTERNO
            </span>
            <?php else: ?>
            <span class="badge bg-institucional" style="font-size:1rem;padding:.5rem .9rem;">
                <i class="fa-solid fa-building-columns me-1"></i>EXTERNO
            </span>
            <?php endif; ?>
            <span class="badge bg-<?= htmlspecialchars($oficio['estado_color']) ?> badge-estado-grande">
                <?= htmlspecialchars($oficio['estado_nombre']) ?>
            </span>
            <span class="text-muted">
                Registrado: <?= $oficio['created_at'] ? date('d/m/Y H:i', strtotime($oficio['created_at'])) : '—' ?>
            </span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/oficios/<?= $oficio['id'] ?>/editar" class="btn btn-outline-secondary btn-lg">
            <i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Editar
        </a>
        <a href="/oficios" class="btn btn-outline-primary btn-lg">
            <i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Regresar
        </a>
    </div>
</div>

<?php if ($es_conocimiento): ?>
<div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center gap-3" style="border-left:6px solid #198754 !important;">
    <i class="fa-solid fa-circle-check fa-2x text-success"></i>
    <div>
        <div class="fw-bold fs-5 text-success-emphasis">No requiere contestación</div>
        <div class="text-muted">
            Este oficio se registró únicamente para <strong>conocimiento</strong>.
            No requiere respuesta ni oficio contestado.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- COLUMNA PRINCIPAL -->
    <div class="col-12 col-xl-8">

        <!-- DATOS PRINCIPALES -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h5 fw-bold mb-0">
                    <i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i>Datos del Oficio
                </h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <p class="text-muted small mb-1">
                            <?= $es_interno ? 'Folio Oficio TICS (interno)' : 'Folio Tesorería' ?>
                        </p>
                        <p class="fw-bold fs-5 text-institucional mb-0">
                            <?php if ($es_interno): ?>
                                <?= htmlspecialchars($oficio['folio_interno_texto'] ?: '—') ?>
                            <?php else: ?>
                                <?= htmlspecialchars($oficio['folio_tesoreria']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-12 col-md-6">
                        <?php if ($es_interno): ?>
                            <p class="text-muted small mb-1">Área Interna</p>
                            <p class="fw-semibold mb-0">
                                <i class="fa-solid fa-house-chimney me-1" style="color:var(--dorado-oscuro)"></i>
                                <?= htmlspecialchars($oficio['area_interna_nombre'] ?? '—') ?>
                            </p>
                        <?php elseif ($es_conocimiento): ?>
                            <p class="text-muted small mb-1">Dependencia (opcional)</p>
                            <p class="fw-semibold mb-0">
                                <i class="fa-solid fa-building-columns me-1 text-muted"></i>
                                <?= htmlspecialchars($oficio['dependencia_nombre'] ?? 'Sin dependencia') ?>
                            </p>
                        <?php else: ?>
                            <p class="text-muted small mb-1">Dependencia</p>
                            <p class="fw-semibold mb-0">
                                <i class="fa-solid fa-building-columns me-1 text-institucional"></i>
                                <?= htmlspecialchars($oficio['dependencia_nombre'] ?? '—') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <p class="text-muted small mb-1">Asunto</p>
                        <p class="fs-5 mb-0"><?= htmlspecialchars($oficio['asunto']) ?></p>
                    </div>
                    <?php if ($oficio['descripcion']): ?>
                    <div class="col-12">
                        <p class="text-muted small mb-1">Descripción</p>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($oficio['descripcion'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($oficio['observaciones']): ?>
                    <div class="col-12">
                        <p class="text-muted small mb-1">Observaciones</p>
                        <p class="mb-0 bg-light p-3 rounded"><?= nl2br(htmlspecialchars($oficio['observaciones'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Folio Minutario</p>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($oficio['folio_minutario'] ?? '—') ?></p>
                    </div>
                    <div class="col-6 col-md-5">
                        <p class="text-muted small mb-1">Folio Dirección</p>
                        <p class="fw-semibold mb-0 text-truncate"><?= htmlspecialchars($oficio['folio_direccion'] ?? '—') ?></p>
                    </div>
                    <div class="col-6 col-md-4">
                        <p class="text-muted small mb-1">Realizó</p>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($oficio['realizo'] ?? '—') ?></p>
                    </div>
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Capturó</p>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($oficio['capturo_nombre'] ?? '—') ?></p>
                    </div>
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Responsable</p>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($oficio['responsable_nombre'] ?? '—') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FECHAS -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h5 fw-bold mb-0">
                    <i class="fa-solid fa-calendar-days me-2" aria-hidden="true"></i>Fechas
                </h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Recepción</p>
                        <p class="fw-semibold mb-0">
                            <?= $oficio['fecha_recepcion'] ? date('d/m/Y', strtotime($oficio['fecha_recepcion'])) : '—' ?>
                        </p>
                    </div>
                    <?php if ($flag_requiere_respuesta): ?>
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Compromiso</p>
                        <?php if ($oficio['fecha_compromiso']): ?>
                        <?php $fc = new DateTime($oficio['fecha_compromiso']); $hoy = new DateTime(); ?>
                        <p class="fw-semibold mb-0 <?= $fc < $hoy && !$oficio['fecha_resolucion'] ? 'text-danger' : '' ?>">
                            <?= date('d/m/Y', strtotime($oficio['fecha_compromiso'])) ?>
                            <?php if ($fc < $hoy && !$oficio['fecha_resolucion']): ?>
                            <span class="badge bg-danger ms-1">Vencido</span>
                            <?php endif; ?>
                        </p>
                        <?php else: ?><p class="fw-semibold mb-0">—</p><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Oficio TICs</p>
                        <p class="fw-semibold mb-0">
                            <?= $oficio['fecha_oficio_tics'] ? date('d/m/Y', strtotime($oficio['fecha_oficio_tics'])) : '—' ?>
                        </p>
                    </div>
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Acuse Recibido</p>
                        <p class="fw-semibold mb-0">
                            <?= $oficio['fecha_acuse'] ? date('d/m/Y', strtotime($oficio['fecha_acuse'])) : '—' ?>
                        </p>
                    </div>
                    <?php if ($flag_requiere_respuesta && $oficio['fecha_resolucion']): ?>
                    <div class="col-6 col-md-3">
                        <p class="text-muted small mb-1">Resolución</p>
                        <p class="fw-semibold mb-0 text-success">
                            <?= date('d/m/Y', strtotime($oficio['fecha_resolucion'])) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- HISTORIAL DE MOVIMIENTOS -->
        <div class="card border-0 shadow-sm mb-4" id="movimientos">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h2 class="h5 fw-bold mb-0">
                    <i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>
                    Historial de Movimientos
                    <span class="badge bg-secondary ms-2"><?= count($movimientos) ?></span>
                </h2>
                <button class="btn btn-sm btn-institucional" type="button" data-bs-toggle="collapse"
                        data-bs-target="#formMovimiento" aria-expanded="false">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Agregar Movimiento
                </button>
            </div>

            <!-- Formulario de nuevo movimiento (colapsable) -->
            <div class="collapse <?= !empty($mov_errors) ? 'show' : '' ?>" id="formMovimiento">
                <div class="card-body border-bottom bg-light">
                    <h3 class="h6 fw-bold mb-3">Nuevo Movimiento</h3>
                    <?php if (!empty($mov_errors)): ?>
                    <div class="alert alert-danger py-2">
                        <?php foreach ($mov_errors as $err): ?>
                        <div><i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="/oficios/<?= $oficio['id'] ?>/movimiento" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="estado_nuevo_id" class="form-label fw-semibold">
                                    Nuevo Estado <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-lg" name="estado_nuevo_id" id="estado_nuevo_id" required>
                                    <option value="">-- Selecciona estado --</option>
                                    <?php foreach ($estados as $e): ?>
                                    <option value="<?= $e['id'] ?>"
                                        <?= $oficio['estado_nombre'] === $e['nombre'] ? 'selected' : '' ?>
                                        <?= ($mov_old['estado_nuevo_id'] ?? '') == $e['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-5">
                                <label for="mov_observacion" class="form-label fw-semibold">
                                    Observación <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" name="observacion"
                                       id="mov_observacion" required maxlength="1000"
                                       placeholder="Describe el movimiento realizado..."
                                       value="<?= htmlspecialchars($mov_old['observacion'] ?? '') ?>">
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="mov_fecha" class="form-label fw-semibold">Fecha</label>
                                <input type="datetime-local" class="form-control form-control-lg"
                                       name="fecha" id="mov_fecha"
                                       value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-institucional btn-lg px-4">
                                <i class="fa-solid fa-check me-2" aria-hidden="true"></i>Guardar Movimiento
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de movimientos -->
            <div class="card-body p-0">
                <?php if (empty($movimientos)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-clock-rotate-left display-6 d-block mb-2" aria-hidden="true"></i>
                    Sin movimientos registrados.
                </div>
                <?php else: ?>
                <div class="timeline-movimientos p-4">
                    <?php foreach ($movimientos as $mov): ?>
                    <div class="timeline-item mb-4">
                        <div class="d-flex align-items-start gap-3">
                            <div class="timeline-dot bg-<?= htmlspecialchars($mov['estado_color'] ?? 'secondary') ?>"></div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <?php if ($mov['estado_anterior']): ?>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($mov['estado_anterior']) ?>
                                    </span>
                                    <i class="fa-solid fa-arrow-right text-muted" aria-hidden="true"></i>
                                    <?php endif; ?>
                                    <span class="badge bg-<?= htmlspecialchars($mov['estado_color'] ?? 'secondary') ?>">
                                        <?= htmlspecialchars($mov['estado_nuevo']) ?>
                                    </span>
                                </div>
                                <p class="mb-1 fs-5"><?= htmlspecialchars($mov['observacion'] ?? '') ?></p>
                                <small class="text-muted">
                                    <i class="fa-solid fa-user me-1" aria-hidden="true"></i>
                                    <?= htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema') ?>
                                    &mdash;
                                    <i class="fa-solid fa-clock me-1" aria-hidden="true"></i>
                                    <?= $mov['fecha'] ? date('d/m/Y H:i', strtotime($mov['fecha'])) : '—' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COLUMNA LATERAL -->
    <div class="col-12 col-xl-4">

        <!-- EVIDENCIAS PDF -->
        <div class="card border-0 shadow-sm mb-4" id="evidencias">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h2 class="h5 fw-bold mb-0">
                    <i class="fa-solid fa-file-pdf me-2 text-danger" aria-hidden="true"></i>
                    Evidencias PDF
                    <span class="badge bg-secondary ms-2"><?= count($evidencias) ?></span>
                </h2>
                <button class="btn btn-sm btn-outline-danger" type="button"
                        data-bs-toggle="collapse" data-bs-target="#formEvidencia" aria-expanded="false">
                    <i class="fa-solid fa-upload me-1" aria-hidden="true"></i>Subir PDF
                </button>
            </div>

            <!-- Formulario upload -->
            <div class="collapse" id="formEvidencia">
                <div class="card-body border-bottom bg-light">
                    <form method="POST" action="/oficios/<?= $oficio['id'] ?>/evidencia"
                          enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                        <div class="mb-3">
                            <label for="tipo_evidencia_id" class="form-label fw-semibold">Tipo de Documento</label>
                            <select class="form-select" name="tipo_evidencia_id" id="tipo_evidencia_id">
                                <option value="">-- Selecciona tipo --</option>
                                <?php foreach ($tipos_evidencia as $te): ?>
                                <option value="<?= $te['id'] ?>"><?= htmlspecialchars($te['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="pdf_file" class="form-label fw-semibold">
                                Archivo PDF <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control" id="pdf_file" name="pdf"
                                   accept=".pdf,application/pdf" required>
                            <small class="text-muted">Máximo 10 MB. Solo archivos PDF.</small>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fa-solid fa-cloud-arrow-up me-2" aria-hidden="true"></i>Subir Archivo
                        </button>
                    </form>
                </div>
            </div>

            <!-- Lista de evidencias -->
            <div class="card-body p-0">
                <?php if (empty($evidencias)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-file-circle-xmark display-6 d-block mb-2" aria-hidden="true"></i>
                    Sin archivos adjuntos.
                </div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($evidencias as $ev): ?>
                    <li class="list-group-item py-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-file-pdf text-danger fs-4 mt-1" aria-hidden="true"></i>
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="mb-0 fw-semibold text-truncate" title="<?= htmlspecialchars($ev['nombre_original']) ?>">
                                    <?= htmlspecialchars($ev['nombre_original']) ?>
                                </p>
                                <small class="text-muted">
                                    <?= htmlspecialchars($ev['tipo_nombre'] ?? 'Sin tipo') ?>
                                    &bull; <?= $ev['tamano_bytes'] ? round($ev['tamano_bytes']/1024, 1) . ' KB' : '' ?>
                                </small>
                                <br>
                                <small class="text-muted">
                                    <?= $ev['created_at'] ? date('d/m/Y H:i', strtotime($ev['created_at'])) : '' ?>
                                    por <?= htmlspecialchars($ev['usuario_nombre'] ?? '—') ?>
                                </small>
                            </div>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <a href="/evidencia/<?= $ev['id'] ?>/ver" target="_blank"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Ver
                            </a>
                            <a href="/evidencia/<?= $ev['id'] ?>/descargar"
                               class="btn btn-sm btn-outline-danger">
                                <i class="fa-solid fa-download me-1" aria-hidden="true"></i>Descargar
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- RESUMEN RÁPIDO -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0 text-muted">
                    <i class="fa-solid fa-chart-bar me-2" aria-hidden="true"></i>Resumen
                </h2>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Movimientos</span>
                    <span class="fw-bold"><?= count($movimientos) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Evidencias PDF</span>
                    <span class="fw-bold"><?= count($evidencias) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Estado actual</span>
                    <span class="badge bg-<?= htmlspecialchars($oficio['estado_color']) ?>">
                        <?= htmlspecialchars($oficio['estado_nombre']) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Última actualización</span>
                    <small class="text-muted"><?= $oficio['updated_at'] ? date('d/m/Y', strtotime($oficio['updated_at'])) : '—' ?></small>
                </div>
            </div>
        </div>
    </div>
</div>
