<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'patient') {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reservation_id = $_POST['reservation_id'];
    $user_id = $_SESSION['user_id'];

    // Verify the reservation belongs to this patient and is not already cancelled
    $stmt_check = $conn->prepare("SELECT r.medicine_id, r.quantity, r.status 
                                  FROM reservations r 
                                  WHERE r.reservation_id = ? AND r.user_id = ?");
    if (!$stmt_check) {
        $_SESSION['reservation_error'] = "Query preparation failed: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt_check->bind_param("ii", $reservation_id, $user_id);
    if (!$stmt_check->execute()) {
        $_SESSION['reservation_error'] = "Query execution failed: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt_check->bind_result($medicine_id, $quantity, $status);
    if (!$stmt_check->fetch()) {
        $_SESSION['reservation_error'] = "Reservation not found or you don't have permission to cancel it.";
        redirect('patient_dashboard.php');
    }
    $stmt_check->close();

    if ($status == 'cancelled') {
        $_SESSION['reservation_error'] = "This reservation is already cancelled.";
        redirect('patient_dashboard.php');
    }

    // Update reservation status to cancelled
    $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?");
    if (!$stmt) {
        $_SESSION['reservation_error'] = "Query preparation failed: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt->bind_param("i", $reservation_id);
    if (!$stmt->execute()) {
        $_SESSION['reservation_error'] = "Error cancelling reservation: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt->close();

    // Restore medicine quantity
    $stmt_restore = $conn->prepare("UPDATE medicines SET quantity = quantity + ? WHERE medicine_id = ?");
    if (!$stmt_restore) {
        $_SESSION['reservation_error'] = "Query preparation failed for restoring quantity: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt_restore->bind_param("ii", $quantity, $medicine_id);
    if (!$stmt_restore->execute()) {
        $_SESSION['reservation_error'] = "Error restoring medicine quantity: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt_restore->close();

    $_SESSION['reservation_success'] = "Reservation cancelled successfully!";
    redirect('patient_dashboard.php');
}
?>