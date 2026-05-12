<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db  = Database::getInstance();
$inv = new InventoryManager();

if($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if($action === 'add_donation') {
        $code = $inv->generateCode('DON-C', 'donations', 'donation_code');
        $donorId = intval($_POST['donor_id']);

        // Check eligibility
        $donor = $db->fetch("SELECT * FROM donors WHERE id=?", [$donorId]);
        if(!$donor['is_eligible']) {
            $_SESSION['error'] = "Donor is currently ineligible to donate.";
            header('Location: donations.php'); exit;
        }

        $db->beginTransaction();
        try {
            $db->execute(
                "INSERT INTO donations (donation_code,donor_id,donation_date,donation_time,donation_type,volume_ml,blood_group,systolic_bp,diastolic_bp,pulse_rate,hemoglobin,temperature,pre_donation_remarks,status,collected_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $code, $donorId, sanitize($_POST['donation_date']),
                    sanitize($_POST['donation_time']), sanitize($_POST['donation_type']),
                    intval($_POST['volume_ml']), sanitize($_POST['blood_group']),
                    intval($_POST['systolic_bp'])??null, intval($_POST['diastolic_bp'])??null,
                    intval($_POST['pulse_rate'])??null, floatval($_POST['hemoglobin'])??null,
                    floatval($_POST['temperature'])??null,
                    sanitize($_POST['pre_donation_remarks']),
                    'Collected', $_SESSION['user_id']
                ]
            );
            $donationId = $db->lastInsertId();

            // Update donor stats & set next eligible date
            $nextDate = date('Y-m-d', strtotime($_POST['donation_date'].' +56 days'));
            $db->execute(
                "UPDATE donors SET total_donations=total_donations+1, next_eligible_date=? WHERE id=?",
                [$nextDate, $donorId]
            );

            // Auto-create blood unit in inventory
            $bloodGroup   = sanitize($_POST['blood_group']);
            $component    = sanitize($_POST['donation_type']) === 'Whole Blood' ? 'Whole Blood' : sanitize($_POST['donation_type']);
            $unitCode     = $inv->generateUnitCode($bloodGroup, $component);
            $expiryDate   = $inv->getExpiryDate($component, sanitize($_POST['donation_date']));
            $rhesus       = strpos($bloodGroup,'+')!==false ? 'Positive' : 'Negative';

            $db->execute(
                "INSERT INTO blood_units (unit_code,donation_id,blood_group,component_type,volume_ml,collection_date,expiry_date,rhesus_factor,storage_temperature,storage_location,status,screening_status,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $unitCode, $donationId, $bloodGroup, $component,
                    intval($_POST['volume_ml']), sanitize($_POST['donation_date']),
                    $expiryDate, $rhesus, 4.0, 'Main Blood Bank',
                    'Quarantine', 'Pending', $_SESSION['user_id']
                ]
            );

            $db->commit();
            $auth->logActivity('Add Donation', 'Donations', "Donation $code recorded. Unit $unitCode created.");
            $_SESSION['success'] = "Donation $code recorded! Blood unit $unitCode created (status: Quarantine, pending screening).";
        } catch(Exception $e) {
            $db->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        header('Location: donations.php'); exit;
    }

    if($action === 'update_status') {
        $db->execute("UPDATE donations SET status=?, post_donation_remarks=? WHERE id=?",
            [sanitize($_POST['status']), sanitize($_POST['remarks']??''), intval($_POST['donation_id'])]);
        $_SESSION['success'] = "Donation status updated.";
        header('Location: donations.php'); exit;
    }
}

// Filters
$search    = sanitize($_GET['search'] ?? '');
$fGroup    = $_GET['blood_group'] ?? '';
$fStatus   = $_GET['status'] ?? '';
$fDonor    = intval($_GET['donor_id'] ?? 0);
$page      = max(1, intval($_GET['page']??1));
$offset    = ($page-1)*ITEMS_PER_PAGE;

$where  = "WHERE 1=1";
$params = [];
if($search)  { $where .= " AND (d.donation_code LIKE ? OR dn.full_name LIKE ?)"; $params = array_merge($params,["%$search%","%$search%"]); }
if($fGroup)  { $where .= " AND d.blood_group=?"; $params[] = $fGroup; }
if($fStatus) { $where .= " AND d.status=?"; $params[] = $fStatus; }
if($fDonor)  { $where .= " AND d.donor_id=?"; $params[] = $fDonor; }

$total = $db->fetch("SELECT COUNT(*) n FROM donations d JOIN donors dn ON d.donor_id=dn.id $where", $params)['n'];
$donations = $db->fetchAll(
    "SELECT d.*, dn.full_name donor_name, dn.donor_code, u.full_name collected_by_name
     FROM donations d
     JOIN donors dn ON d.donor_id=dn.id
     LEFT JOIN users u ON d.collected_by=u.id
     $where ORDER BY d.donation_date DESC, d.donation_time DESC
     LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset",
    $params
);
$totalPages = ceil($total/ITEMS_PER_PAGE);

// Donor list for dropdown
$donors = $db->fetchAll("SELECT id, donor_code, full_name, blood_group, is_eligible FROM donors ORDER BY full_name");

$pageTitle   = 'Donations';
$pageSubtitle = 'Donation Records';
include '../includes/header.php';
?>

<!-- Summary stats row -->
<?php
$todayCount = $db->fetch("SELECT COUNT(*) n FROM donations WHERE DATE(donation_date)=CURDATE()")['n'];
$weekCount  = $db->fetch("SELECT COUNT(*) n FROM donations WHERE donation_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)")['n'];
$monthCount = $db->fetch("SELECT COUNT(*) n FROM donations WHERE MONTH(donation_date)=MONTH(CURDATE()) AND YEAR(donation_date)=YEAR(CURDATE())")['n'];
$totalVol   = $db->fetch("SELECT COALESCE(SUM(volume_ml),0) n FROM donations WHERE MONTH(donation_date)=MONTH(CURDATE())")['n'];
?>
<div class="stats-grid mb-6">
    <div class="stat-card" style="--accent-color:var(--red)">
        <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <div class="stat-value"><?= $todayCount ?></div>
        <div class="stat-label">Today's Donations</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--warning)">
        <div class="stat-icon" style="background:var(--warning)"><i class="fa-solid fa-calendar-week"></i></div>
        <div class="stat-value"><?= $weekCount ?></div>
        <div class="stat-label">This Week</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--info)">
        <div class="stat-icon" style="background:var(--info)"><i class="fa-solid fa-calendar"></i></div>
        <div class="stat-value"><?= $monthCount ?></div>
        <div class="stat-label">This Month</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--success)">
        <div class="stat-icon" style="background:var(--success)"><i class="fa-solid fa-flask"></i></div>
        <div class="stat-value"><?= number_format($totalVol/1000,1) ?>L</div>
        <div class="stat-label">Volume This Month</div>
    </div>
</div>

<div class="d-flex align-center mb-4" style="gap:10px">
    <div style="margin-left:auto">
        <button class="btn btn-primary" onclick="openModal('addDonationModal')">
            <i class="fa-solid fa-plus"></i> Record Donation
        </button>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <div class="search-bar">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search code, donor..." value="<?= $search ?>">
        </div>
        <select id="groupFilter">
            <option value="">All Groups</option>
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
            <option <?= $fGroup===$g?'selected':'' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        <select id="statusFilter">
            <option value="">All Status</option>
            <?php foreach(['Collected','Processing','Quarantine','Approved','Rejected','Discarded'] as $s): ?>
            <option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i></button>
        <?php if($search||$fGroup||$fStatus): ?>
        <a href="donations.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-times"></i></a>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th><th>Donor</th><th>Blood Group</th><th>Type</th>
                    <th>Volume</th><th>Hb</th><th>BP</th><th>Date</th>
                    <th>Status</th><th>Collected By</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($donations as $don): ?>
            <tr>
                <td style="font-family:monospace;font-size:11px;color:var(--info)"><?= $don['donation_code'] ?></td>
                <td>
                    <div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($don['donor_name']) ?></div>
                    <div style="font-size:11px;color:var(--text3)"><?= $don['donor_code'] ?></div>
                </td>
                <td><?= bloodGroupBadge($don['blood_group']) ?></td>
                <td style="font-size:12px"><?= $don['donation_type'] ?></td>
                <td style="font-size:13px"><?= $don['volume_ml'] ?>ml</td>
                <td style="font-size:13px;color:<?= $don['hemoglobin']&&$don['hemoglobin']<12?'#EF4444':'var(--text2)' ?>">
                    <?= $don['hemoglobin'] ? $don['hemoglobin'].' g/dL' : '—' ?>
                </td>
                <td style="font-size:12px;color:var(--text2)">
                    <?= ($don['systolic_bp']&&$don['diastolic_bp']) ? $don['systolic_bp'].'/'.$don['diastolic_bp'] : '—' ?>
                </td>
                <td style="font-size:12px">
                    <div><?= formatDate($don['donation_date']) ?></div>
                    <div style="color:var(--text3);font-size:11px"><?= substr($don['donation_time'],0,5) ?></div>
                </td>
                <td><?= statusBadge($don['status']) ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($don['collected_by_name']??'—') ?></td>
                <td>
                    <button onclick="updateDonation(<?= $don['id'] ?>, '<?= $don['status'] ?>')" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($donations)): ?>
            <tr><td colspan="11" style="text-align:center;padding:40px;color:var(--text3)">No donations found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($totalPages>1): ?>
    <div class="pagination" style="padding:16px">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="?page=<?=$i?>&search=<?=$search?>&blood_group=<?=$fGroup?>&status=<?=$fStatus?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ADD DONATION MODAL -->
<div class="modal-overlay" id="addDonationModal">
<div class="modal" style="max-width:760px">
    <div class="modal-header">
        <div style="width:36px;height:36px;background:var(--red);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px">🩸</div>
        <h3>Record Blood Donation</h3>
        <button class="modal-close" onclick="closeModal('addDonationModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="add_donation">
    <div class="modal-body">
        <div class="tabs" data-tabs>
            <button type="button" class="tab-btn active" data-tab="tab-donor">Donor & Donation</button>
            <button type="button" class="tab-btn" data-tab="tab-vitals">Vital Signs</button>
        </div>

        <div class="tab-content active" id="tab-donor">
            <div class="form-row">
                <div class="form-group" style="grid-column:span 2">
                    <label>Select Donor *</label>
                    <select name="donor_id" required id="donorSelect" onchange="autoFillDonorBlood(this)">
                        <option value="">— Select Donor —</option>
                        <?php foreach($donors as $dn): ?>
                        <option value="<?= $dn['id'] ?>" data-group="<?= $dn['blood_group'] ?>" <?= !$dn['is_eligible']?'style="color:#EF4444"':'' ?>>
                            <?= htmlspecialchars($dn['full_name']) ?> (<?= $dn['donor_code'] ?>) — <?= $dn['blood_group'] ?>
                            <?= !$dn['is_eligible']?' [INELIGIBLE]':'' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Blood Group *</label>
                    <select name="blood_group" id="don_blood_group" required>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                        <option><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Donation Type *</label>
                    <select name="donation_type" required>
                        <option>Whole Blood</option>
                        <option>Platelets</option>
                        <option>Plasma</option>
                        <option>Double Red Cells</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Volume (ml) *</label>
                    <input type="number" name="volume_ml" value="450" min="250" max="500" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Donation Date *</label>
                    <input type="date" name="donation_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Donation Time *</label>
                    <input type="time" name="donation_time" value="<?= date('H:i') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Pre-Donation Remarks</label>
                <textarea name="pre_donation_remarks" rows="2" placeholder="Any observations before donation..."></textarea>
            </div>
        </div>

        <div class="tab-content" id="tab-vitals">
            <div class="alert alert-info">
                <i class="fa-solid fa-stethoscope"></i>
                Record vital signs measured before donation. Hemoglobin &lt;12.5 g/dL should defer the donor.
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Systolic BP (mmHg)</label>
                    <input type="number" name="systolic_bp" min="80" max="200" placeholder="120">
                </div>
                <div class="form-group">
                    <label>Diastolic BP (mmHg)</label>
                    <input type="number" name="diastolic_bp" min="50" max="120" placeholder="80">
                </div>
                <div class="form-group">
                    <label>Pulse Rate (bpm)</label>
                    <input type="number" name="pulse_rate" min="40" max="180" placeholder="72">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Hemoglobin (g/dL)</label>
                    <input type="number" name="hemoglobin" step="0.1" min="5" max="25" placeholder="13.5" id="hbInput" oninput="checkHb(this.value)">
                </div>
                <div class="form-group">
                    <label>Temperature (°C)</label>
                    <input type="number" name="temperature" step="0.1" min="35" max="41" placeholder="36.6">
                </div>
            </div>
            <div id="hbWarning" class="alert alert-warning" style="display:none">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Hemoglobin below 12.5 g/dL — consider deferring this donor!
            </div>
        </div>

        <div class="alert alert-success" style="margin-top:12px">
            <i class="fa-solid fa-info-circle"></i>
            A blood unit will be <strong>automatically created</strong> in inventory with status <strong>Quarantine</strong>, pending lab screening.
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addDonationModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Record Donation</button>
    </div>
    </form>
</div>
</div>

<!-- UPDATE STATUS MODAL -->
<div class="modal-overlay" id="updateDonationModal">
<div class="modal" style="max-width:420px">
    <div class="modal-header"><h3>Update Donation Status</h3><button class="modal-close" onclick="closeModal('updateDonationModal')">✕</button></div>
    <form method="POST">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="donation_id" id="upd_donation_id">
    <div class="modal-body">
        <div class="form-group">
            <label>New Status</label>
            <select name="status" id="upd_status">
                <?php foreach(['Collected','Processing','Quarantine','Approved','Rejected','Discarded'] as $s): ?>
                <option><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Post-Donation Remarks</label>
            <textarea name="remarks" rows="3"></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('updateDonationModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
    </div>
    </form>
</div>
</div>

<script>
function applyFilters() {
    const p = new URLSearchParams({ search: document.getElementById('searchInput').value, blood_group: document.getElementById('groupFilter').value, status: document.getElementById('statusFilter').value });
    window.location.href = 'donations.php?' + p.toString();
}
function autoFillDonorBlood(sel) {
    const g = sel.options[sel.selectedIndex].dataset.group;
    if(g) { const s = document.getElementById('don_blood_group'); for(let o of s.options) if(o.value===g){s.value=g;break;} }
}
function checkHb(v) { document.getElementById('hbWarning').style.display = parseFloat(v)<12.5?'flex':'none'; }
function updateDonation(id, status) {
    document.getElementById('upd_donation_id').value = id;
    document.getElementById('upd_status').value = status;
    openModal('updateDonationModal');
}
</script>
<?php include '../includes/footer.php'; ?>
