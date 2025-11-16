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
$client_ip = getClientIP();

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
    
    // ПРОВЕРКА IP АДРЕСА - ДОБАВЛЕНО ЗДЕСЬ
    if (isset($device['registered_ip']) && $device['registered_ip'] !== $client_ip) {
        $status = "IP Address Mismatch";
        $found = true;
    } else {
        $expiry_date = strtotime($device['expiry_date']);
        $current_time = time();
        
        if ($expiry_date > $current_time) {
            $isPremium = true;
            $daysLeft = ceil(($expiry_date - $current_time) / (60 * 60 * 24));
            $status = "Active Premium";
            $found = true;
            
            // Обновляем последний доступ и IP
            $data[$udid]['last_access_ip'] = $client_ip;
            $data[$udid]['last_access'] = date('Y-m-d H:i:s');
            file_put_contents($database_file, json_encode($data, JSON_PRETTY_PRINT));
        } else {
            $status = "Subscription Expired";
            $found = true;
        }
    }
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'found' => $found,
    'premium' => $isPremium,
    'days_left' => $daysLeft,
    'status' => $status,
    'udid' => $udid,
    'client_ip' => $client_ip
]);

// Логируем запрос
file_put_contents('../logs.txt', date('Y-m-d H:i:s') . " - CHECK - UDID: $udid, IP: $client_ip, Premium: " . ($isPremium ? 'Yes' : 'No') . ", Status: $status\n", FILE_APPEND);
?>
