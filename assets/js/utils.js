/**
 * utils.js - Funciones de utilidad comunes para todo el dashboard
 */

// Funciones de notificación
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

function showInfoNotification(message) {
    showNotification(message, 'info');
}

// Función auxiliar para escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Función para validar IDs de automatización
function validateAutomationId(id) {
    const regex = /^[a-z0-9\-_]+$/;
    return regex.test(id.toLowerCase());
}

// Función para formatear fechas
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Función para formatear precios
function formatPrice(price) {
    return price > 0 ? '$' + parseFloat(price).toFixed(2) : 'Gratis';
}

// Función para debounce (útil para búsquedas)
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Función para mostrar/ocultar elementos con animación
function toggleElementWithAnimation(element, show = true) {
    if (show) {
        element.style.display = 'block';
        element.classList.add('fade-in');
        setTimeout(() => {
            element.classList.remove('fade-in');
        }, 300);
    } else {
        element.classList.add('fade-out');
        setTimeout(() => {
            element.style.display = 'none';
            element.classList.remove('fade-out');
        }, 300);
    }
}

// Función para realizar peticiones HTTP con manejo de errores
async function makeRequest(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        return { success: true, data };
    } catch (error) {
        console.error('Request failed:', error);
        return { success: false, error: error.message };
    }
}

// Función para copiar texto al portapapeles
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showSuccessNotification('Copiado al portapapeles');
        return true;
    } catch (error) {
        console.error('Error copying to clipboard:', error);
        showErrorNotification('Error al copiar al portapapeles');
        return false;
    }
}

// Función para validar formularios
function validateForm(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredFields.forEach(field => {
        const value = field.value.trim();
        
        if (!value) {
            field.classList.add('is-invalid');
            isValid = false;
            
            if (!firstInvalidField) {
                firstInvalidField = field;
            }
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
    });

    if (!isValid && firstInvalidField) {
        firstInvalidField.focus();
        showErrorNotification('Por favor complete todos los campos obligatorios');
    }

    return isValid;
}

// Función para limpiar formularios
function clearForm(formElement) {
    const inputs = formElement.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.value = '';
        input.classList.remove('is-valid', 'is-invalid');
    });
}

// Función para mostrar indicador de carga en botones
function setButtonLoading(button, loading = true, loadingText = 'Cargando...') {
    if (loading) {
        button.setAttribute('data-original-content', button.innerHTML);
        button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${loadingText}`;
        button.disabled = true;
    } else {
        const originalContent = button.getAttribute('data-original-content');
        if (originalContent) {
            button.innerHTML = originalContent;
            button.removeAttribute('data-original-content');
        }
        button.disabled = false;
    }
}

// Función para confirmar acciones peligrosas
function confirmAction(message, confirmCallback, cancelCallback = null) {
    if (confirm(message)) {
        if (typeof confirmCallback === 'function') {
            confirmCallback();
        }
        return true;
    } else {
        if (typeof cancelCallback === 'function') {
            cancelCallback();
        }
        return false;
    }
}

// Función para generar IDs únicos
function generateUniqueId(prefix = 'id') {
    return prefix + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Función para truncar texto
function truncateText(text, maxLength = 100, suffix = '...') {
    if (text.length <= maxLength) {
        return text;
    }
    return text.substring(0, maxLength - suffix.length) + suffix;
}

// Función para detectar si el dispositivo es móvil
function isMobileDevice() {
    return window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Función para animar números (contar hacia arriba)
function animateNumber(element, finalNumber, duration = 1000) {
    const startNumber = 0;
    const increment = finalNumber / (duration / 16); // 60fps
    let currentNumber = startNumber;

    const timer = setInterval(() => {
        currentNumber += increment;
        if (currentNumber >= finalNumber) {
            currentNumber = finalNumber;
            clearInterval(timer);
        }
        element.textContent = Math.floor(currentNumber);
    }, 16);
}

// Función para lazy loading de imágenes
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Función para manejar errores de red
function handleNetworkError(error) {
    if (!navigator.onLine) {
        showErrorNotification('Sin conexión a internet. Verifica tu conexión.');
        return;
    }
    
    if (error.message.includes('404')) {
        showErrorNotification('Recurso no encontrado');
    } else if (error.message.includes('403')) {
        showErrorNotification('Acceso denegado');
    } else if (error.message.includes('500')) {
        showErrorNotification('Error del servidor. Intenta de nuevo más tarde.');
    } else {
        showErrorNotification('Error de conexión. Intenta de nuevo.');
    }
}

// Exportar funciones para uso global
window.Utils = {
    showNotification,
    showSuccessNotification,
    showErrorNotification,
    showWarningNotification,
    showInfoNotification,
    escapeHtml,
    validateAutomationId,
    formatDate,
    formatPrice,
    debounce,
    toggleElementWithAnimation,
    makeRequest,
    copyToClipboard,
    validateForm,
    clearForm,
    setButtonLoading,
    confirmAction,
    generateUniqueId,
    truncateText,
    isMobileDevice,
    animateNumber,
    initLazyLoading,
    handleNetworkError
};

console.log('Utils.js cargado correctamente');