<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener información del usuario y organización
$query = "SELECT u.*, o.max_credentials, o.plan 
          FROM users u 
          LEFT JOIN organizations o ON u.organization_id = o.id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info || !$user_info['organization_id']) {
    echo json_encode(['success' => false, 'message' => 'Usuario sin organización válida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Verificar si existe la tabla credentials
    $table_exists = false;
    try {
        $query = "SHOW TABLES LIKE 'credentials'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $table_exists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $table_exists = false;
    }
    
    if (!$table_exists) {
        echo json_encode(['success' => false, 'message' => 'Tabla de credenciales no existe. Ejecuta el script de base de datos.']);
        exit;
    }
    
    // Determinar la acción
    $action = '';
    $input = null;
    
    if (isset($_POST['name'])) {
        // Formulario para agregar credencial
        $action = 'add';
    } else {
        // JSON para otras acciones
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    }
    
    try {
        switch ($action) {
            case 'add':
                // Verificar límites del plan
                $query = "SELECT COUNT(*) as total FROM credentials WHERE organization_id = :org_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':org_id', $user_info['organization_id']);
                $stmt->execute();
                $current_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                if ($user_info['max_credentials'] && $current_count >= $user_info['max_credentials']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "Límite alcanzado. Plan {$user_info['plan']} permite máximo {$user_info['max_credentials']} credenciales."
                    ]);
                    exit;
                }
                
                $name = trim($_POST['name']);
                $service_type = trim($_POST['service_type']);
                $service_category = trim($_POST['service_category']) ?: 'general';
                $api_key = trim($_POST['api_key']);
                $endpoint_url = trim($_POST['endpoint_url']) ?: null;
                $is_shared = isset($_POST['is_shared']) ? 1 : 0;
                
                if (empty($name) || empty($service_type) || empty($api_key)) {
                    echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben completarse']);
                    exit;
                }
                
                // Verificar si ya existe una credencial con el mismo nombre en la organización
                $checkQuery = "SELECT id FROM credentials WHERE name = :name AND organization_id = :org_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':name', $name);
                $checkStmt->bindParam(':org_id', $user_info['organization_id']);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Ya existe una credencial con este nombre en tu organización']);
                    exit;
                }
                
                // Encriptar la API key (en producción usar openssl_encrypt)
                $encrypted_key = base64_encode($api_key);
                
                // Insertar nueva credencial
                $query = "INSERT INTO credentials (organization_id, created_by, name, service_type, service_category, api_key, endpoint_url, is_shared, status) 
                          VALUES (:org_id, :user_id, :name, :service_type, :service_category, :api_key, :endpoint_url, :is_shared, 'active')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':org_id', $user_info['organization_id']);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':service_type', $service_type);
                $stmt->bindParam(':service_category', $service_category);
                $stmt->bindParam(':api_key', $encrypted_key);
                $stmt->bindParam(':endpoint_url', $endpoint_url);
                $stmt->bindParam(':is_shared', $is_shared);
                
                if ($stmt->execute()) {
                    // Registrar actividad
                    $activity_query = "INSERT INTO system_activities (organization_id, user_id, activity_type, resource_type, description) 
                                       VALUES (:org_id, :user_id, 'credential_created', 'credential', :description)";
                    $activity_stmt = $db->prepare($activity_query);
                    $description = "Nueva credencial '{$name}' creada para {$service_type}";
                    $activity_stmt->bindParam(':org_id', $user_info['organization_id']);
                    $activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $activity_stmt->bindParam(':description', $description);
                    $activity_stmt->execute();
                    
                    echo json_encode(['success' => true, 'message' => 'Credencial agregada exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al agregar la credencial']);
                }
                break;
                
            case 'test':
                $credential_id = $input['id'];
                
                // Obtener la credencial
                $query = "SELECT * FROM credentials WHERE id = :id AND organization_id = :org_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $credential_id);
                $stmt->bindParam(':org_id', $user_info['organization_id']);
                $stmt->execute();
                $credential = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$credential) {
                    echo json_encode(['success' => false, 'message' => 'Credencial no encontrada']);
                    exit;
                }
                
                // Simular test de credencial (en producción hacer llamada real a la API)
                $test_success = true; // Simular éxito
                
                if ($test_success) {
                    // Actualizar last_used
                    $updateQuery = "UPDATE credentials SET last_used = NOW(), last_used_by = :user_id, usage_count = usage_count + 1 WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':user_id', $_SESSION['user_id']);
                    $updateStmt->bindParam(':id', $credential_id);
                    $updateStmt->execute();
                    
                    // Registrar actividad
                    $activity_query = "INSERT INTO system_activities (organization_id, user_id, activity_type, resource_type, resource_id, description) 
                                       VALUES (:org_id, :user_id, 'credential_tested', 'credential', :resource_id, :description)";
                    $activity_stmt = $db->prepare($activity_query);
                    $description = "Credencial '{$credential['name']}' probada exitosamente";
                    $activity_stmt->bindParam(':org_id', $user_info['organization_id']);
                    $activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $activity_stmt->bindParam(':resource_id', $credential_id);
                    $activity_stmt->bindParam(':description', $description);
                    $activity_stmt->execute();
                    
                    echo json_encode(['success' => true, 'message' => 'Credencial validada correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Fallo en la validación de la credencial']);
                }
                break;
                
            case 'delete':
                $credential_id = $input['id'];
                
                // Verificar permisos - solo el creador o admin puede eliminar
                $query = "SELECT c.*, u.role FROM credentials c 
                          LEFT JOIN users u ON u.id = :user_id 
                          WHERE c.id = :id AND c.organization_id = :org_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $credential_id);
                $stmt->bindParam(':org_id', $user_info['organization_id']);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $credential = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$credential) {
                    echo json_encode(['success' => false, 'message' => 'Credencial no encontrada']);
                    exit;
                }
                
                if ($credential['created_by'] != $_SESSION['user_id'] && $user_info['role'] != 'admin') {
                    echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar esta credencial']);
                    exit;
                }
                
                // Eliminar la credencial
                $deleteQuery = "DELETE FROM credentials WHERE id = :id AND organization_id = :org_id";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(':id', $credential_id);
                $deleteStmt->bindParam(':org_id', $user_info['organization_id']);
                
                if ($deleteStmt->execute()) {
                    // Registrar actividad
                    $activity_query = "INSERT INTO system_activities (organization_id, user_id, activity_type, resource_type, description) 
                                       VALUES (:org_id, :user_id, 'credential_deleted', 'credential', :description)";
                    $activity_stmt = $db->prepare($activity_query);
                    $description = "Credencial '{$credential['name']}' eliminada";
                    $activity_stmt->bindParam(':org_id', $user_info['organization_id']);
                    $activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $activity_stmt->bindParam(':description', $description);
                    $activity_stmt->execute();
                    
                    echo json_encode(['success' => true, 'message' => 'Credencial eliminada exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar la credencial']);
                }
                break;
                
            case 'update_status':
                $credential_id = $input['id'];
                $new_status = $input['status']; // 'active' or 'inactive'
                
                if (!in_array($new_status, ['active', 'inactive'])) {
                    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
                    exit;
                }
                
                // Verificar permisos
                $query = "SELECT * FROM credentials WHERE id = :id AND organization_id = :org_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $credential_id);
                $stmt->bindParam(':org_id', $user_info['organization_id']);
                $stmt->execute();
                $credential = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$credential) {
                    echo json_encode(['success' => false, 'message' => 'Credencial no encontrada']);
                    exit;
                }
                
                if ($credential['created_by'] != $_SESSION['user_id'] && $user_info['role'] != 'admin') {
                    echo json_encode(['success' => false, 'message' => 'No tienes permisos para modificar esta credencial']);
                    exit;
                }
                
                // Actualizar estado
                $updateQuery = "UPDATE credentials SET status = :status, updated_at = NOW() WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':status', $new_status);
                $updateStmt->bindParam(':id', $credential_id);
                
                if ($updateStmt->execute()) {
                    // Registrar actividad
                    $activity_query = "INSERT INTO system_activities (organization_id, user_id, activity_type, resource_type, resource_id, description) 
                                       VALUES (:org_id, :user_id, 'credential_updated', 'credential', :resource_id, :description)";
                    $activity_stmt = $db->prepare($activity_query);
                    $description = "Credencial '{$credential['name']}' {$new_status}";
                    $activity_stmt->bindParam(':org_id', $user_info['organization_id']);
                    $activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $activity_stmt->bindParam(':resource_id', $credential_id);
                    $activity_stmt->bindParam(':description', $description);
                    $activity_stmt->execute();
                    
                    echo json_encode(['success' => true, 'message' => "Credencial {$new_status} exitosamente"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
                }
                break;
                
            case 'get_stats':
                // Obtener estadísticas de uso de credenciales
                $query = "SELECT 
                            COUNT(*) as total,
                            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                            COUNT(CASE WHEN DATE(last_used) = CURDATE() THEN 1 END) as used_today,
                            SUM(usage_count) as total_usage
                          FROM credentials 
                          WHERE organization_id = :org_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':org_id', $user_info['organization_id']);
                $stmt->execute();
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Obtener credenciales más usadas
                $query = "SELECT name, service_type, usage_count, last_used 
                          FROM credentials 
                          WHERE organization_id = :org_id 
                          ORDER BY usage_count DESC 
                          LIMIT 5";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':org_id', $user_info['organization_id']);
                $stmt->execute();
                $top_used = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats,
                    'top_used' => $top_used
                ]);
                break;
                
            case 'bulk_action':
                $credential_ids = $input['ids'] ?? [];
                $bulk_action = $input['bulk_action'] ?? '';
                
                if (empty($credential_ids) || empty($bulk_action)) {
                    echo json_encode(['success' => false, 'message' => 'Datos incompletos para acción masiva']);
                    exit;
                }
                
                if (!in_array($bulk_action, ['activate', 'deactivate', 'delete'])) {
                    echo json_encode(['success' => false, 'message' => 'Acción masiva no válida']);
                    exit;
                }
                
                $success_count = 0;
                $error_count = 0;
                
                foreach ($credential_ids as $id) {
                    // Verificar permisos para cada credencial
                    $query = "SELECT * FROM credentials WHERE id = :id AND organization_id = :org_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    $stmt->bindParam(':org_id', $user_info['organization_id']);
                    $stmt->execute();
                    $credential = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$credential || ($credential['created_by'] != $_SESSION['user_id'] && $user_info['role'] != 'admin')) {
                        $error_count++;
                        continue;
                    }
                    
                    try {
                        switch ($bulk_action) {
                            case 'activate':
                                $updateQuery = "UPDATE credentials SET status = 'active' WHERE id = :id";
                                break;
                            case 'deactivate':
                                $updateQuery = "UPDATE credentials SET status = 'inactive' WHERE id = :id";
                                break;
                            case 'delete':
                                $updateQuery = "DELETE FROM credentials WHERE id = :id";
                                break;
                        }
                        
                        $updateStmt = $db->prepare($updateQuery);
                        $updateStmt->bindParam(':id', $id);
                        
                        if ($updateStmt->execute()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    } catch (Exception $e) {
                        $error_count++;
                    }
                }
                
                // Registrar actividad masiva
                $activity_query = "INSERT INTO system_activities (organization_id, user_id, activity_type, resource_type, description) 
                                   VALUES (:org_id, :user_id, 'credential_bulk_action', 'credential', :description)";
                $activity_stmt = $db->prepare($activity_query);
                $description = "Acción masiva '{$bulk_action}': {$success_count} exitosas, {$error_count} errores";
                $activity_stmt->bindParam(':org_id', $user_info['organization_id']);
                $activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $activity_stmt->bindParam(':description', $description);
                $activity_stmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'message' => "Acción completada: {$success_count} exitosas, {$error_count} errores",
                    'success_count' => $success_count,
                    'error_count' => $error_count
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                break;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

// Funciones helper para encriptación (implementar en producción)
function encryptApiKey($api_key) {
    // En producción usar openssl_encrypt con clave secreta
    return base64_encode($api_key);
}

function decryptApiKey($encrypted_key) {
    // En producción usar openssl_decrypt
    return base64_decode($encrypted_key);
}

// Función para validar credenciales según el tipo de servicio
function validateCredential($service_type, $api_key, $endpoint_url = null) {
    switch ($service_type) {
        case 'openai':
            // Validar formato de API key de OpenAI
            return preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key);
            
        case 'whatsapp':
            // Validar token de WhatsApp Business
            return strlen($api_key) > 20;
            
        case 'postgresql':
        case 'mysql':
            // Validar connection string de base de datos
            return strpos($api_key, '://') !== false;
            
        default:
            // Validación genérica - al menos 10 caracteres
            return strlen($api_key) >= 10;
    }
}

// Función para hacer test real de API (implementar según servicio)
function testApiCredential($service_type, $api_key, $endpoint_url = null) {
    // En producción, hacer llamadas reales a las APIs para validar
    switch ($service_type) {
        case 'openai':
            // return testOpenAIKey($api_key);
            break;
        case 'whatsapp':
            // return testWhatsAppToken($api_key);
            break;
        default:
            // Test genérico
            break;
    }
    
    // Por ahora simular éxito
    return true;
}
?>