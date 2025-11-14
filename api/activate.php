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
$days = intval($_GET['days'] ?? 0);

if (empty($udid) || $days <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters: UDID and days required']);
    exit;
}

// Валидация дней
$allowed_days = [7, 30, 360];
if (!in_array($days, $allowed_days)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid days value. Allowed: 7, 30, 360']);
    exit;
}

$database_file = '../database.json';
$data = [];

if (file_exists($database_file)) {
    $json_content = file_get_contents($database_file);
    $data = json_decode($json_content, true) ?? [];
}

// Устанавливаем дату окончания
$expiry_date = date('Y-m-d H:i:s', strtotime("+$days days"));

$data[$udid] = [
    'udid' => $udid,
    'expiry_date' => $expiry_date,
    'activated_at' => date('Y-m-d H:i:s'),
    'days' => $days,
    'plan_type' => $days . '_days'
];

// Сохраняем обновленную базу
if (file_put_contents($database_file, json_encode($data, JSON_PRETTY_PRINT))) {
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Premium activated successfully!',
        'expiry_date' => $expiry_date,
        'days' => $days
    ]);
    
    // Логируем активацию
    file_put_contents('../logs.txt', date('Y-m-d H:i:s') . " - ACTIVATE - UDID: $udid, Days: $days, Expiry: $expiry_date\n", FILE_APPEND);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save database']);
}
?>
