<?php
require __DIR__ . '/config.php';

// علّم المفاتيح المنتهية كـ expired
$stmt = $pdo->prepare("UPDATE license_keys SET status='expired' WHERE expires_at IS NOT NULL AND expires_at < NOW() AND status='active'");
$stmt->execute();

// (اختياري) سجل عملية على كل مفتاح انتهى اليوم
$pdo->exec("INSERT INTO logs (license_id, type, message)
            SELECT id, 'action', CONCAT('License auto-marked as expired at ', NOW())
            FROM license_keys
            WHERE expires_at IS NOT NULL AND expires_at < NOW() AND status='expired' AND DATE(expires_at)=CURDATE()");
echo \"OK\\n\";
