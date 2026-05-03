<?php
// endpoints/gallery.php

define('UPLOAD_DIR',  __DIR__ . '/../../uploads/gallery/');
define('UPLOAD_URL',  'https://alhindtrust.com/uploads/gallery/');

// ── Helper: resolve category name → category_id (auto-create if missing) ──
function resolveCategoryId(PDO $db, ?string $name): ?int {
    if (empty(trim((string)$name))) return null;
    $name = trim($name);
    $stmt = $db->prepare("SELECT id FROM gallery_categories WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) return (int)$row['id'];
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $db->prepare("INSERT INTO gallery_categories (name, slug, is_active) VALUES (?, ?, 1)")
       ->execute([$name, $slug]);
    return (int)$db->lastInsertId();
}

// ── Public gallery ───────────────────────────────────────────
function getPublicGallery(): void {
    $db  = getDB();
    $cat = $_GET['category'] ?? null;
    $sql = "SELECT gi.id, gi.title, gi.title_hi, gi.filepath, gi.alt_text, gi.caption,
                   gi.is_featured, gi.sort_order, gi.taken_at, gi.created_at,
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
        SELECT gi.id, gi.category_id, gi.title, gi.title_hi, gi.filepath, gi.filename,
               gi.alt_text, gi.caption, gi.is_featured, gi.is_active, gi.sort_order,
               gi.taken_at, gi.created_at, gi.updated_at,
               gc.name AS category_name, gc.slug AS category_slug
        FROM gallery_images gi
        LEFT JOIN gallery_categories gc ON gc.id = gi.category_id
        ORDER BY gi.sort_order ASC, gi.created_at DESC
    ");
    ok($stmt->fetchAll());
}

// ── Upload image ─────────────────────────────────────────────
function uploadGallery(): void {
    $db = getDB();

    if (!empty($_FILES['image'])) {
        // ── Multipart file upload ────────────────────────────
        $file = $_FILES['image'];
        validateImageFile($file);
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('img_') . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) error('File upload failed', 500);
        $filepath  = UPLOAD_URL . $filename;
        $title     = sanitize($_POST['title']    ?? '');
        $titleHi   = sanitize($_POST['title_hi'] ?? '');
        $caption   = sanitize($_POST['caption']  ?? $title);
        $isActive  = isset($_POST['is_active'])  ? (int)$_POST['is_active']  : 1;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : null;
        $catId     = (!empty($_POST['category_id']) && is_numeric($_POST['category_id']))
                     ? (int)$_POST['category_id']
                     : resolveCategoryId($db, $_POST['category'] ?? null);
    } else {
        // ── Base64 JSON upload (from admin panel) ─────────────
        $b = body();
        if (empty($b['src'])) error('No image data provided');
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
            $filepath = sanitize($src);
            $filename = basename($filepath);
        }
        $title     = sanitize($b['title']    ?? '');
        $titleHi   = sanitize($b['title_hi'] ?? '');
        $caption   = sanitize($b['caption']  ?? $b['alt_text'] ?? $title);
        $isActive  = isset($b['is_active'])  ? (int)$b['is_active']  : 1;
        $sortOrder = isset($b['sort_order']) ? (int)$b['sort_order'] : null;
        $catId     = (!empty($b['category_id']) && is_numeric($b['category_id']))
                     ? (int)$b['category_id']
                     : resolveCategoryId($db, $b['category'] ?? null);
    }

    if ($sortOrder === null) {
        $sortOrder = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM gallery_images")->fetchColumn() + 1;
    }

    $db->prepare("
        INSERT INTO gallery_images
            (category_id, title, title_hi, filepath, filename, alt_text, caption, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$catId, $title, $titleHi, $filepath, $filename, $caption, $caption, $sortOrder, $isActive]);

    $id = $db->lastInsertId();
    $stmt = $db->prepare("
        SELECT gi.*, gc.name AS category_name, gc.slug AS category_slug
        FROM gallery_images gi
        LEFT JOIN gallery_categories gc ON gc.id = gi.category_id
        WHERE gi.id = ?
    ");
    $stmt->execute([$id]);
    ok($stmt->fetch(), 'Image uploaded', 201);
}

// ── Update image details ─────────────────────────────────────
function updateGallery(string $id): void {
    $b  = body();
    $db = getDB();

    // Resolve category from text name if sent as 'category'
    if (isset($b['category']) && !isset($b['category_id'])) {
        $b['category_id'] = resolveCategoryId($db, $b['category']);
    }

    $intFields = ['category_id','is_featured','is_active','sort_order'];
    $fields    = ['title','title_hi','alt_text','caption','category_id','is_featured','is_active','sort_order'];
    $set = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $set[]  = "$f = ?";
            $vals[] = in_array($f, $intFields)
                      ? (($b[$f] === null || $b[$f] === '') ? null : (int)$b[$f])
                      : sanitize((string)$b[$f]);
        }
    }

    if (empty($set)) error('No fields to update');
    $vals[] = $id;
    $db->prepare("UPDATE gallery_images SET " . implode(', ', $set) . ", updated_at = NOW() WHERE id = ?")
       ->execute($vals);

    $stmt = $db->prepare("
        SELECT gi.*, gc.name AS category_name, gc.slug AS category_slug
        FROM gallery_images gi
        LEFT JOIN gallery_categories gc ON gc.id = gi.category_id
        WHERE gi.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) error('Image not found', 404);
    ok($row, 'Image updated');
}

// ── Delete (soft) ────────────────────────────────────────────
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