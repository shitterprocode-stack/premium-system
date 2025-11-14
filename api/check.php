<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$udid = $_GET['udid'] ?? '';

// База данных в JSON файле (для GitHub Pages)
$database_file = 'database.json';
$data = [];

// Читаем базу данных
if (file_exists($database_file)) {
    $data = json_decode(file_get_contents($database_file), true);
}

// Ищем UDID в базе
$found = false;
$isPremium = false;
$daysLeft = 0;
$status = "Not Found";

if (isset($data[$udid])) {
    $device = $data[$udid];
    $expiry_date = strtotime($device['expiry_date']);
    $current_time = time();
    
    if ($expiry_date > $current_time) {
        $isPremium = true;
        $daysLeft = ceil(($expiry_date - $current_time) / (60 * 60 * 24));
        $status = "Active Premium";
    } else {
        $status = "Subscription Expired";
    }
    $found = true;
}

echo json_encode([
    'success' => true,
    'found' => $found,
    'premium' => $isPremium,
    'days_left' => $daysLeft,
    'status' => $status
]);
?>