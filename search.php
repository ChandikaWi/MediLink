<?php
include 'config.php';
include 'functions.php';

if (!isLoggedIn() || getUserRole() != 'patient') {
    redirect('login.php');
}

$medicine = isset($_GET['medicine']) ? trim($_GET['medicine']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$results = [];

if ($medicine) {
    // Clean and split query into words for partial matching
    $words = preg_split('/\s+/', strtolower($medicine));
    $filteredWords = array_filter($words, function($word) {
        return strlen($word) > 2 && ctype_alnum($word); // Filter short or non-alphanumeric words
    });

    if (!empty($filteredWords)) {
        // Build dynamic WHERE clause for multi-word OR (partial matching)
        $whereClauses = [];
        $params = [];
        foreach ($filteredWords as $word) {
            $whereClauses[] = "LOWER(m.name) LIKE ?";
            $params[] = '%' . $word . '%';
        }
        $medicineWhere = '(' . implode(' OR ', $whereClauses) . ')';

        // Base query
        $query = "SELECT m.*, p.pharmacy_name, p.address 
                  FROM medicines m 
                  JOIN pharmacies p ON m.pharmacy_id = p.pharmacy_id 
                  WHERE $medicineWhere AND m.quantity > 0 AND m.expiry_date > CURDATE()";
        $types = str_repeat('s', count($filteredWords));

        // Add location filter if provided
        if ($location) {
            $query .= " AND p.address LIKE ?";
            $params[] = "%{$location}%";
            $types .= "s";
        }

        // Execute query with prepared statement
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch potential matches
        $potentialResults = [];
        while ($row = $result->fetch_assoc()) {
            $potentialResults[] = $row;
        }
        $stmt->close();

        // Apply fuzzy matching with Levenshtein
        foreach ($potentialResults as &$row) {
            $nameLower = strtolower($row['name']);
            $minDistance = PHP_INT_MAX;
            foreach ($filteredWords as $word) {
                $distance = levenshtein($word, $nameLower, 1, 2, 1); // Costs: insert=1, replace=2, delete=1
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                }
            }
            $row['fuzzy_distance'] = $minDistance;
        }

        // Filter by adaptive threshold and sort by fuzzy distance
        foreach ($potentialResults as $row) {
            $threshold = max(2, strlen($row['name']) * 0.2); // E.g., allow 20% of name length as edits
            if ($row['fuzzy_distance'] <= $threshold) {
                $results[] = $row;
            }
        }
        usort($results, function($a, $b) {
            return $a['fuzzy_distance'] <=> $b['fuzzy_distance'];
        });

        // Limit to top 50 results for performance
        $results = array_slice($results, 0, 50);
    } else {
        // Fallback to original query if no valid words
        $query = "SELECT m.*, p.pharmacy_name, p.address 
                  FROM medicines m 
                  JOIN pharmacies p ON m.pharmacy_id = p.pharmacy_id 
                  WHERE LOWER(m.name) LIKE ? AND m.quantity > 0 AND m.expiry_date > CURDATE()";
        $params = ["%{$medicine}%"];
        $types = "s";

        if ($location) {
            $query .= " AND p.address LIKE ?";
            $params[] = "%{$location}%";
            $types .= "s";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row['fuzzy_distance'] = 0; 
            $results[] = $row;
        }
        $stmt->close();
    }
}

$total_results = count($results);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Medicine Tracker</title>
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

        .search-info {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .filter-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease 0.1s both;
        }

        .filter-header {
            background: linear-gradient(135deg, var(--gray-100), #ffffff);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .filter-header h4 {
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filter-body {
            padding: 1.5rem;
        }

        .results-container {
            display: grid;
            gap: 2rem;
        }

        .results-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            animation: fadeInUp 0.6s ease 0.2s both;
        }

        .results-header {
            background: linear-gradient(135deg, var(--gray-100), #ffffff);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-title {
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .results-count {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .medicine-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .medicine-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .medicine-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.95);
        }

        .medicine-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .medicine-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .medicine-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success-color);
        }

        .pharmacy-info {
            margin-bottom: 1rem;
        }

        .pharmacy-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pharmacy-address {
            color: var(--gray-600);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .medicine-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .detail-label {
            color: var(--gray-600);
            font-weight: 500;
        }

        .detail-value {
            color: var(--gray-800);
            font-weight: 600;
        }

        .quantity-badge {
            background: var(--success-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .expiry-badge {
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .reserve-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(6, 182, 212, 0.05));
            border-radius: var(--border-radius);
            border: 1px solid rgba(79, 70, 229, 0.1);
        }

        .quantity-input {
            width: 80px;
            padding: 0.5rem;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
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

        .btn-success-modern {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-success-modern:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .map-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-top: 2rem;
            animation: fadeInUp 0.6s ease 0.3s both;
        }

        .map-header {
            background: linear-gradient(135deg, var(--gray-100), #ffffff);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .map-header h4 {
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        #map {
            height: 400px;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-600);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            margin-bottom: 2rem;
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

        @media (max-width: 768px) {
            .main-container {
                padding: 0 1rem;
            }
            
            .medicine-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
            
            .medicine-card {
                padding: 1rem;
            }
            
            .medicine-details {
                grid-template-columns: 1fr;
            }
            
            .reserve-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .results-header {
                flex-direction: column;
                align-items: stretch;
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
        
        .dark-mode .filter-card,
        .dark-mode .results-card,
        .dark-mode .map-container,
        .dark-mode .top-nav {
            background: rgba(30, 41, 59, 0.95);
            border-color: var(--gray-200);
        }
        
        .dark-mode .filter-header,
        .dark-mode .results-header,
        .dark-mode .map-header {
            background: linear-gradient(135deg, var(--gray-200), #334155);
        }
        
        .dark-mode .medicine-card {
            background: rgba(51, 65, 85, 0.8);
            border-color: var(--gray-300);
        }
        
        .dark-mode .medicine-card:hover {
            background: rgba(51, 65, 85, 0.95);
        }
        
        .dark-mode .form-control {
            background: rgba(51, 65, 85, 0.8);
            border-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .dark-mode .form-control:focus {
            background: rgba(51, 65, 85, 0.95);
        }

        .dark-mode .quantity-input {
            background: rgba(51, 65, 85, 0.8);
            border-color: var(--gray-300);
            color: var(--gray-700);
        }
    </style>
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap" async defer></script>
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
                    <a class="nav-link" href="patient_dashboard.php">
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
                <i class="fas fa-search me-3"></i>Search Results
            </h1>
            <p class="page-subtitle">Find available medicines near you</p>
            <div class="search-info">
                <strong>Searching for:</strong> "<?php echo htmlspecialchars($medicine); ?>"
                <?php if ($location): ?>
                    <strong>in:</strong> "<?php echo htmlspecialchars($location); ?>"
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-header">
                <h4>
                    <i class="fas fa-filter"></i>
                    Refine Your Search
                </h4>
            </div>
            <div class="filter-body">
                <form action="search.php" method="GET" id="filterForm">
                    <input type="hidden" name="medicine" value="<?php echo htmlspecialchars($medicine); ?>">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt me-2"></i>Location Filter
                        </label>
                        <div class="input-group">
                            <i class="fas fa-map-marker-alt input-icon"></i>
                            <input 
                                type="text" 
                                name="location" 
                                class="form-control with-icon" 
                                placeholder="Enter location to filter results"
                                value="<?php echo htmlspecialchars($location); ?>"
                            >
                        </div>
                    </div>
                    <button type="submit" class="btn-modern btn-primary-modern">
                        <i class="fas fa-search"></i>Apply Filter
                    </button>
                </form>
            </div>
        </div>

        <!-- Results -->
        <?php if ($total_results > 0): ?>
            <div class="results-card">
                <div class="results-header">
                    <h4 class="results-title">
                        <i class="fas fa-pills"></i>
                        Available Medicines
                    </h4>
                    <span class="results-count">
                        <?php echo $total_results; ?> result<?php echo $total_results !== 1 ? 's' : ''; ?> found
                    </span>
                </div>
                
                <div class="medicine-grid">
                    <?php foreach ($results as $row): 
                        // Calculate days until expiry
                        $expiry = new DateTime($row['expiry_date']);
                        $today = new DateTime();
                        $days_until_expiry = $today->diff($expiry)->days;
                    ?>
                        <div class="medicine-card">
                            <div class="medicine-header">
                                <div>
                                    <div class="medicine-name"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <?php if ($row['fuzzy_distance'] > 0): ?>
                                        <small class="text-muted">(Approximate match)</small>
                                    <?php endif; ?>
                                </div>
                                <div class="medicine-price">Rs. <?php echo number_format($row['price'], 2); ?></div>
                            </div>
                            
                            <div class="pharmacy-info">
                                <div class="pharmacy-name">
                                    <i class="fas fa-store"></i>
                                    <?php echo htmlspecialchars($row['pharmacy_name']); ?>
                                </div>
                                <div class="pharmacy-address">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($row['address']); ?>
                                </div>
                            </div>
                            
                            <div class="medicine-details">
                                <div class="detail-item">
                                    <i class="fas fa-cubes text-success"></i>
                                    <span class="detail-label">Available:</span>
                                    <span class="quantity-badge"><?php echo $row['quantity']; ?> units</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt text-warning"></i>
                                    <span class="detail-label">Expires:</span>
                                    <span class="expiry-badge"><?php echo $days_until_expiry; ?> days</span>
                                </div>
                            </div>
                            
                            <div class="reserve-form">
                                <form action="reserve.php" method="POST" class="d-flex align-items-center gap-3 w-100">
                                    <input type="hidden" name="medicine_id" value="<?php echo $row['medicine_id']; ?>">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="detail-label mb-0">Quantity:</label>
                                        <input 
                                            type="number" 
                                            name="quantity" 
                                            min="1" 
                                            max="<?php echo $row['quantity']; ?>" 
                                            class="quantity-input"
                                            value="1"
                                            required
                                        >
                                    </div>
                                    <button type="submit" class="btn-modern btn-success-modern flex-grow-1">
                                        <i class="fas fa-bookmark"></i>Reserve Medicine
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="results-card">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-search-minus"></i>
                    </div>
                    <h3>No Results Found</h3>
                    <p>We couldn't find any available medicines matching "<?php echo htmlspecialchars($medicine); ?>"
                    <?php if ($location): ?>
                        in "<?php echo htmlspecialchars($location); ?>"
                    <?php endif; ?>
                    </p>
                    <a href="patient_dashboard.php" class="btn-modern btn-primary-modern">
                        <i class="fas fa-arrow-left"></i>Try Another Search
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Map 
        <div class="map-container">
            <div class="map-header">
                <h4>
                    <i class="fas fa-map-marked-alt"></i>
                    Pharmacy Locations
                </h4>
            </div>
            <div id="map"></div>
        </div>
    </div>
    -->

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

        // Google Maps initialization
        function initMap() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showMap, showDefaultMap);
            } else {
                showDefaultMap();
            }
        }

        function showMap(position) {
            const userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            const map = new google.maps.Map(document.getElementById("map"), {
                center: userLocation,
                zoom: 12,
                styles: [
                    {
                        featureType: "all",
                        elementType: "geometry.fill",
                        stylers: [{ weight: "2.00" }]
                    },
                    {
                        featureType: "all",
                        elementType: "geometry.stroke",
                        stylers: [{ color: "#9c9c9c" }]
                    }
                ]
            });

            // Add user location marker
            new google.maps.Marker({
                position: userLocation,
                map: map,
                title: "Your Location",
                icon: {
                    url: "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(
                        '<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">' +
                        '<circle cx="20" cy="20" r="8" fill="#4f46e5" stroke="#fff" stroke-width="3"/>' +
                        '</svg>'
                    ),
                    scaledSize: new google.maps.Size(40, 40),
                    anchor: new google.maps.Point(20, 20)
                }
            });

            // Add pharmacy markers
            addPharmacyMarkers(map);
        }

        function showDefaultMap() {
            // Default to a central location if geolocation fails
            const defaultLocation = { lat: 40.7128, lng: -74.0060 }; // New York City
            
            const map = new google.maps.Map(document.getElementById("map"), {
                center: defaultLocation,
                zoom: 10,
                styles: [
                    {
                        featureType: "all",
                        elementType: "geometry.fill",
                        stylers: [{ weight: "2.00" }]
                    },
                    {
                        featureType: "all",
                        elementType: "geometry.stroke",
                        stylers: [{ color: "#9c9c9c" }]
                    }
                ]
            });

            addPharmacyMarkers(map);
        }

        function addPharmacyMarkers(map) {
            // Add markers for pharmacies with available medicines
            const pharmacies = [
                <?php 
                $pharmacy_locations = [];
                foreach ($results as $row): 
                    $pharmacy_key = $row['pharmacy_name'] . '|' . $row['address'];
                    if (!in_array($pharmacy_key, $pharmacy_locations)) {
                        $pharmacy_locations[] = $pharmacy_key;
                        // For demo purposes, using random coordinates
                        $lat = 40.7128 + (rand(-100, 100) / 1000);
                        $lng = -74.0060 + (rand(-100, 100) / 1000);
                ?>
                {
                    name: "<?php echo addslashes($row['pharmacy_name']); ?>",
                    address: "<?php echo addslashes($row['address']); ?>",
                    lat: <?php echo $lat; ?>,
                    lng: <?php echo $lng; ?>
                },
                <?php 
                    }
                endforeach; 
                ?>
            ];

            pharmacies.forEach(pharmacy => {
                const marker = new google.maps.Marker({
                    position: { lat: pharmacy.lat, lng: pharmacy.lng },
                    map: map,
                    title: pharmacy.name,
                    icon: {
                        url: "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(
                            '<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">' +
                            '<path d="M20 0C12.3 0 6 6.3 6 14c0 10.5 14 26 14 26s14-15.5 14-26C34 6.3 27.7 0 20 0z" fill="#10b981"/>' +
                            '<circle cx="20" cy="14" r="7" fill="#fff"/>' +
                            '<path d="M20 8c-3.3 0-6 2.7-6 6s2.7 6 6 6 6-2.7 6-6-2.7-6-6-6zm2 7h-1v1c0 0.6-0.4 1-1 1s-1-0.4-1-1v-1h-1c-0.6 0-1-0.4-1-1s0.4-1 1-1h1v-1c0-0.6 0.4-1 1-1s1 0.4 1 1v1h1c0.6 0 1 0.4 1 1s-0.4 1-1 1z" fill="#10b981"/>' +
                            '</svg>'
                        ),
                        scaledSize: new google.maps.Size(40, 40),
                        anchor: new google.maps.Point(20, 40)
                    }
                });

                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px; font-family: Inter, sans-serif;">
                            <h6 style="margin: 0 0 5px 0; color: #1f2937; font-weight: 600;">${pharmacy.name}</h6>
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">
                                <i class="fas fa-map-marker-alt" style="color: #ef4444; margin-right: 5px;"></i>
                                ${pharmacy.address}
                            </p>
                        </div>
                    `
                });

                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });
            });
        }

        // Initialize map when page loads
        window.addEventListener('load', () => {
            if (typeof google !== 'undefined') {
                initMap();
            }
        });

        // Quantity input validation
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max'));
                const value = parseInt(this.value);
                
                if (value > max) {
                    this.value = max;
                    showNotification(`Maximum available quantity is ${max}`, 'warning');
                } else if (value < 1) {
                    this.value = 1;
                }
            });

            // Add quantity controls
            const controls = document.createElement('div');
            controls.style.cssText = `
                position: absolute;
                right: 5px;
                top: 50%;
                transform: translateY(-50%);
                display: flex;
                flex-direction: column;
                z-index: 10;
            `;
            
            const incrementBtn = document.createElement('button');
            incrementBtn.type = 'button';
            incrementBtn.innerHTML = '▲';
            incrementBtn.style.cssText = `
                background: var(--primary-color);
                color: white;
                border: none;
                padding: 2px 4px;
                font-size: 0.6rem;
                cursor: pointer;
                border-radius: 2px 2px 0 0;
            `;
            
            const decrementBtn = document.createElement('button');
            decrementBtn.type = 'button';
            decrementBtn.innerHTML = '▼';
            decrementBtn.style.cssText = `
                background: var(--primary-color);
                color: white;
                border: none;
                padding: 2px 4px;
                font-size: 0.6rem;
                cursor: pointer;
                border-radius: 0 0 2px 2px;
            `;

            incrementBtn.addEventListener('click', function() {
                const max = parseInt(input.getAttribute('max'));
                const current = parseInt(input.value) || 0;
                if (current < max) {
                    input.value = current + 1;
                }
            });

            decrementBtn.addEventListener('click', function() {
                const current = parseInt(input.value) || 0;
                if (current > 1) {
                    input.value = current - 1;
                }
            });

            controls.appendChild(incrementBtn);
            controls.appendChild(decrementBtn);
            
            const inputContainer = input.parentNode;
            inputContainer.style.position = 'relative';
            inputContainer.appendChild(controls);
        });

        // Reserve form enhancements
        document.querySelectorAll('form[action="reserve.php"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const quantityInput = this.querySelector('.quantity-input');
                const quantity = parseInt(quantityInput.value);
                const maxQuantity = parseInt(quantityInput.getAttribute('max'));
                
                if (quantity > maxQuantity) {
                    e.preventDefault();
                    showNotification(`Cannot reserve more than ${maxQuantity} units`, 'danger');
                    return;
                }
                
                // Add loading state
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reserving...';
                
                showNotification(`Reserving ${quantity} unit${quantity > 1 ? 's' : ''}...`, 'info');
            });
        });

        // Filter form enhancement
        const filterForm = document.getElementById('filterForm');
        filterForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filtering...';
            
            showNotification('Applying filters...', 'info');
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
                border-radius: var(--border-radius);
                border: none;
                padding: 1rem;
            `;

            const iconMap = {
                success: 'check-circle',
                danger: 'exclamation-triangle',
                warning: 'exclamation-circle',
                info: 'info-circle'
            };

            notification.innerHTML = `
                <i class="fas fa-${iconMap[type] || 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close float-end" onclick="this.parentElement.remove()" 
                        style="background: none; border: none; font-size: 1.2rem; color: inherit; opacity: 0.7; cursor: pointer;">×</button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Add slide animations for notifications
        const notificationStyles = document.createElement('style');
        notificationStyles.textContent = `
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
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            .btn-loading {
                pointer-events: none;
                opacity: 0.7;
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
            .alert-warning {
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
                color: var(--warning-color);
                border-left: 4px solid var(--warning-color);
            }
            .alert-info {
                background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(6, 182, 212, 0.1));
                color: var(--primary-color);
                border-left: 4px solid var(--primary-color);
            }
        `;
        document.head.appendChild(notificationStyles);

        // Search result animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                }
            });
        });

        document.querySelectorAll('.medicine-card').forEach(card => {
            observer.observe(card);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + F to focus location filter
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const locationInput = document.querySelector('input[name="location"]');
                if (locationInput) {
                    locationInput.focus();
                    locationInput.select();
                }
            }
            
            // Escape to clear location filter
            if (e.key === 'Escape') {
                const locationInput = document.querySelector('input[name="location"]');
                if (locationInput && locationInput === document.activeElement) {
                    locationInput.value = '';
                }
            }
        });

        // Auto-focus location filter if empty and no results
        <?php if ($total_results === 0 && !$location): ?>
        window.addEventListener('load', () => {
            const locationInput = document.querySelector('input[name="location"]');
            if (locationInput) {
                setTimeout(() => locationInput.focus(), 1000);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>