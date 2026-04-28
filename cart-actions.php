<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['cart_message'] = "Please login first.";
    header("Location: login.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

function getCartItems() {
    if (isset($_COOKIE['cart_data']) && !empty($_COOKIE['cart_data'])) {
        $cart_items = json_decode($_COOKIE['cart_data'], true);
        return is_array($cart_items) ? $cart_items : [];
    }
    return [];
}

function saveCartItems($cart_items) {
    setcookie('cart_data', json_encode($cart_items), time() + (86400 * 30), "/", "", false, true);
}

function calculateCartTotal($cart_items, $conn) {
    $total = 0;
    if (!empty($cart_items)) {
        $ids = array_keys($cart_items);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT product_id, product_price FROM tbl_products WHERE product_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($product = $result->fetch_assoc()) {
            $id = $product['product_id'];
            $quantity = $cart_items[$id]['quantity'];
            $price = $product['product_price'];
            $total += $price * $quantity;
        }
        $stmt->close();
    }
    return $total;
}

$cart_items = getCartItems();

switch ($action) {
    case 'add':
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        if ($product_id <= 0) {
            $_SESSION['cart_message'] = "Invalid product.";
        } else {
            $sql = "SELECT product_id, product_title, product_price, product_stock FROM tbl_products WHERE product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                
                if ($product['product_stock'] == 'out-of-stock') {
                    $_SESSION['cart_message'] = "Sorry, '{$product['product_title']}' is out of stock.";
                } else {
                    if (isset($cart_items[$product_id])) {
                        $cart_items[$product_id]['quantity'] += $quantity;
                    } else {
                        $cart_items[$product_id] = [
                            'id' => $product['product_id'],
                            'name' => $product['product_title'],
                            'price' => $product['product_price'],
                            'quantity' => $quantity
                        ];
                    }
                    
                    saveCartItems($cart_items);
                    $_SESSION['cart_message'] = "{$product['product_title']} added to cart!";
                }
            } else {
                $_SESSION['cart_message'] = "Product not found.";
            }
            $stmt->close();
        }
        break;
    
    case 'remove':
        $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($product_id > 0 && isset($cart_items[$product_id])) {
            $product_name = $cart_items[$product_id]['name'];
            unset($cart_items[$product_id]);
            saveCartItems($cart_items);
            $_SESSION['cart_message'] = "$product_name removed from cart.";
        } else {
            $_SESSION['cart_message'] = "Item not found in cart.";
        }
        break;
    
    case 'empty':
        if (isset($_COOKIE['cart_data'])) {
            setcookie('cart_data', '', time() - 3600, "/");
        }
        $_SESSION['cart_message'] = "Cart has been emptied.";
        break;
    
    case 'update':
        if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
            foreach ($_POST['quantities'] as $product_id => $quantity) {
                $product_id = intval($product_id);
                $quantity = intval($quantity);
                
                if ($quantity > 0 && isset($cart_items[$product_id])) {
                    $cart_items[$product_id]['quantity'] = $quantity;
                } elseif ($quantity <= 0 && isset($cart_items[$product_id])) {
                    unset($cart_items[$product_id]);
                }
            }
            saveCartItems($cart_items);
            $_SESSION['cart_message'] = "Cart updated successfully.";
        }
        break;
    
    default:
        $_SESSION['cart_message'] = "Invalid action.";
        break;
}

$conn->close();

$redirect_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "cart.php";
header("Location: " . $redirect_to);
exit();
?>
