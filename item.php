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
            $review_success = "Thank you for your review! It has been submitted successfully.";
            $_POST = array();
        } else {
            $review_error = "Error submitting review. Please try again.";
        }
        $stmt->close();
    }
}

$sql = "SELECT * FROM products WHERE product_id = ?";
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

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}
$stmt->close();

$avg_rating = 0;
$rating_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$total_reviews = count($reviews);

if ($total_reviews > 0) {
    $rating_sum = 0;
    foreach ($reviews as $review) {
        $rating = intval($review['review_rating']);
        $rating_sum += $rating;
        $rating_counts[$rating]++;
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
                <h1 class="product-title"><?php echo htmlspecialchars($product['product_title']); ?></h1>
                <div class="product-price">£<?php echo number_format($product['product_price'], 2); ?></div>
                
                <div class="rating-summary">
                    <div class="stars-display">
                        <?php
                        $full_stars = floor($avg_rating);
                        $half_star = ($avg_rating - $full_stars) >= 0.5;
                        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                        
                        for ($i = 1; $i <= $full_stars; $i++) {
                            echo '<span class="star-filled">★</span>';
                        }
                        if ($half_star) {
                            echo '<span class="star-half">½</span>';
                        }
                        for ($i = 1; $i <= $empty_stars; $i++) {
                            echo '<span class="star-empty">☆</span>';
                        }
                        ?>
                    </div>
                    <span class="rating-number"><?php echo $avg_rating; ?> / 5</span>
                    <span class="review-count">(<?php echo $total_reviews; ?> <?php echo $total_reviews == 1 ? 'review' : 'reviews'; ?>)</span>
                </div>
                
                <?php
                $stock_class = '';
                $stock_text = '';
                $in_stock = true;
                
                switch($product['product_stock']) {
                    case 'good-stock':
                        $stock_class = 'stock-good';
                        $stock_text = 'In Stock';
                        break;
                    case 'low-stock':
                        $stock_class = 'stock-low';
                        $stock_text = 'Low Stock - Order Soon!';
                        break;
                    case 'out-of-stock':
                        $stock_class = 'stock-out';
                        $stock_text = 'Out of Stock';
                        $in_stock = false;
                        break;
                }
                ?>
                
                <div class="product-stock <?php echo $stock_class; ?>">
                    <?php echo $stock_text; ?>
                </div>
                
                <div class="product-description">
                    <h3>Product Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['product_desc'] ?? 'No description available.')); ?></p>
                </div>
                
                <div class="action-buttons">
                    <a href="products.php" class="back-btn">← Back to Products</a>
                    <?php if ($in_stock): ?>
                        <?php if ($is_logged_in): ?>
                            <form method="POST" action="cart.php" style="display: inline;">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <input type="hidden" name="action" value="add">
                                <button type="submit" name="add_to_cart" class="add-cart-btn">Add to Cart</button>
                            </form>
                        <?php else: ?>
                            <a href="index.php#login" class="add-cart-btn" style="text-decoration: none; text-align: center;">Login to Add to Cart</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="add-cart-btn" disabled>Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($total_reviews > 0): ?>
        <div class="rating-breakdown">
            <h3>Rating Breakdown</h3>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <?php $percentage = $total_reviews > 0 ? ($rating_counts[$i] / $total_reviews) * 100 : 0; ?>
                <div class="rating-bar-item">
                    <div class="rating-label"><?php echo $i; ?> ★</div>
                    <div class="rating-bar-container">
                        <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="rating-percent"><?php echo round($percentage); ?>%</div>
                </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <div class="reviews-section">
            <h3>Customer Reviews</h3>
            
            <?php if ($review_success): ?>
                <div class="success-message"><?php echo htmlspecialchars($review_success); ?></div>
            <?php endif; ?>
            
            <?php if ($review_error): ?>
                <div class="error-message"><?php echo htmlspecialchars($review_error); ?></div>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <div class="review-form">
                    <h4>Write a Review</h4>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Your Rating *</label>
                            <div class="review-stars">
                                <input type="radio" name="rating" value="5" id="star5" required>
                                <label for="star5">★</label>
                                <input type="radio" name="rating" value="4" id="star4">
                                <label for="star4">★</label>
                                <input type="radio" name="rating" value="3" id="star3">
                                <label for="star3">★</label>
                                <input type="radio" name="rating" value="2" id="star2">
                                <label for="star2">★</label>
                                <input type="radio" name="rating" value="1" id="star1">
                                <label for="star1">★</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="review_title">Review Title *</label>
                            <input type="text" id="review_title" name="review_title" required 
                                   placeholder="Summarize your experience (minimum 5 characters)">
                        </div>
                        
                        <div class="form-group">
                            <label for="review_desc">Your Review *</label>
                            <textarea id="review_desc" name="review_desc" required 
                                      placeholder="Share your experience with this product... (minimum 10 characters)"></textarea>
                        </div>
                        
                        <button type="submit" name="submit_review" class="submit-review-btn">Submit Review</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="login-prompt">
                    <p>Please <a href="index.php#login">login</a> to write a review.</p>
                    <p style="margin-top: 10px; font-size: 14px;">Only verified users can share their experiences.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-title"><?php echo htmlspecialchars($review['review_title']); ?></div>
                            <div class="review-stars-display">
                                <?php
                                $rating = intval($review['review_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '★';
                                    } else {
                                        echo '☆';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="review-meta">
                            By <?php echo htmlspecialchars($review['user_name']); ?> | 
                            <?php echo date('F j, Y', strtotime($review['review_timestamp'])); ?>
                        </div>
                        <div class="review-description">
                            <?php echo nl2br(htmlspecialchars($review['review_desc'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-reviews">
                    <p>No reviews yet. Be the first to write a review!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>