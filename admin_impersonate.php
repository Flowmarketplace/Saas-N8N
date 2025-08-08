<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar que es admin
$query = "SELECT role, username FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo administradores.']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['username'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$targetUserId = $input['user_id'];
$targetUsername = $input['username'];

try {
    // Verificar que el usuario objetivo existe y no es admin
    $query = "SELECT id, username, role, status FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $targetUserId);
    $stmt->execute();
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }
    
    if ($targetUser['role'] === 'admin') {
        echo json_encode(['success' => false, 'message' => 'No se puede impersonar a otro administrador']);
        exit;
    }
    
    if ($targetUser['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'No se puede impersonar a un usuario inactivo']);
        exit;
    }
    
    // Guardar datos del admin original para poder volver
    if (!isset($_SESSION['original_admin_id'])) {
        $_SESSION['original_admin_id'] = $_SESSION['user_id'];
        $_SESSION['original_admin_username'] = $admin['username'];
    }
    
    // Cambiar la sesión al usuario objetivo
    $_SESSION['user_id'] = $targetUser['id'];
    $_SESSION['is_impersonating'] = true;
    $_SESSION['impersonated_user'] = $targetUser['username'];
    
    // Registrar la actividad de impersonación
    $activity_description = "Admin {$admin['username']} impersonó al usuario {$targetUser['username']}";
    
    $query = "INSERT INTO system_activities (user_id, activity_type, description, created_at) 
              VALUES (:user_id, :activity_type, :description, CURRENT_TIMESTAMP)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['original_admin_id']);
    $stmt->bindParam(':activity_type', 'admin_impersonation_start');
    $stmt->bindParam(':description', $activity_description);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => "Ahora estás viendo como {$targetUser['username']}"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>