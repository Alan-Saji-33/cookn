<?php
require_once 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['is_verified'] = $user['is_verified'];
            
            $_SESSION['message'] = "Login successful!";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid password.";
        }
    } else {
        $_SESSION['error'] = "User not found.";
    }
    $stmt->close();
}
?>

<div class="form-container">
    <div class="form-title">
        <h2>Login to Your Account</h2>
        <p>Enter your credentials to access your dashboard</p>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        
        <div class="form-group form-actions">
            <button type="submit" name="login" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="#">Forgot your password?</a></p>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
