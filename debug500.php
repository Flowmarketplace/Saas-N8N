<?php
// debug_500.php - Archivo de diagnóstico para error 500
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico Error 500</h2>";

// Test 1: Verificar versión de PHP
echo "<h3>1. Versión de PHP</h3>";
echo "PHP Version: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.0.0', '<')) {
    echo "❌ <strong>PHP muy antiguo. Se requiere PHP 7.0 o superior</strong><br>";
} else {
    echo "✅ <strong>Versión de PHP correcta</strong><br>";
}

// Test 2: Verificar extensiones necesarias
echo "<h3>2. Extensiones PHP necesarias</h3>";
$required_extensions = ['pdo', 'pdo_mysql', 'session', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extensión '$ext' cargada<br>";
    } else {
        echo "❌ <strong>Extensión '$ext' NO está cargada</strong><br>";
    }
}

// Test 3: Verificar rutas de archivos
echo "<h3>3. Verificación de rutas</h3>";
echo "Directorio actual: " . getcwd() . "<br>";
echo "Ruta del script: " . __FILE__ . "<br>";

// Verificar si existe la carpeta config
if (file_exists('config')) {
    echo "✅ Carpeta 'config' existe<br>";
    
    // Verificar si existe database.php
    if (file_exists('config/database.php')) {
        echo "✅ Archivo 'config/database.php' existe<br>";
    } else {
        echo "❌ <strong>Archivo 'config/database.php' NO existe</strong><br>";
    }
} else {
    echo "❌ <strong>Carpeta 'config' NO existe</strong><br>";
}

// Test 4: Verificar permisos
echo "<h3>4. Permisos de archivos</h3>";
if (is_readable('config/database.php')) {
    echo "✅ 'config/database.php' es legible<br>";
} else {
    echo "❌ <strong>'config/database.php' NO es legible</strong><br>";
}

// Test 5: Intentar cargar database.php
echo "<h3>5. Intentar cargar configuración de base de datos</h3>";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        echo "✅ Archivo cargado correctamente<br>";
        
        // Intentar crear conexión
        $database = new Database();
        $db = $database->getConnection();
        if ($db) {
            echo "✅ Conexión a base de datos exitosa<br>";
        }
    } else {
        echo "❌ No se puede cargar el archivo - no existe<br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error al cargar:</strong> " . $e->getMessage() . "<br>";
}

// Test 6: Verificar configuración del servidor
echo "<h3>6. Configuración del servidor</h3>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";

// Test 7: Verificar archivos principales
echo "<h3>7. Archivos principales del sistema</h3>";
$main_files = ['index.php', 'dashboard.php', 'login.php', 'register.php'];
foreach ($main_files as $file) {
    if (file_exists($file)) {
        echo "✅ '$file' existe<br>";
    } else {
        echo "⚠️ '$file' no encontrado<br>";
    }
}

// Test 8: Verificar carpeta uploads (para videos del marketplace)
echo "<h3>8. Verificar carpeta uploads</h3>";
if (file_exists('uploads')) {
    echo "✅ Carpeta 'uploads' existe<br>";
    if (is_writable('uploads')) {
        echo "✅ Carpeta 'uploads' tiene permisos de escritura<br>";
    } else {
        echo "❌ <strong>Carpeta 'uploads' NO tiene permisos de escritura</strong><br>";
    }
    
    if (file_exists('uploads/videos')) {
        echo "✅ Carpeta 'uploads/videos' existe<br>";
    } else {
        echo "⚠️ Carpeta 'uploads/videos' no existe - créala si usas el marketplace<br>";
    }
} else {
    echo "⚠️ Carpeta 'uploads' no existe - créala si usas el marketplace<br>";
}

// Test 9: Verificar .htaccess
echo "<h3>9. Verificar .htaccess</h3>";
if (file_exists('.htaccess')) {
    echo "✅ Archivo '.htaccess' existe<br>";
    echo "<strong>Contenido:</strong><br>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars(file_get_contents('.htaccess'));
    echo "</pre>";
} else {
    echo "⚠️ Archivo '.htaccess' no existe<br>";
}

echo "<hr>";
echo "<h3>📋 Resumen y soluciones</h3>";
echo "<ul>";
echo "<li>Si falta alguna extensión PHP → contacta a tu proveedor de hosting</li>";
echo "<li>Si faltan archivos → verifica que se subieron todos correctamente</li>";
echo "<li>Si hay problemas de permisos → cambia permisos a 755 para carpetas y 644 para archivos</li>";
echo "<li>Si la estructura es diferente → ajusta las rutas en los require_once</li>";
echo "</ul>";
?>