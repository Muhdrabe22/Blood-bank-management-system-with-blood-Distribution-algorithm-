<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();
$inv = new InventoryManager();

if($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if($action === 'add_unit') {
        $collDate     = $_POST['collection_date'];
        $componentType= sanitize($_POST['component_type']);
        $bloodGroup   = sanitize($_POST['blood_group']);
        $unitCode     = $inv->generateUnitCode($bloodGroup, $componentType);
        $expiryDate   = $inv->getExpiryDate($componentType, $collDate);
        $rhesus       = strpos($bloodGroup,'+')!==false ? 'Positive' : 'Negative';

        try {
            $db->execute(
                "INSERT INTO blood_units (unit_code,donation_id,blood_group,component_type,volume_ml,collection_date,expiry_date,storage_temperature,bag_number,status,storage_location,rhesus_factor,screening_status,notes,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $unitCode, $_POST['donation_id']?:null, $bloodGroup, $componentType,
                    intval($_POST['volume_ml']), $collDate, $expiryDate,
                    $_POST['storage_temp']?:null, sanitize($_POST['bag_number']),
                    'Available', sanitize($_POST['storage_location']),
                    $rhesus, 'Pending', sanitize($_POST['notes']),
                    $_SESSION['user_id']
                ]
            );
            $auth->logActivity('Add Unit', 'Inventory', "Added unit: $unitCode");
            $_SESSION['success'] = "Blood unit $unitCode added. Expiry: $expiryDate";
        } catch(Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: inventory.php'); exit;
    }

    if($action === 'update_status') {
        $db->execute("UPDATE blood_units SET status=? WHERE id=?", [sanitize($_POST['status']), intval($_POST['unit_id'])]);
        $_SESSION['success'] = "Unit status updated.";
        header('Location: inventory.php'); exit;
    }

    if($action === 'discard_unit') {
        $db->execute("UPDATE blood_units SET status='Discarded',notes=CONCAT(IFNULL(notes,''),' [DISCARDED: ',?,']') WHERE id=?",
            [sanitize($_POST['reason']), intval($_POST['unit_id'])]);
        $_SESSION['success'] = "Unit discarded.";
        header('Location: inventory.php'); exit;
    }
}

// Filters
$search     = sanitize($_GET['search'] ?? '');
$fGroup     = $_GET['blood_group'] ?? '';
$fComponent = $_GET['component'] ?? '';
$fStatus    = $_GET['status'] ?? '';
$fExpiring  = $_GET['expiring'] ?? '';
$page       = max(1, intval($_GET['page'] ?? 1));
$offset     = ($page-1)*ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = [];
if($search)     { $where .= " AND (unit_code LIKE ? OR bag_number LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%"]); }
if($fGroup)     { $where .= " AND blood_group=?"; $params[] = $fGroup; }
if($fComponent) { $where .= " AND component_type=?"; $params[] = $fComponent; }
if($fStatus)    { $where .= " AND status=?"; $params[] = $fStatus; }
if($fExpiring)  { $where .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status='Available'"; }

$total = $db->fetch("SELECT COUNT(*) as n FROM blood_units $where", $params)['n'];
$units = $db->fetchAll(
    "SELECT bu.*, DATEDIFF(bu.expiry_date, CURDATE()) as days_left,
            d.full_name as donor_name, u.full_name as added_by
     FROM blood_units bu 
     LEFT JOIN donations dn ON bu.donation_id = dn.id
     LEFT JOIN donors d ON dn.donor_id = d.id
     LEFT JOIN users u ON bu.created_by = u.id
     $where ORDER BY bu.expiry_date ASC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset",
    $params
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Summary stats
$summary = $db->fetchAll(
    "SELECT blood_group, component_type, 
            SUM(CASE WHEN status='Available' AND expiry_date>CURDATE() THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status='Reserved' THEN 1 ELSE 0 END) as reserved,
            SUM(CASE WHEN status='Issued' THEN 1 ELSE 0 END) as issued,
            SUM(CASE WHEN status='Expired' OR (status='Available' AND expiry_date<=CURDATE()) THEN 1 ELSE 0 END) as expired
     FROM blood_units GROUP BY blood_group, component_type ORDER BY blood_group, component_type"
);

// Donations for dropdown
$donations = $db->fetchAll("SELECT d.id, d.donation_code, dn.full_name, dn.blood_group FROM donations d JOIN donors dn ON d.donor_id=dn.id WHERE d.status IN ('Approved','Collected') ORDER BY d.donation_date DESC LIMIT 50");

$pageTitle   = 'Blood Inventory';
$pageSubtitle= 'Unit Management';
include '../includes/header.php';
?>

<!-- Summary Row -->
<div class="stats-grid" style="margin-bottom:24px">
    <?php
    $totals = $db->fetch("SELECT 
        SUM(CASE WHEN status='Available' AND expiry_date>CURDATE() THEN 1 ELSE 0 END) as avail,
        SUM(CASE WHEN status='Reserved' THEN 1 ELSE 0 END) as reserved,
        SUM(CASE WHEN status='Issued' THEN 1 ELSE 0 END) as issued,
        SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status='Available' THEN 1 ELSE 0 END) as expiring,
        COUNT(*) as total FROM blood_units");
    ?>
    <div class="stat-card" style="--accent-color:var(--success)">
        <div class="stat-icon" style="background:var(--success)"><i class="fa-solid fa-check"></i></div>
        <div class="stat-value"><?= $totals['avail'] ?></div>
        <div class="stat-label">Available Units</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--warning)">
        <div class="stat-icon" style="background:var(--warning)"><i class="fa-solid fa-bookmark"></i></div>
        <div class="stat-value"><?= $totals['reserved'] ?></div>
        <div class="stat-label">Reserved Units</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--info)">
        <div class="stat-icon" style="background:var(--info)"><i class="fa-solid fa-syringe"></i></div>
        <div class="stat-value"><?= $totals['issued'] ?></div>
        <div class="stat-label">Issued Units</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--red)">
        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-value"><?= $totals['expiring'] ?></div>
        <div class="stat-label">Expiring (7 days)</div>
    </div>
    <div class="stat-card" style="--accent-color:var(--purple)">
        <div class="stat-icon" style="background:var(--purple)"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="stat-value"><?= $totals['total'] ?></div>
        <div class="stat-label">Total Units Ever</div>
    </div>
</div>

<div class="d-flex align-center mb-4" style="gap:10px">
    <div style="margin-left:auto;display:flex;gap:8px">
        <?php if($totals['expiring']>0): ?>
        <a href="?expiring=1" class="btn btn-warning btn-sm">
            <i class="fa-solid fa-clock"></i> Show Expiring (<?= $totals['expiring'] ?>)
        </a>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="openModal('addUnitModal')">
            <i class="fa-solid fa-plus"></i> Add Blood Unit
        </button>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <div class="search-bar">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search unit code, bag#..." value="<?= $search ?>">
        </div>
        <select id="groupFilter">
            <option value="">All Groups</option>
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
            <option <?= $fGroup===$g?'selected':'' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        <select id="componentFilter">
            <option value="">All Components</option>
            <?php foreach(['Whole Blood','Packed Red Cells','Fresh Frozen Plasma','Platelets','Cryoprecipitate','Buffy Coat'] as $c): ?>
            <option <?= $fComponent===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
        <select id="statusFilter">
            <option value="">All Status</option>
            <?php foreach(['Available','Reserved','Issued','Expired','Discarded','Quarantine'] as $s): ?>
            <option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i></button>
        <?php if($search||$fGroup||$fComponent||$fStatus||$fExpiring): ?>
        <a href="inventory.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-times"></i> Clear</a>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Unit Code</th><th>Blood Group</th><th>Component</th><th>Volume</th>
                <th>Donor</th><th>Collection</th><th>Expiry</th><th>Days Left</th>
                <th>Screening</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($units as $u): ?>
            <tr>
                <td style="font-family:monospace;font-size:11px;color:var(--info)"><?= $u['unit_code'] ?></td>
                <td><?= bloodGroupBadge($u['blood_group']) ?></td>
                <td style="font-size:12px"><?= $u['component_type'] ?></td>
                <td style="font-size:13px"><?= $u['volume_ml'] ?>ml</td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($u['donor_name'] ?: 'N/A') ?></td>
                <td style="font-size:12px"><?= formatDate($u['collection_date']) ?></td>
                <td style="font-size:12px;color:<?= $u['days_left']<=3?'#EF4444':($u['days_left']<=7?'#F59E0B':'var(--text2)') ?>">
                    <?= formatDate($u['expiry_date']) ?>
                </td>
                <td>
                    <span style="font-weight:700;font-size:15px;color:<?= $u['days_left']<0?'#EF4444':($u['days_left']<=3?'#EF4444':($u['days_left']<=7?'#F59E0B':'#10B981')) ?>">
                        <?= $u['days_left'] < 0 ? 'EXPIRED' : $u['days_left'].'d' ?>
                    </span>
                </td>
                <td><?= statusBadge($u['screening_status']) ?></td>
                <td><?= statusBadge($u['status']) ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="unit_detail.php?id=<?= $u['id'] ?>" class="btn btn-info btn-sm" title="Details"><i class="fa-solid fa-eye"></i></a>
                        <?php if($u['status']==='Available' && $auth->hasRole(['admin','lab_technician'])): ?>
                        <button onclick="discardUnit(<?= $u['id'] ?>, '<?= htmlspecialchars($u['unit_code']) ?>')" class="btn btn-danger btn-sm" title="Discard"><i class="fa-solid fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($units)): ?>
            <tr><td colspan="11" style="text-align:center;padding:40px;color:var(--text3)">No blood units found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($totalPages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="?page=<?=$i?>&search=<?=$search?>&blood_group=<?=$fGroup?>&component=<?=$fComponent?>&status=<?=$fStatus?>" 
           class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ADD UNIT MODAL -->
<div class="modal-overlay" id="addUnitModal">
<div class="modal" style="max-width:640px">
    <div class="modal-header">
        <h3>Add Blood Unit to Inventory</h3>
        <button class="modal-close" onclick="closeModal('addUnitModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="add_unit">
    <div class="modal-body">
        <div class="form-row">
            <div class="form-group">
                <label>Blood Group *</label>
                <select name="blood_group" required>
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
                <label>Volume (ml) *</label>
                <input type="number" name="volume_ml" value="450" min="50" max="500" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Collection Date *</label>
                <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Storage Temperature (°C)</label>
                <input type="number" name="storage_temp" step="0.1" value="4">
            </div>
            <div class="form-group">
                <label>Storage Location</label>
                <input type="text" name="storage_location" placeholder="e.g. Fridge A-1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Bag Number</label>
                <input type="text" name="bag_number">
            </div>
            <div class="form-group">
                <label>Linked Donation (Optional)</label>
                <select name="donation_id">
                    <option value="">None</option>
                    <?php foreach($donations as $dn): ?>
                    <option value="<?= $dn['id'] ?>"><?= $dn['donation_code'] ?> — <?= htmlspecialchars($dn['full_name']) ?> (<?= $dn['blood_group'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="2"></textarea>
        </div>
        <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i>
            Expiry date will be automatically calculated based on component type. Unit code will be auto-generated.
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addUnitModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Add Unit</button>
    </div>
    </form>
</div>
</div>

<!-- DISCARD MODAL -->
<div class="modal-overlay" id="discardModal">
<div class="modal" style="max-width:400px">
    <div class="modal-header" style="border-color:rgba(239,68,68,.3)">
        <h3 style="color:#EF4444">Discard Blood Unit</h3>
        <button class="modal-close" onclick="closeModal('discardModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="discard_unit">
    <input type="hidden" name="unit_id" id="discard_unit_id">
    <div class="modal-body">
        <div id="discard_unit_code" style="font-family:monospace;font-size:16px;font-weight:700;margin-bottom:16px;color:var(--red-light)"></div>
        <div class="form-group">
            <label>Reason for Discard *</label>
            <select name="reason" required>
                <option value="">Select reason</option>
                <option>Expired</option>
                <option>Failed screening test</option>
                <option>Contaminated</option>
                <option>Damaged bag/seal</option>
                <option>Hemolyzed</option>
                <option>Patient refused transfusion</option>
                <option>Other</option>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('discardModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Discard Unit</button>
    </div>
    </form>
</div>
</div>

<script>
function applyFilters() {
    const p = new URLSearchParams({
        search: document.getElementById('searchInput').value,
        blood_group: document.getElementById('groupFilter').value,
        component: document.getElementById('componentFilter').value,
        status: document.getElementById('statusFilter').value
    });
    window.location.href = 'inventory.php?' + p.toString();
}
function discardUnit(id, code) {
    document.getElementById('discard_unit_id').value = id;
    document.getElementById('discard_unit_code').textContent = code;
    openModal('discardModal');
}
</script>

<?php include '../includes/footer.php'; ?>
