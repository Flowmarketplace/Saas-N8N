<?php
// Habilitar reporte de errores para debugging
require_once 'config/database.php';
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$database = new Database();
$db = $database->getConnection();

// Función para obtener credenciales activas de Evolution API del usuario
function getEvolutionCredentials($db, $user_id) {
    $query = "SELECT * FROM user_credentials 
              WHERE user_id = :user_id 
              AND service_type = 'evolution_api' 
              AND is_active = 1 
              ORDER BY created_at DESC 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener credenciales del usuario logueado
$evolutionCredentials = getEvolutionCredentials($db, $_SESSION['user_id']);

if (!$evolutionCredentials) {
    // Redirigir a credenciales si no hay configuración
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Configuración Requerida</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: #1a1d29;
                color: #ffffff;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .config-card {
                background: #2d3148;
                border-radius: 16px;
                padding: 3rem;
                text-align: center;
                border: 1px solid #3a3f5c;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                max-width: 500px;
            }
            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                border-radius: 10px;
                padding: 12px 30px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="config-card">
            <i class="fab fa-whatsapp fa-4x text-success mb-4"></i>
            <h3 class="mb-3">Configuración de WhatsApp Requerida</h3>
            <p class="text-muted mb-4">
                Para usar WhatsApp necesitas configurar primero tus credenciales de Evolution API.
            </p>
            <a href="credentials.php" class="btn btn-primary btn-lg">
                <i class="fas fa-key me-2"></i>
                Configurar Credenciales
            </a>
            <div class="mt-4">
                <a href="dashboard.php" class="text-muted">
                    <i class="fas fa-arrow-left me-1"></i>
                    Volver al Dashboard
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// config.php - Configuración de Evolution API - VERSIÓN SIMPLIFICADA
class EvolutionAPI {
    private $baseUrl;
    private $apiKey;
    private $instanceName;
    
    public function __construct($baseUrl, $apiKey, $instanceName) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->instanceName = $instanceName;
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        // Log de la petición
        error_log("Evolution API Request: $method $url");
        if ($data) {
            error_log("Request Data: " . json_encode($data));
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // REDUCIDO de 30 a 10 segundos
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey,
            'Accept: application/json'
        ]);
        
        // Configurar método y datos
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log de la respuesta
        error_log("Evolution API Response (HTTP $httpCode): " . substr($response, 0, 500));
        
        if ($error) {
            error_log("cURL Error: " . $error);
            return ['error' => $error];
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $decodedResponse ?: ['raw' => $response];
        }
        
        // Retornar información del error
        return [
            'error' => true,
            'httpCode' => $httpCode,
            'message' => $decodedResponse['message'] ?? 'Error desconocido',
            'raw' => $response
        ];
    }
    
    // SOLO para llamadas AJAX individuales - NO para carga inicial
    // Método correcto para obtener foto de perfil
// REEMPLAZAR el método getProfilePicture POR:
public function getProfilePicture($contactId) {
    // Asegurar formato correcto del número
    if (strpos($contactId, '@') !== false) {
        // Si tiene @, extraer solo el número
        $contactId = str_replace(['@s.whatsapp.net', '@g.us'], '', $contactId);
    }
    
    $data = ['number' => $contactId];
    
    $result = $this->makeRequest("/chat/fetchProfilePictureUrl/{$this->instanceName}", 'POST', $data);
    
    if ($result && !isset($result['error']) && isset($result['profilePictureUrl'])) {
        return $result['profilePictureUrl'];
    }
    
    return null;
}
    
    // SIMPLIFICADO: Obtener chats SIN fotos para carga rápida
    public function getChats() {
        // Intentar POST con datos vacíos (según documentación v2)
        $result = $this->makeRequest("/chat/findChats/{$this->instanceName}", 'POST', new stdClass());
        if ($result && !isset($result['error'])) return $result;
        
        // Como backup, intentar GET
        $result = $this->makeRequest("/chat/findChats/{$this->instanceName}");
        if ($result && !isset($result['error'])) return $result;
        
        return false;
    }
    
    // Obtener mensajes de una conversación específica
    public function getChatMessages($chatId, $limit = 100) {
        // Asegurarse de que el chatId tenga el formato correcto
        if (strpos($chatId, '@') === false) {
            if (strlen($chatId) > 15) {
                $chatId .= '@g.us';
            } else {
                $chatId .= '@s.whatsapp.net';
            }
        }
        
        $data = [
            'where' => [
                'key' => [
                    'remoteJid' => $chatId
                ]
            ],
            'limit' => $limit,
            'offset' => 0
        ];
        
        $result = $this->makeRequest("/chat/findMessages/{$this->instanceName}", 'POST', $data);
        
        if ($result && !isset($result['error'])) {
            return $result;
        }
        
        // Si falla, intentar con estructura alternativa
        $data = [
            'remoteJid' => $chatId,
            'limit' => $limit
        ];
        
        return $this->makeRequest("/chat/findMessages/{$this->instanceName}", 'POST', $data);
    }
    
    // Enviar mensaje
    public function sendMessage($chatId, $message) {
        // NO modificar el chatId si ya tiene el formato correcto
        $number = $chatId;
        
        // Solo agregar el sufijo si no lo tiene
        if (strpos($chatId, '@') === false) {
            // Si es un número sin @, agregar el sufijo apropiado
            if (strlen($chatId) > 15) { // Probablemente es un grupo
                $number = $chatId . '@g.us';
            } else {
                $number = $chatId . '@s.whatsapp.net';
            }
        }
        
        $data = [
            'number' => $number,
            'text' => $message,
            'delay' => 1000
        ];
        
        error_log("Sending to: " . $number . " - Message: " . $message);
        
        $result = $this->makeRequest("/message/sendText/{$this->instanceName}", 'POST', $data);
        
        error_log("Send result: " . json_encode($result));
        
        return $result;
    }
    
    // Obtener información del contacto
    public function getContactInfo($contactId) {
        $data = [
            'where' => [
                'id' => $contactId
            ]
        ];
        
        $result = $this->makeRequest("/chat/findContacts/{$this->instanceName}", 'POST', $data);
     //   var_dump($result );
        if ($result) return $result;
        
        return false;
    }
    
    // Método para verificar conexión e información de la instancia
    public function testConnection() {
        $instanceInfo = $this->makeRequest("/instance/connectionState/{$this->instanceName}");
        return $instanceInfo;
    }
    
    // Obtener código QR para conectar
    public function getQRCode() {
        return $this->makeRequest("/instance/connect/{$this->instanceName}");
    }
    
    // Desconectar instancia
    public function disconnect() {
        return $this->makeRequest("/instance/logout/{$this->instanceName}", 'DELETE');
    }
    
    // Reiniciar instancia
    public function restart() {
        return $this->makeRequest("/instance/restart/{$this->instanceName}", 'PUT');
    }
}

$evolutionAPI = new EvolutionAPI(
    $evolutionCredentials['api_url'],
    $evolutionCredentials['api_key'],
    $evolutionCredentials['instance_name']
);

// Manejo de acciones
$action = $_GET['action'] ?? 'chats';
$chatId = $_GET['chat_id'] ?? null;
$debug = $_GET['debug'] ?? false;

// SOLO para llamadas AJAX individuales
// REEMPLAZAR EL BLOQUE COMENTADO POR:
if ($action === 'get_profile_pic' && isset($_GET['contact_id'])) {
    header('Content-Type: application/json');
    $profilePic = $evolutionAPI->getProfilePicture($_GET['contact_id']);
    echo json_encode(['url' => $profilePic]);
    exit;
}

// Test de conexión si se solicita
if ($debug) {
    echo "<!-- Testing connection... -->";
    $connectionTest = $evolutionAPI->testConnection();
    echo "<!-- Connection test result: " . json_encode($connectionTest) . " -->";
}

// Verificar estado de conexión
if ($action === 'check_connection') {
    header('Content-Type: application/json');
    $connectionState = $evolutionAPI->testConnection();
    echo json_encode($connectionState);
    exit;
}

// Manejar QR Code
if ($action === 'qr') {
    $qrData = $evolutionAPI->getQRCode();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Conectar WhatsApp</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #111b21;
                color: #e9edef;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .qr-container {
                text-align: center;
                background: #202c33;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .qr-code {
                background: white;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
            }
            .qr-code img {
                max-width: 300px;
            }
            .instructions {
                margin-top: 20px;
                font-size: 14px;
                color: #8696a0;
            }
            .button {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background: #00a884;
                color: white;
                text-decoration: none;
                border-radius: 5px;
            }
            .button:hover {
                background: #00966a;
            }
            .error {
                color: #f15c6d;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="qr-container">
            <h2>Conectar WhatsApp</h2>
            <?php if (isset($qrData['base64'])): ?>
                <div class="qr-code">
                    <img src="data:image/png;base64,<?php echo $qrData['base64']; ?>" alt="QR Code">
                </div>
                <div class="instructions">
                    <p>1. Abre WhatsApp en tu teléfono</p>
                    <p>2. Ve a Configuración > Dispositivos vinculados</p>
                    <p>3. Toca "Vincular dispositivo"</p>
                    <p>4. Escanea este código QR</p>
                </div>
            <?php elseif (isset($qrData['qrcode'])): ?>
                <div class="qr-code">
                    <img src="<?php echo htmlspecialchars($qrData['qrcode']); ?>" alt="QR Code">
                </div>
                <div class="instructions">
                    <p>1. Abre WhatsApp en tu teléfono</p>
                    <p>2. Ve a Configuración > Dispositivos vinculados</p>
                    <p>3. Toca "Vincular dispositivo"</p>
                    <p>4. Escanea este código QR</p>
                </div>
            <?php else: ?>
                <div class="error">
                    <p>No se pudo generar el código QR</p>
                    <p><?php echo isset($qrData['message']) ? htmlspecialchars($qrData['message']) : 'Error desconocido'; ?></p>
                </div>
            <?php endif; ?>
            <a href="?" class="button">Volver a Chats</a>
        </div>
        <script>
            setTimeout(() => {
                location.reload();
            }, 20000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// CARGA RÁPIDA: Solo cargar chats sin fotos
$chats = $evolutionAPI->getChats();

// Manejar envío de mensajes AJAX
if ($action === 'send_ajax' && $chatId && isset($_POST['message'])) {
    header('Content-Type: application/json');
    $result = $evolutionAPI->sendMessage($chatId, $_POST['message']);
    
    $response = [
        'success' => $result !== false && !isset($result['error']),
        'result' => $result,
        'chatId' => $chatId,
        'message' => $_POST['message']
    ];
    
    echo json_encode($response);
    exit;
}

// Manejar obtención de mensajes AJAX
if ($action === 'get_messages_ajax' && $chatId) {
    header('Content-Type: application/json');
    $messages = $evolutionAPI->getChatMessages($chatId);
    echo json_encode($messages ?: []);
    exit;
}

switch ($action) {
    case 'chats':
        if ($debug) {
            echo "<!-- Chats result: " . json_encode($chats) . " -->";
            $connectionState = $evolutionAPI->testConnection();
            echo "<!-- Connection state: " . json_encode($connectionState) . " -->";
        }
        break;
    case 'messages':
        if ($chatId) {
            $messages = $evolutionAPI->getChatMessages($chatId);
            $contactInfo = $evolutionAPI->getContactInfo($chatId);
            
            if ($debug) {
                echo "<!-- Messages: " . json_encode($messages) . " -->";
                echo "<!-- Contact: " . json_encode($contactInfo) . " -->";
            }
        }
        break;
    case 'send':
        if ($chatId && isset($_POST['message'])) {
            $result = $evolutionAPI->sendMessage($chatId, $_POST['message']);
            header("Location: ?action=messages&chat_id=" . urlencode($chatId));
            exit;
        }
        break;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Web - Evolution API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #111b21;
            color: #e9edef;
            height: 100vh;
            overflow: hidden;
        }
        
        .container {
            display: flex;
            height: 100vh;
        }
        
        .chat-list {
            width: 30%;
            background: #111b21;
            border-right: 1px solid #2a3942;
            display: flex;
            flex-direction: column;
        }
        
        .chat-list-header {
            padding: 20px;
            background: #202c33;
            border-bottom: 1px solid #2a3942;
            position: relative;
        }
        
        .connection-status {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .connection-status.connected {
            background: #00a884;
            color: white;
        }
        
        .connection-status.disconnected {
            background: #f15c6d;
            color: white;
        }
        
        .connection-status.connecting {
            background: #f7b500;
            color: white;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .reconnect-button {
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-size: 12px;
            padding: 5px 10px;
            background: #00a884;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        
        .reconnect-button:hover {
            background: #00966a;
        }
        
        .chat-list-header h2 {
            color: #e9edef;
            font-size: 18px;
        }
        
        .chats-container {
            flex: 1;
            overflow-y: auto;
        }
        
        .chat-item {
            padding: 15px 20px;
            border-bottom: 1px solid #2a3942;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
        }
        
        .chat-item:hover {
            background: #2a3942;
        }
        
        .chat-item.active {
            background: #2a3942;
        }
        
        /* Avatar optimizado */
        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #00a884;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: bold;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }
        
        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .chat-avatar .avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: #00a884;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .chat-avatar .loading-photo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff40;
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .chat-info {
            flex: 1;
            min-width: 0; /* Para que funcione el text-overflow */
        }
        
        .chat-name {
            font-weight: 500;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-last-message {
            font-size: 14px;
            color: #8696a0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Panel derecho */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #0b141a;
        }
        
        .chat-header {
            padding: 15px 20px;
            background: #202c33;
            border-bottom: 1px solid #2a3942;
            display: flex;
            align-items: center;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23pattern)"/></svg>');
        }
        
        .message {
            max-width: 70%;
            margin-bottom: 10px;
            padding: 10px 15px;
            border-radius: 10px;
            word-wrap: break-word;
        }
        
        .message.sent {
            background: #005c4b;
            margin-left: auto;
            border-bottom-right-radius: 3px;
        }
        
        .message.received {
            background: #202c33;
            margin-right: auto;
            border-bottom-left-radius: 3px;
        }
        
        .message-time {
            font-size: 11px;
            color: #8696a0;
            margin-top: 5px;
            text-align: right;
        }
        
        .message-status {
            font-size: 10px;
            color: #8696a0;
            margin-top: 3px;
        }
        
        .message-input-area {
            padding: 20px;
            background: #202c33;
            border-top: 1px solid #2a3942;
        }
        
        .message-input-form {
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 15px;
            border: none;
            border-radius: 25px;
            background: #2a3942;
            color: #e9edef;
            font-size: 14px;
        }
        
        .message-input:focus {
            outline: none;
            background: #3c4f5c;
        }
        
        .send-button {
            padding: 12px 20px;
            background: #00a884;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .send-button:hover {
            background: #00966a;
        }
        
        .send-button:disabled {
            background: #667781;
            cursor: not-allowed;
        }
        
        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #8696a0;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #8696a0;
        }
        
        .sending-indicator {
            display: none;
            color: #00a884;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .main-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: #242738;
            padding: 2rem 0;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid #3a3f5c;
        }

        .sidebar-brand {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid #3a3f5c;
            margin-bottom: 2rem;
        }

        .sidebar-brand h4 {
            color: #ffffff;
            font-weight: 700;
            margin: 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 2rem;
            color: #8b8fa3;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #ffffff;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
        }

        .nav-link i {
            width: 20px;
            margin-right: 1rem;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
    <div class="container main-container">
        <nav class="sidebar">
            <div class="sidebar-brand">
                <h4><i class="fas fa-robot me-2"></i> n8n CRM</h4>
                <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0.5rem 0 0;">
                    WhatsApp Panel
                </p>
            </div>
            
            <div>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="whatsapp.php" class="nav-link active">
                    <i class="fab fa-whatsapp"></i>
                    WhatsApp
                </a>
                <a href="credentials.php" class="nav-link">
                    <i class="fas fa-key"></i>
                    Credenciales
                </a>
            </div>
        </nav>
        
        <!-- Panel izquierdo - Lista de chats -->
        <div class="chat-list">
            <div class="chat-list-header">
                <h2>Chats</h2>
                <div id="connection-status" class="connection-status connecting">
                    <span class="status-dot"></span>
                    <span id="status-text">Conectando...</span>
                </div>
                <a href="?action=qr" class="reconnect-button">Reconectar WhatsApp</a>
            </div>
            <div class="chats-container">
                <?php if (isset($chats) && $chats && is_array($chats)): ?>
                    <?php foreach ($chats as $chat): ?>
                        <?php
                        $currentChatId = $chat['remoteJid'] ?? $chat['id'] ?? '';
                        $isActive = $chatId === $currentChatId;
                        $chatName = $chat['name'] ?? $chat['pushName'] ?? $currentChatId ?? 'Sin nombre';
                        $avatarLetter = substr($chatName, 0, 1);
                        $lastMessage = $chat['lastMessage']['message'] ?? $chat['lastMessage'] ?? 'Sin mensajes';
                        ?>
                        <div class="chat-item <?php echo $isActive ? 'active' : ''; ?>" 
                             onclick="location.href='?action=messages&chat_id=<?php echo urlencode($currentChatId); ?>'">
                            <div class="chat-avatar" data-contact-id="<?php echo htmlspecialchars($currentChatId); ?>">
                                <div class="avatar-fallback">
                                    <?php echo strtoupper($avatarLetter); ?>
                                </div>
                                <div class="loading-photo"></div>
                            </div>
                            <div class="chat-info">
                                <div class="chat-name">
                                    <?php echo htmlspecialchars($chatName); ?>
                                </div>
                                <div class="chat-last-message">
                                    <?php echo htmlspecialchars(substr($lastMessage, 0, 50)); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="loading">
                        <?php if (isset($chats) && $chats === false): ?>
                            Error al cargar chats. <a href="?debug=1" style="color: #00a884;">Ver detalles</a>
                        <?php else: ?>
                            Cargando chats...
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Panel derecho - Conversación -->
        <div class="chat-area">
            <?php if ($action === 'messages' && $chatId): ?>
                <?php
                // Obtener información del contacto actual para el header
                $currentChat = null;
                if (isset($chats) && is_array($chats)) {
                    foreach ($chats as $chat) {
                        if (($chat['remoteJid'] ?? $chat['id'] ?? '') === $chatId) {
                            $currentChat = $chat;
                            break;
                        }
                    }
                }
                $headerName = $currentChat['name'] ?? $currentChat['pushName'] ?? $chatId ?? 'Contacto';
                $headerAvatarLetter = substr($headerName, 0, 1);
                ?>
                <div class="chat-header">
                    <div class="chat-avatar" data-contact-id="<?php echo htmlspecialchars($chatId); ?>">
                        <div class="avatar-fallback">
                            <?php echo strtoupper($headerAvatarLetter); ?>
                        </div>
                        <div class="loading-photo"></div>
                    </div>
                    <div class="chat-info">
                        <div class="chat-name">
                            <?php echo htmlspecialchars($headerName); ?>
                        </div>
                    </div>
                </div>
                
                <div class="messages-container" id="messages-container">
                    <?php if (isset($messages) && $messages): ?>
                        <?php 
                        // Debug: mostrar estructura de mensajes
                        if ($debug) {
                            echo "<!-- Messages structure: " . json_encode($messages) . " -->";
                        }
                        
                        // Extraer el array de mensajes desde la estructura correcta
                        $messageArray = [];
                        if (isset($messages['messages']['records']) && is_array($messages['messages']['records'])) {
                            $messageArray = $messages['messages']['records'];
                        } elseif (isset($messages['records']) && is_array($messages['records'])) {
                            $messageArray = $messages['records'];
                        } elseif (is_array($messages)) {
                            $messageArray = $messages;
                        }
                        ?>
                        
                        <?php if (!empty($messageArray)): ?>
                            <?php foreach (array_reverse($messageArray) as $message): ?>
                                <?php
                                $isFromMe = $message['key']['fromMe'] ?? false;
                                $messageClass = $isFromMe ? 'sent' : 'received';
                                
                                // Extraer texto del mensaje según la estructura real
                                $messageText = '';
                                if (isset($message['message']['conversation'])) {
                                    $messageText = $message['message']['conversation'];
                                } elseif (isset($message['message']['extendedTextMessage']['text'])) {
                                    $messageText = $message['message']['extendedTextMessage']['text'];
                                } elseif ($message['messageType'] === 'stickerMessage') {
                                    $messageText = '🖼️ Sticker';
                                } elseif (isset($message['message']['imageMessage'])) {
                                    $messageText = '📷 Imagen';
                                } elseif (isset($message['message']['videoMessage'])) {
                                    $messageText = '🎥 Video';
                                } elseif (isset($message['message']['audioMessage'])) {
                                    $messageText = '🎵 Audio';
                                } else {
                                    $messageText = '📄 Mensaje multimedia';
                                }
                                
                                // Timestamp
                                $timestamp = $message['messageTimestamp'] ?? time();
                                $messageTime = date('H:i', $timestamp);
                                
                                // Nombre del contacto
                                $senderName = $isFromMe ? 'Tú' : ($message['pushName'] ?? 'Contacto');
                                ?>
                                <div class="message <?php echo $messageClass; ?>">
                                    <div class="message-text">
                                        <?php echo htmlspecialchars($messageText); ?>
                                    </div>
                                    <div class="message-time">
                                        <?php echo $messageTime; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div>No hay mensajes en esta conversación</div>
                                <?php if ($debug): ?>
                                    <div style="font-size: 12px; margin-top: 10px;">
                                        <a href="?action=messages&chat_id=<?php echo urlencode($chatId); ?>&debug=1" style="color: #00a884;">Ver estructura de datos</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div>No hay mensajes en esta conversación</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="message-input-area">
                    <form id="message-form" class="message-input-form">
                        <input type="text" 
                               id="message-input"
                               name="message" 
                               class="message-input" 
                               placeholder="Escribe un mensaje..." 
                               required>
                        <button type="submit" id="send-button" class="send-button">Enviar</button>
                    </form>
                    <div id="sending-indicator" class="sending-indicator">Enviando...</div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div>
                        <h3>Selecciona un chat para comenzar</h3>
                        <p>Elige una conversación del panel izquierdo para ver los mensajes</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Variables globales
        let lastMessageId = null;
        let isUpdating = false;
        let profilePicCache = new Map();
        let loadingPhotos = new Set(); // Para evitar cargas duplicadas
        
        // OPTIMIZADO: Función para cargar foto de perfil (SIN bloquear carga inicial)
        async function loadProfilePicture(contactId, avatarElement) {
            // Evitar cargas duplicadas
            if (loadingPhotos.has(contactId) || profilePicCache.has(contactId)) {
                return;
            }
            
            loadingPhotos.add(contactId);
            
            const loadingEl = avatarElement.querySelector('.loading-photo');
            const fallbackEl = avatarElement.querySelector('.avatar-fallback');
            
            // Mostrar indicador de carga
            if (loadingEl) {
                loadingEl.style.display = 'block';
            }
            
            try {
                const response = await fetch(`?action=get_profile_pic&contact_id=${encodeURIComponent(contactId)}`);
                const data = await response.json();
                
                if (data && data.url) {
                    // Crear y cargar imagen
                    const img = document.createElement('img');
                    img.onload = function() {
                        // Ocultar fallback y loading
                        if (fallbackEl) fallbackEl.style.display = 'none';
                        if (loadingEl) loadingEl.style.display = 'none';
                        // Insertar imagen
                        avatarElement.insertBefore(img, fallbackEl);
                        profilePicCache.set(contactId, data.url);
                    };
                    img.onerror = function() {
                        // En caso de error, mantener fallback
                        if (loadingEl) loadingEl.style.display = 'none';
                    };
                    img.src = data.url;
                    img.alt = contactId;
                } else {
                    // No hay foto, ocultar loading
                    if (loadingEl) loadingEl.style.display = 'none';
                }
            } catch (error) {
                console.error('Error loading profile picture:', error);
                if (loadingEl) loadingEl.style.display = 'none';
            } finally {
                loadingPhotos.delete(contactId);
            }
        }
        
        // OPTIMIZADO: Cargar fotos gradualmente después de que la página esté lista
        function loadProfilePicturesGradually() {
            const avatars = document.querySelectorAll('.chat-avatar[data-contact-id]');
            
            avatars.forEach((avatar, index) => {
                const contactId = avatar.getAttribute('data-contact-id');
                if (!contactId) return;
                
                // Cargar con delay escalonado para no saturar
                setTimeout(() => {
                    loadProfilePicture(contactId, avatar);
                }, index * 200); // 200ms entre cada carga
            });
        }
        
        // Verificar estado de conexión
        async function checkConnection() {
            try {
                const response = await fetch('?action=check_connection');
                const data = await response.json();
                
                const statusEl = document.getElementById('connection-status');
                const statusText = document.getElementById('status-text');
                
                if (data && data.state) {
                    switch(data.state) {
                        case 'open':
                            statusEl.className = 'connection-status connected';
                            statusText.textContent = 'Conectado';
                            break;
                        case 'connecting':
                            statusEl.className = 'connection-status connecting';
                            statusText.textContent = 'Conectando...';
                            break;
                        case 'close':
                            statusEl.className = 'connection-status disconnected';
                            statusText.textContent = 'Desconectado';
                            break;
                        default:
                            statusEl.className = 'connection-status disconnected';
                            statusText.textContent = 'Estado: ' + data.state;
                    }
                }
            } catch (error) {
                console.error('Error checking connection:', error);
            }
        }
        
        // Auto-scroll al final de los mensajes
        function scrollToBottom() {
            const messagesContainer = document.getElementById('messages-container');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
        
        // Función para escapar HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        <?php if ($action === 'messages' && $chatId): ?>
        
        // Función para actualizar mensajes
        async function updateMessages() {
            if (isUpdating) return;
            
            isUpdating = true;
            
            try {
                const response = await fetch(`?action=get_messages_ajax&chat_id=<?php echo urlencode($chatId); ?>`);
                const data = await response.json();
                
                // Extraer el array de mensajes de la estructura
                let messages = [];
                if (data && data.messages && data.messages.records) {
                    messages = data.messages.records;
                } else if (data && data.records) {
                    messages = data.records;
                } else if (Array.isArray(data)) {
                    messages = data;
                }
                
                if (messages.length > 0) {
                    const latestMessageId = messages[messages.length - 1]?.key?.id;
                    
                    if (latestMessageId !== lastMessageId) {
                        renderMessages(messages);
                        lastMessageId = latestMessageId;
                        
                        const container = document.getElementById('messages-container');
                        const wasNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
                        
                        if (wasNearBottom) {
                            scrollToBottom();
                        }
                        
                        if (lastMessageId !== null) {
                            playNotificationSound();
                        }
                    }
                }
            } catch (error) {
                console.error('Error updating messages:', error);
            } finally {
                isUpdating = false;
            }
        }
        
        // Función para renderizar mensajes
        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            container.innerHTML = '';
            
            messages.sort((a, b) => (a.messageTimestamp || 0) - (b.messageTimestamp || 0));
            
            messages.forEach(message => {
                const isFromMe = message.key?.fromMe || false;
                const messageClass = isFromMe ? 'sent' : 'received';
                
                let messageText = '';
                if (message.message?.conversation) {
                    messageText = message.message.conversation;
                } else if (message.message?.extendedTextMessage?.text) {
                    messageText = message.message.extendedTextMessage.text;
                } else if (message.messageType === 'stickerMessage') {
                    messageText = '🖼️ Sticker';
                } else if (message.message?.imageMessage) {
                    messageText = '📷 Imagen';
                } else if (message.message?.videoMessage) {
                    messageText = '🎥 Video';
                } else if (message.message?.audioMessage) {
                    messageText = '🎵 Audio';
                } else if (message.message?.documentMessage) {
                    messageText = '📄 Documento';
                } else {
                    messageText = '📎 Archivo multimedia';
                }
                
                const timestamp = message.messageTimestamp || Math.floor(Date.now() / 1000);
                const messageTime = new Date(timestamp * 1000).toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageClass}`;
                messageDiv.innerHTML = `
                    <div class="message-text">${escapeHtml(messageText)}</div>
                    <div class="message-time">${messageTime}</div>
                `;
                
                container.appendChild(messageDiv);
            });
        }
        
        // Función para reproducir sonido de notificación
        function playNotificationSound() {
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRl9vT19uZXkAAA==');
                audio.volume = 0.3;
                audio.play().catch(e => console.log('No se pudo reproducir el sonido:', e));
            } catch (e) {
                console.log('Error con el sonido:', e);
            }
        }
        
        // Envío de mensajes
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-button');
        const sendingIndicator = document.getElementById('sending-indicator');
        const messagesContainer = document.getElementById('messages-container');
        
        if (messageForm) {
            messageForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (!message) return;
                
                messageInput.disabled = true;
                sendButton.disabled = true;
                sendingIndicator.style.display = 'block';
                
                const tempId = 'temp_' + Date.now();
                
                const tempMessage = document.createElement('div');
                tempMessage.className = 'message sent';
                tempMessage.id = tempId;
                tempMessage.innerHTML = `
                    <div class="message-text">${escapeHtml(message)}</div>
                    <div class="message-time">${new Date().toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'})}</div>
                    <div class="message-status" style="font-size: 10px; color: #8696a0;">⏱️ Enviando...</div>
                `;
                messagesContainer.appendChild(tempMessage);
                scrollToBottom();
                
                try {
                    const formData = new FormData();
                    formData.append('message', message);
                    
                    const response = await fetch(`?action=send_ajax&chat_id=<?php echo urlencode($chatId); ?>`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        messageInput.value = '';
                        
                        const statusEl = tempMessage.querySelector('.message-status');
                        if (statusEl) {
                            statusEl.innerHTML = '✓ Enviado';
                        }
                        
                        setTimeout(() => {
                            updateMessages();
                        }, 500);
                    } else {
                        throw new Error(result.result?.message || 'Error al enviar');
                    }
                } catch (error) {
                    const statusEl = tempMessage.querySelector('.message-status');
                    if (statusEl) {
                        statusEl.innerHTML = '❌ Error al enviar';
                        statusEl.style.color = '#f15c6d';
                    }
                    
                    alert('Error al enviar el mensaje: ' + error.message);
                }
                
                messageInput.disabled = false;
                sendButton.disabled = false;
                sendingIndicator.style.display = 'none';
                messageInput.focus();
            });
        }
        
        // Permitir enviar con Enter
        if (messageInput) {
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    messageForm.dispatchEvent(new Event('submit'));
                }
            });
        }
        
        // Inicializar actualización de mensajes
        updateMessages();
        
        // Actualizar cada 3 segundos
        setInterval(() => {
            if (document.activeElement !== messageInput) {
                updateMessages();
            }
        }, 3000);
        
        // Actualizar cuando la ventana recupera el foco
        window.addEventListener('focus', () => {
            updateMessages();
        });
        
        <?php endif; ?>
        
        // Inicializar todo cuando la página esté lista
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar conexión inmediatamente
            checkConnection();
            
            // Cargar fotos después de un pequeño delay
            setTimeout(() => {
                loadProfilePicturesGradually();
            }, 100);
            
            // Scroll inicial
            scrollToBottom();
        });
        
        // Verificar conexión cada 15 segundos (menos frecuente)
       // setInterval(checkConnection, 15000);
        
    </script>
</body>
</html>