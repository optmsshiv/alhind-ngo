<?php
require_once __DIR__ . '/config/db.php';

if (function_exists('getDB')) {
    echo "getDB EXISTS ✅";
    $db = getDB();
    echo " — DB connected ✅";
} else {
    echo "getDB NOT FOUND ❌ — db.php is not defining it";
    echo "<br>Contents of db.php:<br><pre>";
    echo htmlspecialchars(file_get_contents(__DIR__ . '/config/db.php'));
    echo "</pre>";
}