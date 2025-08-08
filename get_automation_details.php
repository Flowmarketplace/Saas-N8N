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
$query = "SELECT role FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo administradores.']);
    exit;
}

// Obtener ID de la automatización
$automationId = $_GET['id'] ?? null;

if (!$automationId) {
    echo json_encode(['success' => false, 'message' => 'ID de automatización requerido']);
    exit;
}

try {
    // Obtener detalles completos de la automatización incluyendo descripción
    $query = "SELECT a.*, u.username, u.full_name, u.email, u.company, u.created_at as user_created_at,
                     mp.title as product_title, mp.description as product_description
              FROM automations a 
              LEFT JOIN users u ON a.user_id = u.id 
              LEFT JOIN user_purchases up ON a.purchase_id = up.id
              LEFT JOIN marketplace_products mp ON up.product_id = mp.id
              WHERE a.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $automationId);
    $stmt->execute();
    $automation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$automation) {
        echo json_encode(['success' => false, 'message' => 'Automatización no encontrada']);
        exit;
    }
    
    // Obtener actividades relacionadas con esta automatización
    try {
        $query = "SELECT * FROM system_activities 
                  WHERE (description LIKE :automation_name OR description LIKE :automation_id)
                  ORDER BY created_at DESC LIMIT 10";
        $stmt = $db->prepare($query);
        $automation_name_search = '%' . $automation['name'] . '%';
        $automation_id_search = '%' . $automation['automation_id'] . '%';
        $stmt->bindParam(':automation_name', $automation_name_search);
        $stmt->bindParam(':automation_id', $automation_id_search);
        $stmt->execute();
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Si no existe la tabla, continuar sin actividades
        $recent_activities = [];
    }
    
    // Formatear datos adicionales
    $automation['recent_activities'] = $recent_activities;
    $automation['is_from_marketplace'] = !empty($automation['purchase_id']);
    $automation['formatted_created_at'] = date('d/m/Y H:i:s', strtotime($automation['created_at']));
    $automation['formatted_updated_at'] = $automation['updated_at'] ? date('d/m/Y H:i:s', strtotime($automation['updated_at'])) : null;
    $automation['user_since'] = $automation['user_created_at'] ? date('d/m/Y', strtotime($automation['user_created_at'])) : null;
    
    // ⚠️ ARREGLO PRINCIPAL: NO sobrescribir la descripción del usuario
    // Solo agregar descripción por defecto si está completamente vacía (NULL o cadena vacía)
    if (empty($automation['description']) || trim($automation['description']) === '') {
        if ($automation['is_from_marketplace']) {
            $automation['description'] = $automation['product_description'] ?? 'Automatización del marketplace sin descripción';
        } else {
            $automation['description'] = 'Sin descripción proporcionada';
        }
    }
    // Si el usuario ya tiene una descripción, NO la modificamos
    
    echo json_encode([
        'success' => true, 
        'automation' => $automation
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>