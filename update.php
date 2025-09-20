<?php
require __DIR__.'/auth.php';
require __DIR__.'/config.php';

$OWNER  = 'midoalrawi';
$REPO   = 'lic-manager';
$BRANCH = 'main';

$root = __DIR__;
$localVer = trim(@file_get_contents($root.'/VERSION')) ?: '0.0.0';

function latest_ver($o,$r,$b,$tok=null){
  $u="https://raw.githubusercontent.com/$o/$r/$b/VERSION";
  $ch=curl_init($u);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'LM-Updater']);
  if($tok) curl_setopt($ch,CURLOPT_HTTPHEADER,["Authorization: token $tok"]);
  $body=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return $code===200?trim($body):null;
}
function ver_cmp($a,$b){ return version_compare($a,$b); }
function do_update($o,$r,$b,$root,$tok=null,&$msg=null){
  if (is_dir($root.'/.git')) {
    $cmds=[
      "cd ".escapeshellarg($root)." && git fetch origin",
      "cd ".escapeshellarg($root)." && git reset --hard origin/$b"
    ];
    foreach($cmds as $c){ $out=shell_exec($c." 2>&1"); if($out===null){ $msg='git failed'; return false; } }
    $msg='تم التحديث عبر git'; return true;
  }
  $zip="https://codeload.github.com/$o/$r/zip/refs/heads/$b";
  $tmp=sys_get_temp_dir()."/lm_".uniqid().".zip";
  $fh=fopen($tmp,'w'); $ch=curl_init($zip);
  curl_setopt_array($ch,[CURLOPT_FILE=>$fh,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30,CURLOPT_USERAGENT=>'LM-Updater']);
  if($tok) curl_setopt($ch,CURLOPT_HTTPHEADER,["Authorization: token $tok"]);
  $ok=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); fclose($fh);
  if(!$ok||$code!==200){@unlink($tmp); $msg='download failed'; return false;}
  if(!class_exists('ZipArchive')){@unlink($tmp); $msg='zip unavailable'; return false;}
  $z=new ZipArchive(); if($z->open($tmp)!==true){@unlink($tmp); $msg='zip open failed'; return false;}
  $rootDir=rtrim($z->getNameIndex(0),'/'); $to=sys_get_temp_dir()."/lm_ex_".uniqid(); @mkdir($to,0777,true);
  if(!$z->extractTo($to)){ $z->close(); @unlink($tmp); $msg='extract failed'; return false; }
  $z->close(); @unlink($tmp);
  $src=$to.'/'.$rootDir;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
  foreach($it as $i){
    $rel=substr($i->getPathname(),strlen($src)+1);
    if($rel==='config.php') continue;
    $dest=$root.'/'.$rel;
    if($i->isDir()){ if(!is_dir($dest)) @mkdir($dest,0755,true); }
    else { @copy($i->getPathname(),$dest); }
  }
  $cl=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($to,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($cl as $f){ $f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname()); } @rmdir($to);
  $msg='تم التحديث عبر ZIP'; return true;
}

$token = getenv('GITHUB_TOKEN') ?: null;

$act = $_POST['action'] ?? '';
if ($act==='check') {
  $_SESSION['latest_ver'] = latest_ver($OWNER,$REPO,$BRANCH,$token);
  header('Location: update.php'); exit;
}
if ($act==='update') {
  $ok = do_update($OWNER,$REPO,$BRANCH,$root,$token,$m);
  $localVer = trim(@file_get_contents($root.'/VERSION')) ?: $localVer;
  $_SESSION['flash'] = ($ok?'نجاح: ':'فشل: ').$m.' | v'.$localVer;
  unset($_SESSION['latest_ver']); header('Location: update.php'); exit;
}
$latest = $_SESSION['latest_ver'] ?? null;
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8"><title>التحديث</title>
<style>
body{font-family:Arial,sans-serif;background:#f7f7f7;padding:20px}
.wrap{background:#fff;border:1px solid #ddd;max-width:520px;margin:auto;padding:16px}
.row{display:flex;justify-content:space-between;margin:8px 0}
.badge{padding:2px 6px;border-radius:4px;color:#fff;font-size:12px}
.ok{background:#198754}.warn{background:#ffc107;color:#222}.err{background:#dc3545}
.btn{padding:8px 12px;border:none;border-radius:4px;cursor:pointer}
.primary{background:#0d6efd;color:#fff}
.secondary{background:#6c757d;color:#fff}
.alert{background:#d1e7dd;color:#0f5132;padding:8px;margin-bottom:10px;border:1px solid #badbcc;border-radius:4px}
.top a{display:inline-block;margin-right:6px;text-decoration:none;color:#fff;background:#6c757d;padding:6px 10px;border-radius:4px}
</style>
</head>
<body>
<div class="top"><a href="dashboard.php">الرجوع للداشبورد</a></div>

<?php if(!empty($_SESSION['flash'])): ?>
  <div class="alert"><?= htmlspecialchars($_SESSION['flash'],ENT_QUOTES,'UTF-8'); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="wrap">
  <div class="row"><div>النسخة الحالية</div><div>v<?= htmlspecialchars($localVer,ENT_QUOTES,'UTF-8') ?> <span class="badge ok">محلي</span></div></div>
  <div class="row">
    <div>آخر نسخة</div>
    <div>
      <?php if ($latest===null): ?>
        <span class="badge warn">غير معروف</span>
      <?php else: ?>
        v<?= htmlspecialchars($latest,ENT_QUOTES,'UTF-8') ?> <span class="badge warn">GitHub</span>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" style="margin-top:10px">
    <input type="hidden" name="action" value="check">
    <button class="btn secondary">تحقّق من التحديث</button>
  </form>

  <?php if ($latest && version_compare($latest,$localVer)>0): ?>
    <form method="post" style="margin-top:8px" onsubmit="return confirm('تحديث إلى v<?= htmlspecialchars($latest,ENT_QUOTES,'UTF-8') ?>؟');">
      <input type="hidden" name="action" value="update">
      <button class="btn primary">تحديث الآن</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
