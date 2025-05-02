<?php
require 'config.php';

function log_request($status, $api_key = 'None') {
    $log_line = sprintf("[%s] - IP: %s - API Key: %s - Path: %s - Status: %d\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $api_key,
        $_GET['request_path'] ?? 'N/A',
        $status
    );
    file_put_contents('logs/gateway.log', $log_line, FILE_APPEND);
}

function check_rate_limit($key) {
    $file = "ratelimit_data/$key.json";
    $time = time();
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($time - $data['timestamp'] > 60) {
            $data = ['timestamp' => $time, 'count' => 1];
        } elseif ($data['count'] >= RATE_LIMIT) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            log_request(429, $key);
            exit;
        } else {
            $data['count']++;
        }
    } else {
        $data = ['timestamp' => $time, 'count' => 1];
    }
    file_put_contents($file, json_encode($data));
}

header('Content-Type: application/json');

// API Key check
$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!isset($valid_api_keys[$api_key])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API Key']);
    log_request(401, $api_key ?: 'None');
    exit;
}

// Rate limit check
check_rate_limit($api_key);

// Routing
$request = $_GET['request_path'] ?? '';
if ($request === 'users') {
    include 'services/service_users.php';
    log_request(200, $api_key);
} elseif ($request === 'products') {
    include 'services/service_products.php';
    log_request(200, $api_key);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    log_request(404, $api_key);
}
