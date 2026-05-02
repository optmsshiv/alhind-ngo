<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';
echo "✅ db.php OK<br>";

require_once __DIR__ . '/middleware/response.php';
echo "✅ response.php OK<br>";

require_once __DIR__ . '/middleware/cors.php';
echo "✅ cors.php OK<br>";

require_once __DIR__ . '/middleware/auth.php';
echo "✅ auth.php OK<br>";

require_once __DIR__ . '/endpoints/events.php';
echo "✅ events.php OK<br>";

echo "<br><strong>All files loaded successfully!</strong>";