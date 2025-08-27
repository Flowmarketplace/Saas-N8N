<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$automation_id = isset($_GET['automation_id']) ? intval($_GET['automation_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar propiedad
    $query = "SELECT id FROM automations WHERE id = :automation_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':automation_id', $automation_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $query = "INSERT INTO automatizacion_prompts 
                  (automation_id, nombre, prompt, airtable_db_id, airtable_table_id, 
                   airtable_token, n8n_workflow_id, n8n_api_key) 
                  VALUES 
                  (:automation_id, :nombre, :prompt, :airtable_db_id, :airtable_table_id, 
                   :airtable_token, :n8n_workflow_id, :n8n_api_key)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':automation_id' => $automation_id,
            ':nombre' => $_POST['nombre'],
            ':prompt' => $_POST['prompt'],
            ':airtable_db_id' => $_POST['airtable_db_id'],
            ':airtable_table_id' => $_POST['airtable_table_id'],
            ':airtable_token' => $_POST['airtable_token'],
            ':n8n_workflow_id' => $_POST['n8n_workflow_id'] ?? null,
            ':n8n_api_key' => $_POST['n8n_api_key'] ?? null
        ]);
        
        header("Location: prompts_lista.php?automation_id=$automation_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir Prompt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1d29; color: #fff; }
        .container { margin-top: 30px; }
        .card { background: #2d3148; border: 1px solid #3a3f5c; }
        .form-control, .form-select { 
            background: #1a1d29; 
            border: 1px solid #3a3f5c; 
            color: #fff; 
        }
        .form-control:focus { 
            background: #1a1d29; 
            border-color: #667eea; 
            color: #fff; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h4>Nuevo Prompt</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Prompt</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Prompt</label>
                        <textarea class="form-control" name="prompt" rows="6" required 
                                  placeholder="Escribe aquí tu prompt para la IA..."></textarea>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Credenciales de Airtable</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Database ID</label>
                            <input type="text" class="form-control" name="airtable_db_id" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Table ID</label>
                            <input type="text" class="form-control" name="airtable_table_id" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Token de Airtable</label>
                        <input type="text" class="form-control" name="airtable_token" required 
                               placeholder="Bearer pat...">
                    </div>
                    
                    <h5 class="mt-4 mb-3">Configuración N8N (Opcional)</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Workflow ID</label>
                            <input type="text" class="form-control" name="n8n_workflow_id">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" class="form-control" name="n8n_api_key">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="prompts_lista.php?automation_id=<?php echo $automation_id; ?>" 
                           class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Prompt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>