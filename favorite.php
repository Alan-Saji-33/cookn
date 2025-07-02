<?php
require_once 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = ['success' => false];
    
    if (!isset($_SESSION['user_id'])) {
        $response['error'] = 'Not logged in';
        echo json_encode($response);
        exit();
    }
    
    $car_id = isset($_POST['car_id']) ? (int)$_POST['car_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($car_id <= 0 || !in_array($action, ['add', 'remove'])) {
        $response['error'] = 'Invalid request';
        echo json_encode($response);
        exit();
    }
    
    if ($action == 'add') {
        // Check if already favorited
        $check_stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND car_id = ?");
        $check_stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows == 0) {
            $insert_stmt = $conn->prepare("INSERT INTO favorites (user_id, car_id) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
            $response['success'] = $insert_stmt->execute();
        } else {
            $response['success'] = true; // Already favorited
        }
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
        $delete_stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
        $response['success'] = $delete_stmt->execute();
    }
    
    echo json_encode($response);
    exit();
}

header("Location: index.php");
?>
