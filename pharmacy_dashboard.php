<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'pharmacy') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$stmt_ph = $conn->prepare("SELECT pharmacy_id FROM pharmacies WHERE user_id = ?");
if (!$stmt_ph) {
    die('<div class="alert alert-danger">Query preparation failed: ' . $conn->error . '</div>');
}
$stmt_ph->bind_param("i", $user_id);
if (!$stmt_ph->execute()) {
    die('<div class="alert alert-danger">Query execution failed: ' . $conn->error . '</div>');
}
$stmt_ph->bind_result($pharmacy_id);
if (!$stmt_ph->fetch()) {
    die('<div class="alert alert-danger">No pharmacy found for this user. Please ensure your pharmacy is registered correctly.</div>');
}
$stmt_ph->close();

// Get pharmacy stats
$medicines_result = $conn->query("SELECT * FROM medicines WHERE pharmacy_id = $pharmacy_id");
$total_medicines = $medicines_result ? $medicines_result->num_rows : 0;

$low_stock_result = $conn->query("SELECT * FROM medicines WHERE pharmacy_id = $pharmacy_id AND quantity <= 5");
$low_stock_count = $low_stock_result ? $low_stock_result->num_rows : 0;

$reservation_count_result = $conn->query("SELECT COUNT(*) as count FROM reservations r JOIN medicines m ON r.medicine_id = m.medicine_id WHERE m.pharmacy_id = $pharmacy_id");
$total_reservations = $reservation_count_result ? $reservation_count_result->fetch_assoc()['count'] : 0;

$pending_reservations_result = $conn->query("SELECT COUNT(*) as count FROM reservations r JOIN medicines m ON r.medicine_id = m.medicine_id WHERE m.pharmacy_id = $pharmacy_id AND r.status = 'pending'");
$pending_reservations = $pending_reservations_result ? $pending_reservations_result->fetch_assoc()['count'] : 0;

$alerts = [];
$result = $conn->query("SELECT * FROM medicines WHERE pharmacy_id = $pharmacy_id AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)");
if ($result === false) {
    die('<div class="alert alert-danger">Error fetching medicines: ' . $conn->error . '</div>');
}
while ($row = $result->fetch_assoc()) {
    $alerts[] = $row;
}

$reservation_result = $conn->query("SELECT r.reservation_id, r.quantity, r.status, m.name, u.name AS patient_name 
                                    FROM reservations r 
                                    JOIN medicines m ON r.medicine_id = m.medicine_id 
                                    JOIN users u ON r.user_id = u.user_id 
                                    WHERE m.pharmacy_id = $pharmacy_id ORDER BY r.reservation_id DESC");
if ($reservation_result === false) {
    $reservation_error = "Error fetching reservations: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - Medicine Tracker</title>
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

        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 0.875rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .action-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), #3730a3);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, var(--gray-600), var(--gray-700));
        }

        .action-btn.secondary:hover {
            background: linear-gradient(135deg, var(--gray-700), var(--gray-800));
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff, var(--gray-100));
            padding: 2rem;
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
        .stat-card:nth-child(2)::before { background: linear-gradient(135deg, var(--warning-color), #fbbf24); }
        .stat-card:nth-child(3)::before { background: linear-gradient(135deg, var(--success-color), #22c55e); }
        .stat-card:nth-child(4)::before { background: linear-gradient(135deg, var(--danger-color), #f87171); }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .stat-card:nth-child(1) .stat-icon { color: var(--primary-color); }
        .stat-card:nth-child(2) .stat-icon { color: var(--warning-color); }
        .stat-card:nth-child(3) .stat-icon { color: var(--success-color); }
        .stat-card:nth-child(4) .stat-icon { color: var(--danger-color); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .alert-section {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.05));
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-left: 4px solid var(--warning-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .alert-header h4 {
            color: var(--warning-color);
            font-weight: 600;
            margin: 0;
        }

        .alert-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .alert-list li {
            background: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }

        .alert-list li:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateX(5px);
        }

        .alert-list li::before {
            content: '⚠️';
            font-size: 1.2rem;
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

        .stock-indicator {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
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

        .status-select {
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .update-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .update-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), #3730a3);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
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
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .modern-table {
                font-size: 0.875rem;
            }
            
            .modern-table thead th,
            .modern-table tbody td {
                padding: 0.75rem 0.5rem;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .action-btn {
                justify-content: center;
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

        .modern-card, .alert-section {
            animation: fadeInUp 0.6s ease forwards;
        }

        .modern-card:nth-child(1) { animation-delay: 0.1s; }
        .modern-card:nth-child(2) { animation-delay: 0.2s; }
        .modern-card:nth-child(3) { animation-delay: 0.3s; }
        .modern-card:nth-child(4) { animation-delay: 0.4s; }

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
        
        .dark-mode .alert-section {
            background: rgba(30, 41, 59, 0.95);
        }
        
        .dark-mode .alert-list li {
            background: rgba(51, 65, 85, 0.7);
        }
        
        .dark-mode .status-select {
            background: var(--gray-200);
            color: var(--gray-700);
            border-color: var(--gray-300);
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
    </style>
</head>
<body>
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="sidebarLabel">Pharmacy Panel</h5>
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
                    <a class="nav-link" href="#medicines">
                        <i class="fas fa-pills"></i> Medicines
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#reservations">
                        <i class="fas fa-calendar-check"></i> Reservations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_medicine.php">
                        <i class="fas fa-plus-circle"></i> Add Medicine
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="update_medicine.php">
                        <i class="fas fa-edit"></i> Update Medicine
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
        <?php if (isset($_SESSION['reservation_update_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['reservation_update_success']; ?>
            </div>
            <?php unset($_SESSION['reservation_update_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['reservation_update_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['reservation_update_error']; ?>
            </div>
            <?php unset($_SESSION['reservation_update_error']); ?>
        <?php endif; ?>

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

        <!-- Quick Actions -->
        <div id="overview" class="quick-actions">
            <a href="add_medicine.php" class="action-btn">
                <i class="fas fa-plus-circle"></i> Add Medicine
            </a>
            <a href="update_medicine.php" class="action-btn secondary">
                <i class="fas fa-edit"></i> Update Medicine
            </a>
        </div>

        <!-- Overview Stats -->
        <div class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Pharmacy Overview</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_medicines; ?></div>
                        <div class="stat-label">Total Medicines</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-number"><?php echo $low_stock_count; ?></div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
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
                        <div class="stat-number"><?php echo $pending_reservations; ?></div>
                        <div class="stat-label">Pending Reservations</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiry Alerts -->
        <div class="alert-section">
            <div class="alert-header">
                <i class="fas fa-bell fa-2x"></i>
                <div>
                    <h4>Expiry Alerts</h4>
                    <p class="mb-0 text-muted">Medicines expiring within 15 days</p>
                </div>
            </div>
            <?php if (count($alerts) > 0): ?>
                <ul class="alert-list">
                    <?php foreach ($alerts as $alert): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($alert['name']); ?></strong>
                            <span class="text-muted">Batch: <?php echo htmlspecialchars($alert['batch_no']); ?></span>
                            <span class="ms-auto text-danger">Expires: <?php echo $alert['expiry_date']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state py-3">
                    <i class="fas fa-check-circle text-success"></i>
                    <p class="mb-0">No medicines expiring soon. Great job!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Medicines Section -->
        <div id="medicines" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-pills"></i> Your Medicines (<?php echo $total_medicines; ?>)</h3>
            </div>
            <div class="card-body">
                <?php
                // Reset medicines result for table display
                $medicines_result = $conn->query("SELECT * FROM medicines WHERE pharmacy_id = $pharmacy_id ORDER BY name");
                if ($medicines_result === false): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error fetching medicines: <?php echo $conn->error; ?>
                    </div>
                <?php elseif ($medicines_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="modern-table table">
                            <thead>
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Batch No</th>
                                    <th>Stock Quantity</th>
                                    <th>Expiry Date</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $medicines_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($row['batch_no']); ?></code>
                                        </td>
                                        <td>
                                            <span class="stock-indicator stock-<?php 
                                                echo $row['quantity'] > 10 ? 'high' : ($row['quantity'] > 5 ? 'medium' : 'low'); 
                                            ?>">
                                                <?php echo $row['quantity']; ?> units
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $expiry_date = new DateTime($row['expiry_date']);
                                            $today = new DateTime();
                                            $days_until_expiry = $today->diff($expiry_date)->days;
                                            $is_expired = $expiry_date < $today;
                                            $is_expiring_soon = !$is_expired && $days_until_expiry <= 15;
                                            ?>
                                            <span class="<?php echo $is_expired ? 'text-danger' : ($is_expiring_soon ? 'text-warning' : 'text-success'); ?>">
                                                <?php echo $row['expiry_date']; ?>
                                                <?php if ($is_expired): ?>
                                                    <i class="fas fa-exclamation-triangle ms-1" title="Expired"></i>
                                                <?php elseif ($is_expiring_soon): ?>
                                                    <i class="fas fa-clock ms-1" title="Expiring Soon"></i>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong>Rs. <?php echo number_format($row['price'], 2); ?></strong>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-pills"></i>
                        <h4>No Medicines Added</h4>
                        <p>Start by adding your first medicine to the inventory.</p>
                        <a href="add_medicine.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i> Add Your First Medicine
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reservations Section -->
        <div id="reservations" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Medicine Reservations (<?php echo $total_reservations; ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (isset($reservation_error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $reservation_error; ?>
                    </div>
                <?php elseif ($reservation_result && $reservation_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="modern-table table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Patient Name</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Update Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $reservation_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        </td>
                                        <td>
                                            <i class="fas fa-user me-2 text-muted"></i>
                                            <?php echo htmlspecialchars($row['patient_name']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $row['quantity']; ?> units</span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form action="update_reservation.php" method="POST" class="d-flex gap-2 align-items-center" style="min-width: 200px;">
                                                <input type="hidden" name="reservation_id" value="<?php echo $row['reservation_id']; ?>">
                                                <select name="status" class="status-select">
                                                    <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>
                                                        Pending
                                                    </option>
                                                    <option value="confirmed" <?php echo $row['status'] == 'confirmed' ? 'selected' : ''; ?>>
                                                        Confirmed
                                                    </option>
                                                    <option value="cancelled" <?php echo $row['status'] == 'cancelled' ? 'selected' : ''; ?>>
                                                        Cancelled
                                                    </option>
                                                </select>
                                                <button type="submit" class="update-btn">
                                                    <i class="fas fa-save me-1"></i>Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No Reservations Yet</h4>
                        <p>Patients haven't made any reservations for your medicines yet. Make sure your medicines are properly listed and available.</p>
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
            <p class="text-muted">Request to permanently delete your pharmacy account. This action will schedule your account for deletion and cannot be undone once processed.</p>
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
                        <p>Are you sure you want to request account deletion? This will schedule your pharmacy account for permanent deletion, including all associated data (medicines, reservations, etc.), and cannot be undone once processed.</p>
                        <p class="text-muted mb-0">You will be notified once the request is processed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <form id="deleteAccountForm" action="delete_account_request.php" method="POST">
                            <button type="submit" class="btn btn-danger" id="confirmDeleteAccount">
                                <i class="fas fa-trash-alt me-2"></i>Request Deletion
                            </button>
                        </form>
                    </div>
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

        // Enhanced form submission with loading states
        document.querySelectorAll('form[action="update_reservation.php"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('.update-btn');
                const originalContent = button.innerHTML;
                
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
                button.disabled = true;
                
                // Re-enable button after 3 seconds if form doesn't submit
                setTimeout(() => {
                    if (button.disabled) {
                        button.innerHTML = originalContent;
                        button.disabled = false;
                    }
                }, 3000);
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

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + A for add medicine
            if (e.altKey && e.key === 'a') {
                e.preventDefault();
                window.location.href = 'add_medicine.php';
            }
            
            // Alt + U for update medicine
            if (e.altKey && e.key === 'u') {
                e.preventDefault();
                window.location.href = 'update_medicine.php';
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
                z-index: 1000;
            }
            .keyboard-shortcut:hover::after {
                opacity: 1;
            }
        `;
        document.head.appendChild(keyboardShortcutsStyle);

        // Add keyboard shortcut hints
        document.querySelector('a[href="add_medicine.php"]')?.setAttribute('data-shortcut', 'Alt+A');
        document.querySelector('a[href="update_medicine.php"]')?.setAttribute('data-shortcut', 'Alt+U');
        document.querySelector('.menu-toggle')?.setAttribute('data-shortcut', 'Alt+M');
        document.querySelector('#darkModeToggle')?.setAttribute('data-shortcut', 'Alt+D');
        
        document.querySelector('a[href="add_medicine.php"]')?.classList.add('keyboard-shortcut');
        document.querySelector('a[href="update_medicine.php"]')?.classList.add('keyboard-shortcut');
        document.querySelector('.menu-toggle')?.classList.add('keyboard-shortcut');
        document.querySelector('#darkModeToggle')?.classList.add('keyboard-shortcut');

        // Add welcome message for pharmacy
        function showWelcomeMessage() {
            const userName = '<?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Pharmacy'; ?>';
            if (userName !== 'Pharmacy') {
                showNotification(`Welcome to your pharmacy dashboard, ${userName}! 🏥`, 'success');
            }
        }

        // Show welcome message after page loads
        window.addEventListener('load', () => {
            setTimeout(showWelcomeMessage, 1000);
        });

        // Auto-refresh reservation counts every 30 seconds
        let refreshInterval;
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                console.log('Auto-refreshing data...');
            }, 30000);
        }

        // Start auto-refresh when page loads
        window.addEventListener('load', startAutoRefresh);

        // Clear interval when page unloads
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });

        // Add enhanced status change confirmation
        document.querySelectorAll('.status-select').forEach(select => {
            let originalValue = select.value;
            
            select.addEventListener('change', function() {
                const newValue = this.value;
                const form = this.closest('form');
                const medicineName = this.closest('tr').querySelector('td:first-child strong').textContent;
                const patientName = this.closest('tr').querySelector('td:nth-child(2)').textContent.replace('👤', '').trim();
                
                if (newValue !== originalValue) {
                    const confirmation = confirm(
                        `Are you sure you want to change the reservation status to "${newValue.toUpperCase()}" for:\n\n` +
                        `Medicine: ${medicineName}\n` +
                        `Patient: ${patientName}\n\n` +
                        `Click OK to proceed or Cancel to revert.`
                    );
                    
                    if (!confirmation) {
                        this.value = originalValue;
                    } else {
                        originalValue = newValue;
                    }
                }
            });
        });

        // Add real-time stock level warnings
        document.querySelectorAll('.stock-indicator').forEach(indicator => {
            const stockText = indicator.textContent;
            const stockNumber = parseInt(stockText.match(/\d+/)[0]);
            
            if (stockNumber <= 5) {
                indicator.addEventListener('mouseenter', function() {
                    showNotification(`Low stock alert: Only ${stockNumber} units remaining!`, 'warning');
                });
            }
        });

        document.querySelectorAll('#deleteAccountModal form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            button.disabled = true;

            fetch('delete_account_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(new FormData(this))
            })
            .then(response => response.json().catch(() => ({})))
            .then(data => {
                if (data.success) {
                    window.location.href = 'login.php';
                } else {
                    showNotification(data.error || 'Error submitting deletion request', 'danger');
                    button.innerHTML = '<i class="fas fa-trash-alt me-2"></i>Request Deletion';
                    button.disabled = false;
                    bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal')).hide();
                }
            })
            .catch(error => {
                showNotification('Network error occurred', 'danger');
                button.innerHTML = '<i class="fas fa-trash-alt me-2"></i>Request Deletion';
                button.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal')).hide();
            });
        });
    });
    </script>
</body>
</html>