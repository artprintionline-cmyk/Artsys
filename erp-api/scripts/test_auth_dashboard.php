<?php
$base = 'http://127.0.0.1:8000/api/v1';

function postJson($url, $data)
{
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'timeout' => 10,
        ],
    ];
    $context = stream_context_create($opts);
    return @file_get_contents($url, false, $context);
}

function getWithAuth($url, $token)
{
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer $token\r\n",
            'timeout' => 10,
        ],
    ];
    $context = stream_context_create($opts);
    return @file_get_contents($url, false, $context);
}

echo "Logging in...\n";
$login = postJson($base . '/auth/login', ['email' => 'admin@teste.com', 'password' => 'password']);
if (!$login) {
    echo "Login request failed\n";
    exit(1);
}

$data = json_decode($login, true);
if (!isset($data['token'])) {
    echo "Login did not return token. Response:\n";
    echo $login . "\n";
    exit(1);
}

$token = $data['token'];
echo "Token received (truncated): " . substr($token, 0, 20) . "...\n";

echo "Requesting dashboard summary...\n";
$summary = getWithAuth($base . '/dashboard/summary', $token);
if (!$summary) {
    echo "Summary request failed\n";
    exit(1);
}

echo "Summary response:\n";
echo $summary . "\n";
