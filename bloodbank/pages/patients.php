<?php
// pages/patients.php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

if($_SERVER['REQUEST_METHOD']==='POST') {
    $inv = new InventoryManager();
    $action = $_POST['action'] ?? '';

    if($action === 'add_patient') {
        $pid = 'PAT-' . date('Y') . '-' . str_pad($db->fetch("SELECT COUNT(*)+1 n FROM patients")['n'], 4, '0', STR_PAD_LEFT);
        $db->execute(
            "INSERT INTO patients (patient_id,full_name,date_of_birth,gender,blood_group,phone,email,address,ward,bed_number,diagnosis,attending_doctor,registered_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $pid, sanitize($_POST['full_name']), $_POST['dob']??null, sanitize($_POST['gender']),
                sanitize($_POST['blood_group'])?:null, sanitize($_POST['phone']),
                sanitize($_POST['email']), sanitize($_POST['address']),
                sanitize($_POST['ward']), sanitize($_POST['bed_number']),
                sanitize($_POST['diagnosis']), sanitize($_POST['attending_doctor']),
                $_SESSION['user_id']
            ]
        );
        $_SESSION['success'] = "Patient $pid registered.";
        header('Location: patients.php'); exit;
    }
}

$search = sanitize($_GET['search'] ?? '');
$page = max(1, intval($_GET['page']??1));
$offset = ($page-1)*ITEMS_PER_PAGE;
$where = $search ? "WHERE (full_name LIKE ? OR patient_id LIKE ? OR ward LIKE ?)" : "WHERE 1=1";
$params = $search ? ["%$search%","%$search%","%$search%"] : [];
$total = $db->fetch("SELECT COUNT(*) n FROM patients $where", $params)['n'];
$patients = $db->fetchAll("SELECT p.*, u.full_name reg_by, (SELECT COUNT(*) FROM blood_requests WHERE patient_id=p.id) req_count FROM patients p LEFT JOIN users u ON p.registered_by=u.id $where ORDER BY p.created_at DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset", $params);
$totalPages = ceil($total/ITEMS_PER_PAGE);

$pageTitle = 'Patients';
$pageSubtitle = 'Patient Registry';
include '../includes/header.php';
?>

<div class="d-flex align-center mb-4" style="gap:10px">
    <div class="search-bar" style="max-width:300px">
        <i class="fa-solid fa-search search-icon"></i>
        <input type="text" id="searchInput" placeholder="Search patients..." value="<?= $search ?>">
    </div>
    <div style="margin-left:auto">
        <button class="btn btn-primary" onclick="openModal('addPatientModal')"><i class="fa-solid fa-plus"></i> Register Patient</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Patient ID</th><th>Name</th><th>Gender/Age</th><th>Blood Group</th><th>Ward/Bed</th><th>Diagnosis</th><th>Doctor</th><th>Requests</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($patients as $p): ?>
            <tr>
                <td style="font-family:monospace;font-size:11px;color:var(--info)"><?= $p['patient_id'] ?></td>
                <td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($p['full_name']) ?></td>
                <td><?= $p['gender'] ?><div style="font-size:11px;color:var(--text3)"><?= calculateAge($p['date_of_birth']) ?></div></td>
                <td><?= $p['blood_group'] ? bloodGroupBadge($p['blood_group']) : '<span class="badge badge-secondary">Unknown</span>' ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($p['ward']?:'N/A') ?><?php if($p['bed_number']): ?><div style="font-size:11px;color:var(--text3)">Bed: <?= $p['bed_number'] ?></div><?php endif; ?></td>
                <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['diagnosis']?:'—') ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($p['attending_doctor']?:'—') ?></td>
                <td><a href="requests.php?patient=<?= $p['id'] ?>" style="font-size:18px;font-weight:700;color:var(--info);text-decoration:none"><?= $p['req_count'] ?></a></td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="requests.php" class="btn btn-primary btn-sm" title="Request Blood"><i class="fa-solid fa-droplet"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($patients)): ?><tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text3)">No patients found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($totalPages>1): ?><div class="pagination" style="padding:16px"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="?page=<?=$i?>&search=<?=$search?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>

<div class="modal-overlay" id="addPatientModal">
<div class="modal" style="max-width:680px">
    <div class="modal-header"><h3>Register Patient</h3><button class="modal-close" onclick="closeModal('addPatientModal')">✕</button></div>
    <form method="POST"><input type="hidden" name="action" value="add_patient">
    <div class="modal-body">
        <div class="form-row">
            <div class="form-group" style="grid-column:span 2"><label>Full Name *</label><input type="text" name="full_name" required></div>
            <div class="form-group"><label>Date of Birth</label><input type="date" name="dob"></div>
            <div class="form-group"><label>Gender *</label><select name="gender" required><option>Male</option><option>Female</option><option>Other</option></select></div>
            <div class="form-group"><label>Blood Group</label><select name="blood_group"><option value="">Unknown</option><?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?><option><?=$g?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
            <div class="form-group"><label>Ward</label><input type="text" name="ward" placeholder="Ward 3B, ICU..."></div>
            <div class="form-group"><label>Bed Number</label><input type="text" name="bed_number"></div>
            <div class="form-group" style="grid-column:span 2"><label>Diagnosis</label><textarea name="diagnosis" rows="2"></textarea></div>
            <div class="form-group"><label>Attending Doctor</label><input type="text" name="attending_doctor"></div>
            <div class="form-group" style="grid-column:span 2"><label>Address</label><input type="text" name="address"></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addPatientModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Register</button></div>
    </form>
</div>
</div>
<script>document.getElementById('searchInput').addEventListener('keypress',e=>{if(e.key==='Enter')window.location.href='patients.php?search='+e.target.value});</script>
<?php include '../includes/footer.php'; ?>
