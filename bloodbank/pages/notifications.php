<?php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

// Mark all as read
if(isset($_GET['markread'])) {
    $db->execute("UPDATE notifications SET is_read=1");
    header('Location: notifications.php'); exit;
}

// System-generate current alerts
$inv = new InventoryManager();
$lowStock = $inv->checkLowStock();
foreach($lowStock as $ls) {
    $existing = $db->fetch("SELECT id FROM notifications WHERE type='low_stock' AND blood_group=? AND is_read=0", [$ls['blood_group']]);
    if(!$existing) {
        $db->execute("INSERT INTO notifications (type, title, message, blood_group) VALUES (?,?,?,?)",
            ['low_stock', "Low Stock: ".$ls['blood_group'], "Only ".$ls['available_units']." units of ".$ls['blood_group']." available (minimum: ".$ls['minimum_units'].")", $ls['blood_group']]);
    }
}

$notifications = $db->fetchAll("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50");
$unread = $db->fetch("SELECT COUNT(*) n FROM notifications WHERE is_read=0")['n'];

$pageTitle = 'Notifications';
include '../includes/header.php';
?>

<div class="d-flex align-center mb-4">
    <div style="color:var(--text2)"><?= $unread ?> unread notification(s)</div>
    <?php if($unread > 0): ?>
    <a href="?markread=1" class="btn btn-secondary btn-sm" style="margin-left:auto"><i class="fa-solid fa-check-double"></i> Mark All Read</a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if(empty($notifications)): ?>
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text3)">
        <i class="fa-solid fa-bell-slash fa-3x" style="margin-bottom:12px;display:block"></i>
        No notifications yet.
    </div>
    <?php endif; ?>
    <?php
    $typeIcons = [
        'low_stock'       => ['icon'=>'fa-chart-bar','color'=>'var(--warning)'],
        'expiry_warning'  => ['icon'=>'fa-clock','color'=>'#EF4444'],
        'request_pending' => ['icon'=>'fa-file-medical','color'=>'var(--info)'],
        'system'          => ['icon'=>'fa-gear','color'=>'var(--text3)'],
    ];
    foreach($notifications as $n):
        $ti = $typeIcons[$n['type']] ?? $typeIcons['system'];
    ?>
    <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border);<?= !$n['is_read']?'background:rgba(193,18,31,0.04)':'' ?>">
        <div style="width:36px;height:36px;background:<?=$ti['color']?>22;border:1px solid <?=$ti['color']?>44;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa-solid <?=$ti['icon']?>" style="color:<?=$ti['color']?>;font-size:14px"></i>
        </div>
        <div style="flex:1">
            <div style="font-weight:<?= !$n['is_read']?'700':'600' ?>;color:var(--text)"><?=htmlspecialchars($n['title'])?></div>
            <div style="font-size:13px;color:var(--text2);margin-top:2px"><?=htmlspecialchars($n['message'])?></div>
            <div style="font-size:11px;color:var(--text3);margin-top:4px"><?=timeAgo($n['created_at'])?></div>
        </div>
        <?php if(!$n['is_read']): ?>
        <div style="width:8px;height:8px;background:var(--red);border-radius:50%;margin-top:6px;flex-shrink:0"></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
