<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// GitHub конфигурация
$GITHUB_USERNAME = "shitterprocode-stack";
$GITHUB_REPO = "premium-system";
$GITHUB_FILE_PATH = "database.json";
$GITHUB_TOKEN = "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";

function updateGitHubDatabase($database) {
    global $GITHUB_USERNAME, $GITHUB_REPO, $GITHUB_FILE_PATH, $GITHUB_TOKEN;
    
    $url = "https://api.github.com/repos/{$GITHUB_USERNAME}/{$GITHUB_REPO}/contents/{$GITHUB_FILE_PATH}";
    
    // Получаем текущий SHA
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token {$GITHUB_TOKEN}",
        "User-Agent: PHP-Script",
        "Accept: application/vnd.github.v3+json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $sha = null;
    if ($httpCode == 200) {
        $fileInfo = json_decode($response, true);
        $sha = $fileInfo['sha'];
    }
    curl_close($ch);
    
    // Подготавливаем данные для обновления
    $content = json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $contentBase64 = base64_encode($content);
    
    $data = [
        "message" => "Activate UDID - " . date('Y-m-d H:i:s'),
        "content" => $contentBase64,
        "sha" => $sha
    ];
    
    // Отправляем обновление
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token {$GITHUB_TOKEN}",
        "User-Agent: PHP-Script",
        "Content-Type: application/json",
        "Accept: application/vnd.github.v3+json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}

function loadDatabaseFromGitHub() {
    global $GITHUB_USERNAME, $GITHUB_REPO, $GITHUB_FILE_PATH;
    
    $url = "https://raw.githubusercontent.com/{$GITHUB_USERNAME}/{$GITHUB_REPO}/main/{$GITHUB_FILE_PATH}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Script');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        return json_decode($response, true);
    }
    
    return null;
}

// Основная логика
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $udid = isset($input['udid']) ? trim($input['udid']) : '';
    $device_id = isset($input['device_id']) ? trim($input['device_id']) : '';
    
    if (empty($udid) || empty($device_id)) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing UDID or Device ID'
        ]);
        exit;
    }
    
    // Загружаем базу данных
    $database = loadDatabaseFromGitHub();
    
    if (!$database) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to load database'
        ]);
        exit;
    }
    
    // Ищем UDID
    if (!isset($database[$udid])) {
        echo json_encode([
            'success' => false,
            'error' => 'UDID not found'
        ]);
        exit;
    }
    
    $keyData = $database[$udid];
    
    // Проверяем не активирован ли уже на другом устройстве
    if (!empty($keyData['device_id']) && $keyData['device_id'] != $device_id) {
        echo json_encode([
            'success' => false,
            'error' => 'UDID already activated on another device'
        ]);
        exit;
    }
    
    // Проверяем не истек ли срок
    if (!empty($keyData['expiry_date'])) {
        $expiry = DateTime::createFromFormat('Y-m-d H:i:s', $keyData['expiry_date']);
        if ($expiry && $expiry < new DateTime()) {
            echo json_encode([
                'success' => false,
                'error' => 'UDID expired'
            ]);
            exit;
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
    $database[$udid]['last_access_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Сохраняем в GitHub
    if (updateGitHubDatabase($database)) {
        echo json_encode([
            'success' => true,
            'message' => 'UDID activated successfully',
            'days' => $days,
            'expiry_date' => $expiryDate,
            'activated_at' => $currentTime
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update database'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST method allowed'
    ]);
}
?>
