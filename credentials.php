<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$database = new Database();
$db = $database->getConnection();

// Manejar peticiones AJAX
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_credential') {
        header('Content-Type: application/json');
        $credential_id = (int)$_GET['id'];
        $query = "SELECT * FROM user_credentials WHERE id = :id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $credential_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $credential = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($credential) {
            echo json_encode(['success' => true, 'credential' => $credential]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Credencial no encontrada']);
        }
        exit;
    }
    
    if ($_GET['action'] === 'test_existing_credential') {
        header('Content-Type: application/json');
        $credential_id = (int)$_GET['id'];
        $query = "SELECT * FROM user_credentials WHERE id = :id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $credential_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $credential = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$credential) {
            echo json_encode(['success' => false, 'message' => 'Credencial no encontrada']);
            exit;
        }
        
        $result = ['success' => false, 'message' => 'Tipo de servicio no soportado para pruebas'];
        
        switch ($credential['service_type']) {
            case 'evolution_api':
                $result = testEvolutionConnection($credential['api_url'], $credential['api_key']);
                break;
            case 'openai':
                $result = testOpenAIConnection($credential['api_key']);
                break;
            case 'webhook':
                $result = testWebhookConnection($credential['api_url']);
                break;
        }
        
        echo json_encode($result);
        exit;
    }
}

// Funciones de prueba
function testEvolutionConnection($api_url, $api_key) {
    try {
        $api_url = rtrim($api_url, '/');
        $test_url = $api_url . '/instance/fetchInstances';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'message' => 'No se pudo conectar con la API'];
        }
        
        if ($http_code === 200 || $http_code === 201) {
            return ['success' => true, 'message' => 'Conexión exitosa'];
        } else {
            return ['success' => false, 'message' => 'Código de respuesta HTTP: ' . $http_code];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function testOpenAIConnection($api_key) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'message' => 'No se pudo conectar con OpenAI'];
        }
        
        if ($http_code === 200) {
            return ['success' => true, 'message' => 'API Key válida'];
        } elseif ($http_code === 401) {
            return ['success' => false, 'message' => 'API Key inválida'];
        } else {
            return ['success' => false, 'message' => 'Error HTTP: ' . $http_code];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function testWebhookConnection($webhook_url) {
    try {
        $test_data = [
            'test' => true,
            'timestamp' => time(),
            'message' => 'Prueba de conexión desde n8n CRM'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: n8n-CRM-Test'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'message' => 'No se pudo conectar con el webhook'];
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true, 'message' => 'Webhook accesible'];
        } else {
            return ['success' => false, 'message' => 'Código de respuesta: ' . $http_code];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Procesar formulario si se envió
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
       if ($_POST['action'] === 'save_credential') {
    $service_type = trim($_POST['service_type']);
    $service_name = trim($_POST['service_name']);
    
    if (empty($service_type) || empty($service_name)) {
        $message = 'Por favor completa el tipo de servicio y el nombre.';
        $messageType = 'danger';
    } else {
                try {
                    $additional_config = [];
                    $api_url = null;
                    $api_key = null;
                    $instance_name = null;
                    
                    switch ($service_type) {
                        case 'evolution_api':
                            $api_url = trim($_POST['api_url']);
                            $api_key = trim($_POST['api_key']);
                            $instance_name = trim($_POST['instance_name']);
                            
                            if (empty($api_url) || empty($api_key) || empty($instance_name)) {
                                throw new Exception('Por favor completa todos los campos de Evolution API.');
                            }
                            break;
                            
                        case 'openai':
                            $api_key = trim($_POST['openai_api_key']);
                            if (empty($api_key)) {
                                throw new Exception('Por favor ingresa la API Key de OpenAI.');
                            }
                            if (!empty($_POST['openai_model'])) {
                                $additional_config['model'] = trim($_POST['openai_model']);
                            }
                            break;
                            
                        case 'google_sheets':
                            $credentials_json = trim($_POST['google_credentials']);
                            if (empty($credentials_json)) {
                                throw new Exception('Por favor ingresa las credenciales JSON de Google.');
                            }
                            $json_test = json_decode($credentials_json, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new Exception('Las credenciales JSON no son válidas.');
                            }
                            $additional_config['credentials'] = $credentials_json;
                            break;
                            
                        case 'telegram':
                            $api_key = trim($_POST['telegram_token']);
                            if (empty($api_key)) {
                                throw new Exception('Por favor ingresa el token del bot de Telegram.');
                            }
                            break;
                            
                        case 'webhook':
                            $api_url = trim($_POST['webhook_url']);
                            if (empty($api_url)) {
                                throw new Exception('Por favor ingresa la URL del webhook.');
                            }
                            if (!empty($_POST['webhook_secret'])) {
                                $additional_config['secret'] = trim($_POST['webhook_secret']);
                            }
                            break;
                            
                        case 'database':
                            $db_host = trim($_POST['db_host']);
                            $db_name = trim($_POST['db_name']);
                            $db_user = trim($_POST['db_user']);
                            $db_password = trim($_POST['db_password']);
                            
                            if (empty($db_host) || empty($db_name) || empty($db_user) || empty($db_password)) {
                                throw new Exception('Por favor completa todos los campos de la base de datos.');
                            }
                            
                            $additional_config = [
                                'host' => $db_host,
                                'port' => !empty($_POST['db_port']) ? (int)$_POST['db_port'] : 3306,
                                'database' => $db_name,
                                'username' => $db_user,
                                'password' => $db_password
                            ];
                            break;
                            
                        case 'email_smtp':
                            $smtp_host = trim($_POST['smtp_host']);
                            $smtp_user = trim($_POST['smtp_user']);
                            $smtp_password = trim($_POST['smtp_password']);
                            
                            if (empty($smtp_host) || empty($smtp_user) || empty($smtp_password)) {
                                throw new Exception('Por favor completa todos los campos SMTP obligatorios.');
                            }
                            
                            $additional_config = [
                                'host' => $smtp_host,
                                'port' => !empty($_POST['smtp_port']) ? (int)$_POST['smtp_port'] : 587,
                                'security' => $_POST['smtp_security'] ?? 'tls',
                                'username' => $smtp_user,
                                'password' => $smtp_password
                            ];
                            break;
                            
                        case 'other':
                            $other_config = trim($_POST['other_config']);
                            if (empty($other_config)) {
                                throw new Exception('Por favor ingresa la configuración JSON.');
                            }
                            $json_test = json_decode($other_config, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new Exception('La configuración JSON no es válida.');
                            }
                            $additional_config = $json_test;
                            break;
                    }
                    
                    $query = "INSERT INTO user_credentials (user_id, service_type, service_name, api_url, api_key, instance_name, additional_config) 
                              VALUES (:user_id, :service_type, :service_name, :api_url, :api_key, :instance_name, :additional_config)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->bindParam(':service_type', $service_type);
                    $stmt->bindParam(':service_name', $service_name);
                    $stmt->bindParam(':api_url', $api_url);
                    $stmt->bindParam(':api_key', $api_key);
                    $stmt->bindParam(':instance_name', $instance_name);
                    $stmt->bindParam(':additional_config', json_encode($additional_config));
                    
                       if ($stmt->execute()) {
                // AGREGAR ESTAS LÍNEAS DESPUÉS DEL EXECUTE EXITOSO
                $_SESSION['flash_message'] = 'Credenciales guardadas exitosamente.';
                $_SESSION['flash_type'] = 'success';
                header('Location: credentials.php');
                exit; // MUY IMPORTANTE: salir aquí
            } else {
                $message = 'Error al guardar las credenciales.';
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
            }
        } elseif ($_POST['action'] === 'delete_credential') {
            $credential_id = (int)$_POST['credential_id'];
            
            try {
                $query = "DELETE FROM user_credentials WHERE id = :id AND user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $credential_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $message = 'Credencial eliminada exitosamente.';
                    $messageType = 'success';
                } else {
                    $message = 'Error al eliminar la credencial.';
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } elseif ($_POST['action'] === 'toggle_credential') {
            $credential_id = (int)$_POST['credential_id'];
            $is_active = (int)$_POST['is_active'];
            
            try {
                $query = "UPDATE user_credentials SET is_active = :is_active WHERE id = :id AND user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':is_active', $is_active);
                $stmt->bindParam(':id', $credential_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $message = $is_active ? 'Credencial activada.' : 'Credencial desactivada.';
                    $messageType = 'success';
                } else {
                    $message = 'Error al cambiar el estado de la credencial.';
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } elseif ($_POST['action'] === 'test_connection') {
            $service_type = $_POST['service_type'] ?? '';
            
            switch ($service_type) {
                case 'evolution_api':
                    $api_url = trim($_POST['api_url']);
                    $api_key = trim($_POST['api_key']);
                    
                    $test_result = testEvolutionConnection($api_url, $api_key);
                    if ($test_result['success']) {
                        $message = 'Conexión exitosa con Evolution API.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error de conexión: ' . $test_result['message'];
                        $messageType = 'danger';
                    }
                    break;
                    
                case 'openai':
                    $api_key = trim($_POST['openai_api_key']);
                    $test_result = testOpenAIConnection($api_key);
                    if ($test_result['success']) {
                        $message = 'Conexión exitosa con OpenAI.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error de conexión OpenAI: ' . $test_result['message'];
                        $messageType = 'danger';
                    }
                    break;
                    
                case 'webhook':
                    $webhook_url = trim($_POST['webhook_url']);
                    $test_result = testWebhookConnection($webhook_url);
                    if ($test_result['success']) {
                        $message = 'Webhook accesible correctamente.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error de webhook: ' . $test_result['message'];
                        $messageType = 'danger';
                    }
                    break;
                    
                default:
                    $message = 'Prueba de conexión no disponible para este tipo de servicio.';
                    $messageType = 'warning';
            }
        }
    }
}

// Obtener credenciales del usuario
$query = "SELECT * FROM user_credentials WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$credentials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si es admin
$is_admin = false;
$query = "SELECT role FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$role_check = $stmt->fetch(PDO::FETCH_ASSOC);
if ($role_check && $role_check['role'] === 'admin') {
    $is_admin = true;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credenciales - n8n CRM Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .btn-success {
            background: var(--success-gradient);
            border: none;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-danger {
            background: var(--warning-gradient);
            border: none;
            border-radius: 8px;
            font-weight: 600;
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
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-text {
            color: var(--text-secondary);
        }

        .credential-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .credential-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .credential-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .credential-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .credential-url {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin: 0.25rem 0;
        }

        .credential-actions {
            display: flex;
            gap: 0.5rem;
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

        .status-badge.active {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.2);
            color: #6b7280;
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

        .alert {
            border-radius: 12px;
            border: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
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

        .service-fields {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <nav class="sidebar">
            <div class="sidebar-brand">
                <h4><i class="fas fa-robot me-2"></i> n8n CRM Control Panel</h4>
                <p>Gestión de credenciales y configuraciones</p>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Navigation</div>
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="dashboard.php#automations" class="nav-link">
                        <i class="fas fa-cogs"></i>
                        Agentes y Automatizaciones
                    </a>
                </div>
                <div class="nav-item">
                    <a href="dashboard.php#marketplace" class="nav-link">
                        <i class="fas fa-store"></i>
                        Marketplace
                    </a>
                </div>
                <div class="nav-item">
                    <a href="credentials.php" class="nav-link active">
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
                    <a href="dashboard.php#users" class="nav-link">
                        <i class="fas fa-users-cog"></i>
                        Gestión de Usuarios
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
        <main class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Credenciales</h1>
                        <p class="page-subtitle">Gestiona tus credenciales de Evolution API y otros servicios</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCredentialModal">
                        <i class="fas fa-plus me-2"></i>
                        Agregar Credencial
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle me-2"></i>
                        Evolution API
                    </h3>
                    <p class="card-subtitle">Información sobre la configuración de Evolution API</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p class="mb-3">Evolution API es la solución que utilizamos para conectar con WhatsApp Business. Para configurar correctamente tu instancia necesitas:</p>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>URL de la API:</strong> La URL base de tu servidor Evolution API</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>API Key:</strong> La clave de acceso para autenticarte</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Nombre de Instancia:</strong> Un identificador único para tu instancia de WhatsApp</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fab fa-whatsapp fa-4x text-success mb-3"></i>
                            <p class="text-muted small">Conecta tu WhatsApp Business con Evolution API</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($credentials)): ?>
                <div class="content-card">
                    <div class="card-body">
                        <div class="empty-state">
                            <i class="fas fa-key fa-4x"></i>
                            <h4>No tienes credenciales configuradas</h4>
                            <p>Agrega tu primera credencial de Evolution API para comenzar a usar el sistema</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCredentialModal">
                                <i class="fas fa-plus me-2"></i>
                                Agregar Primera Credencial
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">Mis Credenciales</h3>
                        <p class="card-subtitle"><?php echo count($credentials); ?> credencial(es) configurada(s)</p>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php foreach ($credentials as $credential): ?>
                            <div class="credential-card" style="margin: 1.5rem; margin-bottom: 1rem;">
                                <div class="credential-header">
                                    <div style="flex: 1;">
                                        <h5 class="credential-name">
                                            <?php
                                            $icon = match($credential['service_type']) {
                                                'evolution_api' => 'fab fa-whatsapp text-success',
                                                'openai' => 'fas fa-brain text-primary',
                                                'google_sheets' => 'fas fa-table text-success',
                                                'telegram' => 'fab fa-telegram text-info',
                                                'webhook' => 'fas fa-link text-warning',
                                                'database' => 'fas fa-database text-info',
                                                'email_smtp' => 'fas fa-envelope text-primary',
                                                default => 'fas fa-cog text-secondary'
                                            };
                                            ?>
                                            <i class="<?php echo $icon; ?> me-2"></i>
                                            <?php echo htmlspecialchars($credential['service_name']); ?>
                                        </h5>
                                        <p class="credential-url">
                                            <i class="fas fa-tag me-1"></i>
                                            Tipo: <?php 
                                            echo match($credential['service_type']) {
                                                'evolution_api' => 'Evolution API (WhatsApp)',
                                                'openai' => 'OpenAI GPT',
                                                'google_sheets' => 'Google Sheets',
                                                'telegram' => 'Telegram Bot',
                                                'webhook' => 'Webhook',
                                                'database' => 'Base de Datos',
                                                'email_smtp' => 'Email SMTP',
                                                default => 'Otro'
                                            };
                                            ?>
                                        </p>
                                        <?php if (!empty($credential['api_url'])): ?>
                                            <p class="credential-url">
                                                <i class="fas fa-link me-1"></i>
                                                <?php echo htmlspecialchars($credential['api_url']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($credential['instance_name'])): ?>
                                            <p class="credential-url">
                                                <i class="fas fa-server me-1"></i>
                                                Instancia: <?php echo htmlspecialchars($credential['instance_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Creada: <?php echo date('d/m/Y H:i', strtotime($credential['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="status-badge <?php echo $credential['is_active'] ? 'active' : 'inactive'; ?> me-3">
                                            <?php echo $credential['is_active'] ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                        <label class="switch me-3">
                                            <input type="checkbox" 
                                                   class="credential-toggle" 
                                                   data-id="<?php echo $credential['id']; ?>" 
                                                   <?php echo $credential['is_active'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <div class="credential-actions">
                                            <?php if (in_array($credential['service_type'], ['evolution_api', 'openai', 'webhook'])): ?>
                                                <button class="btn btn-outline-primary btn-sm" onclick="testConnection(<?php echo $credential['id']; ?>)" title="Probar conexión">
                                                    <i class="fas fa-plug"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-info btn-sm" onclick="viewCredentialDetails(<?php echo $credential['id']; ?>)" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteCredential(<?php echo $credential['id']; ?>)" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal para agregar credencial -->
    <div class="modal fade" id="addCredentialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Agregar Nueva Credencial
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="credentialForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save_credential">
                        
                        <div class="mb-3">
                            <label for="service_type" class="form-label">Tipo de Servicio</label>
                            <select class="form-control" id="service_type" name="service_type" required onchange="updateCredentialFields()">
                                <option value="">Selecciona un tipo de servicio</option>
                                <option value="evolution_api">Evolution API (WhatsApp)</option>
                                <option value="openai">OpenAI GPT</option>
                                <option value="google_sheets">Google Sheets</option>
                                <option value="telegram">Telegram Bot</option>
                                <option value="webhook">Webhook URL</option>
                                <option value="database">Base de Datos</option>
                                <option value="email_smtp">Email SMTP</option>
                                <option value="other">Otro</option>
                            </select>
                            <div class="form-text">Selecciona el tipo de servicio para mostrar los campos correspondientes</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="service_name" class="form-label">Nombre del Servicio</label>
                            <input type="text" class="form-control" id="service_name" name="service_name" required 
                                   placeholder="Ej: Mi WhatsApp Business">
                            <div class="form-text">Un nombre descriptivo para identificar esta configuración</div>
                        </div>

                        <!-- Campos específicos para Evolution API -->
                        <div id="evolution_api_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="api_url" class="form-label">URL de la API</label>
                                <input type="url" class="form-control" id="api_url" name="api_url" 
                                       placeholder="https://tu-servidor.com:8080">
                                <div class="form-text">La URL completa de tu servidor Evolution API</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="api_key" class="form-label">API Key</label>
                                <input type="text" class="form-control" id="api_key" name="api_key" 
                                       placeholder="Tu clave de API">
                                <div class="form-text">La clave de autenticación proporcionada por Evolution API</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="instance_name" class="form-label">Nombre de Instancia</label>
                                <input type="text" class="form-control" id="instance_name" name="instance_name" 
                                       placeholder="mi-instancia-whatsapp">
                                <div class="form-text">Identificador único para tu instancia de WhatsApp</div>
                            </div>
                        </div>

                        <!-- Campos específicos para OpenAI -->
                        <div id="openai_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="openai_api_key" class="form-label">API Key de OpenAI</label>
                                <input type="password" class="form-control" id="openai_api_key" name="openai_api_key" 
                                       placeholder="sk-...">
                                <div class="form-text">Tu clave API de OpenAI</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="openai_model" class="form-label">Modelo (Opcional)</label>
                                <select class="form-control" id="openai_model" name="openai_model">
                                    <option value="">Seleccionar modelo</option>
                                    <option value="gpt-4">GPT-4</option>
                                    <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                </select>
                            </div>
                        </div>

                        <!-- Campos específicos para Google Sheets -->
                        <div id="google_sheets_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="google_credentials" class="form-label">Credenciales JSON</label>
                                <textarea class="form-control" id="google_credentials" name="google_credentials" rows="5"
                                          placeholder='{"type": "service_account", ...}'></textarea>
                                <div class="form-text">JSON de credenciales de la cuenta de servicio de Google</div>
                            </div>
                        </div>

                        <!-- Campos específicos para Telegram Bot -->
                        <div id="telegram_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="telegram_token" class="form-label">Bot Token</label>
                                <input type="password" class="form-control" id="telegram_token" name="telegram_token" 
                                       placeholder="123456789:ABCDEF...">
                                <div class="form-text">Token del bot proporcionado por @BotFather</div>
                            </div>
                        </div>

                        <!-- Campos específicos para Webhook -->
                        <div id="webhook_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="webhook_url" class="form-label">URL del Webhook</label>
                                <input type="url" class="form-control" id="webhook_url" name="webhook_url" 
                                       placeholder="https://ejemplo.com/webhook">
                                <div class="form-text">URL donde se enviarán los datos</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="webhook_secret" class="form-label">Secret (Opcional)</label>
                                <input type="password" class="form-control" id="webhook_secret" name="webhook_secret" 
                                       placeholder="clave-secreta">
                                <div class="form-text">Clave secreta para validar el webhook</div>
                            </div>
                        </div>

                        <!-- Campos específicos para Base de Datos -->
                        <div id="database_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Host</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" 
                                       placeholder="localhost">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_port" class="form-label">Puerto</label>
                                        <input type="number" class="form-control" id="db_port" name="db_port" 
                                               placeholder="3306">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_name" class="form-label">Base de Datos</label>
                                        <input type="text" class="form-control" id="db_name" name="db_name" 
                                               placeholder="nombre_db">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_user" class="form-label">Usuario</label>
                                        <input type="text" class="form-control" id="db_user" name="db_user" 
                                               placeholder="usuario">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_password" class="form-label">Contraseña</label>
                                        <input type="password" class="form-control" id="db_password" name="db_password" 
                                               placeholder="contraseña">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos específicos para Email SMTP -->
                        <div id="email_smtp_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="smtp_host" class="form-label">Servidor SMTP</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                       placeholder="smtp.gmail.com">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_port" class="form-label">Puerto</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                               placeholder="587">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_security" class="form-label">Seguridad</label>
                                        <select class="form-control" id="smtp_security" name="smtp_security">
                                            <option value="tls">TLS</option>
                                            <option value="ssl">SSL</option>
                                            <option value="none">Ninguna</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_user" class="form-label">Usuario/Email</label>
                                        <input type="email" class="form-control" id="smtp_user" name="smtp_user" 
                                               placeholder="usuario@ejemplo.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_password" class="form-label">Contraseña</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                               placeholder="contraseña">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos genéricos para "Otro" -->
                        <div id="other_fields" class="service-fields">
                            <div class="mb-3">
                                <label for="other_config" class="form-label">Configuración (JSON)</label>
                                <textarea class="form-control" id="other_config" name="other_config" rows="5"
                                          placeholder='{"key1": "value1", "key2": "value2"}'></textarea>
                                <div class="form-text">Configuración en formato JSON</div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info" id="service-info" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="service-info-text"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-primary" onclick="testConnectionModal()">
                            <i class="fas fa-plug me-2"></i>
                            Probar Conexión
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Guardar Credencial
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que quieres eliminar esta credencial?</p>
                    <p class="text-muted">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-2"></i>
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let credentialToDelete = null;

        function updateCredentialFields() {
            const serviceType = document.getElementById('service_type').value;
            const serviceInfo = document.getElementById('service-info');
            const serviceInfoText = document.getElementById('service-info-text');
            
            document.querySelectorAll('.service-fields').forEach(field => {
                field.style.display = 'none';
            });
            
            document.querySelectorAll('.service-fields input, .service-fields textarea, .service-fields select').forEach(input => {
                input.removeAttribute('required');
            });
            
            let fieldsToShow = '';
            let infoText = '';
            let requiredFields = [];
            
            switch(serviceType) {
                case 'evolution_api':
                    fieldsToShow = 'evolution_api_fields';
                    infoText = 'Evolution API te permite conectar con WhatsApp Business. Necesitas un servidor Evolution API configurado.';
                    requiredFields = ['api_url', 'api_key', 'instance_name'];
                    break;
                    
                case 'openai':
                    fieldsToShow = 'openai_fields';
                    infoText = 'OpenAI GPT te permite usar inteligencia artificial en tus automatizaciones. Necesitas una cuenta de OpenAI.';
                    requiredFields = ['openai_api_key'];
                    break;
                    
                case 'google_sheets':
                    fieldsToShow = 'google_sheets_fields';
                    infoText = 'Google Sheets te permite leer y escribir datos en hojas de cálculo. Necesitas crear una cuenta de servicio.';
                    requiredFields = ['google_credentials'];
                    break;
                    
                case 'telegram':
                    fieldsToShow = 'telegram_fields';
                    infoText = 'Telegram Bot te permite enviar mensajes automáticos. Crea un bot con @BotFather para obtener el token.';
                    requiredFields = ['telegram_token'];
                    break;
                    
                case 'webhook':
                    fieldsToShow = 'webhook_fields';
                    infoText = 'Los webhooks te permiten enviar datos a URLs externas cuando ocurren eventos.';
                    requiredFields = ['webhook_url'];
                    break;
                    
                case 'database':
                    fieldsToShow = 'database_fields';
                    infoText = 'Conecta con bases de datos MySQL, PostgreSQL u otros sistemas de gestión de datos.';
                    requiredFields = ['db_host', 'db_name', 'db_user', 'db_password'];
                    break;
                    
                case 'email_smtp':
                    fieldsToShow = 'email_smtp_fields';
                    infoText = 'SMTP te permite enviar emails desde tus automatizaciones usando tu proveedor de email.';
                    requiredFields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_password'];
                    break;
                    
                case 'other':
                    fieldsToShow = 'other_fields';
                    infoText = 'Configuración personalizada para otros servicios no listados.';
                    requiredFields = ['other_config'];
                    break;
            }
            
            if (fieldsToShow) {
                document.getElementById(fieldsToShow).style.display = 'block';
                serviceInfo.style.display = 'block';
                serviceInfoText.textContent = infoText;
                
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.setAttribute('required', 'required');
                    }
                });
            } else {
                serviceInfo.style.display = 'none';
            }
        }

        document.querySelectorAll('.credential-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const credentialId = this.dataset.id;
                const isActive = this.checked ? 1 : 0;
                
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_credential&credential_id=${credentialId}&is_active=${isActive}`
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Error al cambiar el estado de la credencial');
                        this.checked = !this.checked;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cambiar el estado de la credencial');
                    this.checked = !this.checked;
                });
            });
        });

   function deleteCredential(credentialId) {
    // Prevenir múltiples clicks
    if (credentialToDelete !== null) {
        return; // Ya hay una eliminación en proceso
    }
    
    credentialToDelete = credentialId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (credentialToDelete && !this.disabled) {
        // Deshabilitar el botón inmediatamente
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Eliminando...';
        
        const formData = new FormData();
        formData.append('action', 'delete_credential');
        formData.append('credential_id', credentialToDelete);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                // Cerrar modal antes de recargar
                bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                location.reload();
            } else {
                alert('Error al eliminar la credencial');
                // Rehabilitar botón en caso de error
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-trash me-2"></i>Eliminar';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la credencial');
            // Rehabilitar botón en caso de error
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-trash me-2"></i>Eliminar';
        })
        .finally(() => {
            credentialToDelete = null;
        });
    }
});

        function testConnectionModal() {
            const serviceType = document.getElementById('service_type').value;
            
            if (!serviceType) {
                showNotification('Por favor selecciona un tipo de servicio primero', 'warning');
                return;
            }
            
            let testData = {
                action: 'test_connection',
                service_type: serviceType
            };
            
            switch(serviceType) {
                case 'evolution_api':
                    const apiUrl = document.getElementById('api_url').value.trim();
                    const apiKey = document.getElementById('api_key').value.trim();
                    
                    if (!apiUrl || !apiKey) {
                        showNotification('Por favor completa la URL de la API y la API Key', 'warning');
                        return;
                    }
                    
                    testData.api_url = apiUrl;
                    testData.api_key = apiKey;
                    break;
                    
                case 'openai':
                    const openaiKey = document.getElementById('openai_api_key').value.trim();
                    if (!openaiKey) {
                        showNotification('Por favor ingresa la API Key de OpenAI', 'warning');
                        return;
                    }
                    testData.openai_api_key = openaiKey;
                    break;
                    
                case 'webhook':
                    const webhookUrl = document.getElementById('webhook_url').value.trim();
                    if (!webhookUrl) {
                        showNotification('Por favor ingresa la URL del webhook', 'warning');
                        return;
                    }
                    testData.webhook_url = webhookUrl;
                    break;
                    
                default:
                    showNotification('Prueba de conexión disponible próximamente para este tipo de servicio', 'info');
                    return;
            }
            
            const button = event.target;
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Probando...';
            button.disabled = true;
            
            const formData = new FormData();
            Object.keys(testData).forEach(key => {
                formData.append(key, testData[key]);
            });
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const alertElement = tempDiv.querySelector('.alert');
                
                if (alertElement) {
                    const isSuccess = alertElement.classList.contains('alert-success');
                    const message = alertElement.textContent.replace(/\s+/g, ' ').trim();
                    showNotification(message, isSuccess ? 'success' : 'error');
                } else {
                    showNotification('Conexión probada', 'info');
                }
                
                button.innerHTML = originalContent;
                button.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al probar la conexión', 'error');
                button.innerHTML = originalContent;
                button.disabled = false;
            });
        }

        function testConnection(credentialId) {
            const button = event.target;
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            fetch(`?action=test_existing_credential&id=${credentialId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'error');
                    }
                    
                    button.innerHTML = originalContent;
                    button.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al probar la conexión', 'error');
                    button.innerHTML = originalContent;
                    button.disabled = false;
                });
        }

        function viewCredentialDetails(credentialId) {
            fetch(`?action=get_credential&id=${credentialId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showCredentialModal(data.credential);
                    } else {
                        showNotification('Error al cargar detalles de la credencial', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al cargar detalles', 'error');
                });
        }

        function showCredentialModal(credential) {
            const serviceTypes = {
                'evolution_api': 'Evolution API (WhatsApp)',
                'openai': 'OpenAI GPT',
                'google_sheets': 'Google Sheets',
                'telegram': 'Telegram Bot',
                'webhook': 'Webhook',
                'database': 'Base de Datos',
                'email_smtp': 'Email SMTP',
                'other': 'Otro'
            };

            let detailsHtml = `
                <div class="mb-3">
                    <strong>Tipo:</strong> ${serviceTypes[credential.service_type] || credential.service_type}
                </div>
                <div class="mb-3">
                    <strong>Estado:</strong> 
                    <span class="badge ${credential.is_active ? 'bg-success' : 'bg-secondary'}">
                        ${credential.is_active ? 'Activa' : 'Inactiva'}
                    </span>
                </div>
            `;

            if (credential.api_url) {
                detailsHtml += `<div class="mb-3"><strong>URL:</strong> ${credential.api_url}</div>`;
            }

            if (credential.instance_name) {
                detailsHtml += `<div class="mb-3"><strong>Instancia:</strong> ${credential.instance_name}</div>`;
            }

            if (credential.api_key) {
                detailsHtml += `<div class="mb-3"><strong>API Key:</strong> ${'*'.repeat(credential.api_key.length)}</div>`;
            }

            if (credential.additional_config) {
                try {
                    const config = JSON.parse(credential.additional_config);
                    detailsHtml += `<div class="mb-3"><strong>Configuración adicional:</strong><pre class="text-muted" style="font-size: 0.8rem;">${JSON.stringify(config, null, 2)}</pre></div>`;
                } catch (e) {
                    // Ignorar errores de JSON
                }
            }

            detailsHtml += `
                <div class="mb-3">
                    <strong>Creada:</strong> ${new Date(credential.created_at).toLocaleString()}
                </div>
            `;

            const modalHtml = `
                <div class="modal fade" id="credentialDetailsModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    ${credential.service_name}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${detailsHtml}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('credentialDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const modal = new bootstrap.Modal(document.getElementById('credentialDetailsModal'));
            modal.show();

            document.getElementById('credentialDetailsModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-danger' : 
                              type === 'warning' ? 'alert-warning' : 'alert-info';
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-triangle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            notification.innerHTML = `
                <i class="fas ${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        document.getElementById('credentialForm').addEventListener('submit', function(e) {
            const serviceType = document.getElementById('service_type').value;
            const serviceName = document.getElementById('service_name').value.trim();
            
            if (!serviceType || !serviceName) {
                e.preventDefault();
                showNotification('Por favor completa el tipo de servicio y el nombre', 'warning');
                return;
            }
            
            switch(serviceType) {
                case 'evolution_api':
                    const apiUrl = document.getElementById('api_url').value.trim();
                    const apiKey = document.getElementById('api_key').value.trim();
                    const instanceName = document.getElementById('instance_name').value.trim();
                    
                    if (!apiUrl || !apiKey || !instanceName) {
                        e.preventDefault();
                        showNotification('Por favor completa todos los campos de Evolution API', 'warning');
                        return;
                    }
                    
                    try {
                        new URL(apiUrl);
                    } catch {
                        e.preventDefault();
                        showNotification('Por favor ingresa una URL válida', 'warning');
                        return;
                    }
                    break;
                    
                case 'openai':
                    const openaiKey = document.getElementById('openai_api_key').value.trim();
                    if (!openaiKey) {
                        e.preventDefault();
                        showNotification('Por favor ingresa la API Key de OpenAI', 'warning');
                        return;
                    }
                    break;
            }
        });

        document.getElementById('addCredentialModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('credentialForm').reset();
            document.querySelectorAll('.service-fields').forEach(field => {
                field.style.display = 'none';
            });
            document.getElementById('service-info').style.display = 'none';
        });

        document.getElementById('addCredentialModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('service_type').focus();
        });
    </script>
</body>
</html>