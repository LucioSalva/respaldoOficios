<?php
$pageTitle  = 'Editar Oficio';
$errors     = $errors ?? [];
$old_vals   = $old    ?? $oficio;
$tipo_clave = strtoupper($oficio['tipo_oficio_clave'] ?? 'EXTERNO');
$es_interno = ($tipo_clave === 'INTERNO');
$es_conocimiento = ($tipo_clave === 'CONOCIMIENTO');

// Flags desde el backend
$tipo_flags = $tipo_flags ?? [
    'clave'                    => $tipo_clave,
    'requiere_respuesta'       => !$es_conocimiento,
    'requiere_pdf_contestado'  => !$es_conocimiento && !$es_interno,
];
$flag_requiere_respuesta = !empty($tipo_flags['requiere_respuesta']);
$flag_requiere_pdf_contestado = !empty($tipo_flags['requiere_pdf_contestado']);
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h2 fw-bold text-institucional mb-1">
            <i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Editar Oficio
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url_path('/oficios') ?>">Oficios</a></li>
                <?php
                    $__folio_bread = $es_interno
                        ? ($oficio['folio_interno_texto'] ?: ($oficio['folio_direccion'] ?: ('#'.$oficio['id'])))
                        : $oficio['folio_tesoreria'];
                ?>
                <li class="breadcrumb-item">
                    <a href="<?= url_path('/oficios/'.$oficio['id']) ?>"><?= htmlspecialchars($__folio_bread) ?></a>
                </li>
                <li class="breadcrumb-item active">Editar</li>
            </ol>
        </nav>
    </div>
</div>

<form method="POST" action="<?= url_path('/oficios/'.$oficio['id'].'/editar') ?>"
      enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

    <!-- Tarjeta indicadora del tipo (no editable) -->
    <?php
        if ($es_conocimiento) {
            $__bg = '#f1f3f5'; $__border = '#495057'; $__icon = 'fa-eye'; $__label = 'CONOCIMIENTO';
        } elseif ($es_interno) {
            $__bg = 'var(--dorado-suave)'; $__border = 'var(--dorado-oscuro)'; $__icon = 'fa-house-chimney'; $__label = 'INTERNO';
        } else {
            $__bg = 'var(--guinda-suave)'; $__border = 'var(--guinda)'; $__icon = 'fa-building-columns'; $__label = 'EXTERNO';
        }
    ?>
    <div class="alert d-flex align-items-center gap-3 mb-4 border-0 shadow-sm"
         style="background:<?= $__bg ?>;border-left:6px solid <?= $__border ?> !important;">
        <i class="fa-solid <?= $__icon ?> fa-2x" style="color:<?= $__border ?>"></i>
        <div>
            <div class="fw-bold fs-5">Tipo de Oficio: <?= $__label ?></div>
            <small class="text-muted">
                <i class="fa-solid fa-lock me-1"></i>
                El tipo de oficio no puede modificarse una vez registrado.
            </small>
            <?php if ($es_conocimiento): ?>
            <div class="mt-1 small text-info-emphasis">
                <i class="fa-solid fa-circle-info me-1"></i>
                <strong>Este oficio no requiere contestaciÃ³n.</strong>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-institucional text-white py-3">
            <h2 class="h5 fw-bold mb-0">
                <i class="fa-solid fa-file-lines me-2"></i>Datos del Oficio
            </h2>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">

                <!-- 1. ID -->
                <div class="col-12 col-md-2">
                    <label class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-hashtag"></i> ID
                    </label>
                    <div class="form-control form-control-lg bg-light text-muted text-center fw-bold"
                         style="border:2px dashed var(--borde);border-radius:var(--radius);min-height:58px;display:flex;align-items:center;justify-content:center;">
                        #<?= $oficio['id'] ?>
                    </div>
                </div>

                <!-- 2. FECHA DE RECIBIDO -->
                <div class="col-12 col-md-4">
                    <label for="fecha_recepcion" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-calendar-days"></i> Fecha de Recibido <span class="text-danger">*</span>
                    </label>
                    <input type="date"
                           class="form-control form-control-lg <?= isset($errors['fecha_recepcion']) ? 'is-invalid' : '' ?>"
                           id="fecha_recepcion" name="fecha_recepcion"
                           value="<?= htmlspecialchars($old_vals['fecha_recepcion'] ?? '') ?>" required>
                    <?php if (isset($errors['fecha_recepcion'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['fecha_recepcion']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- 3. FOLIO MINUTARIO -->
                <div class="col-12 col-md-3">
                    <label for="folio_minutario" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-file-contract"></i> Folio Minutario
                    </label>
                    <input type="text"
                           class="form-control form-control-lg"
                           id="folio_minutario" name="folio_minutario"
                           value="<?= htmlspecialchars($old_vals['folio_minutario'] ?? '') ?>"
                           placeholder="Ej: 001">
                </div>

                <!-- SPACER -->
                <div class="col-12 col-md-3 d-none d-md-block"></div>

                <?php if (!$es_interno): ?>
                <!-- 4. DEPENDENCIA (EXTERNO obligatorio / CONOCIMIENTO opcional) -->
                <div class="col-12 col-md-6">
                    <label for="dependencia_id" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-building-columns"></i>
                        Dependencia
                        <?php if ($es_conocimiento): ?>
                            <span class="text-muted small">(opcional)</span>
                        <?php else: ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </label>
                    <select class="form-select form-select-lg <?= isset($errors['dependencia_id']) ? 'is-invalid' : '' ?>"
                            id="dependencia_id" name="dependencia_id"
                            <?= $es_conocimiento ? '' : 'required' ?>>
                        <option value="">-- <?= $es_conocimiento ? 'Sin dependencia' : 'Selecciona' ?> --</option>
                        <?php foreach ($dependencias as $d): ?>
                        <option value="<?= $d['id'] ?>"
                            <?= ($old_vals['dependencia_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['dependencia_id'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['dependencia_id']) ?></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- 4. ÃREA INTERNA (INTERNO) -->
                <div class="col-12 col-md-6">
                    <label for="area_interna_id" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-house-chimney"></i> Ãrea Interna de TesorerÃ­a <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-lg <?= isset($errors['area_interna_id']) ? 'is-invalid' : '' ?>"
                            id="area_interna_id" name="area_interna_id" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach (($areas_internas ?? []) as $a): ?>
                        <option value="<?= $a['id'] ?>"
                            <?= ($old_vals['area_interna_id'] ?? '') == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['area_interna_id'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['area_interna_id']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- 5. FOLIO DE LA DIRECCIÃ“N -->
                <div class="col-12 col-md-6">
                    <label for="folio_direccion" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-folder"></i> Folio de la DirecciÃ³n
                    </label>
                    <input type="text"
                           class="form-control form-control-lg"
                           id="folio_direccion" name="folio_direccion"
                           value="<?= htmlspecialchars($old_vals['folio_direccion'] ?? '') ?>"
                           placeholder="Folio asignado por la direcciÃ³n emisora">
                </div>

                <?php if ($es_interno): ?>
                <!-- 5b. FOLIO INTERNO TEXTO -->
                <div class="col-12">
                    <label for="folio_interno_texto" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-pen-ruler"></i> Folio de Oficio TICS (texto libre)
                    </label>
                    <input type="text"
                           class="form-control form-control-lg"
                           id="folio_interno_texto" name="folio_interno_texto"
                           value="<?= htmlspecialchars($old_vals['folio_interno_texto'] ?? '') ?>"
                           placeholder="Ej: TM/ECA/STIyC/01/2026 o SDTICS/002/02/2026">
                    <small class="text-muted">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Los oficios internos aceptan cualquier formato de folio.
                    </small>
                </div>
                <?php endif; ?>

                <!-- 6. ASUNTO -->
                <div class="col-12">
                    <label for="asunto" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-envelope-open-text"></i> Asunto <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control form-control-lg <?= isset($errors['asunto']) ? 'is-invalid' : '' ?>"
                              id="asunto" name="asunto" rows="3" required><?= htmlspecialchars($old_vals['asunto'] ?? '') ?></textarea>
                    <?php if (isset($errors['asunto'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['asunto']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- 7. FECHA OFICIO TICs -->
                <div class="col-12 col-md-4">
                    <label for="fecha_oficio_tics" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-calendar-check"></i> Fecha Oficio TICs
                    </label>
                    <input type="date"
                           class="form-control form-control-lg"
                           id="fecha_oficio_tics" name="fecha_oficio_tics"
                           value="<?= htmlspecialchars($old_vals['fecha_oficio_tics'] ?? '') ?>">
                </div>

                <?php if (!$es_interno):
                    $__folio_pendiente = empty($oficio['numero_folio']);
                ?>
                <!-- 8. FOLIO TESORERÃA (EXTERNO/CONOCIMIENTO) -->
                <div class="col-12">
                    <div class="p-3 rounded-3"
                         style="background:<?= $__folio_pendiente ? 'var(--dorado-suave)' : '#f8f9fa' ?>;
                                border:3px <?= $__folio_pendiente ? 'dashed var(--dorado-oscuro)' : 'solid var(--borde)' ?>;">
                        <p class="fw-bold text-institucional mb-2 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-hashtag"></i> Folio de TesorerÃ­a
                            <?php if ($__folio_pendiente): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fa-solid fa-hourglass-half me-1"></i>PENDIENTE
                            </span>
                            <?php else: ?>
                            <span class="badge bg-success">
                                <i class="fa-solid fa-check me-1"></i>Asignado
                            </span>
                            <?php endif; ?>
                        </p>

                        <?php if ($__folio_pendiente): ?>
                        <div class="alert alert-warning border-0 mb-3 py-2 small">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Este oficio estÃ¡ <strong>pendiente de folio</strong>. Cuando la TesorerÃ­a te dÃ© el nÃºmero, captÃºralo aquÃ­.
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-2">
                                <label for="numero_folio" class="form-label fw-bold">NÃºmero</label>
                                <input type="number"
                                       class="form-control form-control-lg <?= isset($errors['numero_folio']) ? 'is-invalid' : '' ?>"
                                       id="numero_folio" name="numero_folio"
                                       value="<?= htmlspecialchars($old_vals['numero_folio'] ?? '') ?>"
                                       min="1" max="9999" placeholder="Ej: 495">
                                <?php if (isset($errors['numero_folio'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['numero_folio']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-md-2">
                                <label for="anio_folio" class="form-label fw-bold">AÃ±o</label>
                                <input type="number"
                                       class="form-control form-control-lg <?= isset($errors['anio_folio']) ? 'is-invalid' : '' ?>"
                                       id="anio_folio" name="anio_folio"
                                       value="<?= htmlspecialchars($old_vals['anio_folio'] ?? $oficio['anio_folio'] ?? date('Y')) ?>"
                                       min="2020" max="2099">
                                <?php if (isset($errors['anio_folio'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['anio_folio']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label fw-bold">Vista Previa</label>
                                <div class="folio-preview" id="folioPreview">
                                    <span id="folioTexto">TM/ECA/STIyC/ <em class="text-warning">PENDIENTE</em> /<?= htmlspecialchars($oficio['anio_folio'] ?? date('Y')) ?></span>
                                </div>
                                <small class="text-muted">
                                    <i class="fa-solid fa-circle-info me-1"></i>
                                    Escribe <strong>solo el nÃºmero</strong>. Si lo dejas vacÃ­o, el folio sigue pendiente.
                                </small>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="folio-preview folio-preview-activo">
                            <span><?= htmlspecialchars($oficio['folio_tesoreria']) ?></span>
                        </div>
                        <small class="text-muted">
                            <i class="fa-solid fa-lock me-1"></i>El folio ya fue asignado y no puede modificarse.
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 9. REALIZÃ“ -->
                <div class="col-12 col-md-6">
                    <label for="realizo" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-user-pen"></i> RealizÃ³
                    </label>
                    <input type="text"
                           class="form-control form-control-lg"
                           id="realizo" name="realizo"
                           value="<?= htmlspecialchars($old_vals['realizo'] ?? '') ?>"
                           placeholder="Nombre de quien realizÃ³ la gestiÃ³n">
                </div>

                <!-- 10. FECHA ACUSE -->
                <div class="col-12 col-md-6">
                    <label for="fecha_acuse" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-stamp"></i> Fecha Acuse Recibido por OficialÃ­a de TesorerÃ­a
                    </label>
                    <input type="date"
                           class="form-control form-control-lg"
                           id="fecha_acuse" name="fecha_acuse"
                           value="<?= htmlspecialchars($old_vals['fecha_acuse'] ?? '') ?>">
                </div>

                <!-- 11. STATUS -->
                <div class="col-12 col-md-6">
                    <label for="estado_id" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-flag"></i> Estatus <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-lg <?= isset($errors['estado_id']) ? 'is-invalid' : '' ?>"
                            id="estado_id" name="estado_id" required>
                        <option value="">-- Selecciona el estatus --</option>
                        <?php foreach ($estados as $e): ?>
                        <option value="<?= $e['id'] ?>"
                            <?= ($old_vals['estado_id'] ?? '') == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['estado_id'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['estado_id']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- 12. OBSERVACIONES -->
                <div class="col-12">
                    <label for="observaciones" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-comment-dots"></i> Observaciones
                    </label>
                    <textarea class="form-control form-control-lg"
                              id="observaciones" name="observaciones" rows="3"
                              placeholder="Notas, instrucciones o comentarios adicionales..."><?= htmlspecialchars($old_vals['observaciones'] ?? '') ?></textarea>
                </div>

            </div><!-- /row -->
        </div><!-- /card-body -->
    </div><!-- /card -->

    <!-- 13. EVIDENCIA: OFICIO RECIBIDO -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-institucional text-white py-3">
            <h2 class="h5 fw-bold mb-0">
                <i class="fa-solid fa-file-arrow-up me-2"></i>Evidencia â€” Oficio Recibido
            </h2>
        </div>
        <div class="card-body p-4">
            <?php
            $ev_recibido = null;
            foreach ($evidencias ?? [] as $ev) {
                if (stripos($ev['tipo_nombre'] ?? '', 'recibido') !== false) {
                    $ev_recibido = $ev; break;
                }
            }
            ?>
            <?php if ($ev_recibido): ?>
            <div class="alert d-flex align-items-center gap-3 mb-3"
                 style="background:var(--crema-suave);border:1px solid var(--dorado);border-radius:var(--radius);">
                <i class="fa-solid fa-file-pdf fa-2x" style="color:var(--guinda)"></i>
                <div class="flex-grow-1">
                    <strong><?= htmlspecialchars($ev_recibido['nombre_original']) ?></strong><br>
                    <small class="text-muted">
                        <?= number_format($ev_recibido['tamano_bytes'] / 1024, 1) ?> KB &mdash;
                        Subido: <?= htmlspecialchars(substr($ev_recibido['created_at'], 0, 10)) ?>
                    </small>
                </div>
                <span class="badge" style="background:var(--guinda);color:#fff;font-size:.8rem;">
                    <i class="fa-solid fa-circle-check me-1"></i>Disponible
                </span>
            </div>
            <p class="text-muted small mb-3">
                <i class="fa-solid fa-info-circle me-1"></i>
                Ya existe un PDF. Si subes uno nuevo, reemplazarÃ¡ al anterior.
            </p>
            <?php endif; ?>
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <label for="pdf_recibido" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-file-pdf" style="color:var(--guinda)"></i>
                        <?= $ev_recibido ? 'Reemplazar PDF del Oficio Recibido' : 'Subir PDF del Oficio Recibido' ?>
                        <span class="badge ms-1" style="background:var(--dorado);color:var(--texto-oscuro);font-size:.75rem;">Opcional</span>
                    </label>
                    <input type="file"
                           class="form-control form-control-lg"
                           id="pdf_recibido" name="pdf_recibido"
                           accept=".pdf,application/pdf">
                    <small class="text-muted" id="info_pdf_recibido">
                        <i class="fa-solid fa-circle-info me-1"></i>Solo archivos PDF. TamaÃ±o mÃ¡ximo: 10 MB.
                    </small>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded text-center" style="background:var(--guinda-suave);border:2px dashed var(--guinda);border-radius:var(--radius);">
                        <i class="fa-solid fa-inbox fa-2x mb-2" style="color:var(--guinda);opacity:.6"></i>
                        <p class="mb-0 small fw-bold text-institucional">Oficio Recibido</p>
                        <p class="mb-0 small text-muted">Documento recibido de la dependencia</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($flag_requiere_respuesta): ?>
    <!-- 14. EVIDENCIA: OFICIO CONTESTADO (solo si el tipo requiere respuesta) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header text-white py-3"
             style="background:linear-gradient(135deg,var(--dorado-claro),var(--dorado-oscuro));border-bottom:3px solid var(--guinda);">
            <h2 class="h5 fw-bold mb-0">
                <i class="fa-solid fa-file-circle-check me-2"></i>Evidencia â€” Oficio Contestado
            </h2>
        </div>
        <div class="card-body p-4">
            <?php
            $ev_contestado = null;
            foreach ($evidencias ?? [] as $ev) {
                if (stripos($ev['tipo_nombre'] ?? '', 'contestado') !== false || stripos($ev['tipo_nombre'] ?? '', 'respuesta') !== false) {
                    $ev_contestado = $ev; break;
                }
            }
            ?>
            <?php if ($ev_contestado): ?>
            <div class="alert d-flex align-items-center gap-3 mb-3"
                 style="background:var(--dorado-suave);border:1px solid var(--dorado);border-radius:var(--radius);">
                <i class="fa-solid fa-file-pdf fa-2x" style="color:var(--dorado-oscuro)"></i>
                <div class="flex-grow-1">
                    <strong><?= htmlspecialchars($ev_contestado['nombre_original']) ?></strong><br>
                    <small class="text-muted">
                        <?= number_format($ev_contestado['tamano_bytes'] / 1024, 1) ?> KB &mdash;
                        Subido: <?= htmlspecialchars(substr($ev_contestado['created_at'], 0, 10)) ?>
                    </small>
                </div>
                <span class="badge" style="background:var(--dorado-oscuro);color:#fff;font-size:.8rem;">
                    <i class="fa-solid fa-circle-check me-1"></i>Disponible
                </span>
            </div>
            <p class="text-muted small mb-3">
                <i class="fa-solid fa-info-circle me-1"></i>
                Ya existe un PDF. Si subes uno nuevo, reemplazarÃ¡ al anterior.
            </p>
            <?php endif; ?>
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <label for="pdf_contestado" class="form-label fw-bold fs-5">
                        <i class="fa-solid fa-file-pdf" style="color:var(--dorado-oscuro)"></i>
                        <?= $ev_contestado ? 'Reemplazar PDF del Oficio Contestado' : 'Subir PDF del Oficio Contestado' ?>
                        <span class="badge ms-1" style="background:var(--dorado);color:var(--texto-oscuro);font-size:.75rem;">Opcional</span>
                    </label>
                    <input type="file"
                           class="form-control form-control-lg"
                           id="pdf_contestado" name="pdf_contestado"
                           accept=".pdf,application/pdf">
                    <small class="text-muted" id="info_pdf_contestado">
                        <i class="fa-solid fa-circle-info me-1"></i>Solo archivos PDF. TamaÃ±o mÃ¡ximo: 10 MB.
                    </small>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded text-center" style="background:var(--dorado-suave);border:2px dashed var(--dorado);border-radius:var(--radius);">
                        <i class="fa-solid fa-envelope-open fa-2x mb-2" style="color:var(--dorado-oscuro);opacity:.8"></i>
                        <p class="mb-0 small fw-bold" style="color:var(--dorado-oscuro)">Oficio Contestado</p>
                        <p class="mb-0 small text-muted">Respuesta enviada a la dependencia</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; // $flag_requiere_respuesta ?>

    <!-- BOTONES -->
    <div class="d-flex gap-3 flex-wrap pb-4">
        <button type="submit" class="btn btn-institucional btn-lg px-5 py-3">
            <i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i><strong>Guardar Cambios</strong>
        </button>
        <a href="<?= url_path('/oficios/'.$oficio['id']) ?>" class="btn btn-outline-secondary btn-lg px-5 py-3">
            <i class="fa-solid fa-circle-xmark me-2" aria-hidden="true"></i>Cancelar
        </a>
    </div>

</form>

<script>
['pdf_recibido','pdf_contestado'].forEach(id => {
    const input = document.getElementById(id);
    if (!input) return;
    input.addEventListener('change', function () {
        const info = document.getElementById('info_' + id);
        if (info && this.files && this.files[0]) {
            const name = this.files[0].name;
            const size = (this.files[0].size / 1048576).toFixed(2);
            info.innerHTML = `<i class="fa-solid fa-circle-check me-1" style="color:green"></i><strong>${name}</strong> (${size} MB)`;
        }
    });
});

// Vista previa del folio pendiente (solo aparece si el oficio estÃ¡ PENDIENTE).
(function () {
    const numeroEl = document.getElementById('numero_folio');
    const anioEl   = document.getElementById('anio_folio');
    const el       = document.getElementById('folioTexto');
    const prev     = document.getElementById('folioPreview');
    if (!numeroEl || !anioEl || !el || !prev) return;

    function refresh() {
        const n = numeroEl.value;
        const a = anioEl.value;
        if (n && a) {
            const pad = String(n).padStart(4, '0');
            el.innerHTML = `TM/ECA/STIyC/${pad}/${a}`;
            prev.classList.add('folio-preview-activo');
        } else {
            el.innerHTML = `TM/ECA/STIyC/ <em class="text-warning">PENDIENTE</em> /${a || '????'}`;
            prev.classList.remove('folio-preview-activo');
        }
    }
    numeroEl.addEventListener('input', refresh);
    anioEl.addEventListener('input', refresh);
    refresh();
})();
</script>

