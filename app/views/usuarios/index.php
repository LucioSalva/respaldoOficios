<?php $pageTitle = 'GestiÃ³n de Usuarios'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-users me-2" aria-hidden="true"></i>Usuarios del Sistema
    </h1>
    <a href="<?= url_path('/usuarios/crear') ?>" class="btn btn-institucional btn-lg">
        <i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Nuevo Usuario
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <p class="mb-0 text-muted">
            Total de usuarios registrados: <strong class="text-dark"><?= count($usuarios) ?></strong>
        </p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-lg mb-0" aria-label="Lista de usuarios">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">Nombre</th>
                    <th scope="col">Correo ElectrÃ³nico</th>
                    <th scope="col">Rol</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Registrado</th>
                    <th scope="col" class="pe-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr class="<?= !$u['activo'] ? 'table-secondary opacity-75' : '' ?>">
                <td class="ps-4 fw-semibold">
                    <?= htmlspecialchars($u['nombre']) ?>
                    <?php if ($u['id'] === Auth::userId()): ?>
                    <span class="badge bg-info ms-2 small">TÃº</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($u['email'])): ?>
                        <?= htmlspecialchars($u['email']) ?>
                    <?php else: ?>
                        <span class="text-muted">â€”</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-<?= $u['rol_id'] == ROL_GOD ? 'danger' : ($u['rol_id'] == ROL_ADMIN ? 'warning text-dark' : 'secondary') ?>">
                        <?= htmlspecialchars($u['rol_nombre']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($u['activo']): ?>
                    <span class="badge bg-success">Activo</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : 'â€”' ?></td>
                <td class="pe-4 text-center">
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="<?= url_path('/usuarios/'.$u['id'].'/editar') ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        </a>
                        <?php if ($u['id'] !== Auth::userId()): ?>
                        <form method="POST" action="<?= url_path('/usuarios/'.$u['id'].'/toggle') ?>"
                              data-confirm="<?= $u['activo'] ? 'Â¿Desactivar a' : 'Â¿Activar a' ?> <strong><?= htmlspecialchars($u['nombre']) ?></strong>?"
                              data-confirm-icon="question"
                              data-confirm-btn="<?= $u['activo'] ? 'SÃ­, desactivar' : 'SÃ­, activar' ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                            <button type="submit" class="btn btn-sm btn-<?= $u['activo'] ? 'outline-warning' : 'outline-success' ?>"
                                    title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                <i class="fa-solid fa-<?= $u['activo'] ? 'user-slash' : 'user-check' ?>" aria-hidden="true"></i>
                            </button>
                        </form>
                        <?php if ((int)$u['rol_id'] !== ROL_GOD || Auth::userRolId() === ROL_GOD): ?>
                        <form method="POST" action="<?= url_path('/usuarios/'.$u['id'].'/eliminar') ?>"
                              data-confirm="Se eliminarÃ¡ permanentemente a <strong><?= htmlspecialchars($u['nombre']) ?></strong> de la base de datos."
                              data-confirm-icon="warning"
                              data-confirm-btn="SÃ­, eliminar"
                              data-confirm-double="1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar permanentemente">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

