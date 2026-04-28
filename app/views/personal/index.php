<?php $pageTitle = 'Personal'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 class="h2 fw-bold text-institucional mb-0">
        <i class="fa-solid fa-id-badge me-2" aria-hidden="true"></i>Personal
    </h1>
    <?php if (Auth::hasRole([ROL_GOD, ROL_ADMIN])): ?>
    <a href="<?= url_path('/personal/crear') ?>" class="btn btn-institucional btn-lg">
        <i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Agregar persona
    </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= url_path('/personal') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="f_nombre" class="form-label fw-semibold">Nombre</label>
                <input type="text" id="f_nombre" name="nombre" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['nombre']) ?>"
                       placeholder="Buscar por nombre">
            </div>
            <div class="col-md-2">
                <label for="f_num" class="form-label fw-semibold">NÂ° empleado</label>
                <input type="text" id="f_num" name="num" class="form-control form-control-lg"
                       value="<?= htmlspecialchars($filtros['num']) ?>"
                       placeholder="Ej. 92300">
            </div>
            <div class="col-md-3">
                <label for="f_tipo" class="form-label fw-semibold">Tipo</label>
                <select id="f_tipo" name="tipo" class="form-select form-select-lg">
                    <option value="">Todos</option>
                    <option value="SINDICALIZADO" <?= $filtros['tipo'] === 'SINDICALIZADO' ? 'selected' : '' ?>>Sindicalizado</option>
                    <option value="CONFIANZA"     <?= $filtros['tipo'] === 'CONFIANZA'     ? 'selected' : '' ?>>Confianza</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="f_activo" class="form-label fw-semibold">Estado</label>
                <select id="f_activo" name="activo" class="form-select form-select-lg">
                    <option value="">Todos</option>
                    <option value="1" <?= ($filtros['activo'] === '1') ? 'selected' : '' ?>>Activos</option>
                    <option value="0" <?= ($filtros['activo'] === '0') ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-institucional btn-lg">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <p class="mb-0 text-muted">
            Personas encontradas: <strong class="text-dark"><?= count($personal) ?></strong>
        </p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-lg mb-0" aria-label="Lista de personal">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">NÂ° empleado</th>
                    <th scope="col">Nombre completo</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">CategorÃ­a</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="pe-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($personal)): ?>
                <tr><td colspan="6" class="text-center p-4 text-muted">No hay personal que coincida con los filtros.</td></tr>
            <?php endif; ?>
            <?php foreach ($personal as $p): ?>
                <tr class="<?= !$p['activo'] ? 'table-secondary opacity-75' : '' ?>">
                    <td class="ps-4 fw-semibold"><?= htmlspecialchars($p['numero_empleado'] ?? 'â€”') ?></td>
                    <td><?= htmlspecialchars($p['nombre_completo']) ?></td>
                    <td>
                        <span class="badge bg-<?= $p['tipo_clave'] === 'SINDICALIZADO' ? 'primary' : 'warning text-dark' ?>">
                            <?= htmlspecialchars($p['tipo_nombre']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($p['categoria'] ?? 'â€”') ?></td>
                    <td>
                        <?php if ($p['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4 text-center">
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="<?= url_path('/personal/'.(int)$p['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php if (Auth::hasRole([ROL_GOD, ROL_ADMIN])): ?>
                            <a href="<?= url_path('/personal/'.(int)$p['id'].'/editar') ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

