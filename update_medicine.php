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
$updated_medicine_id = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $medicine_id = $_POST['medicine_id'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];

    // Validate inputs
    if (!is_numeric($quantity) || $quantity < 0 || !is_numeric($price) || $price < 0) {
        $error_message = 'Invalid quantity or price. Please enter valid positive numbers.';
    } else {
        // Get medicine name for success message
        $name_stmt = $conn->prepare("SELECT name FROM medicines WHERE medicine_id = ? AND pharmacy_id = ?");
        $name_stmt->bind_param("ii", $medicine_id, $pharmacy_id);
        $name_stmt->execute();
        $name_stmt->bind_result($medicine_name);
        $name_stmt->fetch();
        $name_stmt->close();

        $stmt_u = $conn->prepare("UPDATE medicines SET quantity = ?, price = ? WHERE medicine_id = ? AND pharmacy_id = ?");
        $stmt_u->bind_param("idii", $quantity, $price, $medicine_id, $pharmacy_id);
        if ($stmt_u->execute()) {
            $success_message = "Medicine '$medicine_name' updated successfully!";
            $updated_medicine_id = $medicine_id;
        } else {
            $error_message = 'Error updating medicine: ' . $conn->error;
        }
        $stmt_u->close();
    }
}

$result = $conn->query("SELECT * FROM medicines WHERE pharmacy_id = $pharmacy_id ORDER BY name");
if ($result === false) {
    $error_message = 'Error fetching medicines: ' . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Medicine - Medicine Tracker</title>
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
            max-width: 1200px;
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

        .medicines-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .medicine-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease;
        }

        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .medicine-card.updated {
            border-color: var(--success-color);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1), var(--shadow-lg);
            animation: successPulse 2s ease;
        }

        .medicine-header {
            background: linear-gradient(135deg, var(--gray-100), #ffffff);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .medicine-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .medicine-info {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .medicine-info span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .medicine-body {
            padding: 1.5rem;
        }

        .update-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
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
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }

        .form-control.is-valid {
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .stock-indicator {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stock-high {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stock-medium {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stock-low {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .btn-update {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
        }

        .btn-update:hover {
            background: linear-gradient(135deg, var(--primary-dark), #3730a3);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-update:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            background: linear-gradient(135deg, var(--gray-600), var(--gray-700));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .btn-back:hover {
            background: linear-gradient(135deg, var(--gray-700), var(--gray-800));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            animation: fadeInUp 0.6s ease;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-600);
            opacity: 0.3;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--gray-800);
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--gray-600);
            margin-bottom: 2rem;
        }

        .search-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            animation: fadeInUp 0.6s ease 0.1s both;
        }

        .search-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 0 1rem;
            }
            
            .medicines-container {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .medicine-info {
                flex-direction: column;
                gap: 0.5rem;
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

        @keyframes successPulse {
            0%, 100% { 
                box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1), var(--shadow-lg);
            }
            50% { 
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0.2), var(--shadow-xl);
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
        
        .dark-mode .medicine-card,
        .dark-mode .top-nav,
        .dark-mode .search-section,
        .dark-mode .empty-state {
            background: rgba(30, 41, 59, 0.95);
            border-color: var(--gray-200);
        }
        
        .dark-mode .medicine-header {
            background: linear-gradient(135deg, var(--gray-200), #334155);
        }
        
        .dark-mode .form-control,
        .dark-mode .search-input {
            background: rgba(51, 65, 85, 0.8);
            border-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .dark-mode .form-control:focus,
        .dark-mode .search-input:focus {
            background: rgba(51, 65, 85, 0.95);
        }

        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
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
                <i class="fas fa-edit me-3"></i>Update Medicines
            </h1>
            <p class="page-subtitle">Modify quantities and prices for your pharmacy inventory</p>
        </div>

        <!-- Back Button -->
        <a href="pharmacy_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

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

        <!-- Search Section -->
        <div class="search-section">
            <div style="position: relative;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search medicines by name...">
            </div>
        </div>

        <!-- Medicines Container -->
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="medicines-container" id="medicinesContainer">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="medicine-card <?php echo ($updated_medicine_id == $row['medicine_id']) ? 'updated' : ''; ?>" data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>">
                        <div class="medicine-header">
                            <h3 class="medicine-name">
                                <i class="fas fa-pills"></i>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </h3>
                            <div class="medicine-info">
                                <span>
                                    <i class="fas fa-barcode"></i>
                                    Batch: <code><?php echo htmlspecialchars($row['batch_no']); ?></code>
                                </span>
                                <span>
                                    <i class="fas fa-calendar-alt"></i>
                                    Expires: <?php echo $row['expiry_date']; ?>
                                </span>
                                <span class="stock-indicator stock-<?php 
                                    echo $row['quantity'] > 10 ? 'high' : ($row['quantity'] > 5 ? 'medium' : 'low'); 
                                ?>">
                                    <i class="fas fa-cubes"></i>
                                    Stock: <?php echo $row['quantity']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="medicine-body">
                            <form method="POST" class="update-form" data-medicine-id="<?php echo $row['medicine_id']; ?>">
                                <input type="hidden" name="medicine_id" value="<?php echo $row['medicine_id']; ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-cubes me-1"></i>Quantity
                                        </label>
                                        <input 
                                            type="number" 
                                            name="quantity" 
                                            value="<?php echo $row['quantity']; ?>" 
                                            class="form-control" 
                                            required 
                                            min="0"
                                            step="1"
                                        >
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-dollar-sign me-1"></i>Price (LKR)
                                        </label>
                                        <input 
                                            type="number" 
                                            name="price" 
                                            value="<?php echo $row['price']; ?>" 
                                            class="form-control" 
                                            required 
                                            min="0" 
                                            step="0.01"
                                        >
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn-update">
                                    <i class="fas fa-save"></i>
                                    Update Medicine
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <h3>No Medicines Found</h3>
                <p>You don't have any medicines in your inventory to update.</p>
                <a href="add_medicine.php" class="btn-update" style="display: inline-flex; width: auto;">
                    <i class="fas fa-plus-circle"></i>
                    Add Your First Medicine
                </a>
            </div>
        <?php endif; ?>
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

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const medicinesContainer = document.getElementById('medicinesContainer');
        const medicineCards = document.querySelectorAll('.medicine-card');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            medicineCards.forEach(card => {
                const medicineName = card.getAttribute('data-name');
                if (medicineName.includes(searchTerm)) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeInUp 0.3s ease';
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            const visibleCards = Array.from(medicineCards).filter(card => 
                card.style.display !== 'none'
            );
            
            let noResultsMsg = document.getElementById('noResultsMsg');
            if (visibleCards.length === 0 && searchTerm) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.className = 'empty-state';
                    noResultsMsg.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>No medicines found</h3>
                        <p>No medicines match your search criteria.</p>
                    `;
                    medicinesContainer.appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });

        // Form validation and submission
        const forms = document.querySelectorAll('.update-form');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input[type="number"]');
            const submitBtn = form.querySelector('.btn-update');
            
            // Real-time validation
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateInput(this);
                    updateButtonState(form);
                });
                
                input.addEventListener('blur', function() {
                    validateInput(this);
                });
            });
            
            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                let formIsValid = true;
                
                inputs.forEach(input => {
                    if (!validateInput(input)) {
                        formIsValid = false;
                    }
                });
                
                if (!formIsValid) {
                    e.preventDefault();
                    showNotification('Please fix the errors before updating.', 'danger');
                    return;
                }
                
                // Add loading state
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span style="opacity: 0;">Update Medicine</span>';
                
                // Show loading notification
                showNotification('Updating medicine...', 'info');
            });
        });

        function validateInput(input) {
            const value = parseFloat(input.value);
            let isValid = true;
            
            // Remove previous validation classes
            input.classList.remove('is-valid', 'is-invalid');
            
            if (input.value.trim() === '' || isNaN(value) || value < 0) {
                isValid = false;
            } else if (input.name === 'quantity' && !Number.isInteger(value)) {
                isValid = false;
            }
            
            // Apply validation classes
            input.classList.add(isValid ? 'is-valid' : 'is-invalid');
            
            return isValid;
        }

        function updateButtonState(form) {
            const inputs = form.querySelectorAll('input[type="number"]');
            const submitBtn = form.querySelector('.btn-update');
            const allValid = Array.from(inputs).every(input => input.classList.contains('is-valid'));
            
            submitBtn.disabled = !allValid;
            submitBtn.style.opacity = allValid ? '1' : '0.7';
        }

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

        // Price formatting
        document.querySelectorAll('input[name="price"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    const price = parseFloat(this.value);
                    if (!isNaN(price)) {
                        this.value = price.toFixed(2);
                    }
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit focused form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                const focusedElement = document.activeElement;
                const form = focusedElement.closest('form');
                if (form) {
                    form.submit();
                }
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                e.preventDefault();
                if (searchInput.value) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input'));
                    searchInput.blur();
                }
            }
            
            // Ctrl + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
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
            <div><kbd>Ctrl</kbd> + <kbd>F</kbd> Search medicines</div>
            <div><kbd>Esc</kbd> Clear search</div>
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

        // Auto-save changes (draft mode)
        function saveChanges() {
            const changes = {};
            forms.forEach(form => {
                const medicineId = form.querySelector('input[name="medicine_id"]').value;
                const quantity = form.querySelector('input[name="quantity"]').value;
                const price = form.querySelector('input[name="price"]').value;
                
                changes[medicineId] = { quantity, price };
            });
            
            localStorage.setItem('medicineUpdateChanges', JSON.stringify(changes));
        }

        function loadSavedChanges() {
            const savedChanges = localStorage.getItem('medicineUpdateChanges');
            if (savedChanges) {
                const changes = JSON.parse(savedChanges);
                
                Object.keys(changes).forEach(medicineId => {
                    const form = document.querySelector(`form[data-medicine-id="${medicineId}"]`);
                    if (form) {
                        const quantityInput = form.querySelector('input[name="quantity"]');
                        const priceInput = form.querySelector('input[name="price"]');
                        
                        if (quantityInput && changes[medicineId].quantity) {
                            quantityInput.value = changes[medicineId].quantity;
                            validateInput(quantityInput);
                        }
                        
                        if (priceInput && changes[medicineId].price) {
                            priceInput.value = changes[medicineId].price;
                            validateInput(priceInput);
                        }
                        
                        updateButtonState(form);
                    }
                });
            }
        }

        function clearSavedChanges() {
            localStorage.removeItem('medicineUpdateChanges');
        }

        // Auto-save on input change
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', saveChanges);
        });

        // Load saved changes on page load
        window.addEventListener('load', () => {
            loadSavedChanges();
            
            // Clear saved changes if update was successful
            if (document.querySelector('.alert-success')) {
                clearSavedChanges();
            }
        });

        // Add bulk update functionality
        const bulkUpdateSection = document.createElement('div');
        bulkUpdateSection.className = 'search-section';
        bulkUpdateSection.style.marginTop = '2rem';
        bulkUpdateSection.innerHTML = `
            <h4 style="margin-bottom: 1rem; color: var(--gray-800);">
                <i class="fas fa-magic me-2"></i>Bulk Actions
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <button type="button" id="applyDiscountBtn" class="btn-update" style="background: linear-gradient(135deg, var(--warning-color), #f59e0b);">
                    <i class="fas fa-percentage"></i> Apply 10% Discount
                </button>
                <button type="button" id="increaseStockBtn" class="btn-update" style="background: linear-gradient(135deg, var(--success-color), #10b981);">
                    <i class="fas fa-plus"></i> Increase Stock by 10
                </button>
                <button type="button" id="resetChangesBtn" class="btn-update" style="background: linear-gradient(135deg, var(--gray-600), var(--gray-700));">
                    <i class="fas fa-undo"></i> Reset All Changes
                </button>
            </div>
        `;

        if (medicinesContainer) {
            medicinesContainer.parentNode.appendChild(bulkUpdateSection);
        }

        // Bulk action handlers
        document.getElementById('applyDiscountBtn').addEventListener('click', function() {
            if (confirm('Apply 10% discount to all medicines?')) {
                document.querySelectorAll('input[name="price"]').forEach(input => {
                    const currentPrice = parseFloat(input.value) || 0;
                    const discountedPrice = (currentPrice * 0.9).toFixed(2);
                    input.value = discountedPrice;
                    validateInput(input);
                    updateButtonState(input.closest('form'));
                });
                showNotification('10% discount applied to all medicines!', 'success');
                saveChanges();
            }
        });

        document.getElementById('increaseStockBtn').addEventListener('click', function() {
            if (confirm('Increase stock by 10 units for all medicines?')) {
                document.querySelectorAll('input[name="quantity"]').forEach(input => {
                    const currentQuantity = parseInt(input.value) || 0;
                    input.value = currentQuantity + 10;
                    validateInput(input);
                    updateButtonState(input.closest('form'));
                });
                showNotification('Stock increased by 10 units for all medicines!', 'success');
                saveChanges();
            }
        });

        document.getElementById('resetChangesBtn').addEventListener('click', function() {
            if (confirm('Reset all unsaved changes?')) {
                clearSavedChanges();
                location.reload();
            }
        });

        // Add change indicators
        document.querySelectorAll('input[type="number"]').forEach(input => {
            const originalValue = input.value;
            
            input.addEventListener('input', function() {
                const hasChanged = this.value !== originalValue;
                const form = this.closest('form');
                
                if (hasChanged) {
                    form.classList.add('has-changes');
                    if (!form.querySelector('.change-indicator')) {
                        const indicator = document.createElement('div');
                        indicator.className = 'change-indicator';
                        indicator.style.cssText = `
                            position: absolute;
                            top: -5px;
                            right: -5px;
                            width: 12px;
                            height: 12px;
                            background: var(--warning-color);
                            border-radius: 50%;
                            animation: pulse 2s infinite;
                        `;
                        form.style.position = 'relative';
                        form.appendChild(indicator);
                    }
                } else {
                    form.classList.remove('has-changes');
                    const indicator = form.querySelector('.change-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                }
            });
        });

        // Add pulse animation for change indicators
        const pulseStyle = document.createElement('style');
        pulseStyle.textContent = `
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
        `;
        document.head.appendChild(pulseStyle);

        // Initialize validation on page load
        window.addEventListener('load', () => {
            document.querySelectorAll('input[type="number"]').forEach(input => {
                validateInput(input);
            });
            
            forms.forEach(form => {
                updateButtonState(form);
            });
        });

        // Add success celebration for updated medicines
        if (document.querySelector('.medicine-card.updated')) {
            setTimeout(() => {
                showNotification('Medicine updated successfully! 🎉', 'success');
                
                // Remove updated class after animation
                setTimeout(() => {
                    document.querySelectorAll('.medicine-card.updated').forEach(card => {
                        card.classList.remove('updated');
                    });
                }, 3000);
            }, 500);
        }
    </script>
</body>
</html>