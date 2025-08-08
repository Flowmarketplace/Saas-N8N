<?php
require_once 'config/database.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $company = trim($_POST['company']);
    
    // Validaciones
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Todos los campos obligatorios deben completarse';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Verificar si el usuario o email ya existen
        $query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'El usuario o email ya están registrados';
        } else {
            // Crear nuevo usuario pendiente de aprobación
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, email, password, full_name, company, role, status, created_at) 
                      VALUES (:username, :email, :password, :full_name, :company, 'user', 'pending', NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':company', $company);
            
            if ($stmt->execute()) {
                $message = 'Registro exitoso. Tu cuenta está pendiente de aprobación por un administrador.';
                
                // Limpiar formulario
                $username = $email = $full_name = $company = '';
            } else {
                $error = 'Error al registrar el usuario';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Plataforma de Automatizaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #f1f1f1;
            padding: 12px 15px;
            font-size: 14px;
            margin-bottom: 1rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .input-group {
            margin-bottom: 1rem;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #f1f1f1;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .text-link {
            color: #667eea;
            text-decoration: none;
        }
        .text-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2 class="mb-0">
                <i class="fas fa-user-plus me-2"></i>
                Crear Cuenta
            </h2>
            <p class="mb-0 mt-2 opacity-75">Regístrate para acceder a la plataforma</p>
        </div>
        
        <div class="register-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <label for="username" class="form-label">Usuario <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Usuario" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <label for="full_name" class="form-label">Nombre Completo <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-id-card"></i>
                    </span>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           placeholder="Nombre completo" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
                </div>

                <label for="company" class="form-label">Empresa/Organización</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-building"></i>
                    </span>
                    <input type="text" class="form-control" id="company" name="company" 
                           placeholder="Empresa (opcional)" value="<?php echo htmlspecialchars($company ?? ''); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Contraseña <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Contraseña" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirmar <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirmar contraseña" required>
                        </div>
                    </div>
                </div>

                <small class="text-muted d-block mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    La contraseña debe tener al menos 6 caracteres
                </small>
                
                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>
                    Crear Cuenta
                </button>
            </form>
            
            <div class="text-center mt-4">
                <p class="mb-0">
                    ¿Ya tienes cuenta? 
                    <a href="index.php" class="text-link">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        Iniciar Sesión
                    </a>
                </p>
            </div>

            <div class="text-center mt-3">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    Tu cuenta será revisada y aprobada por un administrador
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación en tiempo real
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                this.setCustomValidity('Las contraseñas no coinciden');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });

        // Validación de longitud de contraseña
        document.getElementById('password').addEventListener('input', function() {
            if (this.value.length < 6 && this.value.length > 0) {
                this.setCustomValidity('La contraseña debe tener al menos 6 caracteres');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>