<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'];
    
    if (empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        if ($action === 'start_all') {
            // Obtener todas las automatizaciones inactivas del usuario
            $query = "SELECT id, automation_id FROM automations WHERE user_id = :user_id AND is_active = 0";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $activated = 0;
            $errors = 0;
            
            foreach ($automations as $automation) {
                // Llamar a la API de N8N para activar
                $api_url = N8N_API_URL . '/' . $automation['automation_id'] . '/activate';
                
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $api_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_HTTPHEADER => array(
                        'X-N8N-API-KEY: ' . N8N_API_KEY,
                        'Content-Type: application/json'
                    ),
                ));
                
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                
                if ($httpCode == 200 || $httpCode == 201) {
                    // Actualizar en la base de datos
                    $updateQuery = "UPDATE automations SET is_active = 1, updated_at = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':id', $automation['id']);
                    $updateStmt->execute();
                    $activated++;
                } else {
                    $errors++;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Se activaron $activated automatizaciones. " . ($errors > 0 ? "$errors errores." : ""),
                'activated' => $activated,
                'errors' => $errors
            ]);
            
        } elseif ($action === 'pause_all') {
            // Obtener todas las automatizaciones activas del usuario
            $query = "SELECT id, automation_id FROM automations WHERE user_id = :user_id AND is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $paused = 0;
            $errors = 0;
            
            foreach ($automations as $automation) {
                // Llamar a la API de N8N para desactivar
                $api_url = N8N_API_URL . '/' . $automation['automation_id'] . '/deactivate';
                
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $api_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_HTTPHEADER => array(
                        'X-N8N-API-KEY: ' . N8N_API_KEY,
                        'Content-Type: application/json'
                    ),
                ));
                
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                
                if ($httpCode == 200 || $httpCode == 201) {
                    // Actualizar en la base de datos
                    $updateQuery = "UPDATE automations SET is_active = 0, updated_at = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':id', $automation['id']);
                    $updateStmt->execute();
                    $paused++;
                } else {
                    $errors++;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Se pausaron $paused automatizaciones. " . ($errors > 0 ? "$errors errores." : ""),
                'paused' => $paused,
                'errors' => $errors
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>