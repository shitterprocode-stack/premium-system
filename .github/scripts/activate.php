<?php
// Получаем данные из GitHub Actions
$udid = getenv('UDID');
$device_id = getenv('DEVICE_ID');

echo "🎯 Starting UDID activation...\n";
echo "🔑 UDID: " . $udid . "\n";
echo "📱 Device ID: " . $device_id . "\n";

if (empty($udid) || empty($device_id)) {
    echo "❌ Missing UDID or Device ID\n";
    exit(1);
}

// Загружаем текущую базу данных
$database_file = 'database.json';
if (!file_exists($database_file)) {
    echo "❌ Database file not found\n";
    exit(1);
}

$database_json = file_get_contents($database_file);
$database = json_decode($database_json, true);

if (!$database) {
    echo "❌ Failed to parse database\n";
    exit(1);
}

echo "📊 Database loaded with " . count($database) . " keys\n";

// Проверяем существование UDID
if (!isset($database[$udid])) {
    echo "❌ UDID not found: $udid\n";
    exit(1);
}

$keyData = $database[$udid];
echo "✅ UDID found in database\n";

// Проверяем не активирован ли уже на другом устройстве
if (!empty($keyData['device_id']) && $keyData['device_id'] != $device_id) {
    echo "❌ UDID already activated on another device: " . $keyData['device_id'] . "\n";
    exit(1);
}

// Проверяем не истек ли срок
if (!empty($keyData['expiry_date'])) {
    $expiry = DateTime::createFromFormat('Y-m-d H:i:s', $keyData['expiry_date']);
    if ($expiry && $expiry < new DateTime()) {
        echo "❌ UDID expired: " . $keyData['expiry_date'] . "\n";
        exit(1);
    }
}

// АКТИВИРУЕМ КЛЮЧ
$currentTime = date('Y-m-d H:i:s');
$days = intval($keyData['days']);
$expiryDate = date('Y-m-d H:i:s', strtotime("+{$days} days"));

$database[$udid]['activated_at'] = $currentTime;
$database[$udid]['expiry_date'] = $expiryDate;
$database[$udid]['device_id'] = $device_id;
$database[$udid]['status'] = 'activated';
$database[$udid]['last_access'] = $currentTime;

// Сохраняем обновленную базу
file_put_contents($database_file, json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Коммитим изменения
exec('git config --global user.email "actions@github.com"');
exec('git config --global user.name "GitHub Actions"');
exec('git add database.json');
exec('git commit -m "🔑 Activate UDID: ' . $udid . ' for device: ' . substr($device_id, 0, 8) . '"');
exec('git push');

echo "✅ UDID activated successfully!\n";
echo "📅 Expires: $expiryDate\n";
echo "⏰ Activated: $currentTime\n";
echo "📱 Device: " . substr($device_id, 0, 8) . "...\n";
echo "🎉 Activation complete!\n";
?>
