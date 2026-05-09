<?php
/**
 * AL Hind Trust — Path & Folder Structure Detector
 * Upload to your server, open in browser, then DELETE it.
 */

function row(string $label, string $value, string $status = 'info'): string {
    $colors = ['ok' => '#dcfce7', 'fail' => '#fee2e2', 'info' => '#f8fafc', 'warn' => '#fef9c3'];
    $icons  = ['ok' => '✅', 'fail' => '❌', 'info' => 'ℹ️', 'warn' => '⚠️'];
    return "<tr style='background:{$colors[$status]}'>
              <td style='padding:8px 14px;font-weight:600;color:#334155;width:260px'>{$label}</td>
              <td style='padding:8px 14px;font-family:monospace;font-size:13px'>{$icons[$status]} " . htmlspecialchars($value) . "</td>
            </tr>";
}

function sectionStart(string $title): string {
    return "<h2 style='color:#0f766e;margin-top:32px'>{$title}</h2>
            <table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden'>";
}

?><!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Path Detector — AL Hind Trust</title>
  <style>
    body { font-family: Segoe UI, sans-serif; max-width: 900px; margin: 32px auto; padding: 0 16px; color: #1e293b; }
    h1   { color: #0f766e; border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
    .warn-box { background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;margin:16px 0;color:#92400e; }
    code { background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:13px; }
  </style>
</head>
<body>
<h1>🔍 Path & Folder Detector — AL Hind Trust</h1>
<div class="warn-box">⚠️ <strong>Delete this file after use!</strong></div>

<?php

// ══════════════════════════════════════════════════════
// 1. KEY PATHS
// ══════════════════════════════════════════════════════
echo sectionStart('1. Key Paths');
echo row('This file (__FILE__)',      __FILE__);
echo row('This folder (__DIR__)',     __DIR__);
echo row('DOCUMENT_ROOT',            $_SERVER['DOCUMENT_ROOT'] ?? 'not set');
echo row('SERVER_NAME',              $_SERVER['SERVER_NAME']   ?? 'not set');
echo row('SCRIPT_FILENAME',          $_SERVER['SCRIPT_FILENAME'] ?? 'not set');
echo "</table>";

// ══════════════════════════════════════════════════════
// 2. AUTOLOAD SEARCH
// ══════════════════════════════════════════════════════
echo sectionStart('2. vendor/autoload.php — Where is it?');

$candidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php',
];

$found = false;
foreach ($candidates as $path) {
    $real   = realpath($path) ?: $path;
    $exists = file_exists($path);
    if ($exists) {
        echo row($path, 'EXISTS ← USE THIS PATH', 'ok');
        $found = true;
    } else {
        echo row($path, 'not found', 'fail');
    }
}
if (!$found) {
    echo row('vendor/autoload.php', 'NOT FOUND anywhere! Composer not installed.', 'fail');
}
echo "</table>";

// ══════════════════════════════════════════════════════
// 3. CONTACT.PHP LOCATION
// ══════════════════════════════════════════════════════
echo sectionStart('3. contact.php — Where is it?');
$contactCandidates = [
    __DIR__ . '/contact.php',
    __DIR__ . '/endpoints/contact.php',
    __DIR__ . '/../endpoints/contact.php',
    __DIR__ . '/api/endpoints/contact.php',
    $_SERVER['DOCUMENT_ROOT'] . '/api/endpoints/contact.php',
    $_SERVER['DOCUMENT_ROOT'] . '/endpoints/contact.php',
];
foreach ($contactCandidates as $path) {
    $exists = file_exists($path);
    echo row($path, $exists ? 'EXISTS' : 'not found', $exists ? 'ok' : 'fail');
}
echo "</table>";

// ══════════════════════════════════════════════════════
// 4. FULL FOLDER TREE (2 levels from DOCUMENT_ROOT)
// ══════════════════════════════════════════════════════
echo "<h2 style='color:#0f766e;margin-top:32px'>4. Folder Tree (from DOCUMENT_ROOT)</h2>";
echo "<pre style='background:#0f172a;color:#e2e8f0;padding:20px;border-radius:10px;font-size:13px;overflow-x:auto;line-height:1.7'>";

function printTree(string $dir, string $prefix = '', int $depth = 0, int $maxDepth = 3): void {
    if ($depth > $maxDepth) return;
    $skip = ['node_modules', '.git', '.cache', 'cache', 'logs', 'tmp'];

    try {
        $items = array_diff(scandir($dir), ['.', '..']);
    } catch (Throwable $e) {
        echo $prefix . "  [permission denied]\n";
        return;
    }

    $items = array_values($items);
    $count = count($items);

    foreach ($items as $i => $item) {
        if (in_array($item, $skip)) continue;

        $path     = $dir . '/' . $item;
        $isLast   = ($i === $count - 1);
        $connector = $isLast ? '└── ' : '├── ';
        $childPfx  = $isLast ? '    ' : '│   ';
        $isDir    = is_dir($path);

        // Highlight important files
        $highlight = '';
        if (in_array($item, ['autoload.php', 'contact.php', 'event-volunteers.php', 'composer.json', 'composer.lock']))
            $highlight = ' ◄◄◄';

        echo $prefix . $connector . ($isDir ? '📁 ' : '📄 ') . $item . $highlight . "\n";

        if ($isDir) {
            printTree($path, $prefix . $childPfx, $depth + 1, $maxDepth);
        }
    }
}

$root = $_SERVER['DOCUMENT_ROOT'];
echo "📁 " . $root . "\n";
printTree($root);
echo "</pre>";

// ══════════════════════════════════════════════════════
// 5. CORRECT PATH TO USE
// ══════════════════════════════════════════════════════
echo "<h2 style='color:#0f766e;margin-top:32px'>5. Correct line to use in contact.php</h2>";
echo "<div style='background:#0f172a;color:#e2e8f0;padding:16px 20px;border-radius:10px;font-family:monospace;font-size:14px'>";

foreach ($candidates as $path) {
    if (file_exists($path)) {
        $real = realpath($path);
        echo "<span style='color:#86efac'>// ✅ Use this line in cntSendMail():</span><br>";
        echo "<span style='color:#93c5fd'>\$autoload</span> = <span style='color:#fde68a'>'" . addslashes($real) . "'</span>;";
        break;
    }
}

echo "</div>";

?>
</body>
</html>
