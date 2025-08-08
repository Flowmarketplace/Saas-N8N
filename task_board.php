<?php
session_start();
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener tableros del usuario
$query = "SELECT * FROM task_boards WHERE user_id = :user_id AND is_active = 1 ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$boards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si hay un tablero seleccionado, obtener sus columnas y tareas
$selected_board_id = $_GET['board'] ?? ($boards[0]['id'] ?? null);
$columns = [];
$tasks = [];

if ($selected_board_id) {
    // Obtener columnas
    $query = "SELECT * FROM task_columns WHERE board_id = :board_id ORDER BY position";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':board_id', $selected_board_id);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener tareas con información del usuario asignado
    $query = "SELECT t.*, u.username as assigned_username 
              FROM tasks t 
              LEFT JOIN users u ON t.assigned_to = u.id 
              WHERE t.column_id IN (SELECT id FROM task_columns WHERE board_id = :board_id) 
              ORDER BY t.position";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':board_id', $selected_board_id);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tareas - n8n CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark-bg: #1a1d29;
            --sidebar-bg: #242738;
            --card-bg: #2d3148;
            --text-primary: #ffffff;
            --text-secondary: #8b8fa3;
            --border-color: #3a3f5c;
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        .main-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            overflow-x: auto;
        }

        .board-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .board-selector {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
        }

        .board-container {
            display: flex;
            gap: 1.5rem;
            min-height: 600px;
            overflow-x: auto;
            padding-bottom: 2rem;
        }

        .task-column {
            background: var(--card-bg);
            border-radius: 12px;
            width: 300px;
            min-width: 300px;
            padding: 1rem;
            border: 1px solid var(--border-color);
        }

        .column-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .column-title {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .task-count {
            background: var(--primary-gradient);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        .task-list {
            min-height: 400px;
        }

        .task-card {
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: move;
            transition: all 0.3s ease;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .task-card.dragging {
            opacity: 0.5;
        }

        .task-title {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .task-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .task-meta {
            display: flex;
            justify-content: between;
            align-items: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .task-priority {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .priority-low { background: #28a745; color: white; }
        .priority-medium { background: #ffc107; color: #000; }
        .priority-high { background: #fd7e14; color: white; }
        .priority-urgent { background: #dc3545; color: white; }

        .add-task-btn {
            width: 100%;
            padding: 0.75rem;
            background: transparent;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-task-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .task-form {
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }

        .form-control, .form-select {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            background: var(--card-bg);
            border-color: #667eea;
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .drag-over {
            background: rgba(102, 126, 234, 0.1);
            border: 2px dashed #667eea;
        }

        .task-assigned {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
        }

        .task-assigned-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.625rem;
            font-weight: bold;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-brand {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .sidebar-brand h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin: 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 2rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: var(--text-primary);
        }

        .nav-link.active {
            background: var(--primary-gradient);
            color: var(--text-primary);
        }

        .nav-link i {
            width: 20px;
            margin-right: 1rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-brand">
                <h4><i class="fas fa-robot me-2"></i> n8n CRM</h4>
                <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0.5rem 0 0;">
                    Gestión de Tareas
                </p>
            </div>
            
            <div>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="#" class="nav-link active">
                    <i class="fas fa-tasks"></i>
                    Tareas
                </a>
                <a href="credentials.php" class="nav-link">
                    <i class="fas fa-key"></i>
                    Credenciales
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="board-header mb-4">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="d-flex align-items-center gap-3">
                        <h1 class="h3 mb-0">Tablero de Tareas</h1>
                        <?php if (!empty($boards)): ?>
                        <select class="board-selector" onchange="changeBoard(this.value)">
                            <?php foreach ($boards as $board): ?>
                            <option value="<?php echo $board['id']; ?>" <?php echo $board['id'] == $selected_board_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($board['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newBoardModal">
                            <i class="fas fa-plus me-2"></i>Nuevo Tablero
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($boards)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-4x mb-3" style="color: var(--text-secondary);"></i>
                    <h4>No tienes tableros aún</h4>
                    <p style="color: var(--text-secondary);">Crea tu primer tablero para comenzar a gestionar tareas</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newBoardModal">
                        <i class="fas fa-plus me-2"></i>Crear Tablero
                    </button>
                </div>
            <?php else: ?>
                <div class="board-container">
                    <?php foreach ($columns as $column): ?>
                    <div class="task-column" data-column-id="<?php echo $column['id']; ?>">
                        <div class="column-header">
                            <h5 class="column-title mb-0"><?php echo htmlspecialchars($column['name']); ?></h5>
                            <span class="task-count">
                                <?php 
                                $task_count = count(array_filter($tasks, function($task) use ($column) {
                                    return $task['column_id'] == $column['id'];
                                }));
                                echo $task_count;
                                ?>
                            </span>
                        </div>
                        
                        <div class="task-list" ondrop="drop(event, <?php echo $column['id']; ?>)" ondragover="allowDrop(event)">
                            <?php foreach ($tasks as $task): ?>
                                <?php if ($task['column_id'] == $column['id']): ?>
                                <div class="task-card" draggable="true" ondragstart="drag(event)" data-task-id="<?php echo $task['id']; ?>">
                                    <h6 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h6>
                                    <?php if (!empty($task['description'])): ?>
                                    <p class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)) . '...'; ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="task-meta">
                                        <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        
                                        <?php if ($task['assigned_to']): ?>
                                        <div class="task-assigned">
                                            <div class="task-assigned-avatar">
                                                <?php echo strtoupper(substr($task['assigned_username'], 0, 2)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($task['assigned_username']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($task['due_date']): ?>
                                    <div class="mt-2">
                                        <small style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d/m/Y', strtotime($task['due_date'])); ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <button class="add-task-btn" onclick="showAddTaskForm(<?php echo $column['id']; ?>)">
                            <i class="fas fa-plus me-2"></i>Agregar tarea
                        </button>
                        
                        <div id="task-form-<?php echo $column['id']; ?>" class="task-form" style="display: none;">
                            <form onsubmit="addTask(event, <?php echo $column['id']; ?>)">
                                <div class="mb-2">
                                    <input type="text" class="form-control form-control-sm" placeholder="Título de la tarea" required>
                                </div>
                                <div class="mb-2">
                                    <textarea class="form-control form-control-sm" rows="2" placeholder="Descripción (opcional)"></textarea>
                                </div>
                                <div class="mb-2">
                                    <select class="form-select form-select-sm">
                                        <option value="low">Baja</option>
                                        <option value="medium" selected>Media</option>
                                        <option value="high">Alta</option>
                                        <option value="urgent">Urgente</option>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="hideAddTaskForm(<?php echo $column['id']; ?>)">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal Nuevo Tablero -->
    <div class="modal fade" id="newBoardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Crear Nuevo Tablero
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newBoardForm">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Tablero</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" name="color" value="#667eea">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="createBoard()">
                        <i class="fas fa-save me-2"></i>Crear Tablero
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let draggedTask = null;

        function changeBoard(boardId) {
            window.location.href = `task_board.php?board=${boardId}`;
        }

        function drag(event) {
            draggedTask = event.target;
            event.target.classList.add('dragging');
        }

        function allowDrop(event) {
            event.preventDefault();
            const taskList = event.target.closest('.task-list');
            if (taskList) {
                taskList.classList.add('drag-over');
            }
        }

        function drop(event, columnId) {
            event.preventDefault();
            const taskList = event.target.closest('.task-list');
            if (taskList) {
                taskList.classList.remove('drag-over');
            }

            if (draggedTask) {
                const taskId = draggedTask.getAttribute('data-task-id');
                
                // Agregar la tarea a la nueva columna
                taskList.appendChild(draggedTask);
                draggedTask.classList.remove('dragging');
                
                // Actualizar en el servidor
                updateTaskColumn(taskId, columnId);
                
                // Actualizar contadores
                updateColumnCounts();
                
                draggedTask = null;
            }
        }

        function updateTaskColumn(taskId, columnId) {
            fetch('task_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'move_task',
                    task_id: taskId,
                    column_id: columnId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error al mover la tarea');
                    location.reload();
                }
            });
        }

        function updateColumnCounts() {
            document.querySelectorAll('.task-column').forEach(column => {
                const count = column.querySelectorAll('.task-card').length;
                column.querySelector('.task-count').textContent = count;
            });
        }

        function showAddTaskForm(columnId) {
            document.getElementById(`task-form-${columnId}`).style.display = 'block';
            document.querySelector(`[onclick="showAddTaskForm(${columnId})"]`).style.display = 'none';
        }

        function hideAddTaskForm(columnId) {
            document.getElementById(`task-form-${columnId}`).style.display = 'none';
            document.querySelector(`[onclick="showAddTaskForm(${columnId})"]`).style.display = 'block';
            document.getElementById(`task-form-${columnId}`).querySelector('form').reset();
        }

        function addTask(event, columnId) {
            event.preventDefault();
            
            const form = event.target;
            const title = form.querySelector('input[type="text"]').value;
            const description = form.querySelector('textarea').value;
            const priority = form.querySelector('select').value;
            
            fetch('task_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_task',
                    column_id: columnId,
                    title: title,
                    description: description,
                    priority: priority
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al crear la tarea');
                }
            });
        }

        function createBoard() {
            const form = document.getElementById('newBoardForm');
            const formData = new FormData(form);
            
            fetch('task_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_board',
                    name: formData.get('name'),
                    description: formData.get('description'),
                    color: formData.get('color')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `task_board.php?board=${data.board_id}`;
                } else {
                    alert('Error al crear el tablero');
                }
            });
        }

        // Prevenir el comportamiento por defecto del drag and drop en el documento
        document.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        document.addEventListener('drop', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>