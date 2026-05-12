<?php
// pages/crossmatch.php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db   = Database::getInstance();
$algo = new BloodDistributionAlgorithm();

$result = null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['donor_group'])) {
    $donor     = sanitize($_POST['donor_group']);
    $recipient = sanitize($_POST['recipient_group']);
    $compat    = $algo->isCompatible($donor, $recipient);
    $result    = ['donor' => $donor, 'recipient' => $recipient, 'compat' => $compat];

    // Also find available units of donor group
    $availUnits = $db->fetchAll(
        "SELECT * FROM blood_units WHERE blood_group=? AND status='Available' AND screening_status='Cleared' AND expiry_date>CURDATE() ORDER BY expiry_date LIMIT 10",
        [$donor]
    );
    $result['units'] = $availUnits;
}

// History of crossmatch records
$crossmatches = $db->fetchAll(
    "SELECT bd.*, p.full_name patient_name, bu.blood_group donor_group, bu.unit_code FROM blood_distributions bd
     JOIN patients p ON bd.patient_id=p.id
     JOIN blood_units bu ON bd.unit_id=bu.id
     ORDER BY bd.created_at DESC LIMIT 20"
);

$pageTitle = 'Crossmatch';
$pageSubtitle = 'Compatibility Testing';
include '../includes/header.php';
?>

<div class="grid-2">
<div>
<div class="card mb-6">
    <div class="card-header">
        <i class="fa-solid fa-vials" style="color:var(--purple)"></i>
        <h3>Crossmatch Compatibility Check</h3>
    </div>
    <form method="POST">
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label>Donor Blood Group</label>
                <select name="donor_group" required>
                    <option value="">Select Donor Group</option>
                    <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                    <option <?= (isset($result)&&$result['donor']===$g)?'selected':'' ?>><?=$g?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Recipient Blood Group</label>
                <select name="recipient_group" required>
                    <option value="">Select Recipient Group</option>
                    <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                    <option <?= (isset($result)&&$result['recipient']===$g)?'selected':'' ?>><?=$g?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-microscope"></i> Check Compatibility</button>
    </div>
    </form>
</div>

<?php if($result): ?>
<div class="card mb-6" style="border-color:<?= $result['compat']['is_compatible']?'rgba(16,185,129,.5)':'rgba(239,68,68,.5)' ?>">
    <div class="card-body" style="text-align:center;padding:32px">
        <div style="font-size:60px;margin-bottom:16px"><?= $result['compat']['is_compatible']?'✅':'❌' ?></div>
        <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:16px">
            <?= bloodGroupBadge($result['donor']) ?>
            <span style="font-size:24px;color:var(--text3)">→</span>
            <?= bloodGroupBadge($result['recipient']) ?>
        </div>
        <?php if($result['compat']['is_compatible']): ?>
        <div style="font-size:22px;font-weight:700;color:var(--success);font-family:'Syne',sans-serif">COMPATIBLE</div>
        <div style="color:var(--text2);margin-top:8px">Compatibility Score: <strong style="color:var(--success);font-size:20px"><?= $result['compat']['compatibility_score'] ?>/10</strong></div>
        <?php else: ?>
        <div style="font-size:22px;font-weight:700;color:#EF4444;font-family:'Syne',sans-serif">INCOMPATIBLE</div>
        <div style="color:var(--text2);margin-top:8px">Transfusion would cause adverse reaction.</div>
        <?php endif; ?>

        <?php if(!empty($result['units'])): ?>
        <div style="margin-top:20px;text-align:left">
            <div style="font-size:12px;font-weight:700;color:var(--text2);text-transform:uppercase;margin-bottom:8px">Available units of <?= $result['donor'] ?>:</div>
            <?php foreach($result['units'] as $u): ?>
            <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:8px 12px;margin-bottom:6px;display:flex;gap:12px;font-size:12px">
                <span style="font-family:monospace;color:var(--info)"><?= $u['unit_code'] ?></span>
                <span><?= $u['component_type'] ?></span>
                <span style="color:var(--text3)">Exp: <?= formatDate($u['expiry_date']) ?></span>
                <?= statusBadge($u['status']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>

<!-- Crossmatch History -->
<div class="card">
    <div class="card-header"><i class="fa-solid fa-history" style="color:var(--info)"></i><h3>Crossmatch History</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Patient</th><th>Recipient</th><th>Donor Unit</th><th>Result</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach($crossmatches as $cm): ?>
            <tr>
                <td style="font-weight:600;color:var(--text)"><?=htmlspecialchars($cm['patient_name'])?></td>
                <td><?=bloodGroupBadge($cm['blood_group'])?></td>
                <td style="font-size:11px;font-family:monospace;color:var(--info)"><?=$cm['unit_code']?><br><span style="color:var(--text3)"><?=bloodGroupBadge($cm['donor_group'])?></span></td>
                <td><?=statusBadge($cm['crossmatch_result'])?></td>
                <td style="font-size:11px;color:var(--text3)"><?=formatDate($cm['issue_date'],'d M H:i')?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
