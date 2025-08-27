<?php
// automations_view.php - Vista independiente de automatizaciones
?>

<!-- Automations View -->
<div id="automations-view" style="display: none;">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <?php if ($is_admin): ?>
                        Todas las Automatizaciones (Admin)
                    <?php else: ?>
                        Automatizaciones y Agentes
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <?php if ($is_admin): ?>
                        Gestiona todos los workflows de n8n del sistema
                    <?php else: ?>
                        Gestiona tus workflows de n8n
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!$is_admin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAutomationModal">
                <i class="fas fa-plus me-2"></i>
                Agregar Automatización
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- Estadísticas para Admin -->
    <div class="stats-grid mb-4">
        <div class="stats-card">
            <div class="stats-header">
                <div class="stats-icon primary">
                    <i class="fas fa-cogs"></i>
                </div>
            </div>
            <div class="stats-value"><?php echo count($automations); ?></div>
            <div class="stats-label">Total Automatizaciones</div>
            <div class="stats-trend">En todo el sistema</div>
        </div>

        <div class="stats-card">
            <div class="stats-header">
                <div class="stats-icon success">
                    <i class="fas fa-play"></i>
                </div>
            </div>
            <div class="stats-value"><?php echo count(array_filter($automations, function($a) { return $a['is_active']; })); ?></div>
            <div class="stats-label">Activas</div>
            <div class="stats-trend">Funcionando ahora</div>
        </div>

        <div class="stats-card">
            <div class="stats-header">
                <div class="stats-icon warning">
                    <i class="fas fa-pause"></i>
                </div>
            </div>
            <div class="stats-value"><?php echo count(array_filter($automations, function($a) { return !$a['is_active']; })); ?></div>
            <div class="stats-label">Inactivas</div>
            <div class="stats-trend">Pausadas o detenidas</div>
        </div>

        <div class="stats-card">
            <div class="stats-header">
                <div class="stats-icon info">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stats-value"><?php echo count(array_unique(array_column($automations, 'user_id'))); ?></div>
            <div class="stats-label">Usuarios Activos</div>
            <div class="stats-trend">Con automatizaciones</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($automations)): ?>
        <div class="automation-card text-center py-5">
            <i class="fas fa-robot fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">
                <?php if ($is_admin): ?>
                    No hay automatizaciones en el sistema
                <?php else: ?>
                    No tienes automatizaciones aún
                <?php endif; ?>
            </h5>
            <p class="text-muted">
                <?php if ($is_admin): ?>
                    Los usuarios aún no han creado automatizaciones
                <?php else: ?>
                    Comienza agregando tu primera automatización
                <?php endif; ?>
            </p>
            <?php if (!$is_admin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAutomationModal">
                <i class="fas fa-plus me-2"></i>
                Agregar Primera Automatización
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Tabla de automatizaciones para admin -->
        <?php if ($is_admin): ?>
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Lista de Automatizaciones</h3>
                <p class="card-subtitle">Todas las automatizaciones del sistema</p>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table id="automationsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Automatización</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th>Información</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($automations as $automation): ?>
                            <tr>
                                <td>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($automation['name']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-key me-1"></i>
                                            ID: <?php echo htmlspecialchars($automation['automation_id']); ?>
                                        </small>
                                        <?php if (!empty($automation['purchase_id'])): ?>
                                            <br><span class="badge bg-info">Marketplace</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon primary me-2" style="width: 32px; height: 32px; font-size: 12px;">
                                            <?php echo strtoupper(substr($automation['username'] ?? 'U', 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($automation['username'] ?? 'Usuario desconocido'); ?></div>
                                            <?php if (!empty($automation['full_name'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($automation['full_name']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($automation['company'])): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-building me-1"></i>
                                                    <?php echo htmlspecialchars($automation['company']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $automation['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $automation['is_active'] ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Creada: <?php echo date('d/m/Y H:i', strtotime($automation['created_at'])); ?>
                                    </small>
                                    <?php if (!empty($automation['updated_at'])): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-sync me-1"></i>
                                            Actualizada: <?php echo date('d/m/Y H:i', strtotime($automation['updated_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="viewAutomationDetails(<?php echo $automation['id']; ?>)" 
                                                title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                       
                                        <?php if ($automation['is_active']): ?>
                                            <button class="btn btn-outline-warning btn-sm" 
                                                    onclick="adminToggleAutomation(<?php echo $automation['id']; ?>, false)" 
                                                    title="Pausar">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-success btn-sm" 
                                                    onclick="adminToggleAutomation(<?php echo $automation['id']; ?>, true)" 
                                                    title="Activar">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Vista de tarjetas para usuarios normales -->
        <?php foreach ($automations as $automation): ?>
            <div class="automation-card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-1"><?php echo htmlspecialchars($automation['name']); ?></h5>
                        <p class="text-muted mb-0">
                            <i class="fas fa-key me-2"></i>
                            ID: <?php echo htmlspecialchars($automation['automation_id']); ?>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Creada: <?php echo date('d/m/Y H:i', strtotime($automation['created_at'])); ?>
                            <?php if (!empty($automation['purchase_id'])): ?>
                                <span class="badge bg-info ms-2">Marketplace</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="col-md-4">
                        <span class="status-badge <?php echo $automation['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $automation['is_active'] ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </div>
                    <div class="col-md-2 text-end">
                        <label class="switch">
                            <input type="checkbox" 
                                   class="automation-toggle" 
                                   data-id="<?php echo $automation['id']; ?>" 
                                   data-automation-id="<?php echo $automation['automation_id']; ?>"
                                   <?php echo $automation['is_active'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>