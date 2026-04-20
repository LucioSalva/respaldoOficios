<?php $pageTitle = 'Catálogos'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-table-list me-2" aria-hidden="true"></i>Catálogos del Sistema
    </h1>
</div>

<!-- NAV TABS -->
<ul class="nav nav-tabs nav-tabs-lg mb-4" id="catalogosTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link active fs-5 fw-semibold" id="dep-tab" href="#dependencias"
           data-bs-toggle="tab" role="tab">
            <i class="fa-solid fa-building-columns me-2" aria-hidden="true"></i>Dependencias
            <span class="badge bg-secondary ms-2"><?= count($dependencias) ?></span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link fs-5 fw-semibold" id="ai-tab" href="#areas-internas"
           data-bs-toggle="tab" role="tab">
            <i class="fa-solid fa-house-chimney me-2" aria-hidden="true"></i>Áreas Internas
            <span class="badge bg-secondary ms-2"><?= count($areas_internas ?? []) ?></span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link fs-5 fw-semibold" id="est-tab" href="#estados"
           data-bs-toggle="tab" role="tab">
            <i class="fa-solid fa-flag me-2" aria-hidden="true"></i>Estados
            <span class="badge bg-secondary ms-2"><?= count($estados) ?></span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link fs-5 fw-semibold" id="tev-tab" href="#tipos-evidencia"
           data-bs-toggle="tab" role="tab">
            <i class="fa-solid fa-file-pdf me-2" aria-hidden="true"></i>Tipos de Evidencia
            <span class="badge bg-secondary ms-2"><?= count($tipos_ev) ?></span>
        </a>
    </li>
</ul>

<div class="tab-content" id="catalogosTabsContent">

    <!-- ===== DEPENDENCIAS ===== -->
    <div class="tab-pane fade show active" id="dependencias" role="tabpanel">
        <div class="row g-4">
            <!-- Formulario agregar -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-institucional-light py-3">
                        <h2 class="h5 fw-bold mb-0 text-institucional">
                            <i class="fa-solid fa-circle-plus me-2" aria-hidden="true"></i>Agregar Dependencia
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/catalogos/dependencias" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                            <div class="mb-3">
                                <label for="dep_nombre" class="form-label fw-semibold">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg"
                                       id="dep_nombre" name="nombre"
                                       placeholder="Nombre completo de la dependencia" required>
                            </div>
                            <div class="mb-3">
                                <label for="dep_clave" class="form-label fw-semibold">Clave</label>
                                <input type="text" class="form-control" id="dep_clave" name="clave"
                                       placeholder="Ej: DA, DDU, OICM..." maxlength="20">
                            </div>
                            <button type="submit" class="btn btn-institucional w-100 btn-lg">
                                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Guardar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista de dependencias -->
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="h6 fw-bold mb-0 text-muted">Lista de Dependencias</h2>
                    </div>
                    <div class="table-responsive" style="max-height:500px; overflow-y:auto;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Clave</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($dependencias as $dep): ?>
                            <tr class="<?= !$dep['activo'] ? 'table-secondary opacity-75' : '' ?>">
                                <td class="fw-semibold"><?= htmlspecialchars($dep['nombre']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($dep['clave'] ?? '—') ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $dep['activo'] ? 'success' : 'danger' ?>">
                                        <?= $dep['activo'] ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <!-- Editar (modal) -->
                                        <button class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editDepModal"
                                                data-id="<?= $dep['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($dep['nombre']) ?>"
                                                data-clave="<?= htmlspecialchars($dep['clave'] ?? '') ?>"
                                                title="Editar">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        </button>
                                        <!-- Toggle activo -->
                                        <form method="POST" action="/catalogos/dependencias/<?= $dep['id'] ?>/toggle"
                                              data-confirm="<?= $dep['activo'] ? '¿Desactivar' : '¿Activar' ?> la dependencia <strong><?= htmlspecialchars($dep['nombre']) ?></strong>?"
                                              data-confirm-icon="question">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                                            <button type="submit" class="btn btn-sm btn-<?= $dep['activo'] ? 'outline-danger' : 'outline-success' ?>"
                                                    title="<?= $dep['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                <i class="fa-solid fa-<?= $dep['activo'] ? 'ban' : 'circle-check' ?>" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ÁREAS INTERNAS ===== -->
    <div class="tab-pane fade" id="areas-internas" role="tabpanel">
        <div class="row g-4">
            <!-- Formulario agregar -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-institucional-light py-3">
                        <h2 class="h5 fw-bold mb-0 text-institucional">
                            <i class="fa-solid fa-circle-plus me-2" aria-hidden="true"></i>Agregar Área Interna
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/catalogos/areas-internas" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                            <div class="mb-3">
                                <label for="ai_nombre" class="form-label fw-semibold">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg"
                                       id="ai_nombre" name="nombre"
                                       placeholder="Ej: Subdirección de Egresos" required>
                            </div>
                            <div class="mb-3">
                                <label for="ai_clave" class="form-label fw-semibold">Clave</label>
                                <input type="text" class="form-control" id="ai_clave" name="clave"
                                       placeholder="Ej: SE, CAT, SJT..." maxlength="30">
                            </div>
                            <button type="submit" class="btn btn-institucional w-100 btn-lg">
                                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Guardar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista -->
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="h6 fw-bold mb-0 text-muted">Lista de Áreas Internas de Tesorería</h2>
                    </div>
                    <div class="table-responsive" style="max-height:500px; overflow-y:auto;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Clave</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (($areas_internas ?? []) as $ai): ?>
                            <tr class="<?= !$ai['activo'] ? 'table-secondary opacity-75' : '' ?>">
                                <td class="fw-semibold"><?= htmlspecialchars($ai['nombre']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ai['clave'] ?? '—') ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $ai['activo'] ? 'success' : 'danger' ?>">
                                        <?= $ai['activo'] ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editAreaModal"
                                                data-id="<?= $ai['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($ai['nombre']) ?>"
                                                data-clave="<?= htmlspecialchars($ai['clave'] ?? '') ?>"
                                                title="Editar">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        </button>
                                        <form method="POST" action="/catalogos/areas-internas/<?= $ai['id'] ?>/toggle"
                                              data-confirm="<?= $ai['activo'] ? '¿Desactivar' : '¿Activar' ?> el área interna <strong><?= htmlspecialchars($ai['nombre']) ?></strong>?"
                                              data-confirm-icon="question">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                                            <button type="submit" class="btn btn-sm btn-<?= $ai['activo'] ? 'outline-danger' : 'outline-success' ?>"
                                                    title="<?= $ai['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                <i class="fa-solid fa-<?= $ai['activo'] ? 'ban' : 'circle-check' ?>" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ESTADOS ===== -->
    <div class="tab-pane fade" id="estados" role="tabpanel">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-institucional-light py-3">
                        <h2 class="h5 fw-bold mb-0 text-institucional">
                            <i class="fa-solid fa-circle-plus me-2" aria-hidden="true"></i>Agregar Estado
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/catalogos/estados" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                            <div class="mb-3">
                                <label for="est_nombre" class="form-label fw-semibold">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg"
                                       id="est_nombre" name="nombre" required>
                            </div>
                            <div class="mb-3">
                                <label for="est_color" class="form-label fw-semibold">Color (Bootstrap)</label>
                                <select class="form-select" id="est_color" name="color">
                                    <option value="primary">Azul (primary)</option>
                                    <option value="warning">Amarillo (warning)</option>
                                    <option value="success">Verde (success)</option>
                                    <option value="info">Celeste (info)</option>
                                    <option value="danger">Rojo (danger)</option>
                                    <option value="dark">Oscuro (dark)</option>
                                    <option value="secondary">Gris (secondary)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="est_orden" class="form-label fw-semibold">Orden</label>
                                <input type="number" class="form-control" id="est_orden" name="orden"
                                       value="99" min="1" max="999">
                            </div>
                            <button type="submit" class="btn btn-institucional w-100 btn-lg">
                                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Guardar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="h6 fw-bold mb-0 text-muted">Lista de Estados</h2>
                    </div>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Color</th>
                                <th>Orden</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($estados as $est): ?>
                        <tr>
                            <td class="fw-semibold">
                                <span class="badge bg-<?= htmlspecialchars($est['color']) ?> me-2">
                                    <?= htmlspecialchars($est['nombre']) ?>
                                </span>
                            </td>
                            <td><code><?= htmlspecialchars($est['color']) ?></code></td>
                            <td><?= $est['orden'] ?></td>
                            <td>
                                <span class="badge bg-<?= $est['activo'] ? 'success' : 'danger' ?>">
                                    <?= $est['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="POST" action="/catalogos/estados/<?= $est['id'] ?>/toggle"
                                      data-confirm="<?= $est['activo'] ? '¿Desactivar' : '¿Activar' ?> el estado <strong><?= htmlspecialchars($est['nombre']) ?></strong>?"
                                      data-confirm-icon="question">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                                    <button type="submit" class="btn btn-sm btn-<?= $est['activo'] ? 'outline-danger' : 'outline-success' ?>"
                                            title="<?= $est['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fa-solid fa-<?= $est['activo'] ? 'ban' : 'circle-check' ?>" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TIPOS DE EVIDENCIA ===== -->
    <div class="tab-pane fade" id="tipos-evidencia" role="tabpanel">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-institucional-light py-3">
                        <h2 class="h5 fw-bold mb-0 text-institucional">
                            <i class="fa-solid fa-circle-plus me-2" aria-hidden="true"></i>Agregar Tipo de Evidencia
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/catalogos/tipos-evidencia" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                            <div class="mb-3">
                                <label for="te_nombre" class="form-label fw-semibold">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg"
                                       id="te_nombre" name="nombre" required>
                            </div>
                            <div class="mb-3">
                                <label for="te_desc" class="form-label fw-semibold">Descripción</label>
                                <textarea class="form-control" id="te_desc" name="descripcion" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-institucional w-100 btn-lg">
                                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Guardar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="h6 fw-bold mb-0 text-muted">Lista de Tipos de Evidencia</h2>
                    </div>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tipos_ev as $te): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($te['nombre']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($te['descripcion'] ?? '—') ?></td>
                            <td>
                                <span class="badge bg-<?= $te['activo'] ? 'success' : 'danger' ?>">
                                    <?= $te['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="POST" action="/catalogos/tipos-evidencia/<?= $te['id'] ?>/toggle"
                                      data-confirm="<?= $te['activo'] ? '¿Desactivar' : '¿Activar' ?> el tipo de evidencia <strong><?= htmlspecialchars($te['nombre']) ?></strong>?"
                                      data-confirm-icon="question">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                                    <button type="submit" class="btn btn-sm btn-<?= $te['activo'] ? 'outline-danger' : 'outline-success' ?>"
                                            title="<?= $te['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fa-solid fa-<?= $te['activo'] ? 'ban' : 'circle-check' ?>" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal editar dependencia -->
<div class="modal fade" id="editDepModal" tabindex="-1" aria-labelledby="editDepModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editDepForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editDepModalLabel">Editar Dependencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_dep_nombre" class="form-label fw-semibold">Nombre</label>
                        <input type="text" class="form-control form-control-lg" id="edit_dep_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_dep_clave" class="form-label fw-semibold">Clave</label>
                        <input type="text" class="form-control" id="edit_dep_clave" name="clave" maxlength="20">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-institucional btn-lg">
                        <i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal editar área interna -->
<div class="modal fade" id="editAreaModal" tabindex="-1" aria-labelledby="editAreaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editAreaForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editAreaModalLabel">Editar Área Interna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_ai_nombre" class="form-label fw-semibold">Nombre</label>
                        <input type="text" class="form-control form-control-lg" id="edit_ai_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_ai_clave" class="form-label fw-semibold">Clave</label>
                        <input type="text" class="form-control" id="edit_ai_clave" name="clave" maxlength="30">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-institucional btn-lg">
                        <i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Activar tab según hash de URL
document.addEventListener('DOMContentLoaded', function () {
    const hash = window.location.hash;
    if (hash) {
        const tabEl = document.querySelector(`a[href="${hash}"]`);
        if (tabEl) {
            new bootstrap.Tab(tabEl).show();
        }
    }

    // Modal editar dependencia
    const editModal = document.getElementById('editDepModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const btn  = event.relatedTarget;
        const id   = btn.getAttribute('data-id');
        const nombre = btn.getAttribute('data-nombre');
        const clave  = btn.getAttribute('data-clave');
        document.getElementById('edit_dep_nombre').value = nombre;
        document.getElementById('edit_dep_clave').value  = clave;
        document.getElementById('editDepForm').action = `/catalogos/dependencias/${id}/editar`;
    });

    // Modal editar área interna
    const editAreaModal = document.getElementById('editAreaModal');
    if (editAreaModal) {
        editAreaModal.addEventListener('show.bs.modal', function (event) {
            const btn    = event.relatedTarget;
            const id     = btn.getAttribute('data-id');
            const nombre = btn.getAttribute('data-nombre');
            const clave  = btn.getAttribute('data-clave');
            document.getElementById('edit_ai_nombre').value = nombre;
            document.getElementById('edit_ai_clave').value  = clave;
            document.getElementById('editAreaForm').action  = `/catalogos/areas-internas/${id}/editar`;
        });
    }
});
</script>
