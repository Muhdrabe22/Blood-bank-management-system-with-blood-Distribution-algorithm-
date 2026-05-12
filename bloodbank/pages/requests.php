<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

if($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    $inv = new InventoryManager();

    if($action === 'add_request') {
        $code = $inv->generateCode('REQ', 'blood_requests', 'request_code');
        $db->execute(
            "INSERT INTO blood_requests (request_code,patient_id,requested_by,blood_group,component_type,units_requested,urgency,reason,required_date,crossmatch_required)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $code, intval($_POST['patient_id']), $_SESSION['user_id'],
                sanitize($_POST['blood_group']), sanitize($_POST['component_type']),
                intval($_POST['units_requested']), sanitize($_POST['urgency']),
                sanitize($_POST['reason']), $_POST['required_date']?:null,
                isset($_POST['crossmatch_required'])?1:0
            ]
        );

        // Create notification for emergency
        if($_POST['urgency'] === 'Emergency') {
            $db->execute(
                "INSERT INTO notifications (type, title, message, blood_group) VALUES (?,?,?,?)",
                ['request_pending', 'EMERGENCY Blood Request', "Emergency request $code needs immediate attention", sanitize($_POST['blood_group'])]
            );
        }
        
        $auth->logActivity('Add Request', 'Requests', "Request $code added");
        $_SESSION['success'] = "Blood request $code created.";
        header('Location: requests.php'); exit;
    }

    if($action === 'approve_request') {
        $db->execute(
            "UPDATE blood_requests SET status='Approved', approved_by=?, approval_date=NOW() WHERE id=?",
            [$_SESSION['user_id'], intval($_POST['req_id'])]
        );
        $_SESSION['success'] = "Request approved.";
        header('Location: requests.php'); exit;
    }

    if($action === 'reject_request') {
        $db->execute(
            "UPDATE blood_requests SET status='Rejected', rejection_reason=? WHERE id=?",
            [sanitize($_POST['reason']), intval($_POST['req_id'])]
        );
        $_SESSION['success'] = "Request rejected.";
        header('Location: requests.php'); exit;
    }

    if($action === 'cancel_request') {
        $db->execute("UPDATE blood_requests SET status='Cancelled' WHERE id=? AND requested_by=?", 
            [intval($_POST['req_id']), $_SESSION['user_id']]);
        $_SESSION['success'] = "Request cancelled.";
        header('Location: requests.php'); exit;
    }
}

$fStatus   = $_GET['status'] ?? '';
$fUrgency  = $_GET['urgency'] ?? '';
$fGroup    = $_GET['blood_group'] ?? '';
$search    = sanitize($_GET['search'] ?? '');
$page      = max(1, intval($_GET['page'] ?? 1));
$offset    = ($page-1)*ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = [];
if($fStatus)  { $where .= " AND br.status=?"; $params[] = $fStatus; }
if($fUrgency) { $where .= " AND br.urgency=?"; $params[] = $fUrgency; }
if($fGroup)   { $where .= " AND br.blood_group=?"; $params[] = $fGroup; }
if($search)   { $where .= " AND (br.request_code LIKE ? OR p.full_name LIKE ?)"; $params = array_merge($params,["%$search%","%$search%"]); }

$total = $db->fetch("SELECT COUNT(*) as n FROM blood_requests br JOIN patients p ON br.patient_id=p.id $where", $params)['n'];
$requests = $db->fetchAll(
    "SELECT br.*, p.full_name as patient_name, p.blood_group as p_blood, p.ward,
            u.full_name as requester, a.full_name as approver_name
     FROM blood_requests br 
     JOIN patients p ON br.patient_id=p.id
     JOIN users u ON br.requested_by=u.id
     LEFT JOIN users a ON br.approved_by=a.id
     $where
     ORDER BY CASE br.urgency WHEN 'Emergency' THEN 1 WHEN 'Urgent' THEN 2 ELSE 3 END, br.created_at DESC
     LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset",
    $params
);
$totalPages = ceil($total/ITEMS_PER_PAGE);

$patients = $db->fetchAll("SELECT id, patient_id, full_name, blood_group, ward FROM patients WHERE is_active=1 ORDER BY full_name");

$pageTitle = 'Blood Requests';
$pageSubtitle = 'Request Management';
include '../includes/header.php';
?>

<div class="d-flex align-center mb-4" style="gap:10px">
    <div>
        <div class="text-muted"><?= $total ?> request(s)</div>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px">
        <button class="btn btn-primary" onclick="openModal('addRequestModal')">
            <i class="fa-solid fa-plus"></i> New Request
        </button>
        <a href="distribution.php" class="btn btn-secondary"><i class="fa-solid fa-truck-medical"></i> Issue Blood</a>
    </div>
</div>

<!-- Quick status tabs -->
<div class="tabs" data-tabs style="margin-bottom:0">
    <a href="?status=" class="tab-btn <?= !$fStatus?'active':'' ?>">All</a>
    <a href="?status=Pending" class="tab-btn <?= $fStatus==='Pending'?'active':'' ?>">Pending</a>
    <a href="?urgency=Emergency" class="tab-btn <?= $fUrgency==='Emergency'?'active':'' ?>" style="color:#EF4444">🚨 Emergency</a>
    <a href="?status=Approved" class="tab-btn <?= $fStatus==='Approved'?'active':'' ?>">Approved</a>
    <a href="?status=Fulfilled" class="tab-btn <?= $fStatus==='Fulfilled'?'active':'' ?>">Fulfilled</a>
    <a href="?status=Rejected" class="tab-btn <?= $fStatus==='Rejected'?'active':'' ?>">Rejected</a>
</div>

<div class="card">
    <div class="filter-bar">
        <div class="search-bar">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search code, patient..." value="<?= $search ?>">
        </div>
        <select id="groupFilter">
            <option value="">All Groups</option>
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
            <option <?= $fGroup===$g?'selected':'' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i></button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Request Code</th><th>Patient</th><th>Blood Group</th><th>Component</th>
                <th>Units</th><th>Urgency</th><th>Required Date</th><th>Status</th><th>Requested By</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($requests as $req): ?>
            <tr <?= $req['urgency']==='Emergency'?'style="background:rgba(239,68,68,.04)"':'' ?>>
                <td style="font-family:monospace;font-size:11px;color:var(--info)"><?= $req['request_code'] ?></td>
                <td>
                    <div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($req['patient_name']) ?></div>
                    <?php if($req['ward']): ?><div style="font-size:11px;color:var(--text3)"><?= $req['ward'] ?></div><?php endif; ?>
                </td>
                <td><?= bloodGroupBadge($req['blood_group']) ?></td>
                <td style="font-size:12px"><?= $req['component_type'] ?></td>
                <td>
                    <span style="font-size:18px;font-weight:700;color:var(--red)"><?= $req['units_requested'] ?></span>
                    <?php if($req['units_approved']>0): ?>
                    <div style="font-size:11px;color:var(--success)">✓ <?= $req['units_approved'] ?> approved</div>
                    <?php endif; ?>
                </td>
                <td><?= statusBadge($req['urgency']) ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= $req['required_date']?formatDate($req['required_date']):'ASAP' ?></td>
                <td><?= statusBadge($req['status']) ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($req['requester']) ?><br><span style="font-size:11px;color:var(--text3)"><?= timeAgo($req['created_at']) ?></span></td>
                <td>
                    <div class="d-flex gap-2" style="flex-wrap:wrap">
                        <?php if(in_array($req['status'],['Pending','Processing']) && $auth->hasRole(['admin','doctor'])): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="approve_request">
                            <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Approve"><i class="fa-solid fa-check"></i></button>
                        </form>
                        <button onclick="rejectRequest(<?= $req['id'] ?>)" class="btn btn-danger btn-sm" title="Reject"><i class="fa-solid fa-times"></i></button>
                        <?php endif; ?>
                        <?php if(in_array($req['status'],['Pending','Approved'])): ?>
                        <a href="distribution.php?request_id=<?= $req['id'] ?>" class="btn btn-primary btn-sm" title="Issue Blood">
                            <i class="fa-solid fa-truck-medical"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($requests)): ?>
            <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3)">No requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($totalPages>1): ?>
    <div class="pagination" style="padding:16px">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="?page=<?=$i?>&status=<?=$fStatus?>&urgency=<?=$fUrgency?>&blood_group=<?=$fGroup?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ADD REQUEST MODAL -->
<div class="modal-overlay" id="addRequestModal">
<div class="modal" style="max-width:640px">
    <div class="modal-header">
        <h3>New Blood Request</h3>
        <button class="modal-close" onclick="closeModal('addRequestModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="add_request">
    <div class="modal-body">
        <div class="form-row">
            <div class="form-group" style="grid-column:span 2">
                <label>Patient *</label>
                <select name="patient_id" required onchange="autoFillBlood(this)">
                    <option value="">Select Patient</option>
                    <?php foreach($patients as $pt): ?>
                    <option value="<?= $pt['id'] ?>" data-group="<?= $pt['blood_group'] ?>">
                        <?= htmlspecialchars($pt['full_name']) ?> (<?= $pt['patient_id'] ?>) — <?= $pt['blood_group'] ?> — <?= $pt['ward'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Blood Group Required *</label>
                <select name="blood_group" id="req_blood_group" required>
                    <option value="">Select Group</option>
                    <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                    <option><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Component Type *</label>
                <select name="component_type" required>
                    <?php foreach(['Whole Blood','Packed Red Cells','Fresh Frozen Plasma','Platelets','Cryoprecipitate','Buffy Coat'] as $c): ?>
                    <option><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Units Required *</label>
                <input type="number" name="units_requested" min="1" value="1" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Urgency *</label>
                <select name="urgency" required>
                    <option value="Routine">Routine</option>
                    <option value="Urgent">Urgent</option>
                    <option value="Emergency">🚨 Emergency</option>
                </select>
            </div>
            <div class="form-group">
                <label>Required Date</label>
                <input type="date" name="required_date" min="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Clinical Reason / Diagnosis *</label>
            <textarea name="reason" required rows="3" placeholder="e.g. Severe anaemia due to malaria, surgery preparation, trauma..."></textarea>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;text-transform:none">
                <input type="checkbox" name="crossmatch_required" checked style="width:auto">
                Crossmatch required before transfusion
            </label>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addRequestModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Submit Request</button>
    </div>
    </form>
</div>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
<div class="modal" style="max-width:420px">
    <div class="modal-header">
        <h3 style="color:#EF4444">Reject Request</h3>
        <button class="modal-close" onclick="closeModal('rejectModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="reject_request">
    <input type="hidden" name="req_id" id="reject_req_id">
    <div class="modal-body">
        <div class="form-group">
            <label>Reason for Rejection *</label>
            <textarea name="reason" required rows="3" placeholder="Clinical reason..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject</button>
    </div>
    </form>
</div>
</div>

<script>
function applyFilters() {
    const p = new URLSearchParams({
        search: document.getElementById('searchInput').value,
        blood_group: document.getElementById('groupFilter').value,
        status: '<?= $fStatus ?>'
    });
    window.location.href = 'requests.php?' + p.toString();
}
function autoFillBlood(sel) {
    const group = sel.options[sel.selectedIndex].dataset.group;
    if(group) {
        const s = document.getElementById('req_blood_group');
        for(let o of s.options) if(o.value === group) { s.value = group; break; }
    }
}
function rejectRequest(id) {
    document.getElementById('reject_req_id').value = id;
    openModal('rejectModal');
}
</script>

<?php include '../includes/footer.php'; ?>
