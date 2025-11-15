<?php
header('Content-Type: application/json');

$database_file = 'database.json';
$data = [];

if (file_exists($database_file)) {
    $json_content = file_get_contents($database_file);
    $data = json_decode($json_content, true) ?? [];
}

$current_time = time();
$cleaned_count = 0;

foreach ($data as $key => $device) {
    $expiry_date = strtotime($device['expiry_date']);
    if ($expiry_date < $current_time) {
        unset($data[$key]);
        $cleaned_count++;
        error_log("🗑️ Removed expired UDID: " . $key);
    }
}

// Сохраняем очищенную базу
file_put_contents($database_file, json_encode($data, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'cleaned_count' => $cleaned_count,
    'remaining_udids' => count($data),
    'message' => 'Cleanup completed'
]);
?>
