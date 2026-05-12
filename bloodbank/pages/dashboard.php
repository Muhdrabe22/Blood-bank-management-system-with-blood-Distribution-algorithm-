<?php
require_once '../config.php';
require_once '../includes/functions.php';

if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$inv = new InventoryManager();
$algo = new BloodDistributionAlgorithm();

$stats = getDashboardStats();
$stockStatus = $algo->getStockStatus();
$lowStock = $inv->checkLowStock();
$expiring = $inv->getExpiringSoon(7);

// Recent donations
$recentDonations = $db->fetchAll(
    "SELECT d.*, dn.full_name as donor_name, dn.blood_group 
     FROM donations d JOIN donors dn ON d.donor_id = dn.id 
     ORDER BY d.created_at DESC LIMIT 8"
);

// Pending requests
$pendingRequests = $db->fetchAll(
    "SELECT br.*, p.full_name as patient_name, p.blood_group as patient_blood_group 
     FROM blood_requests br JOIN patients p ON br.patient_id = p.id 
     WHERE br.status IN ('Pending','Processing')
     ORDER BY CASE br.urgency WHEN 'Emergency' THEN 1 WHEN 'Urgent' THEN 2 ELSE 3 END, br.created_at ASC 
     LIMIT 8"
);

// Monthly donations (last 6 months)
$monthlyData = $db->fetchAll(
    "SELECT DATE_FORMAT(donation_date,'%b %Y') as month, 
            COUNT(*) as donations,
            DATE_FORMAT(donation_date,'%Y-%m') as sort_key
     FROM donations 
     WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY sort_key
     ORDER BY sort_key"
);

$pageTitle = 'Dashboard';
include '../includes/header.php';
?>

<!-- EMERGENCY BANNER -->
<?php if($stats['emergency_requests'] > 0): ?>
<div class="alert alert-danger" style="display:flex;align-items:center;gap:12px;animation:pulse 1s infinite alternate;">
    <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
    <strong><?= $stats['emergency_requests'] ?> EMERGENCY blood request(s) pending immediate attention!</strong>
    <a href="requests.php?urgency=Emergency" class="btn btn-danger btn-sm" style="margin-left:auto">View Now</a>
</div>
<style>@keyframes pulse{from{opacity:1}to{opacity:.8}}</style>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stats-grid">
    <div class="stat-card" style="--accent-color:var(--red)">
        <div class="stat-icon">🩸</div>
        <div class="stat-value"><?= number_format($stats['available_units']) ?></div>
        <div class="stat-label">Available Blood Units</div>
        <?php if($stats['expiring_units']>0): ?>
        <div class="stat-change down"><i class="fa-solid fa-clock"></i> <?= $stats['expiring_units'] ?> expiring in 7 days</div>
        <?php endif; ?>
    </div>
    <div class="stat-card" style="--accent-color:var(--info)">
        <div class="stat-icon" style="background:var(--info)">👥</div>
        <div class="stat-value"><?= number_format($stats['total_donors']) ?></div>
        <div class="stat-label">Registered Donors</div>
        <div class="stat-change up"><i class="fa-solid fa-arrow-up"></i> <?= $stats['today_donations'] ?> donation(s) today</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--warning)">
        <div class="stat-icon" style="background:var(--warning)">📋</div>
        <div class="stat-value"><?= number_format($stats['pending_requests']) ?></div>
        <div class="stat-label">Pending Requests</div>
        <?php if($stats['emergency_requests']>0): ?>
        <div class="stat-change down"><i class="fa-solid fa-triangle-exclamation"></i> <?= $stats['emergency_requests'] ?> emergency</div>
        <?php endif; ?>
    </div>
    <div class="stat-card" style="--accent-color:var(--success)">
        <div class="stat-icon" style="background:var(--success)">🏥</div>
        <div class="stat-value"><?= number_format($stats['total_patients']) ?></div>
        <div class="stat-label">Registered Patients</div>
        <div class="stat-change up"><i class="fa-solid fa-arrow-up"></i> <?= $stats['total_issued'] ?> units issued total</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--purple)">
        <div class="stat-icon" style="background:var(--purple)">💉</div>
        <div class="stat-value"><?= number_format($stats['total_donations']) ?></div>
        <div class="stat-label">Total Donations</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--cyan)">
        <div class="stat-icon" style="background:var(--cyan)">🔬</div>
        <div class="stat-value"><?= number_format($stats['total_issued']) ?></div>
        <div class="stat-label">Units Distributed</div>
    </div>
</div>

<!-- BLOOD INVENTORY GRID -->
<div class="card mb-6">
    <div class="card-header">
        <div style="width:8px;height:8px;background:var(--red);border-radius:50%;box-shadow:0 0 8px var(--red)"></div>
        <h3>Blood Inventory by Group</h3>
        <div class="card-actions">
            <a href="inventory.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-boxes-stacked"></i> Full Inventory</a>
            <a href="stock_status.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chart-bar"></i> Stock Status</a>
        </div>
    </div>
    <div class="card-body">
        <?php
        $groups = ['O+','O-','A+','A-','B+','B-','AB+','AB-'];
        $groupColors = ['O+'=>'#2ecc71','O-'=>'#27ae60','A+'=>'#e74c3c','A-'=>'#c0392b','B+'=>'#e67e22','B-'=>'#d35400','AB+'=>'#9b59b6','AB-'=>'#8e44ad'];
        $groupCounts = [];
        foreach($stockStatus as $s) {
            if(!isset($groupCounts[$s['blood_group']])) $groupCounts[$s['blood_group']] = 0;
            $groupCounts[$s['blood_group']] += $s['available'];
        }
        ?>
        <div class="blood-grid">
        <?php foreach($groups as $g):
            $count = $groupCounts[$g] ?? 0;
            $cls = $count == 0 ? 'critical' : ($count < 5 ? 'critical' : ($count < 10 ? 'low' : 'normal'));
            $color = $groupColors[$g];
        ?>
        <div class="blood-card <?= $cls ?>">
            <div class="blood-type" style="color:<?= $color ?>"><?= $g ?></div>
            <div class="blood-count" style="color:<?= $count<5?'#EF4444':($count<10?'#F59E0B':'#10B981') ?>"><?= $count ?></div>
            <div style="font-size:11px;color:var(--text3);margin-bottom:10px;">units</div>
            <div class="progress-wrap">
                <div class="progress-bar" style="width:<?= min(100,($count/20)*100) ?>%;background:<?= $count<5?'#EF4444':($count<10?'#F59E0B':'#10B981') ?>"></div>
            </div>
            <?php if($cls==='critical' && $count > 0): ?>
            <div style="font-size:10px;color:#EF4444;margin-top:6px;font-weight:600;">⚠ CRITICAL</div>
            <?php elseif($cls==='critical' && $count == 0): ?>
            <div style="font-size:10px;color:#EF4444;margin-top:6px;font-weight:600;">✕ OUT OF STOCK</div>
            <?php elseif($cls==='low'): ?>
            <div style="font-size:10px;color:#F59E0B;margin-top:6px;font-weight:600;">⚡ LOW</div>
            <?php else: ?>
            <div style="font-size:10px;color:#10B981;margin-top:6px;">● Available</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>

        <?php if(!empty($lowStock)): ?>
        <div class="alert alert-warning mt-4" style="margin-top:16px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Low Stock Alert:</strong> 
            <?= implode(', ', array_map(fn($l) => $l['blood_group'].' ('.$l['component_type'].'): '.$l['available_units'].' units', $lowStock)) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- TWO COLUMNS -->
<div class="grid-2 mb-6">
    <!-- Recent Donations -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-droplet" style="color:var(--red)"></i>
            <h3>Recent Donations</h3>
            <div class="card-actions"><a href="donations.php" class="btn btn-secondary btn-sm">View All</a></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Donor</th><th>Blood</th><th>Type</th><th>Status</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach($recentDonations as $don): ?>
                <tr>
                    <td><div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($don['donor_name']) ?></div><div style="font-size:11px;color:var(--text3)"><?= $don['donation_code'] ?></div></td>
                    <td><?= bloodGroupBadge($don['blood_group']) ?></td>
                    <td style="font-size:12px"><?= $don['donation_type'] ?></td>
                    <td><?= statusBadge($don['status']) ?></td>
                    <td style="font-size:12px;color:var(--text3)"><?= timeAgo($don['donation_date']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($recentDonations)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:24px">No recent donations</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending Requests -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-file-medical" style="color:var(--warning)"></i>
            <h3>Pending Requests</h3>
            <div class="card-actions"><a href="requests.php" class="btn btn-secondary btn-sm">View All</a></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Patient</th><th>Blood</th><th>Units</th><th>Priority</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach($pendingRequests as $req): ?>
                <tr>
                    <td><div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($req['patient_name']) ?></div><div style="font-size:11px;color:var(--text3)"><?= $req['request_code'] ?></div></td>
                    <td><?= bloodGroupBadge($req['blood_group']) ?></td>
                    <td style="font-weight:700"><?= $req['units_requested'] ?></td>
                    <td><?= statusBadge($req['urgency']) ?></td>
                    <td>
                        <a href="distribution.php?request_id=<?= $req['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-truck-medical"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pendingRequests)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:24px">No pending requests</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EXPIRING SOON + CHART ROW -->
<div class="grid-2">
    <?php if(!empty($expiring)): ?>
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-clock" style="color:var(--warning)"></i>
            <h3>Expiring Soon (7 days)</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Unit Code</th><th>Group</th><th>Component</th><th>Expires</th><th>Days Left</th></tr></thead>
                <tbody>
                <?php foreach(array_slice($expiring,0,6) as $u):
                    $daysLeft = (strtotime($u['expiry_date']) - time()) / 86400;
                ?>
                <tr>
                    <td style="font-size:12px;font-family:monospace"><?= $u['unit_code'] ?></td>
                    <td><?= bloodGroupBadge($u['blood_group']) ?></td>
                    <td style="font-size:12px"><?= $u['component_type'] ?></td>
                    <td style="font-size:12px"><?= date('d M Y',strtotime($u['expiry_date'])) ?></td>
                    <td><span style="color:<?= $daysLeft<=3?'#EF4444':'#F59E0B' ?>;font-weight:700"><?= ceil($daysLeft) ?>d</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Donation Trend Chart -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-chart-line" style="color:var(--info)"></i>
            <h3>Donation Trend (6 months)</h3>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="180"></canvas>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const months = <?= json_encode(array_column($monthlyData, 'month')) ?>;
const donations = <?= json_encode(array_column($monthlyData, 'donations')) ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Donations',
            data: donations,
            borderColor: '#C1121F',
            backgroundColor: 'rgba(193,18,31,0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#C1121F',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8B90A4' } },
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8B90A4' }, beginAtZero: true }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
