<?php
require_once 'header.php';

// Check if user is logged in and is a seller or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] != 'seller' && $_SESSION['user_type'] != 'admin')) {
    header("Location: login.php");
    exit();
}

// Check if seller is verified (admin doesn't need verification)
if ($_SESSION['user_type'] == 'seller' && !$_SESSION['is_verified']) {
    $_SESSION['error'] = "You need to verify your seller account before adding cars.";
    header("Location: profile.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_car'])) {
    $seller_id = $_SESSION['user_id'];
    $model = $conn->real_escape_string($_POST['model']);
    $brand = $conn->real_escape_string($_POST['brand']);
    $year = $conn->real_escape_string($_POST['year']);
    $price = $conn->real_escape_string($_POST['price']);
    $km_driven = $conn->real_escape_string($_POST['km_driven']);
    $fuel_type = $conn->real_escape_string($_POST['fuel_type']);
    $transmission = $conn->real_escape_string($_POST['transmission']);
    $description = $conn->real_escape_string($_POST['description']);
    $ownership_details = $conn->real_escape_string($_POST['ownership_details']);
    $insurance_details = $conn->real_escape_string($_POST['insurance_details']);
    
    // Handle file upload
    $target_dir = "Uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error'] = "File is not an image.";
        header("Location: add_car.php");
        exit();
    }
    
    // Check file size (5MB max)
    if ($_FILES["image"]["size"] > 5000000) {
        $_SESSION['error'] = "Sorry, your file is too large (max 5MB).";
        header("Location: add_car.php");
        exit();
    }
    
    // Allow certain file formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowed_types)) {
        $_SESSION['error'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        header("Location: add_car.php");
        exit();
    }
    
    // Try to upload file
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Insert car into database
        $stmt = $conn->prepare("INSERT INTO cars (seller_id, model, brand, year, price, km_driven, fuel_type, transmission, image_path, description, ownership_details, insurance_details) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiisssssss", $seller_id, $model, $brand, $year, $price, $km_driven, $fuel_type, $transmission, $target_file, $description, $ownership_details, $insurance_details);
        
        if ($stmt->execute()) {
            $car_id = $stmt->insert_id;
            
            // Handle additional images
            if (!empty($_FILES['additional_images']['name'][0])) {
                foreach ($_FILES['additional_images']['tmp_name'] as $key => $tmp_name) {
                    $imageFileType = strtolower(pathinfo($_FILES['additional_images']['name'][$key], PATHINFO_EXTENSION));
                    $new_filename = uniqid() . '.' . $imageFileType;
                    $target_file = $target_dir . $new_filename;
                    
                    if (in_array($imageFileType, $allowed_types) && 
                        $_FILES['additional_images']['size'][$key] <= 5000000 &&
                        move_uploaded_file($tmp_name, $target_file)) {
                        
                        $img_stmt = $conn->prepare("INSERT INTO car_images (car_id, image_path) VALUES (?, ?)");
                        $img_stmt->bind_param("is", $car_id, $target_file);
                        $img_stmt->execute();
                        $img_stmt->close();
                    }
                }
            }
            
            $_SESSION['message'] = "Car added successfully!";
            header("Location: car_details.php?id=$car_id");
            exit();
        } else {
            $_SESSION['error'] = "Error adding car. Please try again.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Sorry, there was an error uploading your file.";
    }
}
?>

<div class="form-container">
    <div class="form-title">
        <h2>Add New Car</h2>
        <p>Fill in the details of your car to list it for sale</p>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="brand">Brand</label>
            <input type="text" id="brand" name="brand" class="form-control" placeholder="e.g. Toyota" required>
        </div>
        
        <div class="form-group">
            <label for="model">Model</label>
            <input type="text" id="model" name="model" class="form-control" placeholder="e.g. Corolla" required>
        </div>
        
        <div class="form-group">
            <label for="year">Year</label>
            <input type="number" id="year" name="year" class="form-control" min="1900" max="<?php echo date('Y'); ?>" placeholder="e.g. 2020" required>
        </div>
        
        <div class="form-group">
            <label for="price">Price (₹)</label>
            <input type="number" id="price" name="price" class="form-control" min="0" step="1" placeholder="e.g. 500000" required>
        </div>
        
        <div class="form-group">
            <label for="km_driven">Kilometers Driven</label>
            <input type="number" id="km_driven" name="km_driven" class="form-control" min="0" placeholder="e.g. 25000" required>
        </div>
        
        <div class="form-group">
            <label for="fuel_type">Fuel Type</label>
            <select id="fuel_type" name="fuel_type" class="form-control" required>
                <option value="">Select Fuel Type</option>
                <option value="Petrol">Petrol</option>
                <option value="Diesel">Diesel</option>
                <option value="Electric">Electric</option>
                <option value="Hybrid">Hybrid</option>
                <option value="CNG">CNG</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="transmission">Transmission</label>
            <select id="transmission" name="transmission" class="form-control" required>
                <option value="">Select Transmission</option>
                <option value="Automatic">Automatic</option>
                <option value="Manual">Manual</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="image">Main Car Image (required)</label>
            <input type="file" id="image" name="image" class="form-control" accept="image/*" required>
        </div>
        
        <div class="form-group">
            <label for="additional_images">Additional Car Images (optional)</label>
            <input type="file" id="additional_images" name="additional_images[]" class="form-control" accept="image/*" multiple>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" rows="4" placeholder="Add details about the car's condition, features, etc." required></textarea>
        </div>
        
        <div class="form-group">
            <label for="ownership_details">Ownership Details</label>
            <textarea id="ownership_details" name="ownership_details" class="form-control" rows="3" placeholder="Number of previous owners, purchase date, etc."></textarea>
        </div>
        
        <div class="form-group">
            <label for="insurance_details">Insurance Details</label>
            <textarea id="insurance_details" name="insurance_details" class="form-control" rows="3" placeholder="Insurance validity, type, etc."></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="add_car" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Car
            </button>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
