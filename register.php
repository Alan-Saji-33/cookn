<?php
require_once 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_type = $_POST['user_type'];
    
    // Check if username or email already exists
    $check_stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "Username or email already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone, user_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $password, $email, $phone, $user_type);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['error'] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
    $check_stmt->close();
}
?>

<div class="form-container">
    <div class="form-title">
        <h2>Create Your Account</h2>
        <p>Join our community of car enthusiasts</p>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" class="form-control" placeholder="Enter your phone number">
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Create a password" required>
        </div>
        
        <div class="form-group">
            <label for="user_type">I want to:</label>
            <select id="user_type" name="user_type" class="form-control" required>
                <option value="buyer">Buy Cars</option>
                <option value="seller">Sell Cars</option>
            </select>
        </div>
        
        <div class="form-group form-actions">
            <button type="submit" name="register" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
