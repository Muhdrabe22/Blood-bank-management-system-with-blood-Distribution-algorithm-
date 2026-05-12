<?php
// pages/settings.php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireRole(['admin']);
$db = Database::getInstance();

if($_SERVER['REQUEST_METHOD']==='POST') {
    foreach($_POST as $key => $value) {
        if($key === 'action') continue;
        $db->execute("UPDATE clinic_settings SET setting_value=? WHERE setting_key=?", [sanitize($value), $key]);
    }
    $_SESSION['success'] = "Settings saved successfully.";
    header('Location: settings.php'); exit;
}

$settings = [];
$rows = $db->fetchAll("SELECT * FROM clinic_settings ORDER BY setting_group, setting_key");
foreach($rows as $row) $settings[$row['setting_group']][$row['setting_key']] = $row;

$pageTitle = 'System Settings';
include '../includes/header.php';
?>

<form method="POST">
<div class="tabs" data-tabs>
    <button type="button" class="tab-btn active" data-tab="tab-general">General</button>
    <button type="button" class="tab-btn" data-tab="tab-inventory">Inventory</button>
    <button type="button" class="tab-btn" data-tab="tab-donation">Donation Rules</button>
    <button type="button" class="tab-btn" data-tab="tab-alerts">Alerts</button>
    <button type="button" class="tab-btn" data-tab="tab-storage">Storage</button>
</div>

<div class="tab-content active" id="tab-general">
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-hospital" style="color:var(--info)"></i><h3>Clinic Information</h3></div>
    <div class="card-body">
        <div class="form-row">
        <?php foreach(($settings['general']??[]) as $key => $s): ?>
        <div class="form-group">
            <label><?=ucwords(str_replace('_',' ',str_replace('clinic_','',$key)))?></label>
            <input type="text" name="<?=$key?>" value="<?=htmlspecialchars($s['setting_value']?:'')?>">
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<div class="tab-content" id="tab-inventory">
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-boxes-stacked" style="color:var(--warning)"></i><h3>Component Shelf Life (Days)</h3></div>
    <div class="card-body">
        <div class="form-row">
        <?php foreach(($settings['inventory']??[]) as $key => $s): ?>
        <div class="form-group">
            <label><?=htmlspecialchars($s['description']?:$key)?></label>
            <input type="number" name="<?=$key?>" value="<?=htmlspecialchars($s['setting_value']?:'')?>">
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<div class="tab-content" id="tab-donation">
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-droplet" style="color:var(--red)"></i><h3>Donation Rules</h3></div>
    <div class="card-body">
        <div class="form-row">
        <?php foreach(($settings['donation']??[]) as $key => $s): ?>
        <div class="form-group">
            <label><?=htmlspecialchars($s['description']?:$key)?></label>
            <input type="number" name="<?=$key?>" value="<?=htmlspecialchars($s['setting_value']?:'')?>">
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<div class="tab-content" id="tab-alerts">
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-bell" style="color:var(--warning)"></i><h3>Alert Configuration</h3></div>
    <div class="card-body">
        <div class="form-row">
        <?php foreach(($settings['alerts']??[]) as $key => $s): ?>
        <div class="form-group">
            <label><?=htmlspecialchars($s['description']?:$key)?></label>
            <input type="text" name="<?=$key?>" value="<?=htmlspecialchars($s['setting_value']?:'')?>">
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<div class="tab-content" id="tab-storage">
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-temperature-half" style="color:var(--cyan)"></i><h3>Storage Temperatures (°C)</h3></div>
    <div class="card-body">
        <div class="form-row">
        <?php foreach(($settings['storage']??[]) as $key => $s): ?>
        <div class="form-group">
            <label><?=htmlspecialchars($s['description']?:$key)?></label>
            <input type="number" name="<?=$key?>" step="0.1" value="<?=htmlspecialchars($s['setting_value']?:'')?>">
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<div style="margin-top:16px">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save All Settings</button>
</div>
</form>

<?php include '../includes/footer.php'; ?>
