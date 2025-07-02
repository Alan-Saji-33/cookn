<?php
require_once 'header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Get stats for dashboard
$users_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$sellers_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'seller'")->fetch_assoc()['count'];
$cars_count = $conn->query("SELECT COUNT(*) as count FROM cars")->fetch_assoc()['count'];
$pending_verifications = $conn->query("SELECT COUNT(*) as count FROM seller_verifications WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending verification requests
$verifications_stmt = $conn->prepare("SELECT sv.*, u.username, u.email, u.phone 
                                     FROM seller_verifications sv 
                                     JOIN users u ON sv.user_id = u.id 
                                     WHERE sv.status = 'pending'");
$verifications_stmt->execute();
$verifications_result = $verifications_stmt->get_result();
$verifications = $verifications_result->fetch_all(MYSQLI_ASSOC);

// Handle verification approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_verification'])) {
        $verification_id = $conn->real_escape_string($_POST['verification_id']);
        $user_id = $conn->real_escape_string($_POST['user_id']);
        
        // Update verification status
        $update_stmt = $conn->prepare("UPDATE seller_verifications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("ii", $_SESSION['user_id'], $verification_id);
        $update_stmt->execute();
        
        // Update user as verified
        $user_stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        
        $_SESSION['message'] = "Verification approved successfully!";
        header("Location: admin_dashboard.php");
        exit();
    } elseif (isset($_POST['reject_verification'])) {
        $verification_id = $conn->real_escape_string($_POST['verification_id']);
        $reason = $conn->real_escape_string($_POST['reason']);
        
        $update_stmt = $conn->prepare("UPDATE seller_verifications SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("sii", $reason, $_SESSION['user_id'], $verification_id);
        $update_stmt->execute();
        
        $_SESSION['message'] = "Verification rejected successfully!";
        header("Location: admin_dashboard.php");
        exit();
    }
}
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--light);">Admin Menu</h3>
        
        <ul class="dashboard-menu">
            <li><a href="#dashboard" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#verifications"><i class="fas fa-id-card"></i> Seller Verifications 
                <?php if ($pending_verifications > 0): ?>
                    <span style="background-color: var(--danger); color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px;"><?php echo $pending_verifications; ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="#users"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="#cars"><i class="fas fa-car"></i> Cars</a></li>
        </ul>
    </div>
    
    <div class="dashboard-content">
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
        
        <!-- Dashboard Section -->
        <div id="dashboard" class="dashboard-section active">
            <h3 class="dashboard-section-title">Dashboard Overview</h3>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $users_count; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $sellers_count; ?></div>
                    <div class="stat-label">Sellers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $cars_count; ?></div>
                    <div class="stat-label">Cars Listed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $pending_verifications; ?></div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
            </div>
        </div>
        
        <!-- Verifications Section -->
        <div id="verifications" class="dashboard-section">
            <h3 class="dashboard-section-title">Seller Verifications</h3>
            
            <?php if (empty($verifications)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-check-circle" style="font-size: 60px; color: var(--light-gray); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--gray);">No pending verification requests</h3>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Seller</th>
                                <th>ID Type</th>
                                <th>ID Number</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verifications as $verification): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($verification['username']); ?></strong><br>
                                        <?php echo htmlspecialchars($verification['email']); ?><br>
                                        <?php echo htmlspecialchars($verification['phone']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($verification['id_type']); ?></td>
                                    <td><?php echo htmlspecialchars($verification['id_number']); ?></td>
                                    <td><?php echo date('d M Y H:i', strtotime($verification['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary" onclick="viewVerification(<?php echo $verification['id']; ?>, '<?php echo htmlspecialchars($verification['id_proof']); ?>')">
                                            <i class="fas fa-eye"></i> View ID
                                        </button>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="verification_id" value="<?php echo $verification['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $verification['user_id']; ?>">
                                            <button type="submit" name="approve_verification" class="btn btn-success">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        
                                        <button class="btn btn-danger" onclick="showRejectForm(<?php echo $verification['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        
                                        <form method="POST" id="reject-form-<?php echo $verification['id']; ?>" style="display: none; margin-top: 10px;">
                                            <input type="hidden" name="verification_id" value="<?php echo $verification['id']; ?>">
                                            <div class="form-group">
                                                <label>Reason for Rejection</label>
                                                <textarea name="reason" class="form-control" rows="2" required></textarea>
                                            </div>
                                            <button type="submit" name="reject_verification" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Confirm Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Users Section -->
        <div id="users" class="dashboard-section">
            <h3 class="dashboard-section-title">Users Management</h3>
            
            <?php
            $users_stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
            $users_stmt->execute();
            $users_result = $users_stmt->get_result();
            $users = $users_result->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td>
                                    <?php if ($user['user_type'] == 'admin'): ?>
                                        <span class="badge badge-danger">Admin</span>
                                    <?php elseif ($user['user_type'] == 'seller'): ?>
                                        <span class="badge badge-warning">Seller</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">Buyer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['user_type'] == 'seller'): ?>
                                        <?php if ($user['is_verified']): ?>
                                            <span class="badge badge-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Unverified</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="#" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Cars Section -->
        <div id="cars" class="dashboard-section">
            <h3 class="dashboard-section-title">Cars Management</h3>
            
            <?php
            $cars_stmt = $conn->prepare("SELECT c.*, u.username as seller_name FROM cars c JOIN users u ON c.seller_id = u.id ORDER BY c.created_at DESC");
            $cars_stmt->execute();
            $cars_result = $cars_stmt->get_result();
            $cars = $cars_result->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Car</th>
                            <th>Seller</th>
                            <th>Price</th>
                            <th>Year</th>
                            <th>KM</th>
                            <th>Status</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cars as $car): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></strong><br>
                                    <?php echo htmlspecialchars($car['fuel_type']); ?>, <?php echo htmlspecialchars($car['transmission']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($car['seller_name']); ?></td>
                                <td>₹<?php echo number_format($car['price']); ?></td>
                                <td><?php echo htmlspecialchars($car['year']); ?></td>
                                <td><?php echo number_format($car['km_driven']); ?> km</td>
                                <td>
                                    <?php if ($car['is_sold']): ?>
                                        <span class="badge badge-danger">Sold</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($car['created_at'])); ?></td>
                                <td>
                                    <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="#" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div id="verification-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">ID Verification</h3>
            <button class="close-btn" onclick="closeModal('verification-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <img id="verification-image" src="" style="max-width: 100%;">
        </div>
    </div>
</div>

<script>
function viewVerification(id, imagePath) {
    document.getElementById('verification-image').src = imagePath;
    openModal('verification-modal');
}

function showRejectForm(id) {
    const form = document.getElementById('reject-form-' + id);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}
</script>

<?php require_once 'footer.php'; ?>
