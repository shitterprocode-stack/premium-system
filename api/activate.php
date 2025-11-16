<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запроса
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Функция для получения реального IP пользователя
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$udid = $_GET['udid'] ?? '';
$days = intval($_GET['days'] ?? 0);
$client_ip = getClientIP();

if (empty($udid) || $days <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters: UDID and days required']);
    exit;
}

// Валидация дней
$allowed_days = [1, 7, 30, 360];
if (!in_array($days, $allowed_days)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid days value. Allowed: 1, 7, 30, 360']);
    exit;
}

$database_file = '../database.json';
$data = [];

if (file_exists($database_file)) {
    $json_content = file_get_contents($database_file);
    $data = json_decode($json_content, true) ?? [];
}

// Функция для очистки просроченных ключей
function cleanupExpiredKeys(&$data) {
    $current_time = time();
    $keys_to_remove = [];
    
    foreach ($data as $udid => $record) {
        if (isset($record['expiry_date'])) {
            $expiry_timestamp = strtotime($record['expiry_date']);
            if ($expiry_timestamp <= $current_time) {
                $keys_to_remove[] = $udid;
            }
        }
    }
    
    foreach ($keys_to_remove as $udid_to_remove) {
        unset($data[$udid_to_remove]);
    }
    
    return count($keys_to_remove);
}

// Очищаем просроченные ключи перед добавлением нового
$cleaned_count = cleanupExpiredKeys($data);

// Проверяем, существует ли уже ключ с таким UDID
if (isset($data[$udid])) {
    // Если ключ существует, проверяем IP
    $existing_record = $data[$udid];
    if ($existing_record['registered_ip'] !== $client_ip) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'This UDID is already registered with different IP address',
            'registered_ip' => $existing_record['registered_ip'],
            'your_ip' => $client_ip
        ]);
        exit;
    }
}

// Устанавливаем дату окончания
$expiry_date = date('Y-m-d H:i:s', strtotime("+$days days"));

$data[$udid] = [
    'udid' => $udid,
    'expiry_date' => $expiry_date,
    'activated_at' => date('Y-m-d H:i:s'),
    'days' => $days,
    'plan_type' => $days . '_days',
    'registered_ip' => $client_ip,
    'last_access_ip' => $client_ip,
    'last_access' => date('Y-m-d H:i:s')
];

// Сохраняем обновленную базу
if (file_put_contents($database_file, json_encode($data, JSON_PRETTY_PRINT))) {
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Premium activated successfully!',
        'expiry_date' => $expiry_date,
        'days' => $days,
        'registered_ip' => $client_ip,
        'cleaned_expired' => $cleaned_count
    ]);
    
    // Логируем активацию
    file_put_contents('../logs.txt', date('Y-m-d H:i:s') . " - ACTIVATE - UDID: $udid, Days: $days, IP: $client_ip, Expiry: $expiry_date, Cleaned: $cleaned_count expired keys\n", FILE_APPEND);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save database']);
}
?>
