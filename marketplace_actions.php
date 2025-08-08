<?php
// marketplace_actions.php - VERSION MEJORADA
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            try {
                // Obtener productos activos del marketplace
                $query = "SELECT mp.*, u.username as created_by_name 
                          FROM marketplace_products mp
                          LEFT JOIN users u ON mp.created_by = u.id
                          WHERE mp.is_active = 1
                          ORDER BY mp.featured DESC, mp.sales_count DESC, mp.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Agregar información adicional de video para cada producto
                foreach ($products as &$product) {
                    if (!empty($product['video_filename'])) {
                        $video_path = 'uploads/videos/' . $product['video_filename'];
                        $product['has_video'] = file_exists($video_path);
                        $product['video_url'] = $product['has_video'] ? $video_path : null;
                    } else {
                        $product['has_video'] = false;
                        $product['video_url'] = null;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'products' => $products,
                    'total_count' => count($products)
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_product':
            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                exit;
            }
            
            try {
                $query = "SELECT mp.*, u.username as created_by_name 
                          FROM marketplace_products mp
                          LEFT JOIN users u ON mp.created_by = u.id
                          WHERE mp.id = :id AND mp.is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    // Verificar si el video existe
                    if (!empty($product['video_filename'])) {
                        $video_path = 'uploads/videos/' . $product['video_filename'];
                        $product['has_video'] = file_exists($video_path);
                        $product['video_url'] = $product['has_video'] ? $video_path : null;
                    } else {
                        $product['has_video'] = false;
                        $product['video_url'] = null;
                    }
                    
                    echo json_encode(['success' => true, 'product' => $product]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'my_purchases':
            try {
                // Obtener compras del usuario
                $query = "SELECT up.*, mp.title, mp.description, mp.video_filename, mp.category, mp.price
                          FROM user_purchases up
                          INNER JOIN marketplace_products mp ON up.product_id = mp.id
                          WHERE up.user_id = :user_id
                          ORDER BY up.purchased_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Agregar información de video para cada compra
                foreach ($purchases as &$purchase) {
                    if (!empty($purchase['video_filename'])) {
                        $video_path = 'uploads/videos/' . $purchase['video_filename'];
                        $purchase['has_video'] = file_exists($video_path);
                        $purchase['video_url'] = $purchase['has_video'] ? $video_path : null;
                    } else {
                        $purchase['has_video'] = false;
                        $purchase['video_url'] = null;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'purchases' => $purchases
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'buy':
                $product_id = $input['product_id'];
                
                if (empty($product_id)) {
                    echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                    exit;
                }
                
                // Verificar que el producto existe y está activo
                $query = "SELECT * FROM marketplace_products WHERE id = :id AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $product_id);
                $stmt->execute();
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no disponible']);
                    exit;
                }
                
                // Verificar si ya compró el producto
                $query = "SELECT id FROM user_purchases WHERE user_id = :user_id AND product_id = :product_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':product_id', $product_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Ya has adquirido este producto']);
                    exit;
                }
                
                // Crear la compra
                $query = "INSERT INTO user_purchases (user_id, product_id, purchase_price, status, purchased_at) 
                          VALUES (:user_id, :product_id, :price, 'purchased', NOW())";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':product_id', $product_id);
                $stmt->bindParam(':price', $product['price']);
                
                if ($stmt->execute()) {
                    // Incrementar contador de ventas
                    $update_query = "UPDATE marketplace_products SET sales_count = sales_count + 1 WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':id', $product_id);
                    $update_stmt->execute();
                    
                    // Registrar actividad si la tabla existe
                    try {
                        $activity_query = "INSERT INTO system_activities (user_id, activity_type, resource_type, resource_id, description, created_at) 
                                           VALUES (:user_id, 'product_purchased', 'marketplace_product', :resource_id, :description, NOW())";
                        $activity_stmt = $db->prepare($activity_query);
                        $activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
                        $activity_stmt->bindParam(':resource_id', $product_id);
                        $description = "Producto adquirido: {$product['title']}";
                        $activity_stmt->bindParam(':description', $description);
                        $activity_stmt->execute();
                    } catch (Exception $activity_error) {
                        // Si falla el log de actividad, no afectar la operación principal
                        error_log("Error logging purchase activity: " . $activity_error->getMessage());
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Producto adquirido exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al procesar la compra']);
                }
                break;
                
            case 'activate':
                $purchase_id = $input['purchase_id'];
                $automation_id = trim($input['automation_id']);
                
                if (empty($purchase_id) || empty($automation_id)) {
                    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                    exit;
                }
                
                // Verificar que la compra pertenece al usuario y está en estado 'purchased'
                $query = "SELECT up.*, mp.title FROM user_purchases up 
                          INNER JOIN marketplace_products mp ON up.product_id = mp.id 
                          WHERE up.id = :id AND up.user_id = :user_id AND up.status = 'purchased'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $purchase_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$purchase) {
                    echo json_encode(['success' => false, 'message' => 'Compra no encontrada o ya activada']);
                    exit;
                }
                
                // Verificar que el automation_id no esté ya en uso
                $query = "SELECT id FROM user_purchases WHERE automation_id = :automation_id AND id != :purchase_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':automation_id', $automation_id);
                $stmt->bindParam(':purchase_id', $purchase_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Este ID de automatización ya está en uso']);
                    exit;
                }
                
                // También verificar en la tabla automations
                $query = "SELECT id FROM automations WHERE automation_id = :automation_id AND user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':automation_id', $automation_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Este ID ya está registrado en tus automatizaciones']);
                    exit;
                }
                
                // Actualizar la compra con el automation_id
                $query = "UPDATE user_purchases SET automation_id = :automation_id, status = 'activated', activated_at = NOW() 
                          WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':automation_id', $automation_id);
                $stmt->bindParam(':id', $purchase_id);
                
                if ($stmt->execute()) {
                    // Crear la entrada en la tabla automations
                    $query = "INSERT INTO automations (name, automation_id, user_id, is_active, purchase_id, created_at, updated_at) 
                              VALUES (:name, :automation_id, :user_id, 0, :purchase_id, NOW(), NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $purchase['title']);
                    $stmt->bindParam(':automation_id', $automation_id);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->bindParam(':purchase_id', $purchase_id);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Automatización activada exitosamente']);
                    } else {
                        // Si falla la creación de la automatización, revertir el estado de la compra
                        $revert_query = "UPDATE user_purchases SET automation_id = NULL, status = 'purchased', activated_at = NULL 
                                         WHERE id = :id";
                        $revert_stmt = $db->prepare($revert_query);
                        $revert_stmt->bindParam(':id', $purchase_id);
                        $revert_stmt->execute();
                        
                        echo json_encode(['success' => false, 'message' => 'Error al crear la automatización']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al activar la compra']);
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
?>