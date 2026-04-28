<?php
session_start();
require_once 'conn.php';

$is_logged_in = isset($_SESSION['user_id']);
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: products.php");
    exit();
}

$review_success = '';
$review_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review']) && $is_logged_in) {
    $rating = intval($_POST['rating'] ?? 0);
    $review_title = trim($_POST['review_title'] ?? '');
    $review_desc = trim($_POST['review_desc'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if ($rating < 1 || $rating > 5) {
        $review_error = "Please select a rating between 1 and 5 stars.";
    } elseif (empty($review_title)) {
        $review_error = "Please enter a review title.";
    } elseif (strlen($review_title) < 5) {
        $review_error = "Review title must be at least 5 characters long.";
    } elseif (empty($review_desc)) {
        $review_error = "Please enter your review description.";
    } elseif (strlen($review_desc) < 10) {
        $review_error = "Review description must be at least 10 characters long.";
    } else {
        $sql = "INSERT INTO tbl_reviews (user_id, product_id, review_title, review_desc, review_rating, review_timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissi", $user_id, $product_id, $review_title, $review_desc, $rating);
        
        if ($stmt->execute()) {
            $review_success = "Thank you for your review!";
            $_POST = array();
        } else {
            $review_error = "Error submitting review.";
        }
        $stmt->close();
    }
}

$sql = "SELECT * FROM tbl_products WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

$reviews = [];
$sql = "SELECT r.*, u.user_name 
        FROM tbl_reviews r
        JOIN tbl_users u ON r.user_id = u.user_id
        WHERE r.product_id = ?
        ORDER BY r.review_timestamp DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

$avg_rating = 0;
$total_reviews = count($reviews);

if ($total_reviews > 0) {
    $rating_sum = 0;
    foreach ($reviews as $review) {
        $rating_sum += intval($review['review_rating']);
    }
    $avg_rating = round($rating_sum / $total_reviews, 1);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title><?php echo htmlspecialchars($product['product_title']); ?> - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="item-container">
        <div class="product-details">
            <img class="product-image" 
                 src="<?php echo htmlspecialchars($product['product_src'] ?? 'images/placeholder.jpg'); ?>" 
                 alt="<?php echo htmlspecialchars($product['product_title']); ?>">
            
            <div class="product-info">
                <h1><?php echo htmlspecialchars($product['product_title']); ?></h1>
                <div class="product-price">£<?php echo number_format($product['product_price'], 2); ?></div>
                
                <div class="rating">
                    <span>★ <?php echo $avg_rating; ?></span>
                    <span>(<?php echo $total_reviews; ?> reviews)</span>
                </div>
                
                <?php
                if ($product['product_stock'] == 'out-of-stock') {
                    echo '<div class="stock-out">Out of Stock</div>';
                    $in_stock = false;
                } elseif ($product['product_stock'] == 'low-stock') {
                    echo '<div class="stock-low">Low Stock - Order Soon!</div>';
                    $in_stock = true;
                } else {
                    echo '<div class="stock-good">In Stock</div>';
                    $in_stock = true;
                }
                ?>
                
                <div class="description">
                    <h3>Product Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['product_desc'] ?? 'No description available.')); ?></p>
                </div>
                
                <div class="actions">
                    <a href="products.php" class="back-btn">← Back to Products</a>
                    
                    <?php if ($in_stock): ?>
                        <?php if ($is_logged_in): ?>
                            <form method="POST" action="cart-actions.php?action=add" style="display: inline;">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="add-btn">Add to Cart</button>
                            </form>
                        <?php else: ?>
                            <a href="login.php" class="add-btn">Login to Add to Cart</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="add-btn" disabled style="background:#999; cursor:not-allowed;">Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="reviews-section">
            <h3>Customer Reviews</h3>
            
            <?php if ($review_success): ?>
                <div class="success"><?php echo htmlspecialchars($review_success); ?></div>
            <?php endif; ?>
            
            <?php if ($review_error): ?>
                <div class="error"><?php echo htmlspecialchars($review_error); ?></div>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <div class="write-review">
                    <h4>Write a Review</h4>
                    <form method="POST">
                        <select name="rating" required>
                            <option value="">Select Rating</option>
                            <option value="5">★★★★★ (5)</option>
                            <option value="4">★★★★☆ (4)</option>
                            <option value="3">★★★☆☆ (3)</option>
                            <option value="2">★★☆☆☆ (2)</option>
                            <option value="1">★☆☆☆☆ (1)</option>
                        </select>
                        <input type="text" name="review_title" placeholder="Review Title" required>
                        <textarea name="review_desc" placeholder="Share your experience with this product..." rows="4" required></textarea>
                        <button type="submit" name="submit_review">Submit Review</button>
                    </form>
                </div>
            <?php else: ?>
                <p>Please <a href="login.php">login</a> to write a review.</p>
            <?php endif; ?>
            
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review">
                        <div class="review-header">
                            <strong><?php echo htmlspecialchars($review['review_title']); ?></strong>
                            <span>★ <?php echo $review['review_rating']; ?></span>
                        </div>
                        <div class="review-meta">
                            By <?php echo htmlspecialchars($review['user_name']); ?> | 
                            <?php echo date('F j, Y', strtotime($review['review_timestamp'])); ?>
                        </div>
                        <div class="review-text">
                            <?php echo nl2br(htmlspecialchars($review['review_desc'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No reviews yet. Be the first to write a review!</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
