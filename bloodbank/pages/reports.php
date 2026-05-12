<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

$reportType = $_GET['type'] ?? 'summary';
$dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
$dateTo     = $_GET['date_to']   ?? date('Y-m-d');

// ---- Summary Stats ----
$summary = [
    'donations_period'  => $db->fetch("SELECT COUNT(*) n FROM donations WHERE donation_date BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
    'units_added'       => $db->fetch("SELECT COUNT(*) n FROM blood_units WHERE collection_date BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
    'units_issued'      => $db->fetch("SELECT COUNT(*) n FROM blood_distributions WHERE DATE(issue_date) BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
    'requests_period'   => $db->fetch("SELECT COUNT(*) n FROM blood_requests WHERE DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
    'new_donors'        => $db->fetch("SELECT COUNT(*) n FROM donors WHERE DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
    'emergency_handled' => $db->fetch("SELECT COUNT(*) n FROM blood_requests WHERE urgency='Emergency' AND DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
    'wastage'           => $db->fetch("SELECT COUNT(*) n FROM blood_units WHERE (status='Expired' OR (status='Available' AND expiry_date<=CURDATE())) AND collection_date BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
    'adverse_reactions' => $db->fetch("SELECT COUNT(*) n FROM blood_distributions WHERE adverse_reaction=1 AND DATE(issue_date) BETWEEN ? AND ?", [$dateFrom, $dateTo])['n'],
];

// Blood group distribution
$bgDistrib = $db->fetchAll("SELECT blood_group, COUNT(*) as total FROM donors GROUP BY blood_group ORDER BY blood_group");
$inventoryByGroup = $db->fetchAll(
    "SELECT blood_group, component_type,
            SUM(CASE WHEN status='Available' AND expiry_date>CURDATE() THEN 1 ELSE 0 END) as available,
            COUNT(*) as total
     FROM blood_units GROUP BY blood_group, component_type ORDER BY blood_group"
);

// Top donors
$topDonors = $db->fetchAll("SELECT full_name, blood_group, total_donations FROM donors ORDER BY total_donations DESC LIMIT 10");

// Monthly trend (12 months)
$monthlyTrend = $db->fetchAll(
    "SELECT DATE_FORMAT(donation_date,'%b %Y') month, COUNT(*) donations, DATE_FORMAT(donation_date,'%Y-%m') s
     FROM donations WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY s ORDER BY s"
);

// Fulfillment rate
$fulfilRate = $db->fetch(
    "SELECT 
        COUNT(*) total,
        SUM(CASE WHEN status='Fulfilled' THEN 1 ELSE 0 END) fulfilled,
        SUM(CASE WHEN status='Partially Fulfilled' THEN 1 ELSE 0 END) partial,
        SUM(CASE WHEN status='Rejected' OR status='Cancelled' THEN 1 ELSE 0 END) rejected
     FROM blood_requests"
);

// Transfusion outcomes
$outcomes = $db->fetchAll(
    "SELECT transfusion_status, COUNT(*) n FROM blood_distributions GROUP BY transfusion_status"
);

$pageTitle   = 'Reports & Analytics';
$pageSubtitle= 'Data Insights';
include '../includes/header.php';
?>

<!-- Date Filter -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="type" value="<?= $reportType ?>">
            <div class="form-group" style="margin:0">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= $dateFrom ?>" style="width:auto">
            </div>
            <div class="form-group" style="margin:0">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= $dateTo ?>" style="width:auto">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i> Generate Report</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-size:12px;color:var(--text2)">
                Period: <strong style="color:var(--text)"><?= formatDate($dateFrom) ?> — <?= formatDate($dateTo) ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Summary KPIs -->
<div class="stats-grid mb-6">
    <div class="stat-card" style="--accent-color:var(--info)">
        <div class="stat-icon" style="background:var(--info)"><i class="fa-solid fa-droplet"></i></div>
        <div class="stat-value"><?= $summary['donations_period'] ?></div>
        <div class="stat-label">Donations (period)</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--success)">
        <div class="stat-icon" style="background:var(--success)"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="stat-value"><?= $summary['units_added'] ?></div>
        <div class="stat-label">Units Added</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--red)">
        <div class="stat-icon"><i class="fa-solid fa-truck-medical"></i></div>
        <div class="stat-value"><?= $summary['units_issued'] ?></div>
        <div class="stat-label">Units Issued</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--warning)">
        <div class="stat-icon" style="background:var(--warning)"><i class="fa-solid fa-users"></i></div>
        <div class="stat-value"><?= $summary['new_donors'] ?></div>
        <div class="stat-label">New Donors</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--purple)">
        <div class="stat-icon" style="background:var(--purple)"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-value"><?= $summary['emergency_handled'] ?></div>
        <div class="stat-label">Emergencies Handled</div>
    </div>
    <div class="stat-card" style="--accent-color:#EF4444">
        <div class="stat-icon" style="background:#EF4444"><i class="fa-solid fa-trash"></i></div>
        <div class="stat-value"><?= $summary['wastage'] ?></div>
        <div class="stat-label">Units Wasted/Expired</div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid-2 mb-6">
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-chart-line" style="color:var(--info)"></i>
            <h3>Monthly Donation Trend</h3>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="200"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-chart-pie" style="color:var(--red)"></i>
            <h3>Donor Blood Group Distribution</h3>
        </div>
        <div class="card-body">
            <canvas id="bgChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Fulfillment Rate -->
<div class="grid-2 mb-6">
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-chart-bar" style="color:var(--success)"></i>
            <h3>Request Fulfillment Rate</h3>
        </div>
        <div class="card-body">
            <?php
            $ft = max(1, $fulfilRate['total']);
            $fp = round(($fulfilRate['fulfilled']/$ft)*100,1);
            $pp = round(($fulfilRate['partial']/$ft)*100,1);
            $rp = round(($fulfilRate['rejected']/$ft)*100,1);
            ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
                <div style="text-align:center;padding:20px;background:rgba(16,185,129,.1);border-radius:12px">
                    <div style="font-size:40px;font-weight:800;color:var(--success);font-family:'Syne',sans-serif"><?= $fp ?>%</div>
                    <div style="color:var(--text2);font-size:13px">Fully Fulfilled</div>
                    <div style="font-size:12px;color:var(--text3)"><?= $fulfilRate['fulfilled'] ?> requests</div>
                </div>
                <div style="text-align:center;padding:20px;background:rgba(245,158,11,.1);border-radius:12px">
                    <div style="font-size:40px;font-weight:800;color:var(--warning);font-family:'Syne',sans-serif"><?= $pp ?>%</div>
                    <div style="color:var(--text2);font-size:13px">Partially Fulfilled</div>
                    <div style="font-size:12px;color:var(--text3)"><?= $fulfilRate['partial'] ?> requests</div>
                </div>
            </div>
            <canvas id="fulfillChart" height="150"></canvas>
        </div>
    </div>

    <!-- Top Donors -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-trophy" style="color:var(--warning)"></i>
            <h3>Top Donors</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Donor</th><th>Blood Group</th><th>Donations</th></tr></thead>
                <tbody>
                <?php foreach($topDonors as $i => $td): ?>
                <tr>
                    <td>
                        <?php if($i===0): ?><span style="font-size:20px">🥇</span>
                        <?php elseif($i===1): ?><span style="font-size:20px">🥈</span>
                        <?php elseif($i===2): ?><span style="font-size:20px">🥉</span>
                        <?php else: ?><span style="color:var(--text3)"><?= $i+1 ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($td['full_name']) ?></td>
                    <td><?= bloodGroupBadge($td['blood_group']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="font-size:18px;font-weight:700;color:var(--red)"><?= $td['total_donations'] ?></span>
                            <div class="progress-wrap" style="width:60px">
                                <div class="progress-bar" style="width:<?= min(100,($td['total_donations']/max(1,$topDonors[0]['total_donations']))*100) ?>%;background:var(--red)"></div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Inventory Summary Table -->
<div class="card mb-6">
    <div class="card-header">
        <i class="fa-solid fa-table" style="color:var(--cyan)"></i>
        <h3>Current Inventory Summary by Blood Group & Component</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Blood Group</th><th>Component</th><th>Available</th><th>Total Ever</th><th>Stock Bar</th></tr></thead>
            <tbody>
            <?php foreach($inventoryByGroup as $row): ?>
            <tr>
                <td><?= bloodGroupBadge($row['blood_group']) ?></td>
                <td style="font-size:13px"><?= $row['component_type'] ?></td>
                <td>
                    <span style="font-size:18px;font-weight:700;color:<?= $row['available']<5?'#EF4444':($row['available']<10?'#F59E0B':'#10B981') ?>">
                        <?= $row['available'] ?>
                    </span>
                </td>
                <td style="color:var(--text2)"><?= $row['total'] ?></td>
                <td style="min-width:120px">
                    <div class="progress-wrap">
                        <div class="progress-bar" style="width:<?= min(100,($row['available']/max(1,20))*100) ?>%;background:<?= $row['available']<5?'#EF4444':($row['available']<10?'#F59E0B':'#10B981') ?>"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Audit/Activity Summary -->
<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-clock-rotate-left" style="color:var(--text2)"></i>
        <h3>Recent Activity Log</h3>
        <div class="card-actions"><a href="audit_log.php" class="btn btn-secondary btn-sm">Full Log</a></div>
    </div>
    <div class="table-wrap">
        <?php $logs = $db->fetchAll("SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 15"); ?>
        <table>
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th></tr></thead>
            <tbody>
            <?php foreach($logs as $log): ?>
            <tr>
                <td style="font-size:12px;color:var(--text3)"><?= formatDate($log['created_at'],'d M H:i') ?></td>
                <td style="font-size:13px"><?= htmlspecialchars($log['full_name']??'System') ?></td>
                <td><span class="badge badge-info" style="font-size:10px"><?= $log['action'] ?></span></td>
                <td style="font-size:12px;color:var(--text2)"><?= $log['module'] ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($log['description']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const months = <?= json_encode(array_column($monthlyTrend,'month')) ?>;
const donCounts = <?= json_encode(array_column($monthlyTrend,'donations')) ?>;
new Chart(document.getElementById('trendChart'), {
    type:'line',
    data:{labels:months,datasets:[{label:'Donations',data:donCounts,borderColor:'#3B82F6',backgroundColor:'rgba(59,130,246,.1)',fill:true,tension:.4,borderWidth:2,pointBackgroundColor:'#3B82F6',pointRadius:4}]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#8B90A4'}},y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#8B90A4'},beginAtZero:true}}}
});

const bgLabels = <?= json_encode(array_column($bgDistrib,'blood_group')) ?>;
const bgData   = <?= json_encode(array_column($bgDistrib,'total')) ?>;
const bgColors = ['#e74c3c','#c0392b','#e67e22','#d35400','#9b59b6','#8e44ad','#2ecc71','#27ae60'];
new Chart(document.getElementById('bgChart'), {
    type:'doughnut',
    data:{labels:bgLabels,datasets:[{data:bgData,backgroundColor:bgColors,borderColor:'rgba(0,0,0,0)',borderWidth:2}]},
    options:{responsive:true,plugins:{legend:{position:'right',labels:{color:'#8B90A4',padding:12,font:{size:12}}}}}
});

new Chart(document.getElementById('fulfillChart'), {
    type:'bar',
    data:{
        labels:['Fulfilled','Partial','Rejected','Pending'],
        datasets:[{data:[<?= $fulfilRate['fulfilled']?>,<?= $fulfilRate['partial']?>,<?= $fulfilRate['rejected']?>,<?= $fulfilRate['total']-$fulfilRate['fulfilled']-$fulfilRate['partial']-$fulfilRate['rejected'] ?>],
        backgroundColor:['#10B981','#F59E0B','#EF4444','#94A3B8']}]
    },
    options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#8B90A4'}},y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#8B90A4'},beginAtZero:true}}}
});
</script>

<?php include '../includes/footer.php'; ?>
