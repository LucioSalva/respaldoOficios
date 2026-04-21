<?php
$pageTitle   = 'Nuevo Oficio';
$errors      = $errors ?? [];
$old         = $old    ?? [];
$anio        = $old['anio_folio']   ?? $anio_actual;
$numero      = $old['numero_folio'] ?? '';
$tipo_actual = strtoupper($old['tipo_oficio'] ?? 'EXTERNO');

// Construir índice de flags por clave para que el JS tome la verdad del backend.
$tipos_oficio = $tipos_oficio ?? [];
$tipos_flags_map = [];
foreach ($tipos_oficio as $t) {
    $tipos_flags_map[$t['clave']] = [
        'requiere_respuesta'      => (bool)$t['requiere_respuesta'],
        'requiere_pdf_contestado' => (bool)$t['requiere_pdf_contestado'],
    ];
}
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h2 fw-bold text-institucional mb-1">
            <i class="fa-solid fa-circle-plus me-2"></i>Nuevo Oficio
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/oficios">Oficios</a></li>
                <li class="breadcrumb-item active">Nuevo</li>
            </ol>
        </nav>
    </div>
</div>

<form method="POST" action="/oficios/crear" enctype="multipart/form-data" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

<!-- ========== SELECTOR DE TIPO DE OFICIO ========== -->
<div class="card border-0 shadow-sm mb-4" id="cardTipoOficio">
    <div class="card-header bg-institucional text-white py-3">
        <h2 class="h5 fw-bold mb-0">
            <i class="fa-solid fa-circle-question me-2"></i>¿Qué tipo de oficio desea registrar?
        </h2>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="tipo-oficio-radio w-100 d-block p-4 rounded-3 text-center"
                       for="tipo_externo"
                       style="border:3px solid var(--borde);cursor:pointer;background:#fff;transition:all .15s ease;">
                    <input type="radio" name="tipo_oficio" value="EXTERNO" id="tipo_externo"
                           class="form-check-input d-none"
                           data-requiere-respuesta="<?= !empty($tipos_flags_map['EXTERNO']['requiere_respuesta']) ? '1' : '0' ?>"
                           data-requiere-pdf-contestado="<?= !empty($tipos_flags_map['EXTERNO']['requiere_pdf_contestado']) ? '1' : '0' ?>"
                           <?= $tipo_actual === 'EXTERNO' ? 'checked' : '' ?>>
                    <i class="fa-solid fa-building-columns fa-3x mb-3 text-institucional"></i>
                    <p class="h4 fw-bold mb-1 text-institucional">Oficio de otra dependencia</p>
                    <p class="mb-0 text-muted">
                        Recibido de otra <strong>dependencia externa</strong><br>
                        (H. Ayuntamiento, Obras Públicas, Secretaría, etc.)
                    </p>
                </label>
            </div>
            <div class="col-12 col-md-4">
                <label class="tipo-oficio-radio w-100 d-block p-4 rounded-3 text-center"
                       for="tipo_interno"
                       style="border:3px solid var(--borde);cursor:pointer;background:#fff;transition:all .15s ease;">
                    <input type="radio" name="tipo_oficio" value="INTERNO" id="tipo_interno"
                           class="form-check-input d-none"
                           data-requiere-respuesta="<?= !empty($tipos_flags_map['INTERNO']['requiere_respuesta']) ? '1' : '0' ?>"
                           data-requiere-pdf-contestado="<?= !empty($tipos_flags_map['INTERNO']['requiere_pdf_contestado']) ? '1' : '0' ?>"
                           <?= $tipo_actual === 'INTERNO' ? 'checked' : '' ?>>
                    <i class="fa-solid fa-house-chimney fa-3x mb-3" style="color:var(--dorado-oscuro)"></i>
                    <p class="h4 fw-bold mb-1" style="color:var(--dorado-oscuro)">Oficio interno de Tesorería</p>
                    <p class="mb-0 text-muted">
                        Generado por un <strong>área interna</strong> de Tesorería<br>
                        (Subdirección de Egresos, Jurídico, etc.)
                    </p>
                </label>
            </div>
            <div class="col-12 col-md-4">
                <label class="tipo-oficio-radio w-100 d-block p-4 rounded-3 text-center"
                       for="tipo_conocimiento"
                       style="border:3px solid var(--borde);cursor:pointer;background:#fff;transition:all .15s ease;">
                    <input type="radio" name="tipo_oficio" value="CONOCIMIENTO" id="tipo_conocimiento"
                           class="form-check-input d-none"
                           data-requiere-respuesta="<?= !empty($tipos_flags_map['CONOCIMIENTO']['requiere_respuesta']) ? '1' : '0' ?>"
                           data-requiere-pdf-contestado="<?= !empty($tipos_flags_map['CONOCIMIENTO']['requiere_pdf_contestado']) ? '1' : '0' ?>"
                           <?= $tipo_actual === 'CONOCIMIENTO' ? 'checked' : '' ?>>
                    <i class="fa-solid fa-eye fa-3x mb-3" style="color:#495057"></i>
                    <p class="h4 fw-bold mb-1" style="color:#495057">Oficio de conocimiento</p>
                    <p class="mb-0 text-muted">
                        Se recibe solo <strong>para enterar</strong>.<br>
                        No requiere contestación.
                    </p>
                </label>
            </div>
        </div>
        <!-- Banner dinámico para CONOCIMIENTO -->
        <div class="alert alert-info border-0 mt-3 mb-0 py-3 d-none" id="bannerConocimiento">
            <i class="fa-solid fa-circle-info me-2"></i>
            <strong>Este oficio se registra solo para conocimiento y no requiere contestación.</strong>
            Los campos de oficio contestado y fecha de compromiso no aplican para este tipo.
        </div>
    </div>
</div>
<style>
.tipo-oficio-radio:hover { border-color: var(--dorado) !important; }
.tipo-oficio-radio.seleccionado-externo       { border-color: var(--guinda) !important; background: var(--guinda-suave) !important; }
.tipo-oficio-radio.seleccionado-interno       { border-color: var(--dorado-oscuro) !important; background: var(--dorado-suave) !important; }
.tipo-oficio-radio.seleccionado-conocimiento  { border-color: #495057 !important; background: #f1f3f5 !important; }
</style>

<div class="card border-0 shadow-sm mb-4">

    <!-- HEADER CARD -->
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
                     style="border:2px dashed var(--borde); border-radius:var(--radius); min-height:58px; display:flex; align-items:center; justify-content:center;">
                    Auto
                </div>
                <small class="text-muted">Se asigna al guardar</small>
            </div>

            <!-- 2. FECHA DE RECIBIDO -->
            <div class="col-12 col-md-4">
                <label for="fecha_recepcion" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-calendar-days"></i> Fecha de Recibido <span class="text-danger">*</span>
                </label>
                <input type="date"
                       class="form-control form-control-lg <?= isset($errors['fecha_recepcion']) ? 'is-invalid' : '' ?>"
                       id="fecha_recepcion" name="fecha_recepcion"
                       value="<?= htmlspecialchars($old['fecha_recepcion'] ?? date('Y-m-d')) ?>"
                       required autofocus>
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
                       value="<?= htmlspecialchars($old['folio_minutario'] ?? '') ?>"
                       placeholder="Ej: 001">
            </div>

            <!-- SPACER -->
            <div class="col-12 col-md-3 d-none d-md-block"></div>

            <!-- 4a. DEPENDENCIA (solo EXTERNO) -->
            <div class="col-12 col-md-6 bloque-externo">
                <label for="dependencia_id" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-building-columns"></i> Dependencia <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-lg <?= isset($errors['dependencia_id']) ? 'is-invalid' : '' ?>"
                        id="dependencia_id" name="dependencia_id">
                    <option value="">-- Selecciona una dependencia --</option>
                    <?php foreach ($dependencias as $d): ?>
                    <option value="<?= $d['id'] ?>"
                        <?= ($old['dependencia_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['dependencia_id'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['dependencia_id']) ?></div>
                <?php endif; ?>
            </div>

            <!-- 4b. ÁREA INTERNA (solo INTERNO) -->
            <div class="col-12 col-md-6 bloque-interno" style="display:none;">
                <label for="area_interna_id" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-house-chimney"></i> Área Interna de Tesorería <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-lg <?= isset($errors['area_interna_id']) ? 'is-invalid' : '' ?>"
                        id="area_interna_id" name="area_interna_id">
                    <option value="">-- Selecciona un área interna --</option>
                    <?php foreach (($areas_internas ?? []) as $a): ?>
                    <option value="<?= $a['id'] ?>"
                        <?= ($old['area_interna_id'] ?? '') == $a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['area_interna_id'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['area_interna_id']) ?></div>
                <?php endif; ?>
                <small class="text-muted">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    ¿No aparece el área? Solicita a un Administrador que la dé de alta en
                    <a href="/catalogos#areas-internas">Catálogos → Áreas Internas</a>.
                </small>
            </div>

            <!-- 5. FOLIO DE LA DIRECCIÓN -->
            <div class="col-12 col-md-6">
                <label for="folio_direccion" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-folder"></i> Folio de la Dirección
                </label>
                <input type="text"
                       class="form-control form-control-lg"
                       id="folio_direccion" name="folio_direccion"
                       value="<?= htmlspecialchars($old['folio_direccion'] ?? '') ?>"
                       placeholder="Folio asignado por la dirección emisora">
            </div>

            <!-- 5b. FOLIO INTERNO TEXTO (solo INTERNO) -->
            <div class="col-12 bloque-interno" style="display:none;">
                <label for="folio_interno_texto" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-pen-ruler"></i> Folio de Oficio TICS (texto libre)
                </label>
                <input type="text"
                       class="form-control form-control-lg"
                       id="folio_interno_texto" name="folio_interno_texto"
                       value="<?= htmlspecialchars($old['folio_interno_texto'] ?? '') ?>"
                       placeholder="Ej: TM/ECA/STIyC/01/2026  ó  SDTICS/002/02/2026  ó  TM/STIYC/008/2026">
                <small class="text-muted">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    Los oficios internos pueden usar cualquier formato de folio.
                    Este campo se guarda tal como lo escribas.
                </small>
            </div>

            <!-- 6. ASUNTO -->
            <div class="col-12">
                <label for="asunto" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-envelope-open-text"></i> Asunto <span class="text-danger">*</span>
                </label>
                <textarea class="form-control form-control-lg <?= isset($errors['asunto']) ? 'is-invalid' : '' ?>"
                          id="asunto" name="asunto" rows="3"
                          placeholder="Describe brevemente el asunto del oficio..."
                          required><?= htmlspecialchars($old['asunto'] ?? '') ?></textarea>
                <?php if (isset($errors['asunto'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['asunto']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Separador: inicio de campos opcionales (pendientes) -->
            <div class="col-12">
                <div class="alert alert-warning border-0 mb-0 py-2 small">
                    <i class="fa-solid fa-hourglass-half me-2"></i>
                    <strong>Los siguientes campos son opcionales.</strong>
                    Si los dejas vacíos, el oficio se guardará como <span class="badge bg-warning text-dark">pendiente</span> (estatus "En Proceso") con folio auto-asignado.
                </div>
            </div>

            <!-- ZONA PENDIENTE — Folio + Fecha Oficio TICs + Realizó + Fecha Acuse -->
            <div class="col-12">
                <div class="folio-zona p-3 rounded-3" id="folioZona"
                     style="background:var(--dorado-suave);border:3px dashed var(--dorado-oscuro);">
                    <p class="fw-bold text-institucional mb-2 d-flex align-items-center gap-2 fs-5">
                        <i class="fa-solid fa-hourglass-half"></i>
                        Datos que pueden quedar pendientes
                        <span class="badge bg-warning text-dark" id="folioBadgePendiente">
                            <i class="fa-solid fa-hourglass-half me-1"></i>Pendientes permitidos
                        </span>
                    </p>
                    <div class="alert alert-warning border-0 mb-3 py-2 small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <strong>¿Todavía no tienes el Folio de Tesorería, la Fecha de Oficio TICs, el nombre de quien realizó o la Fecha de Acuse?</strong>
                        Déjalos vacíos: el oficio se guardará como <strong>PENDIENTE</strong> y podrás capturarlos después desde la edición del oficio.
                    </div>

                    <!-- Folio de Tesorería (SOLO EXTERNO) -->
                    <div class="bloque-externo">
                        <p class="fw-bold text-institucional mb-2 mt-2">
                            <i class="fa-solid fa-hashtag me-1"></i> Folio de Tesorería
                        </p>
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-2">
                                <label for="numero_folio" class="form-label fw-bold">Número</label>
                                <input type="number"
                                       class="form-control form-control-lg <?= isset($errors['numero_folio']) ? 'is-invalid' : '' ?>"
                                       id="numero_folio" name="numero_folio"
                                       value="<?= htmlspecialchars($numero) ?>"
                                       min="1" max="9999" placeholder="Ej: 495">
                                <?php if (isset($errors['numero_folio'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['numero_folio']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-md-2">
                                <label for="anio_folio" class="form-label fw-bold">Año</label>
                                <input type="number"
                                       class="form-control form-control-lg <?= isset($errors['anio_folio']) ? 'is-invalid' : '' ?>"
                                       id="anio_folio" name="anio_folio"
                                       value="<?= htmlspecialchars($anio) ?>"
                                       min="2020" max="2099">
                                <?php if (isset($errors['anio_folio'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['anio_folio']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label fw-bold">Vista Previa</label>
                                <div class="folio-preview" id="folioPreview">
                                    <span id="folioTexto">TM/ECA/STIyC/ <em class="text-warning">PENDIENTE</em> /<?= $anio_actual ?></span>
                                </div>
                                <small class="text-muted">
                                    <i class="fa-solid fa-circle-info me-1"></i>
                                    Escribe <strong>solo el número</strong> (las XXXX) cuando lo tengas. El prefijo <strong>TM/ECA/STIyC</strong> y el año se arman solos.
                                </small>
                            </div>
                        </div>

                        <hr class="my-3">
                    </div>

                    <!-- Otros datos pendientes (aplican a TODOS los tipos) -->
                    <p class="fw-bold text-institucional mb-2 mt-2">
                        <i class="fa-solid fa-calendar-check me-1"></i> Seguimiento
                    </p>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="fecha_oficio_tics" class="form-label fw-bold">
                                <i class="fa-solid fa-calendar-check"></i> Fecha Oficio TICs
                            </label>
                            <input type="date"
                                   class="form-control form-control-lg"
                                   id="fecha_oficio_tics" name="fecha_oficio_tics"
                                   value="<?= htmlspecialchars($old['fecha_oficio_tics'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="realizo" class="form-label fw-bold">
                                <i class="fa-solid fa-user-pen"></i> Realizó
                            </label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="realizo" name="realizo"
                                   value="<?= htmlspecialchars($old['realizo'] ?? '') ?>"
                                   placeholder="Nombre de quien realizó la gestión">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="fecha_acuse" class="form-label fw-bold">
                                <i class="fa-solid fa-stamp"></i> Fecha Acuse Oficialía Tesorería
                            </label>
                            <input type="date"
                                   class="form-control form-control-lg"
                                   id="fecha_acuse" name="fecha_acuse"
                                   value="<?= htmlspecialchars($old['fecha_acuse'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 11. STATUS -->
            <div class="col-12 col-md-6">
                <label for="estado_id" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-flag"></i> Estatus
                </label>
                <select class="form-select form-select-lg <?= isset($errors['estado_id']) ? 'is-invalid' : '' ?>"
                        id="estado_id" name="estado_id">
                    <option value="">— Pendiente (En Proceso) —</option>
                    <?php foreach ($estados as $e): ?>
                    <option value="<?= $e['id'] ?>"
                        <?= ($old['estado_id'] ?? '') == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['estado_id'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['estado_id']) ?></div>
                <?php endif; ?>
                <small class="text-muted">Si se deja vacío, el oficio queda <strong>pendiente</strong> en estatus "En Proceso".</small>
            </div>

            <!-- 12. OBSERVACIONES -->
            <div class="col-12">
                <label for="observaciones" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-comment-dots"></i> Observaciones
                </label>
                <textarea class="form-control form-control-lg"
                          id="observaciones" name="observaciones" rows="3"
                          placeholder="Notas, instrucciones o comentarios adicionales (opcional)..."><?= htmlspecialchars($old['observaciones'] ?? '') ?></textarea>
            </div>

        </div><!-- /row -->
    </div><!-- /card-body -->
</div><!-- /card -->

<!-- 13. SUBIR EVIDENCIA: OFICIO RECIBIDO -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-institucional text-white py-3">
        <h2 class="h5 fw-bold mb-0">
            <i class="fa-solid fa-file-arrow-up me-2"></i>Evidencia — Oficio Recibido
        </h2>
    </div>
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-8">
                <label for="pdf_recibido" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-file-pdf" style="color:var(--guinda)"></i>
                    Subir PDF del Oficio Recibido
                    <span class="badge ms-1" style="background:var(--dorado);color:var(--texto-oscuro);font-size:.75rem;">Opcional</span>
                </label>
                <input type="file"
                       class="form-control form-control-lg"
                       id="pdf_recibido" name="pdf_recibido"
                       accept=".pdf,application/pdf">
                <small class="text-muted">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Solo archivos PDF. Tamaño máximo: 10 MB.
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

<!-- 14. SUBIR EVIDENCIA: OFICIO CONTESTADO -->
<div class="card border-0 shadow-sm mb-4 bloque-respuesta">
    <div class="card-header text-white py-3" style="background:linear-gradient(135deg,var(--dorado-claro),var(--dorado-oscuro));border-bottom:3px solid var(--guinda);">
        <h2 class="h5 fw-bold mb-0">
            <i class="fa-solid fa-file-circle-check me-2"></i>Evidencia — Oficio Contestado
        </h2>
    </div>
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-8">
                <label for="pdf_contestado" class="form-label fw-bold fs-5">
                    <i class="fa-solid fa-file-pdf" style="color:var(--dorado-oscuro)"></i>
                    Subir PDF del Oficio Contestado
                    <span class="badge ms-1" style="background:var(--dorado);color:var(--texto-oscuro);font-size:.75rem;">Opcional</span>
                </label>
                <input type="file"
                       class="form-control form-control-lg"
                       id="pdf_contestado" name="pdf_contestado"
                       accept=".pdf,application/pdf">
                <small class="text-muted">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Solo archivos PDF. Tamaño máximo: 10 MB.
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

<!-- BOTONES FINALES -->
<div class="d-flex gap-3 flex-wrap pb-4">
    <button type="submit" class="btn btn-institucional btn-lg px-5 py-3">
        <i class="fa-solid fa-circle-check"></i>
        <strong>Guardar Oficio</strong>
    </button>
    <a href="/oficios" class="btn btn-outline-institucional btn-lg px-5 py-3">
        <i class="fa-solid fa-circle-xmark"></i>Cancelar
    </a>
</div>

</form>

<script>
function actualizarFolioPreview() {
    const numeroEl = document.getElementById('numero_folio');
    const anioEl   = document.getElementById('anio_folio');
    if (!numeroEl || !anioEl) return;
    const numero = numeroEl.value;
    const anio   = anioEl.value;
    const el     = document.getElementById('folioTexto');
    const prev   = document.getElementById('folioPreview');
    const badge  = document.getElementById('folioBadgePendiente');
    const zona   = document.getElementById('folioZona');

    if (numero && anio) {
        const pad = String(numero).padStart(4, '0');
        el.innerHTML = `TM/ECA/STIyC/${pad}/${anio}`;
        prev.classList.add('folio-preview-activo');
        if (badge) badge.classList.add('d-none');
        if (zona)  zona.style.borderStyle = 'solid';
    } else {
        el.innerHTML = `TM/ECA/STIyC/ <em class="text-warning">PENDIENTE</em> /${anio || '????'}`;
        prev.classList.remove('folio-preview-activo');
        if (badge) badge.classList.remove('d-none');
        if (zona)  zona.style.borderStyle = 'dashed';
    }
}

document.getElementById('numero_folio').addEventListener('input', actualizarFolioPreview);
document.getElementById('anio_folio').addEventListener('input',   actualizarFolioPreview);
actualizarFolioPreview();

// ===== TIPO DE OFICIO: alternar bloques (lee flags desde data-attributes) =====
(function () {
    const radios       = document.querySelectorAll('input[name="tipo_oficio"]');
    const bloquesExt   = document.querySelectorAll('.bloque-externo');
    const bloquesInt   = document.querySelectorAll('.bloque-interno');
    const bloquesResp  = document.querySelectorAll('.bloque-respuesta');
    const labelExt     = document.querySelector('label[for="tipo_externo"]');
    const labelInt     = document.querySelector('label[for="tipo_interno"]');
    const labelCon     = document.querySelector('label[for="tipo_conocimiento"]');
    const bannerCon    = document.getElementById('bannerConocimiento');
    const selDep       = document.getElementById('dependencia_id');
    const selArea      = document.getElementById('area_interna_id');
    const numFolio     = document.getElementById('numero_folio');
    const anioFolio    = document.getElementById('anio_folio');

    function aplicar(radioSeleccionado) {
        const tipo = radioSeleccionado.value;
        const requiereRespuesta = radioSeleccionado.dataset.requiereRespuesta === '1';

        const esInterno      = (tipo === 'INTERNO');
        const esConocimiento = (tipo === 'CONOCIMIENTO');
        const esExterno      = (tipo === 'EXTERNO');

        // Bloques con dependencia externa: visibles para EXTERNO y CONOCIMIENTO (en CONOC es opcional).
        bloquesExt.forEach(el => el.style.display = esInterno ? 'none' : '');
        bloquesInt.forEach(el => el.style.display = esInterno ? '' : 'none');

        // Bloques que solo aplican si el tipo requiere respuesta (PDF contestado, etc.)
        bloquesResp.forEach(el => el.style.display = requiereRespuesta ? '' : 'none');

        // Banner CONOCIMIENTO
        if (bannerCon) bannerCon.classList.toggle('d-none', !esConocimiento);

        // Selección visual
        if (labelExt) labelExt.classList.toggle('seleccionado-externo',      esExterno);
        if (labelInt) labelInt.classList.toggle('seleccionado-interno',      esInterno);
        if (labelCon) labelCon.classList.toggle('seleccionado-conocimiento', esConocimiento);

        // required dinámico (server-side manda; esto solo ayuda al usuario)
        if (selDep)  selDep.required  = esExterno;          // CONOCIMIENTO: opcional, sin required
        if (selArea) selArea.required = esInterno;
        // numero_folio SIEMPRE opcional: si el usuario no lo tiene, el oficio queda pendiente.
        if (numFolio) numFolio.required = false;

        // Ajustar label de dependencia para que CONOCIMIENTO no muestre asterisco
        const labelDep = document.querySelector('label[for="dependencia_id"]');
        if (labelDep && esConocimiento) {
            labelDep.innerHTML = '<i class="fa-solid fa-building-columns"></i> Dependencia (opcional)';
        } else if (labelDep && esExterno) {
            labelDep.innerHTML = '<i class="fa-solid fa-building-columns"></i> Dependencia <span class="text-danger">*</span>';
        }

        // El folio numerado TM/ECA/... NO aplica a INTERNO ni CONOCIMIENTO
        if (esInterno || esConocimiento) {
            if (numFolio)  numFolio.value  = '';
            if (anioFolio) anioFolio.value = '';
        }
    }

    radios.forEach(r => r.addEventListener('change', e => aplicar(e.target)));
    const actual = document.querySelector('input[name="tipo_oficio"]:checked')
                 || document.getElementById('tipo_externo');
    if (actual) aplicar(actual);
})();

// Mostrar nombre del archivo seleccionado
['pdf_recibido','pdf_contestado'].forEach(id => {
    const input = document.getElementById(id);
    if (!input) return;
    input.addEventListener('change', function () {
        const label = this.previousElementSibling ? this.previousElementSibling.querySelector('small') : null;
        if (this.files && this.files[0]) {
            const name = this.files[0].name;
            const size = (this.files[0].size / 1048576).toFixed(2);
            this.classList.remove('is-invalid');
            const next = this.nextElementSibling;
            if (next && next.tagName === 'SMALL') {
                next.innerHTML = `<i class="fa-solid fa-circle-check me-1" style="color:green"></i><strong>${name}</strong> (${size} MB)`;
            }
        }
    });
});
</script>
