<?php
function validate_hwid($hwid) {
    // Regex يتحقق من صيغة UUID قياسية RFC-4122
    return (bool)preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $hwid);
}
