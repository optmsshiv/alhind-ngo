<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ============================================================
//  index.php — API Router
//  Place at: api.alhindtrust.com/index.php
//  All requests go through this file via .htaccess
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/middleware/response.php';

// ── Apply CORS headers ───────────────────────────────────────
applyCors();

// ── Parse request ────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Strip base path if API lives in a subfolder (e.g. /api/v1)
// Adjust 'api' below if your subdomain root is different
$uri = preg_replace('#^api/?#', '', $uri);
$parts = explode('/', $uri);

$resource = $parts[0] ?? '';   // e.g. "auth", "events", "gallery"
$id       = $parts[1] ?? null; // e.g. "123"
$action   = $parts[2] ?? null; // e.g. "reorder"

// ── Handle preflight ─────────────────────────────────────────
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Public routes (no auth needed) ──────────────────────────
if ($resource === 'auth' && $method === 'POST') {
    require_once __DIR__ . '/endpoints/auth.php';
    handleAuth();
    exit;
}

// Public GET routes — used by the main site
if ($method === 'GET') {
    if ($resource === 'events') {
        require_once __DIR__ . '/endpoints/events.php';
        getPublicEvents();
        exit;
    }
    if ($resource === 'gallery') {
        require_once __DIR__ . '/endpoints/gallery.php';
        getPublicGallery();
        exit;
    }
}

// Contact & Donate POST — public (called from main site forms)
if ($resource === 'contact' && $method === 'POST') {
    require_once __DIR__ . '/endpoints/contact.php';
    submitContact();
    exit;
}
if ($resource === 'donate' && $method === 'POST') {
    require_once __DIR__ . '/endpoints/donate.php';
    submitDonation();
    exit;
}
if ($resource === 'donate' && $method === 'PATCH' && $id) {
    // Razorpay webhook / payment confirmation
    require_once __DIR__ . '/endpoints/donate.php';
    confirmDonation($id);
    exit;
}

// ── Protected routes — require JWT ───────────────────────────
requireAuth();

switch ($resource) {

    // ── Events ──────────────────────────────────────────────
    case 'events':
        require_once __DIR__ . '/endpoints/events.php';
        if ($method === 'GET'  && !$id)      getAllEvents();
        elseif ($method === 'POST')          createEvent();
        elseif ($method === 'PUT'  && $id)   updateEvent($id);
        elseif ($method === 'DELETE' && $id) deleteEvent($id);
        elseif ($method === 'POST' && $action === 'reorder') reorderEvents();
        else notFound();
        break;

    // ── Gallery ─────────────────────────────────────────────
    case 'gallery':
        require_once __DIR__ . '/endpoints/gallery.php';
        if ($method === 'GET'  && !$id)      getAllGallery();
        elseif ($method === 'POST')          uploadGallery();
        elseif ($method === 'PUT'  && $id)   updateGallery($id);
        elseif ($method === 'DELETE' && $id) deleteGallery($id);
        elseif ($method === 'POST' && $action === 'reorder') reorderGallery();
        else notFound();
        break;

    // ── Donations ────────────────────────────────────────────
    case 'donations':
        require_once __DIR__ . '/endpoints/donate.php';
        if ($method === 'GET')               getAllDonations();
        elseif ($method === 'DELETE' && $id) deleteDonation($id);
        elseif ($method === 'DELETE' && !$id) clearDonations();
        else notFound();
        break;

    // ── Messages ─────────────────────────────────────────────
    case 'messages':
        require_once __DIR__ . '/endpoints/contact.php';
        if ($method === 'GET')               getAllMessages();
        elseif ($method === 'PATCH' && $id)  markMessageRead($id);
        elseif ($method === 'PATCH' && !$id) markAllRead();
        elseif ($method === 'DELETE' && $id) deleteMessage($id);
        elseif ($method === 'DELETE' && !$id) clearMessages();
        else notFound();
        break;

    // ── Dashboard stats ──────────────────────────────────────
    case 'dashboard':
        require_once __DIR__ . '/endpoints/dashboard.php';
        getDashboardStats();
        break;

    // ── Categories ───────────────────────────────────────────
    case 'categories':
        require_once __DIR__ . '/endpoints/categories.php';
        if ($method === 'GET')               getCategories();
        elseif ($method === 'POST')          createCategory();
        elseif ($method === 'PUT'  && $id)   updateCategory($id);
        elseif ($method === 'DELETE' && $id) deleteCategory($id);
        else notFound();
        break;

    default:
        notFound();
}