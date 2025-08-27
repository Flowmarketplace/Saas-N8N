<?php
require_once 'config/database.php';
session_start();

// Asegurar que siempre devolvemos JSON
header('Content-Type: application/json');

// Capturar todos los errores para evitar output no deseado
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

$data = json_decode(file_get_contents('php://input'), true);
$prompt_id = $data['prompt_id'] ?? 0;

if (!$prompt_id) {
    die(json_encode(['success' => false, 'message' => 'ID de prompt no válido']));
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener datos del prompt específico
    $query = "SELECT p.*, a.user_id 
              FROM automatizacion_prompts p 
              JOIN automations a ON p.automation_id = a.id 
              WHERE p.id = :id AND a.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $prompt_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();

    $prompt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prompt) {
        die(json_encode(['success' => false, 'message' => 'Prompt no encontrado']));
    }

    // Preparar token
    $authToken = $prompt['airtable_token'];
    if (strpos($authToken, 'Bearer ') !== 0) {
        $authToken = 'Bearer ' . $authToken;
    }

    // PASO 1: Obtener el registro de Airtable (solo traerá 1)
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.airtable.com/v0/{$prompt['airtable_db_id']}/{$prompt['airtable_table_id']}?maxRecords=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            "Authorization: " . $authToken,
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        die(json_encode(['success' => false, 'message' => 'Error CURL: ' . $curlError]));
    }

    if ($httpCode != 200) {
        die(json_encode(['success' => false, 'message' => 'Error Airtable: HTTP ' . $httpCode]));
    }

    $airtableData = json_decode($response, true);
    
    if (!isset($airtableData['records']) || empty($airtableData['records'])) {
        die(json_encode(['success' => false, 'message' => 'No hay registros en Airtable']));
    }

    // Obtener el primer (y único) registro
    $record = $airtableData['records'][0];
    $recordId = $record['id'];
    $fields = $record['fields'];
    
    // PASO 2: Procesar el prompt con los datos del registro
    $instruccionNueva = $prompt['prompt']; // Usar el prompt tal cual está guardado
    
    // Si quieres reemplazar variables en el prompt:
    // Ejemplo: si el prompt contiene {{nombre_empresa}}, lo reemplaza con el valor del campo
    foreach ($fields as $campo => $valor) {
        $placeholder = '{{' . strtolower($campo) . '}}';
        if (is_string($valor)) {
            $instruccionNueva = str_replace($placeholder, $valor, $instruccionNueva);
        }
    }
    
    // PASO 3: Actualizar el campo INSTRUCCION en Airtable
    $updateData = [
        'fields' => [
            'INSTRUCCION' => $instruccionNueva
        ]
    ];
    
    $updateCurl = curl_init();
    curl_setopt_array($updateCurl, [
        CURLOPT_URL => "https://api.airtable.com/v0/{$prompt['airtable_db_id']}/{$prompt['airtable_table_id']}/{$recordId}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($updateData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "Authorization: " . $authToken
        ]
    ]);
    
    $updateResponse = curl_exec($updateCurl);
    $updateHttpCode = curl_getinfo($updateCurl, CURLINFO_HTTP_CODE);
    $updateError = curl_error($updateCurl);
    curl_close($updateCurl);
    
    if ($updateError) {
        die(json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $updateError]));
    }
    
    if ($updateHttpCode == 200) {
        echo json_encode([
            'success' => true,
            'message' => 'Registro actualizado correctamente',
            'record_id' => $recordId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar: HTTP ' . $updateHttpCode
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>