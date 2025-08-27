<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener automation_id desde GET
$automation_id = isset($_GET['automation_id']) ? intval($_GET['automation_id']) : 0;

if (!$automation_id) {
    die('ID de automatización no válido');
}

// Verificar que el usuario sea dueño de la automatización
$query = "SELECT * FROM automations WHERE id = :automation_id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':automation_id', $automation_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    die('No tienes permisos para ver esta automatización');
}

$automation = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener prompts
$query = "SELECT * FROM automatizacion_prompts WHERE automation_id = :automation_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':automation_id', $automation_id);
$stmt->execute();
$prompts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Prompts - <?php echo htmlspecialchars($automation['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1d29; color: #fff; }
        .container { margin-top: 30px; }
        .card { background: #2d3148; border: 1px solid #3a3f5c; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .table { color: #fff; }
        .table td, .table th { border-color: #3a3f5c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Prompts de: <?php echo htmlspecialchars($automation['name']); ?></h2>
            <div>
                <a href="prompt_nuevo.php?automation_id=<?php echo $automation_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Añadir Prompt
                </a>
                <a href="dashboard.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>

        <?php if (empty($prompts)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h5>No hay prompts configurados</h5>
                    <p>Comienza agregando tu primer prompt para esta automatización</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Airtable DB</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prompts as $prompt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prompt['nombre']); ?></td>
                                    <td>
                                        <small><?php echo substr($prompt['airtable_db_id'], 0, 20); ?>...</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $prompt['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($prompt['estado']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($prompt['created_at'])); ?></td>
                                    <td>
                                        <a href="prompt_editar.php?id=<?php echo $prompt['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">Editar</a>
                                        <button onclick="sincronizarAirtable(<?php echo $prompt['id']; ?>)" 
                                                class="btn btn-sm btn-outline-info">Sincronizar</button>
                                    </td>
                                    <td>                             <button onclick="procesarYActualizar(<?php echo $prompt['id']; ?>)" 
        class="btn btn-sm btn-warning text-white"
        title="Procesar todos los registros y actualizar el campo INSTRUCCION">
    <i class="fas fa-sync-alt"></i> Procesar
</button> </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function sincronizarAirtable(promptId) {
            if (confirm('¿Sincronizar con Airtable ahora?')) {
                fetch('sincronizar_airtable.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt_id: promptId })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }

        function procesarYActualizar(promptId) {
    if (!confirm('¿Estás seguro? Esto actualizará el campo INSTRUCCION de todos los registros en Airtable con el prompt configurado.')) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btn.disabled = true;
    
    fetch('sincronizar_airtable.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt_id: promptId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ ${data.message}`);
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    })
    .catch(error => {
        alert('Error de conexión: ' + error);
    })
    .finally(() => {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}
    </script>
</body>
</html>