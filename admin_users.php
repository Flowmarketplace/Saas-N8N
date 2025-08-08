<?php
session_start();
require_once 'config/database.php';

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario es admin
$query = "SELECT role FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user || $current_user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Manejar acciones POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   
   $data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$user_id = $data['user_id'] ?? '';

if (!empty($action) && !empty($user_id)) {
    switch ($action) {
        case 'approve':
            $query = "UPDATE users SET status = 'active' WHERE id = :user_id";
            break;
        case 'reject':
            $query = "UPDATE users SET status = 'rejected' WHERE id = :user_id";
            break;
        case 'suspend':
            $query = "UPDATE users SET status = 'suspended' WHERE id = :user_id";
            break;
        case 'activate':
            $query = "UPDATE users SET status = 'active' WHERE id = :user_id";
            break;
        case 'delete':
            $query = "DELETE FROM users WHERE id = :user_id AND role != 'admin'";
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Acción inválida']);
            exit;
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $success = $stmt->execute();

    echo json_encode(['success' => $success]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}
    }


// Obtener estadísticas
$stats = [];

// Total usuarios
$query = "SELECT COUNT(*) as total FROM users WHERE role != 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Usuarios pendientes
$query = "SELECT COUNT(*) as pending FROM users WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];

// Usuarios activos
$query = "SELECT COUNT(*) as active FROM users WHERE status = 'active' AND role != 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

// Usuarios suspendidos/rechazados
$query = "SELECT COUNT(*) as inactive FROM users WHERE status IN ('suspended', 'rejected')";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['inactive'] = $stmt->fetch(PDO::FETCH_ASSOC)['inactive'];

// Obtener todos los usuarios
$query = "SELECT id, username, email, full_name, company, role, status, created_at 
          FROM users 
          ORDER BY 
            CASE 
              WHEN status = 'pending' THEN 1 
              WHEN status = 'active' THEN 2 
              WHEN status = 'suspended' THEN 3 
              WHEN status = 'rejected' THEN 4 
              ELSE 5 
            END, 
            created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function getStatusBadge($status) {
    switch ($status) {
        case 'active':
            return '<span class="badge bg-success">Activo</span>';
        case 'pending':
            return '<span class="badge bg-warning">Pendiente</span>';
        case 'suspended':
            return '<span class="badge bg-danger">Suspendido</span>';
        case 'rejected':
            return '<span class="badge bg-secondary">Rechazado</span>';
        default:
            return '<span class="badge bg-light text-dark">Desconocido</span>';
    }
}

function getRoleBadge($role) {
    switch ($role) {
        case 'admin':
            return '<span class="badge bg-primary">Administrador</span>';
        case 'user':
            return '<span class="badge bg-info">Usuario</span>';
        default:
            return '<span class="badge bg-light text-dark">' . ucfirst($role) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios - Plataforma de Automatizaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        .main-content {
            padding: 2rem 0;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease;
            margin-bottom: 1rem;
        }
        .stats-card:hover {
            transform: translateY(-3px);
        }
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 1rem;
        }
        .stats-icon.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-icon.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .stats-icon.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .stats-icon.danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        .users-table-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-top: 2rem;
        }
        .btn-action {
            border-radius: 8px;
            font-size: 12px;
            padding: 5px 10px;
            margin: 2px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .pending-highlight {
            background-color: #fff3cd !important;
        }
        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .table th {
            border-top: none;
            color: #495057;
            font-weight: 600;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 8px 12px;
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-robot me-2"></i>
                Panel de Administración
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-2"></i>
                        <?php echo $_SESSION['username']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Cerrar Sesión
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Administración de Usuarios</h2>
                <p class="text-muted mb-0">Gestiona registros y permisos de usuarios</p>
            </div>
            <?php if ($stats['pending'] > 0): ?>
                <div class="alert alert-warning d-flex align-items-center mb-0" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?php echo $stats['pending']; ?></strong> usuario(s) pendiente(s) de aprobación
                </div>
            <?php endif; ?>
        </div>

        <!-- Estadísticas -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0">Total Usuarios</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                    <p class="text-muted mb-0">Pendientes</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="mb-0"><?php echo $stats['active']; ?></h3>
                    <p class="text-muted mb-0">Activos</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon danger">
                        <i class="fas fa-ban"></i>
                    </div>
                    <h3 class="mb-0"><?php echo $stats['inactive']; ?></h3>
                    <p class="text-muted mb-0">Inactivos</p>
                </div>
            </div>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="users-table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Lista de Usuarios</h4>
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Los usuarios pendientes aparecen resaltados
                </small>
            </div>
            
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Información</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr class="<?php echo $user['status'] === 'pending' ? 'pending-highlight' : ''; ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="fw-medium"><?php echo htmlspecialchars($user['full_name'] ?: 'N/A'); ?></div>
                                    <?php if (!empty($user['company'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo htmlspecialchars($user['company']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo getRoleBadge($user['role']); ?></td>
                            <td><?php echo getStatusBadge($user['status']); ?></td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <div class="btn-group-vertical" role="group">
                                        <?php if ($user['status'] === 'pending'): ?>
                                            <button class="btn btn-success btn-action" onclick="performAction('approve', <?php echo $user['id']; ?>)">
                                                <i class="fas fa-check me-1"></i> Aprobar
                                            </button>
                                            <button class="btn btn-danger btn-action" onclick="performAction('reject', <?php echo $user['id']; ?>)">
                                                <i class="fas fa-times me-1"></i> Rechazar
                                            </button>
                                        <?php elseif ($user['status'] === 'active'): ?>
                                            <button class="btn btn-warning btn-action" onclick="performAction('suspend', <?php echo $user['id']; ?>)">
                                                <i class="fas fa-pause me-1"></i> Suspender
                                            </button>
                                        <?php elseif ($user['status'] === 'suspended'): ?>
                                            <button class="btn btn-success btn-action" onclick="performAction('activate', <?php echo $user['id']; ?>)">
                                                <i class="fas fa-play me-1"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['status'] !== 'pending'): ?>
                                            <button class="btn btn-outline-danger btn-action" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash me-1"></i> Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Administrador
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmar Acción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;" id="actionForm">
                        <input type="hidden" id="actionType" name="action">
                        <input type="hidden" id="actionUserId" name="user_id">
                        <button type="submit" class="btn btn-danger" id="confirmButton">Confirmar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Inicializar DataTable
        $(document).ready(function() {
            $('#usersTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json"
                },
                "pageLength": 25,
                "order": [[4, "desc"]], // Ordenar por fecha de registro descendente
                "columnDefs": [
                    { "orderable": false, "targets": 5 } // No ordenar columna de acciones
                ]
            });
        });

        // Función para realizar acciones rápidas
        function performAction(action, userId) {
            const actions = {
                'approve': {
                    message: '¿Estás seguro de que quieres aprobar este usuario?',
                    button: 'Aprobar',
                    class: 'btn-success'
                },
                'reject': {
                    message: '¿Estás seguro de que quieres rechazar este usuario?',
                    button: 'Rechazar',
                    class: 'btn-danger'
                },
                'suspend': {
                    message: '¿Estás seguro de que quieres suspender este usuario?',
                    button: 'Suspender',
                    class: 'btn-warning'
                },
                'activate': {
                    message: '¿Estás seguro de que quieres activar este usuario?',
                    button: 'Activar',
                    class: 'btn-success'
                }
            };

            if (actions[action]) {
                document.getElementById('confirmMessage').textContent = actions[action].message;
                document.getElementById('actionType').value = action;
                document.getElementById('actionUserId').value = userId;
                
                const confirmButton = document.getElementById('confirmButton');
                confirmButton.textContent = actions[action].button;
                confirmButton.className = 'btn ' + actions[action].class;
                
                new bootstrap.Modal(document.getElementById('confirmModal')).show();
            }
        }

        // Función específica para confirmar eliminación
        function confirmDelete(userId, username) {
            document.getElementById('confirmMessage').innerHTML = 
                `¿Estás seguro de que quieres eliminar permanentemente al usuario <strong>${username}</strong>?<br><br>` +
                '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Esta acción no se puede deshacer.</span>';
            
            document.getElementById('actionType').value = 'delete';
            document.getElementById('actionUserId').value = userId;
            
            const confirmButton = document.getElementById('confirmButton');
            confirmButton.textContent = 'Eliminar';
            confirmButton.className = 'btn btn-danger';
            
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }

        // Auto-refresh para mostrar cambios en tiempo real
        setInterval(function() {
            // Solo hacer refresh si no hay modales abiertos
            if (!document.querySelector('.modal.show')) {
                const currentUrl = window.location.href;
                if (!currentUrl.includes('#')) {
                    // Verificar si hay cambios pendientes
                    fetch('check_pending_users.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.has_changes) {
                                // Mostrar notificación suave en lugar de recargar
                                showNotification('Hay cambios en los usuarios. Recarga la página para verlos.', 'info');
                            }
                        })
                        .catch(error => console.log('Check failed:', error));
                }
            }
        }, 30000); // Cada 30 segundos

        // Función para mostrar notificaciones
        function showNotification(message, type = 'info') {
            const alertClass = type === 'info' ? 'alert-info' : 'alert-warning';
            const icon = type === 'info' ? 'fa-info-circle' : 'fa-exclamation-triangle';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
            notification.innerHTML = `
                <i class="fas ${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Destacar usuarios recién registrados (últimas 24 horas)
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#usersTable tbody tr');
            const now = new Date();
            const oneDayAgo = new Date(now.getTime() - 24 * 60 * 60 * 1000);
            
            rows.forEach(row => {
                const dateCell = row.cells[4].textContent.trim();
                if (dateCell) {
                    const parts = dateCell.split(' ');
                    const datePart = parts[0].split('/');
                    const timePart = parts[1].split(':');
                    
                    const rowDate = new Date(
                        parseInt('20' + datePart[2]), // Año
                        parseInt(datePart[1]) - 1,    // Mes (0-based)
                        parseInt(datePart[0]),        // Día
                        parseInt(timePart[0]),        // Hora
                        parseInt(timePart[1])         // Minuto
                    );
                    
                    if (rowDate > oneDayAgo) {
                        row.classList.add('table-info');
                        row.title = 'Usuario registrado en las últimas 24 horas';
                    }
                }
            });
        });
    </script>
</body>
</html>