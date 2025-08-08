<?php
echo "<h2>🔍 Diagnóstico de Login</h2>";

// Test 1: Conexión a la base de datos
echo "<h3>1. Test de conexión a la base de datos</h3>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✅ <strong>Conexión exitosa</strong><br>";
        echo "Host: localhost<br>";
        echo "Database: crmn8n_automation_platform<br>";
    } else {
        echo "❌ <strong>Error de conexión</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Test 2: Verificar si existe la tabla users
echo "<h3>2. Verificar tabla users</h3>";
try {
    $query = "SHOW TABLES LIKE 'users'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong>Tabla 'users' existe</strong><br>";
        
        // Mostrar estructura de la tabla
        $query = "DESCRIBE users";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<strong>Estructura de la tabla:</strong><br>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ <strong>Tabla 'users' NO existe</strong><br>";
        echo "<em>Necesitas ejecutar el script SQL para crear las tablas</em><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Test 3: Verificar usuarios existentes
echo "<h3>3. Usuarios en la base de datos</h3>";
try {
    $query = "SELECT id, username, email, created_at FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "✅ <strong>Usuarios encontrados:</strong><br>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Usuario</th><th>Email</th><th>Fecha creación</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['username'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ <strong>No hay usuarios</strong><br>";
        echo "<em>Necesitas crear el usuario admin</em><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Test 4: Verificar password del usuario admin
echo "<h3>4. Verificar contraseña del usuario admin</h3>";
try {
    $query = "SELECT username, password FROM users WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ <strong>Usuario admin encontrado</strong><br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Password hash: " . substr($user['password'], 0, 20) . "...<br>";
        
        // Test de verificación de contraseña
        $test_password = 'admin123';
        if (password_verify($test_password, $user['password'])) {
            echo "✅ <strong>La contraseña 'admin123' es CORRECTA</strong><br>";
        } else {
            echo "❌ <strong>La contraseña 'admin123' NO coincide</strong><br>";
            
            // Generar nueva contraseña
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "<br><strong>Hash correcto para 'admin123':</strong><br>";
            echo "<code style='background: #f8f9fa; padding: 5px; font-size: 12px; word-break: break-all;'>" . $new_hash . "</code><br>";
            
            echo "<br><strong>🔧 Para solucionarlo, ejecuta esta consulta SQL:</strong><br>";
            echo "<code style='background: #f8f9fa; padding: 10px; display: block; margin: 10px 0;'>";
            echo "UPDATE users SET password = '" . $new_hash . "' WHERE username = 'admin';";
            echo "</code>";
        }
    } else {
        echo "❌ <strong>Usuario admin NO encontrado</strong><br>";
        
        // Generar insert para crear usuario
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        echo "<br><strong>🔧 Para crear el usuario admin, ejecuta esta consulta SQL:</strong><br>";
        echo "<code style='background: #f8f9fa; padding: 10px; display: block; margin: 10px 0;'>";
        echo "INSERT INTO users (username, password, email) VALUES ('admin', '" . $new_hash . "', 'admin@example.com');";
        echo "</code>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Test 5: Simulación de login
echo "<h3>5. Simulación de proceso de login</h3>";
try {
    $username = 'admin';
    $password = 'admin123';
    
    echo "Intentando login con:<br>";
    echo "- Usuario: '$username'<br>";
    echo "- Contraseña: '$password'<br><br>";
    
    $query = "SELECT id, username, password FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    echo "Consulta SQL: <code>$query</code><br>";
    echo "Parámetro username: '$username'<br>";
    echo "Filas encontradas: " . $stmt->rowCount() . "<br><br>";
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Usuario encontrado:<br>";
        echo "- ID: " . $user['id'] . "<br>";
        echo "- Username: " . $user['username'] . "<br>";
        
        if (password_verify($password, $user['password'])) {
            echo "✅ <strong>LOGIN EXITOSO</strong><br>";
        } else {
            echo "❌ <strong>CONTRASEÑA INCORRECTA</strong><br>";
        }
    } else {
        echo "❌ <strong>USUARIO NO ENCONTRADO</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error en simulación:</strong> " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>📋 Resumen y próximos pasos</h3>";
echo "<p>Después de revisar los resultados arriba:</p>";
echo "<ul>";
echo "<li>Si hay errores de conexión → revisa config/database.php</li>";
echo "<li>Si no existe la tabla → ejecuta el script SQL</li>";
echo "<li>Si no hay usuarios → ejecuta la consulta INSERT mostrada arriba</li>";
echo "<li>Si la contraseña no coincide → ejecuta la consulta UPDATE mostrada arriba</li>";
echo "</ul>";
echo "<br><a href='index.php' style='color: #007bff;'>← Volver al login</a>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}
h2, h3 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}
table {
    background: white;
    margin: 10px 0;
}
th {
    background: #007bff;
    color: white;
}
code {
    background: #f8f9fa;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: monospace;
}
</style>