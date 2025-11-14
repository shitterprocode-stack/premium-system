<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запроса
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$udid = $_GET['udid'] ?? '';

// База данных в JSON файле
$database_file = '../database.json';
$data = [];

// Читаем базу данных
if (file_exists($database_file)) {
    $json_content = file_get_contents($database_file);
    $data = json_decode($json_content, true) ?? [];
}

// Ищем UDID в базе
$found = false;
$isPremium = false;
$daysLeft = 0;
$status = "Not Found";

if ($udid && isset($data[$udid])) {
    $device = $data[$udid];
    $expiry_date = strtotime($device['expiry_date']);
    $current_time = time();
    
    if ($expiry_date > $current_time) {
        $isPremium = true;
        $daysLeft = ceil(($expiry_date - $current_time) / (60 * 60 * 24));
        $status = "Active Premium";
        $found = true;
    } else {
        $status = "Subscription Expired";
        $found = true;
    }
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'found' => $found,
    'premium' => $isPremium,
    'days_left' => $daysLeft,
    'status' => $status,
    'udid' => $udid
]);

// Логируем запрос
file_put_contents('../logs.txt', date('Y-m-d H:i:s') . " - CHECK - UDID: $udid, Premium: " . ($isPremium ? 'Yes' : 'No') . "\n", FILE_APPEND);
?>
