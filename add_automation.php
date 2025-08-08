<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $automation_id = trim($_POST['automation_id'] ?? '');
    
    // Validaciones básicas
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        exit;
    }
    
    if (empty($automation_id)) {
        echo json_encode(['success' => false, 'message' => 'El ID de automatización es obligatorio']);
        exit;
    }
    
    // Convertir automation_id a minúsculas
    $automation_id = strtolower($automation_id);
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Verificar si ya existe
        $checkQuery = "SELECT id FROM automations WHERE automation_id = :automation_id AND user_id = :user_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':automation_id', $automation_id);
        $checkStmt->bindParam(':user_id', $_SESSION['user_id']);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una automatización con este ID']);
            exit;
        }
        
        // Insertar nueva automatización - VERSIÓN SIMPLE
        $query = "INSERT INTO automations (name, description, automation_id, user_id, is_active) VALUES (:name, :description, :automation_id, :user_id, 0)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':automation_id', $automation_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            // ✅ RESPUESTA SIMPLE SIN COMPLICACIONES
            echo json_encode(['success' => true, 'message' => 'Automatización creada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>