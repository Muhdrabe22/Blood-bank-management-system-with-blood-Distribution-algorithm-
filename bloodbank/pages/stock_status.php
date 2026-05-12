<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db   = Database::getInstance();
$algo = new BloodDistributionAlgorithm();
$inv  = new InventoryManager();

$stockStatus = $algo->getStockStatus();
$lowStock    = $inv->checkLowStock();
$expiring7   = $inv->getExpiringSoon(7);
$expiring3   = $inv->getExpiringSoon(3);

// Organize stock by group
$stockByGroup = [];
foreach($stockStatus as $s) {
    $stockByGroup[$s['blood_group']][$s['component_type']] = $s;
}

// Overall availability
$overallAvail = $db->fetchAll(
    "SELECT blood_group, COUNT(*) available FROM blood_units
     WHERE status='Available' AND expiry_date>CURDATE()
     GROUP BY blood_group ORDER BY blood_group"
);

// Cross-compatibility quick view
$compatMatrix = $db->fetchAll(
    "SELECT recipient_group, donor_group, is_compatible, compatibility_score
     FROM blood_compatibility ORDER BY recipient_group, compatibility_score DESC"
);
$matrix = [];
foreach($compatMatrix as $row) {
    $matrix[$row['recipient_group']][$row['donor_group']] = $row;
}

$pageTitle   = 'Stock Status';
$pageSubtitle = 'Real-time Blood Bank Overview';
include '../includes/header.php';
?>

<!-- Alerts -->
<?php if(!empty($expiring3)): ?>
<div class="alert alert-danger">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <strong><?= count($expiring3) ?> unit(s) expiring within 3 days!</strong> Prioritize for distribution.
    <a href="distribution.php" class="btn btn-danger btn-sm" style="margin-left:auto">Distribute Now</a>
</div>
<?php endif; ?>
<?php if(!empty($lowStock)): ?>
<div class="alert alert-warning">
    <i class="fa-solid fa-chart-bar"></i>
    <strong>Low stock detected:</strong> <?= implode(', ', array_map(fn($l) => $l['blood_group'].' ('.$l['available_units'].'u)', $lowStock)) ?>
</div>
<?php endif; ?>

<!-- OVERVIEW CARDS -->
<div class="blood-grid mb-6">
<?php
$groups      = ['O+','O-','A+','A-','B+','B-','AB+','AB-'];
$groupColors = ['O+'=>'#2ecc71','O-'=>'#27ae60','A+'=>'#e74c3c','A-'=>'#c0392b','B+'=>'#e67e22','B-'=>'#d35400','AB+'=>'#9b59b6','AB-'=>'#8e44ad'];
$groupAvail  = [];
foreach($overallAvail as $ga) $groupAvail[$ga['blood_group']] = $ga['available'];

foreach($groups as $g):
    $count   = $groupAvail[$g] ?? 0;
    $maxView = 25;
    $pct     = min(100, ($count/$maxView)*100);
    $cls     = $count === 0 ? 'critical' : ($count < 5 ? 'critical' : ($count < 10 ? 'low' : 'normal'));
    $color   = $groupColors[$g];
    $barColor= $count === 0 ? '#EF4444' : ($count < 5 ? '#EF4444' : ($count < 10 ? '#F59E0B' : '#10B981'));
?>
<div class="blood-card <?= $cls ?>" style="cursor:pointer" onclick="toggleDetail('detail-<?= str_replace(['+','-'],['P','N'],$g) ?>')">
    <div class="blood-type" style="color:<?= $color ?>"><?= $g ?></div>
    <div class="blood-count" style="color:<?= $barColor ?>;font-family:'Syne',sans-serif"><?= $count ?></div>
    <div style="font-size:11px;color:var(--text3);margin-bottom:10px">available units</div>
    <div class="progress-wrap" style="height:8px;margin-bottom:8px">
        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
    </div>
    <?php
    $exp = count(array_filter($expiring7, fn($u) => $u['blood_group']===$g));
    if($cls === 'critical' && $count === 0): ?>
    <span class="badge badge-danger" style="font-size:10px">OUT OF STOCK</span>
    <?php elseif($cls === 'critical'): ?>
    <span class="badge badge-danger" style="font-size:10px">⚠ CRITICAL</span>
    <?php elseif($cls === 'low'): ?>
    <span class="badge badge-warning" style="font-size:10px">LOW STOCK</span>
    <?php else: ?>
    <span class="badge badge-success" style="font-size:10px">ADEQUATE</span>
    <?php endif; ?>
    <?php if($exp > 0): ?>
    <div style="font-size:10px;color:var(--warning);margin-top:4px">⏰ <?= $exp ?> expiring soon</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<!-- DETAILED BREAKDOWN BY GROUP -->
<?php foreach($groups as $g):
    $safeId = str_replace(['+','-'],['P','N'],$g);
    $color  = $groupColors[$g];
    $comps  = $stockByGroup[$g] ?? [];
?>
<div id="detail-<?= $safeId ?>" class="card mb-4" style="display:none;border-color:<?= $color ?>55">
    <div class="card-header" style="background:<?= $color ?>11">
        <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:<?= $color ?>"><?= $g ?></div>
        <div style="margin-left:12px"><div style="font-size:14px;font-weight:600">Blood Group <?= $g ?> — Stock Detail</div></div>
        <button class="btn btn-secondary btn-sm" style="margin-left:auto" onclick="toggleDetail('detail-<?= $safeId ?>')">Close ✕</button>
    </div>
    <div class="card-body">
        <?php if(empty($comps)): ?>
        <div style="color:var(--text3);text-align:center;padding:20px">No stock data available for <?= $g ?>.</div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
        <?php foreach($comps as $comp => $data): ?>
        <div style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:16px">
            <div style="font-size:12px;font-weight:700;color:var(--text2);margin-bottom:10px"><?= $comp ?></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:12px;color:var(--success)">Available</span>
                <span style="font-weight:700;color:var(--success)"><?= $data['available'] ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:12px;color:var(--warning)">Reserved</span>
                <span style="font-weight:700;color:var(--warning)"><?= $data['reserved'] ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:12px;color:var(--info)">Issued</span>
                <span style="font-weight:700;color:var(--info)"><?= $data['issued'] ?></span>
            </div>
            <?php if($data['expiring_soon'] > 0): ?>
            <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px solid var(--border)">
                <span style="font-size:11px;color:var(--warning)">⏰ Expiring 7d</span>
                <span style="font-weight:700;color:var(--warning)"><?= $data['expiring_soon'] ?></span>
            </div>
            <?php endif; ?>
            <?php if($data['nearest_expiry']): ?>
            <div style="font-size:11px;color:var(--text3);margin-top:4px">Nearest expiry: <?= formatDate($data['nearest_expiry']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- COMPATIBILITY MATRIX -->
<div class="card mb-6">
    <div class="card-header">
        <i class="fa-solid fa-table" style="color:var(--purple)"></i>
        <h3>Blood Compatibility Matrix</h3>
        <span style="font-size:12px;color:var(--text3);margin-left:8px">(Rows = Recipient · Columns = Donor)</span>
    </div>
    <div class="card-body">
        <div class="table-wrap">
        <table style="text-align:center">
            <thead>
                <tr>
                    <th style="min-width:80px">Recipient ↓ / Donor →</th>
                    <?php foreach($groups as $g): ?>
                    <th><?= bloodGroupBadge($g) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach($groups as $recipient): ?>
            <tr>
                <td><?= bloodGroupBadge($recipient) ?></td>
                <?php foreach($groups as $donor): ?>
                <?php $cell = $matrix[$recipient][$donor] ?? null; $compat = $cell && $cell['is_compatible']; ?>
                <td style="text-align:center">
                    <?php if($compat): ?>
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:24px;border-radius:6px;font-size:11px;font-weight:700;background:rgba(16,185,129,<?= $cell['compatibility_score']/12 ?>);color:#10B981;border:1px solid rgba(16,185,129,.4)">
                        <?= $cell['compatibility_score'] ?>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:16px">✕</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div style="margin-top:14px;display:flex;gap:16px;font-size:12px;color:var(--text2);flex-wrap:wrap">
            <div><span style="background:rgba(16,185,129,.4);color:#10B981;padding:2px 8px;border-radius:4px;font-weight:700">10</span> = Exact match (best)</div>
            <div><span style="background:rgba(16,185,129,.3);color:#10B981;padding:2px 8px;border-radius:4px;font-weight:700">5–9</span> = Compatible</div>
            <div><span style="color:var(--text3)">✕</span> = Incompatible</div>
        </div>
    </div>
</div>

<!-- EXPIRING UNITS TABLE -->
<?php if(!empty($expiring7)): ?>
<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-clock" style="color:var(--warning)"></i>
        <h3>Units Expiring Within 7 Days</h3>
        <div class="card-actions">
            <a href="distribution.php" class="btn btn-warning btn-sm">Distribute Now</a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Unit Code</th><th>Blood Group</th><th>Component</th><th>Volume</th><th>Expiry Date</th><th>Days Left</th><th>Location</th></tr></thead>
            <tbody>
            <?php foreach($expiring7 as $u):
                $days = ceil((strtotime($u['expiry_date']) - time()) / 86400);
            ?>
            <tr>
                <td style="font-family:monospace;font-size:12px;color:var(--info)"><?= $u['unit_code'] ?></td>
                <td><?= bloodGroupBadge($u['blood_group']) ?></td>
                <td style="font-size:13px"><?= $u['component_type'] ?></td>
                <td><?= $u['volume_ml'] ?>ml</td>
                <td style="font-size:13px"><?= formatDate($u['expiry_date']) ?></td>
                <td>
                    <span style="font-weight:800;font-size:16px;color:<?= $days<=2?'#EF4444':($days<=4?'#F59E0B':'#10B981') ?>">
                        <?= $days ?>d
                    </span>
                </td>
                <td style="font-size:12px;color:var(--text3)"><?= $u['storage_location']?:'Main Bank' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function toggleDetail(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
    if(el.style.display === 'block') el.scrollIntoView({behavior:'smooth',block:'nearest'});
}
</script>

<?php include '../includes/footer.php'; ?>
