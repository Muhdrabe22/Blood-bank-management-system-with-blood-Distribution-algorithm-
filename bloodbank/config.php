<?php
// ============================================================
// LUGGA CLINIC - BLOOD BANK MANAGEMENT SYSTEM
// Configuration File
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lugga_bloodbank');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Lugga Clinic Blood Bank');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://localhost/bloodbank');
define('BASE_PATH', __DIR__);

define('SESSION_TIMEOUT', 3600); // 1 hour
define('ITEMS_PER_PAGE', 20);

// Blood group expiry days
define('EXPIRY_WHOLE_BLOOD', 35);
define('EXPIRY_PACKED_RBC', 42);
define('EXPIRY_FFP', 365);
define('EXPIRY_PLATELETS', 5);
define('EXPIRY_CRYO', 365);

// Stock thresholds
define('STOCK_LOW_THRESHOLD', 10);
define('STOCK_CRITICAL_THRESHOLD', 5);

// Timezone
date_default_timezone_set('Africa/Lagos');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
