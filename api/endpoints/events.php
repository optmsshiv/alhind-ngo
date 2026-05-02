<?php
// endpoints/events.php
// Works with u699609112_alhind events table (original + migrated columns)

// ── Public: active events for main website ───────────────────
function getPublicEvents(): void {
    $db  = getDB();
    $cat = $_GET['category'] ?? null;
    $sql = "SELECT id, title, event_date, location,
                   COALESCE(image_path, image) AS image,
                   COALESCE(description, short_desc) AS description,
                   map_query, COALESCE(join_link, register_link) AS join_link,
                   category, status, slug
            FROM events
            WHERE is_active = 1"
         . ($cat ? " AND category = ?" : "")
         . " ORDER BY sort_order ASC, event_date DESC";
    $stmt = $db->prepare($sql);
    $cat ? $stmt->execute([$cat]) : $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r = formatEvent($r);
    ok($rows);
}

// ── Admin: all events ────────────────────────────────────────
function getAllEvents(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT id, title, event_date, location,
                COALESCE(image_path, image) AS image,
                COALESCE(description, short_desc) AS description,
                map_query, COALESCE(join_link, register_link) AS join_link,
                category, status, slug, sort_order, is_active, created_at
         FROM events ORDER BY sort_order ASC, event_date DESC"
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r = formatEvent($r);
    ok($rows);
}

// ── Create ───────────────────────────────────────────────────
function createEvent(): void {
    $b = body();
    if (empty($b['title']) || empty($b['event_date'])) {
        error('Title and date are required');
    }

    $slug = makeSlug($b['title'] . '-' . date('Y', strtotime($b['event_date'])));
    $db   = getDB();

    // Ensure slug unique
    $check = $db->prepare("SELECT COUNT(*) FROM events WHERE slug = ?");
    $check->execute([$slug]);
    if ($check->fetchColumn() > 0) $slug .= '-' . time();

    // Get next sort order
    $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0) FROM events")->fetchColumn();

    $stmt = $db->prepare("
        INSERT INTO events
            (slug, title, event_date, location, image, image_path,
             short_desc, description, map_query, register_link, join_link,
             category, status, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $imgPath = sanitize($b['image_path'] ?? $b['image'] ?? '');
    $desc    = sanitize($b['description'] ?? '');
    $stmt->execute([
        $slug,
        sanitize($b['title']),
        $b['event_date'],
        sanitize($b['location']    ?? ''),
        $imgPath,
        $imgPath,
        $desc,
        $desc,
        sanitize($b['map_query']   ?? ''),
        sanitize($b['join_link']   ?? ''),
        sanitize($b['join_link']   ?? ''),
        sanitize($b['category']    ?? ''),
        $b['event_date'] >= date('Y-m-d') ? 'upcoming' : 'completed',
        $maxOrder + 1,
    ]);

    $id   = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    ok(formatEvent($stmt->fetch()), 'Event created', 201);
}

// ── Update ───────────────────────────────────────────────────
function updateEvent(string $id): void {
    $b  = body();
    $db = getDB();

    $map = [
        'title'       => 'title',
        'event_date'  => 'event_date',
        'location'    => 'location',
        'description' => 'description',
        'image_path'  => 'image_path',
        'map_query'   => 'map_query',
        'join_link'   => 'join_link',
        'category'    => 'category',
        'sort_order'  => 'sort_order',
        'is_active'   => 'is_active',
    ];

    $set = []; $vals = [];
    foreach ($map as $input => $col) {
        if (!array_key_exists($input, $b)) continue;
        $set[]  = "`$col` = ?";
        $vals[] = in_array($col, ['sort_order','is_active']) ? (int)$b[$input] : sanitize((string)$b[$input]);

        // Keep legacy columns in sync
        if ($col === 'description') { $set[] = "`short_desc` = ?"; $vals[] = sanitize((string)$b[$input]); }
        if ($col === 'image_path')  { $set[] = "`image` = ?";       $vals[] = sanitize((string)$b[$input]); }
        if ($col === 'join_link')   { $set[] = "`register_link` = ?"; $vals[] = sanitize((string)$b[$input]); }
    }

    // Auto-update status based on date
    if (isset($b['event_date'])) {
        $set[]  = "`status` = ?";
        $vals[] = $b['event_date'] >= date('Y-m-d') ? 'upcoming' : 'completed';
    }

    if (empty($set)) error('No fields to update');
    $vals[] = $id;

    $db->prepare("UPDATE events SET " . implode(', ', $set) . ", updated_at = NOW() WHERE id = ?")
       ->execute($vals);

    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row) error('Event not found', 404);
    ok(formatEvent($row), 'Event updated');
}

// ── Delete (soft) ────────────────────────────────────────────
function deleteEvent(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE events SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Event not found', 404);
    ok(null, 'Event deleted');
}

// ── Reorder ──────────────────────────────────────────────────
function reorderEvents(): void {
    $b   = body();
    $ids = $b['order'] ?? [];
    if (!is_array($ids) || empty($ids)) error('order array required');
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE events SET sort_order = ? WHERE id = ?");
        foreach ($ids as $i => $id) $stmt->execute([$i + 1, $id]);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error('Reorder failed', 500);
    }
    ok(null, 'Order saved');
}

// ── Format ────────────────────────────────────────────────────
function formatEvent(array $r): array {
    return [
        'id'          => (int)$r['id'],
        'title'       => $r['title'] ?? '',
        'date'        => isset($r['event_date']) ? substr($r['event_date'], 0, 10) : '',
        'location'    => $r['location']    ?? '',
        'description' => $r['description'] ?? $r['short_desc'] ?? '',
        'image'       => $r['image_path']  ?? $r['image'] ?? '',
        'mapQuery'    => $r['map_query']   ?? '',
        'joinLink'    => $r['join_link']   ?? $r['register_link'] ?? '',
        'category'    => $r['category']    ?? '',
        'status'      => $r['status']      ?? 'upcoming',
        'slug'        => $r['slug']        ?? '',
        'isActive'    => (bool)($r['is_active'] ?? 1),
        'sortOrder'   => (int)($r['sort_order'] ?? 0),
        'createdAt'   => $r['created_at']  ?? '',
    ];
}

function makeSlug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}