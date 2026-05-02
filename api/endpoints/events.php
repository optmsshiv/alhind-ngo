<?php
// endpoints/events.php
// Compatible with u699609112_alhind existing events table structure

function getPublicEvents(): void {
    $db  = getDB();
    $cat = $_GET['category'] ?? null;
    $sql = "SELECT id, title, event_date, location,
                   COALESCE(image_path, image) AS image,
                   COALESCE(description, short_desc) AS description,
                   map_query, COALESCE(join_link, register_link) AS join_link,
                   category, status, slug
            FROM events WHERE is_active = 1"
         . ($cat ? " AND category = ?" : "")
         . " ORDER BY sort_order ASC, event_date DESC";
    $stmt = $db->prepare($sql);
    $cat ? $stmt->execute([$cat]) : $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r = formatEvent($r);
    ok($rows);
}

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

function createEvent(): void {
    $b = body();
    if (empty($b['title']) || empty($b['event_date'])) error('Title and date are required');

    $db      = getDB();
    $slug    = makeSlug($b['title'] . '-' . date('Y', strtotime($b['event_date'])));
    $check   = $db->prepare("SELECT COUNT(*) FROM events WHERE slug = ?");
    $check->execute([$slug]);
    if ((int)$check->fetchColumn() > 0) $slug .= '-' . time();

    $maxOrder = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM events")->fetchColumn();
    $imgPath  = sanitize($b['image_path'] ?? $b['image'] ?? '');
    $desc     = sanitize($b['description'] ?? '');
    $link     = sanitize($b['join_link'] ?? '');
    $status   = ($b['event_date'] >= date('Y-m-d')) ? 'upcoming' : 'completed';

    $stmt = $db->prepare("
        INSERT INTO events (slug, title, event_date, location, image, image_path,
             short_desc, description, map_query, register_link, join_link,
             category, status, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $slug, sanitize($b['title']), $b['event_date'],
        sanitize($b['location'] ?? ''), $imgPath, $imgPath,
        $desc, $desc, sanitize($b['map_query'] ?? ''),
        $link, $link, sanitize($b['category'] ?? ''),
        $status, $maxOrder + 1,
    ]);

    $id = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    ok(formatEvent($stmt->fetch()), 'Event created', 201);
}

function updateEvent(string $id): void {
    $b = body(); $db = getDB();
    $set = []; $vals = [];
    $fieldMap = [
        'title'       => ['title'],
        'event_date'  => ['event_date'],
        'location'    => ['location'],
        'description' => ['description', 'short_desc'],
        'image_path'  => ['image_path', 'image'],
        'map_query'   => ['map_query'],
        'join_link'   => ['join_link', 'register_link'],
        'category'    => ['category'],
        'sort_order'  => ['sort_order'],
        'is_active'   => ['is_active'],
    ];
    foreach ($fieldMap as $input => $cols) {
        if (!array_key_exists($input, $b)) continue;
        foreach ($cols as $col) {
            $set[]  = "`$col` = ?";
            $vals[] = in_array($col, ['sort_order','is_active','event_date']) ? $b[$input] : sanitize((string)$b[$input]);
        }
    }
    if (isset($b['event_date'])) { $set[] = "`status` = ?"; $vals[] = ($b['event_date'] >= date('Y-m-d')) ? 'upcoming' : 'completed'; }
    if (empty($set)) error('No fields to update');
    $vals[] = $id;
    $db->prepare("UPDATE events SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals);
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) error('Event not found', 404);
    ok(formatEvent($row), 'Event updated');
}

function deleteEvent(string $id): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE events SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Event not found', 404);
    ok(null, 'Event deleted');
}

function reorderEvents(): void {
    $b = body(); $ids = $b['order'] ?? [];
    if (!is_array($ids) || empty($ids)) error('order array required');
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE events SET sort_order = ? WHERE id = ?");
        foreach ($ids as $i => $eid) $stmt->execute([$i + 1, $eid]);
        $db->commit();
    } catch (Exception $e) { $db->rollBack(); error('Reorder failed', 500); }
    ok(null, 'Order saved');
}

function formatEvent(array $r): array {
    return [
        'id'          => (int)$r['id'],
        'title'       => $r['title']       ?? '',
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