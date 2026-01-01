<?php
$data = json_encode(['email' => 'admin@teste.com', 'password' => 'password']);
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $data,
        'timeout' => 10,
    ],
];
$context = stream_context_create($opts);
$res = @file_get_contents('http://127.0.0.1:8000/api/v1/auth/login', false, $context);
if ($res === false) {
    echo "Request failed\n";
    if (isset($http_response_header)) {
        echo "Response headers:\n";
        print_r($http_response_header);
    }
    exit(1);
}
echo $res;
echo "\n\nResponse headers:\n";
print_r($http_response_header);
