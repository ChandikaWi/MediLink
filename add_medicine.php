<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'pharmacy') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT pharmacy_id FROM pharmacies WHERE user_id = ?");
if (!$stmt) {
    die('<div class="alert alert-danger">Query preparation failed: ' . $conn->error . '</div>');
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die('<div class="alert alert-danger">Query execution failed: ' . $conn->error . '</div>');
}
$stmt->bind_result($pharmacy_id);
if (!$stmt->fetch()) {
    die('<div class="alert alert-danger">No pharmacy found for this user. Please ensure your pharmacy is registered correctly.</div>');
}
$stmt->close();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $batch_no = trim($_POST['batch_no'] ?? '');
    $quantity = $_POST['quantity'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $price = $_POST['price'] ?? '';

    // Validate inputs
    if (empty($name) || empty($batch_no) || !is_numeric($quantity) || $quantity < 0 || empty($expiry_date) || !is_numeric($price) || $price < 0) {
        $error_message = 'All fields are required, and quantity/price must be valid numbers.';
    } else {
        // Check if batch number already exists for this pharmacy
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM medicines WHERE pharmacy_id = ? AND batch_no = ?");
        $check_stmt->bind_param("is", $pharmacy_id, $batch_no);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($count > 0) {
            $error_message = 'A medicine with this batch number already exists in your inventory.';
        } else {
            $stmt_m = $conn->prepare("INSERT INTO medicines (pharmacy_id, name, batch_no, quantity, expiry_date, price) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_m) {
                $error_message = 'Query preparation failed: ' . $conn->error;
            } else {
                $stmt_m->bind_param("issisd", $pharmacy_id, $name, $batch_no, $quantity, $expiry_date, $price);
                if ($stmt_m->execute()) {
                    $success_message = 'Medicine added successfully! Redirecting to dashboard...';
                    echo '<script>setTimeout(function() { window.location.href = "pharmacy_dashboard.php"; }, 2000);</script>';
                } else {
                    $error_message = 'Error adding medicine: ' . $conn->error;
                }
                $stmt_m->close();
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
    <title>Add Medicine - Medicine Tracker</title>
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
            max-width: 800px;
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
                    <a class="nav-link" href="pharmacy_dashboard.php">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
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
                <i class="fas fa-plus-circle me-3"></i>Add New Medicine
            </h1>
            <p class="page-subtitle">Expand your pharmacy inventory with new medicines</p>
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
                    <i class="fas fa-pills"></i>
                    Medicine Information
                </h3>
            </div>
            <div class="form-body">
                <form method="POST" id="medicineForm" novalidate>
                    <!-- Medicine Name -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-pills me-2"></i>Medicine Name
                        </label>
                        <div class="input-group">
                            <i class="fas fa-pills input-icon"></i>
                            <input 
                                type="text" 
                                name="name" 
                                class="form-control with-icon" 
                                placeholder="Enter medicine name (e.g., Paracetamol, Ibuprofen)"
                                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                required
                            >
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Form Grid for Batch and Quantity -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-barcode me-2"></i>Batch Number
                            </label>
                            <div class="input-group">
                                <i class="fas fa-barcode input-icon"></i>
                                <input 
                                    type="text" 
                                    name="batch_no" 
                                    class="form-control with-icon" 
                                    placeholder="Enter batch number"
                                    value="<?php echo isset($_POST['batch_no']) ? htmlspecialchars($_POST['batch_no']) : ''; ?>"
                                    required
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-cubes me-2"></i>Quantity
                            </label>
                            <div class="input-group">
                                <i class="fas fa-cubes input-icon"></i>
                                <input 
                                    type="number" 
                                    name="quantity" 
                                    class="form-control with-icon" 
                                    placeholder="Enter quantity"
                                    value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : ''; ?>"
                                    required 
                                    min="0"
                                    step="1"
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <!-- Form Grid for Expiry and Price -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt me-2"></i>Expiry Date
                            </label>
                            <div class="input-group">
                                <i class="fas fa-calendar-alt input-icon"></i>
                                <input 
                                    type="date" 
                                    name="expiry_date" 
                                    class="form-control with-icon" 
                                    value="<?php echo isset($_POST['expiry_date']) ? htmlspecialchars($_POST['expiry_date']) : ''; ?>"
                                    required
                                    min="<?php echo date('Y-m-d'); ?>"
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-dollar-sign me-2"></i>Price (LKR)
                            </label>
                            <div class="input-group">
                                <i class="fas fa-dollar-sign input-icon"></i>
                                <input 
                                    type="number" 
                                    name="price" 
                                    class="form-control with-icon" 
                                    placeholder="Enter price"
                                    value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>"
                                    required 
                                    min="0" 
                                    step="0.01"
                                >
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="btn-container">
                        <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                            <span class="btn-text">
                                <i class="fas fa-plus-circle"></i> Add Medicine
                            </span>
                        </button>
                        <a href="pharmacy_dashboard.php" class="btn-modern btn-secondary-modern">
                            <i class="fas fa-times"></i>Cancel
                        </a>
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

        // Form validation and enhancement
        const form = document.getElementById('medicineForm');
        const submitBtn = document.getElementById('submitBtn');
        const progressBar = document.getElementById('progressBar');

        // Real-time validation
        const inputs = form.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('input', validateInput);
            input.addEventListener('blur', validateInput);
        });

        function validateInput(e) {
            const input = e.target;
            const feedback = input.parentNode.parentNode.querySelector('.invalid-feedback');
            
            // Remove previous validation classes
            input.classList.remove('is-valid', 'is-invalid');
            
            // Validate based on input type
            let isValid = true;
            let message = '';
            
            if (input.value.trim() === '') {
                isValid = false;
                message = 'This field is required.';
            } else {
                switch (input.type) {
                    case 'text':
                        if (input.name === 'name' && input.value.length < 2) {
                            isValid = false;
                            message = 'Medicine name must be at least 2 characters long.';
                        } else if (input.name === 'batch_no' && input.value.length < 3) {
                            isValid = false;
                            message = 'Batch number must be at least 3 characters long.';
                        }
                        break;
                    case 'number':
                        const num = parseFloat(input.value);
                        if (isNaN(num) || num < 0) {
                            isValid = false;
                            message = 'Please enter a valid positive number.';
                        } else if (input.name === 'quantity' && !Number.isInteger(num)) {
                            isValid = false;
                            message = 'Quantity must be a whole number.';
                        }
                        break;
                    case 'date':
                        const selectedDate = new Date(input.value);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        if (selectedDate <= today) {
                            isValid = false;
                            message = 'Expiry date must be in the future.';
                        }
                        break;
                }
            }
            
            // Apply validation classes
            input.classList.add(isValid ? 'is-valid' : 'is-invalid');
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = isValid ? 'none' : 'block';
            }
            
            // Update progress
            updateProgress();
        }

        // Update form progress
        function updateProgress() {
            const validInputs = form.querySelectorAll('input.is-valid').length;
            const totalInputs = inputs.length;
            const progress = (validInputs / totalInputs) * 100;
            progressBar.style.width = progress + '%';
        }

        // Form submission with loading state
        form.addEventListener('submit', function(e) {
            // Validate all inputs
            let formIsValid = true;
            inputs.forEach(input => {
                validateInput({ target: input });
                if (input.classList.contains('is-invalid')) {
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
            showNotification('Adding medicine to inventory...', 'info');
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
            localStorage.setItem('medicineFormData', JSON.stringify(formData));
        }

        function loadFormData() {
            const savedData = localStorage.getItem('medicineFormData');
            if (savedData) {
                const formData = JSON.parse(savedData);
                Object.keys(formData).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && !input.value) {
                        input.value = formData[key];
                        validateInput({ target: input });
                    }
                });
            }
        }

        function clearFormData() {
            localStorage.removeItem('medicineFormData');
        }

        // Auto-save on input
        inputs.forEach(input => {
            input.addEventListener('input', saveFormData);
        });

        // Load saved data on page load
        window.addEventListener('load', () => {
            loadFormData();
            
            // Clear saved data if form was successfully submitted
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success')) {
                clearFormData();
            }
        });

        // Clear saved data on successful submission
        form.addEventListener('submit', () => {
            setTimeout(clearFormData, 1000);
        });

        // Medicine name suggestions (common medicines)
        const commonMedicines = [
            'Paracetamol', 'Ibuprofen', 'Aspirin', 'Amoxicillin', 'Cetirizine',
            'Omeprazole', 'Metformin', 'Atorvastatin', 'Amlodipine', 'Simvastatin',
            'Lisinopril', 'Levothyroxine', 'Azithromycin', 'Hydrochlorothiazide',
            'Gabapentin', 'Clopidogrel', 'Fluticasone', 'Tramadol', 'Sertraline',
            'Montelukast'
        ];

        // Add autocomplete functionality
        const nameInput = form.querySelector('[name="name"]');
        let suggestionBox = null;

        nameInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const matches = commonMedicines.filter(med => 
                med.toLowerCase().includes(query) && query.length > 1
            );

            // Remove existing suggestion box
            if (suggestionBox) {
                suggestionBox.remove();
                suggestionBox = null;
            }

            if (matches.length > 0 && query.length > 1) {
                suggestionBox = document.createElement('div');
                suggestionBox.className = 'suggestion-box';
                suggestionBox.style.cssText = `
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid var(--gray-200);
                    border-top: none;
                    border-radius: 0 0 8px 8px;
                    max-height: 200px;
                    overflow-y: auto;
                    z-index: 1000;
                    box-shadow: var(--shadow-md);
                `;

                matches.slice(0, 5).forEach(medicine => {
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.style.cssText = `
                        padding: 0.75rem 1rem;
                        cursor: pointer;
                        border-bottom: 1px solid var(--gray-100);
                        transition: background-color 0.2s ease;
                    `;
                    item.textContent = medicine;
                    
                    item.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = 'var(--gray-100)';
                    });
                    
                    item.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = 'transparent';
                    });
                    
                    item.addEventListener('click', function() {
                        nameInput.value = medicine;
                        suggestionBox.remove();
                        suggestionBox = null;
                        validateInput({ target: nameInput });
                        nameInput.focus();
                    });
                    
                    suggestionBox.appendChild(item);
                });

                nameInput.parentNode.parentNode.style.position = 'relative';
                nameInput.parentNode.parentNode.appendChild(suggestionBox);
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!nameInput.contains(e.target) && suggestionBox && !suggestionBox.contains(e.target)) {
                suggestionBox.remove();
                suggestionBox = null;
            }
        });

        // Batch number generator
        const batchInput = form.querySelector('[name="batch_no"]');
        const generateBatchBtn = document.createElement('button');
        generateBatchBtn.type = 'button';
        generateBatchBtn.className = 'btn btn-outline-secondary btn-sm';
        generateBatchBtn.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        `;
        generateBatchBtn.innerHTML = '<i class="fas fa-random"></i>';
        generateBatchBtn.title = 'Generate batch number';

        generateBatchBtn.addEventListener('click', function() {
            const today = new Date();
            const year = today.getFullYear().toString().substr(-2);
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const random = Math.random().toString(36).substr(2, 4).toUpperCase();
            const batchNumber = `${year}${month}${random}`;
            
            batchInput.value = batchNumber;
            validateInput({ target: batchInput });
            showNotification('Batch number generated successfully!', 'success');
        });

        batchInput.parentNode.style.position = 'relative';
        batchInput.parentNode.appendChild(generateBatchBtn);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                form.submit();
            }
            
            // Escape to cancel
            if (e.key === 'Escape') {
                e.preventDefault();
                if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                    window.location.href = 'pharmacy_dashboard.php';
                }
            }
            
            // Ctrl + G to generate batch number
            if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                e.preventDefault();
                generateBatchBtn.click();
            }
        });

        // Add keyboard shortcut hints
        const keyboardHints = document.createElement('div');
        keyboardHints.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.75rem;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        keyboardHints.innerHTML = `
            <div><kbd>Ctrl</kbd> + <kbd>Enter</kbd> Submit form</div>
            <div><kbd>Ctrl</kbd> + <kbd>G</kbd> Generate batch</div>
            <div><kbd>Esc</kbd> Cancel</div>
        `;
        document.body.appendChild(keyboardHints);

        // Show keyboard hints on Alt key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Alt') {
                keyboardHints.style.opacity = '1';
            }
        });

        document.addEventListener('keyup', function(e) {
            if (e.key === 'Alt') {
                keyboardHints.style.opacity = '0';
            }
        });

        // Price formatting
        const priceInput = form.querySelector('[name="price"]');
        priceInput.addEventListener('blur', function() {
            if (this.value) {
                const price = parseFloat(this.value);
                if (!isNaN(price)) {
                    this.value = price.toFixed(2);
                }
            }
        });

        // Quantity step increment/decrement
        const quantityInput = form.querySelector('[name="quantity"]');
        const stepButtons = document.createElement('div');
        stepButtons.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            display: flex;
            flex-direction: column;
        `;
        
        const incrementBtn = document.createElement('button');
        incrementBtn.type = 'button';
        incrementBtn.innerHTML = '▲';
        incrementBtn.style.cssText = `
            background: var(--gray-200);
            border: none;
            padding: 2px 6px;
            font-size: 0.6rem;
            cursor: pointer;
            border-radius: 2px 2px 0 0;
        `;
        
        const decrementBtn = document.createElement('button');
        decrementBtn.type = 'button';
        decrementBtn.innerHTML = '▼';
        decrementBtn.style.cssText = `
            background: var(--gray-200);
            border: none;
            padding: 2px 6px;
            font-size: 0.6rem;
            cursor: pointer;
            border-radius: 0 0 2px 2px;
        `;

        incrementBtn.addEventListener('click', function() {
            const current = parseInt(quantityInput.value) || 0;
            quantityInput.value = current + 1;
            validateInput({ target: quantityInput });
        });

        decrementBtn.addEventListener('click', function() {
            const current = parseInt(quantityInput.value) || 0;
            if (current > 0) {
                quantityInput.value = current - 1;
                validateInput({ target: quantityInput });
            }
        });

        stepButtons.appendChild(incrementBtn);
        stepButtons.appendChild(decrementBtn);
        quantityInput.parentNode.style.position = 'relative';
        quantityInput.parentNode.appendChild(stepButtons);

        // Form completion celebration
        function celebrateCompletion() {
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

        // Check if form was successfully submitted and celebrate
        if (window.location.search.includes('success') || document.querySelector('.alert-success')) {
            setTimeout(celebrateCompletion, 500);
        }

        // Initialize progress on page load
        window.addEventListener('load', () => {
            setTimeout(updateProgress, 100);
        });
    </script>
</body>
</html>