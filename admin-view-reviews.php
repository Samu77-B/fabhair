<?php
/**
 * Admin Dashboard - View Customer Reviews
 * 
 * This page allows the salon owner to view all submitted ratings.
 * PROTECT THIS PAGE WITH PASSWORD AUTHENTICATION!
 * 
 * To secure this page:
 * 1. Add .htaccess password protection, OR
 * 2. Add login form at the top of this file, OR
 * 3. Host this on a separate admin subdomain with its own authentication
 */

// Database configuration - UPDATE THESE WITH YOUR DATABASE CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_NAME', 'u556329104_ratings');
define('DB_USER', 'u556329104_fb_admin');
define('DB_PASS', '^8@f?r0^Ht');

// Simple password protection (REPLACE WITH YOUR OWN PASSWORD)
// For better security, use .htaccess or proper login system
$admin_password = 'kjrXbiztdPcS';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        // Show login form
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Admin Login - View Reviews</title>
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; }
                .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                input[type="password"] { padding: 10px; width: 200px; margin: 10px 0; }
                button { padding: 10px 20px; background: #81b29a; color: white; border: none; border-radius: 5px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Admin Login</h2>
                <form method="POST">
                    <input type="password" name="password" placeholder="Enter password" required>
                    <br>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Get filter parameters
$filter_rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Build query with filters
    $where = [];
    $params = [];
    
    if ($filter_rating > 0) {
        $where[] = "rating = ?";
        $params[] = $filter_rating;
    }
    
    if ($filter_date_from) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $filter_date_to;
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Sort order
    $order_by = "ORDER BY created_at DESC";
    if ($sort_by === 'oldest') {
        $order_by = "ORDER BY created_at ASC";
    } elseif ($sort_by === 'rating_high') {
        $order_by = "ORDER BY rating DESC, created_at DESC";
    } elseif ($sort_by === 'rating_low') {
        $order_by = "ORDER BY rating ASC, created_at DESC";
    }
    
    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ratings $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch()['total'];
    
    // Get average rating
    $avg_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM ratings $where_clause");
    $avg_stmt->execute($params);
    $stats = $avg_stmt->fetch();
    $average_rating = $stats['avg_rating'] !== null ? round($stats['avg_rating'], 1) : 0;
    
    // Get rating distribution
    $dist_stmt = $pdo->prepare("
        SELECT rating, COUNT(*) as count 
        FROM ratings 
        $where_clause
        GROUP BY rating 
        ORDER BY rating DESC
    ");
    $dist_stmt->execute($params);
    $distribution = $dist_stmt->fetchAll();
    
    // Get reviews (with pagination)
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // LIMIT and OFFSET must be integers, not placeholders
    $stmt = $pdo->prepare("
        SELECT * FROM ratings 
        $where_clause
        $order_by
        LIMIT " . intval($per_page) . " OFFSET " . intval($offset) . "
    ");
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    
    $total_pages = ceil($total_count / $per_page);
    
} catch (PDOException $e) {
    // Log error for debugging
    error_log("Admin Dashboard Error: " . $e->getMessage());
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Helper function to display stars
function displayStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating ? '★' : '☆';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer Reviews - Fab Hair London</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            border-bottom: 3px solid #81b29a;
            padding-bottom: 10px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: #81b29a;
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .stat-box .value {
            font-size: 32px;
            font-weight: bold;
        }
        .filters {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .filter-group button {
            padding: 10px 20px;
            background: #81b29a;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .filter-group button:hover {
            background: #6a9980;
        }
        .reviews-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .reviews-table th {
            background: #81b29a;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        .reviews-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .reviews-table tr:hover {
            background: #f9f9f9;
        }
        .rating-stars {
            font-size: 20px;
            color: #FFD700;
        }
        .rating-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        .rating-5 { background: #4CAF50; color: white; }
        .rating-4 { background: #8BC34A; color: white; }
        .rating-3 { background: #FFC107; color: #333; }
        .rating-2 { background: #FF9800; color: white; }
        .rating-1 { background: #F44336; color: white; }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            background: #81b29a;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .pagination a:hover {
            background: #6a9980;
        }
        .pagination .current {
            background: #6a9980;
        }
        .logout {
            float: right;
            padding: 8px 15px;
            background: #f44336;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .logout:hover {
            background: #d32f2f;
        }
        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="?logout=1" class="logout">Logout</a>
        <h1>⭐ Customer Reviews Dashboard</h1>
        
        <?php if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: admin-view-reviews.php');
            exit;
        } ?>
        
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-box">
                <h3>Total Reviews</h3>
                <div class="value"><?php echo number_format($total_count); ?></div>
            </div>
            <div class="stat-box">
                <h3>Average Rating</h3>
                <div class="value"><?php echo $average_rating; ?> ⭐</div>
            </div>
            <div class="stat-box">
                <h3>5 Star Reviews</h3>
                <div class="value">
                    <?php 
                    $five_star = array_filter($distribution, fn($d) => $d['rating'] == 5);
                    echo $five_star ? number_format($five_star[array_key_first($five_star)]['count']) : '0';
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <div class="filter-group">
                    <label>Filter by Rating:</label>
                    <select name="rating">
                        <option value="0">All Ratings</option>
                        <option value="5" <?php echo $filter_rating == 5 ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $filter_rating == 4 ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $filter_rating == 3 ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $filter_rating == 2 ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $filter_rating == 1 ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date From:</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                <div class="filter-group">
                    <label>Date To:</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                <div class="filter-group">
                    <label>Sort By:</label>
                    <select name="sort">
                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="rating_high" <?php echo $sort_by == 'rating_high' ? 'selected' : ''; ?>>Highest Rating</option>
                        <option value="rating_low" <?php echo $sort_by == 'rating_low' ? 'selected' : ''; ?>>Lowest Rating</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit">Apply Filters</button>
                </div>
            </form>
        </div>
        
        <!-- Reviews Table -->
        <?php if (empty($reviews)): ?>
            <div class="no-reviews">
                <p><strong>No reviews found.</strong></p>
                <p style="margin-top: 10px; font-size: 14px;">
                    <?php if ($total_count == 0): ?>
                        No ratings have been submitted yet. Once customers click stars in your Vagaro emails, reviews will appear here.
                    <?php else: ?>
                        No reviews match your current filter criteria. Try adjusting your filters.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <table class="reviews-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Rating</th>
                        <th>Stars</th>
                        <th>Email</th>
                        <th>Appointment ID</th>
                        <th>Token</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td><?php echo date('M d, Y h:i A', strtotime($review['created_at'])); ?></td>
                            <td>
                                <span class="rating-badge rating-<?php echo $review['rating']; ?>">
                                    <?php echo $review['rating']; ?> Star<?php echo $review['rating'] != 1 ? 's' : ''; ?>
                                </span>
                            </td>
                            <td class="rating-stars"><?php echo displayStars($review['rating']); ?></td>
                            <td><?php echo isset($review['email']) && !empty($review['email']) ? htmlspecialchars($review['email']) : 'N/A'; ?></td>
                            <td><?php echo $review['appointment_id'] ? htmlspecialchars($review['appointment_id']) : 'N/A'; ?></td>
                            <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($review['token']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = $_GET;
                    for ($i = 1; $i <= $total_pages; $i++):
                        $query_params['page'] = $i;
                        $url = '?' . http_build_query($query_params);
                        $class = $i == $page ? 'current' : '';
                    ?>
                        <a href="<?php echo $url; ?>" class="<?php echo $class; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
