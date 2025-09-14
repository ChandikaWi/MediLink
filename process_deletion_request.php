<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if (!$request_id || !in_array($action, ['process', 'cancel'])) {
        $_SESSION['deletion_error'] = "Invalid request or action.";
        redirect('admin_dashboard.php');
    }

    // Update request status
    $stmt = $conn->prepare("UPDATE account_deletion_requests SET status = ? WHERE id = ?");
    $new_status = $action === 'process' ? 'processed' : 'cancelled';
    $stmt->bind_param("si", $new_status, $request_id);

    if ($stmt->execute()) {
        if ($action === 'process') {
            // Delete user and related data 
            $user_id_query = $conn->prepare("SELECT user_id FROM account_deletion_requests WHERE id = ?");
            $user_id_query->bind_param("i", $request_id);
            $user_id_query->execute();
            $user_id_result = $user_id_query->get_result();
            $user_id = $user_id_result->fetch_assoc()['user_id'];
            $user_id_query->close();

            // Delete user 
            $delete_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delete_user->bind_param("i", $user_id);
            $delete_user->execute();
            $delete_user->close();

            $_SESSION['deletion_success'] = "Account deletion request processed successfully.";
        } else {
            $_SESSION['deletion_success'] = "Account deletion request cancelled successfully.";
        }
    } else {
        $_SESSION['deletion_error'] = "Error updating deletion request: " . $conn->error;
    }
    $stmt->close();
    redirect('admin_dashboard.php');
}
?>