<?php
// endpoints/gallery.php

define('UPLOAD_DIR',  __DIR__ . '/../../uploads/gallery/');
define('UPLOAD_URL',  'https://alhindtrust.com/uploads/gallery/'); // ← Update to your domain

// ── Public gallery ───────────────────────────────────────────
function getPublicGallery(): void {
    $db   = getDB();
    $cat  = $_GET['category'] ?? null;
    $sql  = "SELECT gi.id, gi.title, gi.filepath, gi.alt_text, gi.is_featured, gi.sort_order, gi.taken_at,
                    gc.name AS category_name, gc.slug AS category_slug
             FROM gallery_images gi
             LEFT JOIN gallery_categories gc ON gc.id = gi.category_id
             WHERE gi.is_active = 1" . ($cat ? " AND gc.slug = ?" : "") . "
             ORDER BY gi.sort_order ASC";
    $stmt = $db->prepare($sql);
    $cat ? $stmt->execute([$cat]) : $stmt->execute();
    ok($stmt->fetchAll());
}

// ── Admin: all images ────────────────────────────────────────
function getAllGallery(): void {
    $db   = getDB();
    $stmt = $db->query("
        SELECT gi.*, gc.name AS category_name
        FROM gallery_images gi
        LEFT JOIN gallery_categories gc ON gc.id = gi.category_id
        ORDER BY gi.sort_order ASC
    ");
    ok($stmt->fetchAll());
}

// ── Upload image ─────────────────────────────────────────────
function uploadGallery(): void {
    // Supports both file upload (multipart) and base64 JSON
    $db = getDB();

    if (!empty($_FILES['image'])) {
        // ── Multipart file upload ────────────────────────────
        $file = $_FILES['image'];
        validateImageFile($file);

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('img_') . '.' . $ext;
        $dest     = UPLOAD_DIR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            error('File upload failed', 500);
        }

        $filepath = UPLOAD_URL . $filename;
        $caption  = sanitize($_POST['caption'] ?? '');
        $catId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    } else {
        // ── Base64 JSON upload (from admin panel localStorage migration) ──
        $b = body();
        if (empty($b['src'])) error('No image data provided');

        // Decode base64
        $src = $b['src'];
        if (preg_match('/^data:image\/(\w+);base64,/', $src, $type)) {
            $imageData = base64_decode(substr($src, strpos($src, ',') + 1));
            $ext       = strtolower($type[1]);
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) error('Invalid image type');

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $filename = uniqid('img_') . '.' . $ext;
            file_put_contents(UPLOAD_DIR . $filename, $imageData);
            $filepath = UPLOAD_URL . $filename;
        } else {
            // Plain URL (already hosted)
            $filepath = sanitize($src);
            $filename = basename($filepath);
        }

        $caption = sanitize($b['caption'] ?? '');
        $catId   = !empty($b['category_id']) ? (int)$b['category_id'] : null;
    }

    // Get next sort order
    $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0) FROM gallery_images")->fetchColumn();

    $stmt = $db->prepare("
        INSERT INTO gallery_images (category_id, title, filepath, filename, alt_text, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$catId, $caption, $filepath, $filename, $caption, $maxOrder + 1]);

    $id   = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM gallery_images WHERE id = ?");
    $stmt->execute([$id]);
    ok($stmt->fetch(), 'Image uploaded', 201);
}

// ── Update caption / category ────────────────────────────────
function updateGallery(string $id): void {
    $b  = body();
    $db = getDB();

    $fields = ['title','alt_text','category_id','is_featured','is_active','sort_order'];
    $set    = [];
    $vals   = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $set[]  = "$f = ?";
            $vals[] = in_array($f, ['category_id','is_featured','is_active','sort_order']) ? (int)$b[$f] : sanitize((string)$b[$f]);
        }
    }

    if (empty($set)) error('No fields to update');
    $vals[] = $id;

    $db->prepare("UPDATE gallery_images SET " . implode(', ', $set) . ", updated_at = NOW() WHERE id = ?")
       ->execute($vals);

    $stmt = $db->prepare("SELECT * FROM gallery_images WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) error('Image not found', 404);
    ok($row, 'Image updated');
}

// ── Delete ───────────────────────────────────────────────────
function deleteGallery(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE gallery_images SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Image not found', 404);
    ok(null, 'Image deleted');
}

// ── Reorder ──────────────────────────────────────────────────
function reorderGallery(): void {
    $b   = body();
    $ids = $b['order'] ?? [];
    if (!is_array($ids) || empty($ids)) error('order array required');

    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE gallery_images SET sort_order = ?, updated_at = NOW() WHERE id = ?");
        foreach ($ids as $i => $id) $stmt->execute([$i + 1, $id]);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error('Reorder failed', 500);
    }
    ok(null, 'Order saved');
}

// ── Validate uploaded file ───────────────────────────────────
function validateImageFile(array $file): void {
    if ($file['error'] !== UPLOAD_ERR_OK) error('Upload error code: ' . $file['error']);
    if ($file['size'] > 10 * 1024 * 1024) error('File too large (max 10MB)');
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) error('Invalid file type: ' . $mime);
}