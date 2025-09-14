<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sendEmail($to, $subject, $message) {
    $headers = "From: no-reply@medtracker.com\r\n";
    mail($to, $subject, $message, $headers);
}
?>