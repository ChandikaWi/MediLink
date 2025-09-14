<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'pharmacy') {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reservation_id = $_POST['reservation_id'];
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];

    // Validate status
    if (!in_array($status, ['pending', 'confirmed', 'cancelled'])) {
        $_SESSION['reservation_update_error'] = "Invalid status selected.";
        redirect('pharmacy_dashboard.php');
    }

    // Verify the reservation belongs to this pharmacy
    $stmt_check = $conn->prepare("SELECT r.reservation_id 
                                  FROM reservations r 
                                  JOIN medicines m ON r.medicine_id = m.medicine_id 
                                  JOIN pharmacies p ON m.pharmacy_id = p.pharmacy_id 
                                  WHERE r.reservation_id = ? AND p.user_id = ?");
    if (!$stmt_check) {
        $_SESSION['reservation_update_error'] = "Query preparation failed: " . $conn->error;
        redirect('pharmacy_dashboard.php');
    }
    $stmt_check->bind_param("ii", $reservation_id, $user_id);
    if (!$stmt_check->execute()) {
        $_SESSION['reservation_update_error'] = "Query execution failed: " . $conn->error;
        redirect('pharmacy_dashboard.php');
    }
    if (!$stmt_check->fetch()) {
        $_SESSION['reservation_update_error'] = "Reservation not found or you don't have permission to update it.";
        redirect('pharmacy_dashboard.php');
    }
    $stmt_check->close();

    // Update status
    $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
    if (!$stmt) {
        $_SESSION['reservation_update_error'] = "Query preparation failed: " . $conn->error;
        redirect('pharmacy_dashboard.php');
    }
    $stmt->bind_param("si", $status, $reservation_id);
    if ($stmt->execute()) {
        $_SESSION['reservation_update_success'] = "Reservation status updated to '$status'.";
        redirect('pharmacy_dashboard.php');
    } else {
        $_SESSION['reservation_update_error'] = "Error updating status: " . $conn->error;
        redirect('pharmacy_dashboard.php');
    }
    $stmt->close();
}
?>