<?php
require_once 'config/database.php';
session_start();

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar si es admin
$query = "SELECT role FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'toggle_any_automation':
        $automation_id = (int)($_POST['automation_id'] ?? 0);
        $is_active = (bool)($_POST['is_active'] ?? false);
        
        try {
            $query = "UPDATE automations SET is_active = :is_active WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
            $stmt->bindParam(':id', $automation_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>