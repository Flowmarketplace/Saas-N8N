/**
 * admin-automations.js - Funciones específicas de administrador para automatizaciones
 * Este archivo solo se carga para usuarios administradores
 */

/**
 * Inicializar funciones específicas de administrador
 */
function initializeAdminFunctions() {
    console.log('Inicializando funciones de administrador para automatizaciones');
    
    // Funciones específicas de admin se inicializan aquí
    initializeAutomationsTable();
    
    // Agregar event listeners específicos de admin
    setupAdminEventListeners();
}

/**
 * Configurar event listeners específicos de admin
 */
function setupAdminEventListeners() {
    // Event listeners para botones de acción admin
    document.addEventListener('click', function(e) {
        const target = e.target;
        
        // Manejar clicks en botones de ver detalles
        if (target.closest('[onclick*="viewAutomationDetails"]')) {
            e.preventDefault();
            const button = target.closest('button');
            const match = button.getAttribute('onclick').match(/viewAutomationDetails\((\d+)\)/);
            if (match) {
                viewAutomationDetails(parseInt(match[1]));
            }
        }
        
        // Manejar clicks en botones de toggle admin
        if (target.closest('[onclick*="adminToggleAutomation"]')) {
            e.preventDefault();
            const button = target.closest('button');
            const onclickAttr = button.getAttribute('onclick');
            const match = onclickAttr.match(/adminToggleAutomation\((\d+),\s*(true|false)\)/);
            if (match) {
                adminToggleAutomation(parseInt(match[1]), match[2] === 'true');
            }
        }
    });
}

/**
 * Inicializar DataTable para automatizaciones (solo admin)
 */
function initializeAutomationsTable() {
    if (!$.fn.DataTable.isDataTable('#automationsTable')) {
        $('#automationsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json"
            },
            "pageLength": 25,
            "order": [[3, "desc"]], // Ordenar por fecha de creación descendente
            "columnDefs": [
                { "orderable": false, "targets": 4 } // No ordenar columna de acciones
            ],
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-1"></i> Exportar Excel',
                    className: 'btn btn-success btn-sm'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf me-1"></i> Exportar PDF',
                    className: 'btn btn-danger btn-sm'
                }
            ],
            "initComplete": function() {
                console.log('Tabla de automatizaciones inicializada');
            }
        });
    }
}

/**
 * Ver detalles de automatización
 */
function viewAutomationDetails(automationId) {
    // Mostrar loading
    const button = document.querySelector(`[onclick*="viewAutomationDetails(${automationId})"]`);
    if (button) {
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        // Restaurar botón después de timeout
        setTimeout(() => {
            if (button.innerHTML.includes('fa-spinner')) {
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        }, 10000);
    }

    fetch(`get_automation_details.php?id=${automationId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showAutomationDetailsModal(data.automation);
            } else {
                throw new Error(data.message || 'Error desconocido');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorNotification('Error al obtener detalles: ' + error.message);
        })
        .finally(() => {
            // Restaurar botón
            if (button) {
                button.innerHTML = '<i class="fas fa-eye"></i>';
                button.disabled = false;
            }
        });
}

/**
 * Alternar estado de automatización desde admin
 */
function adminToggleAutomation(automationId, isActive) {
    const action = isActive ? 'activar' : 'pausar';
    
    if (!confirm(`¿Estás seguro de que quieres ${action} esta automatización?`)) {
        return;
    }
    
    // Mostrar loading en el botón
    const button = document.querySelector(`[onclick*="adminToggleAutomation(${automationId}, ${isActive})"]`);
    if (button) {
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        // Restaurar botón después de timeout
        setTimeout(() => {
            if (button.innerHTML.includes('fa-spinner')) {
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        }, 10000);
    }

    fetch('admin_toggle_automation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            automation_id: automationId,
            is_active: isActive
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showSuccessNotification(data.message || `Automatización ${action}da correctamente`);
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Error desconocido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorNotification('Error: ' + error.message);
    })
    .finally(() => {
        // Restaurar botón
        if (button) {
            const originalContent = isActive ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    });
}

/**
 * Mostrar modal de detalles de automatización
 */
function showAutomationDetailsModal(automation) {
    // Crear modal dinámicamente si no existe
    let modal = document.getElementById('automationDetailsModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'automationDetailsModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-cog me-2"></i>
                            Detalles de Automatización
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="automationDetailsBody">
                        <!-- Contenido se carga dinámicamente -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // Llenar contenido del modal
    const modalBody = document.getElementById('automationDetailsBody');
    modalBody.innerHTML = createAutomationDetailsContent(automation);

    // Mostrar modal con animación
    try {
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    } catch (error) {
        console.error('Error showing modal:', error);
        showErrorNotification('Error al mostrar el modal de detalles');
    }
}

/**
 * Crear contenido HTML para el modal de detalles
 */
function createAutomationDetailsContent(automation) {
    return `
        <div class="row">
            <div class="col-md-8">
                <h6><i class="fas fa-info-circle me-2"></i>Información de la Automatización</h6>
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <tr>
                                <td><strong>Nombre</strong></td>
                                <td>${escapeHtml(automation.name)}</td>
                            </tr>
                            ${automation.description ? `
                            <tr>
                                <td><strong>Descripción</strong></td>
                                <td>
                                    <div style="max-width: 400px; word-wrap: break-word;">
                                        ${escapeHtml(automation.description)}
                                    </div>
                                </td>
                            </tr>
                            ` : ''}
                            <tr>
                                <td><strong>ID de Automatización</strong></td>
                                <td><code>${escapeHtml(automation.automation_id)}</code></td>
                            </tr>
                            <tr>
                                <td><strong>Estado</strong></td>
                                <td>
                                    <span class="status-badge ${automation.is_active == 1 ? 'active' : 'inactive'}">
                                        <i class="fas fa-${automation.is_active == 1 ? 'play' : 'pause'} me-1"></i>
                                        ${automation.is_active == 1 ? 'Activa' : 'Inactiva'}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Fecha de Creación</strong></td>
                                <td>
                                    <i class="fas fa-calendar me-2"></i>
                                    ${automation.formatted_created_at || new Date(automation.created_at).toLocaleString()}
                                </td>
                            </tr>
                            ${automation.formatted_updated_at ? `
                            <tr>
                                <td><strong>Última Actualización</strong></td>
                                <td>
                                    <i class="fas fa-sync me-2"></i>
                                    ${automation.formatted_updated_at}
                                </td>
                            </tr>
                            ` : ''}
                            ${automation.is_from_marketplace || automation.purchase_id ? `
                            <tr>
                                <td><strong>Origen</strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="fas fa-store me-1"></i>Marketplace
                                    </span>
                                    ${automation.product_title ? `<br><small class="text-muted mt-1">${escapeHtml(automation.product_title)}</small>` : ''}
                                </td>
                            </tr>
                            ` : `
                            <tr>
                                <td><strong>Origen</strong></td>
                                <td>
                                    <span class="badge" style="background: var(--success-gradient); color: white;">
                                        <i class="fas fa-user me-1"></i>Creación Manual
                                    </span>
                                </td>
                            </tr>
                            `}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <h6><i class="fas fa-user me-2"></i>Información del Usuario</h6>
                <div class="card" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stats-icon primary me-3" style="width: 48px; height: 48px; font-size: 1rem;">
                                ${automation.username ? automation.username.substring(0, 2).toUpperCase() : 'U'}
                            </div>
                            <div>
                                <h6 class="mb-0">${escapeHtml(automation.username || 'Usuario desconocido')}</h6>
                                ${automation.full_name ? `<small class="text-muted">${escapeHtml(automation.full_name)}</small>` : ''}
                            </div>
                        </div>
                        
                        ${automation.email ? `
                        <p class="mb-2">
                            <i class="fas fa-envelope"></i>
                            ${escapeHtml(automation.email)}
                        </p>
                        ` : ''}
                        
                        ${automation.company ? `
                        <p class="mb-2">
                            <i class="fas fa-building"></i>
                            ${escapeHtml(automation.company)}
                        </p>
                        ` : ''}
                        
                        ${automation.user_since ? `
                        <p class="mb-3">
                            <i class="fas fa-calendar-plus"></i>
                            Usuario desde: ${automation.user_since}
                        </p>
                        ` : ''}
                        
                        <hr>
                        <button class="btn btn-outline-info btn-sm w-100" onclick="impersonateUser(${automation.user_id}, '${escapeHtml(automation.username)}')">
                            <i class="fas fa-user-secret me-1"></i>
                            Ver como usuario
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        ${automation.recent_activities && automation.recent_activities.length > 0 ? `
        <hr style="margin: 2rem 0;">
        <h6><i class="fas fa-history me-2"></i>Actividad Reciente</h6>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 180px;"><i class="fas fa-clock me-1"></i>Fecha y Hora</th>
                        <th><i class="fas fa-list me-1"></i>Descripción de la Actividad</th>
                    </tr>
                </thead>
                <tbody>
                    ${automation.recent_activities.map(activity => `
                        <tr>
                            <td>
                                <small>${new Date(activity.created_at).toLocaleString('es-ES', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}</small>
                            </td>
                            <td>
                                <small>${escapeHtml(activity.description)}</small>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
        ` : `
        <hr style="margin: 2rem 0;">
        <div class="no-activities">
            <i class="fas fa-history fa-2x mb-3" style="opacity: 0.3;"></i>
            <p>No hay actividad reciente registrada para esta automatización</p>
        </div>
        `}
    `;
}

/**
 * Impersonar usuario
 */
function impersonateUser(userId, username) {
    if (!confirm(`⚠️ ACCIÓN DE ADMINISTRADOR ⚠️\n\nVas a ver el dashboard como el usuario "${username}".\nPodrás realizar acciones en su nombre.\n\n¿Estás seguro de continuar?`)) {
        return;
    }
    
    fetch('admin_impersonate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: userId,
            username: username
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showSuccessNotification(`Ahora estás viendo como ${username}`);
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Error desconocido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorNotification('Error al impersonar usuario: ' + error.message);
    });
}

/**
 * Terminar impersonación
 */
function stopImpersonation() {
    if (!confirm('¿Estás seguro de que quieres volver al modo administrador?')) {
        return;
    }
    
    fetch('stop_impersonation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showSuccessNotification('Has vuelto al modo administrador');
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.message || 'Error desconocido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorNotification('Error al terminar la impersonación: ' + error.message);
    });
}

/**
 * Exportar estadísticas de automatizaciones
 */
function exportAutomationsStats() {
    const currentDate = new Date().toISOString().split('T')[0];
    const filename = `automatizaciones_${currentDate}.xlsx`;
    
    showInfoNotification('Preparando exportación...');
    
    fetch('export_automations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            format: 'xlsx',
            include_user_data: true
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showSuccessNotification('Archivo exportado correctamente');
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorNotification('Error al exportar: ' + error.message);
    });
}

// Funciones de utilidad específicas para admin
function refreshAutomationsData() {
    location.reload();
}

function bulkToggleAutomations(isActive) {
    const action = isActive ? 'activar' : 'pausar';
    
    if (!confirm(`¿Estás seguro de que quieres ${action} TODAS las automatizaciones del sistema?`)) {
        return;
    }
    
    showInfoNotification(`${action === 'activar' ? 'Activando' : 'Pausando'} todas las automatizaciones...`);
    
    fetch('admin_bulk_toggle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: isActive ? 'activate_all' : 'pause_all'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showSuccessNotification(data.message || `Todas las automatizaciones han sido ${action}das`);
            setTimeout(() => location.reload(), 2000);
        } else {
            throw new Error(data.message || 'Error desconocido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorNotification('Error en la operación masiva: ' + error.message);
    });
}

// Exportar funciones para uso global
window.AdminAutomationsModule = {
    viewAutomationDetails,
    adminToggleAutomation,
    impersonateUser,
    stopImpersonation,
    exportAutomationsStats,
    bulkToggleAutomations,
    initializeAdminFunctions
};

console.log('Archivo admin-automations.js cargado');