<?php
require_once 'header.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$car_id = $conn->real_escape_string($_GET['id']);

// Get car details
$car_stmt = $conn->prepare("SELECT c.*, u.username as seller_name, u.phone as seller_phone, u.email as seller_email, u.location as seller_location 
                           FROM cars c 
                           JOIN users u ON c.seller_id = u.id 
                           WHERE c.id = ?");
$car_stmt->bind_param("i", $car_id);
$car_stmt->execute();
$car_result = $car_stmt->get_result();

if ($car_result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$car = $car_result->fetch_assoc();

// Get car images (assuming multiple images are stored in a separate table)
$images_stmt = $conn->prepare("SELECT image_path FROM car_images WHERE car_id = ?");
$images_stmt->bind_param("i", $car_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();
$car_images = $images_result->fetch_all(MYSQLI_ASSOC);

// If no additional images, use the main image
if (empty($car_images)) {
    $car_images = [['image_path' => $car['image_path']];
}

// Check if car is in favorites
$is_favorite = false;
if (isset($_SESSION['user_id'])) {
    $fav_stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND car_id = ?");
    $fav_stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
    $fav_stmt->execute();
    $fav_result = $fav_stmt->get_result();
    $is_favorite = $fav_result->num_rows > 0;
    $fav_stmt->close();
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $message = $conn->real_escape_string($_POST['message']);
    
    $insert_stmt = $conn->prepare("INSERT INTO messages (car_id, sender_id, receiver_id, message, sender_name, sender_email, sender_phone) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
    $sender_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $insert_stmt->bind_param("iiissss", $car_id, $sender_id, $car['seller_id'], $message, $name, $email, $phone);
    
    if ($insert_stmt->execute()) {
        $_SESSION['message'] = "Your message has been sent to the seller!";
    } else {
        $_SESSION['error'] = "Failed to send message. Please try again.";
    }
    $insert_stmt->close();
}

// Mark as sold (for seller/admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_sold']) && 
    (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $car['seller_id'] || $_SESSION['user_type'] == 'admin'))) {
    $update_stmt = $conn->prepare("UPDATE cars SET is_sold = TRUE WHERE id = ?");
    $update_stmt->bind_param("i", $car_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['message'] = "Car marked as sold!";
        header("Location: car_details.php?id=$car_id");
        exit();
    }
    $update_stmt->close();
}
?>

<div class="car-details-container">
    <div class="car-gallery">
        <div class="car-main-image">
            <img src="<?php echo htmlspecialchars($car_images[0]['image_path']); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
        </div>
        
        <?php foreach ($car_images as $image): ?>
            <div class="car-thumbnail">
                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="car-info">
        <h1 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h1>
        <div class="car-price">₹<?php echo number_format($car['price']); ?></div>
        
        <div class="car-specs">
            <div class="car-spec">
                <div class="car-spec-label">Brand:</div>
                <div class="car-spec-value"><?php echo htmlspecialchars($car['brand']); ?></div>
            </div>
            <div class="car-spec">
                <div class="car-spec-label">Model:</div>
                <div class="car-spec-value"><?php echo htmlspecialchars($car['model']); ?></div>
            </div>
            <div class="car-spec">
                <div class="car-spec-label">Year:</div>
                <div class="car-spec-value"><?php echo htmlspecialchars($car['year']); ?></div>
            </div>
            <div class="car-spec">
                <div class="car-spec-label">Kilometers:</div>
                <div class="car-spec-value"><?php echo number_format($car['km_driven']); ?> km</div>
            </div>
            <div class="car-spec">
                <div class="car-spec-label">Fuel Type:</div>
                <div class="car-spec-value"><?php echo htmlspecialchars($car['fuel_type']); ?></div>
            </div>
            <div class="car-spec">
                <div class="car-spec-label">Transmission:</div>
                <div class="car-spec-value"><?php echo htmlspecialchars($car['transmission']); ?></div>
            </div>
            <div class="car-spec">
                <div class="car-spec-label">Location:</div>
                <div class="car-spec-value"><?php echo htmlspecialchars($car['seller_location']); ?></div>
            </div>
            <div class="car-spec">
                <div class="car-spec-label">Status:</div>
                <div class="car-spec-value"><?php echo $car['is_sold'] ? '<span style="color:var(--danger)">Sold</span>' : '<span style="color:var(--success)">Available</span>'; ?></div>
            </div>
        </div>
        
        <div class="car-description">
            <h4>Description</h4>
            <p><?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
        </div>
        
        <div class="car-description">
            <h4>Ownership Details</h4>
            <p><?php echo nl2br(htmlspecialchars($car['ownership_details'] ?? 'Not specified')); ?></p>
        </div>
        
        <div class="car-description">
            <h4>Insurance</h4>
            <p><?php echo nl2br(htmlspecialchars($car['insurance_details'] ?? 'Not specified')); ?></p>
        </div>
        
        <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $car['seller_id'] || $_SESSION['user_type'] == 'admin')): ?>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                <h4>Seller Actions</h4>
                <div style="display: flex; gap: 15px; margin-top: 15px;">
                    <a href="edit_car.php?id=<?php echo $car['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Details
                    </a>
                    
                    <?php if (!$car['is_sold']): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="mark_sold" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Mark as Sold
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container" style="margin-top: 50px;">
    <div class="seller-info">
        <h3>Contact Seller</h3>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div>
                <h4>Seller Information</h4>
                <div class="car-spec">
                    <div class="car-spec-label">Name:</div>
                    <div class="car-spec-value"><?php echo htmlspecialchars($car['seller_name']); ?></div>
                </div>
                
                <?php if (!empty($car['seller_phone'])): ?>
                    <div class="car-spec">
                        <div class="car-spec-label">Phone:</div>
                        <div class="car-spec-value"><?php echo htmlspecialchars($car['seller_phone']); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($car['seller_email'])): ?>
                    <div class="car-spec">
                        <div class="car-spec-label">Email:</div>
                        <div class="car-spec-value"><?php echo htmlspecialchars($car['seller_email']); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="car-spec">
                    <div class="car-spec-label">Location:</div>
                    <div class="car-spec-value"><?php echo htmlspecialchars($car['seller_location']); ?></div>
                </div>
            </div>
            
            <div>
                <h4>Send Message</h4>
                <form method="POST">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="form-group">
                            <label for="name">Your Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Your Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Your Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="4" required>Hi, I'm interested in your <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?> listed for ₹<?php echo number_format($car['price']); ?>. Please contact me with more details.</textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button type="button" class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" 
                                    onclick="toggleFavorite(this, <?php echo $car['id']; ?>)">
                                <i class="fas fa-heart"></i> <?php echo $is_favorite ? 'Remove Favorite' : 'Add to Favorites'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
