<?php
// pages/audit_log.php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

$page   = max(1, intval($_GET['page']??1));
$offset = ($page-1)*ITEMS_PER_PAGE;
$fUser  = intval($_GET['user_id']??0);
$fMod   = $_GET['module'] ?? '';
$search = sanitize($_GET['search']??'');

$where = "WHERE 1=1";
$params = [];
if($fUser)  { $where .= " AND al.user_id=?"; $params[] = $fUser; }
if($fMod)   { $where .= " AND al.module=?"; $params[] = $fMod; }
if($search) { $where .= " AND al.description LIKE ?"; $params[] = "%$search%"; }

$total = $db->fetch("SELECT COUNT(*) n FROM activity_logs al $where", $params)['n'];
$logs  = $db->fetchAll("SELECT al.*, u.full_name, u.role FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id $where ORDER BY al.created_at DESC LIMIT ".ITEMS_PER_PAGE." OFFSET $offset", $params);
$totalPages = ceil($total/ITEMS_PER_PAGE);
$users   = $db->fetchAll("SELECT id,full_name FROM users ORDER BY full_name");
$modules = $db->fetchAll("SELECT DISTINCT module FROM activity_logs ORDER BY module");

$pageTitle = 'Audit Log';
include '../includes/header.php';
?>
<div class="card">
    <div class="filter-bar">
        <div class="search-bar"><i class="fa-solid fa-search search-icon"></i><input type="text" id="si" placeholder="Search description..." value="<?=$search?>"></div>
        <select id="userF"><option value="">All Users</option><?php foreach($users as $u): ?><option value="<?=$u['id']?>" <?=$fUser==$u['id']?'selected':''?>><?=htmlspecialchars($u['full_name'])?></option><?php endforeach; ?></select>
        <select id="modF"><option value="">All Modules</option><?php foreach($modules as $m): ?><option <?=$fMod===$m['module']?'selected':''?>><?=$m['module']?></option><?php endforeach; ?></select>
        <button class="btn btn-secondary btn-sm" onclick="window.location.href='audit_log.php?search='+document.getElementById('si').value+'&user_id='+document.getElementById('userF').value+'&module='+document.getElementById('modF').value"><i class="fa-solid fa-filter"></i></button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach($logs as $log): ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap"><?=formatDate($log['created_at'],'d M Y H:i:s')?></td>
                <td style="font-weight:600;color:var(--text)"><?=htmlspecialchars($log['full_name']??'System')?></td>
                <td style="font-size:11px;color:var(--text3)"><?=str_replace('_',' ',$log['role']??'')?></td>
                <td><span class="badge badge-info" style="font-size:10px"><?=$log['action']?></span></td>
                <td style="font-size:12px;color:var(--text2)"><?=$log['module']?></td>
                <td style="font-size:12px"><?=htmlspecialchars($log['description'])?></td>
                <td style="font-size:11px;font-family:monospace;color:var(--text3)"><?=$log['ip_address']?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if($totalPages>1): ?><div class="pagination" style="padding:16px"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="?page=<?=$i?>&search=<?=$search?>&user_id=<?=$fUser?>&module=<?=$fMod?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
