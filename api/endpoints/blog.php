<?php
// endpoints/blog.php

define('BLOG_UPLOAD_DIR', __DIR__ . '/../../uploads/blog/');
define('BLOG_UPLOAD_URL', 'https://alhindtrust.com/uploads/blog/');

/* ── Public: list published posts ────────────────────────── */
function getPublicPosts(): void {
    $db  = getDB();
    $cat = $_GET['category'] ?? null;
    $sql = "SELECT id, title, slug, excerpt, cover_image, category, tags,
                   author, views, published_at, created_at
            FROM blog_posts
            WHERE is_published = 1"
         . ($cat ? " AND category = ?" : "")
         . " ORDER BY published_at DESC, created_at DESC";
    $stmt = $db->prepare($sql);
    $cat ? $stmt->execute([$cat]) : $stmt->execute();
    ok($stmt->fetchAll());
}

/* ── Public: single post by slug ─────────────────────────── */
function getPublicPost(string $slug): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, title, slug, excerpt, content, cover_image,
               category, tags, author, views, published_at, created_at
        FROM blog_posts WHERE slug = ? AND is_published = 1 LIMIT 1
    ");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if (!$post) error('Post not found', 404);

    // Increment views
    $db->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")
       ->execute([$post['id']]);

    ok($post);
}

/* ── Admin: all posts ────────────────────────────────────── */
function getAllPosts(): void {
    $db   = getDB();
    $stmt = $db->query("
        SELECT id, title, slug, excerpt, cover_image, category, tags,
               author, is_published, views, sort_order, published_at, created_at
        FROM blog_posts ORDER BY created_at DESC
    ");
    ok($stmt->fetchAll());
}

/* ── Admin: single post (for editing) ───────────────────── */
function getPost(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) error('Post not found', 404);
    ok($post);
}

/* ── Admin: create post ──────────────────────────────────── */
function createPost(): void {
    $b  = body();
    $db = getDB();

    $title   = sanitize($b['title']   ?? '');
    $content = $b['content'] ?? '';  // allow HTML
    if (empty($title)) error('Title is required');

    $slug = makeBlogSlug($title);
    // Ensure unique slug
    $check = $db->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ?");
    $check->execute([$slug]);
    if ((int)$check->fetchColumn() > 0) $slug .= '-' . time();

    $cover      = resolveBlogImage($b['cover_image'] ?? '');
    $isPub      = isset($b['is_published']) ? (int)$b['is_published'] : 0;
    $pubAt      = $isPub ? date('Y-m-d H:i:s') : null;

    $db->prepare("
        INSERT INTO blog_posts
            (title, slug, excerpt, content, cover_image, category, tags, author, is_published, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $title,
        $slug,
        sanitize($b['excerpt']  ?? ''),
        $content,
        $cover,
        sanitize($b['category'] ?? ''),
        sanitize($b['tags']     ?? ''),
        sanitize($b['author']   ?? 'AL Hind Trust'),
        $isPub,
        $pubAt,
    ]);

    $id   = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    ok($stmt->fetch(), 'Post created', 201);
}

/* ── Admin: update post ──────────────────────────────────── */
function updatePost(string $id): void {
    $b  = body();
    $db = getDB();

    $fields = ['title','slug','excerpt','content','cover_image','category','tags','author','is_published','sort_order'];
    $set    = [];
    $vals   = [];

    foreach ($fields as $f) {
        if (!array_key_exists($f, $b)) continue;
        if ($f === 'cover_image') {
            $set[]  = "$f = ?";
            $vals[] = resolveBlogImage($b[$f]);
        } elseif (in_array($f, ['is_published','sort_order'])) {
            $set[]  = "$f = ?";
            $vals[] = (int)$b[$f];
        } elseif ($f === 'content') {
            $set[]  = "$f = ?";
            $vals[] = $b[$f]; // allow HTML
        } else {
            $set[]  = "$f = ?";
            $vals[] = sanitize((string)$b[$f]);
        }
    }

    // Handle publish_at when publishing
    if (isset($b['is_published'])) {
        $set[]  = "published_at = ?";
        $vals[] = ((int)$b['is_published'] === 1) ? date('Y-m-d H:i:s') : null;
    }

    if (empty($set)) error('No fields to update');
    $vals[] = $id;

    $db->prepare("UPDATE blog_posts SET " . implode(', ', $set) . ", updated_at = NOW() WHERE id = ?")
       ->execute($vals);

    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) error('Post not found', 404);
    ok($post, 'Post updated');
}

/* ── Admin: delete post ──────────────────────────────────── */
function deletePost(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Post not found', 404);
    ok(null, 'Post deleted');
}

/* ── Resolve base64 cover image ──────────────────────────── */
function resolveBlogImage(?string $src): string {
    if (empty($src)) return '';
    if (!preg_match('/^data:image\/(\w+);base64,/', $src, $type)) return $src;
    $ext  = strtolower($type[1]);
    if ($ext === 'jpeg') $ext = 'jpg';
    if (!in_array($ext, ['jpg','png','webp','gif'])) return '';
    $data = base64_decode(substr($src, strpos($src, ',') + 1));
    if (!$data) return '';
    if (!is_dir(BLOG_UPLOAD_DIR)) mkdir(BLOG_UPLOAD_DIR, 0755, true);
    $filename = uniqid('blog_') . '.' . $ext;
    file_put_contents(BLOG_UPLOAD_DIR . $filename, $data);
    return BLOG_UPLOAD_URL . $filename;
}

/* ── Slug generator ──────────────────────────────────────── */
function makeBlogSlug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-') ?: 'post-' . time();
}
