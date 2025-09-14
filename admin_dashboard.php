<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    redirect('login.php');
}

// Fetch counts
$pharmacies_count = $conn->query("SELECT COUNT(*) as count FROM pharmacies")->fetch_assoc()['count'];
$patients_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")->fetch_assoc()['count'];
$medicines_count = $conn->query("SELECT COUNT(*) as count FROM medicines")->fetch_assoc()['count'];
$reservations_count = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];

// Fetch pharmacies details
$pharmacies_result = $conn->query("SELECT p.pharmacy_id, p.pharmacy_name, p.address, p.phone, u.user_id, u.name AS user_name, u.email 
                                   FROM pharmacies p 
                                   JOIN users u ON p.user_id = u.user_id");

// Fetch patients details
$patients_result = $conn->query("SELECT user_id, name, email, location FROM users WHERE role = 'patient'");

// Fetch medicines details
$medicines_result = $conn->query("SELECT m.medicine_id, m.name, m.batch_no, m.quantity, m.expiry_date, m.price, p.pharmacy_name 
                                  FROM medicines m 
                                  JOIN pharmacies p ON m.pharmacy_id = p.pharmacy_id");

// Fetch reservations details
$reservations_result = $conn->query("SELECT r.reservation_id, r.quantity, r.status, m.name AS medicine_name, u.name AS patient_name, p.pharmacy_name 
                                     FROM reservations r 
                                     JOIN medicines m ON r.medicine_id = m.medicine_id 
                                     JOIN users u ON r.user_id = u.user_id 
                                     JOIN pharmacies p ON m.pharmacy_id = p.pharmacy_id");

$deletion_requests_count = $conn->query("SELECT COUNT(*) as count FROM account_deletion_requests")->fetch_assoc()['count'];
$deletion_requests_result = $conn->query("SELECT adr.id, adr.user_id, adr.request_date, adr.status, u.name, u.email 
                                         FROM account_deletion_requests adr 
                                         JOIN users u ON adr.user_id = u.user_id");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Medicine Tracker</title>
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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

        .modern-card {
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

        .btn-success-modern {
        background: linear-gradient(135deg, var(--success-color), #22c55e);
        color: white;
        box-shadow: var(--shadow-sm);
        }

        .btn-success-modern:hover {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: white;
        }
    </style>
</head>
<body>
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="sidebarLabel">Admin Panel</h5>
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
                    <a class="nav-link" href="#pharmacies">
                        <i class="fas fa-clinic-medical"></i> Pharmacies
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#patients">
                        <i class="fas fa-users"></i> Patients
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
                    <a class="nav-link" href="#deletion-requests">
                        <i class="fas fa-user-slash"></i> Deletion Requests
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
        </nav>

        <!-- Alerts -->
        <?php if (isset($_SESSION['delete_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['delete_success']; ?>
            </div>
            <?php unset($_SESSION['delete_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['delete_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['delete_error']; ?>
            </div>
            <?php unset($_SESSION['delete_error']); ?>
        <?php endif; ?>

        <!-- Overview Section -->
        <div id="overview" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> System Overview</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clinic-medical"></i>
                        </div>
                        <div class="stat-number"><?php echo $pharmacies_count; ?></div>
                        <div class="stat-label">Pharmacies</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo $patients_count; ?></div>
                        <div class="stat-label">Patients</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="stat-number"><?php echo $medicines_count; ?></div>
                        <div class="stat-label">Medicines</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-number"><?php echo $reservations_count; ?></div>
                        <div class="stat-label">Reservations</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pharmacies Section -->
        <div id="pharmacies" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-clinic-medical"></i> Pharmacies (<?php echo $pharmacies_count; ?>)</h3>
            </div>
            <div class="card-body">
                <?php if ($pharmacies_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="modern-table table">
                            <thead>
                                <tr>
                                    <th>Pharmacy Name</th>
                                    <th>Address</th>
                                    <th>Phone</th>
                                    <th>User Name</th>
                                    <th>Email</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $pharmacies_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['pharmacy_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td>
                                            <form action="delete_user.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this pharmacy? This will also delete related users, medicines, and reservations.');" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                <button type="submit" class="btn-modern btn-danger-modern">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted py-4">
                        <i class="fas fa-clinic-medical fa-3x mb-3 d-block opacity-25"></i>
                        No pharmacies registered yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Patients Section -->
        <div id="patients" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Patients (<?php echo $patients_count; ?>)</h3>
            </div>
            <div class="card-body">
                <?php if ($patients_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="modern-table table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $patients_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                                        <td>
                                            <form action="delete_user.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this patient? This will also delete related reservations.');" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                <button type="submit" class="btn-modern btn-danger-modern">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted py-4">
                        <i class="fas fa-users fa-3x mb-3 d-block opacity-25"></i>
                        No patients registered yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Medicines Section -->
        <div id="medicines" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-pills"></i> Medicines (<?php echo $medicines_count; ?>)</h3>
            </div>
            <div class="card-body">
                <?php if ($medicines_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="modern-table table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Batch No</th>
                                    <th>Quantity</th>
                                    <th>Expiry Date</th>
                                    <th>Price</th>
                                    <th>Pharmacy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $medicines_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['batch_no']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['quantity'] > 10 ? 'success' : ($row['quantity'] > 0 ? 'warning' : 'danger'); ?>">
                                                <?php echo $row['quantity']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['expiry_date']; ?></td>
                                        <td><strong>Rs. <?php echo number_format($row['price'], 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['pharmacy_name']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted py-4">
                        <i class="fas fa-pills fa-3x mb-3 d-block opacity-25"></i>
                        No medicines available yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reservations Section -->
        <div id="reservations" class="modern-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Reservations (<?php echo $reservations_count; ?>)</h3>
            </div>
            <div class="card-body">
                <?php if ($reservations_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="modern-table table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Patient</th>
                                    <th>Pharmacy</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $reservations_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['pharmacy_name']); ?></td>
                                        <td><?php echo $row['quantity']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $row['status'] == 'confirmed' ? 'success' : 
                                                    ($row['status'] == 'pending' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted py-4">
                        <i class="fas fa-calendar-check fa-3x mb-3 d-block opacity-25"></i>
                        No reservations found yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deletion Section -->
        <div id="deletion-requests" class="modern-card">
        <div class="card-header">
            <h3><i class="fas fa-user-slash"></i> Account Deletion Requests (<?php echo $deletion_requests_count; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if ($deletion_requests_result->num_rows > 0): ?>
                <div class="table-container">
                    <table class="modern-table table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $deletion_requests_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($row['request_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $row['status'] == 'pending' ? 'warning' : 
                                                ($row['status'] == 'processed' ? 'success' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] == 'pending'): ?>
                                            <form action="process_deletion_request.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to <?php echo $row['status'] == 'pending' ? 'process' : 'cancel'; ?> this deletion request?');">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="process">
                                                <button type="submit" class="btn-modern btn-success-modern">
                                                    <i class="fas fa-check"></i> Process
                                                </button>
                                            </form>
                                            <form action="process_deletion_request.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this deletion request?');">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn-modern btn-danger-modern">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-ban me-1"></i><?php echo ucfirst($row['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted py-4">
                    <i class="fas fa-user-slash fa-3x mb-3 d-block opacity-25"></i>
                    No account deletion requests found.
                </p>
            <?php endif; ?>
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

        // Add loading animation to delete buttons
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                    button.disabled = true;
                }
            });
        });

        // Add counter animation
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 20);
            });
        }

        // Trigger counter animation when page loads
        window.addEventListener('load', () => {
            setTimeout(animateCounters, 500);
        });

        // Add hover effects to table rows
        document.querySelectorAll('.modern-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

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

        // Add dark mode toggle 
        function addDarkModeToggle() {
            const toggle = document.createElement('button');
            toggle.className = 'btn btn-outline-secondary btn-sm ms-2';
            toggle.innerHTML = '<i class="fas fa-moon"></i>';
            toggle.title = 'Toggle Dark Mode';
            
            const navbar = document.querySelector('.top-nav');
            navbar.appendChild(toggle);
            
            toggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const icon = toggle.querySelector('i');
                if (document.body.classList.contains('dark-mode')) {
                    icon.className = 'fas fa-sun';
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    icon.className = 'fas fa-moon';
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
            
            // Load saved preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
                toggle.querySelector('i').className = 'fas fa-sun';
            }
        }

        // Add dark mode styles
        const darkModeStyle = document.createElement('style');
        darkModeStyle.textContent = `
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
        `;
        document.head.appendChild(darkModeStyle);

        // Initialize dark mode toggle
        addDarkModeToggle();

        // Add notification system for better UX
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                animation: slideInRight 0.3s ease;
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

        // Enhanced form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const confirmText = this.getAttribute('onsubmit');
                if (confirmText && confirmText.includes('confirm')) {
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
                                        Confirm Action
                                    </h5>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete this item? This action cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDelete">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(modal);
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                    
                    modal.querySelector('#confirmDelete').addEventListener('click', () => {
                        bsModal.hide();
                        modal.remove();
                        // Remove onsubmit to prevent recursion
                        this.removeAttribute('onsubmit');
                        this.submit();
                    });
                    
                    modal.addEventListener('hidden.bs.modal', () => {
                        modal.remove();
                    });
                }
            });
        });

        document.querySelectorAll('#deletion-requests form').forEach(form => {
        form.addEventListener('submit', function(e) {
        e.preventDefault();
        const button = this.querySelector('button[type="submit"]');
        const action = this.querySelector('input[name="action"]').value;
        const isProcess = action === 'process';

        // Create custom confirmation modal
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-${isProcess ? 'success' : 'warning'} text-${isProcess ? 'white' : 'dark'}">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Confirm ${isProcess ? 'Process' : 'Cancel'} Deletion Request
                        </h5>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to ${isProcess ? 'process' : 'cancel'} this account deletion request?</p>
                        <p class="text-muted mb-0">${isProcess ? 'This will permanently delete the user and their data.' : 'This action cannot be undone.'}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-${isProcess ? 'success' : 'danger'}" id="confirmAction">
                            <i class="fas fa-${isProcess ? 'check' : 'ban'} me-2"></i>${isProcess ? 'Process' : 'Cancel'} Request
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        modal.querySelector('#confirmAction').addEventListener('click', () => {
            bsModal.hide();
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${isProcess ? 'Processing' : 'Cancelling'}...`;
            button.disabled = true;
            this.submit();
        });
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    });
    });
    </script>
</body>
</html>