<?php
include 'config.php';
include 'functions.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate form inputs
    if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['role'])) {
        $error_message = 'All fields are required. Please fill in all the required information.';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $location = trim($_POST['location'] ?? '');

        // Validate role
        if (!in_array($role, ['pharmacy', 'patient'])) {
            $error_message = 'Invalid role selected. Please choose either Patient or Pharmacy.';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, location) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password, $role, $location);
            if ($stmt->execute()) {
                if ($role == 'pharmacy') {
                    $user_id = $conn->insert_id;
                    $pharmacy_name = trim($_POST['pharmacy_name'] ?? '');
                    $address = trim($_POST['address'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    if (empty($pharmacy_name) || empty($address)) {
                        $error_message = 'Pharmacy name and address are required for pharmacy role.';
                    } else {
                        $stmt_ph = $conn->prepare("INSERT INTO pharmacies (user_id, pharmacy_name, address, phone) VALUES (?, ?, ?, ?)");
                        $stmt_ph->bind_param("isss", $user_id, $pharmacy_name, $address, $phone);
                        if (!$stmt_ph->execute()) {
                            $error_message = 'Error registering pharmacy: ' . $conn->error;
                        } else {
                            $success_message = 'Registration successful! Redirecting to login page...';
                            echo '<script>setTimeout(function() { window.location.href = "login.php"; }, 2000);</script>';
                        }
                    }
                } else {
                    $success_message = 'Registration successful! Redirecting to login page...';
                    echo '<script>setTimeout(function() { window.location.href = "login.php"; }, 2000);</script>';
                }
            } else {
                $error_message = 'Error: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Medicine Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --secondary-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--gray-700);
            line-height: 1.6;
        }

        .top-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-sm);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-link {
            color: var(--gray-600);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            transform: translateY(-1px);
        }

        .main-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            animation: fadeInUp 0.6s ease;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 400;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            animation: fadeInUp 0.6s ease 0.2s both;
        }

        .form-header {
            background: linear-gradient(135deg, var(--gray-100), #ffffff);
            padding: 2rem;
            border-bottom: 1px solid var(--gray-200);
            text-align: center;
        }

        .form-header h3 {
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .form-body {
            padding: 2.5rem;
        }

        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-1px);
        }

        .form-control:valid {
            border-color: var(--success-color);
        }

        .form-control::placeholder {
            color: var(--gray-600);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1rem;
            padding-right: 2.5rem;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
            z-index: 10;
        }

        .form-control.with-icon {
            padding-left: 3rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-grid .form-group {
            margin-bottom: 1.5rem;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-600);
            cursor: pointer;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        #pharmacy_fields {
            border: 2px dashed var(--gray-200);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 2rem;
            background: rgba(79, 70, 229, 0.02);
            transition: all 0.5s ease;
            position: relative;
        }

        #pharmacy_fields.show {
            animation: slideInDown 0.5s ease;
            border-color: var(--primary-color);
            background: rgba(79, 70, 229, 0.05);
        }

        .pharmacy-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .role-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .role-card {
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.5);
            text-align: center;
        }

        .role-card:hover {
            border-color: var(--primary-color);
            background: rgba(79, 70, 229, 0.05);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .role-card.active {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(6, 182, 212, 0.05));
            box-shadow: var(--shadow-lg);
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card-icon {
            font-size: 2rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }

        .role-card.active .role-card-icon {
            color: var(--primary-color);
        }

        .role-card-title {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .role-card-desc {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .btn-container {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }

        .btn-modern {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
            min-width: 160px;
            justify-content: center;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary-modern:hover {
            background: linear-gradient(135deg, var(--primary-dark), #3730a3);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary-modern:active {
            transform: translateY(0);
        }

        .btn-secondary-modern {
            background: linear-gradient(135deg, var(--gray-600), var(--gray-700));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-secondary-modern:hover {
            background: linear-gradient(135deg, var(--gray-700), var(--gray-800));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-loading .btn-text {
            opacity: 0;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            padding: 1.25rem;
            animation: slideInDown 0.3s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(34, 197, 94, 0.1));
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert i {
            margin-right: 0.75rem;
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-control.is-valid {
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .invalid-feedback, .valid-feedback {
            display: block;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            padding-left: 0.5rem;
        }

        .invalid-feedback {
            color: var(--danger-color);
        }

        .valid-feedback {
            color: var(--success-color);
        }

        .form-links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

        .form-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .progress-indicator {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: rgba(79, 70, 229, 0.2);
            z-index: 9999;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            width: 0%;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 0 1rem;
            }
            
            .form-body {
                padding: 1.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .role-selection {
                grid-template-columns: 1fr;
            }
            
            .btn-container {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-modern {
                width: 100%;
                max-width: 280px;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dark-mode {
            --light-color: #1e293b;
            --gray-100: #334155;
            --gray-200: #475569;
            --gray-600: #cbd5e1;
            --gray-700: #e2e8f0;
            --gray-800: #f1f5f9;
        }
        
        .dark-mode body {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: var(--gray-700);
        }
        
        .dark-mode .form-card,
        .dark-mode .top-nav {
            background: rgba(30, 41, 59, 0.95);
            border-color: var(--gray-200);
        }
        
        .dark-mode .form-header {
            background: linear-gradient(135deg, var(--gray-200), #334155);
        }
        
        .dark-mode .form-control {
            background: rgba(51, 65, 85, 0.8);
            border-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .dark-mode .form-control:focus {
            background: rgba(51, 65, 85, 0.95);
        }

        .dark-mode .role-card {
            background: rgba(51, 65, 85, 0.5);
            border-color: var(--gray-300);
        }

        .dark-mode #pharmacy_fields {
            background: rgba(79, 70, 229, 0.1);
        }
    </style>
</head>
<body>
    <!-- Progress Indicator -->
    <div class="progress-indicator">
        <div class="progress-bar" id="progressBar"></div>
    </div>

    <!-- Navigation -->
    <nav class="top-nav">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a class="navbar-brand" href="index.html">
                    <i class="fas fa-heartbeat me-2"></i>MediLink
                </a>
                <div class="d-flex gap-2">
                    <a class="nav-link" href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" id="darkModeToggle" title="Toggle Dark Mode">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-plus me-3"></i>Create Account
            </h1>
            <p class="page-subtitle">Join MediLink to manage your healthcare needs</p>
        </div>

        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card">
            <div class="form-header">
                <h3>
                    <i class="fas fa-user-circle"></i>
                    Registration Information
                </h3>
            </div>
            <div class="form-body">
                <form method="POST" id="registerForm" novalidate>
                    <!-- Personal Information -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user me-2"></i>Full Name
                            </label>
                            <div class="input-group">
                                <i class="fas fa-user input-icon"></i>
                                <input 
                                    type="text" 
                                    name="name" 
                                    class="form-control with-icon" 
                                    placeholder="Enter your full name"
                                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                    required
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email Address
                            </label>
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input 
                                    type="email" 
                                    name="email" 
                                    class="form-control with-icon" 
                                    placeholder="Enter your email address"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    required
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon"></i>
                                <input 
                                    type="password" 
                                    name="password" 
                                    class="form-control with-icon" 
                                    placeholder="Create a secure password"
                                    required
                                    minlength="6"
                                >
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt me-2"></i>Location
                            </label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt input-icon"></i>
                                <input 
                                    type="text" 
                                    name="location" 
                                    class="form-control with-icon" 
                                    placeholder="Enter your city/location"
                                    value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-tag me-2"></i>Account Type
                        </label>
                        <div class="role-selection">
                            <div class="role-card" data-role="patient">
                                <input type="radio" name="role" value="patient" id="patient" <?php echo (!isset($_POST['role']) || $_POST['role'] == 'patient') ? 'checked' : ''; ?>>
                                <div class="role-card-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="role-card-title">Patient</div>
                                <div class="role-card-desc">I need to find medicines and reserve</div>
                            </div>
                            <div class="role-card" data-role="pharmacy">
                                <input type="radio" name="role" value="pharmacy" id="pharmacy" <?php echo (isset($_POST['role']) && $_POST['role'] == 'pharmacy') ? 'checked' : ''; ?>>
                                <div class="role-card-icon">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div class="role-card-title">Pharmacy</div>
                                <div class="role-card-desc">I want to manage my pharmacy inventory and track reservations</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pharmacy Fields -->
                    <div id="pharmacy_fields" style="display:none;">
                        <div class="pharmacy-header">
                            <i class="fas fa-store"></i>
                            <span>Pharmacy Information</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-prescription-bottle me-2"></i>Pharmacy Name
                            </label>
                            <div class="input-group">
                                <i class="fas fa-prescription-bottle input-icon"></i>
                                <input 
                                    type="text" 
                                    name="pharmacy_name" 
                                    class="form-control with-icon" 
                                    placeholder="Enter your pharmacy name"
                                    value="<?php echo isset($_POST['pharmacy_name']) ? htmlspecialchars($_POST['pharmacy_name']) : ''; ?>"
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-home me-2"></i>Pharmacy Address
                            </label>
                            <div class="input-group">
                                <i class="fas fa-home input-icon"></i>
                                <input 
                                    type="text" 
                                    name="address" 
                                    class="form-control with-icon" 
                                    placeholder="Enter complete address"
                                    value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>"
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-phone me-2"></i>Phone Number
                            </label>
                            <div class="input-group">
                                <i class="fas fa-phone input-icon"></i>
                                <input 
                                    type="tel" 
                                    name="phone" 
                                    class="form-control with-icon" 
                                    placeholder="Enter phone number (optional)"
                                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <!-- Register Button -->
                    <div class="btn-container">
                        <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                            <span class="btn-text">
                                <i class="fas fa-user-plus"></i> Create Account
                            </span>
                        </button>
                    </div>

                    <!-- Additional Links -->
                    <div class="form-links">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Log in here
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const icon = darkModeToggle.querySelector('i');
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'fas fa-sun';
                localStorage.setItem('darkMode', 'enabled');
            } else {
                icon.className = 'fas fa-moon';
                localStorage.setItem('darkMode', 'disabled');
            }
        });

        // Load saved dark mode preference
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            darkModeToggle.querySelector('i').className = 'fas fa-sun';
        }

        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.querySelector('input[name="password"]');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Role selection functionality
        const roleCards = document.querySelectorAll('.role-card');
        const roleInputs = document.querySelectorAll('input[name="role"]');
        const pharmacyFields = document.getElementById('pharmacy_fields');

        function updateRoleSelection() {
            // Update visual appearance of cards
            roleCards.forEach(card => card.classList.remove('active'));
            
            const selectedInput = document.querySelector('input[name="role"]:checked');
            if (selectedInput) {
                const selectedRole = selectedInput.value;
                const selectedCard = document.querySelector(`[data-role="${selectedRole}"]`);
                if (selectedCard) {
                    selectedCard.classList.add('active');
                }
                
                // Toggle pharmacy fields
                if (selectedRole === 'pharmacy') {
                    pharmacyFields.style.display = 'block';
                    pharmacyFields.classList.add('show');
                    
                    // Make pharmacy fields required
                    const pharmacyInputs = pharmacyFields.querySelectorAll('input[name="pharmacy_name"], input[name="address"]');
                    pharmacyInputs.forEach(input => {
                        input.required = true;
                    });
                } else {
                    pharmacyFields.style.display = 'none';
                    pharmacyFields.classList.remove('show');
                    
                    // Remove pharmacy field requirements
                    const pharmacyInputs = pharmacyFields.querySelectorAll('input');
                    pharmacyInputs.forEach(input => {
                        input.required = false;
                        input.classList.remove('is-invalid', 'is-valid');
                    });
                }
            }
            updateProgress();
        }

        // Handle card clicks
        roleCards.forEach(card => {
            card.addEventListener('click', (e) => {
                // Prevent if clicking on the actual radio button
                if (e.target.type === 'radio') return;
                
                const role = card.dataset.role;
                const radioInput = document.getElementById(role);
                if (radioInput) {
                    radioInput.checked = true;
                    updateRoleSelection();
                }
            });
        });

        // Handle direct radio button changes
        roleInputs.forEach(input => {
            input.addEventListener('change', updateRoleSelection);
        });

        // Initialize role selection after DOM is ready
        setTimeout(updateRoleSelection, 100);

        // Form validation and enhancement
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');
        const progressBar = document.getElementById('progressBar');

        // Real-time validation
        const inputs = form.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', validateInput);
            input.addEventListener('blur', validateInput);
        });

        function validateInput(e) {
            const input = e.target;
            const feedback = input.parentNode.parentNode.querySelector('.invalid-feedback');
            
            // Skip validation for hidden pharmacy fields
            if (!input.offsetParent && input.closest('#pharmacy_fields')) {
                return;
            }
            
            // Remove previous validation classes
            input.classList.remove('is-valid', 'is-invalid');
            
            // Validate based on input type and requirements
            let isValid = true;
            let message = '';
            
            if (input.required && input.value.trim() === '') {
                isValid = false;
                message = 'This field is required.';
            } else if (input.value.trim() !== '') {
                switch (input.type) {
                    case 'text':
                        if (input.name === 'name' && input.value.length < 2) {
                            isValid = false;
                            message = 'Name must be at least 2 characters long.';
                        } else if (input.name === 'pharmacy_name' && input.value.length < 3) {
                            isValid = false;
                            message = 'Pharmacy name must be at least 3 characters long.';
                        } else if (input.name === 'address' && input.value.length < 10) {
                            isValid = false;
                            message = 'Please enter a complete address.';
                        }
                        break;
                    case 'email':
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(input.value)) {
                            isValid = false;
                            message = 'Please enter a valid email address.';
                        }
                        break;
                    case 'password':
                        if (input.value.length < 6) {
                            isValid = false;
                            message = 'Password must be at least 6 characters long.';
                        } else if (!/(?=.*[a-z])(?=.*[A-Z])|(?=.*\d)/.test(input.value)) {
                            message = 'Consider using a mix of letters, numbers for better security.';
                        }
                        break;
                    case 'tel':
                        const phoneRegex = /^[\+]?[\d\s\-\(\)]{10,}$/;
                        if (input.value && !phoneRegex.test(input.value)) {
                            isValid = false;
                            message = 'Please enter a valid phone number.';
                        }
                        break;
                }
            }
            
            // Apply validation classes
            if (input.value.trim() !== '' || input.required) {
                input.classList.add(isValid ? 'is-valid' : 'is-invalid');
            }
            
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = (!isValid && message) ? 'block' : 'none';
            }
            
            // Update progress
            updateProgress();
        }

        // Update form progress
        function updateProgress() {
            const requiredInputs = Array.from(inputs).filter(input => 
                input.required && input.offsetParent // Only count visible required inputs
            );
            const validInputs = requiredInputs.filter(input => 
                input.classList.contains('is-valid') || 
                (input.value.trim() !== '' && !input.classList.contains('is-invalid'))
            );
            const progress = requiredInputs.length > 0 ? (validInputs.length / requiredInputs.length) * 100 : 0;
            progressBar.style.width = progress + '%';
        }

        // Form submission with loading state
        form.addEventListener('submit', function(e) {
            // Validate all visible required inputs
            const requiredInputs = Array.from(inputs).filter(input => 
                input.required && input.offsetParent
            );
            
            let formIsValid = true;
            requiredInputs.forEach(input => {
                validateInput({ target: input });
                if (input.classList.contains('is-invalid') || 
                    (input.required && input.value.trim() === '')) {
                    formIsValid = false;
                }
            });
            
            if (!formIsValid) {
                e.preventDefault();
                showNotification('Please fix the errors in the form before submitting.', 'danger');
                return;
            }
            
            // Add loading state
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            // Update progress to 100%
            progressBar.style.width = '100%';
            
            // Show loading notification
            showNotification('Creating your account...', 'info');
        });

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                animation: slideInRight 0.3s ease;
                box-shadow: var(--shadow-lg);
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'danger' ? 'times' : 'info'}-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Add slide animation for notifications
        const notificationStyle = document.createElement('style');
        notificationStyle.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(notificationStyle);

        // Auto-save form data to prevent data loss
        function saveFormData() {
            const formData = {};
            inputs.forEach(input => {
                if (input.value) {
                    formData[input.name] = input.value;
                }
            });
            // Also save selected role
            const selectedRole = document.querySelector('input[name="role"]:checked');
            if (selectedRole) {
                formData.role = selectedRole.value;
            }
            localStorage.setItem('registerFormData', JSON.stringify(formData));
        }

        function loadFormData() {
            const savedData = localStorage.getItem('registerFormData');
            if (savedData) {
                const formData = JSON.parse(savedData);
                Object.keys(formData).forEach(key => {
                    if (key === 'role') {
                        const roleInput = document.getElementById(formData[key]);
                        if (roleInput) {
                            roleInput.checked = true;
                            updateRoleSelection();
                        }
                    } else {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input && !input.value) {
                            input.value = formData[key];
                            validateInput({ target: input });
                        }
                    }
                });
            }
        }

        function clearFormData() {
            localStorage.removeItem('registerFormData');
        }

        // Auto-save on input
        inputs.forEach(input => {
            input.addEventListener('input', saveFormData);
        });

        // Save role selection
        roleInputs.forEach(input => {
            input.addEventListener('change', saveFormData);
        });

        // Load saved data on page load
        window.addEventListener('load', () => {
            loadFormData();
            updateProgress();
        });

        // Clear saved data on successful submission
        form.addEventListener('submit', () => {
            setTimeout(clearFormData, 1000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                form.submit();
            }
            
            // Escape to clear form
            if (e.key === 'Escape') {
                e.preventDefault();
                if (confirm('Are you sure you want to clear the form? Any unsaved changes will be lost.')) {
                    form.reset();
                    clearFormData();
                    updateRoleSelection();
                    updateProgress();
                }
            }
        });

        // Auto-focus first input
        window.addEventListener('load', () => {
            const firstInput = form.querySelector('input[name="name"]');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Password strength indicator
        const passwordStrengthIndicator = document.createElement('div');
        passwordStrengthIndicator.style.cssText = `
            margin-top: 0.5rem;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        `;
        passwordInput.parentNode.parentNode.appendChild(passwordStrengthIndicator);

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let message = '';
            let color = '';

            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]/)) strength += 1;
            if (password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^A-Za-z0-9]/)) strength += 1;

            switch (strength) {
                case 0:
                case 1:
                    message = 'Weak password';
                    color = 'var(--danger-color)';
                    break;
                case 2:
                case 3:
                    message = 'Medium password';
                    color = 'var(--warning-color)';
                    break;
                case 4:
                case 5:
                    message = 'Strong password';
                    color = 'var(--success-color)';
                    break;
            }

            if (password.length > 0) {
                passwordStrengthIndicator.textContent = message;
                passwordStrengthIndicator.style.color = color;
                passwordStrengthIndicator.style.opacity = '1';
            } else {
                passwordStrengthIndicator.style.opacity = '0';
            }
        });

        // Success celebration
        function celebrateSuccess() {
            // Create confetti effect
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.style.cssText = `
                    position: fixed;
                    top: -10px;
                    left: ${Math.random() * 100}%;
                    width: 10px;
                    height: 10px;
                    background: ${['#4f46e5', '#06b6d4', '#10b981', '#f59e0b'][Math.floor(Math.random() * 4)]};
                    z-index: 10000;
                    pointer-events: none;
                    animation: confettiFall 3s ease-out forwards;
                `;
                document.body.appendChild(confetti);
                
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.remove();
                    }
                }, 3000);
            }
        }

        // Add confetti animation
        const confettiStyle = document.createElement('style');
        confettiStyle.textContent = `
            @keyframes confettiFall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(confettiStyle);

        // Check for success and celebrate
        if (window.location.search.includes('success') || document.querySelector('.alert-success')) {
            setTimeout(celebrateSuccess, 500);
        }
    </script>
</body>
</html>