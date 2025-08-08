<?php
// Configurar headers antes que nada
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Iniciar buffer de salida para capturar cualquier output no deseado
ob_start();

try {
    require_once 'config/database.php';
    
    // Verificar si está logueado
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // Verificar que es admin
    $query = "SELECT role FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'admin') {
        ob_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo administradores.']);
        exit;
    }

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['automation_id']) || !isset($input['is_active'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $automationId = $input['automation_id'];
    $isActive = $input['is_active'] ? 1 : 0;

    // Actualizar el estado de la automatización
    $query = "UPDATE automations SET is_active = :is_active, updated_at = NOW() WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
    $stmt->bindParam(':id', $automationId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $rowsAffected = $stmt->rowCount();
        
        if ($rowsAffected === 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Automatización no encontrada']);
            exit;
        }
        
        // Limpiar buffer antes de enviar respuesta
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => $isActive ? 'Automatización activada exitosamente' : 'Automatización pausada exitosamente'
        ]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la automatización']);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>