<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || !in_array(getUserRole(), ['patient', 'pharmacy'])) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if a deletion request already exists
    $check_query = $conn->prepare("SELECT id FROM account_deletion_requests WHERE user_id = ? AND status = 'pending'");
    $check_query->bind_param("i", $user_id);
    $check_query->execute();
    $check_result = $check_query->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['deletion_error'] = "You already have a pending deletion request.";
    } else {
        // Insert deletion request into the database
        $stmt = $conn->prepare("INSERT INTO account_deletion_requests (user_id, request_date, status) VALUES (?, NOW(), 'pending')");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // Clear session and redirect to login
            session_unset();
            session_destroy();
            header('Location: login.php');
            exit();
        } else {
            $_SESSION['deletion_error'] = "Error submitting deletion request: " . $conn->error;
        }
        $stmt->close();
    }
    $check_query->close();
    redirect('login.php');
}
?>