/**
 * automations.js - Funciones para la gestión de automatizaciones
 */

// Variables globales para automatizaciones
let automationsInitialized = false;

/**
 * Inicializar funcionalidad de automatizaciones
 */
function initializeAutomationsModule() {
    if (automationsInitialized) return;
    
    // Inicializar toggles de automatización
    initializeAutomationToggles();
    
    // Inicializar funcionalidad específica de admin
    if (window.isAdmin) {
        initializeAdminFunctions();
    }
    
    automationsInitialized = true;
    console.log('Módulo de automatizaciones inicializado');
}

/**
 * Mostrar vista de automatizaciones
 */
function showAutomations() {
    // Ocultar todas las vistas
    document.getElementById('dashboard-view').style.display = 'none';
    document.getElementById('automations-view').style.display = 'block';
    document.getElementById('marketplace-view').style.display = 'none';
    
    if (window.isAdmin && document.getElementById('users-view')) {
        document.getElementById('users-view').style.display = 'none';
    }
    
    // Actualizar navegación activa
    updateActiveNavigation('[href="#automations"]');
    
    // Inicializar módulo si no se ha hecho
    initializeAutomationsModule();
    
    // Inicializar tabla si es admin
    if (window.isAdmin) {
        setTimeout(() => {
            initializeAutomationsTable();
        }, 100);
    }
}

/**
 * Actualizar navegación activa
 */
function updateActiveNavigation(selector) {
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    const activeLink = document.querySelector(selector);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}

/**
 * Inicializar toggles de automatización
 */
function initializeAutomationToggles() {
    document.querySelectorAll('.automation-toggle').forEach(toggle => {
        // Remover event listeners existentes
        const newToggle = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(newToggle, toggle);
        
        // Agregar nuevo event listener
        newToggle.addEventListener('change', function() {
            handleAutomationToggle(this);
        });
    });
}

/**
 * Manejar toggle de automatización
 */
function handleAutomationToggle(toggle) {
    const automationId = toggle.dataset.automationId;
    const isActive = toggle.checked;
    const id = toggle.dataset.id;
    
    // Deshabilitar toggle durante la petición
    toggle.disabled = true;
    
    fetch('toggle_automation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            automation_id: automationId,
            is_active: isActive
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessNotification(data.message || 'Estado actualizado correctamente');
            // Recargar después de un breve delay
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            // Revertir el toggle
            toggle.checked = !toggle.checked;
            showErrorNotification('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revertir el toggle
        toggle.checked = !toggle.checked;
        showErrorNotification('Error al cambiar el estado de la automatización');
    })
    .finally(() => {
        toggle.disabled = false;
    });
}

/**
 * Función para agregar automatización
 */
function addAutomation() {
    const form = document.getElementById('addAutomationForm');
    if (!form) {
        showErrorNotification('Formulario no encontrado');
        return;
    }

    const formData = new FormData(form);
    
    // Validaciones del lado cliente
    const name = formData.get('name').trim();
    const automationId = formData.get('automation_id').trim();
    
    if (!name) {
        showErrorNotification('El nombre es obligatorio');
        return;
    }
    
    if (!automationId) {
        showErrorNotification('El ID de automatización es obligatorio');
        return;
    }
    
    // Validar formato del automation_id
    const automationIdRegex = /^[a-z0-9\-_]+$/;
    if (!automationIdRegex.test(automationId.toLowerCase())) {
        showErrorNotification('El ID de automatización solo puede contener letras minúsculas, números, guiones (-) y guiones bajos (_)');
        return;
    }
    
    // Mostrar loading en el botón
    const submitButton = document.querySelector('#addAutomationModal .btn-primary');
    if (!submitButton) {
        showErrorNotification('Botón de envío no encontrado');
        return;
    }
    
    const originalButtonText = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando...';
    submitButton.disabled = true;
    
    fetch('add_automation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP ${response.status}: ${text}`);
            });
        }
        
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Respuesta inválida del servidor');
            }
        });
    })
    .then(data => {
        if (data.success) {
            // Mostrar éxito
            submitButton.innerHTML = '<i class="fas fa-check me-2"></i>¡Creada!';
            submitButton.style.background = 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)';
            
            showSuccessNotification(data.message);
            
            // Cerrar modal y recargar
            setTimeout(() => {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('addAutomationModal'));
                if (modalInstance) {
                    modalInstance.hide();
                }
                form.reset();
                location.reload();
            }, 1500);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorNotification('Error: ' + error.message);
    })
    .finally(() => {
        // Restaurar botón
        submitButton.innerHTML = originalButtonText;
        submitButton.disabled = false;
        submitButton.style.background = '';
    });
}

/**
 * Funciones de acciones rápidas
 */
function startAllAgents() {
    if (!confirm('¿Estás seguro de que quieres activar todas las automatizaciones?')) {
        return;
    }
    
    performBulkAction('start_all', 'Activando todas las automatizaciones...');
}

function pauseAllAgents() {
    if (!confirm('¿Estás seguro de que quieres pausar todas las automatizaciones?')) {
        return;
    }
    
    performBulkAction('pause_all', 'Pausando todas las automatizaciones...');
}

/**
 * Realizar acción en lote
 */
function performBulkAction(action, loadingMessage) {
    showInfoNotification(loadingMessage);
    
    fetch('action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessNotification(data.message);
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showErrorNotification('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorNotification('Error al procesar la acción');
    });
}

/**
 * Funciones auxiliares para notificaciones
 */
function showInfoNotification(message) {
    showNotification(message, 'info');
}

// Verificar si las funciones de notificación existen, si no, crearlas
if (typeof showNotification !== 'function') {
    function showNotification(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const icon = type === 'success' ? 'fa-check-circle' : 
                     type === 'error' ? 'fa-exclamation-triangle' : 
                     type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        // Crear notificación
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        notification.innerHTML = `
            <i class="fas ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    function showSuccessNotification(message) {
        showNotification(message, 'success');
    }
    
    function showErrorNotification(message) {
        showNotification(message, 'error');
    }
    
    function showWarningNotification(message) {
        showNotification(message, 'warning');
    }
}

// Función auxiliar para escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // La inicialización se hace cuando se muestra la vista de automatizaciones
    console.log('Archivo automations.js cargado');
});

// Exportar funciones para uso global
window.AutomationsModule = {
    showAutomations,
    addAutomation,
    startAllAgents,
    pauseAllAgents,
    initializeAutomationsModule
};