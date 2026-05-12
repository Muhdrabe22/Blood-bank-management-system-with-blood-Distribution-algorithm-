<?php
// ============================================================
// DATABASE CLASS - PDO WRAPPER
// ============================================================

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() { $this->pdo->beginTransaction(); }
    public function commit() { $this->pdo->commit(); }
    public function rollback() { $this->pdo->rollBack(); }
}

// ============================================================
// BLOOD DISTRIBUTION ALGORITHM
// ============================================================

class BloodDistributionAlgorithm {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * MAIN DISTRIBUTION ALGORITHM
     * Finds best available blood units for a request
     * Factors: Compatibility, Freshness, Urgency, Stock Levels
     */
    public function findBestUnits($requestId) {
        $request = $this->db->fetch(
            "SELECT br.*, p.blood_group as patient_blood_group 
             FROM blood_requests br 
             JOIN patients p ON br.patient_id = p.id 
             WHERE br.id = ?",
            [$requestId]
        );

        if (!$request) return ['error' => 'Request not found'];

        $recipientGroup = $request['blood_group'];
        $componentType  = $request['component_type'];
        $unitsNeeded    = $request['units_requested'];
        $urgency        = $request['urgency'];

        // Step 1: Get all compatible blood groups ranked by score
        $compatibleGroups = $this->db->fetchAll(
            "SELECT donor_group, compatibility_score 
             FROM blood_compatibility 
             WHERE recipient_group = ? AND is_compatible = 1 
             ORDER BY compatibility_score DESC",
            [$recipientGroup]
        );

        $allCandidates = [];

        foreach ($compatibleGroups as $cg) {
            // Step 2: Fetch available units per compatible group
            $units = $this->db->fetchAll(
                "SELECT bu.*, 
                        DATEDIFF(bu.expiry_date, CURDATE()) as days_to_expiry,
                        DATEDIFF(CURDATE(), bu.collection_date) as age_days
                 FROM blood_units bu
                 WHERE bu.blood_group = ? 
                   AND bu.component_type = ?
                   AND bu.status = 'Available'
                   AND bu.screening_status = 'Cleared'
                   AND bu.expiry_date > CURDATE()
                 ORDER BY bu.expiry_date ASC",
                [$cg['donor_group'], $componentType]
            );

            foreach ($units as $unit) {
                $score = $this->calculatePriorityScore(
                    $cg['compatibility_score'],
                    $unit['days_to_expiry'],
                    $unit['age_days'],
                    $urgency,
                    $unit['blood_group'],
                    $recipientGroup
                );

                $unit['priority_score']      = $score;
                $unit['compatibility_score'] = $cg['compatibility_score'];
                $allCandidates[]             = $unit;
            }
        }

        // Step 3: Sort by priority score (highest = best pick)
        usort($allCandidates, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        // Step 4: Select required number of units
        $selectedUnits = array_slice($allCandidates, 0, $unitsNeeded);

        return [
            'request'        => $request,
            'selected_units' => $selectedUnits,
            'total_available'=> count($allCandidates),
            'units_found'    => count($selectedUnits),
            'can_fulfill'    => count($selectedUnits) >= $unitsNeeded
        ];
    }

    /**
     * PRIORITY SCORING ENGINE
     * Higher score = better candidate for distribution
     */
    private function calculatePriorityScore($compatibilityScore, $daysToExpiry, $ageDays, $urgency, $donorGroup, $recipientGroup) {
        $score = 0;

        // 1. Compatibility weight (0–50 pts)
        $score += $compatibilityScore * 5;  // Max 50

        // 2. Expiry urgency — use units expiring soonest (FIFO) (0–30 pts)
        if ($daysToExpiry <= 3)       $score += 30;
        elseif ($daysToExpiry <= 7)   $score += 25;
        elseif ($daysToExpiry <= 14)  $score += 18;
        elseif ($daysToExpiry <= 21)  $score += 10;
        else                          $score += 5;

        // 3. Exact match bonus (0–20 pts)
        if ($donorGroup === $recipientGroup) $score += 20;

        // 4. Age of blood unit — fresher is better (0–10 pts)
        if ($ageDays <= 7)       $score += 10;
        elseif ($ageDays <= 14)  $score += 7;
        elseif ($ageDays <= 21)  $score += 4;
        else                     $score += 1;

        // 5. Urgency modifier
        // For emergency: prioritize O- (universal donor) if patient is unknown/critical
        if ($urgency === 'Emergency') {
            $score += 5;
            if ($donorGroup === 'O-') $score += 10; // extra for O-
        }

        return $score;
    }

    /**
     * STOCK OPTIMIZATION: Identify which groups are at risk
     */
    public function getStockStatus() {
        return $this->db->fetchAll(
            "SELECT 
                blood_group,
                component_type,
                COUNT(*) as total_units,
                SUM(CASE WHEN status = 'Available' AND expiry_date > CURDATE() THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status='Available' THEN 1 ELSE 0 END) as expiring_soon,
                MIN(CASE WHEN status='Available' AND expiry_date > CURDATE() THEN expiry_date END) as nearest_expiry
             FROM blood_units
             GROUP BY blood_group, component_type
             ORDER BY blood_group, component_type"
        );
    }

    /**
     * AUTO-DISTRIBUTE: Match pending requests with available units
     */
    public function autoDistribute() {
        $pendingRequests = $this->db->fetchAll(
            "SELECT * FROM blood_requests 
             WHERE status IN ('Pending','Processing') 
             ORDER BY 
               CASE urgency WHEN 'Emergency' THEN 1 WHEN 'Urgent' THEN 2 ELSE 3 END,
               created_at ASC"
        );

        $results = [];
        foreach ($pendingRequests as $req) {
            $match = $this->findBestUnits($req['id']);
            if ($match['can_fulfill']) {
                $results[] = [
                    'request_id' => $req['id'],
                    'request_code' => $req['request_code'],
                    'units_matched' => $match['units_found'],
                    'units' => array_column($match['selected_units'], 'unit_code')
                ];
            }
        }
        return $results;
    }

    /**
     * Check if a specific unit is compatible with a patient
     */
    public function isCompatible($donorGroup, $recipientGroup) {
        $result = $this->db->fetch(
            "SELECT is_compatible, compatibility_score FROM blood_compatibility 
             WHERE donor_group = ? AND recipient_group = ?",
            [$donorGroup, $recipientGroup]
        );
        return $result ?: ['is_compatible' => 0, 'compatibility_score' => 0];
    }
}

// ============================================================
// AUTHENTICATION CLASS
// ============================================================

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public function login($username, $password) {
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = ? AND is_active = 1",
            [$username]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['login_time']= time();

            $this->db->execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            $this->logActivity('Login', 'Auth', 'User logged in');
            return true;
        }
        return false;
    }

    public function logout() {
        $this->logActivity('Logout', 'Auth', 'User logged out');
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) return false;
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        return true;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/index.php?timeout=1');
            exit;
        }
    }

    public function hasRole($roles) {
        if (!is_array($roles)) $roles = [$roles];
        return in_array($_SESSION['role'] ?? '', $roles);
    }

    public function requireRole($roles) {
        $this->requireLogin();
        if (!$this->hasRole($roles)) {
            header('Location: ' . APP_URL . '/pages/dashboard.php?error=unauthorized');
            exit;
        }
    }

    public function logActivity($action, $module, $description, $oldValues = null, $newValues = null) {
        $this->db->execute(
            "INSERT INTO activity_logs (user_id, action, module, description, ip_address, old_values, new_values)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $_SESSION['user_id'] ?? null,
                $action, $module, $description,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null
            ]
        );
    }
}

// ============================================================
// INVENTORY MANAGER
// ============================================================

class InventoryManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function generateUnitCode($bloodGroup, $componentType) {
        $prefix = 'BU-' . strtoupper(str_replace(['+','-',' '], ['P','N',''], $bloodGroup));
        $abbr = [
            'Whole Blood' => 'WB', 'Packed Red Cells' => 'PRC',
            'Fresh Frozen Plasma' => 'FFP', 'Platelets' => 'PLT',
            'Cryoprecipitate' => 'CRYO', 'Buffy Coat' => 'BC'
        ];
        $typeCode = $abbr[$componentType] ?? 'UN';
        $count = $this->db->fetch("SELECT COUNT(*)+1 as n FROM blood_units")['n'];
        return $prefix . '-' . $typeCode . '-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function getExpiryDate($componentType, $collectionDate) {
        $expiryDays = [
            'Whole Blood' => EXPIRY_WHOLE_BLOOD,
            'Packed Red Cells' => EXPIRY_PACKED_RBC,
            'Fresh Frozen Plasma' => EXPIRY_FFP,
            'Platelets' => EXPIRY_PLATELETS,
            'Cryoprecipitate' => EXPIRY_CRYO,
            'Buffy Coat' => 5
        ];
        $days = $expiryDays[$componentType] ?? 35;
        return date('Y-m-d', strtotime($collectionDate . " +{$days} days"));
    }

   public function checkLowStock() {
    $lowStock = $this->db->fetchAll(
        "SELECT bu.blood_group, bu.component_type,    /* ← added bu. prefix */
                COUNT(*) as available_units,
                sa.minimum_units, sa.critical_units
         FROM blood_units bu
         JOIN stock_alerts sa ON bu.blood_group = sa.blood_group AND bu.component_type = sa.component_type
         WHERE bu.status = 'Available' AND bu.expiry_date > CURDATE()
         GROUP BY bu.blood_group, bu.component_type, sa.minimum_units, sa.critical_units  /* ← added sa columns */
         HAVING available_units <= sa.minimum_units"
    );
    return $lowStock;
    }

    public function getExpiringSoon($days = 7) {
        return $this->db->fetchAll(
            "SELECT * FROM blood_units 
             WHERE status = 'Available' 
               AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY expiry_date ASC",
            [$days]
        );
    }

    public function generateCode($prefix, $table, $codeCol) {
        $year = date('Y');
        $count = $this->db->fetch("SELECT COUNT(*)+1 as n FROM $table WHERE $codeCol LIKE ?", ["{$prefix}-{$year}-%"])['n'];
        return "{$prefix}-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

}
// ============================================================
// HELPER FUNCTIONS
// ============================================================

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function formatDate($date, $format = 'd M Y') {
    return $date ? date($format, strtotime($date)) : 'N/A';
}

function formatDateTime($dt) {
    return $dt ? date('d M Y, H:i', strtotime($dt)) : 'N/A';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}

function bloodGroupBadge($group) {
    $colors = [
        'A+'  => '#e74c3c', 'A-'  => '#c0392b',
        'B+'  => '#e67e22', 'B-'  => '#d35400',
        'AB+' => '#9b59b6', 'AB-' => '#8e44ad',
        'O+'  => '#2ecc71', 'O-'  => '#27ae60'
    ];
    $color = $colors[$group] ?? '#3498db';
    return "<span class='blood-badge' style='background:{$color}'>{$group}</span>";
}

function statusBadge($status) {
    $classes = [
        'Available'   => 'badge-success',
        'Reserved'    => 'badge-warning',
        'Issued'      => 'badge-info',
        'Expired'     => 'badge-danger',
        'Discarded'   => 'badge-secondary',
        'Pending'     => 'badge-warning',
        'Approved'    => 'badge-success',
        'Fulfilled'   => 'badge-success',
        'Rejected'    => 'badge-danger',
        'Cancelled'   => 'badge-secondary',
        'Cleared'     => 'badge-success',
        'Reactive'    => 'badge-danger',
        'Emergency'   => 'badge-danger',
        'Urgent'      => 'badge-warning',
        'Routine'     => 'badge-info',
        'Transfused'  => 'badge-success',
    ];
    $class = $classes[$status] ?? 'badge-secondary';
    return "<span class='badge $class'>$status</span>";
}

function calculateAge($dob) {
    if (!$dob) return 'N/A';
    return date_diff(date_create($dob), date_create('today'))->y . ' yrs';
}

function getDashboardStats() {
    $db = Database::getInstance();

    return [
        'total_donors'        => $db->fetch("SELECT COUNT(*) as n FROM donors")['n'],
        'total_donations'     => $db->fetch("SELECT COUNT(*) as n FROM donations")['n'],
        'available_units'     => $db->fetch("SELECT COUNT(*) as n FROM blood_units WHERE status='Available' AND expiry_date > CURDATE()")['n'],
        'pending_requests'    => $db->fetch("SELECT COUNT(*) as n FROM blood_requests WHERE status='Pending'")['n'],
        'today_donations'     => $db->fetch("SELECT COUNT(*) as n FROM donations WHERE DATE(donation_date)=CURDATE()")['n'],
        'expiring_units'      => $db->fetch("SELECT COUNT(*) as n FROM blood_units WHERE status='Available' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)")['n'],
        'total_issued'        => $db->fetch("SELECT COUNT(*) as n FROM blood_distributions")['n'],
        'emergency_requests'  => $db->fetch("SELECT COUNT(*) as n FROM blood_requests WHERE urgency='Emergency' AND status='Pending'")['n'],
        'low_stock_groups'    => $db->fetch("SELECT COUNT(DISTINCT blood_group) as n FROM blood_units WHERE status='Available' GROUP BY blood_group HAVING COUNT(*) < 5")['n'] ?? 0,
        'total_patients'      => $db->fetch("SELECT COUNT(*) as n FROM patients")['n'],
    ];
}
