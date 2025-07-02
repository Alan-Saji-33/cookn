<?php
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $location = $conn->real_escape_string($_POST['location']);
    
    // Handle password change if provided
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            $update_stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, location = ?, password = ? WHERE id = ?");
            $update_stmt->bind_param("ssssi", $email, $phone, $location, $new_password, $user_id);
        } else {
            $_SESSION['error'] = "Current password is incorrect.";
            header("Location: profile.php");
            exit();
        }
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, location = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $email, $phone, $location, $user_id);
    }
    
    if ($update_stmt->execute()) {
        $_SESSION['email'] = $email;
        $_SESSION['phone'] = $phone;
        $_SESSION['message'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating profile. Please try again.";
    }
    $update_stmt->close();
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user's cars if seller
$user_cars = [];
if ($user['user_type'] == 'seller') {
    $cars_stmt = $conn->prepare("SELECT * FROM cars WHERE seller_id = ? ORDER BY created_at DESC");
    $cars_stmt->bind_param("i", $user_id);
    $cars_stmt->execute();
    $cars_result = $cars_stmt->get_result();
    $user_cars = $cars_result->fetch_all(MYSQLI_ASSOC);
    $cars_stmt->close();
}

// Get favorite cars
$favorites_stmt = $conn->prepare("SELECT c.* FROM favorites f JOIN cars c ON f.car_id = c.id WHERE f.user_id = ?");
$favorites_stmt->bind_param("i", $user_id);
$favorites_stmt->execute();
$favorites_result = $favorites_stmt->get_result();
$favorite_cars = $favorites_result->fetch_all(MYSQLI_ASSOC);
$favorites_stmt->close();
?>

<div class="profile-container">
    <div class="profile-sidebar">
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&size=150" alt="Profile" class="profile-avatar">
        <h3 class="profile-name"><?php echo htmlspecialchars($user['username']); ?></h3>
        <div class="profile-role">
            <?php 
            if ($user['user_type'] == 'admin') {
                echo '<span style="color: var(--danger)">Admin</span>';
            } elseif ($user['user_type'] == 'seller') {
                if ($user['is_verified']) {
                    echo '<span style="color: var(--success)">Verified Seller</span>';
                } else {
                    echo '<span style="color: var(--warning)">Unverified Seller</span>';
                }
            } else {
                echo 'Buyer';
            }
            ?>
        </div>
        
        <ul class="profile-menu">
            <li><a href="#profile" class="active"><i class="fas fa-user"></i> Profile</a></li>
            <?php if ($user['user_type'] == 'seller'): ?>
                <li><a href="#my-cars"><i class="fas fa-car"></i> My Cars</a></li>
            <?php endif; ?>
            <li><a href="#favorites"><i class="fas fa-heart"></i> Favorites</a></li>
        </ul>
    </div>
    
    <div class="profile-content">
        <!-- Profile Section -->
        <div id="profile" class="profile-section active">
            <h3 class="profile-section-title">Profile Information</h3>
            
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
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($user['location']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="current_password">Current Password (for password change)</label>
                    <input type="password" id="current_password" name="current_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
            
            <?php if ($user['user_type'] == 'seller' && !$user['is_verified']): ?>
                <div style="margin-top: 30px; padding: 20px; background-color: #fff3cd; border-radius: 8px;">
                    <h4 style="margin-bottom: 10px; color: #856404;">Seller Verification Required</h4>
                    <p style="margin-bottom: 15px;">To list cars for sale, you need to verify your seller account by submitting a government-issued ID.</p>
                    <a href="verify_seller.php" class="btn btn-primary">
                        <i class="fas fa-id-card"></i> Verify Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- My Cars Section (for sellers) -->
        <?php if ($user['user_type'] == 'seller'): ?>
            <div id="my-cars" class="profile-section">
                <h3 class="profile-section-title">My Listed Cars</h3>
                
                <?php if (empty($user_cars)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-car" style="font-size: 60px; color: var(--light-gray); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--gray);">You haven't listed any cars yet</h3>
                        <p>Start selling by listing your first car</p>
                        <a href="add_car.php" class="btn btn-primary" style="margin-top: 20px;" id="add-car-btn" 
                           data-verified="<?php echo ($user['is_verified'] == 1) ? 'true' : 'false'; ?>">
                            <i class="fas fa-plus"></i> Add Car
                        </a>
                    </div>
                <?php else: ?>
                    <div class="cars-grid">
                        <?php foreach ($user_cars as $car): ?>
                            <div class="car-card">
                                <?php if ($car['is_sold']): ?>
                                    <div class="sold-badge">SOLD</div>
                                <?php else: ?>
                                    <div class="car-badge">LISTED</div>
                                <?php endif; ?>
                                
                                <div class="car-image">
                                    <img src="<?php echo htmlspecialchars($car['image_path']); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                                </div>
                                
                                <div class="car-details">
                                    <h3 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                                    <div class="car-price">₹<?php echo number_format($car['price']); ?></div>
                                    
                                    <div class="car-specs">
                                        <span class="car-spec"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($car['year']); ?></span>
                                        <span class="car-spec"><i class="fas fa-tachometer-alt"></i> <?php echo number_format($car['km_driven']); ?> km</span>
                                        <span class="car-spec"><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($car['fuel_type']); ?></span>
                                    </div>
                                    
                                    <div class="car-actions">
                                        <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_car.php?id=<?php echo $car['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Favorites Section -->
        <div id="favorites" class="profile-section">
            <h3 class="profile-section-title">My Favorite Cars</h3>
            
            <?php if (empty($favorite_cars)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-heart" style="font-size: 60px; color: var(--light-gray); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--gray);">You haven't added any cars to favorites yet</h3>
                    <p>Browse cars and add them to your favorites list</p>
                    <a href="index.php#cars" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-car"></i> Browse Cars
                    </a>
                </div>
            <?php else: ?>
                <div class="cars-grid">
                    <?php foreach ($favorite_cars as $car): ?>
                        <div class="car-card">
                            <?php if ($car['is_sold']): ?>
                                <div class="sold-badge">SOLD</div>
                            <?php else: ?>
                                <div class="car-badge">AVAILABLE</div>
                            <?php endif; ?>
                            
                            <div class="car-image">
                                <img src="<?php echo htmlspecialchars($car['image_path']); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                            </div>
                            
                            <div class="car-details">
                                <h3 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                                <div class="car-price">₹<?php echo number_format($car['price']); ?></div>
                                
                                <div class="car-specs">
                                    <span class="car-spec"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($car['year']); ?></span>
                                    <span class="car-spec"><i class="fas fa-tachometer-alt"></i> <?php echo number_format($car['km_driven']); ?> km</span>
                                    <span class="car-spec"><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($car['fuel_type']); ?></span>
                                </div>
                                
                                <div class="car-actions">
                                    <button class="favorite-btn active" onclick="toggleFavorite(this, <?php echo $car['id']; ?>)">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                    <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
