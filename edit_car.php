<?php
require_once 'header.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$car_id = $conn->real_escape_string($_GET['id']);

// Get car details
$car_stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
$car_stmt->bind_param("i", $car_id);
$car_stmt->execute();
$car_result = $car_stmt->get_result();

if ($car_result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$car = $car_result->fetch_assoc();

// Check if user is authorized to edit this car (seller or admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_id'] != $car['seller_id'] && $_SESSION['user_type'] != 'admin')) {
    header("Location: index.php");
    exit();
}

// Get car images
$images_stmt = $conn->prepare("SELECT id, image_path FROM car_images WHERE car_id = ?");
$images_stmt->bind_param("i", $car_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();
$car_images = $images_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_car'])) {
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
    
    // Handle main image update if provided
    $image_path = $car['image_path'];
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "Uploads/";
        $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false && 
            $_FILES["image"]["size"] <= 5000000 && 
            in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif']) && 
            move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            
            // Delete old image file
            if (file_exists($image_path)) {
                unlink($image_path);
            }
            
            $image_path = $target_file;
        }
    }
    
    // Update car in database
    $stmt = $conn->prepare("UPDATE cars SET model = ?, brand = ?, year = ?, price = ?, km_driven = ?, fuel_type = ?, transmission = ?, image_path = ?, description = ?, ownership_details = ?, insurance_details = ? WHERE id = ?");
    $stmt->bind_param("ssiisssssssi", $model, $brand, $year, $price, $km_driven, $fuel_type, $transmission, $image_path, $description, $ownership_details, $insurance_details, $car_id);
    
    if ($stmt->execute()) {
        // Handle additional images
        if (!empty($_FILES['additional_images']['name'][0])) {
            $target_dir = "Uploads/";
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
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
        
        $_SESSION['message'] = "Car updated successfully!";
        header("Location: car_details.php?id=$car_id");
        exit();
    } else {
        $_SESSION['error'] = "Error updating car. Please try again.";
    }
    $stmt->close();
}

// Handle image deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_image'])) {
    $image_id = $conn->real_escape_string($_POST['image_id']);
    
    // Get image path
    $img_stmt = $conn->prepare("SELECT image_path FROM car_images WHERE id = ?");
    $img_stmt->bind_param("i", $image_id);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result();
    $image = $img_result->fetch_assoc();
    
    if ($image && file_exists($image['image_path'])) {
        unlink($image['image_path']);
    }
    
    // Delete from database
    $del_stmt = $conn->prepare("DELETE FROM car_images WHERE id = ?");
    $del_stmt->bind_param("i", $image_id);
    $del_stmt->execute();
    $del_stmt->close();
    
    $_SESSION['message'] = "Image deleted successfully!";
    header("Location: edit_car.php?id=$car_id");
    exit();
}
?>

<div class="form-container">
    <div class="form-title">
        <h2>Edit Car Details</h2>
        <p>Update the information for your car listing</p>
    </div>
    
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
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="brand">Brand</label>
            <input type="text" id="brand" name="brand" class="form-control" value="<?php echo htmlspecialchars($car['brand']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="model">Model</label>
            <input type="text" id="model" name="model" class="form-control" value="<?php echo htmlspecialchars($car['model']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="year">Year</label>
            <input type="number" id="year" name="year" class="form-control" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($car['year']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="price">Price (₹)</label>
            <input type="number" id="price" name="price" class="form-control" min="0" step="1" value="<?php echo htmlspecialchars($car['price']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="km_driven">Kilometers Driven</label>
            <input type="number" id="km_driven" name="km_driven" class="form-control" min="0" value="<?php echo htmlspecialchars($car['km_driven']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="fuel_type">Fuel Type</label>
            <select id="fuel_type" name="fuel_type" class="form-control" required>
                <option value="Petrol" <?php echo $car['fuel_type'] == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                <option value="Diesel" <?php echo $car['fuel_type'] == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                <option value="Electric" <?php echo $car['fuel_type'] == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                <option value="Hybrid" <?php echo $car['fuel_type'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                <option value="CNG" <?php echo $car['fuel_type'] == 'CNG' ? 'selected' : ''; ?>>CNG</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="transmission">Transmission</label>
            <select id="transmission" name="transmission" class="form-control" required>
                <option value="Automatic" <?php echo $car['transmission'] == 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                <option value="Manual" <?php echo $car['transmission'] == 'Manual' ? 'selected' : ''; ?>>Manual</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Current Main Image</label>
            <img src="<?php echo htmlspecialchars($car['image_path']); ?>" style="max-width: 200px; display: block; margin-bottom: 10px;">
            <label for="image">Update Main Image (optional)</label>
            <input type="file" id="image" name="image" class="form-control" accept="image/*">
        </div>
        
        <div class="form-group">
            <label>Additional Images</label>
            <?php if (!empty($car_images)): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                    <?php foreach ($car_images as $image): ?>
                        <div style="position: relative;">
                            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" style="width: 100px; height: 100px; object-fit: cover;">
                            <form method="POST" style="position: absolute; top: 5px; right: 5px;">
                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                <button type="submit" name="delete_image" class="btn btn-danger" style="padding: 5px 8px; font-size: 12px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label for="additional_images">Add More Images (optional)</label>
            <input type="file" id="additional_images" name="additional_images[]" class="form-control" accept="image/*" multiple>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($car['description']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="ownership_details">Ownership Details</label>
            <textarea id="ownership_details" name="ownership_details" class="form-control" rows="3"><?php echo htmlspecialchars($car['ownership_details']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="insurance_details">Insurance Details</label>
            <textarea id="insurance_details" name="insurance_details" class="form-control" rows="3"><?php echo htmlspecialchars($car['insurance_details']); ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="update_car" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
