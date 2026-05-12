<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();
$algo = new BloodDistributionAlgorithm();
$inv  = new InventoryManager();

// Handle distribution
if($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if($action === 'issue_blood') {
        $db->beginTransaction();
        try {
            $requestId = intval($_POST['request_id']);
            $unitIds   = $_POST['unit_ids'] ?? [];
            $request   = $db->fetch("SELECT * FROM blood_requests WHERE id=?", [$requestId]);
            
            if(!$request) throw new Exception("Request not found");
            if(empty($unitIds)) throw new Exception("No units selected");

            $distCode = $inv->generateCode('DIST', 'blood_distributions', 'distribution_code');
            
            foreach($unitIds as $unitId) {
                $unit = $db->fetch("SELECT * FROM blood_units WHERE id=? AND status='Available'", [$unitId]);
                if(!$unit) continue;

                // Check compatibility
                $compat = $algo->isCompatible($unit['blood_group'], $request['blood_group']);
                
                // Create distribution record
                $db->execute(
                    "INSERT INTO blood_distributions 
                     (distribution_code, request_id, unit_id, patient_id, blood_group, component_type, issue_date, issued_by, received_by, ward, crossmatch_result, notes)
                     VALUES (?,?,?,?,?,?,NOW(),?,?,?,?,?)",
                    [
                        $distCode, $requestId, $unitId, $request['patient_id'],
                        $unit['blood_group'], $unit['component_type'],
                        $_SESSION['user_id'], sanitize($_POST['received_by']??''),
                        sanitize($_POST['ward']??''),
                        $compat['is_compatible'] ? 'Compatible' : 'Incompatible',
                        sanitize($_POST['notes']??'')
                    ]
                );
                
                // Mark unit as Issued
                $db->execute("UPDATE blood_units SET status='Issued' WHERE id=?", [$unitId]);
            }
            
            // Update request status
            $unitsIssued = count($unitIds);
            $status = $unitsIssued >= $request['units_requested'] ? 'Fulfilled' : 'Partially Fulfilled';
            $db->execute(
                "UPDATE blood_requests SET status=?, units_approved=?, approved_by=?, approval_date=NOW() WHERE id=?",
                [$status, $unitsIssued, $_SESSION['user_id'], $requestId]
            );

            $db->commit();
            $auth->logActivity('Issue Blood', 'Distribution', "Issued $unitsIssued units for request #{$requestId}");
            $_SESSION['success'] = "$unitsIssued blood unit(s) issued successfully!";
        } catch(Exception $e) {
            $db->rollback();
            $_SESSION['error'] = "Distribution failed: " . $e->getMessage();
        }
        header('Location: distribution.php'); exit;
    }

    if($action === 'update_transfusion') {
        $db->execute(
            "UPDATE blood_distributions SET transfusion_status=?, transfusion_date=NOW(), transfusion_outcome=?, adverse_reaction=?, adverse_reaction_details=? WHERE id=?",
            [
                $_POST['transfusion_status'], sanitize($_POST['outcome']),
                isset($_POST['adverse_reaction'])?1:0, sanitize($_POST['adverse_details']??''),
                intval($_POST['dist_id'])
            ]
        );
        $_SESSION['success'] = "Transfusion record updated.";
        header('Location: distribution.php'); exit;
    }
}

// Auto-find best units for a request
$matchResults = null;
$selectedRequest = null;
if(isset($_GET['request_id']) && $_GET['request_id']) {
    $matchResults = $algo->findBestUnits(intval($_GET['request_id']));
    $selectedRequest = $matchResults['request'] ?? null;
}

// Recent distributions
$distributions = $db->fetchAll(
    "SELECT bd.*, p.full_name as patient_name, bu.unit_code, bu.blood_group as unit_blood_group,
            u.full_name as issued_by_name, br.request_code, br.urgency
     FROM blood_distributions bd
     JOIN patients p ON bd.patient_id = p.id
     JOIN blood_units bu ON bd.unit_id = bu.id
     JOIN users u ON bd.issued_by = u.id
     JOIN blood_requests br ON bd.request_id = br.id
     ORDER BY bd.issue_date DESC LIMIT 30"
);

// Pending approved requests ready for distribution
$readyRequests = $db->fetchAll(
    "SELECT br.*, p.full_name as patient_name, p.blood_group as patient_group
     FROM blood_requests br
     JOIN patients p ON br.patient_id = p.id
     WHERE br.status IN ('Pending','Processing','Approved')
     ORDER BY CASE br.urgency WHEN 'Emergency' THEN 1 WHEN 'Urgent' THEN 2 ELSE 3 END, br.created_at"
);

$pageTitle = 'Blood Distribution';
$pageSubtitle = 'Smart Issuance & Tracking';
include '../includes/header.php';
?>

<div class="d-flex align-center mb-6" style="gap:12px;flex-wrap:wrap">
    <div>
        <div style="font-size:14px;color:var(--text2)">Use the algorithm to auto-match blood units to patient requests</div>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px">
        <a href="requests.php" class="btn btn-secondary"><i class="fa-solid fa-list"></i> All Requests</a>
        <a href="crossmatch.php" class="btn btn-secondary"><i class="fa-solid fa-vials"></i> Crossmatch</a>
    </div>
</div>

<!-- DISTRIBUTION ALGORITHM PANEL -->
<div class="card mb-6" style="border-color:rgba(193,18,31,.3)">
    <div class="card-header" style="background:rgba(193,18,31,.05)">
        <div style="width:10px;height:10px;background:var(--red);border-radius:50%;box-shadow:0 0 10px var(--red);animation:pulse 1.5s infinite alternate"></div>
        <h3 style="color:var(--red-light)">🧬 Blood Distribution Algorithm</h3>
        <span style="font-size:12px;color:var(--text2);margin-left:8px">Priority scoring: Compatibility × Expiry × Freshness × Urgency</span>
    </div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:250px;margin:0">
                <label>Select Blood Request</label>
                <select name="request_id">
                    <option value="">-- Select a pending request --</option>
                    <?php foreach($readyRequests as $rq): ?>
                    <option value="<?= $rq['id'] ?>" <?= isset($_GET['request_id']) && $_GET['request_id']==$rq['id']?'selected':'' ?>>
                        <?= $rq['request_code'] ?> — <?= htmlspecialchars($rq['patient_name']) ?> 
                        (<?= $rq['blood_group'] ?> · <?= $rq['units_requested'] ?> units · <?= $rq['urgency'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-wand-magic-sparkles"></i> Find Best Units</button>
        </form>

        <?php if($matchResults): ?>
        <div style="margin-top:24px">
            <?php if($matchResults['can_fulfill']): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Algorithm found <strong><?= $matchResults['units_found'] ?></strong> compatible unit(s) from <strong><?= $matchResults['total_available'] ?></strong> candidates. Request can be fulfilled.
            </div>
            <?php else: ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-xmark"></i>
                Only <strong><?= $matchResults['units_found'] ?></strong> unit(s) available but <strong><?= $matchResults['request']['units_requested'] ?></strong> needed. Consider emergency alternatives.
            </div>
            <?php endif; ?>

            <div style="background:var(--bg3);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:var(--text3);margin-bottom:12px;font-weight:600">Request Details</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
                    <div><div style="font-size:11px;color:var(--text3)">Patient</div><div style="font-weight:600"><?= htmlspecialchars($selectedRequest['patient_name'] ?? '') ?></div></div>
                    <div><div style="font-size:11px;color:var(--text3)">Blood Group</div><div><?= bloodGroupBadge($selectedRequest['blood_group']) ?></div></div>
                    <div><div style="font-size:11px;color:var(--text3)">Component</div><div style="font-weight:600"><?= $selectedRequest['component_type'] ?></div></div>
                    <div><div style="font-size:11px;color:var(--text3)">Units Needed</div><div style="font-size:20px;font-weight:700;color:var(--red)"><?= $selectedRequest['units_requested'] ?></div></div>
                    <div><div style="font-size:11px;color:var(--text3)">Urgency</div><div><?= statusBadge($selectedRequest['urgency']) ?></div></div>
                </div>
            </div>

            <!-- Algorithm Results Table -->
            <form method="POST">
                <input type="hidden" name="action" value="issue_blood">
                <input type="hidden" name="request_id" value="<?= $selectedRequest['id'] ?>">
                
                <div style="font-size:13px;font-weight:600;color:var(--text2);margin-bottom:8px">
                    Ranked Candidates (by Algorithm Priority Score ↓)
                </div>
                <div class="table-wrap" style="margin-bottom:16px">
                <table>
                    <thead><tr>
                        <th><input type="checkbox" id="checkAll" onchange="toggleAll(this)"> Select</th>
                        <th>Unit Code</th><th>Blood Group</th><th>Component</th>
                        <th>Priority Score</th><th>Compatibility</th><th>Expires</th><th>Age (days)</th><th>Location</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($matchResults['selected_units'] as $i => $unit): ?>
                    <tr style="<?= $i===0?'background:rgba(16,185,129,.05);':'' ?>">
                        <td><input type="checkbox" name="unit_ids[]" value="<?= $unit['id'] ?>" <?= $i<$selectedRequest['units_requested']?'checked':'' ?>></td>
                        <td>
                            <div style="font-family:monospace;font-size:12px"><?= $unit['unit_code'] ?></div>
                            <?php if($i===0): ?><span class="badge badge-success" style="font-size:9px">BEST MATCH</span><?php endif; ?>
                        </td>
                        <td><?= bloodGroupBadge($unit['blood_group']) ?></td>
                        <td style="font-size:12px"><?= $unit['component_type'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <span style="font-size:18px;font-weight:700;color:<?= $unit['priority_score']>70?'#10B981':($unit['priority_score']>50?'#F59E0B':'#EF4444') ?>"><?= $unit['priority_score'] ?></span>
                                <div class="progress-wrap" style="width:60px">
                                    <div class="progress-bar" style="width:<?= min(100,$unit['priority_score']) ?>%;background:<?= $unit['priority_score']>70?'#10B981':($unit['priority_score']>50?'#F59E0B':'#EF4444') ?>"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px;color:var(--text2)">Score: <?= $unit['compatibility_score'] ?>/10</div>
                            <?php if($unit['blood_group']===$selectedRequest['blood_group']): ?>
                            <span class="badge badge-success" style="font-size:9px">EXACT MATCH</span>
                            <?php else: ?>
                            <span class="badge badge-info" style="font-size:9px">COMPATIBLE</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="color:<?= $unit['days_to_expiry']<=3?'#EF4444':($unit['days_to_expiry']<=7?'#F59E0B':'#10B981') ?>;font-weight:600">
                                <?= $unit['days_to_expiry'] ?>d left
                            </div>
                            <div style="font-size:11px;color:var(--text3)"><?= formatDate($unit['expiry_date']) ?></div>
                        </td>
                        <td style="color:var(--text2)"><?= $unit['age_days'] ?> days</td>
                        <td style="font-size:12px;color:var(--text3)"><?= $unit['storage_location'] ?: 'Main Bank' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Received By</label>
                        <input type="text" name="received_by" placeholder="Nurse/Doctor name" required>
                    </div>
                    <div class="form-group">
                        <label>Ward / Department</label>
                        <input type="text" name="ward" placeholder="e.g. Ward 3B, ICU">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Optional notes">
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" style="min-width:180px">
                        <i class="fa-solid fa-truck-medical"></i> Issue Selected Units
                    </button>
                    <a href="distribution.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if(empty($readyRequests)): ?>
        <div style="text-align:center;padding:32px;color:var(--text3)">
            <i class="fa-solid fa-circle-check fa-2x" style="margin-bottom:8px;display:block;color:var(--success)"></i>
            No pending blood requests at this time.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- RECENT DISTRIBUTIONS -->
<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-history" style="color:var(--info)"></i>
        <h3>Recent Distributions</h3>
        <div class="card-actions">
            <a href="reports.php?type=distribution" class="btn btn-secondary btn-sm">View Report</a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Dist. Code</th><th>Patient</th><th>Unit Code</th>
                <th>Blood Group</th><th>Component</th><th>Issued By</th>
                <th>Crossmatch</th><th>Transfusion</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($distributions as $dist): ?>
            <tr>
                <td style="font-family:monospace;font-size:11px"><?= $dist['distribution_code'] ?></td>
                <td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($dist['patient_name']) ?></td>
                <td style="font-family:monospace;font-size:11px;color:var(--text3)"><?= $dist['unit_code'] ?></td>
                <td><?= bloodGroupBadge($dist['blood_group']) ?></td>
                <td style="font-size:12px"><?= $dist['component_type'] ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($dist['issued_by_name']) ?></td>
                <td><?= statusBadge($dist['crossmatch_result']) ?></td>
                <td><?= statusBadge($dist['transfusion_status']) ?></td>
                <td style="font-size:12px;color:var(--text3)"><?= formatDate($dist['issue_date'],'d M H:i') ?></td>
                <td>
                    <?php if($dist['transfusion_status']==='Pending'): ?>
                    <button onclick="updateTransfusion(<?= $dist['id'] ?>)" class="btn btn-info btn-sm">
                        <i class="fa-solid fa-syringe"></i> Update
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($distributions)): ?>
            <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3)">No distributions recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TRANSFUSION UPDATE MODAL -->
<div class="modal-overlay" id="transfusionModal">
<div class="modal" style="max-width:480px">
    <div class="modal-header">
        <h3>Update Transfusion Record</h3>
        <button class="modal-close" onclick="closeModal('transfusionModal')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="update_transfusion">
    <input type="hidden" name="dist_id" id="transfusion_dist_id">
    <div class="modal-body">
        <div class="form-row">
            <div class="form-group">
                <label>Transfusion Status *</label>
                <select name="transfusion_status" required>
                    <option value="Transfused">Transfused</option>
                    <option value="Returned">Returned to Bank</option>
                    <option value="Discarded">Discarded</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Outcome / Notes</label>
            <textarea name="outcome" rows="3" placeholder="Patient response, complications, etc."></textarea>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="adverse_reaction" style="width:auto;margin-right:8px"> Adverse Reaction Observed</label>
        </div>
        <div class="form-group">
            <label>Adverse Reaction Details</label>
            <textarea name="adverse_details" rows="2" placeholder="Describe reaction if any..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('transfusionModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Record</button>
    </div>
    </form>
</div>
</div>

<script>
function toggleAll(cb) {
    document.querySelectorAll('input[name="unit_ids[]"]').forEach(c => c.checked = cb.checked);
}
function updateTransfusion(id) {
    document.getElementById('transfusion_dist_id').value = id;
    openModal('transfusionModal');
}
</script>

<?php include '../includes/footer.php'; ?>
