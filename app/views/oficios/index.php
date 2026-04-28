<?php $pageTitle = 'Oficios'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-folder-open me-2" aria-hidden="true"></i>Oficios
    </h1>
    <a href="<?= url_path('/oficios/crear') ?>" class="btn btn-institucional btn-lg">
        <i class="fa-solid fa-circle-plus me-2" aria-hidden="true"></i>Nuevo Oficio
    </a>
</div>

<!-- FILTROS DE BÃšSQUEDA -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h2 class="h6 fw-bold mb-0 text-muted">
            <i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Filtros de BÃºsqueda
        </h2>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= url_path('/oficios') ?>" novalidate>
            <div class="row g-3">
                <!-- Tipo de oficio -->
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="f_tipo" class="form-label fw-semibold">Tipo de Oficio</label>
                    <select class="form-select" id="f_tipo" name="tipo">
                        <option value=""             <?= $filtro_tipo === ''             ? 'selected' : '' ?>>Todos</option>
                        <option value="EXTERNO"      <?= $filtro_tipo === 'EXTERNO'      ? 'selected' : '' ?>>Externos</option>
                        <option value="INTERNO"      <?= $filtro_tipo === 'INTERNO'      ? 'selected' : '' ?>>Internos</option>
                        <option value="CONOCIMIENTO" <?= $filtro_tipo === 'CONOCIMIENTO' ? 'selected' : '' ?>>Conocimiento</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="f_folio" class="form-label fw-semibold">Folio</label>
                    <input type="text" class="form-control" id="f_folio" name="folio"
                           value="<?= htmlspecialchars($filtro_folio) ?>"
                           placeholder="Busca en TesorerÃ­a / Interno / DirecciÃ³n">
                </div>
                <div class="col-12 col-md-8 col-lg-3">
                    <label for="f_asunto" class="form-label fw-semibold">Asunto</label>
                    <input type="text" class="form-control" id="f_asunto" name="asunto"
                           value="<?= htmlspecialchars($filtro_asunto) ?>"
                           placeholder="Buscar en el asunto...">
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="f_dep" class="form-label fw-semibold">Dependencia</label>
                    <select class="form-select" id="f_dep" name="dependencia">
                        <option value="">-- Todas --</option>
                        <?php foreach ($dependencias as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filtro_dep == $d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="f_area" class="form-label fw-semibold">Ãrea Interna</label>
                    <select class="form-select" id="f_area" name="area_interna">
                        <option value="">-- Todas --</option>
                        <?php foreach (($areas_internas ?? []) as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($filtro_area ?? 0) == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="f_estado" class="form-label fw-semibold">Estado</label>
                    <select class="form-select" id="f_estado" name="estado">
                        <option value="">-- Todos --</option>
                        <?php foreach ($estados as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filtro_estado == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="f_desde" class="form-label fw-semibold">Desde</label>
                    <input type="date" class="form-control" id="f_desde" name="desde"
                           value="<?= htmlspecialchars($filtro_desde) ?>">
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="f_hasta" class="form-label fw-semibold">Hasta</label>
                    <input type="date" class="form-control" id="f_hasta" name="hasta"
                           value="<?= htmlspecialchars($filtro_hasta) ?>">
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-institucional btn-lg px-4">
                    <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Buscar
                </button>
                <a href="<?= url_path('/oficios') ?>" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fa-solid fa-circle-xmark me-2" aria-hidden="true"></i>Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- RESULTADOS -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
        <h2 class="h6 fw-bold mb-0 text-muted">
            <i class="fa-solid fa-list-ul me-2" aria-hidden="true"></i>
            Resultados: <strong class="text-dark"><?= number_format($total) ?></strong> oficio(s)
        </h2>
    </div>

    <?php if (empty($oficios)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="fa-solid fa-magnifying-glass display-4 d-block mb-3" aria-hidden="true"></i>
        <p class="fs-5">No se encontraron oficios con los filtros aplicados.</p>
        <a href="<?= url_path('/oficios/crear') ?>" class="btn btn-institucional mt-2">Registrar nuevo oficio</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-lg mb-0" aria-label="Lista de oficios">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">Tipo</th>
                    <th scope="col">Folio</th>
                    <th scope="col">Dependencia / Ãrea Interna</th>
                    <th scope="col">Asunto</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Fecha RecepciÃ³n</th>
                    <th scope="col">Compromiso</th>
                    <th scope="col" class="pe-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($oficios as $oficio):
                $_tipo_clave_row = $oficio['tipo_oficio_clave'] ?? 'EXTERNO';
                $es_int = ($_tipo_clave_row === 'INTERNO');
                $es_con = ($_tipo_clave_row === 'CONOCIMIENTO');
                if ($es_int) {
                    $origen = $oficio['area_interna'] ?? 'â€”';
                } elseif ($es_con) {
                    $origen = $oficio['dependencia'] ?? 'Sin dependencia';
                } else {
                    $origen = $oficio['dependencia'] ?? 'â€”';
                }
            ?>
                <tr>
                    <td class="ps-4">
                        <?php if ($es_con): ?>
                            <span class="badge" style="background:#495057;color:#fff;" title="Oficio de Conocimiento">
                                <i class="fa-solid fa-eye me-1"></i>Conocimiento
                            </span>
                        <?php elseif ($es_int): ?>
                            <span class="badge" style="background:var(--dorado-oscuro);color:#fff;" title="Oficio Interno">
                                <i class="fa-solid fa-house-chimney me-1"></i>Interno
                            </span>
                        <?php else: ?>
                            <span class="badge bg-institucional" title="Oficio Externo">
                                <i class="fa-solid fa-building-columns me-1"></i>Externo
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $__pendienteFolio = empty($oficio['numero_folio']) && ($oficio['tipo_oficio_clave'] ?? '') !== 'INTERNO'; ?>
                        <a href="<?= url_path('/oficios/'.$oficio['id']) ?>" class="folio-link fw-bold text-decoration-none <?= $__pendienteFolio ? 'text-warning' : '' ?>">
                            <?= htmlspecialchars($oficio['folio_display'] ?? $oficio['folio_tesoreria']) ?>
                        </a>
                        <?php if ($__pendienteFolio): ?>
                        <br>
                        <span class="badge bg-warning text-dark mt-1">
                            <i class="fa-solid fa-hourglass-half me-1"></i>Folio pendiente
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-truncate" style="max-width:200px;" title="<?= htmlspecialchars($origen) ?>">
                        <?= htmlspecialchars($origen) ?>
                    </td>
                    <td class="text-truncate" style="max-width:220px;" title="<?= htmlspecialchars($oficio['asunto']) ?>">
                        <?= htmlspecialchars($oficio['asunto']) ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= htmlspecialchars($oficio['estado_color']) ?> badge-estado">
                            <?= htmlspecialchars($oficio['estado']) ?>
                        </span>
                    </td>
                    <td><?= $oficio['fecha_recepcion'] ? date('d/m/Y', strtotime($oficio['fecha_recepcion'])) : 'â€”' ?></td>
                    <td>
                        <?php if ($es_con): ?>
                            <span class="text-muted">N/A</span>
                        <?php elseif ($oficio['fecha_compromiso']): ?>
                            <?php $fc = new DateTime($oficio['fecha_compromiso']); $hoy = new DateTime(); ?>
                            <span class="<?= $fc < $hoy ? 'text-danger fw-bold' : '' ?>">
                                <?= date('d/m/Y', strtotime($oficio['fecha_compromiso'])) ?>
                            </span>
                        <?php else: ?>â€”<?php endif; ?>
                    </td>
                    <td class="pe-4 text-center">
                        <div class="d-flex gap-1 justify-content-center align-items-center flex-wrap">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?= url_path('/oficios/'.$oficio['id']) ?>" class="btn btn-outline-primary" title="Ver detalle">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <a href="<?= url_path('/oficios/'.$oficio['id'].'/editar') ?>" class="btn btn-outline-secondary" title="Editar">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                </a>
                            </div>
                            <?php if (Auth::hasRole([ROL_GOD, ROL_ADMIN])): ?>
                            <?php $__folio = $oficio['folio_display'] ?? ($oficio['folio_tesoreria'] ?? ('#' . $oficio['id'])); ?>
                            <form method="POST" action="<?= url_path('/oficios/'.$oficio['id'].'/eliminar') ?>" class="m-0"
                                  data-confirm="Se eliminarÃ¡ permanentemente el oficio <strong><?= htmlspecialchars($__folio) ?></strong> junto con sus movimientos y PDFs. Esta acciÃ³n no se puede deshacer."
                                  data-confirm-icon="warning"
                                  data-confirm-btn="SÃ­, eliminar"
                                  data-confirm-double="1">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar permanentemente">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINACIÃ“N -->
    <?php if ($paginas > 1): ?>
    <div class="card-footer bg-white border-top">
        <nav aria-label="PaginaciÃ³n de oficios">
            <ul class="pagination justify-content-center mb-0">
                <?php if ($pagina > 1): ?>
                <li class="page-item">
                    <a class="page-link page-link-lg" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Anterior
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($p = max(1, $pagina - 2); $p <= min($paginas, $pagina + 2); $p++): ?>
                <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                    <a class="page-link page-link-lg" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($pagina < $paginas): ?>
                <li class="page-item">
                    <a class="page-link page-link-lg" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
                        Siguiente <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <p class="text-center text-muted small mt-2">PÃ¡gina <?= $pagina ?> de <?= $paginas ?></p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

