<?php
// pages/users.php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireRole(['admin']);
$db = Database::getInstance();

if($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if($action === 'add_user') {
        $hash = password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT);
        try {
            $db->execute(
                "INSERT INTO users (username,password_hash,full_name,email,phone,role,department) VALUES (?,?,?,?,?,?,?)",
                [sanitize($_POST['username']), $hash, sanitize($_POST['full_name']), sanitize($_POST['email']), sanitize($_POST['phone']), sanitize($_POST['role']), sanitize($_POST['department'])]
            );
            $_SESSION['success'] = "User created successfully.";
        } catch(Exception $e) { $_SESSION['error'] = $e->getMessage(); }
        header('Location: users.php'); exit;
    }
    if($action === 'toggle_user') {
        $db->execute("UPDATE users SET is_active=NOT is_active WHERE id=? AND id!=1", [intval($_POST['user_id'])]);
        $_SESSION['success'] = "User status updated.";
        header('Location: users.php'); exit;
    }
    if($action === 'reset_password') {
        $hash = password_hash(sanitize($_POST['new_password']), PASSWORD_DEFAULT);
        $db->execute("UPDATE users SET password_hash=? WHERE id=?", [$hash, intval($_POST['user_id'])]);
        $_SESSION['success'] = "Password reset successfully.";
        header('Location: users.php'); exit;
    }
}

$users = $db->fetchAll("SELECT * FROM users ORDER BY role, full_name");
$pageTitle = 'Users & Roles';
include '../includes/header.php';
?>

<div class="d-flex align-center mb-4">
    <div style="margin-left:auto">
        <button class="btn btn-primary" onclick="openModal('addUserModal')"><i class="fa-solid fa-user-plus"></i> Add User</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Role</th><th>Department</th><th>Email</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;background:<?= $u['is_active']?'var(--red)':'var(--bg3)' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0">
                            <?= strtoupper(substr($u['full_name'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($u['full_name']) ?></div>
                            <div style="font-size:11px;color:var(--text3)">@<?= $u['username'] ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php $roleColors=['admin'=>'var(--red)','doctor'=>'var(--info)','nurse'=>'var(--success)','lab_technician'=>'var(--purple)','receptionist'=>'var(--warning)']; ?>
                    <span class="badge" style="background:<?= $roleColors[$u['role']]??'var(--text3)' ?>22;color:<?= $roleColors[$u['role']]??'var(--text3)' ?>;border:1px solid <?= $roleColors[$u['role']]??'var(--text3)' ?>44;text-transform:capitalize">
                        <?= str_replace('_',' ',$u['role']) ?>
                    </span>
                </td>
                <td style="font-size:13px;color:var(--text2)"><?= htmlspecialchars($u['department']?:'—') ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($u['email']) ?></td>
                <td style="font-size:12px;color:var(--text3)"><?= $u['last_login']?timeAgo($u['last_login']):'Never' ?></td>
                <td><?= statusBadge($u['is_active']?'Available':'Discarded') ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <?php if($u['id'] != 1): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn <?= $u['is_active']?'btn-warning':'btn-success' ?> btn-sm">
                                <?= $u['is_active']?'Deactivate':'Activate' ?>
                            </button>
                        </form>
                        <button onclick="resetPwd(<?= $u['id'] ?>)" class="btn btn-secondary btn-sm"><i class="fa-solid fa-key"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addUserModal">
<div class="modal" style="max-width:560px">
    <div class="modal-header"><h3>Add New User</h3><button class="modal-close" onclick="closeModal('addUserModal')">✕</button></div>
    <form method="POST"><input type="hidden" name="action" value="add_user">
    <div class="modal-body">
        <div class="form-row">
            <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
            <div class="form-group"><label>Username *</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
            <div class="form-group"><label>Role *</label>
                <select name="role" required>
                    <option value="receptionist">Receptionist</option>
                    <option value="nurse">Nurse</option>
                    <option value="lab_technician">Lab Technician</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="form-group"><label>Department</label><input type="text" name="department"></div>
            <div class="form-group" style="grid-column:span 2"><label>Password *</label><input type="password" name="password" required minlength="6"></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button><button type="submit" class="btn btn-primary">Create User</button></div>
    </form>
</div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="resetPwdModal">
<div class="modal" style="max-width:380px">
    <div class="modal-header"><h3>Reset Password</h3><button class="modal-close" onclick="closeModal('resetPwdModal')">✕</button></div>
    <form method="POST"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" id="reset_user_id">
    <div class="modal-body">
        <div class="form-group"><label>New Password *</label><input type="password" name="new_password" required minlength="6"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('resetPwdModal')">Cancel</button><button type="submit" class="btn btn-primary">Reset</button></div>
    </form>
</div>
</div>
<script>function resetPwd(id){document.getElementById('reset_user_id').value=id;openModal('resetPwdModal');}</script>
<?php include '../includes/footer.php'; ?>
