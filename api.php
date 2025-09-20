<?php
require __DIR__ . '/config.php';
require __DIR__ . '/validate_hwid.php';

header('Content-Type: application/json; charset=utf-8');

$action = '';
if (isset($_GET['check_key'])) $action='check';
if (isset($_GET['activate']))  $action='activate';
if (isset($_GET['deactivate']))$action='deactivate';

$key  = $_POST['key']  ?? '';
$hwid = $_POST['hwid'] ?? '';

if ($key === '') { echo json_encode(['error'=>'key required']); exit; }
if ($action === '') { echo json_encode(['error'=>'invalid action']); exit; }
if ($hwid !== '' && !validate_hwid($hwid)) { echo json_encode(['error'=>'invalid hwid format']); exit; }

$stmt = $pdo->prepare("SELECT * FROM license_keys WHERE `key`=? LIMIT 1");
$stmt->execute([$key]);
$lic = $stmt->fetch();
if (!$lic) { echo json_encode(['error'=>'license not found']); exit; }

if ($action === 'check') {
    if ($lic['status'] !== 'active') { echo json_encode(['valid'=>false,'message'=>'License not active']); exit; }
    echo json_encode(['valid'=>true,'message'=>'License active','expires_at'=>$lic['expires_at'],'max_devices'=>(int)$lic['max_devices']]); exit;
}

if ($action === 'activate') {
    if ($lic['status'] !== 'active') { echo json_encode(['activated'=>false,'message'=>'License not active']); exit; }
    if ($hwid !== '') {
        $q = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE license_id=? AND hwid=?");
        $q->execute([$lic['id'],$hwid]);
        if ((int)$q->fetchColumn() === 0) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE license_id=? AND is_active=1");
            $cnt->execute([$lic['id']]);
            if ((int)$cnt->fetchColumn() >= (int)$lic['max_devices']) { echo json_encode(['activated'=>false,'message'=>'Max devices reached']); exit; }
            $pdo->prepare("INSERT INTO devices (license_id, hwid, is_active, activated_at) VALUES (?, ?, 1, NOW())")->execute([$lic['id'],$hwid]);
        }
    }
    echo json_encode(['activated'=>true,'message'=>'Device activated']); exit;
}

if ($action === 'deactivate') {
    if ($hwid === '') { echo json_encode(['deactivated'=>false,'message'=>'hwid required']); exit; }
    $pdo->prepare("DELETE FROM devices WHERE license_id=? AND hwid=?")->execute([$lic['id'],$hwid]);
    echo json_encode(['deactivated'=>true,'message'=>'Device deactivated']); exit;
}
