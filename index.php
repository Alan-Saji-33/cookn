<?php
require_once 'header.php';

// Initialize variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 10000000;
$fuel_type = isset($_GET['fuel_type']) ? $_GET['fuel_type'] : '';
$transmission = isset($_GET['transmission']) ? $_GET['transmission'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';

// Build SQL query for cars
$sql = "SELECT c.*, u.username as seller_name, u.phone as seller_phone, u.email as seller_email, u.location as seller_location 
        FROM cars c 
        JOIN users u ON c.seller_id = u.id 
        WHERE c.is_sold = FALSE";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (c.model LIKE ? OR c.brand LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if ($min_price > 0 || $max_price < 10000000) {
    $sql .= " AND c.price BETWEEN ? AND ?";
    $params[] = $min_price;
    $params[] = $max_price;
    $types .= 'ii';
}

if (!empty($fuel_type)) {
    $sql .= " AND c.fuel_type = ?";
    $params[] = $fuel_type;
    $types .= 's';
}

if (!empty($transmission)) {
    $sql .= " AND c.transmission = ?";
    $params[] = $transmission;
    $types .= 's';
}

if (!empty($location)) {
    $sql .= " AND u.location LIKE ?";
    $params[] = "%$location%";
    $types .= 's';
}

$sql .= " ORDER BY c.created_at DESC LIMIT 12";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cars_result = $stmt->get_result();

// Get favorite cars for logged in user
$favorites = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $favorites_stmt = $conn->prepare("SELECT car_id FROM favorites WHERE user_id = ?");
    $favorites_stmt->bind_param("i", $user_id);
    $favorites_stmt->execute();
    $favorites_result = $favorites_stmt->get_result();
    
    while ($row = $favorites_result->fetch_assoc()) {
        $favorites[] = $row['car_id'];
    }
    $favorites_stmt->close();
}
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Find Your Perfect Used Car</h1>
        <p>Buy and sell quality used cars from trusted sellers across India</p>
        <div class="hero-buttons">
            <a href="#cars" class="btn btn-primary">
                <i class="fas fa-car"></i> Browse Cars
            </a>
            <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] == 'seller' || $_SESSION['user_type'] == 'admin')): ?>
                <a href="add_car.php" class="btn btn-outline" id="add-car-btn" 
                   data-verified="<?php echo ($_SESSION['user_type'] == 'admin' || $_SESSION['is_verified'] == 1) ? 'true' : 'false'; ?>">
                    <i class="fas fa-plus"></i> Add Car
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Search Section -->
<div class="container">
    <div class="search-section" id="search">
        <div class="search-title">
            <h2>Find Your Dream Car</h2>
            <p>Search through our extensive inventory of quality used cars</p>
        </div>
        
        <form method="GET" class="search-form">
            <div class="form-group">
                <label for="search"><i class="fas fa-search"></i> Keywords</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Toyota, Honda, SUV..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group">
                <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                <input type="text" id="location" name="location" class="form-control" placeholder="City or State" value="<?php echo htmlspecialchars($location); ?>">
            </div>
            
            <div class="form-group">
                <label for="min_price"><i class="fas fa-rupee-sign"></i> Min Price</label>
                <input type="number" id="min_price" name="min_price" class="form-control" min="0" placeholder="₹10,000" value="<?php echo $min_price; ?>">
            </div>
            
            <div class="form-group">
                <label for="max_price"><i class="fas fa-rupee-sign"></i> Max Price</label>
                <input type="number" id="max_price" name="max_price" class="form-control" min="0" placeholder="₹50,00,000" value="<?php echo $max_price; ?>">
            </div>
            
            <div class="form-group">
                <label for="fuel_type"><i class="fas fa-gas-pump"></i> Fuel Type</label>
                <select id="fuel_type" name="fuel_type" class="form-control">
                    <option value="">Any Fuel Type</option>
                    <option value="Petrol" <?php echo $fuel_type == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                    <option value="Diesel" <?php echo $fuel_type == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                    <option value="Electric" <?php echo $fuel_type == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                    <option value="Hybrid" <?php echo $fuel_type == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                    <option value="CNG" <?php echo $fuel_type == 'CNG' ? 'selected' : ''; ?>>CNG</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="transmission"><i class="fas fa-cog"></i> Transmission</label>
                <select id="transmission" name="transmission" class="form-control">
                    <option value="">Any Transmission</option>
                    <option value="Automatic" <?php echo $transmission == 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                    <option value="Manual" <?php echo $transmission == 'Manual' ? 'selected' : ''; ?>>Manual</option>
                </select>
            </div>
            
            <div class="form-group form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search Cars
                </button>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-sync-alt"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Main Content -->
<div class="container">
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

    <!-- Cars Section -->
    <section id="cars">
        <div class="section-header">
            <h2 class="section-title">Available Cars</h2>
            <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] == 'seller' || $_SESSION['user_type'] == 'admin')): ?>
                <a href="add_car.php" class="btn btn-primary" id="add-car-btn" 
                   data-verified="<?php echo ($_SESSION['user_type'] == 'admin' || $_SESSION['is_verified'] == 1) ? 'true' : 'false'; ?>">
                    <i class="fas fa-plus"></i> Add New Car
                </a>
            <?php endif; ?>
        </div>
        
        <div class="cars-grid">
            <?php if ($cars_result->num_rows > 0): ?>
                <?php while ($car = $cars_result->fetch_assoc()): ?>
                    <div class="car-card">
                        <?php if ($car['is_sold']): ?>
                            <div class="sold-badge">SOLD</div>
                        <?php else: ?>
                            <div class="car-badge">NEW</div>
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
                                <span class="car-spec"><i class="fas fa-cog"></i> <?php echo htmlspecialchars($car['transmission']); ?></span>
                                <span class="car-spec"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($car['seller_location']); ?></span>
                            </div>
                            
                            <p class="car-description"><?php echo htmlspecialchars(substr($car['description'], 0, 100)); ?>...</p>
                            
                            <div class="car-actions">
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="favorite-btn <?php echo in_array($car['id'], $favorites) ? 'active' : ''; ?>" 
                                            onclick="toggleFavorite(this, <?php echo $car['id']; ?>)">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <i class="fas fa-car" style="font-size: 60px; color: var(--light-gray); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--gray);">No cars found matching your criteria</h3>
                    <p>Try adjusting your search filters or check back later for new listings</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-sync-alt"></i> Reset Search
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" style="margin: 100px 0;">
        <div class="section-header">
            <h2 class="section-title">About CarBazaar</h2>
        </div>
        
        <div style="background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <p style="margin-bottom: 20px;">CarBazaar is India's leading platform for buying and selling used cars. We connect genuine buyers with trusted sellers to make the car buying process simple, transparent and reliable.</p>
            
            <h3 style="margin-bottom: 15px;">Why Choose CarBazaar?</h3>
            <ul style="margin-left: 20px; margin-bottom: 20px;">
                <li>Verified sellers and authentic car listings</li>
                <li>Transparent pricing and detailed vehicle history</li>
                <li>Wide selection of cars across all price ranges</li>
                <li>Secure payment options and documentation support</li>
            </ul>
            
            <h3 style="margin-bottom: 15px;">Our Mission</h3>
            <p>To revolutionize the used car market in India by providing a platform that prioritizes trust, transparency and customer satisfaction.</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" style="margin: 100px 0;">
        <div class="section-header">
            <h2 class="section-title">Contact Us</h2>
        </div>
        
        <div style="background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h3 style="margin-bottom: 20px;">Get in Touch</h3>
                    <p style="margin-bottom: 20px;">Have questions or need assistance? Our team is here to help you.</p>
                    
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-map-marker-alt" style="color: var(--primary); margin-right: 10px;"></i>
                        <span>123 Street, Mumbai, India</span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-phone-alt" style="color: var(--primary); margin-right: 10px;"></i>
                        <span>+91 9876543210</span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-envelope" style="color: var(--primary); margin-right: 10px;"></i>
                        <span>info@carbazaar.com</span>
                    </div>
                </div>
                
                <div>
                    <h3 style="margin-bottom: 20px;">Send us a Message</h3>
                    <form>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Your Name" required>
                        </div>
                        
                        <div class="form-group">
                            <input type="email" class="form-control" placeholder="Your Email" required>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Subject">
                        </div>
                        
                        <div class="form-group">
                            <textarea class="form-control" rows="4" placeholder="Your Message" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Verification Modal -->
<div id="verification-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Seller Verification Required</h3>
            <button class="close-btn" onclick="closeModal('verification-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <p style="margin-bottom: 20px;">Before you can list cars for sale, we need to verify your identity as a seller. This helps us maintain a trusted marketplace for all users.</p>
            
            <p style="margin-bottom: 30px;">Please submit a copy of your Aadhaar card or other government-issued ID for verification. This process typically takes 1-2 business days.</p>
            
            <div style="display: flex; justify-content: center; gap: 15px;">
                <a href="verify_seller.php" class="btn btn-primary">
                    <i class="fas fa-id-card"></i> Verify Now
                </a>
                <button class="btn btn-outline" onclick="closeModal('verification-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
