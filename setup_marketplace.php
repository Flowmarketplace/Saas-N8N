<?php
// setup_marketplace.php - Ejecutar una vez para configurar el marketplace
require_once 'config/database.php';

echo "<h2>🚀 Configuración del Marketplace</h2>";

// 1. Crear directorios necesarios
echo "<h3>1. Creando directorios</h3>";

$directories = [
    'uploads',
    'uploads/videos',
    'uploads/images',
    'uploads/temp'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Directorio creado: $dir<br>";
        } else {
            echo "❌ Error creando directorio: $dir<br>";
        }
    } else {
        echo "✅ Directorio ya existe: $dir<br>";
    }
}

// 2. Crear archivo .htaccess para uploads
echo "<h3>2. Configurando .htaccess</h3>";

$htaccess_content = '
# Permitir acceso a videos y imágenes
<Files ~ "\.(mp4|webm|avi|mov|jpg|jpeg|png|gif)$">
    Order allow,deny
    Allow from all
</Files>

# Configurar headers para videos
<FilesMatch "\.(mp4|webm|avi|mov)$">
    Header set Cache-Control "public, max-age=86400"
    Header set Accept-Ranges "bytes"
</FilesMatch>

# Denegar acceso a otros archivos
<Files ~ "\.(php|txt|log)$">
    Order deny,allow
    Deny from all
</Files>
';

$htaccess_path = 'uploads/.htaccess';
if (file_put_contents($htaccess_path, $htaccess_content)) {
    echo "✅ Archivo .htaccess creado en uploads/<br>";
} else {
    echo "❌ Error creando .htaccess<br>";
}

// 3. Verificar tablas de base de datos
echo "<h3>3. Verificando tablas de base de datos</h3>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar tabla marketplace_products
    $query = "SHOW TABLES LIKE 'marketplace_products'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla marketplace_products existe<br>";
        
        // Verificar si tiene la columna video_filename
        $query = "SHOW COLUMNS FROM marketplace_products LIKE 'video_filename'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "✅ Columna video_filename existe<br>";
        } else {
            echo "❌ Columna video_filename NO existe<br>";
            echo "<em>Ejecuta el script SQL para agregar la columna</em><br>";
        }
    } else {
        echo "❌ Tabla marketplace_products NO existe<br>";
        echo "<em>Ejecuta el script SQL completo</em><br>";
    }
    
    // Verificar tabla user_purchases
    $query = "SHOW TABLES LIKE 'user_purchases'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla user_purchases existe<br>";
    } else {
        echo "❌ Tabla user_purchases NO existe<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "<br>";
}

// 4. Crear productos de ejemplo (opcional)
echo "<h3>4. ¿Crear productos de ejemplo?</h3>";
echo '<form method="POST" style="margin: 20px 0;">';
echo '<button type="submit" name="create_samples" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Crear productos de ejemplo</button>';
echo '</form>';

if (isset($_POST['create_samples'])) {
    try {
        // Productos de ejemplo
        $sample_products = [
            [
                'title' => 'Bot de Atención al Cliente WhatsApp',
                'description' => 'Automatización inteligente que responde automáticamente a consultas frecuentes de clientes en WhatsApp. Incluye respuestas personalizadas, derivación a humanos y seguimiento de conversaciones.',
                'price' => 49.99,
                'category' => 'customer_service',
                'tags' => '["whatsapp", "chatbot", "customer_service", "ai"]',
                'featured' => 1,
                'is_active' => 1
            ],
            [
                'title' => 'Generador de Leads Automático',
                'description' => 'Sistema que captura leads desde múltiples fuentes (formularios web, redes sociales) y los organiza automáticamente en tu CRM con notificaciones inmediatas.',
                'price' => 79.99,
                'category' => 'sales',
                'tags' => '["leads", "crm", "automation", "sales"]',
                'featured' => 1,
                'is_active' => 1
            ],
            [
                'title' => 'Recordatorios de Seguimiento',
                'description' => 'Automatización que programa y envía recordatorios automáticos para seguimiento de clientes potenciales, citas y tareas importantes.',
                'price' => 0,
                'category' => 'general',
                'tags' => '["reminders", "follow_up", "productivity"]',
                'featured' => 0,
                'is_active' => 1
            ]
        ];
        
        foreach ($sample_products as $product) {
            $query = "INSERT INTO marketplace_products (title, description, price, category, tags, featured, is_active, created_by, created_at, sales_count) 
                      VALUES (:title, :description, :price, :category, :tags, :featured, :is_active, 1, NOW(), :sales_count)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $product['title']);
            $stmt->bindParam(':description', $product['description']);
            $stmt->bindParam(':price', $product['price']);
            $stmt->bindParam(':category', $product['category']);
            $stmt->bindParam(':tags', $product['tags']);
            $stmt->bindParam(':featured', $product['featured']);
            $stmt->bindParam(':is_active', $product['is_active']);
            $stmt->bindParam(':sales_count', rand(5, 25));
            
            if ($stmt->execute()) {
                echo "✅ Producto creado: " . $product['title'] . "<br>";
            } else {
                echo "❌ Error creando producto: " . $product['title'] . "<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error creando productos: " . $e->getMessage() . "<br>";
    }
}

// 5. Verificar permisos
echo "<h3>5. Verificando permisos</h3>";

$upload_dir = 'uploads/videos';
if (is_writable($upload_dir)) {
    echo "✅ Directorio uploads/videos tiene permisos de escritura<br>";
} else {
    echo "❌ Directorio uploads/videos NO tiene permisos de escritura<br>";
    echo "<em>Ejecuta: chmod 755 uploads/videos</em><br>";
}

// 6. Script SQL para crear/actualizar tablas
echo "<h3>6. Script SQL necesario</h3>";
echo "<p>Si alguna tabla no existe, ejecuta este SQL:</p>";
echo "<textarea readonly style='width: 100%; height: 200px; font-family: monospace; background: #f8f9fa; padding: 10px;'>";
echo "
-- Tabla marketplace_products
CREATE TABLE IF NOT EXISTS marketplace_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    category VARCHAR(100) DEFAULT 'general',
    tags JSON,
    video_filename VARCHAR(255),
    featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sales_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Tabla user_purchases
CREATE TABLE IF NOT EXISTS user_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    automation_id VARCHAR(255),
    status ENUM('purchased', 'activated', 'inactive') DEFAULT 'purchased',
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES marketplace_products(id) ON DELETE CASCADE
);

-- Agregar columna video_filename si no existe
ALTER TABLE marketplace_products 
ADD COLUMN IF NOT EXISTS video_filename VARCHAR(255) AFTER tags;
";
echo "</textarea>";

echo "<hr>";
echo "<h3>🎉 Configuración completada</h3>";
echo "<p>Tu marketplace está listo para usar!</p>";
echo "<a href='dashboard.php' style='color: #007bff; text-decoration: none;'>← Ir al Dashboard</a>";
echo " | ";
echo "<a href='marketplace_admin.php' style='color: #007bff; text-decoration: none;'>Administrar Marketplace →</a>";
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
</style>