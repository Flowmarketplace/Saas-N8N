<?php
// views/modals.php - Todos los modales del dashboard
?>

<!-- Modal para agregar automatización -->
<div class="modal fade" id="addAutomationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    Agregar Nueva Automatización
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addAutomationForm">
                    <div class="mb-3">
                        <label for="automationName" class="form-label">
                            <i class="fas fa-tag me-1"></i>
                            Nombre de la Automatización
                        </label>
                        <input type="text" class="form-control" id="automationName" name="name" required 
                               placeholder="ej: Customer Support Agent">
                        <div class="form-text">Un nombre descriptivo para identificar tu automatización</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="automationDescription" class="form-label">
                            <i class="fas fa-file-text me-1"></i>
                            Descripción
                        </label>
                        <textarea class="form-control" id="automationDescription" name="description" rows="3" 
                                  placeholder="Describe qué hace esta automatización y para qué la usas..."></textarea>
                        <div class="form-text">Una breve descripción de la funcionalidad de esta automatización</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="automationId" class="form-label">
                            <i class="fas fa-key me-1"></i>
                            ID de Automatización
                        </label>
                        <input type="text" class="form-control" id="automationId" name="automation_id" required 
                               placeholder="ej: customer-support-whatsapp">
                        <div class="form-text">
                            <strong>Importante:</strong> Este ID será usado para las llamadas a la API de N8N. 
                            Usa solo letras minúsculas, números y guiones.
                        </div>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>
                            Recuerda configurar este mismo ID en tu workflow de N8N para que funcione correctamente.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="addAutomation()">
                    <i class="fas fa-save me-2"></i>
                    Crear Automatización
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación para Usuarios -->
<div class="modal fade" id="userConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmar Acción
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="userConfirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="userConfirmButton" onclick="executeUserAction()">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Activar Compra -->
<div class="modal fade" id="activatePurchaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-rocket me-2"></i>
                    Activar Automatización
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Para activar esta automatización, debes asignarle un ID único que usarás en N8N:</p>
                <form id="activatePurchaseForm">
                    <input type="hidden" id="purchaseId" name="purchase_id">
                    <div class="mb-3">
                        <label for="activationAutomationId" class="form-label">ID de Automatización</label>
                        <input type="text" class="form-control" id="activationAutomationId" name="automation_id" required 
                               placeholder="ej: mi-automatizacion-whatsapp">
                        <div class="form-text">Este ID será usado para identificar tu workflow en N8N</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="activatePurchase()">
                    <i class="fas fa-check me-2"></i>
                    Activar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para detalles del producto del marketplace -->
<div class="modal fade product-detail-modal" id="productDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-robot me-2"></i>
                    <span id="modalProductTitle">Detalles del Producto</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Header del producto -->
                <div class="product-header">
                    <div class="product-title-section">
                        <h3 id="detailTitle">Título del Producto</h3>
                        <p class="product-subtitle" id="detailSubtitle">Descripción breve del producto</p>
                        <div class="status-badge running">
                            <i class="fas fa-play"></i>
                            <span>Running</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="h4 mb-0" style="color: #4facfe;" id="detailPrice">$49.99</div>
                        <small class="text-muted" id="detailSales">23 ventas</small>
                    </div>
                </div>

                <!-- Herramientas conectadas -->
                <div class="tools-section">
                    <div class="tools-title">
                        <i class="fas fa-plug"></i>
                        Herramientas Conectadas
                    </div>
                    <div class="tools-grid" id="toolsGrid">
                        <!-- Se llenarán dinámicamente -->
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="stats-section">
                    <div class="stat-item">
                        <div class="stat-number" id="statMessages">145</div>
                        <div class="stat-label">Mensajes Procesados</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">2 minutes ago</div>
                        <div class="stat-label">Última Actividad</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">99.9%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                </div>

                <!-- Contenido principal -->
                <div class="content-sections">
                    <!-- Demo explicativo -->
                    <div class="demo-section">
                        <h4>
                            <i class="fas fa-play-circle me-2"></i>
                            Demo Explicativo
                        </h4>
                        <video class="demo-video" controls id="demoVideo">
                            <source src="" type="video/mp4">
                            Tu navegador no soporta la reproducción de videos.
                        </video>
                    </div>

                    <!-- Ejemplo en vivo -->
                    <div class="example-section">
                        <h4>
                            <i class="fas fa-mobile-alt me-2"></i>
                            Ejemplo en Vivo
                        </h4>
                        <div style="width: 100%"> 
                            <div class="phone-mockup-detail">
                                <div class="phone-screen-detail">
                                    <video class="phone-video-detail" autoplay muted loop id="phoneVideo">
                                        <source src="" type="video/mp4">
                                    </video>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="action-buttons">
                    <button class="btn btn-primary-large boton_modal" onclick="buyProduct2()">
                        <i class="fas fa-shopping-cart me-2"></i>
                        <span id="buyButtonText">Comprar Ahora</span>
                    </button>
                    <button class="btn btn-secondary-large" onclick="addToWishlist()">
                        <i class="fas fa-heart me-2"></i>
                        Agregar a Favoritos
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de video para marketplace -->
<div class="modal fade video-modal" id="videoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalTitle">Video Demo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <video class="w-100" controls id="modalVideo">
                    <source id="modalVideoSource" src="" type="video/mp4">
                    Tu navegador no soporta la reproducción de videos.
                </video>
            </div>
        </div>
    </div>
</div>