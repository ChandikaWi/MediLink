<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'patient') {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $medicine_id = $_POST['medicine_id'];
    $quantity = $_POST['quantity'];
    $user_id = $_SESSION['user_id'];

    // Validate inputs
    if (!is_numeric($quantity) || $quantity <= 0) {
        $_SESSION['reservation_error'] = "Invalid quantity.";
        redirect('patient_dashboard.php');
    }

    // Check if medicine exists and has enough quantity
    $stmt_check = $conn->prepare("SELECT quantity FROM medicines WHERE medicine_id = ? AND quantity >= ?");
    $stmt_check->bind_param("ii", $medicine_id, $quantity);
    if (!$stmt_check->execute()) {
        $_SESSION['reservation_error'] = "Error checking medicine: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt_check->bind_result($available_quantity);
    if (!$stmt_check->fetch() || $available_quantity < $quantity) {
        $_SESSION['reservation_error'] = "Not enough stock available.";
        redirect('patient_dashboard.php');
    }
    $stmt_check->close();

    // Insert reservation
    $stmt = $conn->prepare("INSERT INTO reservations (medicine_id, user_id, quantity) VALUES (?, ?, ?)");
    if (!$stmt) {
        $_SESSION['reservation_error'] = "Query preparation failed: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt->bind_param("iii", $medicine_id, $user_id, $quantity);
    if ($stmt->execute()) {
        // Update medicine quantity
        $conn->query("UPDATE medicines SET quantity = quantity - $quantity WHERE medicine_id = $medicine_id");
        $_SESSION['reservation_success'] = "Medicine reserved successfully!";
        redirect('patient_dashboard.php');
    } else {
        $_SESSION['reservation_error'] = "Error reserving medicine: " . $conn->error;
        redirect('patient_dashboard.php');
    }
    $stmt->close();
}
?>