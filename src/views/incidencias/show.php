<?php $pageTitle = 'Incidencia #' . (int)$inc['id']; ?>
<?php $puedeEditar = Auth::hasRole([ROL_GOD, ROL_ADMIN]); ?>
<?php $estClave = $inc['estatus_clave'] ?? ''; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        Incidencia #<?= (int)$inc['id'] ?>
    </h1>
    <div class="d-flex gap-2">
        <a href="/incidencias" class="btn btn-outline-secondary btn-lg">
            <i class="fa-solid fa-arrow-left me-1"></i>Regresar
        </a>
        <?php if ($puedeEditar): ?>
            <a href="/incidencias/<?= (int)$inc['id'] ?>/editar" class="btn btn-outline-primary btn-lg">
                <i class="fa-solid fa-pen me-1"></i>Editar
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">
                    <span class="badge bg-<?= htmlspecialchars($inc['tipo_color']) ?> fs-6 me-2">
                        <?php if (!empty($inc['tipo_icono'])): ?>
                            <i class="fa-solid <?= htmlspecialchars($inc['tipo_icono']) ?> me-1"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($inc['tipo_nombre']) ?>
                    </span>
                    <span class="badge bg-<?= htmlspecialchars($inc['estatus_color']) ?> fs-6">
                        <?= htmlspecialchars($inc['estatus_nombre']) ?>
                    </span>
                </h2>

                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Empleado</dt>
                    <dd class="col-sm-8">
                        <a href="/personal/<?= (int)$inc['personal_id'] ?>" class="text-decoration-none">
                            <strong><?= htmlspecialchars($inc['nombre_completo']) ?></strong>
                        </a>
                        · N° <?= htmlspecialchars($inc['numero_empleado'] ?? '—') ?>
                        · <?= htmlspecialchars($inc['tipo_personal_nombre']) ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Fecha de incidencia</dt>
                    <dd class="col-sm-8"><?= $inc['fecha_incidencia'] ? date('d/m/Y', strtotime($inc['fecha_incidencia'])) : '—' ?></dd>

                    <dt class="col-sm-4 text-muted">Día a justificar</dt>
                    <dd class="col-sm-8">
                        <?php if ($inc['fecha_inicio'] && $inc['fecha_fin'] && $inc['fecha_inicio'] !== $inc['fecha_fin']): ?>
                            Del <?= date('d/m/Y', strtotime($inc['fecha_inicio'])) ?>
                            al  <?= date('d/m/Y', strtotime($inc['fecha_fin'])) ?>
                        <?php elseif ($inc['fecha_inicio']): ?>
                            <?= date('d/m/Y', strtotime($inc['fecha_inicio'])) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Recibido por coordinación</dt>
                    <dd class="col-sm-8"><?= $inc['fecha_recibido_coord'] ? date('d/m/Y', strtotime($inc['fecha_recibido_coord'])) : '—' ?></dd>

                    <dt class="col-sm-4 text-muted">Periodo</dt>
                    <dd class="col-sm-8">
                        <?= htmlspecialchars($inc['periodo'] ?? '—') ?>
                        <?php if (!empty($inc['anio'])): ?>
                            <small class="text-muted ms-2">
                                · Año <?= (int)$inc['anio'] ?>
                                <?php if (!empty($inc['mes'])): ?>· Mes <?= (int)$inc['mes'] ?><?php endif; ?>
                                <?php if (!empty($inc['quincena'])): ?>· <?= (int)$inc['quincena'] ?>ª quincena<?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Duración</dt>
                    <dd class="col-sm-8">
                        <?= (int)$inc['dias']    ?> días ·
                        <?= (int)$inc['horas']   ?> h ·
                        <?= (int)$inc['minutos'] ?> min
                    </dd>

                    <dt class="col-sm-4 text-muted">Motivo</dt>
                    <dd class="col-sm-8"><?= nl2br(htmlspecialchars($inc['motivo'] ?? '—')) ?></dd>

                    <?php if (!empty($inc['observaciones'])): ?>
                    <dt class="col-sm-4 text-muted">Observaciones</dt>
                    <dd class="col-sm-8"><?= nl2br(htmlspecialchars($inc['observaciones'])) ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($inc['justificacion'])): ?>
                    <dt class="col-sm-4 text-muted">Justificación</dt>
                    <dd class="col-sm-8"><?= nl2br(htmlspecialchars($inc['justificacion'])) ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($inc['folio_justificacion'])): ?>
                    <dt class="col-sm-4 text-muted">Folio de justificación</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($inc['folio_justificacion']) ?></dd>
                    <?php endif; ?>

                    <dt class="col-sm-4 text-muted">Fuente</dt>
                    <dd class="col-sm-8"><small class="text-muted"><?= htmlspecialchars($inc['fuente'] ?? 'MANUAL') ?></small></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom py-3 fw-semibold">
                <i class="fa-solid fa-clock-rotate-left me-2"></i>Línea de tiempo
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fa-solid fa-circle-plus text-success me-1"></i>
                        <strong>Registrada</strong><br>
                        <small class="text-muted">
                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($inc['created_at']))) ?>
                            por <?= htmlspecialchars($inc['capturo_nombre'] ?? '—') ?>
                        </small>
                    </li>
                    <?php if ($inc['updated_at'] !== $inc['created_at']): ?>
                    <li class="mb-2">
                        <i class="fa-solid fa-pen text-primary me-1"></i>
                        <strong>Última edición</strong><br>
                        <small class="text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($inc['updated_at']))) ?></small>
                    </li>
                    <?php endif; ?>
                    <li>
                        <i class="fa-solid fa-flag text-<?= htmlspecialchars($inc['estatus_color']) ?> me-1"></i>
                        <strong>Estatus actual:</strong>
                        <span class="badge bg-<?= htmlspecialchars($inc['estatus_color']) ?>">
                            <?= htmlspecialchars($inc['estatus_nombre']) ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ($puedeEditar && !in_array($estClave, ['JUSTIFICADA', 'CANCELADA'], true)): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom py-3 fw-semibold">
                <i class="fa-solid fa-file-circle-check me-2"></i>Justificar incidencia
            </div>
            <div class="card-body">
                <form method="POST" action="/incidencias/<?= (int)$inc['id'] ?>/justificar">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                    <div class="mb-2">
                        <label for="justificacion" class="form-label fw-semibold">Texto de la justificación</label>
                        <textarea id="justificacion" name="justificacion" rows="4"
                                  maxlength="2000" required minlength="5"
                                  class="form-control"
                                  placeholder="Describa la justificación formal..."><?= htmlspecialchars($inc['justificacion'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100"
                            data-confirm="Se marcará la incidencia como justificada."
                            data-confirm-btn="Sí, justificar"
                            data-confirm-icon="question">
                        <i class="fa-solid fa-check me-1"></i>Marcar como justificada
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($puedeEditar && $estClave !== 'CANCELADA'): ?>
        <form method="POST" action="/incidencias/<?= (int)$inc['id'] ?>/cancelar"
              data-confirm="Se cancelará el registro. El empleado seguirá en el historial pero la incidencia dejará de contar."
              data-confirm-btn="Sí, cancelar incidencia"
              data-confirm-icon="warning">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
            <button type="submit" class="btn btn-outline-danger w-100 btn-lg">
                <i class="fa-solid fa-ban me-1"></i>Cancelar esta incidencia
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
