<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

if($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if($action === 'update_screening') {
        $unitId = intval($_POST['unit_id']);
        $hiv    = sanitize($_POST['hiv_status']);
        $hbsag  = sanitize($_POST['hbsag_status']);
        $hcv    = sanitize($_POST['hcv_status']);
        $syphilis = sanitize($_POST['syphilis_status']);
        $malaria  = sanitize($_POST['malaria_status']);

        // Determine overall screening status
        $isReactive = in_array('Reactive', [$hiv, $hbsag, $hcv, $syphilis, $malaria]);
        $allDone    = !in_array('Pending', [$hiv, $hbsag, $hcv, $syphilis, $malaria]);

        $screenStatus = 'Pending';
        $unitStatus   = 'Quarantine';

        if($allDone) {
            if($isReactive) {
                $screenStatus = 'Reactive';
                $unitStatus   = 'Discarded';
            } else {
                $screenStatus = 'Cleared';
                $unitStatus   = 'Available';
            }
        }

        $db->execute(
            "UPDATE blood_units SET hiv_status=?, hbsag_status=?, hcv_status=?, syphilis_status=?, malaria_status=?, screening_status=?, status=? WHERE id=?",
            [$hiv, $hbsag, $hcv, $syphilis, $malaria, $screenStatus, $unitStatus, $unitId]
        );

        // Log the tests
        $tests = [
            'HIV'      => $hiv,
            'HBsAg'    => $hbsag,
            'HCV'      => $hcv,
            'Syphilis' => $syphilis,
            'Malaria'  => $malaria,
        ];
        foreach($tests as $testType => $result) {
            if($result !== 'Pending') {
                $tCode = 'TEST-' . date('YmdHis') . '-' . rand(100,999);
                $db->execute(
                    "INSERT INTO lab_tests (test_code, unit_id, test_type, test_date, result, status, performed_by)
                     VALUES (?,?,?,NOW(),?,?,?)",
                    [$tCode, $unitId, $testType, $result, 'Completed', $_SESSION['user_id']]
                );
            }
        }

        $msg = $isReactive ? "Unit REACTIVE — marked as Discarded." : ($allDone ? "Unit cleared — now Available in inventory." : "Partial screening saved.");
        $auth->logActivity('Screening', 'Lab', "Screened unit #$unitId: $screenStatus");
        $_SESSION[$isReactive ? 'error' : 'success'] = $msg;
        header('Location: screening.php'); exit;
    }
}

// Units pending screening
$pendingUnits = $db->fetchAll(
    "SELECT bu.*, d.full_name donor_name, dn.donation_code
     FROM blood_units bu
     LEFT JOIN donations dn ON bu.donation_id=dn.id
     LEFT JOIN donors d ON dn.donor_id=d.id
     WHERE bu.screening_status='Pending' OR bu.status='Quarantine'
     ORDER BY bu.collection_date ASC"
);

// Recently screened
$recentlyScreened = $db->fetchAll(
    "SELECT bu.*, d.full_name donor_name FROM blood_units bu
     LEFT JOIN donations dn ON bu.donation_id=dn.id
     LEFT JOIN donors d ON dn.donor_id=d.id
     WHERE bu.screening_status IN ('Cleared','Reactive')
     ORDER BY bu.updated_at DESC LIMIT 20"
);

// Stats
$screenStats = $db->fetch(
    "SELECT
        SUM(CASE WHEN screening_status='Pending' THEN 1 ELSE 0 END) pending,
        SUM(CASE WHEN screening_status='Cleared' THEN 1 ELSE 0 END) cleared,
        SUM(CASE WHEN screening_status='Reactive' THEN 1 ELSE 0 END) reactive
     FROM blood_units"
);

$pageTitle   = 'Screening & Lab Tests';
$pageSubtitle = 'Blood Safety Testing';
include '../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid mb-6">
    <div class="stat-card" style="--accent-color:var(--warning)">
        <div class="stat-icon" style="background:var(--warning)"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-value"><?= $screenStats['pending'] ?></div>
        <div class="stat-label">Pending Screening</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--success)">
        <div class="stat-icon" style="background:var(--success)"><i class="fa-solid fa-shield-check"></i></div>
        <div class="stat-value"><?= $screenStats['cleared'] ?></div>
        <div class="stat-label">Units Cleared</div>
    </div>
    <div class="stat-card" style="--accent-color:#EF4444">
        <div class="stat-icon" style="background:#EF4444"><i class="fa-solid fa-biohazard"></i></div>
        <div class="stat-value"><?= $screenStats['reactive'] ?></div>
        <div class="stat-label">Reactive / Discarded</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--info)">
        <div class="stat-icon" style="background:var(--info)"><i class="fa-solid fa-percent"></i></div>
        <div class="stat-value">
            <?php $total = max(1, ($screenStats['cleared']+$screenStats['reactive']));
                  echo round(($screenStats['cleared']/$total)*100,1); ?>%
        </div>
        <div class="stat-label">Clearance Rate</div>
    </div>
</div>

<div class="tabs" data-tabs>
    <button class="tab-btn active" data-tab="tab-pending">Pending (<?= count($pendingUnits) ?>)</button>
    <button class="tab-btn" data-tab="tab-screened">Recently Screened</button>
</div>

<!-- PENDING SCREENING -->
<div class="tab-content active" id="tab-pending">
<?php if(empty($pendingUnits)): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text3)">
    <i class="fa-solid fa-check-circle fa-3x" style="color:var(--success);margin-bottom:12px;display:block"></i>
    All blood units have been screened!
</div></div>
<?php endif; ?>

<?php foreach($pendingUnits as $unit): ?>
<div class="card mb-4" style="border-color:rgba(245,158,11,.3)">
    <div class="card-header" style="background:rgba(245,158,11,.05)">
        <i class="fa-solid fa-flask" style="color:var(--warning)"></i>
        <div>
            <div style="font-family:monospace;font-weight:700;color:var(--info)"><?= $unit['unit_code'] ?></div>
            <div style="font-size:12px;color:var(--text3)"><?= $unit['component_type'] ?> · Collected: <?= formatDate($unit['collection_date']) ?></div>
        </div>
        <div style="margin-left:20px"><?= bloodGroupBadge($unit['blood_group']) ?></div>
        <div style="margin-left:12px">
            <div style="font-size:12px;color:var(--text2)">Donor: <strong><?= htmlspecialchars($unit['donor_name']??'Unknown') ?></strong></div>
            <div style="font-size:11px;color:var(--text3)">Expiry: <?= formatDate($unit['expiry_date']) ?></div>
        </div>
        <div style="margin-left:auto"><?= statusBadge($unit['screening_status']) ?></div>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="update_screening">
    <input type="hidden" name="unit_id" value="<?= $unit['id'] ?>">
    <div class="card-body">
        <div style="font-size:12px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px">
            Mandatory Screening Panel
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <?php
            $tests = [
                'hiv_status'      => ['label' => 'HIV 1&2', 'icon' => '🔴', 'current' => $unit['hiv_status']],
                'hbsag_status'    => ['label' => 'HBsAg (Hepatitis B)', 'icon' => '🟠', 'current' => $unit['hbsag_status']],
                'hcv_status'      => ['label' => 'HCV (Hepatitis C)', 'icon' => '🟡', 'current' => $unit['hcv_status']],
                'syphilis_status' => ['label' => 'Syphilis (VDRL)', 'icon' => '🟣', 'current' => $unit['syphilis_status']],
                'malaria_status'  => ['label' => 'Malaria Parasite', 'icon' => '🟢', 'current' => $unit['malaria_status']],
            ];
            foreach($tests as $field => $test):
            ?>
            <div style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:6px">
                    <?= $test['icon'] ?> <?= $test['label'] ?>
                </div>
                <select name="<?= $field ?>" style="width:100%;font-size:13px">
                    <option value="Pending" <?= $test['current']==='Pending'?'selected':'' ?>>⏳ Pending</option>
                    <option value="Non-Reactive" <?= $test['current']==='Non-Reactive'?'selected':'' ?> style="color:#10B981">✓ Non-Reactive</option>
                    <option value="Reactive" <?= $test['current']==='Reactive'?'selected':'' ?> style="color:#EF4444">⚠ Reactive</option>
                </select>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="alert alert-warning" style="margin-top:16px">
            <i class="fa-solid fa-info-circle"></i>
            All <strong>Reactive</strong> results will automatically mark this unit as <strong>Discarded</strong> and remove it from inventory.
            All <strong>Non-Reactive</strong> results will mark the unit <strong>Available</strong>.
        </div>
    </div>
    <div class="modal-footer" style="padding:16px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-vial"></i> Save Screening Results</button>
    </div>
    </form>
</div>
<?php endforeach; ?>
</div>

<!-- RECENTLY SCREENED -->
<div class="tab-content" id="tab-screened">
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Unit Code</th><th>Group</th><th>Component</th><th>Donor</th>
                <th>HIV</th><th>HBsAg</th><th>HCV</th><th>Syphilis</th><th>Malaria</th>
                <th>Overall</th><th>Unit Status</th>
            </tr></thead>
            <tbody>
            <?php foreach($recentlyScreened as $u):
                $tests = ['hiv_status','hbsag_status','hcv_status','syphilis_status','malaria_status'];
            ?>
            <tr>
                <td style="font-family:monospace;font-size:11px;color:var(--info)"><?= $u['unit_code'] ?></td>
                <td><?= bloodGroupBadge($u['blood_group']) ?></td>
                <td style="font-size:12px"><?= $u['component_type'] ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($u['donor_name']??'—') ?></td>
                <?php foreach($tests as $t): ?>
                <td>
                    <?php if($u[$t]==='Non-Reactive'): ?>
                    <span style="color:var(--success);font-size:12px;font-weight:600">NR</span>
                    <?php elseif($u[$t]==='Reactive'): ?>
                    <span style="color:#EF4444;font-size:12px;font-weight:700">R!</span>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td><?= statusBadge($u['screening_status']) ?></td>
                <td><?= statusBadge($u['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<?php include '../includes/footer.php'; ?>
