<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración del Marketplace - n8n CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark-bg: #1a1d29;
            --sidebar-bg: #242738;
            --card-bg: #2d3148;
            --text-primary: #ffffff;
            --text-secondary: #8b8fa3;
            --border-color: #3a3f5c;
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .main-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.5rem;
        }

        .content-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-body {
            padding: 1.5rem;
        }

        .product-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .form-control {
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            padding: 12px 15px;
        }

        .form-control:focus {
            background: var(--dark-bg);
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            color: var(--text-primary);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }

        .video-preview {
            max-width: 200px;
            border-radius: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stats-icon.primary { background: var(--primary-gradient); }
        .stats-icon.success { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stats-icon.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .upload-area.dragover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div style="padding: 0 2rem 2rem; border-bottom: 1px solid var(--border-color); margin-bottom: 2rem;">
                <h4 style="color: var(--text-primary); font-weight: 700; margin: 0;">
                    <i class="fas fa-store me-2"></i> Marketplace Admin
                </h4>
                <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0.5rem 0 0;">
                    Gestión de productos
                </p>
            </div>
            
            <div style="margin-bottom: 2rem;">
                <div style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 0 2rem 1rem;">
                    Navegación
                </div>
                <div>
                    <a href="dashboard.php" style="display: flex; align-items: center; padding: 0.875rem 2rem; color: var(--text-secondary); text-decoration: none;">
                        <i class="fas fa-arrow-left" style="width: 20px; margin-right: 1rem;"></i>
                        Volver al Dashboard
                    </a>
                    <a href="#" style="display: flex; align-items: center; padding: 0.875rem 2rem; color: var(--text-primary); background: var(--primary-gradient); text-decoration: none;">
                        <i class="fas fa-box" style="width: 20px; margin-right: 1rem;"></i>
                        Productos
                    </a>
                    <a href="#" onclick="showAnalytics()" style="display: flex; align-items: center; padding: 0.875rem 2rem; color: var(--text-secondary); text-decoration: none;">
                        <i class="fas fa-chart-bar" style="width: 20px; margin-right: 1rem;"></i>
                        Analíticas
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Administración del Marketplace</h1>
                        <p style="color: var(--text-secondary); margin: 0;">Gestiona productos y automatizaciones</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus me-2"></i>
                        Agregar Producto
                    </button>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="fas fa-box"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 700; margin: 0 0 0.25rem;" id="totalProducts">5</div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Productos</div>
                </div>

                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 700; margin: 0 0 0.25rem;" id="totalSales">23</div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Ventas Totales</div>
                </div>

                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="fas fa-star"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 700; margin: 0 0 0.25rem;" id="featuredProducts">2</div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Productos Destacados</div>
                </div>
            </div>

            <!-- Lista de Productos -->
            <div class="content-card">
                <div class="card-header">
                    <h3 style="margin: 0 0 0.25rem; font-size: 1.125rem; font-weight: 600;">Productos del Marketplace</h3>
                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0;">Administra las automatizaciones disponibles para venta</p>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div id="productsList">
                        <!-- Los productos se cargarán aquí via JavaScript -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Agregar/Editar Producto -->
   <!-- Modal Agregar/Editar Producto con 2 Videos -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    <span id="modalTitle">Agregar Nuevo Producto</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="productForm" enctype="multipart/form-data">
                    <input type="hidden" id="productId" name="product_id">
                    
                    <!-- Información básica -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="productTitle" class="form-label">Título del Producto</label>
                                <input type="text" class="form-control" id="productTitle" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="productPrice" class="form-label">Precio</label>
                                <input type="number" class="form-control" id="productPrice" name="price" step="0.01" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="productDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="productDescription" name="description" rows="4" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="productCategory" class="form-label">Categoría</label>
                                <select class="form-control" id="productCategory" name="category">
                                    <option value="general">General</option>
                                    <option value="customer_service">Atención al Cliente</option>
                                    <option value="sales">Ventas</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="messaging">Mensajería</option>
                                    <option value="finance">Finanzas</option>
                                    <option value="hr">Recursos Humanos</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="productTags" class="form-label">Etiquetas (separadas por comas)</label>
                                <input type="text" class="form-control" id="productTags" name="tags" placeholder="automation, ai, whatsapp">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="productTools" class="form-label">Herramientas (separadas por comas)</label>
                                <input type="text" class="form-control" id="productTools" name="tools" placeholder="WhatsApp, OpenAI GPT-4, Webhook">
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Videos -->
                    <div class="row">
                        <!-- Video Promocional -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-video me-2"></i>
                                    Video Promocional (Para las cards)
                                </label>
                                <div class="upload-area" id="promoUploadArea" onclick="document.getElementById('promoVideoFile').click()">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: var(--text-secondary);"></i>
                                    <p style="margin: 0; color: var(--text-secondary);">
                                        Video para mostrar en las tarjetas del marketplace
                                    </p>
                                    <small style="color: var(--text-secondary);">
                                        Formatos: MP4, WebM, AVI (Max: 50MB)
                                    </small>
                                </div>
                                <input type="file" id="promoVideoFile" name="promotional_video" accept="video/*" style="display: none;">
                                <div id="promoVideoPreview" style="margin-top: 1rem; display: none;">
                                    <video class="video-preview" style="width: 100%; max-height: 200px;" controls>
                                        <source id="promoVideoSource" src="" type="video/mp4">
                                    </video>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removePromoVideo()">
                                        <i class="fas fa-trash me-1"></i>
                                        Remover Video
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Video Demo -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-mobile-alt me-2"></i>
                                    Video Demo (Para el mockup del celular)
                                </label>
                                <div class="upload-area" id="demoUploadArea" onclick="document.getElementById('demoVideoFile').click()">
                                    <i class="fas fa-mobile-alt fa-3x mb-3" style="color: var(--text-secondary);"></i>
                                    <p style="margin: 0; color: var(--text-secondary);">
                                        Video para mostrar en el mockup del celular
                                    </p>
                                    <small style="color: var(--text-secondary);">
                                        Formatos: MP4, WebM, AVI (Max: 50MB)
                                    </small>
                                </div>
                                <input type="file" id="demoVideoFile" name="demo_video" accept="video/*" style="display: none;">
                                <div id="demoVideoPreview" style="margin-top: 1rem; display: none;">
                                    <video class="video-preview" style="width: 100%; max-height: 200px;" controls>
                                        <source id="demoVideoSource" src="" type="video/mp4">
                                    </video>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeDemoVideo()">
                                        <i class="fas fa-trash me-1"></i>
                                        Remover Video
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuraciones -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                                <label class="form-check-label" for="isActive">
                                    Producto Activo
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="isFeatured" name="featured">
                                <label class="form-check-label" for="isFeatured">
                                    Producto Destacado
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Vista previa del resultado -->
                    <div class="mt-4">
                        <h6><i class="fas fa-eye me-2"></i>Vista Previa</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="preview-card">
                                    <div class="preview-title">Como se verá en el marketplace:</div>
                                    <div class="marketplace-card-preview">
                                        <div class="card-image-preview" id="cardPreview">
                                            <div style="padding: 2rem; text-align: center; color: #999;">
                                                Video promocional aparecerá aquí
                                            </div>
                                        </div>
                                        <div style="padding: 1rem;">
                                            <h6 id="previewTitle">Título del producto</h6>
                                            <p style="font-size: 0.875rem; color: #666;" id="previewDesc">Descripción...</p>
                                            <button class="btn btn-sm btn-primary">Ver Más</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="preview-card">
                                    <div class="preview-title">En el popup detallado:</div>
                                    <div class="phone-mockup-preview">
                                        <div class="phone-screen-preview" id="phonePreview">
                                            <div style="padding: 2rem; text-align: center; color: #999; font-size: 0.875rem;">
                                                Video demo aparecerá aquí
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveProduct()">
                    <i class="fas fa-save me-2"></i>
                    <span id="saveButtonText">Guardar Producto</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.upload-area {
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    background: var(--dark-bg);
}

.upload-area:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.upload-area.dragover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.1);
}

.video-preview {
    border-radius: 8px;
    background: #000;
}

.preview-card {
    background: var(--dark-bg);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid var(--border-color);
}

.preview-title {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
    font-weight: 500;
}

.marketplace-card-preview {
    background: var(--card-bg);
    border-radius: 8px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.card-image-preview {
    height: 120px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.phone-mockup-preview {
    width: 120px;
    height: 200px;
    background: #1a1a1a;
    border-radius: 15px;
    padding: 10px;
    margin: 0 auto;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.phone-screen-preview {
    width: 100%;
    height: 100%;
    border-radius: 10px;
    background: #000;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
let currentEditingId = null;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    setupFileUpload();
    loadProducts();
});

// === SUBIDA DE ARCHIVOS ===
function setupFileUpload() {
    setupVideoUpload('promo', 'promoUploadArea', 'promoVideoFile', 'promoVideoPreview', 'promoVideoSource');
    setupVideoUpload('demo', 'demoUploadArea', 'demoVideoFile', 'demoVideoPreview', 'demoVideoSource');
}

function setupVideoUpload(type, uploadAreaId, fileInputId, previewId, sourceId) {
    const uploadArea = document.getElementById(uploadAreaId);
    const fileInput = document.getElementById(fileInputId);

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleVideoFile(files[0], type, previewId, sourceId, uploadAreaId);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleVideoFile(e.target.files[0], type, previewId, sourceId, uploadAreaId);
        }
    });
}

function handleVideoFile(file, type, previewId, sourceId, uploadAreaId) {
    if (file.size > 50 * 1024 * 1024) {
        alert('El archivo es demasiado grande. Máximo 50MB.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById(sourceId).src = e.target.result;
        document.getElementById(previewId).style.display = 'block';
        document.getElementById(uploadAreaId).style.display = 'none';
        updatePreview(type, e.target.result);
    };
    reader.readAsDataURL(file);
}

function updatePreview(type, videoSrc) {
    if (type === 'promo') {
        document.getElementById('cardPreview').innerHTML = `<video style="width: 100%; height: 100%; object-fit: cover;" autoplay muted loop><source src="${videoSrc}" type="video/mp4"></video>`;
    } else if (type === 'demo') {
        document.getElementById('phonePreview').innerHTML = `<video style="width: 100%; height: 100%; object-fit: cover;" autoplay muted loop><source src="${videoSrc}" type="video/mp4"></video>`;
    }
}

function removePromoVideo() {
    document.getElementById('promoVideoFile').value = '';
    document.getElementById('promoVideoPreview').style.display = 'none';
    document.getElementById('promoUploadArea').style.display = 'block';
    document.getElementById('cardPreview').innerHTML = '<div style="padding: 2rem; text-align: center; color: #999;">Video promocional aparecerá aquí</div>';
}

function removeDemoVideo() {
    document.getElementById('demoVideoFile').value = '';
    document.getElementById('demoVideoPreview').style.display = 'none';
    document.getElementById('demoUploadArea').style.display = 'block';
    document.getElementById('phonePreview').innerHTML = '<div style="padding: 2rem; text-align: center; color: #999; font-size: 0.875rem;">Video demo aparecerá aquí</div>';
}

// === VISTA PREVIA DE TEXTO ===
document.getElementById('productTitle').addEventListener('input', function() {
    document.getElementById('previewTitle').textContent = this.value || 'Título del producto';
});

document.getElementById('productDescription').addEventListener('input', function() {
    const desc = this.value || 'Descripción...';
    document.getElementById('previewDesc').textContent = desc.substring(0, 100) + (desc.length > 100 ? '...' : '');
});

// === FUNCIONES CRUD ===
function saveProduct() {
    const form = document.getElementById('productForm');
    const formData = new FormData(form);

    if (currentEditingId) {
        formData.append('action', 'update');
        formData.append('product_id', currentEditingId);
    } else {
        formData.append('action', 'create');
    }

    const saveButton = document.querySelector('#saveButtonText');
    const originalText = saveButton.textContent;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';

    fetch('marketplace_admin_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
            loadProducts();
            resetForm();
            showNotification('Producto guardado exitosamente', 'success');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar el producto');
    })
    .finally(() => {
        saveButton.textContent = originalText;
    });
}

function editProduct(id) {
    fetch(`marketplace_admin_actions.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const product = data.product;
                currentEditingId = id;

                document.getElementById('modalTitle').textContent = 'Editar Producto';
                document.getElementById('saveButtonText').textContent = 'Actualizar Producto';
                document.getElementById('productTitle').value = product.title;
                document.getElementById('productDescription').value = product.description;
                document.getElementById('productCategory').value = product.category;
                document.getElementById('productPrice').value = product.price;
                document.getElementById('productTags').value = product.tags ? JSON.parse(product.tags).join(', ') : '';
                document.getElementById('productTools').value = product.tools ? JSON.parse(product.tools).join(', ') : '';
                document.getElementById('isActive').checked = product.is_active;
                document.getElementById('isFeatured').checked = product.featured;

                if (product.promotional_video && product.promo_video_exists) {
                    document.getElementById('promoVideoSource').src = `uploads/videos/${product.promotional_video}`;
                    document.getElementById('promoVideoPreview').style.display = 'block';
                    document.getElementById('promoUploadArea').style.display = 'none';
                    updatePreview('promo', `uploads/videos/${product.promotional_video}`);
                }

                if (product.demo_video && product.demo_video_exists) {
                    document.getElementById('demoVideoSource').src = `uploads/videos/${product.demo_video}`;
                    document.getElementById('demoVideoPreview').style.display = 'block';
                    document.getElementById('demoUploadArea').style.display = 'none';
                    updatePreview('demo', `uploads/videos/${product.demo_video}`);
                }

                document.getElementById('previewTitle').textContent = product.title;
                document.getElementById('previewDesc').textContent = product.description.substring(0, 100) + '...';

                new bootstrap.Modal(document.getElementById('addProductModal')).show();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar el producto');
        });
}

function resetForm() {
    document.getElementById('productForm').reset();
    currentEditingId = null;
    document.getElementById('modalTitle').textContent = 'Agregar Nuevo Producto';
    document.getElementById('saveButtonText').textContent = 'Guardar Producto';
    removePromoVideo();
    removeDemoVideo();
    document.getElementById('previewTitle').textContent = 'Título del producto';
    document.getElementById('previewDesc').textContent = 'Descripción...';
}

// === PRODUCTOS ===
function loadProducts() {
    fetch('marketplace_admin_actions.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayProducts(data.products);
                updateStats(data.stats);
            } else {
                console.error('Error:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

function displayProducts(products) {
    const container = document.getElementById('productsList');

    if (products.length === 0) {
        container.innerHTML = `
            <div style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                <i class="fas fa-box-open fa-3x mb-3"></i>
                <h5>No hay productos</h5>
                <p>Comienza agregando tu primer producto al marketplace</p>
            </div>
        `;
        return;
    }

    container.innerHTML = products.map(product => {
        // Determinar qué video mostrar (priorizar promocional)
        const hasPromoVideo = product.promotional_video && product.promo_video_exists;
        const hasDemoVideo = product.demo_video && product.demo_video_exists;
        const displayVideo = hasPromoVideo ? product.promotional_video : (hasDemoVideo ? product.demo_video : null);
        
        return `
            <div class="product-card">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        ${displayVideo ? `
                            <video class="video-preview" style="width: 100px; height: 60px; object-fit: cover;">
                                <source src="uploads/videos/${displayVideo}" type="video/mp4">
                            </video>
                        ` : `
                            <div style="width: 100px; height: 60px; background: var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-video" style="color: var(--text-secondary);"></i>
                            </div>
                        `}
                    </div>
                    <div class="col-md-6">
                        <div>
                            <h5>${product.title}</h5>
                            <p style="font-size: 0.875rem;">${product.description.substring(0, 100)}${product.description.length > 100 ? '...' : ''}</p>
                            <span class="badge bg-info">${getCategoryName(product.category)}</span>
                            ${product.featured ? '<span class="badge bg-warning text-dark ms-1">Destacado</span>' : ''}
                            ${hasPromoVideo ? '<span class="badge bg-success ms-1">Video Promo</span>' : ''}
                            ${hasDemoVideo ? '<span class="badge bg-primary ms-1">Video Demo</span>' : ''}
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <strong>$${parseFloat(product.price).toFixed(2)}</strong>
                        <br>
                        <small>${product.sales_count} ventas</small>
                    </div>
                    <div class="col-md-2 text-end">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="editProduct(${product.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm ${product.is_active ? 'btn-outline-warning' : 'btn-outline-success'}" onclick="toggleProductStatus(${product.id}, ${!product.is_active})"><i class="fas ${product.is_active ? 'fa-eye-slash' : 'fa-eye'}"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(${product.id})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function toggleProductStatus(id, newStatus) {
    fetch('marketplace_admin_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle_status', product_id: id, is_active: newStatus })
    })
    .then(r => r.json())
    .then(data => data.success ? loadProducts() : alert('Error: ' + data.message))
    .catch(e => { console.error(e); alert('Error al cambiar estado'); });
}

function deleteProduct(id) {
    if (confirm('¿Eliminar este producto?')) {
        fetch('marketplace_admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', product_id: id })
        })
        .then(r => r.json())
        .then(data => data.success ? loadProducts() : alert('Error: ' + data.message))
        .catch(e => { console.error(e); alert('Error al eliminar'); });
    }
}

function updateStats(stats) {
    document.getElementById('totalProducts').textContent = stats.total_products || 0;
    document.getElementById('totalSales').textContent = stats.total_sales || 0;
    document.getElementById('featuredProducts').textContent = stats.featured_products || 0;
}

function getCategoryName(category) {
    const categories = {
        'general': 'General',
        'customer_service': 'Atención al Cliente',
        'sales': 'Ventas',
        'marketing': 'Marketing',
        'messaging': 'Mensajería',
        'finance': 'Finanzas',
        'hr': 'Recursos Humanos'
    };
    return categories[category] || 'General';
}

// Notificación elegante
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle';

    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    notification.innerHTML = `<i class="fas ${icon} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}

// Reset al cerrar modal
document.getElementById('addProductModal').addEventListener('hidden.bs.modal', resetForm);
</script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>