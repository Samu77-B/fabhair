<?php
/**
 * Rating Handler - Processes star ratings from Vagaro emails
 * 
 * This file receives rating submissions and saves them to the database.
 * Upload this file to your website root (www.fabhair.london/rate.php)
 */

// Database configuration - UPDATE THESE WITH YOUR DATABASE CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_NAME', 'u556329104_ratings');
define('DB_USER', 'u556329104_fb_admin');
define('DB_PASS', '^8@f?r0^Ht');


// Get rating, token, and email from URL
$rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// Validate rating
if ($rating < 1 || $rating > 5) {
    // Invalid rating - redirect to error page or homepage
    header('Location: /rating-landing-page-static.html?error=invalid');
    exit;
}

// Validate token
if (empty($token)) {
    header('Location: /rating-landing-page-static.html?error=invalid_token');
    exit;
}

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Check if this token has already been used (prevent duplicate ratings)
    $stmt = $pdo->prepare("SELECT id FROM ratings WHERE token = ?");
    $stmt->execute([$token]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Rating already submitted for this token
        // Redirect to thank you page anyway (don't show error to user)
        header('Location: /rating-landing-page-static.html?rating=' . $rating . '&token=' . urlencode($token) . '&duplicate=1');
        exit;
    }
    
    // Save rating to database
    // Check if email column exists (for backwards compatibility)
    $columns = "rating, token, appointment_id, created_at, ip_address, user_agent";
    $placeholders = "?, ?, ?, NOW(), ?, ?";
    $values = [$rating, $token];
    
    // Extract appointment_id from token if it's in the format {appointment_id}
    // If token is just the appointment_id, use it directly
    $appointment_id = is_numeric($token) ? intval($token) : null;
    $values[] = $appointment_id;
    
    // Add email if provided
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $columns .= ", email";
        $placeholders .= ", ?";
        $values[] = $email;
    }
    
    $values[] = $_SERVER['REMOTE_ADDR'] ?? '';
    $values[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO ratings ($columns)
        VALUES ($placeholders)
    ");
    
    $stmt->execute($values);
    
    // Optional: Send email notification for low ratings (1-2 stars)
    if ($rating <= 2) {
        // Uncomment and configure if you want email notifications for low ratings
        /*
        $to = "your-email@fabhair.london";
        $subject = "Low Rating Received - " . $rating . " Stars";
        $message = "A customer submitted a " . $rating . " star rating.\n\n";
        $message .= "Token: " . $token . "\n";
        $message .= "Appointment ID: " . ($appointment_id ?? 'N/A') . "\n";
        $message .= "Date: " . date('Y-m-d H:i:s') . "\n";
        mail($to, $subject, $message);
        */
    }
    
    // Redirect to thank you page
    header('Location: /rating-landing-page-static.html?rating=' . $rating . '&token=' . urlencode($token));
    exit;
    
} catch (PDOException $e) {
    // Database error - log it and redirect to thank you page anyway
    // (Don't show error to user, but log for debugging)
    error_log("Rating submission error: " . $e->getMessage());
    
    // Still redirect to thank you page so user doesn't see an error
    header('Location: /rating-landing-page-static.html?rating=' . $rating . '&token=' . urlencode($token));
    exit;
}
?>
