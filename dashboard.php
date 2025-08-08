<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas básicas
$stats = [];

// Total automatizaciones
$query = "SELECT COUNT(*) as total FROM automations WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats['total_automations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Automatizaciones activas
$query = "SELECT COUNT(*) as active FROM automations WHERE user_id = :user_id AND is_active = 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats['active_automations'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

// Mensajes por hora (simulado)
$stats['messages_per_hour'] = rand(15, 35);

// Verificar si existen las nuevas tablas
$table_exists = [];
try {
    $query = "SHOW TABLES LIKE 'webhooks'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $table_exists['webhooks'] = $stmt->rowCount() > 0;
    
    $query = "SHOW TABLES LIKE 'system_activities'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $table_exists['activities'] = $stmt->rowCount() > 0;
    
    $query = "SHOW TABLES LIKE 'marketplace_products'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $table_exists['marketplace'] = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $table_exists['webhooks'] = false;
    $table_exists['activities'] = false;
    $table_exists['marketplace'] = false;
}

// Obtener datos de webhooks si la tabla existe
if ($table_exists['webhooks']) {
    $query = "SELECT COUNT(*) as total FROM webhooks WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $stats['total_webhooks'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $query = "SELECT COUNT(*) as active FROM webhooks WHERE user_id = :user_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $stats['active_webhooks'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
} else {
    $stats['total_webhooks'] = 3;
    $stats['active_webhooks'] = 2;
}

// Obtener datos del marketplace si la tabla existe
$marketplace_data = [];
if ($table_exists['marketplace']) {
    // Productos destacados
    $query = "SELECT * FROM marketplace_products WHERE is_active = 1 AND featured = 1 ORDER BY sales_count DESC LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $marketplace_data['featured_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compras del usuario
    $query = "SELECT COUNT(*) as total FROM user_purchases WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $marketplace_data['user_purchases'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compras pendientes de activación
    $query = "SELECT COUNT(*) as pending FROM user_purchases WHERE user_id = :user_id AND status = 'purchased'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $marketplace_data['pending_activations'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
}

// Estado del sistema
$stats['system_status'] = 'online';

// Verificar si es admin y obtener datos de administración
$is_admin = false;
$admin_stats = [];
$pending_users = 0;
$all_users = [];

$query = "SELECT role FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$role_check = $stmt->fetch(PDO::FETCH_ASSOC);

if ($role_check && $role_check['role'] === 'admin') {
    $is_admin = true;
    
    // Obtener estadísticas de usuarios para admin
    $query = "SELECT COUNT(*) as total FROM users WHERE role != 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin_stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $query = "SELECT COUNT(*) as pending FROM users WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_users = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    $admin_stats['pending'] = $pending_users;

    $query = "SELECT COUNT(*) as active FROM users WHERE status = 'active' AND role != 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin_stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

    $query = "SELECT COUNT(*) as inactive FROM users WHERE status IN ('suspended', 'rejected')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin_stats['inactive'] = $stmt->fetch(PDO::FETCH_ASSOC)['inactive'];

    // Obtener todos los usuarios para la tabla
    $query = "SELECT id, username, email, full_name, company, role, status, created_at 
              FROM users 
              ORDER BY 
                CASE 
                  WHEN status = 'pending' THEN 1 
                  WHEN status = 'active' THEN 2 
                  WHEN status = 'suspended' THEN 3 
                  WHEN status = 'rejected' THEN 4 
                  ELSE 5 
                END, 
                created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener actividad reciente
$recent_activities = [];
if ($table_exists['activities']) {
    $query = "SELECT * FROM system_activities WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 8";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Datos de ejemplo si no existe la tabla
    $recent_activities = [
        ['activity_type' => 'automation_activated', 'description' => 'Customer Support Agent activated', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes'))],
        ['activity_type' => 'message_received', 'description' => 'New WhatsApp message received', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
        ['activity_type' => 'webhook_triggered', 'description' => 'Lead Generation webhook triggered', 'created_at' => date('Y-m-d H:i:s', strtotime('-8 minutes'))],
        ['activity_type' => 'automation_created', 'description' => 'Sales Follow-up Agent paused', 'created_at' => date('Y-m-d H:i:s', strtotime('-12 minutes'))]
    ];
}

// Obtener automatizaciones
if ($is_admin) {
    // Si es admin, obtener TODAS las automatizaciones con información del usuario
    $query = "SELECT a.*, u.username, u.full_name, u.email, u.company 
              FROM automations a 
              LEFT JOIN users u ON a.user_id = u.id 
              ORDER BY a.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Si es usuario normal, solo sus automatizaciones
    $query = "SELECT * FROM automations WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>n8n CRM Control Panel - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
            margin: 0;
            overflow-x: hidden;
        }

        .phone-mockup-detail {
    display: flex;
    justify-content: center;
    padding: 1.5rem 0;
}
.fw-medium{
    color:white !important;
}
.fw-bold{
    color: white !important;
}

.text-muted{
    color:white !important;
}

.sorting{
    color: white !important;
}
.phone-screen-detail {
    width: 300px;
    height: 600px;
    border-radius: 2rem;
    overflow: hidden;
    background-color: #000;
    box-shadow: 0 0 25px rgba(0,0,0,0.4);
    border: 6px solid #222;
    position: relative;
}
.phone-video-detail {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background-color: #000;
}


/* El video dentro del mockup ocupa todo el espacio */
.phone-video-detail {
    width: 100%;
    height: 100%;
    object-fit: cover;
}


        .main-container {
            display: flex;
            min-height: 100vh;
        }
        .marketplace-card {
    background: var(--card-bg);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    height: 100%;
}

.marketplace-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    border-color: #667eea;
}

.product-media {
    position: relative;
    height: 250px;
    background: linear-gradient(135deg, #2d3148 0%, #1a1d29 100%);
    overflow: hidden;
    cursor: pointer;
}

.phone-mockup {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 180px;
    height: 320px;
    background: #1a1a1a;
    border-radius: 25px;
    padding: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.phone-screen {
    width: 100%;
    height: 100%;
    border-radius: 15px;
    overflow: hidden;
    position: relative;
    background: #000;
}

.phone-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 15px;
}

.video-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.play-button {
    width: 60px;
    height: 60px;
    background: var(--primary-gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
}

.play-button:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.no-video-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    font-size: 48px;
}

.video-badge {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    backdrop-filter: blur(10px);
}

.product-content {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    height: calc(100% - 250px);
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.product-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    line-height: 1.3;
    color: var(--text-primary);
}

.product-category {
    background: var(--primary-gradient);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-description {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
    height: 60px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
}

.product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}

.product-price {
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.product-sales {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-buy {
    background: var(--primary-gradient);
    border: none;
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-buy:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-buy::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: all 0.3s ease;
}

.btn-buy:hover::before {
    width: 300px;
    height: 300px;
}

.featured-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #ffd700 0%, #ffb347 100%);
    color: #1a1a1a;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    z-index: 10;
    box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);
}

.category-section {
    margin-bottom: 3rem;
}

.category-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.category-icon {
    width: 48px;
    height: 48px;
    background: var(--primary-gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.category-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.marketplace-header {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
}

.loading-animation {
    position: relative;
}

.loading-animation::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 60px;
    height: 60px;
    border: 3px solid transparent;
    border-top: 3px solid var(--primary-gradient);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Modal de video */
.video-modal .modal-dialog {
    max-width: 800px;
}

.video-modal .modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

.video-modal .modal-header {
    background: var(--primary-gradient);
    color: white;
}

.video-modal .modal-body {
    padding: 0;
}

.video-modal video {
    width: 100%;
    height: auto;
    border-radius: 0 0 15px 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .phone-mockup {
        width: 140px;
        height: 250px;
        padding: 12px;
    }

    .product-media {
        height: 200px;
    }

    .play-button {
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
    
    .marketplace-header .d-flex {
        flex-direction: column;
        gap: 1rem;
    }
    
    .marketplace-header .d-flex > div:last-child {
        text-align: center;
    }
}

        /* Sidebar Navigation */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
        }

        .sidebar-brand {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .sidebar-brand h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin: 0;
        }

        .sidebar-brand p {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin: 0.5rem 0 0;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 2rem 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 2rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0;
        }

        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: var(--text-primary);
        }

        .nav-link.active {
            background: var(--primary-gradient);
            color: var(--text-primary);
            position: relative;
        }

        .nav-link i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1rem;
        }

        .nav-link .badge {
            font-size: 0.625rem;
            padding: 0.25rem 0.5rem;
        }

        /* Main Content */
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

        .page-subtitle {
            color: var(--text-secondary);
            margin: 0;
        }

        /* Stats Cards */
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
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .stats-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stats-icon.primary { background: var(--primary-gradient); }
        .stats-icon.success { background: var(--success-gradient); }
        .stats-icon.warning { background: var(--warning-gradient); }
        .stats-icon.info { background: var(--info-gradient); }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.25rem;
        }

        .stats-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin: 0;
        }

        .stats-trend {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .content-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0 0 0.25rem;
        }

        .card-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            gap: 1rem;
        }

        .quick-action {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .quick-action:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
        }
        /* ============================================
   AGREGAR ESTOS ESTILOS AL CSS EXISTENTE
   (Dentro de la etiqueta <style> en tu archivo)
   ============================================ */

/* Modal de detalles de automatización - Mejoras de visibilidad */
#automationDetailsModal .modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

#automationDetailsModal .modal-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: 16px 16px 0 0;
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
}

#automationDetailsModal .modal-header .modal-title {
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
}

#automationDetailsModal .modal-body {
    background: var(--card-bg);
    color: var(--text-primary);
    padding: 2rem;
}

/* Mejorar contraste de texto */
#automationDetailsModal .modal-body h6 {
    color: #4facfe;
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid rgba(79, 172, 254, 0.2);
}

/* Tabla de información */
#automationDetailsModal .table {
    color: var(--text-primary);
    background: transparent;
}

#automationDetailsModal .table td {
    border-color: var(--border-color);
    padding: 0.75rem 0.5rem;
    vertical-align: middle;
}

#automationDetailsModal .table td strong {
    color: var(--text-primary);
    font-weight: 600;
    min-width: 140px;
    display: inline-block;
}

#automationDetailsModal .table td:first-child {
    background: rgba(79, 172, 254, 0.05);
    font-weight: 600;
    width: 180px;
}

#automationDetailsModal .table td:last-child {
    color: var(--text-primary);
    font-weight: 500;
}

/* Código ID */
#automationDetailsModal code {
    background: rgba(79, 172, 254, 0.15);
    color: #4facfe;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    font-weight: 600;
    border: 1px solid rgba(79, 172, 254, 0.3);
}

/* Tarjeta de información del usuario */
#automationDetailsModal .card {
    background: rgba(79, 172, 254, 0.05) !important;
    border: 1px solid rgba(79, 172, 254, 0.2) !important;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

#automationDetailsModal .card-body {
    padding: 1.5rem;
}

#automationDetailsModal .card h6 {
    color: var(--text-primary) !important;
    font-weight: 700;
    margin-bottom: 0.5rem;
    border: none;
    padding: 0;
}

#automationDetailsModal .card p {
    color: var(--text-primary);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

#automationDetailsModal .card small {
    color: var(--text-secondary);
    font-weight: 400;
}

/* Iconos en la información del usuario */
#automationDetailsModal .card i {
    color: #4facfe;
    margin-right: 0.5rem;
    width: 16px;
    text-align: center;
}

/* Botón "Ver como usuario" */
#automationDetailsModal .btn-outline-info {
    background: transparent;
    border: 2px solid #4facfe;
    color: #4facfe;
    font-weight: 600;
    transition: all 0.3s ease;
}

#automationDetailsModal .btn-outline-info:hover {
    background: #4facfe;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
}

/* Separador */
#automationDetailsModal hr {
    border-color: var(--border-color);
    opacity: 0.5;
    margin: 1.5rem 0;
}

/* Tabla de actividad reciente */
#automationDetailsModal .table-responsive {
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: rgba(45, 49, 72, 0.5);
}

#automationDetailsModal .table-responsive .table {
    margin-bottom: 0;
}

#automationDetailsModal .table-responsive .table thead th {
    background: rgba(79, 172, 254, 0.1);
    color: var(--text-primary);
    font-weight: 700;
    border-bottom: 2px solid var(--border-color);
    padding: 1rem 0.75rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

#automationDetailsModal .table-responsive .table tbody td {
    background: transparent;
    color: var(--text-primary);
    padding: 0.75rem;
    border-bottom: 1px solid rgba(79, 172, 254, 0.1);
}

#automationDetailsModal .table-responsive .table tbody td small {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Footer del modal */
#automationDetailsModal .modal-footer {
    background: var(--card-bg);
    border-top: 1px solid var(--border-color);
    border-radius: 0 0 16px 16px;
    padding: 1rem 2rem;
}

#automationDetailsModal .modal-footer .btn-secondary {
    background: var(--border-color);
    border: none;
    color: var(--text-primary);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

#automationDetailsModal .modal-footer .btn-secondary:hover {
    background: var(--text-secondary);
    color: var(--card-bg);
    transform: translateY(-1px);
}

/* Badges mejorados */
#automationDetailsModal .badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

#automationDetailsModal .badge.bg-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
    color: white;
    box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);
}

/* Status badges específicos */
#automationDetailsModal .status-badge.active {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);
}

#automationDetailsModal .status-badge.inactive {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
}

/* Animación de entrada */
#automationDetailsModal.show .modal-dialog {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive */
@media (max-width: 768px) {
    #automationDetailsModal .modal-body {
        padding: 1rem;
    }
    
    #automationDetailsModal .table td:first-child {
        width: auto;
        min-width: 120px;
    }
    
    #automationDetailsModal .row {
        flex-direction: column;
    }
    
    #automationDetailsModal .col-md-4 {
        margin-top: 2rem;
    }
}

/* Mejoras adicionales para mejor contraste */
#automationDetailsModal .text-muted {
    color: #a0a6b8 !important;
}

#automationDetailsModal .text-primary {
    color: #4facfe !important;
}

/* Mejorar la visibilidad de los elementos de la tabla */
#automationDetailsModal .table tr:hover {
    background: rgba(79, 172, 254, 0.05);
}

/* Estilo para cuando no hay actividades */
#automationDetailsModal .no-activities {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
    font-style: italic;
}

        .quick-action i {
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        /* Activity Feed */
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-description {
            font-size: 0.875rem;
            margin: 0 0 0.25rem;
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Status Indicators */
        .status-online {
            color: #10b981;
        }

        .status-offline {
            color: #ef4444;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.online {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-badge.active {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.2);
            color: #6b7280;
        }

        /* Automation Cards */
        .automation-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .automation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        /* Marketplace Products */
        .marketplace-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .marketplace-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-color: #667eea;
        }

        .marketplace-featured {
            position: relative;
            overflow: hidden;
        }

        .marketplace-featured::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
        }

        .marketplace-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4facfe;
        }

        .marketplace-sales {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #667eea;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
            border-bottom: 1px solid var(--border-color);
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

        .form-label {
            color: var(--text-primary);
        }

        .form-text {
            color: var(--text-secondary);
        }

        /* Banner de Impersonación */
        .impersonation-banner {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 0.75rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 3px solid rgba(255,255,255,0.2);
        }
        
        .impersonation-banner + .sidebar {
            top: 60px;
            height: calc(100vh - 60px);
        }
        
        .impersonation-banner + .sidebar + .main-content {
            padding-top: 80px;
        }
        
        .impersonation-banner .btn-outline-light {
            border-color: rgba(255,255,255,0.3);
            color: white;
        }
        
        .impersonation-banner .btn-outline-light:hover {
            background-color: rgba(255,255,255,0.1);
            border-color: white;
            color: white;
        }
        .table-warning {
            background-color: #fff3cd !important;
        }
        
        .table th {
            border-top: none;
            color: #495057;
            font-weight: 600;
            padding: 1rem 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .btn-group .btn {
            margin-right: 5px;
        }
        
        .btn-group .btn:last-child {
            margin-right: 0;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        /* DataTables customization */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 8px 12px;
            background: var(--card-bg);
            color: var(--text-primary);
        }
        
        .dataTables_wrapper .dataTables_length select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 5px 10px;
            background: var(--card-bg);
            color: var(--text-primary);
        }
        
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: var(--text-secondary);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-primary) !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-gradient) !important;
            color: white !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                margin-right: 0;
                margin-bottom: 5px;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--sidebar-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #667eea;
        }

        :root {
    --marketplace-card-bg: #2d3148;
    --marketplace-hover-shadow: 0 20px 40px rgba(0,0,0,0.4);
    --marketplace-border: #3a3f5c;
    --marketplace-featured-gradient: linear-gradient(135deg, #ffd700 0%, #ffb347 100%);
}

/* ============ TARJETAS NORMALES ============ */
.marketplace-card-normal {
    background: var(--card-bg);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.marketplace-card-normal:hover {
    transform: translateY(-8px);
    box-shadow: var(--marketplace-hover-shadow);
    border-color: #667eea;
}

.card-image-normal {
    position: relative;
    height: 200px;
    background: linear-gradient(135deg, #2d3148 0%, #1a1d29 100%);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 0;
}

.video-overlay-normal {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    opacity: 1;
}

.video-overlay-normal:hover {
    background: rgba(0,0,0,0.7);
}

.play-btn-normal {
    width: 50px;
    height: 50px;
    background: var(--primary-gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
}

.play-btn-normal:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.no-video-placeholder-normal {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    font-size: 40px;
    width: 100%;
    height: 100%;
}

.card-content-normal {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.card-header-normal {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.card-title-normal {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    color: var(--text-primary);
    line-height: 1.3;
}

.card-category-normal {
    background: var(--primary-gradient);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.card-description-normal {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
    flex: 1;
}

.card-footer-normal {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}

.card-price-normal {
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.25rem;
}

.card-sales-normal {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-ver-mas {
    background: var(--primary-gradient);
    border: none;
    border-radius: 10px;
    padding: 0.5rem 1rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
    font-size: 0.875rem;
}

.btn-ver-mas:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: white;
}

/* ============ MODAL DE DETALLE DEL PRODUCTO ============ */
.product-detail-modal .modal-dialog {
    max-width: 900px;
}

.product-detail-modal .modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
}

.product-detail-modal .modal-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
}

.product-detail-modal .modal-body {
    padding: 2rem;
    color: var(--text-primary);
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.product-title-section h3 {
    color: var(--text-primary);
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.product-subtitle {
    color: var(--text-secondary);
    font-size: 1rem;
    margin-bottom: 1rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    background: var(--success-gradient);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    gap: 0.5rem;
}

.status-badge.running {
    background: var(--success-gradient);
}

/* ============ HERRAMIENTAS CONECTADAS ============ */
.tools-section {
    margin-bottom: 2rem;
}

.tools-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tools-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.tool-badge {
    background: rgba(102, 126, 234, 0.1);
    border: 1px solid rgba(102, 126, 234, 0.3);
    color: var(--text-primary);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.tool-badge:hover {
    background: rgba(102, 126, 234, 0.2);
    border-color: rgba(102, 126, 234, 0.5);
}

.tool-badge i {
    color: #667eea;
}

/* ============ ESTADÍSTICAS ============ */
.stats-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(102, 126, 234, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(102, 126, 234, 0.1);
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ============ CONTENIDO PRINCIPAL ============ */
.content-sections {
    margin-bottom: 2rem;
}

.content-sections h4 {
    color: var(--text-primary);
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.demo-section {
    margin-bottom: 2rem;
}

.demo-video {
    width: 100%;
    max-height: 400px;
    border-radius: 12px;
    background: #000;
}

.example-section {
    margin-bottom: 2rem;
}

.phone-mockup-detail {
    display: flex;
    justify-content: center;
    margin: 1.5rem 0;
}

.phone-mockup-detail {
    width: 200px;
    height: 350px;
    background: #1a1a1a;
    border-radius: 25px;
    padding: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.phone-screen-detail {
    width: 100%;
    height: 100%;
    border-radius: 15px;
    overflow: hidden;
    background: #000;
}

.phone-video-detail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 15px;
}

/* ============ BOTONES DE ACCIÓN ============ */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-primary-large {
    background: var(--primary-gradient);
    border: none;
    border-radius: 12px;
    padding: 1rem 2rem;
    font-weight: 600;
    color: white;
    font-size: 1rem;
    transition: all 0.3s ease;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-primary-large:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-secondary-large {
    background: transparent;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem 2rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s ease;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-secondary-large:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.1);
    color: var(--text-primary);
    transform: translateY(-2px);
}

/* ============ BADGE DESTACADO ============ */
.featured-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: var(--marketplace-featured-gradient);
    color: #1a1a1a;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    z-index: 10;
    box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .product-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .stats-section {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .phone-mockup-detail {
        width: 160px;
        height: 280px;
        padding: 12px;
    }
    
    .tools-grid {
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .product-detail-modal .modal-body {
        padding: 1rem;
    }
    
    .stats-section {
        grid-template-columns: 1fr;
    }
    
    .card-content-normal {
        padding: 1rem;
    }
    
    .btn-primary-large,
    .btn-secondary-large {
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
    }
}

/* ============ ANIMACIONES ADICIONALES ============ */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.marketplace-card-normal {
    animation: fadeInUp 0.6s ease forwards;
}

.marketplace-card-normal:nth-child(1) { animation-delay: 0.1s; }
.marketplace-card-normal:nth-child(2) { animation-delay: 0.2s; }
.marketplace-card-normal:nth-child(3) { animation-delay: 0.3s; }

/* ============ ESTADOS DE CARGA ============ */
.loading-animation {
    position: relative;
    min-height: 200px;
}

.loading-animation::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    border: 3px solid rgba(102, 126, 234, 0.3);
    border-top: 3px solid #667eea;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* ============ MEJORAS ADICIONALES ============ */
.marketplace-header {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    margin-bottom: 2rem;
}

.category-section {
    margin-bottom: 3rem;
}

.category-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.category-icon {
    width: 48px;
    height: 48px;
    background: var(--primary-gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

/* Video Modal específico para marketplace */
.video-modal .modal-dialog {
    max-width: 800px;
}

.video-modal .modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

.video-modal .modal-header {
    background: var(--primary-gradient);
    color: white;
}

.video-modal .modal-body {
    padding: 0;
}

.video-modal video {
    width: 100%;
    height: auto;
    border-radius: 0 0 15px 15px;
}
/* Textos y secciones */
#detailTitle {
  font-size: 1.75rem;
  font-weight: 700;
  color: #fff;
}

#detailSubtitle {
  font-size: 1rem;
  color: #b0b0b0;
}

.status-badge.running {
  background-color: #2ecc71;
  color: white;
  padding: 0.3rem 0.6rem;
  font-size: 0.75rem;
  border-radius: 0.5rem;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

/* Estadísticas */
.stat-number {
  font-size: 1.4rem;
  font-weight: 600;
  color: #ffffff;
}

.stat-label {
  font-size: 0.85rem;
  color: #aaaaaa;
}

/* Video demostrativo */
.demo-video {
  width: 100%;
  border-radius: 0.75rem;
  margin-top: 0.5rem;
}

/* Celular mockup */
.phone-mockup-detail {
  width: 300px;
  height: 600px;
  border-radius: 2rem;
  overflow: hidden;
  background-color: #000;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  border: 6px solid #1a1a1a;
}

.phone-screen-detail {
  width: 100%;
  height: 100%;
}

.phone-video-detail {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* Botones */
.btn-primary-large,
.btn-secondary-large {
  padding: 1rem 2rem;
  font-size: 1rem;
  border-radius: 0.75rem;
  transition: all 0.2s ease;
}

    </style>
</head>
<body>
    <div class="main-container">
        <!-- Banner de Impersonación -->
        <?php if (isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating']): ?>
        <div class="impersonation-banner">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-secret me-2"></i>
                            <strong>Modo Administrador:</strong>
                            <span class="ms-2">Estás viendo como 
                                <span class="badge bg-light text-dark ms-1"><?php echo htmlspecialchars($_SESSION['impersonated_user']); ?></span>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-outline-light btn-sm" onclick="stopImpersonation()">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Volver a Admin
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
           <div class="sidebar-brand text-center p-3">
    <img src="logo.png" alt="n8n CRM Logo" class="img-fluid" style="max-width: 150px;">
</div>
            
            <div class="nav-section">
                <div class="nav-section-title">Navigation</div>
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-chart-line"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#automations" class="nav-link" onclick="showAutomations()">
                        <i class="fas fa-cogs"></i>
                      Agentes y Automatizaciones 
                    </a>
                </div>
                <?php if ($table_exists['marketplace']): ?>
                <div class="nav-item">
                    <a href="#marketplace" class="nav-link" onclick="showMarketplace()">
                        <i class="fas fa-store"></i>
                        Marketplace
                        <?php if (!empty($marketplace_data['pending_activations']) && $marketplace_data['pending_activations'] > 0): ?>
                            <span class="badge bg-warning ms-2 text-dark"><?php echo $marketplace_data['pending_activations']; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endif; ?>
                <div class="nav-item">
                    <a href="credentials.php" class="nav-link">
                        <i class="fas fa-key"></i>
                        Credenciales
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="whatsapp.php" class="nav-link">
                        <i class="fab fa-whatsapp"></i>
                        Mi WhatsApp
                    </a>
                </div>
                <?php if ($is_admin): ?>
                <div class="nav-item">
                    <a href="#users" class="nav-link" onclick="showUsers()">
                        <i class="fas fa-users-cog"></i>
                        Gestión de Usuarios
                        <?php if ($pending_users > 0): ?>
                            <span class="badge bg-warning ms-2 text-dark"><?php echo $pending_users; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="marketplace_admin.php" class="nav-link">
                        <i class="fas fa-store-alt"></i>
                        Admin Marketplace
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Usuario</div>
                <div class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard View -->
            <div id="dashboard-view">
                <div class="page-header">
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Overview of your n8n workflow management system</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon primary">
                                <i class="fas fa-cogs"></i>
                            </div>
                        </div>
                        <div class="stats-value"><?php echo $stats['active_automations']; ?></div>
                        <div class="stats-label">Active Agents</div>
                        <div class="stats-trend"><?php echo $stats['total_automations']; ?> total</div>
                    </div>

                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon info">
                                <i class="fas fa-comments"></i>
                            </div>
                        </div>
                        <div class="stats-value"><?php echo $stats['messages_per_hour']; ?></div>
                        <div class="stats-label">Messages/Hour</div>
                        <div class="stats-trend">WhatsApp activity</div>
                    </div>

                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon success">
                                <i class="fas fa-link"></i>
                            </div>
                        </div>
                        <div class="stats-value"><?php echo $stats['active_webhooks']; ?></div>
                        <div class="stats-label">Webhooks</div>
                        <div class="stats-trend"><?php echo $stats['total_webhooks']; ?> configured</div>
                    </div>

                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stats-value">
                            <span class="status-online">Online</span>
                        </div>
                        <div class="stats-label">System Status</div>
                        <div class="stats-trend">All systems operational</div>
                    </div>
                </div>

                <!-- Marketplace Featured Products (if available) -->
                <?php if ($table_exists['marketplace'] && !empty($marketplace_data['featured_products'])): ?>
                <div class="content-card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="card-title">🌟 Featured in Marketplace</h3>
                                <p class="card-subtitle">Popular automations ready to use</p>
                            </div>
                            <a href="#marketplace" onclick="showMarketplace()" class="btn btn-sm btn-primary">
                                View All <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($marketplace_data['featured_products'] as $product): ?>
                            <div class="col-md-4">
                                <div class="marketplace-card marketplace-featured" onclick="viewMarketplaceProduct(<?php echo $product['id']; ?>)">
                                    <h5 class="mb-2"><?php echo htmlspecialchars($product['title']); ?></h5>
                                    <p class="text-muted mb-3" style="font-size: 0.875rem;">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-end">
                                        <div>
                                            <div class="marketplace-price">
<a href="#" class="quick-action" onclick="event.preventDefault(); startAllAgents();">...</a>

                                            </div>
                                            <div class="marketplace-sales">
                                                <i class="fas fa-shopping-cart me-1"></i><?php echo $product['sales_count']; ?> ventas
                                            </div>
                                        </div>
                                        <button class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i> Ver
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Quick Actions -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                            <p class="card-subtitle">Common tasks and controls</p>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="#" class="quick-action" onclick="startAllAgents()">
                                    <i class="fas fa-play"></i>
                                    <div>
                                        <div>Start All Agents</div>
                                        <small style="color: var(--text-secondary);">Activate all automations</small>
                                    </div>
                                </a>
                                
                                <a href="#" class="quick-action" onclick="pauseAllAgents()">
                                    <i class="fas fa-pause"></i>
                                    <div>
                                        <div>Pause All Agents</div>
                                        <small style="color: var(--text-secondary);">Temporarily stop all</small>
                                    </div>
                                </a>
                                
                                <a href="#" class="quick-action" data-bs-toggle="modal" data-bs-target="#addAutomationModal">
                                    <i class="fas fa-plus"></i>
                                    <div>
                                        <div>Add Automation</div>
                                        <small style="color: var(--text-secondary);">Create new workflow</small>
                                    </div>
                                </a>
                                
                                <?php if ($table_exists['marketplace']): ?>
                                <a href="#marketplace" class="quick-action" onclick="showMarketplace()">
                                    <i class="fas fa-store"></i>
                                    <div>
                                        <div>Browse Marketplace</div>
                                        <small style="color: var(--text-secondary);">Find new automations</small>
                                    </div>
                                </a>
                                <?php endif; ?>

                                <?php if ($is_admin && $pending_users > 0): ?>
                                <a href="#" class="quick-action" onclick="showUsers()" style="border-color: #f093fb; background: linear-gradient(135deg, #f093fb 0%, #f5576c 20%);">
                                    <i class="fas fa-user-check"></i>
                                    <div>
                                        <div>Usuarios Pendientes</div>
                                        <small style="color: rgba(255,255,255,0.8);"><?php echo $pending_users; ?> usuario(s) esperando aprobación</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activity</h3>
                            <p class="card-subtitle">Latest system events and notifications</p>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item" style="padding: 1rem 1.5rem;">
                                <div class="activity-icon <?php 
                                    echo match($activity['activity_type']) {
                                        'automation_activated' => 'success',
                                        'webhook_triggered' => 'info',
                                        'automation_created' => 'primary',
                                        'message_received' => 'warning',
                                        'product_purchased' => 'warning',
                                        default => 'primary'
                                    };
                                ?>">
                                    <i class="fas <?php 
                                        echo match($activity['activity_type']) {
                                            'automation_activated' => 'fa-play',
                                            'webhook_triggered' => 'fa-link',
                                            'automation_created' => 'fa-plus',
                                            'message_received' => 'fa-message',
                                            'product_purchased' => 'fa-shopping-cart',
                                            default => 'fa-info'
                                        };
                                    ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-time"><?php echo date('H:i A', strtotime($activity['created_at'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- REEMPLAZAR toda la sección <!-- Automations View --> por esta versión corregida -->

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

            <!-- Marketplace View -->
            <div id="marketplace-view" style="display: none;">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">Marketplace</h1>
                            <p class="page-subtitle">Descubre y adquiere nuevas automatizaciones</p>
                        </div>
                        <div>
                            <?php if (!empty($marketplace_data['user_purchases'])): ?>
                                <span class="badge bg-info me-2">
                                    <i class="fas fa-shopping-bag me-1"></i>
                                    <?php echo $marketplace_data['user_purchases']; ?> compras
                                </span>
                            <?php endif; ?>
                            <button class="btn btn-primary" onclick="showMyPurchases()">
                                <i class="fas fa-download me-2"></i>
                                Mis Compras
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Marketplace Content will be loaded here -->
                <div id="marketplace-content">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted mt-3">Cargando productos del marketplace...</p>
                    </div>
                </div>
            </div>

            <!-- Users Management View (Solo para Admin) -->
            <?php if ($is_admin): ?>
            <div id="users-view" style="display: none;">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">Gestión de Usuarios</h1>
                            <p class="page-subtitle">Administra registros y permisos de usuarios</p>
                        </div>
                        <?php if ($pending_users > 0): ?>
                            <div class="alert alert-warning d-flex align-items-center mb-0" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo $pending_users; ?></strong> usuario(s) pendiente(s) de aprobación
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estadísticas de Usuarios -->
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stats-value"><?php echo $admin_stats['total']; ?></div>
                        <div class="stats-label">Total Usuarios</div>
                        <div class="stats-trend">Registrados en el sistema</div>
                    </div>

                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stats-value"><?php echo $admin_stats['pending']; ?></div>
                        <div class="stats-label">Pendientes</div>
                        <div class="stats-trend">Esperando aprobación</div>
                    </div>

                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stats-value"><?php echo $admin_stats['active']; ?></div>
                        <div class="stats-label">Activos</div>
                        <div class="stats-trend">Con acceso completo</div>
                    </div>

                    <div class="stats-card">
                        <div class="stats-header">
                            <div class="stats-icon info">
                                <i class="fas fa-ban"></i>
                            </div>
                        </div>
                        <div class="stats-value"><?php echo $admin_stats['inactive']; ?></div>
                        <div class="stats-label">Inactivos</div>
                        <div class="stats-trend">Suspendidos o rechazados</div>
                    </div>
                </div>

                <!-- Tabla de Usuarios -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">Lista de Usuarios</h3>
                        <p class="card-subtitle">Gestiona el acceso y estado de todos los usuarios</p>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Información</th>
                                        <th>Estado</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $user): ?>
                                    <tr class="<?php echo $user['status'] === 'pending' ? 'table-warning' : ''; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="stats-icon primary me-3" style="width: 40px; height: 40px; font-size: 14px;">
                                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($user['full_name'] ?: 'N/A'); ?></div>
                                                <?php if (!empty($user['company'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-building me-1"></i>
                                                        <?php echo htmlspecialchars($user['company']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusBadges = [
                                                'active' => '<span class="badge bg-success">Activo</span>',
                                                'pending' => '<span class="badge bg-warning text-dark">Pendiente</span>',
                                                'suspended' => '<span class="badge bg-danger">Suspendido</span>',
                                                'rejected' => '<span class="badge bg-secondary">Rechazado</span>'
                                            ];
                                            echo $statusBadges[$user['status']] ?? '<span class="badge bg-light text-dark">Desconocido</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <div class="btn-group" role="group">
                                                    <?php if ($user['status'] === 'pending'): ?>
                                                        <button class="btn btn-success btn-sm" onclick="performUserAction('approve', <?php echo $user['id']; ?>)" title="Aprobar">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm" onclick="performUserAction('reject', <?php echo $user['id']; ?>)" title="Rechazar">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php elseif ($user['status'] === 'active'): ?>
                                                        <button class="btn btn-warning btn-sm" onclick="performUserAction('suspend', <?php echo $user['id']; ?>)" title="Suspender">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    <?php elseif ($user['status'] === 'suspended'): ?>
                                                        <button class="btn btn-success btn-sm" onclick="performUserAction('activate', <?php echo $user['id']; ?>)" title="Activar">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($user['status'] !== 'pending'): ?>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="confirmUserDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-shield-alt me-1"></i>
                                                    Admin
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
<!-- Modal para detalles del producto estilo n8n -->
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
                        <div style= "width=100%"> 
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Variables globales para acciones de usuarios
        let pendingUserAction = null;
        let pendingUserId = null;

        // Navegación entre vistas
        function showDashboard() {
            document.getElementById('dashboard-view').style.display = 'block';
            document.getElementById('automations-view').style.display = 'none';
            document.getElementById('marketplace-view').style.display = 'none';
            <?php if ($is_admin): ?>
            document.getElementById('users-view').style.display = 'none';
            <?php endif; ?>
            
            // Actualizar navegación activa
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            document.querySelector('[href="#"]').classList.add('active');
        }

        function showAutomations() {
            document.getElementById('dashboard-view').style.display = 'none';
            document.getElementById('automations-view').style.display = 'block';
            document.getElementById('marketplace-view').style.display = 'none';
            <?php if ($is_admin): ?>
            document.getElementById('users-view').style.display = 'none';
            <?php endif; ?>
            
            // Actualizar navegación activa
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            document.querySelector('[href="#automations"]').classList.add('active');
        }

        function showMarketplace() {
            document.getElementById('dashboard-view').style.display = 'none';
            document.getElementById('automations-view').style.display = 'none';
            document.getElementById('marketplace-view').style.display = 'block';
            <?php if ($is_admin): ?>
            document.getElementById('users-view').style.display = 'none';
            <?php endif; ?>
            
            // Actualizar navegación activa
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            document.querySelector('[href="#marketplace"]').classList.add('active');
            
            // Cargar productos del marketplace
            loadMarketplaceProducts();
        }

        <?php if ($is_admin): ?>
        function showUsers() {
            document.getElementById('dashboard-view').style.display = 'none';
            document.getElementById('automations-view').style.display = 'none';
            document.getElementById('marketplace-view').style.display = 'none';
            document.getElementById('users-view').style.display = 'block';
            
            // Actualizar navegación activa
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            document.querySelector('[href="#users"]').classList.add('active');
            
            // Inicializar DataTable si no existe
            if (!$.fn.DataTable.isDataTable('#usersTable')) {
                $('#usersTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json"
                    },
                    "pageLength": 25,
                    "order": [[3, "desc"]], // Ordenar por fecha de registro descendente
                    "columnDefs": [
                        { "orderable": false, "targets": 4 } // No ordenar columna de acciones
                    ]
                });
            }
        }

        // Funciones para gestión de usuarios
        function performUserAction(action, userId) {
            const actions = {
                'approve': {
                    message: '¿Estás seguro de que quieres aprobar este usuario?',
                    button: 'Aprobar',
                    class: 'btn-success'
                },
                'reject': {
                    message: '¿Estás seguro de que quieres rechazar este usuario?',
                    button: 'Rechazar',
                    class: 'btn-danger'
                },
                'suspend': {
                    message: '¿Estás seguro de que quieres suspender este usuario?',
                    button: 'Suspender',
                    class: 'btn-warning'
                },
                'activate': {
                    message: '¿Estás seguro de que quieres activar este usuario?',
                    button: 'Activar',
                    class: 'btn-success'
                }
            };

            if (actions[action]) {
                document.getElementById('userConfirmMessage').textContent = actions[action].message;
                
                const confirmButton = document.getElementById('userConfirmButton');
                confirmButton.textContent = actions[action].button;
                confirmButton.className = 'btn ' + actions[action].class;
                
                pendingUserAction = action;
                pendingUserId = userId;
                
                new bootstrap.Modal(document.getElementById('userConfirmModal')).show();
            }
        }

        function confirmUserDelete(userId, username) {
            document.getElementById('userConfirmMessage').innerHTML = 
                `¿Estás seguro de que quieres eliminar permanentemente al usuario <strong>${username}</strong>?<br><br>` +
                '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Esta acción no se puede deshacer.</span>';
            
            const confirmButton = document.getElementById('userConfirmButton');
            confirmButton.textContent = 'Eliminar';
            confirmButton.className = 'btn btn-danger';
            
            pendingUserAction = 'delete';
            pendingUserId = userId;
            
            new bootstrap.Modal(document.getElementById('userConfirmModal')).show();
        }

        function executeUserAction() {
            if (pendingUserAction && pendingUserId) {
                fetch('admin_users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: pendingUserAction,
                        user_id: pendingUserId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la acción');
                });
                
                // Limpiar variables
                pendingUserAction = null;
                pendingUserId = null;
                
                // Cerrar modal
                bootstrap.Modal.getInstance(document.getElementById('userConfirmModal')).hide();
            }
        }
        <?php endif; ?>

        // Funciones del Marketplace
  function loadMarketplaceProducts() {
    const container = document.getElementById('marketplace-content');
    
    // Mostrar loading
    container.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <h5 style="color: var(--text-secondary); margin-top: 1rem;">Cargando productos del marketplace...</h5>
            <p style="color: var(--text-secondary); font-size: 0.875rem;">Preparando las mejores automatizaciones para ti</p>
        </div>
    `;

    fetch('marketplace_actions.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMarketplaceProducts(data.products);
            } else {
                showMarketplaceError('Error al cargar los productos: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMarketplaceError('Error al conectar con el servidor');
        });
}

function showMarketplaceError(message) {
    const container = document.getElementById('marketplace-content');
    container.innerHTML = `
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button class="btn btn-outline-danger btn-sm ms-auto" onclick="loadMarketplaceProducts()">
                <i class="fas fa-retry me-1"></i>Reintentar
            </button>
        </div>
    `;
}

        function displayMarketplaceProducts(products) {
    const container = document.getElementById('marketplace-content');
    
    if (products.length === 0) {
        container.innerHTML = `
            <div class="empty-state text-center py-5">
                <i class="fas fa-store fa-4x mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <h4 style="color: var(--text-secondary);">No hay productos disponibles</h4>
                <p style="color: var(--text-secondary);">Vuelve pronto, estamos preparando nuevas automatizaciones increíbles</p>
                <button class="btn btn-primary mt-3" onclick="loadMarketplaceProducts()">
                    <i class="fas fa-sync-alt me-2"></i>Refrescar
                </button>
            </div>
        `;
        return;
    }
    // Agrupar productos por categoría
    const categories = {};
    products.forEach(product => {
        if (!categories[product.category]) {
            categories[product.category] = [];
        }
        categories[product.category].push(product);
    });

    let html = `
        <div class="marketplace-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 style="margin: 0; color: var(--text-primary);">
                        <i class="fas fa-store me-2"></i>Marketplace de Automatizaciones
                    </h3>
                    <p style="margin: 0; color: var(--text-secondary);">
                        Descubre ${products.length} automatizaciones listas para usar
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-primary btn-sm me-2" onclick="showMyPurchases()">
                        <i class="fas fa-shopping-bag me-1"></i>Mis Compras
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="loadMarketplaceProducts()">
                        <i class="fas fa-sync-alt me-1"></i>Refrescar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    for (const [category, categoryProducts] of Object.entries(categories)) {
        const categoryInfo = getCategoryInfo(category);
        
        html += `
            <div class="category-section mb-5">
                <div class="category-header mb-4">
                    <div class="d-flex align-items-center">
                        <div class="category-icon me-3">
                            <i class="${categoryInfo.icon}"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: var(--text-primary);">${categoryInfo.name}</h4>
                            <small style="color: var(--text-secondary);">${categoryProducts.length} ${categoryProducts.length === 1 ? 'producto' : 'productos'}</small>
                        </div>
                    </div>
                </div>
                <div class="row g-4">
        `;
        
        categoryProducts.forEach(product => {
            html += createNormalProductCard(product);
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    
    // Inicializar lazy loading para videos promocionales
    initializeVideoLazyLoading();
}

function createNormalProductCard(product) {
    const hasPromoVideo = product.promotional_video && product.promotional_video.trim() !== '';
    
    return `
        <div class="col-lg-4 col-md-6">
            <div class="marketplace-card-normal h-100">
                ${product.featured ? '<div class="featured-badge"><i class="fas fa-star me-1"></i>Destacado</div>' : ''}
                
                <div class="card-image-normal">
                    ${hasPromoVideo ? `
                        <video class="card-video" preload="none" data-src="uploads/videos/${product.promotional_video}" muted>
                            <source data-src="uploads/videos/${product.promotional_video}#t=0.1" type="video/mp4">
                        </video>
                        <div class="video-overlay-normal">
                            <div class="play-btn-normal">
                                <i class="fas fa-play"></i>
                            </div>
                        </div>
                    ` : `
                        <div class="no-video-placeholder-normal">
                            <i class="fas fa-robot"></i>
                        </div>
                    `}
                </div>

                <div class="card-content-normal">
                    <div class="card-header-normal">
                        <h5 class="card-title-normal">${escapeHtml(product.title)}</h5>
                        <div class="card-category-normal">${getCategoryInfo(product.category).name}</div>
                    </div>

                    <p class="card-description-normal">${escapeHtml(product.description.substring(0, 120))}${product.description.length > 120 ? '...' : ''}</p>

                    <div class="card-footer-normal">
                        <div>
                            <div class="card-price-normal">
                                ${product.price > 0 ? '$' + parseFloat(product.price).toFixed(2) : 'Gratis'}
                            </div>
                            <div class="card-sales-normal">
                                <i class="fas fa-shopping-cart me-1"></i>
                                <span>${product.sales_count} ${product.sales_count === 1 ? 'venta' : 'ventas'}</span>
                            </div>
                        </div>
                        <button class="btn btn-ver-mas" onclick="showProductDetail(${product.id})" data-product-id="${product.id}">
                            <i class="fas fa-eye me-2"></i>Ver Más
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}


function showProductDetail(productId) {
    // Mostrar loading en el botón específico
    const button = document.querySelector(`[onclick="showProductDetail(${productId})"]`);
    if (button) {
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Cargando...';
        button.disabled = true;
        
        // Restaurar botón después de un tiempo
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
        }, 3000);
    }
     // Buscar el producto en la lista cargada o hacer fetch individual
    fetch(`marketplace_actions.php?action=get_product&id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayProductDetailModal(data.product);
            } else {
                showErrorNotification('Error al cargar detalles del producto: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorNotification('Error al cargar detalles del producto');
        });
}



// Función para mostrar el modal de detalles
function displayProductDetailModal(product) {
    // Verificar que el modal existe antes de continuar
    const modal = document.getElementById('productDetailModal');
    if (!modal) {
        console.error('Modal productDetailModal no encontrado');
        showErrorNotification('Error: Modal no encontrado');
        return;
    }

    // Actualizar contenido del modal con verificaciones
    const modalTitle = document.getElementById('modalProductTitle');
    const detailTitle = document.getElementById('detailTitle');
    const detailSubtitle = document.getElementById('detailSubtitle');
    const detailPrice = document.getElementById('detailPrice');
    const detailSales = document.getElementById('detailSales');

    if (modalTitle) modalTitle.textContent = product.title;
    if (detailTitle) detailTitle.textContent = product.title;
    if (detailSubtitle) detailSubtitle.textContent = product.description;
    if (detailPrice) detailPrice.textContent = product.price > 0 ? '$' + parseFloat(product.price).toFixed(2) : 'Gratis';
    if (detailSales) detailSales.textContent = product.sales_count + ' ventas';

    // Llenar herramientas conectadas
    const toolsGrid = document.getElementById('toolsGrid');
    if (toolsGrid) {
        toolsGrid.innerHTML = '';
        
        if (product.tools) {
            try {
                const tools = typeof product.tools === 'string' ? JSON.parse(product.tools) : product.tools;
                if (Array.isArray(tools)) {
                    tools.forEach(tool => {
                        const toolBadge = document.createElement('div');
                        toolBadge.className = 'tool-badge';
                        toolBadge.innerHTML = `
                            <i class="fas fa-${getToolIcon(tool)}"></i>
                            ${tool}
                        `;
                        toolsGrid.appendChild(toolBadge);
                    });
                }
            } catch (e) {
                console.error('Error parsing tools:', e);
            }
        }
    }
function getToolIcon(tool) {
    const icons = {
        'WhatsApp': 'whatsapp',
        'OpenAI GPT-4': 'brain',
        'Webhook': 'link',
        'Postgres': 'database',
        'Google Sheets': 'table',
        'Zapier': 'bolt',
        'Slack': 'slack',
        'Gmail': 'envelope',
        'Calendar': 'calendar',
        'Notion': 'file-alt',
        'Stripe': 'credit-card',
        'PayPal': 'paypal',
        'QuickBooks': 'calculator',
        'Zendesk': 'life-ring',
        'Jira': 'tasks',
        'Mailchimp': 'mail-bulk',
        'Google Analytics': 'chart-bar'
    };
    return icons[tool] || 'cog';
}
    // Configurar video promocional (demo explicativo)
    const demoVideo = document.getElementById('demoVideo');
    if (demoVideo) {
        const source = demoVideo.querySelector('source');
        if (product.promotional_video && source) {
            source.src = `uploads/videos/${product.promotional_video}`;
            demoVideo.load();
            demoVideo.style.display = 'block';
        } else {
            demoVideo.style.display = 'none';
        }
    }

    // Configurar video demo (para el celular)
    const phoneVideo = document.getElementById('phoneVideo');
    const phoneScreen = document.querySelector('.phone-screen-detail');
    
    if (phoneVideo && phoneScreen) {
        const source = phoneVideo.querySelector('source');
        if (product.demo_video && source) {
            source.src = `uploads/videos/${product.demo_video}`;
            phoneVideo.load();
            phoneVideo.style.display = 'block';
        } else {
            // Si no hay video demo, mostrar placeholder
            phoneVideo.style.display = 'none';
            phoneScreen.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                    <i class="fas fa-mobile-alt fa-3x"></i>
                </div>
            `;
        }
    }

    // Configurar estadísticas (simuladas o reales)
    const statMessages = document.getElementById('statMessages');
    if (statMessages) {
        statMessages.textContent = Math.floor(Math.random() * 1000) + 100;
    }

    // Configurar botón de compra
    const buyButton = document.getElementById('buyButtonText');
    if (buyButton) {
        buyButton.textContent = product.price > 0 ? `Comprar por $${parseFloat(product.price).toFixed(2)}` : 'Obtener Gratis';
    }
    
    // Guardar ID del producto para la función de compra
    modal.setAttribute('data-product-id', product.id);

    // Mostrar modal
    try {
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    } catch (error) {
        console.error('Error showing modal:', error);
        showErrorNotification('Error al mostrar el modal');
    }
}

// Función para comprar desde el modal detallado
function buyProduct2() {
     console.log("llegue aca");
    const modal = document.getElementById('productDetailModal');
    if (!modal) {
        showErrorNotification('Error: Modal no encontrado');
        return;
    }

    const productId = modal.getAttribute('data-product-id');
    if (!productId) {
        showErrorNotification('Error: ID de producto no encontrado');
        return;
    }

    fetch(`marketplace_actions.php?action=get_product&id=${productId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Respuesta producto:', data);
            if (!data.success) {
                showErrorNotification('Error al obtener información del producto: ' + data.message);
                return;
            }

            const product = data.product;
            const confirmMessage = product.price > 0
                ? `¿Estás seguro de que quieres comprar "${product.title}" por $${parseFloat(product.price).toFixed(2)}?`
                : `¿Estás seguro de que quieres obtener "${product.title}"?`;

            if (!confirm(confirmMessage)) return;
           
            const button = modal.querySelector('.boton_modal');
            if (!button) {
                showErrorNotification('Error: Botón no encontrado');
                return;
            }

            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            button.disabled = true;
            button.style.pointerEvents = 'none';

            fetch('marketplace_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'buy',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Respuesta compra:', data);
                if (data.success) {
                    button.innerHTML = '<i class="fas fa-check me-2"></i>¡Adquirido!';
                    button.style.background = 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)';
                    showSuccessNotification('¡Producto adquirido exitosamente! Ve a "Mis Compras" para activarlo.');

                    setTimeout(() => {
                        loadMarketplaceProducts();
                    }, 2000);
                } else {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                    button.style.pointerEvents = 'auto';
                    showErrorNotification('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error en POST compra:', error);
                button.innerHTML = originalContent;
                button.disabled = false;
                button.style.pointerEvents = 'auto';
                showErrorNotification('Error al procesar la compra');
            });
        })
        .catch(error => {
            console.error('Error en GET producto:', error);
            showErrorNotification('Error al procesar la compra');
        });
}


function processPurchase(productId, title, price) {
    // Mostrar loading en el botón
    const buyButton = document.querySelector('.btn-primary-large');
    if (!buyButton) {
        showErrorNotification('Error: Botón de compra no encontrado');
        return;
    }
    
    const originalContent = buyButton.innerHTML;
    buyButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
    buyButton.disabled = true;

    fetch('marketplace_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'buy',
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar éxito
            buyButton.innerHTML = '<i class="fas fa-check me-2"></i>¡Adquirido!';
            buyButton.style.background = 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)';
            
            // Cerrar modal y mostrar notificación
            setTimeout(() => {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('productDetailModal'));
                if (modalInstance) {
                    modalInstance.hide();
                }
                showSuccessNotification('¡Producto adquirido exitosamente! Ve a "Mis Compras" para activarlo.');
                loadMarketplaceProducts(); // Recargar productos
            }, 1500);
        } else {
            // Restaurar botón en caso de error
            buyButton.innerHTML = originalContent;
            buyButton.disabled = false;
            showErrorNotification('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        buyButton.innerHTML = originalContent;
        buyButton.disabled = false;
        showErrorNotification('Error al procesar la compra');
    });
}


function addToWishlist() {
    showSuccessNotification('¡Agregado a favoritos!');
}
function initializeVideoLazyLoading() {
    const videos = document.querySelectorAll('video[data-src]');
    
    const videoObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const video = entry.target;
                const source = video.querySelector('source[data-src]');
                
                if (source) {
                    source.src = source.dataset.src;
                    video.src = video.dataset.src;
                    video.load();
                    video.removeAttribute('data-src');
                    source.removeAttribute('data-src');
                }
                
                observer.unobserve(video);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });
    
    videos.forEach(video => {
        videoObserver.observe(video);
    });
}

function createProductCard(product) {
    const hasVideo = product.video_filename && product.video_filename.trim() !== '';
    const videoPath = hasVideo ? `uploads/videos/${product.video_filename}` : null;
    
    return `
        <div class="col-lg-4 col-md-6">
            <div class="marketplace-card h-100">
                ${product.featured ? '<div class="featured-badge"><i class="fas fa-star me-1"></i>Destacado</div>' : ''}
                
                <div class="product-media" ${hasVideo ? `onclick="openVideoModal('${escapeHtml(product.title)}', '${product.video_filename}')"` : ''}>
                    <div class="phone-mockup">
                        <div class="phone-screen">
                            ${hasVideo ? `
                                <video class="phone-video" preload="none" data-src="${videoPath}">
                                    <source data-src="${videoPath}#t=0.1" type="video/mp4">
                                </video>
                                <div class="video-overlay">
                                    <div class="play-button">
                                        <i class="fas fa-play"></i>
                                    </div>
                                </div>
                            ` : `
                                <div class="no-video-placeholder">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                            `}
                        </div>
                    </div>
                    ${hasVideo ? `
                        <div class="video-badge">
                            <i class="fas fa-play me-1"></i>Demo disponible
                        </div>
                    ` : ''}
                </div>

                <div class="product-content">
                    <div class="product-header">
                        <div style="flex: 1;">
                            <h5 class="product-title">${escapeHtml(product.title)}</h5>
                            <div class="product-category">${getCategoryInfo(product.category).name}</div>
                        </div>
                    </div>

                    <p class="product-description">${escapeHtml(product.description)}</p>

                    <div class="product-footer mt-auto">
                        <div>
                            <div class="product-price">
                                ${product.price > 0 ? '$' + parseFloat(product.price).toFixed(2) : 'Gratis'}
                            </div>
                            <div class="product-sales">
                                <i class="fas fa-shopping-cart me-1"></i>
                                <span>${product.sales_count} ${product.sales_count === 1 ? 'venta' : 'ventas'}</span>
                            </div>
                        </div>
                        <button class="btn btn-buy" onclick="buyProduct(${product.id}, '${escapeHtml(product.title)}', ${product.price})" data-product-id="${product.id}">
                            <i class="fas fa-shopping-cart me-2"></i>
                            ${product.price > 0 ? 'Comprar' : 'Obtener'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

    function getCategoryInfo(category) {
    const categories = {
        'general': { name: 'General', icon: 'fas fa-cog' },
        'customer_service': { name: 'Atención al Cliente', icon: 'fas fa-headset' },
        'sales': { name: 'Ventas', icon: 'fas fa-chart-line' },
        'marketing': { name: 'Marketing', icon: 'fas fa-bullhorn' },
        'messaging': { name: 'Mensajería', icon: 'fas fa-comments' },
        'finance': { name: 'Finanzas', icon: 'fas fa-dollar-sign' },
        'hr': { name: 'Recursos Humanos', icon: 'fas fa-users' }
    };
    return categories[category] || { name: 'General', icon: 'fas fa-cog' };
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

        function viewMarketplaceProduct(productId) {
            // Aquí podrías mostrar más detalles del producto
            console.log('Ver producto:', productId);
        }

   function openVideoModal(title, videoFilename) {
    if (!videoFilename) {
        showErrorNotification('Video no disponible');
        return;
    }
    
    const videoModal = document.getElementById('videoModal');
    const videoModalTitle = document.getElementById('videoModalTitle');
    const modalVideoSource = document.getElementById('modalVideoSource');
    const modalVideo = document.getElementById('modalVideo');
    
    // Verificar que todos los elementos existen
    if (!videoModal || !videoModalTitle || !modalVideoSource || !modalVideo) {
        console.error('Elementos del modal de video no encontrados');
        showErrorNotification('Error: Modal de video no está disponible');
        return;
    }
    
    videoModalTitle.innerHTML = `
        <i class="fas fa-play me-2"></i>
        Demo: ${escapeHtml(title)}
    `;
    
    modalVideoSource.src = `uploads/videos/${videoFilename}`;
    modalVideo.load();
    
    try {
        const modal = new bootstrap.Modal(videoModal);
        modal.show();
        
        // Pausar video cuando se cierre el modal
        videoModal.addEventListener('hidden.bs.modal', function () {
            modalVideo.pause();
            modalVideo.currentTime = 0;
        }, { once: true });
    } catch (error) {
        console.error('Error showing video modal:', error);
        showErrorNotification('Error al mostrar el video');
    }
}

    function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

      function buyProduct(productId, title, price) {
        console.log("soy yo");
    const confirmMessage = price > 0 
        ? `¿Estás seguro de que quieres comprar "${title}" por $${price}?`
        : `¿Estás seguro de que quieres obtener "${title}"?`;
        
    if (confirm(confirmMessage)) {
        // Mostrar loading en el botón específico
        const button = document.querySelector(`[data-product-id="${productId}"]`);
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
        button.disabled = true;
        button.style.pointerEvents = 'none';

        fetch('marketplace_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'buy',
                product_id: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar éxito con animación
                button.innerHTML = '<i class="fas fa-check me-2"></i>¡Adquirido!';
                button.style.background = 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)';
                
                // Mostrar notificación de éxito
                showSuccessNotification('¡Producto adquirido exitosamente! Ve a "Mis Compras" para activarlo.');
                
                setTimeout(() => {
                    loadMarketplaceProducts(); // Recargar productos
                }, 2000);
            } else {
                // Restaurar botón en caso de error
                button.innerHTML = originalContent;
                button.disabled = false;
                button.style.pointerEvents = 'auto';
                showErrorNotification('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = originalContent;
            button.disabled = false;
            button.style.pointerEvents = 'auto';
            showErrorNotification('Error al procesar la compra');
        });
    }
}

function showSuccessNotification(message) {
    showNotification(message, 'success');
}

function showErrorNotification(message) {
    showNotification(message, 'error');
}

function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
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

        function showMyPurchases() {
            const container = document.getElementById('marketplace-content');
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-3">Cargando tus compras...</p>
                </div>
            `;

            fetch('marketplace_actions.php?action=my_purchases')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMyPurchases(data.purchases);
                    } else {
                        container.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error al cargar las compras: ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error al conectar con el servidor
                        </div>
                    `;
                });
        }

        function displayMyPurchases(purchases) {
            const container = document.getElementById('marketplace-content');
            
            if (purchases.length === 0) {
                container.innerHTML = `
                    <div class="content-card text-center py-5">
                        <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No tienes compras aún</h5>
                        <p class="text-muted">Explora el marketplace para encontrar automatizaciones útiles</p>
                        <button class="btn btn-primary" onclick="loadMarketplaceProducts()">
                            <i class="fas fa-store me-2"></i>
                            Ver Marketplace
                        </button>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Mis Compras</h3>
                    <button class="btn btn-outline-primary" onclick="loadMarketplaceProducts()">
                        <i class="fas fa-arrow-left me-2"></i>
                        Volver al Marketplace
                    </button>
                </div>
                <div class="row">
            `;
            
            purchases.forEach(purchase => {
                const statusInfo = getStatusInfo(purchase.status);
                html += `
                    <div class="col-md-6 mb-4">
                        <div class="content-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="mb-0">${purchase.title}</h5>
                                    <span class="badge ${statusInfo.class}">${statusInfo.text}</span>
                                </div>
                                <p class="text-muted mb-3">${purchase.description}</p>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Comprado: ${new Date(purchase.purchased_at).toLocaleDateString()}
                                    </small>
                                    ${purchase.activated_at ? `
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-rocket me-1"></i>
                                            Activado: ${new Date(purchase.activated_at).toLocaleDateString()}
                                        </small>
                                    ` : ''}
                                </div>
                                ${purchase.status === 'purchased' ? `
                                    <button class="btn btn-primary" onclick="showActivateModal(${purchase.id})">
                                        <i class="fas fa-rocket me-2"></i>
                                        Activar Automatización
                                    </button>
                                ` : purchase.status === 'activated' ? `
                                    <div>
                                        <p class="mb-2">
                                            <strong>ID de Automatización:</strong> 
                                            <code>${purchase.automation_id}</code>
                                        </p>
                                        <button class="btn btn-outline-primary btn-sm" onclick="goToAutomations()">
                                            <i class="fas fa-cog me-1"></i>
                                            Ver en Automatizaciones
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function getStatusInfo(status) {
            const statusMap = {
                'purchased': { text: 'Pendiente de Activación', class: 'bg-warning text-dark' },
                'activated': { text: 'Activado', class: 'bg-success' },
                'inactive': { text: 'Inactivo', class: 'bg-secondary' }
            };
            return statusMap[status] || { text: 'Desconocido', class: 'bg-light text-dark' };
        }

        function showActivateModal(purchaseId) {
            document.getElementById('purchaseId').value = purchaseId;
            document.getElementById('activationAutomationId').value = '';
            new bootstrap.Modal(document.getElementById('activatePurchaseModal')).show();
        }

        function activatePurchase() {
            const purchaseId = document.getElementById('purchaseId').value;
            const automationId = document.getElementById('activationAutomationId').value.trim();
            
            if (!automationId) {
                alert('Por favor ingresa un ID de automatización');
                return;
            }

            fetch('marketplace_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'activate',
                    purchase_id: purchaseId,
                    automation_id: automationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('activatePurchaseModal')).hide();
                    alert('¡Automatización activada exitosamente!');
                    showMyPurchases();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al activar la automatización');
            });
        }

        function goToAutomations() {
            showAutomations();
        }

        // Función para agregar automatización
       // REEMPLAZAR temporalmente la función addAutomation por esta versión de debug:

function addAutomation() {
    const form = document.getElementById('addAutomationForm');
    const formData = new FormData(form);
    
    // Validaciones del lado cliente
    const name = formData.get('name').trim();
    const automationId = formData.get('automation_id').trim();
    const description = formData.get('description').trim();
    
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
            // Restaurar botón
            submitButton.innerHTML = originalButtonText;
            submitButton.disabled = false;
            submitButton.style.background = '';
            
            showErrorNotification('Error: ' + data.message);
        }
    })
    .catch(error => {
        // Restaurar botón
        submitButton.innerHTML = originalButtonText;
        submitButton.disabled = false;
        submitButton.style.background = '';
        
        showErrorNotification('Error: ' + error.message);
    });
}

        // Función para toggle de automatización
        document.querySelectorAll('.automation-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const automationId = this.dataset.automationId;
                const isActive = this.checked;
                const id = this.dataset.id;
                
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
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        this.checked = !this.checked; // Revertir el toggle
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cambiar el estado de la automatización');
                    this.checked = !this.checked; // Revertir el toggle
                });
            });
        });

        function startAllAgents() {
            if (confirm('¿Estás seguro de que quieres activar todas las automatizaciones?')) {
                fetch('action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'start_all' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al activar todas las automatizaciones');
                });
            }
        }

        function pauseAllAgents() {
            if (confirm('¿Estás seguro de que quieres pausar todas las automatizaciones?')) {
                fetch('action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'pause_all' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al pausar todas las automatizaciones');
                });
            }
        }

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            if (document.getElementById('dashboard-view').style.display !== 'none') {
                fetch('stats.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const statsValues = document.querySelectorAll('.stats-value');
                            if (statsValues.length >= 4) {
                                statsValues[0].textContent = data.data.active_automations;
                                statsValues[1].textContent = data.data.messages_per_hour;
                                statsValues[2].textContent = data.data.active_webhooks;
                            }
                        }
                    })
                    .catch(error => console.log('Stats update failed:', error));
            }
        }, 30000);

        // Navegación del sidebar
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                if (href === '#automations') {
                    e.preventDefault();
                    showAutomations();
                } else if (href === '#marketplace') {
                    e.preventDefault();
                    showMarketplace();
                } else if (href === '#users') {
                    e.preventDefault();
                    showUsers();
                } else if (href === '#' && this.textContent.trim().includes('Dashboard')) {
                    e.preventDefault();
                    showDashboard();
                }
            });
        });

     <?php if ($is_admin): ?>
// Inicializar DataTable para automatizaciones (solo admin)
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
            ]
        });
    }
}

// Función para ver detalles de automatización
function viewAutomationDetails(automationId) {
    // Aquí puedes implementar un modal con más detalles
    fetch(`get_automation_details.php?id=${automationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar modal con detalles
                showAutomationDetailsModal(data.automation);
            } else {
                alert('Error al obtener detalles: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al obtener detalles de la automatización');
        });
}

// Función para alternar estado de automatización desde admin
function adminToggleAutomation(automationId, isActive) {
    const action = isActive ? 'activar' : 'pausar';
    
    if (confirm(`¿Estás seguro de que quieres ${action} esta automatización?`)) {
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cambiar el estado de la automatización');
        });
    }
}

// Función para impersonar usuario
function impersonateUser(userId, username) {
    if (confirm(`¿Quieres ver el dashboard como el usuario "${username}"?`)) {
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al impersonar usuario');
        });
    }
}

// Modificar la función showAutomations para inicializar la tabla
function showAutomations() {
    document.getElementById('dashboard-view').style.display = 'none';
    document.getElementById('automations-view').style.display = 'block';
    document.getElementById('marketplace-view').style.display = 'none';
    document.getElementById('users-view').style.display = 'none';
    
    // Actualizar navegación activa
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    document.querySelector('[href="#automations"]').classList.add('active');
    
    // Inicializar tabla si es admin
    setTimeout(() => {
        initializeAutomationsTable();
    }, 100);
}

<?php endif; ?>   

// Función para mostrar modal de detalles de automatización
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

    // Llenar contenido del modal con descripción incluida
    const modalBody = document.getElementById('automationDetailsBody');
    modalBody.innerHTML = `
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

    // Mostrar modal con animación
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

// Función para terminar la impersonación
function stopImpersonation() {
    if (confirm('¿Estás seguro de que quieres volver al modo administrador?')) {
        fetch('stop_impersonation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al terminar la impersonación');
        });
    }
}

<?php if ($is_admin): ?>
// Inicializar DataTable para automatizaciones (solo admin)
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
            ]
        });
    }
}

// Función para ver detalles de automatización
function viewAutomationDetails(automationId) {
    // Mostrar loading
    const button = document.querySelector(`[onclick="viewAutomationDetails(${automationId})"]`);
    if (button) {
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
        }, 5000);
    }

    fetch(`get_automation_details.php?id=${automationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAutomationDetailsModal(data.automation);
            } else {
                alert('Error al obtener detalles: ' + data.message);
            }
            
            // Restaurar botón
            if (button) {
                button.innerHTML = '<i class="fas fa-eye"></i>';
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al obtener detalles de la automatización');
            
            // Restaurar botón
            if (button) {
                button.innerHTML = '<i class="fas fa-eye"></i>';
                button.disabled = false;
            }
        });
}

// Función para alternar estado de automatización desde admin
function adminToggleAutomation(automationId, isActive) {
    const action = isActive ? 'activar' : 'pausar';
    
    if (confirm(`¿Estás seguro de que quieres ${action} esta automatización?`)) {
        // Mostrar loading en el botón
        const button = document.querySelector(`[onclick="adminToggleAutomation(${automationId}, ${isActive})"]`);
        if (button) {
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessNotification(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showErrorNotification('Error: ' + data.message);
                // Restaurar botón
                if (button) {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorNotification('Error al cambiar el estado de la automatización');
            // Restaurar botón
            if (button) {
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        });
    }
}

// Función mejorada para impersonar usuario
function impersonateUser(userId, username) {
    if (confirm(`⚠️ ACCIÓN DE ADMINISTRADOR ⚠️\n\nVas a ver el dashboard como el usuario "${username}".\nPodrás realizar acciones en su nombre.\n\n¿Estás seguro de continuar?`)) {
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessNotification(`Ahora estás viendo como ${username}`);
                setTimeout(() => location.reload(), 1500);
            } else {
                showErrorNotification('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorNotification('Error al impersonar usuario');
        });
    }
}

// Modificar la función showAutomations para inicializar la tabla
const originalShowAutomations = showAutomations;
showAutomations = function() {
    document.getElementById('dashboard-view').style.display = 'none';
    document.getElementById('automations-view').style.display = 'block';
    document.getElementById('marketplace-view').style.display = 'none';
    document.getElementById('users-view').style.display = 'none';
    
    // Actualizar navegación activa
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    const automationsLink = document.querySelector('[href="#automations"]');
    if (automationsLink) {
        automationsLink.classList.add('active');
    }
    
    // Inicializar tabla si es admin
    setTimeout(() => {
        initializeAutomationsTable();
    }, 100);
};

<?php endif; ?>
    </script>
</body>
</html>