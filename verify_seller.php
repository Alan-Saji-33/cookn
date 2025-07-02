<?php
require_once 'header.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header("Location: login.php");
    exit();
}

// Check if seller is already verified
if ($_SESSION['is_verified']) {
    header("Location: profile.php");
    exit();
}

// Check if seller already has a pending verification
$check_stmt = $conn->prepare("SELECT * FROM seller_verifications WHERE user_id = ? AND status = 'pending'");
$check_stmt->bind_param("i", $_SESSION['user_id']);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $_SESSION['message'] = "Your verification request is already pending. Please wait for admin approval.";
    header("Location: profile.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_verification'])) {
    $id_type = $conn->real_escape_string($_POST['id_type']);
    $id_number = $conn->real_escape_string($_POST['id_number']);
    
    // Handle file upload
    $target_dir = "Uploads/verifications/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $imageFileType = strtolower(pathinfo($_FILES["id_proof"]["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($_FILES["id_proof"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error'] = "File is not an image.";
        header("Location: verify_seller.php");
        exit();
    }
    
    // Check file size (5MB max)
    if ($_FILES["id_proof"]["size"] > 5000000) {
        $_SESSION['error'] = "Sorry, your file is too large (max 5MB).";
        header("Location: verify_seller.php");
        exit();
    }
    
    // Allow certain file formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowed_types)) {
        $_SESSION['error'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        header("Location: verify_seller.php");
        exit();
    }
    
    // Try to upload file
    if (move_uploaded_file($_FILES["id_proof"]["tmp_name"], $target_file)) {
        // Insert verification request
        $stmt = $conn->prepare("INSERT INTO seller_verifications (user_id, id_type, id_number, id_proof) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $_SESSION['user_id'], $id_type, $id_number, $target_file);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Verification request submitted successfully! We'll review your details and get back to you soon.";
            header("Location: profile.php");
            exit();
        } else {
            $_SESSION['error'] = "Error submitting verification. Please try again.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Sorry, there was an error uploading your file.";
    }
}
?>

<div class="verification-container">
    <div class="verification-title">
        <h2>Seller Verification</h2>
        <p>Please provide your identification details to verify your seller account</p>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="id_type">ID Type</label>
            <select id="id_type" name="id_type" class="form-control" required>
                <option value="">Select ID Type</option>
                <option value="Aadhaar">Aadhaar Card</option>
                <option value="PAN">PAN Card</option>
                <option value="Driving License">Driving License</option>
                <option value="Passport">Passport</option>
                <option value="Voter ID">Voter ID</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="id_number">ID Number</label>
            <input type="text" id="id_number" name="id_number" class="form-control" placeholder="Enter your ID number" required>
        </div>
        
        <div class="form-group">
            <label for="id_proof">ID Proof (Upload a clear photo of your ID)</label>
            <input type="file" id="id_proof" name="id_proof" class="form-control" accept="image/*" required>
            <img id="id_preview" class="id-preview" style="display: none;">
        </div>
        
        <div class="form-actions">
            <button type="submit" name="submit_verification" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> Submit Verification
            </button>
            <a href="profile.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
