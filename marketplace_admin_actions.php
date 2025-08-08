<?php
// marketplace_admin_actions.php - VERSION CORREGIDA CON 2 VIDEOS
session_start();
require_once 'config/database.php';

// Agregar header para JSON
header('Content-Type: application/json; charset=UTF-8');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar si es admin
$query = "SELECT role FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            try {
                // Obtener todos los productos
                $query = "SELECT mp.*, u.username as created_by_name 
                          FROM marketplace_products mp
                          LEFT JOIN users u ON mp.created_by = u.id
                          ORDER BY mp.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Verificar si los videos existen
                foreach ($products as &$product) {
                    // Video promocional
                    if (!empty($product['promotional_video'])) {
                        $promo_path = 'uploads/videos/' . $product['promotional_video'];
                        $product['promo_video_exists'] = file_exists($promo_path);
                        $product['promo_video_size'] = $product['promo_video_exists'] ? filesize($promo_path) : 0;
                    } else {
                        $product['promo_video_exists'] = false;
                        $product['promo_video_size'] = 0;
                    }
                    
                    // Video demo
                    if (!empty($product['demo_video'])) {
                        $demo_path = 'uploads/videos/' . $product['demo_video'];
                        $product['demo_video_exists'] = file_exists($demo_path);
                        $product['demo_video_size'] = $product['demo_video_exists'] ? filesize($demo_path) : 0;
                    } else {
                        $product['demo_video_exists'] = false;
                        $product['demo_video_size'] = 0;
                    }
                    
                    // Compatibilidad con video_filename antiguo
                    $product['video_filename'] = $product['promotional_video'];
                    $product['video_exists'] = $product['promo_video_exists'];
                }
                
                // Obtener estadísticas
                $stats_query = "SELECT 
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN featured = 1 THEN 1 END) as featured_products,
                    SUM(sales_count) as total_sales,
                    COUNT(CASE WHEN promotional_video IS NOT NULL AND promotional_video != '' THEN 1 END) as products_with_video
                    FROM marketplace_products";
                $stats_stmt = $db->prepare($stats_query);
                $stats_stmt->execute();
                $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'products' => $products,
                    'stats' => $stats
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit;
            }
            
            try {
                $query = "SELECT * FROM marketplace_products WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    // Verificar video promocional
                    if (!empty($product['promotional_video'])) {
                        $promo_path = 'uploads/videos/' . $product['promotional_video'];
                        $product['promo_video_exists'] = file_exists($promo_path);
                        $product['promo_video_url'] = $product['promo_video_exists'] ? $promo_path : null;
                    } else {
                        $product['promo_video_exists'] = false;
                        $product['promo_video_url'] = null;
                    }
                    
                    // Verificar video demo
                    if (!empty($product['demo_video'])) {
                        $demo_path = 'uploads/videos/' . $product['demo_video'];
                        $product['demo_video_exists'] = file_exists($demo_path);
                        $product['demo_video_url'] = $product['demo_video_exists'] ? $demo_path : null;
                    } else {
                        $product['demo_video_exists'] = false;
                        $product['demo_video_url'] = null;
                    }
                    
                    echo json_encode(['success' => true, 'product' => $product]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $contentType = isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : $contentType;
    
    if (strpos($contentType, 'application/json') !== false) {
        // Peticiones JSON (toggle_status, delete)
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'toggle_status':
                $product_id = $input['product_id'] ?? '';
                $is_active = $input['is_active'] ?? false;
                
                if (empty($product_id)) {
                    echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                    exit;
                }
                
                try {
                    $query = "UPDATE marketplace_products SET is_active = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$is_active, $product_id])) {
                        echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                break;
                
            case 'delete':
                $product_id = $input['product_id'] ?? '';
                
                if (empty($product_id)) {
                    echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                    exit;
                }
                
                try {
                    // Obtener información de los videos antes de eliminar
                    $query = "SELECT promotional_video, demo_video FROM marketplace_products WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Eliminar el producto
                    $query = "DELETE FROM marketplace_products WHERE id = ?";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$product_id])) {
                        // Eliminar archivos de video si existen
                        if ($product) {
                            if (!empty($product['promotional_video'])) {
                                $promo_path = 'uploads/videos/' . $product['promotional_video'];
                                if (file_exists($promo_path)) {
                                    unlink($promo_path);
                                }
                            }
                            if (!empty($product['demo_video'])) {
                                $demo_path = 'uploads/videos/' . $product['demo_video'];
                                if (file_exists($demo_path)) {
                                    unlink($demo_path);
                                }
                            }
                        }
                        echo json_encode(['success' => true, 'message' => 'Producto eliminado']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                break;
        }
    } else {
        // Peticiones multipart/form-data (create, update con archivos)
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
            case 'update':
                try {
                    $title = trim($_POST['title'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $price = floatval($_POST['price'] ?? 0);
                    $category = trim($_POST['category'] ?? 'general');
                    $tools = trim($_POST['tools'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $featured = isset($_POST['featured']) ? 1 : 0;
                    
                    if (empty($title) || empty($description)) {
                        echo json_encode(['success' => false, 'message' => 'Título y descripción son obligatorios']);
                        exit;
                    }
                    
                    // Procesar tools
                    $tools_array = array_filter(array_map('trim', explode(',', $tools)));
                    $tools_json = json_encode($tools_array);
                    
                    // Manejar subida de videos
                    $promotional_video = null;
                    $demo_video = null;
                    $promo_uploaded = false;
                    $demo_uploaded = false;
                    
                    // Video promocional
                    if (isset($_FILES['promotional_video']) && $_FILES['promotional_video']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = handleVideoUpload($_FILES['promotional_video'], 'promo');
                        
                        if ($upload_result['success']) {
                            $promotional_video = $upload_result['filename'];
                            $promo_uploaded = true;
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error video promocional: ' . $upload_result['message']]);
                            exit;
                        }
                    }
                    
                    // Video demo
                    if (isset($_FILES['demo_video']) && $_FILES['demo_video']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = handleVideoUpload($_FILES['demo_video'], 'demo');
                        
                        if ($upload_result['success']) {
                            $demo_video = $upload_result['filename'];
                            $demo_uploaded = true;
                        } else {
                            // Si falla el demo, eliminar el promocional si se subió
                            if ($promo_uploaded && !empty($promotional_video)) {
                                unlink('uploads/videos/' . $promotional_video);
                            }
                            echo json_encode(['success' => false, 'message' => 'Error video demo: ' . $upload_result['message']]);
                            exit;
                        }
                    }
                    
                    if ($action === 'create') {
                        // CREAR PRODUCTO
                        $query = "INSERT INTO marketplace_products 
                                  (title, description, price, category, tools, promotional_video, demo_video, featured, is_active, created_by, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute([
                            $title, 
                            $description, 
                            $price, 
                            $category, 
                            $tools_json, 
                            $promotional_video,
                            $demo_video,
                            $featured, 
                            $is_active, 
                            $_SESSION['user_id']
                        ]);
                    } else {
                        // ACTUALIZAR PRODUCTO
                        $product_id = $_POST['product_id'] ?? '';
                        if (empty($product_id)) {
                            echo json_encode(['success' => false, 'message' => 'ID de producto requerido para actualizar']);
                            exit;
                        }
                        
                        // Obtener videos anteriores para eliminarlos si se suben nuevos
                        $old_promo_video = null;
                        $old_demo_video = null;
                        
                        if ($promo_uploaded || $demo_uploaded) {
                            $query = "SELECT promotional_video, demo_video FROM marketplace_products WHERE id = ?";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$product_id]);
                            $old_product = $stmt->fetch(PDO::FETCH_ASSOC);
                            $old_promo_video = $old_product['promotional_video'] ?? null;
                            $old_demo_video = $old_product['demo_video'] ?? null;
                        }
                        
                        // Construir query de actualización dinámicamente
                        $update_fields = [
                            'title = ?',
                            'description = ?',
                            'price = ?',
                            'category = ?',
                            'tools = ?',
                            'featured = ?',
                            'is_active = ?',
                            'updated_at = NOW()'
                        ];
                        
                        $update_params = [
                            $title,
                            $description,
                            $price,
                            $category,
                            $tools_json,
                            $featured,
                            $is_active
                        ];
                        
                        if ($promo_uploaded) {
                            $update_fields[] = 'promotional_video = ?';
                            $update_params[] = $promotional_video;
                        }
                        
                        if ($demo_uploaded) {
                            $update_fields[] = 'demo_video = ?';
                            $update_params[] = $demo_video;
                        }
                        
                        $update_params[] = $product_id;
                        
                        $query = "UPDATE marketplace_products SET " . implode(', ', $update_fields) . " WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute($update_params);
                        
                        // Si la actualización fue exitosa y hay nuevos videos, eliminar los anteriores
                        if ($result) {
                            if ($promo_uploaded && !empty($old_promo_video)) {
                                $old_promo_path = 'uploads/videos/' . $old_promo_video;
                                if (file_exists($old_promo_path)) {
                                    unlink($old_promo_path);
                                }
                            }
                            if ($demo_uploaded && !empty($old_demo_video)) {
                                $old_demo_path = 'uploads/videos/' . $old_demo_video;
                                if (file_exists($old_demo_path)) {
                                    unlink($old_demo_path);
                                }
                            }
                        }
                    }
                    
                    if ($result) {
                        echo json_encode([
                            'success' => true, 
                            'message' => $action === 'create' ? 'Producto creado exitosamente' : 'Producto actualizado exitosamente',
                            'promo_uploaded' => $promo_uploaded,
                            'demo_uploaded' => $demo_uploaded
                        ]);
                    } else {
                        // Si falla la operación de BD, eliminar los videos subidos
                        if ($promo_uploaded && !empty($promotional_video)) {
                            unlink('uploads/videos/' . $promotional_video);
                        }
                        if ($demo_uploaded && !empty($demo_video)) {
                            unlink('uploads/videos/' . $demo_video);
                        }
                        echo json_encode(['success' => false, 'message' => 'Error al guardar el producto']);
                    }
                    
                } catch (Exception $e) {
                    error_log('Error en marketplace_admin_actions: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                break;
        }
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

/**
 * Manejar la subida de video
 */
function handleVideoUpload($file, $type = 'video') {
    $upload_dir = 'uploads/videos/';
    
    // Crear directorio si no existe
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'No se pudo crear el directorio de uploads'];
        }
    }
    
    // Verificar si el directorio es escribible
    if (!is_writable($upload_dir)) {
        return ['success' => false, 'message' => 'El directorio de uploads no tiene permisos de escritura'];
    }
    
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension'] ?? '');
    $allowed_extensions = ['mp4', 'webm', 'avi', 'mov'];
    
    // Verificar extensión
    if (!in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Formato de video no permitido. Use: ' . implode(', ', $allowed_extensions)];
    }
    
    // Verificar tamaño (50MB max)
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'El video excede el tamaño máximo de 50MB'];
    }
    
    // Verificar tipo MIME
    $allowed_mimes = ['video/mp4', 'video/webm', 'video/x-msvideo', 'video/quicktime'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_mimes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no válido. Solo se permiten videos'];
    }
    
    // Generar nombre único
    $video_filename = $type . '_' . uniqid() . '_' . time() . '.' . $extension;
    $upload_path = $upload_dir . $video_filename;
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Verificar que el archivo se subió correctamente
        if (file_exists($upload_path) && filesize($upload_path) > 0) {
            return [
                'success' => true, 
                'filename' => $video_filename,
                'path' => $upload_path,
                'size' => filesize($upload_path)
            ];
        } else {
            return ['success' => false, 'message' => 'Error: el archivo no se guardó correctamente'];
        }
    } else {
        $error_msg = 'Error al subir el video. ';
        $upload_error = $_FILES['promotional_video']['error'] ?? $_FILES['demo_video']['error'] ?? UPLOAD_ERR_UNKNOWN;
        
        switch ($upload_error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg .= 'El archivo es demasiado grande.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_msg .= 'Directorio temporal no encontrado.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_msg .= 'Error al escribir el archivo.';
                break;
            default:
                $error_msg .= 'Verifica los permisos del servidor.';
        }
        
        return ['success' => false, 'message' => $error_msg];
    }
}
?>