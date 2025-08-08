<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_board':
                $name = trim($input['name'] ?? '');
                $description = trim($input['description'] ?? '');
                $color = $input['color'] ?? '#667eea';
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
                    exit;
                }
                
                // Crear el tablero
                $query = "INSERT INTO task_boards (user_id, name, description, color) VALUES (:user_id, :name, :description, :color)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':color', $color);
                
                if ($stmt->execute()) {
                    $board_id = $db->lastInsertId();
                    
                    // Crear columnas por defecto
                    $default_columns = [
                        ['name' => 'Por Hacer', 'position' => 1],
                        ['name' => 'En Progreso', 'position' => 2],
                        ['name' => 'En Revisión', 'position' => 3],
                        ['name' => 'Completado', 'position' => 4]
                    ];
                    
                    foreach ($default_columns as $column) {
                        $query = "INSERT INTO task_columns (board_id, name, position) VALUES (:board_id, :name, :position)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':board_id', $board_id);
                        $stmt->bindParam(':name', $column['name']);
                        $stmt->bindParam(':position', $column['position']);
                        $stmt->execute();
                    }
                    
                    echo json_encode(['success' => true, 'board_id' => $board_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al crear el tablero']);
                }
                break;
                
            case 'create_task':
                $column_id = $input['column_id'] ?? '';
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                $priority = $input['priority'] ?? 'medium';
                $assigned_to = $input['assigned_to'] ?? null;
                $due_date = $input['due_date'] ?? null;
                
                if (empty($column_id) || empty($title)) {
                    echo json_encode(['success' => false, 'message' => 'Columna y título son obligatorios']);
                    exit;
                }
                
                // Obtener la siguiente posición
                $query = "SELECT MAX(position) as max_pos FROM tasks WHERE column_id = :column_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':column_id', $column_id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $position = ($result['max_pos'] ?? 0) + 1;
                
                // Crear la tarea
                $query = "INSERT INTO tasks (column_id, title, description, position, priority, due_date, assigned_to, created_by) 
                          VALUES (:column_id, :title, :description, :position, :priority, :due_date, :assigned_to, :created_by)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':column_id', $column_id);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':position', $position);
                $stmt->bindParam(':priority', $priority);
                $stmt->bindParam(':due_date', $due_date);
                $stmt->bindParam(':assigned_to', $assigned_to);
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $task_id = $db->lastInsertId();
                    
                    // Registrar actividad
                    logTaskActivity($db, $task_id, $_SESSION['user_id'], 'created', ['title' => $title]);
                    
                    echo json_encode(['success' => true, 'task_id' => $task_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al crear la tarea']);
                }
                break;
                
            case 'move_task':
                $task_id = $input['task_id'] ?? '';
                $column_id = $input['column_id'] ?? '';
                
                if (empty($task_id) || empty($column_id)) {
                    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                    exit;
                }
                
                // Obtener información actual de la tarea
                $query = "SELECT t.*, c.name as old_column_name 
                          FROM tasks t 
                          JOIN task_columns c ON t.column_id = c.id 
                          WHERE t.id = :task_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':task_id', $task_id);
                $stmt->execute();
                $old_task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$old_task) {
                    echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
                    exit;
                }
                
                // Obtener nombre de la nueva columna
                $query = "SELECT name FROM task_columns WHERE id = :column_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':column_id', $column_id);
                $stmt->execute();
                $new_column = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Actualizar la tarea
                $query = "UPDATE tasks SET column_id = :column_id WHERE id = :task_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':column_id', $column_id);
                $stmt->bindParam(':task_id', $task_id);
                
                if ($stmt->execute()) {
                    // Registrar actividad
                    logTaskActivity($db, $task_id, $_SESSION['user_id'], 'moved', [
                        'from' => $old_task['old_column_name'],
                        'to' => $new_column['name']
                    ]);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al mover la tarea']);
                }
                break;
                
            case 'update_task':
                $task_id = $input['task_id'] ?? '';
                $updates = [];
                
                if (empty($task_id)) {
                    echo json_encode(['success' => false, 'message' => 'ID de tarea requerido']);
                    exit;
                }
                
                // Construir la consulta de actualización dinámicamente
                $allowed_fields = ['title', 'description', 'priority', 'due_date', 'assigned_to', 'is_completed'];
                $set_clauses = [];
                $params = [':task_id' => $task_id];
                
                foreach ($allowed_fields as $field) {
                    if (isset($input[$field])) {
                        $set_clauses[] = "$field = :$field";
                        $params[":$field"] = $input[$field];
                        $updates[$field] = $input[$field];
                    }
                }
                
                if (empty($set_clauses)) {
                    echo json_encode(['success' => false, 'message' => 'No hay campos para actualizar']);
                    exit;
                }
                
                // Si se está completando la tarea
                if (isset($input['is_completed']) && $input['is_completed']) {
                    $set_clauses[] = "completed_at = NOW()";
                }
                
                $query = "UPDATE tasks SET " . implode(', ', $set_clauses) . " WHERE id = :task_id";
                $stmt = $db->prepare($query);
                
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                
                if ($stmt->execute()) {
                    // Registrar actividad
                    logTaskActivity($db, $task_id, $_SESSION['user_id'], 'updated', $updates);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar la tarea']);
                }
                break;
                
            case 'delete_task':
                $task_id = $input['task_id'] ?? '';
                
                if (empty($task_id)) {
                    echo json_encode(['success' => false, 'message' => 'ID de tarea requerido']);
                    exit;
                }
                
                // Obtener información de la tarea antes de eliminar
                $query = "SELECT title FROM tasks WHERE id = :task_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':task_id', $task_id);
                $stmt->execute();
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$task) {
                    echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
                    exit;
                }
                
                // Eliminar la tarea
                $query = "DELETE FROM tasks WHERE id = :task_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':task_id', $task_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Tarea eliminada']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar la tarea']);
                }
                break;
                
            case 'add_comment':
                $task_id = $input['task_id'] ?? '';
                $comment = trim($input['comment'] ?? '');
                
                if (empty($task_id) || empty($comment)) {
                    echo json_encode(['success' => false, 'message' => 'Tarea y comentario son obligatorios']);
                    exit;
                }
                
                // Agregar comentario
                $query = "INSERT INTO task_comments (task_id, user_id, comment) VALUES (:task_id, :user_id, :comment)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':task_id', $task_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':comment', $comment);
                
                if ($stmt->execute()) {
                    // Registrar actividad
                    logTaskActivity($db, $task_id, $_SESSION['user_id'], 'commented', ['comment' => substr($comment, 0, 50) . '...']);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al agregar el comentario']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                break;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

function logTaskActivity($db, $task_id, $user_id, $action, $details = []) {
    try {
        $query = "INSERT INTO task_activities (task_id, user_id, action, details) 
                  VALUES (:task_id, :user_id, :action, :details)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':task_id', $task_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', json_encode($details));
        $stmt->execute();
    } catch (Exception $e) {
        // No interrumpir la operación principal si falla el log
        error_log("Error logging task activity: " . $e->getMessage());
    }
}
?>