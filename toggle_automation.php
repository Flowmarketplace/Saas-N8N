<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'];
    $automation_id = $input['automation_id'];
    $is_active = $input['is_active'];
    
    if (empty($id) || empty($automation_id)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
    // Determinar la URL de la API según el estado
    if ($is_active) {
        $api_url = N8N_API_URL . '/' . $automation_id . '/activate';
    } else {
        $api_url = N8N_API_URL . '/' . $automation_id . '/deactivate';
    }
    
    // Realizar la petición a la API de N8N
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'X-N8N-API-KEY: ' . N8N_API_KEY,
            'Content-Type: application/json'
        ),
    ));
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Verificar si la API respondió correctamente
    if ($httpCode == 200 || $httpCode == 201) {
        // Actualizar el estado en la base de datos
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "UPDATE automations SET is_active = :is_active, updated_at = NOW() WHERE id = :id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Automatización ' . ($is_active ? 'activada' : 'desactivada') . ' exitosamente'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado en la base de datos']);
        }
    } else {
        // Error en la API
        echo json_encode([
            'success' => false, 
            'message' => 'Error al comunicarse con la API de N8N. Código: ' . $httpCode
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>