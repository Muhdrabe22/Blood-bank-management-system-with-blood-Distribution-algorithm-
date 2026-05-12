<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

// Handle form submissions
if($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if($action === 'add_donor') {
        $inv = new InventoryManager();
        $code = $inv->generateCode('DON', 'donors', 'donor_code');
        
        try {
            $db->execute(
                "INSERT INTO donors (donor_code,full_name,date_of_birth,gender,blood_group,phone,email,address,city,state,occupation,weight,height,medical_history,allergies,medications,registered_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $code, sanitize($_POST['full_name']), $_POST['date_of_birth'],
                    $_POST['gender'], $_POST['blood_group'], sanitize($_POST['phone']),
                    sanitize($_POST['email']), sanitize($_POST['address']),
                    sanitize($_POST['city']), sanitize($_POST['state']),
                    sanitize($_POST['occupation']), $_POST['weight'] ?: null, $_POST['height'] ?: null,
                    sanitize($_POST['medical_history']), sanitize($_POST['allergies']),
                    sanitize($_POST['medications']), $_SESSION['user_id']
                ]
            );
            $auth->logActivity('Add Donor', 'Donors', "Added donor: {$code}");
            $_SESSION['success'] = "Donor $code registered successfully!";
        } catch(Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        header('Location: donors.php'); exit;
    }

    if($action === 'update_eligibility') {
        $db->execute(
            "UPDATE donors SET is_eligible=?, ineligibility_reason=?, next_eligible_date=? WHERE id=?",
            [$_POST['is_eligible'], sanitize($_POST['reason']??''), $_POST['next_date']??null, $_POST['donor_id']]
        );
        $_SESSION['success'] = "Eligibility updated successfully.";
        header('Location: donors.php'); exit;
    }

    if($action === 'delete_donor') {
        $db->execute("DELETE FROM donors WHERE id=?", [$_POST['donor_id']]);
        $_SESSION['success'] = "Donor deleted.";
        header('Location: donors.php'); exit;
    }
}

// Search & Filter
$search = sanitize($_GET['search'] ?? '');
$filterGroup = $_GET['blood_group'] ?? '';
$filterGender = $_GET['gender'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];
if($search) { $where .= " AND (d.full_name LIKE ? OR d.donor_code LIKE ? OR d.phone LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if($filterGroup) { $where .= " AND d.blood_group=?"; $params[] = $filterGroup; }
if($filterGender) { $where .= " AND d.gender=?"; $params[] = $filterGender; }

$total = $db->fetch("SELECT COUNT(*) as n FROM donors d $where", $params)['n'];
$donors = $db->fetchAll("SELECT d.*, u.full_name as reg_by FROM donors d LEFT JOIN users u ON d.registered_by=u.id $where ORDER BY d.created_at DESC LIMIT $limit OFFSET $offset", $params);
$totalPages = ceil($total / $limit);

$pageTitle = 'Donors';
$pageSubtitle = 'Donor Management';
include '../includes/header.php';
?>

<div class="d-flex align-center gap-2 mb-6">
    <div>
        <div style="font-size:14px;color:var(--text2)"><?= $total ?> donor(s) registered</div>
    </div>
    <div style="margin-left:auto">
        <button class="btn btn-primary" onclick="openModal('addDonorModal')">
            <i class="fa-solid fa-plus"></i> Register Donor
        </button>
        <a href="donor_eligibility.php" class="btn btn-secondary"><i class="fa-solid fa-clipboard-check"></i> Eligibility Check</a>
    </div>
</div>

<div class="card">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="search-bar">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search donors..." value="<?= $search ?>">
        </div>
        <select id="groupFilter">
            <option value="">All Blood Groups</option>
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
            <option <?= $filterGroup===$g?'selected':'' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        <select id="genderFilter">
            <option value="">All Genders</option>
            <option <?= $filterGender==='Male'?'selected':'' ?>>Male</option>
            <option <?= $filterGender==='Female'?'selected':'' ?>>Female</option>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if($search || $filterGroup || $filterGender): ?>
        <a href="donors.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-times"></i> Clear</a>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Donor</th><th>Blood Group</th><th>Gender/Age</th><th>Phone</th>
                    <th>Donations</th><th>Eligible</th><th>Registered</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($donors as $d): ?>
            <tr>
                <td>
                    <div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($d['full_name']) ?></div>
                    <div style="font-size:11px;color:var(--text3)"><?= $d['donor_code'] ?></div>
                </td>
                <td><?= bloodGroupBadge($d['blood_group']) ?></td>
                <td>
                    <div><?= $d['gender'] ?></div>
                    <div style="font-size:11px;color:var(--text3)"><?= calculateAge($d['date_of_birth']) ?></div>
                </td>
                <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($d['phone']) ?></td>
                <td>
                    <span style="font-size:18px;font-weight:700;color:var(--info)"><?= $d['total_donations'] ?></span>
                </td>
                <td>
                    <?php if($d['is_eligible']): ?>
                    <span class="badge badge-success">Eligible</span>
                    <?php else: ?>
                    <span class="badge badge-danger">Ineligible</span>
                    <?php if($d['next_eligible_date']): ?>
                    <div style="font-size:11px;color:var(--text3)">From: <?= formatDate($d['next_eligible_date']) ?></div>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--text3)"><?= formatDate($d['created_at']) ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="donor_profile.php?id=<?= $d['id'] ?>" class="btn btn-info btn-sm" title="View Profile"><i class="fa-solid fa-eye"></i></a>
                        <a href="donations.php?donor_id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm" title="Donations"><i class="fa-solid fa-droplet"></i></a>
                        <button onclick="editEligibility(<?= htmlspecialchars(json_encode($d)) ?>)" class="btn btn-warning btn-sm" title="Eligibility"><i class="fa-solid fa-clipboard-check"></i></button>
                        <?php if($auth->hasRole(['admin'])): ?>
                        <form method="POST" onsubmit="return confirmDelete()">
                            <input type="hidden" name="action" value="delete_donor">
                            <input type="hidden" name="donor_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($donors)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text3)">
                <i class="fa-solid fa-users fa-2x" style="margin-bottom:8px;display:block"></i>
                No donors found. Register the first donor!
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($totalPages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="?page=<?=$i?>&search=<?=$search?>&blood_group=<?=$filterGroup?>&gender=<?=$filterGender?>" 
           class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ADD DONOR MODAL -->
<div class="modal-overlay" id="addDonorModal">
<div class="modal" style="max-width:800px">
    <div class="modal-header">
        <div class="stat-icon" style="width:36px;height:36px;background:var(--red)"><i class="fa-solid fa-user-plus" style="font-size:14px"></i></div>
        <h3>Register New Donor</h3>
        <button class="modal-close" onclick="closeModal('addDonorModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="add_donor">
    <div class="modal-body">
        <div class="tabs" data-tabs>
            <button type="button" class="tab-btn active" data-tab="tab-personal">Personal Info</button>
            <button type="button" class="tab-btn" data-tab="tab-medical">Medical Info</button>
        </div>
        <div class="tab-content active" id="tab-personal">
            <div class="form-row">
                <div class="form-group" style="grid-column:span 2">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label>Date of Birth *</label>
                    <input type="date" name="date_of_birth" required max="<?= date('Y-m-d', strtotime('-17 years')) ?>">
                </div>
                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option>Male</option><option>Female</option><option>Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Blood Group *</label>
                    <select name="blood_group" required>
                        <option value="">Select Blood Group</option>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                        <option><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="tel" name="phone" required placeholder="08012345678">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@example.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="grid-column:span 2">
                    <label>Address</label>
                    <input type="text" name="address" placeholder="Street address">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" placeholder="City" value="Ibadan">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state" placeholder="State" value="Oyo">
                </div>
                <div class="form-group">
                    <label>Occupation</label>
                    <input type="text" name="occupation">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" step="0.1" min="50" placeholder="50+">
                </div>
                <div class="form-group">
                    <label>Height (cm)</label>
                    <input type="number" name="height" step="0.1">
                </div>
            </div>
        </div>
        <div class="tab-content" id="tab-medical">
            <div class="form-row">
                <div class="form-group" style="grid-column:span 2">
                    <label>Medical History</label>
                    <textarea name="medical_history" placeholder="Any relevant medical conditions..."></textarea>
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label>Allergies</label>
                    <textarea name="allergies" placeholder="Known allergies..." rows="3"></textarea>
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label>Current Medications</label>
                    <textarea name="medications" rows="3"></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addDonorModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Register Donor</button>
    </div>
    </form>
</div>
</div>

<!-- ELIGIBILITY MODAL -->
<div class="modal-overlay" id="eligibilityModal">
<div class="modal" style="max-width:480px">
    <div class="modal-header">
        <h3>Update Donor Eligibility</h3>
        <button class="modal-close" onclick="closeModal('eligibilityModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="update_eligibility">
    <input type="hidden" name="donor_id" id="elig_donor_id">
    <div class="modal-body">
        <div id="elig_donor_name" style="font-weight:700;font-size:18px;margin-bottom:16px"></div>
        <div class="form-group">
            <label>Eligibility Status *</label>
            <select name="is_eligible" id="elig_status" required>
                <option value="1">Eligible to Donate</option>
                <option value="0">Not Eligible</option>
            </select>
        </div>
        <div class="form-group" id="reason_group">
            <label>Reason for Ineligibility</label>
            <textarea name="reason" id="elig_reason" rows="3" placeholder="Medical reason..."></textarea>
        </div>
        <div class="form-group" id="date_group">
            <label>Next Eligible Date</label>
            <input type="date" name="next_date" id="elig_next_date">
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('eligibilityModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
    </div>
    </form>
</div>
</div>

<script>
function applyFilters() {
    const s = document.getElementById('searchInput').value;
    const g = document.getElementById('groupFilter').value;
    const gn = document.getElementById('genderFilter').value;
    window.location.href = `donors.php?search=${s}&blood_group=${g}&gender=${gn}`;
}
document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key==='Enter') applyFilters(); });

function editEligibility(donor) {
    document.getElementById('elig_donor_id').value = donor.id;
    document.getElementById('elig_donor_name').textContent = donor.full_name;
    document.getElementById('elig_status').value = donor.is_eligible;
    document.getElementById('elig_reason').value = donor.ineligibility_reason || '';
    document.getElementById('elig_next_date').value = donor.next_eligible_date || '';
    openModal('eligibilityModal');
}
</script>

<?php include '../includes/footer.php'; ?>
