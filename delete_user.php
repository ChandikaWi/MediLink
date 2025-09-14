<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];

    // Validate input
    if (!is_numeric($user_id)) {
        $_SESSION['delete_error'] = "Invalid user ID.";
        redirect('admin_dashboard.php');
    }

    // Delete user (cascades to related tables)
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $_SESSION['delete_success'] = "User deleted successfully.";
    } else {
        $_SESSION['delete_error'] = "Error deleting user: " . $conn->error;
    }
    $stmt->close();
    redirect('admin_dashboard.php');
}
?>