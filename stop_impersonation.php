<?php
require_once 'config/database.php';

// Verificar si está en modo impersonación
if (!isset($_SESSION['is_impersonating']) || !$_SESSION['is_impersonating']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No estás en modo impersonación']);
    exit;
}

if (!isset($_SESSION['original_admin_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: datos de admin original no encontrados']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Registrar el fin de la impersonación
    $activity_description = "Admin {$_SESSION['original_admin_username']} terminó la impersonación del usuario {$_SESSION['impersonated_user']}";
    
    $query = "INSERT INTO system_activities (user_id, activity_type, description, created_at) 
              VALUES (:user_id, :activity_type, :description, CURRENT_TIMESTAMP)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['original_admin_id']);
    $stmt->bindParam(':activity_type', 'admin_impersonation_end');
    $stmt->bindParam(':description', $activity_description);
    $stmt->execute();
    
    // Restaurar la sesión del admin original
    $_SESSION['user_id'] = $_SESSION['original_admin_id'];
    
    // Limpiar variables de impersonación
    unset($_SESSION['is_impersonating']);
    unset($_SESSION['impersonated_user']);
    unset($_SESSION['original_admin_id']);
    unset($_SESSION['original_admin_username']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Impersonación terminada. Has vuelto al modo administrador.'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>