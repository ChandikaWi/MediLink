<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'patient') {
    redirect('login.php');
}

require 'vendor/autoload.php';

use thiagoalessio\TesseractOCR\TesseractOCR;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['medicine_image'])) {
    $file = $_FILES['medicine_image'];
    
    if ($file['error'] !== 0) {
        $_SESSION['reservation_error'] = "Error uploading file: " . $file['error'];
        header("Location: patient_dashboard.php");
        exit;
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types) || $file['size'] > 5 * 1024 * 1024) {
        $_SESSION['reservation_error'] = "Invalid file type or size too large (max 5MB, JPEG/PNG/GIF only).";
        header("Location: patient_dashboard.php");
        exit;
    }
    
    $temp_name = $file['tmp_name'];
    
    // Validate it's a real image
    $imageInfo = getimagesize($temp_name);
    if ($imageInfo === false) {
        $_SESSION['reservation_error'] = "Uploaded file is not a valid image. Try a different photo.";
        header("Location: patient_dashboard.php");
        exit;
    }
    if (!in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
        $_SESSION['reservation_error'] = "Only JPEG, PNG, or GIF images are supported.";
        header("Location: patient_dashboard.php");
        exit;
    }
    
    // Debug log
    error_log("Processing image: " . $temp_name . " (Type: " . $imageInfo['mime'] . ", Size: " . $file['size'] . ")");

    // Preprocess function 
    function preprocessImage($filePath, $imageInfo) {
        $image = false;
        $type = $imageInfo[2];
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                if (!function_exists('imagecreatefromjpeg')) {
                    error_log("GD JPEG support missing");
                    return false;
                }
                $image = imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                if (!function_exists('imagecreatefrompng')) {
                    error_log("GD PNG support missing");
                    return false;
                }
                $image = imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                if (!function_exists('imagecreatefromgif')) {
                    error_log("GD GIF support missing");
                    return false;
                }
                $image = imagecreatefromgif($filePath);
                break;
        }
        
        if (!$image) {
            error_log("Failed to create image resource from: " . $filePath);
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $newWidth = (int)($width * 1.5);
        $newHeight = (int)($height * 1.5);
        $resized = imagescale($image, $newWidth, $newHeight);

        imagefilter($resized, IMG_FILTER_GRAYSCALE);
        imagefilter($resized, IMG_FILTER_CONTRAST, -20);

        $threshold = 128;
        for ($x = 0; $x < $newWidth; $x++) {
            for ($y = 0; $y < $newHeight; $y++) {
                $color = imagecolorat($resized, $x, $y);
                $gray = ($color >> 16) & 0xFF;
                $newColor = ($gray < $threshold) ? 0 : 255;
                imagesetpixel($resized, $x, $y, imagecolorallocate($resized, $newColor, $newColor, $newColor));
            }
        }

        $processedPath = tempnam(sys_get_temp_dir(), 'ocr_');
        imagejpeg($resized, $processedPath, 90);

        imagedestroy($image);
        imagedestroy($resized);

        return $processedPath;
    }

    $processedPath = preprocessImage($temp_name, $imageInfo);
    if (!$processedPath) {
        error_log("Preprocessing failed; using original image");
        $processedPath = $temp_name;  
    }

    try {
        $ocr = new TesseractOCR($processedPath);
        $ocr->psm(6);
        $text = $ocr->run();

        if (is_file($processedPath) && $processedPath !== $temp_name) {
            unlink($processedPath);
        }

        $text = trim(preg_replace('/\s+/', ' ', $text));
        $words = preg_split('/\W+/', strtolower($text));
        $filteredWords = array_filter($words, function($word) {
            return strlen($word) > 3 && ctype_alnum($word);
        });
        $cleanedText = implode(' ', $filteredWords);

        if (!empty($cleanedText)) {
            header("Location: search.php?medicine=" . urlencode($cleanedText));
            exit;
        } else {
            $_SESSION['reservation_error'] = "No readable text extracted. Try a clearer photo with visible text.";
            header("Location: patient_dashboard.php");
            exit;
        }
    } catch (Exception $e) {
        if (isset($processedPath) && is_file($processedPath) && $processedPath !== $temp_name) {
            unlink($processedPath);
        }
        error_log("Tesseract error: " . $e->getMessage());
        $_SESSION['reservation_error'] = "OCR processing failed: " . $e->getMessage() . ". Ensure Tesseract is installed.";
        header("Location: patient_dashboard.php");
        exit;
    }
} else {
    header("Location: patient_dashboard.php");
    exit;
}