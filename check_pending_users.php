<?php
require_once 'config/database.php';

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario es admin
$query = "SELECT role FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user || $current_user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Verificar si hay usuarios pendientes
    $query = "SELECT COUNT(*) as pending FROM users WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Verificar usuarios registrados en la última hora
    $query = "SELECT COUNT(*) as recent FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_count = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
    
    // Determinar si hay cambios significativos
    $has_changes = $pending_count > 0 || $recent_count > 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'has_changes' => $has_changes,
        'pending_count' => (int)$pending_count,
        'recent_count' => (int)$recent_count,
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>