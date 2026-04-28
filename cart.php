<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

$cart_items = [];
if (isset($_COOKIE['cart_data']) && !empty($_COOKIE['cart_data'])) {
    $cart_items = json_decode($_COOKIE['cart_data'], true);
    if (!is_array($cart_items)) {
        $cart_items = [];
    }
}

if (isset($_SESSION['cart']) && !empty($_SESSION['cart']) && empty($cart_items)) {
    $cart_items = $_SESSION['cart'];
    setcookie('cart_data', json_encode($cart_items), time() + (86400 * 30), "/", "", false, true);
    unset($_SESSION['cart']);
}

$offer_applied = null;
$discount_amount = 0;
$offer_percentage = 0;
$offer_type = '';

if (isset($_POST['apply_offer']) && isset($_POST['offer_code'])) {
    $offer_code = trim($_POST['offer_code']);
    
    $sql = "SELECT * FROM tbl_offers WHERE offer_desc LIKE ?";
    $search = "%" . $offer_code . "%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($offer = $result->fetch_assoc()) {
        $_SESSION['applied_offer'] = $offer;
        
        if (strpos($offer['offer_desc'], 'GRAD25') !== false) {
            $message = "Offer '{$offer['offer_title']}' applied! 25% off your entire order!";
            $offer_percentage = 25;
            $offer_type = 'global';
        } elseif (strpos($offer['offer_desc'], 'half-price') !== false) {
            $message = "Offer '{$offer['offer_title']}' applied! 50% off Cyan, Magenta, and Yellow t-shirts only!";
            $offer_percentage = 50;
            $offer_type = 'color_specific';
        } else {
            $message = "Offer '{$offer['offer_title']}' applied!";
        }
        $message_type = 'success';
    } else {
        $message = "Invalid offer code. Try GRAD25 for 25% off or HALFPRICE for 50% off selected items.";
        $message_type = 'error';
        unset($_SESSION['applied_offer']);
    }
    $stmt->close();
}

if (isset($_GET['remove_offer'])) {
    unset($_SESSION['applied_offer']);
    header("Location: cart.php");
    exit();
}

if (isset($_SESSION['applied_offer'])) {
    $offer_applied = $_SESSION['applied_offer'];
    if (strpos($offer_applied['offer_desc'], 'GRAD25') !== false) {
        $offer_percentage = 25;
        $offer_type = 'global';
    } elseif (strpos($offer_applied['offer_desc'], 'half-price') !== false) {
        $offer_percentage = 50;
        $offer_type = 'color_specific';
    }
}

$subtotal = 0;
$discounted_subtotal = 0;
$cart_items_with_details = [];

$color_discount_items = ['Cyan', 'Magenta', 'Yellow'];

if (!empty($cart_items)) {
    $ids = array_keys($cart_items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT product_id, product_title, product_price, product_stock FROM tbl_products WHERE product_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($product = $result->fetch_assoc()) {
        $id = $product['product_id'];
        $quantity = $cart_items[$id]['quantity'];
        $price = $product['product_price'];
        $title = $product['product_title'];
        $subtotal += $price * $quantity;
        
        $eligible_for_discount = false;
        if ($offer_type == 'color_specific') {
            foreach ($color_discount_items as $color_item) {
                if (stripos($title, $color_item) !== false) {
                    $eligible_for_discount = true;
                    break;
                }
            }
        }
        
        $item_subtotal = $price * $quantity;
        $item_discount = 0;
        
        if ($offer_type == 'global') {
            $item_discount = $item_subtotal * ($offer_percentage / 100);
        } elseif ($offer_type == 'color_specific' && $eligible_for_discount) {
            $item_discount = $item_subtotal * ($offer_percentage / 100);
        }
        
        $discounted_subtotal += ($item_subtotal - $item_discount);
        $discount_amount += $item_discount;
        
        $cart_items_with_details[$id] = [
            'id' => $id,
            'name' => htmlspecialchars($title),
            'price' => $price,
            'quantity' => $quantity,
            'stock' => $product['product_stock'],
            'subtotal' => $item_subtotal,
            'discount' => $item_discount,
            'final_price' => $item_subtotal - $item_discount,
            'eligible' => $eligible_for_discount
        ];
    }
    $stmt->close();
}

$final_total = $discounted_subtotal;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_cart'])) {
    if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $pid => $qty) {
            $pid = intval($pid);
            $qty = intval($qty);
            if ($qty > 0 && isset($cart_items[$pid])) {
                $cart_items[$pid]['quantity'] = $qty;
            } elseif ($qty <= 0) {
                unset($cart_items[$pid]);
            }
        }
        setcookie('cart_data', json_encode($cart_items), time() + (86400 * 30), "/", "", false, true);
        $message = "Cart updated.";
        $message_type = 'success';
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    if (empty($cart_items_with_details)) {
        $message = "Your cart is empty. Please add items before checking out.";
        $message_type = 'error';
    } else {
        $stock_error = false;
        foreach ($cart_items_with_details as $item) {
            if ($item['stock'] == 'out-of-stock') {
                $stock_error = true;
                $message = "Sorry, '" . $item['name'] . "' is out of stock and cannot be purchased.";
                $message_type = 'error';
                break;
            }
        }
        
        if (!$stock_error) {
            $product_ids = [];
            foreach ($cart_items_with_details as $item) {
                $product_ids[] = $item['id'] . ':' . $item['quantity'];
            }
            $product_ids_str = implode(',', $product_ids);
            
            $sql = "INSERT INTO tbl_orders (user_id, product_ids, order_timestamp) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $product_ids_str);
            
            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;
                
                setcookie('cart_data', '', time() - 3600, "/");
                if (isset($_COOKIE['cart_data'])) {
                    unset($_COOKIE['cart_data']);
                }
                unset($_SESSION['applied_offer']);
                $_SESSION['cart_message'] = "Order #" . $order_id . " placed successfully!";
                $_SESSION['last_order'] = $order_id;
                
                header("Location: order-confirmation.php");
                exit();
            } else {
                $message = "Error processing your order: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['checkout_success']) && isset($_SESSION['checkout_success'])) {
    $message = $_SESSION['checkout_success'];
    $message_type = 'success';
    unset($_SESSION['checkout_success']);
}

if (isset($_SESSION['cart_message'])) {
    $message = $_SESSION['cart_message'];
    $message_type = 'info';
    unset($_SESSION['cart_message']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="style.css">
    <title>Your Cart - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="cart-container">
        <h1 class="cart-title">Your Shopping Cart</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($cart_items_with_details)): ?>
            <div class="cart-empty">
                <p>Your cart is empty</p>
                <a href="products.php">Continue Shopping →</a>
            </div>
        <?php else: ?>
            <form method="POST" action="cart.php">
                <div class="cart-table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items_with_details as $id => $item): ?>
                                <tr>
                                    <td class="product-name">
                                        <?php echo $item['name']; ?>
                                        <?php if ($item['eligible'] && $offer_percentage == 50 && $offer_type == 'color_specific'): ?>
                                            <div class="stock-warning" style="background:#d4edda; color:#155724;">🎉 50% OFF on this item!</div>
                                        <?php endif; ?>
                                        <?php if ($item['stock'] == 'low-stock'): ?>
                                            <div class="stock-warning">⚠ Low Stock</div>
                                        <?php endif; ?>
                                        <?php if ($item['stock'] == 'out-of-stock'): ?>
                                            <div class="stock-warning" style="background:#f8d7da; color:#721c24;">✗ Out of Stock - Cannot checkout</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>£<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <input type="number" name="quantities[<?php echo $id; ?>]" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" class="quantity-input"
                                               <?php echo ($item['stock'] == 'out-of-stock') ? 'disabled' : ''; ?>>
                                    </td>
                                    <td>
                                        <?php if ($item['discount'] > 0): ?>
                                            <span style="text-decoration: line-through; color: #999;">£<?php echo number_format($item['subtotal'], 2); ?></span><br>
                                            <span style="color: #28a745;">£<?php echo number_format($item['final_price'], 2); ?></span>
                                        <?php else: ?>
                                            £<?php echo number_format($item['subtotal'], 2); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="cart-actions.php?action=remove&id=<?php echo $id; ?>" class="remove-link" onclick="return confirm('Remove this item from your cart?')">✗ Remove</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="cart-summary">
                    <div class="offer-section">
                        <h3>Apply Offer Code</h3>
                        <div class="offer-form">
                            <input type="text" name="offer_code" placeholder="Enter GRAD25 or HALFPRICE" class="offer-input">
                            <button type="submit" name="apply_offer" class="apply-offer-btn">Apply Code</button>
                        </div>
                        
                        <?php if ($offer_applied): ?>
                            <div class="applied-offer">
                                Offer Applied: <?php echo htmlspecialchars($offer_applied['offer_title']); ?>
                                <?php if ($offer_percentage > 0): ?>
                                    - <?php echo $offer_percentage; ?>% off
                                    <?php if ($offer_type == 'color_specific'): ?>
                                        (Cyan, Magenta, Yellow only)
                                    <?php else: ?>
                                        (entire order)
                                    <?php endif; ?>
                                    (£<?php echo number_format($discount_amount, 2); ?> saved)
                                <?php endif; ?>
                                <a href="?remove_offer=1" class="remove-offer-btn" onclick="return confirm('Remove this offer?')">[Remove]</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>£<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <?php if ($discount_amount > 0): ?>
                        <div class="summary-row discount-row">
                            <span>Discount (<?php echo $offer_percentage; ?>%):</span>
                            <span>-£<?php echo number_format($discount_amount, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>£<?php echo number_format($final_total, 2); ?></span>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="products.php" class="continue-btn">← Continue Shopping</a>
                        <button type="submit" name="update_cart" class="update-btn">Update Cart</button>
                        <a href="cart-actions.php?action=empty" class="update-btn" style="background-color: #dc3545;" 
                           onclick="return confirm('Empty your entire cart? This cannot be undone.')">Empty Cart</a>
                        <button type="submit" name="checkout" class="checkout-btn" 
                                onclick="return confirm('Proceed to checkout? Total: £<?php echo number_format($final_total, 2); ?>')">
                            Proceed to Checkout →
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
