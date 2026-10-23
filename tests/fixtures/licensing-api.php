<?php

// Router for PHP's built-in server: answers with the status code the path
// ends in, e.g. `/status/401`, and a body naming a different failure, which
// the client must not read.
$status = (int)basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

http_response_code($status);
header('Content-Type: application/json');

echo json_encode($status >= 200 && $status < 300
    ? ['packageName' => 'test/package', 'licenseCompatibility' => '^1.0.0']
    : ['message' => 'Unauthorized']);
