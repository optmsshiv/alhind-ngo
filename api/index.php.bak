<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/middleware/response.php';

echo "✅ All middleware loaded<br>";

applyCors();
echo "✅ CORS applied<br>";

$method = $_SERVER['REQUEST_METHOD'];
$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

echo "✅ Method: $method<br>";
echo "✅ URI: $uri<br>";

$uri   = preg_replace('#^api/?#', '', $uri);
$parts = explode('/', $uri);

$resource = $parts[0] ?? '';
$id       = $parts[1] ?? null;

echo "✅ Resource: $resource<br>";
echo "✅ ID: $id<br>";

echo "<br><strong>Router is working!</strong>";