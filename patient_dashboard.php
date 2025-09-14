<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'patient') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch patient's reservations
$result = $conn->query("SELECT r.reservation_id, r.quantity, r.status, m.name, p.pharmacy_name, p.address 
                        FROM reservations r 
                        JOIN medicines m ON r.medicine_id = m.medicine_id 
                        JOIN pharmacies p ON m.pharmacy_id = p.pharmacy_id 
                        WHERE r.user_id = $user_id");
if ($result === false) {
    $reservation_error = "Error fetching reservations: " . $conn->error;
}

// Count reservations by status
$total_reservations = 0;
$active_reservations = 0;
$cancelled_reservations = 0;

if ($result && $result->num_rows > 0) {
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
        $total_reservations++;
        if ($row['status'] == 'cancelled') {
            $cancelled_reservations++;
        } else {
            $active_reservations++;
        }
    }
    $result = $reservations; // Reassign after fetching
} else {
    $result = []; // Ensure $result is an array if no rows
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Medicine Tracker</title>
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
            --sidebar-width: 280px;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
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

        .offcanvas {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--gray-200);
            box-shadow: var(--shadow-lg);
        }

        .offcanvas-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }

        .offcanvas-title {
            font-weight: 600;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .offcanvas-title::before {
            content: '👤';
            font-size: 1.5rem;
        }

        .offcanvas-body {
            padding: 1.5rem;
        }

        .nav-link {
            color: var(--gray-600);
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .content {
            flex-grow: 1;
            padding: 2rem;
            background: var(--light-color);
            min-height: 100vh;
        }

        .top-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            padding: 1rem 1.5rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
        }

        .menu-toggle {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            color: white;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .menu-toggle:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .modern-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--gray-100), #ffffff);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h3 {
            margin: 0;
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .search-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .search-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .search-header h2 {
            color: var(--gray-800);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .search-header p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        .search-form {
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input-group {
            position: relative;
            display: flex;
            align-items: center;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .search-input-group:focus-within {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1), var(--shadow-md);
        }

        .search-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            outline: none;
            font-size: 1rem;
            background: transparent;
        }

        .search-input::placeholder {
            color: var(--gray-600);
        }

        .search-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), #3730a3);
            transform: translateX(-2px);
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            display: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff, var(--gray-100));
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:nth-child(1)::before { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-card:nth-child(2)::before { background: linear-gradient(135deg, var(--success-color), #22c55e); }
        .stat-card:nth-child(3)::before { background: linear-gradient(135deg, var(--danger-color), #f87171); }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.8;
        }

        .stat-card:nth-child(1) .stat-icon { color: var(--primary-color); }
        .stat-card:nth-child(2) .stat-icon { color: var(--success-color); }
        .stat-card:nth-child(3) .stat-icon { color: var(--danger-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .modern-table {
            margin: 0;
            width: 100%;
        }

        .modern-table thead th {
            background: linear-gradient(135deg, var(--gray-100), #ffffff);
            color: var(--gray-800);
            font-weight: 600;
            padding: 1rem;
            border: none;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .modern-table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .modern-table tbody tr {
            transition: all 0.2s ease;
        }

        .modern-table tbody tr:hover {
            background: rgba(79, 70, 229, 0.05);
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-confirmed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-modern {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-danger-modern {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-danger-modern:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
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

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h4 {
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }
            
            .search-section {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .modern-table {
                font-size: 0.875rem;
            }
            
            .modern-table thead th,
            .modern-table tbody td {
                padding: 0.75rem 0.5rem;
            }
            
            .search-input-group {
                flex-direction: column;
            }
            
            .search-btn {
                width: 100%;
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

        .modern-card, .search-section {
            animation: fadeInUp 0.6s ease forwards;
        }

        .modern-card:nth-child(1) { animation-delay: 0.1s; }
        .modern-card:nth-child(2) { animation-delay: 0.2s; }
        .modern-card:nth-child(3) { animation-delay: 0.3s; }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-600);
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
        
        .dark-mode .modern-card,
        .dark-mode .top-nav,
        .dark-mode .search-section,
        .dark-mode .table-container {
            background: rgba(30, 41, 59, 0.95);
            border-color: var(--gray-200);
        }
        
        .dark-mode .card-header {
            background: linear-gradient(135deg, var(--gray-200), #334155);
        }
        
        .dark-mode .stat-card {
            background: linear-gradient(135deg, #334155, var(--gray-200));
        }
        
        .dark-mode .modern-table tbody tr:hover {
            background: rgba(79, 70, 229, 0.1);
        }
        
        .dark-mode .search-input-group {
            background: var(--gray-200);
        }
    </style>
</head>
<body>
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="sidebarLabel">Patient Panel</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="#overview">
                        <i class="fas fa-tachometer-alt"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#search">
                        <i class="fas fa-search"></i> Search Medicine
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#reservations">
                        <i class="fas fa-calendar-check"></i> My Reservations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#account-deletion">
                        <i class="fas fa-user-slash"></i> Account Deletion
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="content">
        <nav class="top-nav d-flex justify-content-between align-items-center">
            <button class="menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                <i class="fas fa-bars"></i> Menu
            </button>
            <a class="navbar-brand" href="index.html">
                <i class="fas fa-heartbeat"></i> MediLink
            </a>
            <button class="btn btn-outline-secondary btn-sm" id="darkModeToggle" title="Toggle Dark Mode">
                <i class="fas fa-moon"></i>
            </button>
        </nav>

        <!-- Alerts -->
        <?php if (isset($_SESSION['reservation_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['reservation_success']; ?>
            </div>
            <?php unset($_SESSION['reservation_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['reservation_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['reservation_error']; ?>
            </div>
            <?php unset($_SESSION['reservation_error']); ?>
        <?php endif; ?>
        
        <?php if (isset($reservation_error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $reservation_error; ?>
            </div>
        <?php endif; ?>

        <!-- Alerts for deletion request feedback -->
        <?php if (isset($_SESSION['deletion_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['deletion_success']; ?>
            </div>
            <?php unset($_SESSION['deletion_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['deletion_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['deletion_error']; ?>
            </div>
            <?php unset($_SESSION['deletion_error']); ?>
        <?php endif; ?>

        <!-- Overview Section -->
        <div id="overview" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Your Overview</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_reservations; ?></div>
                        <div class="stat-label">Total Reservations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $active_reservations; ?></div>
                        <div class="stat-label">Active Reservations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $cancelled_reservations; ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div id="search" class="search-section">
            <div class="search-header">
                <h2><i class="fas fa-search me-2"></i>Find Your Medicine</h2>
                <p>Search for medicines across all registered pharmacies</p>
            </div>
            <form action="search.php" method="GET" class="search-form">
                <div class="search-input-group">
                    <input type="text" name="medicine" class="search-input" placeholder="Enter medicine name..." required>
                    <button class="search-btn" type="submit">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>

            <!-- Image Upload Feature -->
            <div class="search-header mt-4">
                <h2><i class="fas fa-image me-2"></i>Or Upload Medicine Box Image</h2>
                <p>Upload a quality photo of the medicine box to automatically search the medicine</p>
            </div>
            <form action="process_image_search.php" method="POST" enctype="multipart/form-data" class="search-form">
                <div class="search-input-group">
                    <input type="file" name="medicine_image" class="search-input" accept="image/jpeg, image/png, image/gif" required>
                    <button class="search-btn" type="submit">
                        <i class="fas fa-upload me-2"></i>Upload Image
                    </button>
                </div>
                <img id="imagePreview" class="image-preview" alt="Image Preview">
            </form>
        </div>

        <!-- Reservations Section -->
        <div id="reservations" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> My Reservations (<?php echo $total_reservations; ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($result) && is_array($result) && count($result) > 0): ?>
                    <div class="table-container">
                        <table class="modern-table table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Pharmacy</th>
                                    <th>Address</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['pharmacy_name']); ?></td>
                                        <td>
                                            <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                            <?php echo htmlspecialchars($row['address']); ?>
                                        </td>
                                        <td><?php echo $row['quantity']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] != 'cancelled'): ?>
                                                <form action="cancel_reservation.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this reservation?');" class="d-inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $row['reservation_id']; ?>">
                                                    <button type="submit" class="btn-modern btn-danger-modern">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-ban me-1"></i>Cancelled
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No Reservations Yet</h4>
                        <p>You haven't made any medicine reservations. Use the search above to find and reserve medicines.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deletion Section -->
        <div id="account-deletion" class="modern-card">
        <div class="card-header">
            <h3><i class="fas fa-user-slash"></i> Account Deletion</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">Request to permanently delete your account. This action will schedule your account for deletion and cannot be undone once processed.</p>
            <button class="btn-modern btn-danger-modern" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                <i class="fas fa-trash-alt"></i> Request Account Deletion
            </button>
        </div>
        </div>

        <!-- Modal for Account Deletion Confirmation -->
        <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteAccountModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Confirm Account Deletion
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to request account deletion? This will schedule your account for permanent deletion, and all associated data will be removed after processing.</p>
                        <p class="text-muted mb-0">You will be notified once the request is processed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <form action="delete_account_request.php" method="POST">
                            <button type="submit" class="btn btn-danger" id="confirmDeleteAccount">
                                <i class="fas fa-trash-alt me-2"></i>Request Deletion
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                if (link.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const target = document.querySelector(link.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                        // Close sidebar on mobile
                        const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('sidebar'));
                        if (offcanvas) {
                            offcanvas.hide();
                        }
                    }
                }
            });
        });

        // Add counter animation
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                let current = 0;
                const increment = target / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 50);
            });
        }

        // Trigger counter animation when page loads
        window.addEventListener('load', () => {
            setTimeout(animateCounters, 500);
        });

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

        // Add hover effects to table rows
        document.querySelectorAll('.modern-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Enhanced form validation and loading states
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button && button.classList.contains('btn-danger-modern')) {
                    // Add loading state to cancel buttons
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
                    button.disabled = true;
                } else if (button && button.classList.contains('search-btn')) {
                    // Add loading state to search button
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Searching...';
                    button.disabled = true;
                }
            });
        });

        // Add notification system
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

        // Add scroll indicator for tables on mobile
        function addScrollIndicator() {
            const tableContainers = document.querySelectorAll('.table-container');
            tableContainers.forEach(container => {
                if (container.scrollWidth > container.clientWidth) {
                    container.classList.add('scrollable');
                    if (!container.querySelector('.scroll-indicator')) {
                        const indicator = document.createElement('div');
                        indicator.className = 'scroll-indicator';
                        indicator.innerHTML = '<i class="fas fa-arrows-alt-h"></i> Scroll to see more';
                        container.appendChild(indicator);
                    }
                }
            });
        }

        // Add CSS for scroll indicator
        const scrollIndicatorStyle = document.createElement('style');
        scrollIndicatorStyle.textContent = `
            .table-container.scrollable {
                position: relative;
            }
            .scroll-indicator {
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(79, 70, 229, 0.9);
                color: white;
                padding: 0.5rem;
                border-radius: 6px;
                font-size: 0.75rem;
                z-index: 10;
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0%, 100% { opacity: 0.7; }
                50% { opacity: 1; }
            }
            @media (min-width: 768px) {
                .scroll-indicator { display: none; }
            }
        `;
        document.head.appendChild(scrollIndicatorStyle);

        // Check for scroll indicators on load and resize
        window.addEventListener('load', addScrollIndicator);
        window.addEventListener('resize', addScrollIndicator);

        // Add search input enhancements
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            // Add search suggestions (you can populate this from your database)
            const suggestions = ['Paracetamol', 'Ibuprofen', 'Aspirin', 'Amoxicillin', 'Vitamin C'];
            
            searchInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                // You can implement autocomplete functionality here
                if (value.length >= 2) {
                    // Show suggestions
                    console.log('Searching for:', value);
                }
            });

            // Add enter key support
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.closest('form').submit();
                }
            });
        }

        // Add status badge animations
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
            });
            
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Enhanced confirmation for cancel actions
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Create custom confirmation modal
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Cancel Reservation
                                </h5>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to cancel this medicine reservation?</p>
                                <p class="text-muted mb-0">This action cannot be undone.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Keep Reservation
                                </button>
                                <button type="button" class="btn btn-danger" id="confirmCancel">
                                    <i class="fas fa-ban me-2"></i>Cancel Reservation
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
                
                modal.querySelector('#confirmCancel').addEventListener('click', () => {
                    bsModal.hide();
                    modal.remove();
                    // Remove onsubmit to prevent recursion
                    this.removeAttribute('onsubmit');
                    this.submit();
                });
                
                modal.addEventListener('hidden.bs.modal', () => {
                    modal.remove();
                });
            });
        });

        // Add welcome animation
        function showWelcomeMessage() {
            const userName = '<?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Patient'; ?>';
            if (userName !== 'Patient') {
                showNotification(`Welcome back, ${userName}! 👋`, 'success');
            }
        }

        // Show welcome message after page loads
        window.addEventListener('load', () => {
            setTimeout(showWelcomeMessage, 1000);
        });

        // Add real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour12: true });
            const dateString = now.toLocaleDateString('en-US', { timeZone: 'Asia/Kolkata' });
            
            console.log(`Current time: ${timeString} on ${dateString}`);
        }

        // Update clock every second
        setInterval(updateClock, 1000);

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + S for search focus
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                const searchInput = document.querySelector('.search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Alt + M for menu toggle
            if (e.altKey && e.key === 'm') {
                e.preventDefault();
                const menuButton = document.querySelector('.menu-toggle');
                if (menuButton) {
                    menuButton.click();
                }
            }
            
            // Alt + D for dark mode toggle
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                darkModeToggle.click();
            }
        });

        // Add tooltips for keyboard shortcuts
        const keyboardShortcutsStyle = document.createElement('style');
        keyboardShortcutsStyle.textContent = `
            .keyboard-shortcut {
                position: relative;
            }
            .keyboard-shortcut::after {
                content: attr(data-shortcut);
                position: absolute;
                top: -25px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--gray-800);
                color: white;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 0.7rem;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
                white-space: nowrap;
            }
            .keyboard-shortcut:hover::after {
                opacity: 1;
            }
        `;
        document.head.appendChild(keyboardShortcutsStyle);

        // Add keyboard shortcut hints
        document.querySelector('.search-input')?.setAttribute('data-shortcut', 'Alt+S');
        document.querySelector('.menu-toggle')?.setAttribute('data-shortcut', 'Alt+M');
        document.querySelector('#darkModeToggle')?.setAttribute('data-shortcut', 'Alt+D');
        
        document.querySelector('.search-input')?.classList.add('keyboard-shortcut');
        document.querySelector('.menu-toggle')?.classList.add('keyboard-shortcut');
        document.querySelector('#darkModeToggle')?.classList.add('keyboard-shortcut');

        document.querySelectorAll('#deleteAccountModal form').forEach(form => {
        form.addEventListener('submit', function(e) {
        const button = this.querySelector('button[type="submit"]');
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
        button.disabled = true;
    });
});

        // Image Preview for Upload
        const imageInput = document.querySelector('input[name="medicine_image"]');
        const imagePreview = document.getElementById('imagePreview');
        if (imageInput && imagePreview) {
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.style.display = 'none';
                }
            });
        }

    </script>
</body>
</html>