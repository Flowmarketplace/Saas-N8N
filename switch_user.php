<?php
session_start();
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario actual es admin (o si ya está en modo impersonación)
$is_admin_action = false;
$current_admin_id = null;

// Si ya está impersonando, puede ser admin original o necesita verificación
if (isset($_SESSION['original_admin_id'])) {
    $is_admin_action = true;
    $current_admin_id = $_SESSION['original_admin_id'];
} else {
    // Verificar si es admin actual
    $query = "SELECT role FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_user && $current_user['role'] === 'admin') {
        $is_admin_action = true;
        $current_admin_id = $_SESSION['user_id'];
    }
}

if (!$is_admin_action) {
    echo json_encode(['success' => false, 'message' => 'Solo administradores pueden usar esta función']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'impersonate':
                $target_user_id = $input['user_id'] ?? '';
                
                if (empty($target_user_id)) {
                    echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
                    exit;
                }
                
                // Verificar que el usuario objetivo existe y no es admin
                $query = "SELECT id, username, role, status FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $target_user_id);
                $stmt->execute();
                $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$target_user) {
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                    exit;
                }
                
                if ($target_user['role'] === 'admin') {
                    echo json_encode(['success' => false, 'message' => 'No se puede impersonar a otro administrador']);
                    exit;
                }
                
                if ($target_user['status'] !== 'active') {
                    echo json_encode(['success' => false, 'message' => 'Solo se puede impersonar usuarios activos']);
                    exit;
                }
                
                // Guardar el admin original si no está ya guardado
                if (!isset($_SESSION['original_admin_id'])) {
                    $_SESSION['original_admin_id'] = $_SESSION['user_id'];
                    $_SESSION['original_username'] = $_SESSION['username'];
                }
                
                // Cambiar la sesión al usuario objetivo
                $_SESSION['user_id'] = $target_user['id'];
                $_SESSION['username'] = $target_user['username'];
                $_SESSION['is_impersonating'] = true;
                $_SESSION['impersonated_user'] = $target_user['username'];
                
                // Registrar la acción
                try {
                    $query = "INSERT INTO admin_logs (admin_id, action, target_user_id, description, ip_address, created_at) 
                              VALUES (:admin_id, 'impersonate_start', :target_user_id, :description, :ip_address, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':admin_id', $current_admin_id);
                    $stmt->bindParam(':target_user_id', $target_user_id);
                    $description = "Iniciando sesión como usuario: {$target_user['username']}";
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                    $stmt->execute();
                } catch (Exception $log_error) {
                    // Si falla el log, no afectar la operación principal
                    error_log("Error logging impersonation: " . $log_error->getMessage());
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Ahora estás viendo como: {$target_user['username']}",
                    'redirect' => 'dashboard.php'
                ]);
                break;
                
            case 'stop_impersonation':
                if (!isset($_SESSION['original_admin_id'])) {
                    echo json_encode(['success' => false, 'message' => 'No hay impersonación activa']);
                    exit;
                }
                
                $impersonated_user = $_SESSION['impersonated_user'] ?? 'unknown';
                
                // Restaurar la sesión del admin original
                $_SESSION['user_id'] = $_SESSION['original_admin_id'];
                $_SESSION['username'] = $_SESSION['original_username'];
                
                // Limpiar variables de impersonación
                unset($_SESSION['original_admin_id']);
                unset($_SESSION['original_username']);
                unset($_SESSION['is_impersonating']);
                unset($_SESSION['impersonated_user']);
                
                // Registrar el final de la impersonación
                try {
                    $query = "INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at) 
                              VALUES (:admin_id, 'impersonate_end', :description, :ip_address, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $description = "Finalizando impersonación del usuario: {$impersonated_user}";
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                    $stmt->execute();
                } catch (Exception $log_error) {
                    error_log("Error logging impersonation end: " . $log_error->getMessage());
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Has vuelto a tu sesión de administrador',
                    'redirect' => 'dashboard.php'
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                break;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error del servidor: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>