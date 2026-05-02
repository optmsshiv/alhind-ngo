<?php
// endpoints/categories.php

function getCategories(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM gallery_categories WHERE is_active = 1 ORDER BY sort_order ASC");
    ok($stmt->fetchAll());
}

function createCategory(): void {
    $b = body();
    if (empty($b['name'])) error('Name is required');

    $db   = getDB();
    $slug = makeSlug($b['name']);

    // Ensure slug is unique
    $existing = $db->prepare("SELECT COUNT(*) FROM gallery_categories WHERE slug = ?");
    $existing->execute([$slug]);
    if ($existing->fetchColumn() > 0) $slug .= '-' . time();

    $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0) FROM gallery_categories")->fetchColumn();

    $stmt = $db->prepare("INSERT INTO gallery_categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->execute([sanitize($b['name']), $slug, sanitize($b['description'] ?? ''), $maxOrder + 1]);
    ok(['id' => $db->lastInsertId(), 'slug' => $slug], 'Category created', 201);
}

function updateCategory(string $id): void {
    $b  = body();
    $db = getDB();

    $fields = ['name','description','sort_order','is_active'];
    $set = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $set[]  = "$f = ?";
            $vals[] = in_array($f, ['sort_order','is_active']) ? (int)$b[$f] : sanitize((string)$b[$f]);
        }
    }
    if (empty($set)) error('No fields to update');
    $vals[] = $id;
    $db->prepare("UPDATE gallery_categories SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals);
    ok(null, 'Category updated');
}

function deleteCategory(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE gallery_categories SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    ok(null, 'Category deleted');
}

function makeSlug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}