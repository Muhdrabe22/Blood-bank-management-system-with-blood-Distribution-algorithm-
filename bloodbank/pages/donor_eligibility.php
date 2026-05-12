<?php
// pages/donor_eligibility.php
require_once '../config.php';
require_once '../includes/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

$result = null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    $donorId = intval($_POST['donor_id'] ?? 0);
    if($donorId) {
        $donor = $db->fetch("SELECT d.*, COALESCE(MAX(dn.donation_date),'Never') as last_donation FROM donors d LEFT JOIN donations dn ON d.id=dn.donor_id WHERE d.id=? GROUP BY d.id", [$donorId]);
        if($donor) {
            $issues = [];
            $eligible = true;

            // Age check (17-65)
            $age = date_diff(date_create($donor['date_of_birth']), date_create('today'))->y;
            if($age < 17) { $issues[] = "Age below minimum (17 years). Current age: $age"; $eligible = false; }
            if($age > 65) { $issues[] = "Age above maximum (65 years). Current age: $age"; $eligible = false; }

            // Weight check (>50kg)
            if($donor['weight'] && $donor['weight'] < 50) { $issues[] = "Weight below minimum (50kg). Current: {$donor['weight']}kg"; $eligible = false; }

            // Interval check (56 days)
            if($donor['last_donation'] !== 'Never') {
                $daysSince = date_diff(date_create($donor['last_donation']), date_create('today'))->days;
                if($daysSince < 56) { $issues[] = "Less than 56 days since last donation ($daysSince days). Next eligible: " . date('d M Y', strtotime($donor['last_donation'].' +56 days')); $eligible = false; }
            }

            // Check existing ineligibility
            if(!$donor['is_eligible']) {
                $issues[] = "Marked ineligible: " . ($donor['ineligibility_reason'] ?: 'No reason given');
                $eligible = false;
            }

            $result = ['donor' => $donor, 'eligible' => $eligible, 'issues' => $issues, 'age' => $age,
                       'daysSince' => $donor['last_donation'] !== 'Never' ? date_diff(date_create($donor['last_donation']),date_create('today'))->days : null];
        }
    }
}

$donors = $db->fetchAll("SELECT id, donor_code, full_name, blood_group FROM donors ORDER BY full_name");
$pageTitle = 'Donor Eligibility Check';
include '../includes/header.php';
?>
<div class="grid-2">
<div>
<div class="card mb-6">
    <div class="card-header"><i class="fa-solid fa-clipboard-check" style="color:var(--info)"></i><h3>Check Donor Eligibility</h3></div>
    <form method="POST">
    <div class="card-body">
        <div class="form-group">
            <label>Select Donor *</label>
            <select name="donor_id" required>
                <option value="">— Choose Donor —</option>
                <?php foreach($donors as $d): ?>
                <option value="<?=$d['id']?>"><?=htmlspecialchars($d['full_name'])?> (<?=$d['donor_code']?>) — <?=$d['blood_group']?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Check Eligibility</button>
    </div>
    </form>
</div>

<?php if($result): ?>
<div class="card" style="border-color:<?= $result['eligible']?'rgba(16,185,129,.5)':'rgba(239,68,68,.5)' ?>">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)">
            <div style="width:56px;height:56px;background:<?=$result['eligible']?'var(--success)':'#EF4444'?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px">
                <?=$result['eligible']?'✓':'✕'?>
            </div>
            <div>
                <div style="font-size:20px;font-weight:700;color:var(--text)"><?=htmlspecialchars($result['donor']['full_name'])?></div>
                <div><?=bloodGroupBadge($result['donor']['blood_group'])?> <span style="font-size:13px;color:var(--text3)">· <?=$result['donor']['donor_code']?></span></div>
            </div>
            <div style="margin-left:auto;text-align:right">
                <div style="font-size:26px;font-weight:800;color:<?=$result['eligible']?'var(--success)':'#EF4444'?>;font-family:'Syne',sans-serif">
                    <?=$result['eligible']?'ELIGIBLE':'NOT ELIGIBLE'?>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <?php $checks = [
                ['label'=>'Age','value'=>$result['age'].' years','ok'=>($result['age']>=17&&$result['age']<=65),'req'=>'17–65 years'],
                ['label'=>'Weight','value'=>($result['donor']['weight']?$result['donor']['weight'].'kg':'Not recorded'),'ok'=>(!$result['donor']['weight']||$result['donor']['weight']>=50),'req'=>'≥50kg'],
                ['label'=>'Last Donation','value'=>$result['donor']['last_donation']==='Never'?'Never':formatDate($result['donor']['last_donation']),'ok'=>($result['daysSince']===null||$result['daysSince']>=56),'req'=>'56+ days ago'],
                ['label'=>'Total Donations','value'=>$result['donor']['total_donations'],'ok'=>true,'req'=>''],
            ];
            foreach($checks as $c): ?>
            <div style="background:var(--bg3);border:1px solid <?=$c['ok']?'rgba(16,185,129,.3)':'rgba(239,68,68,.3)'?>;border-radius:8px;padding:12px">
                <div style="font-size:11px;color:var(--text3);margin-bottom:4px"><?=$c['label']?></div>
                <div style="font-weight:700;color:<?=$c['ok']?'var(--success)':'#EF4444'?>"><?=$c['value']?></div>
                <?php if($c['req']): ?><div style="font-size:10px;color:var(--text3)">Req: <?=$c['req']?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if(!empty($result['issues'])): ?>
        <div class="alert alert-danger">
            <div><strong>Issues Found:</strong></div>
            <ul style="margin:8px 0 0 16px">
            <?php foreach($result['issues'] as $issue): ?>
            <li style="margin-bottom:4px"><?=htmlspecialchars($issue)?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Donor passes all eligibility criteria. Proceed with donation.</div>
        <?php endif; ?>

        <?php if($result['eligible']): ?>
        <a href="donations.php" class="btn btn-success"><i class="fa-solid fa-droplet"></i> Record Donation</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>

<!-- Criteria Reference -->
<div class="card">
    <div class="card-header"><i class="fa-solid fa-book-medical" style="color:var(--warning)"></i><h3>Eligibility Criteria</h3></div>
    <div class="card-body">
        <?php $criteria = [
            ['icon'=>'🎂','title'=>'Age','desc'=>'17 to 65 years old'],
            ['icon'=>'⚖️','title'=>'Weight','desc'=>'Minimum 50kg body weight'],
            ['icon'=>'🩸','title'=>'Hemoglobin','desc'=>'≥12.5 g/dL (female), ≥13.5 g/dL (male)'],
            ['icon'=>'💓','title'=>'Blood Pressure','desc'=>'Systolic 90–180 mmHg, Diastolic 50–100 mmHg'],
            ['icon'=>'🌡️','title'=>'Temperature','desc'=>'Normal body temperature (≤37.5°C)'],
            ['icon'=>'⏱️','title'=>'Donation Interval','desc'=>'At least 56 days (8 weeks) between whole blood donations'],
            ['icon'=>'🚫','title'=>'Deferrals','desc'=>'No HIV, Hepatitis B/C, Malaria, Syphilis, recent surgery, pregnancy'],
            ['icon'=>'💊','title'=>'Medications','desc'=>'No anticoagulants, immunosuppressants, or certain antibiotics'],
        ]; ?>
        <?php foreach($criteria as $c): ?>
        <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
            <div style="font-size:20px"><?=$c['icon']?></div>
            <div>
                <div style="font-weight:700;color:var(--text);font-size:14px"><?=$c['title']?></div>
                <div style="font-size:13px;color:var(--text2)"><?=$c['desc']?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
