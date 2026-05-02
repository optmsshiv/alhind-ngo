<?php
// endpoints/dashboard.php

function getDashboardStats(): void {
    $db = getDB();

    $today = date('Y-m-d');

    // Events
    $totalEvents    = $db->query("SELECT COUNT(*) FROM events WHERE is_active = 1")->fetchColumn();
    $upcomingEvents = $db->query("SELECT COUNT(*) FROM events WHERE is_active = 1 AND event_date >= '$today'")->fetchColumn();
    $pastEvents     = $db->query("SELECT COUNT(*) FROM events WHERE is_active = 1 AND event_date < '$today'")->fetchColumn();
    $locations      = $db->query("SELECT COUNT(DISTINCT location) FROM events WHERE is_active = 1 AND location != ''")->fetchColumn();

    // Gallery
    $totalImages    = $db->query("SELECT COUNT(*) FROM gallery_images WHERE is_active = 1")->fetchColumn();

    // Donations
    $totalDonations = $db->query("SELECT COUNT(*) FROM donations")->fetchColumn();
    $totalRaised    = $db->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status = 'paid'")->fetchColumn();

    // Messages
    $totalMessages  = $db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
    $unreadMessages = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

    // Recent events (last 5)
    $recentEvt = $db->query("SELECT id, title, event_date, location, image_path, category FROM events WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Recent donations (last 5)
    $recentDon = $db->query("SELECT id, donor_name, donor_email, amount, payment_status, created_at FROM donations ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Recent messages (last 5)
    $recentMsg = $db->query("SELECT id, sender_name, sender_email, interest_type, is_read, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Gallery preview (first 6)
    $galPrev = $db->query("SELECT id, filepath, title FROM gallery_images WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 6")->fetchAll();

    ok([
        'stats' => [
            'total_events'    => (int)$totalEvents,
            'upcoming_events' => (int)$upcomingEvents,
            'past_events'     => (int)$pastEvents,
            'locations'       => (int)$locations,
            'total_images'    => (int)$totalImages,
            'total_donations' => (int)$totalDonations,
            'total_raised'    => (float)$totalRaised,
            'total_messages'  => (int)$totalMessages,
            'unread_messages' => (int)$unreadMessages,
        ],
        'recent_events'    => $recentEvt,
        'recent_donations' => $recentDon,
        'recent_messages'  => $recentMsg,
        'gallery_preview'  => $galPrev,
    ]);
}