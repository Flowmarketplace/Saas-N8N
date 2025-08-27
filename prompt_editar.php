<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$prompt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener el prompt con verificación de permisos
$query = "SELECT p.*, a.name as automation_name, a.user_id 
          FROM automatizacion_prompts p 
          JOIN automations a ON p.automation_id = a.id 
          WHERE p.id = :id AND a.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $prompt_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$prompt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prompt) {
    die('Prompt no encontrado o sin permisos');
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete'])) {
        // Eliminar prompt
        $query = "DELETE FROM automatizacion_prompts WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $prompt_id);
        $stmt->execute();
        
        header("Location: prompts_lista.php?automation_id={$prompt['automation_id']}");
        exit;
    } else {
        // Actualizar prompt
        $query = "UPDATE automatizacion_prompts SET 
                  nombre = :nombre,
                  prompt = :prompt,
                  airtable_db_id = :airtable_db_id,
                  airtable_table_id = :airtable_table_id,
                  airtable_token = :airtable_token,
                  n8n_workflow_id = :n8n_workflow_id,
                  n8n_api_key = :n8n_api_key,
                  estado = :estado
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nombre' => $_POST['nombre'],
            ':prompt' => $_POST['prompt'],
            ':airtable_db_id' => $_POST['airtable_db_id'],
            ':airtable_table_id' => $_POST['airtable_table_id'],
            ':airtable_token' => $_POST['airtable_token'],
            ':n8n_workflow_id' => $_POST['n8n_workflow_id'] ?? null,
            ':n8n_api_key' => $_POST['n8n_api_key'] ?? null,
            ':estado' => $_POST['estado'],
            ':id' => $prompt_id
        ]);
        
        header("Location: prompts_lista.php?automation_id={$prompt['automation_id']}&updated=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Prompt - <?php echo htmlspecialchars($prompt['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #1a1d29; 
            color: #fff; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .container { margin-top: 30px; }
        .card { 
            background: #2d3148; 
            border: 1px solid #3a3f5c;
            border-radius: 16px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }
        .form-control, .form-select { 
            background: #1a1d29; 
            border: 1px solid #3a3f5c; 
            color: #fff;
            border-radius: 10px;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus { 
            background: #1a1d29; 
            border-color: #667eea; 
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .alert-info {
            background: rgba(79, 172, 254, 0.1);
            border: 1px solid rgba(79, 172, 254, 0.3);
            color: #4facfe;
        }
        .section-title {
            color: #4facfe;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-label {
            color: #a0a6b8;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
        }
        .btn-secondary {
            background: #3a3f5c;
            border: none;
        }
        textarea.form-control {
            min-height: 150px;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .status-badge.activo {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .status-badge.inactivo {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">
                            <i class="fas fa-edit me-2"></i>
                            Editar Prompt
                        </h4>
                        <small>Automatización: <?php echo htmlspecialchars($prompt['automation_name']); ?></small>
                    </div>
                    <span class="status-badge <?php echo $prompt['estado']; ?>">
                        <?php echo ucfirst($prompt['estado']); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="editForm">
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-tag me-1"></i>
                            Nombre del Prompt
                        </label>
                        <input type="text" class="form-control" name="nombre" 
                               value="<?php echo htmlspecialchars($prompt['nombre']); ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-file-alt me-1"></i>
                            Prompt
                        </label>
                        <textarea class="form-control" name="prompt" rows="8" required><?php echo htmlspecialchars($prompt['prompt']); ?></textarea>
                        <small class="form-text text-muted mt-1">
                            Este prompt será enviado a la IA para procesar las respuestas
                        </small>
                    </div>
                    
                    <h5 class="section-title mt-5">
                        <i class="fas fa-database"></i>
                        Credenciales de Airtable
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Database ID</label>
                            <input type="text" class="form-control" name="airtable_db_id" 
                                   value="<?php echo htmlspecialchars($prompt['airtable_db_id']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Table ID</label>
                            <input type="text" class="form-control" name="airtable_table_id" 
                                   value="<?php echo htmlspecialchars($prompt['airtable_table_id']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Token de Airtable</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="airtable_token" 
                                   id="airtableToken"
                                   value="<?php echo htmlspecialchars($prompt['airtable_token']); ?>" required>
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="togglePassword('airtableToken')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <h5 class="section-title mt-5">
                        <i class="fas fa-cog"></i>
                        Configuración N8N (Opcional)
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Workflow ID</label>
                            <input type="text" class="form-control" name="n8n_workflow_id"
                                   value="<?php echo htmlspecialchars($prompt['n8n_workflow_id'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">API Key</label>
                            <input type="password" class="form-control" name="n8n_api_key" id="n8nApiKey"
                                   value="<?php echo htmlspecialchars($prompt['n8n_api_key'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-toggle-on me-1"></i>
                            Estado
                        </label>
                        <select class="form-select" name="estado">
                            <option value="activo" <?php echo $prompt['estado'] == 'activo' ? 'selected' : ''; ?>>
                                Activo
                            </option>
                            <option value="inactivo" <?php echo $prompt['estado'] == 'inactivo' ? 'selected' : ''; ?>>
                                Inactivo
                            </option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>
                            <strong>Última actualización:</strong> 
                            <?php echo date('d/m/Y H:i', strtotime($prompt['updated_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash me-2"></i>
                            Eliminar Prompt
                        </button>
                        
                        <div class="d-flex gap-2">
                            <a href="prompts_lista.php?automation_id=<?php echo $prompt['automation_id']; ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>
                                Cancelar
                            </a>
                            <button type="button" class="btn btn-info" onclick="testAirtable()">
                                <i class="fas fa-vial me-2"></i>
                                Probar Conexión
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
        
        function confirmDelete() {
            if (confirm('¿Estás seguro de eliminar este prompt? Esta acción no se puede deshacer.')) {
                const form = document.getElementById('editForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        }
        
        function testAirtable() {
            alert('Probando conexión con Airtable...');
            // Aquí podrías hacer una llamada AJAX para probar la conexión
        }
    </script>
</body>
</html>