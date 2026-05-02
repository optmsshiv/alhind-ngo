/**
 * api.js — AL Hind Trust API Client
 * Used by both the main site and admin panel.
 * Include this before any page-specific JS.
 */

const API_BASE = 'https://api.alhindtrust.com'; // ← Update if different

// ── Token management ─────────────────────────────────────────
const Auth = {
    getToken: () => sessionStorage.getItem('alhind_token'),
    setToken: (t) => sessionStorage.setItem('alhind_token', t),
    removeToken: () => sessionStorage.removeItem('alhind_token'),
    isLoggedIn: () => !!sessionStorage.getItem('alhind_token'),
};

// ── Core fetch wrapper ────────────────────────────────────────
async function apiRequest(method, endpoint, body = null, isFormData = false) {
    const headers = {};
    if (!isFormData) headers['Content-Type'] = 'application/json';
    const token = Auth.getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const opts = { method, headers };
    if (body) opts.body = isFormData ? body : JSON.stringify(body);

    try {
        const res = await fetch(`${API_BASE}/${endpoint}`, opts);
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || `HTTP ${res.status}`);
        }
        return data;
    } catch (err) {
        console.error(`API [${method} /${endpoint}]:`, err.message);
        throw err;
    }
}

const api = {
    get: (ep) => apiRequest('GET', ep),
    post: (ep, body) => apiRequest('POST', ep, body),
    put: (ep, body) => apiRequest('PUT', ep, body),
    patch: (ep, body) => apiRequest('PATCH', ep, body),
    delete: (ep) => apiRequest('DELETE', ep),
    upload: (ep, fd) => apiRequest('POST', ep, fd, true),
};

// ── Auth ──────────────────────────────────────────────────────
const AuthAPI = {
    login: async (username, password) => {
        const res = await api.post('auth', { username, password });
        if (res.data?.token) Auth.setToken(res.data.token);
        return res.data;
    },
    logout: () => Auth.removeToken(),
};

// ── Events ───────────────────────────────────────────────────
const EventsAPI = {
    getPublic: () => api.get('events'),
    getAll: () => api.get('events'),
    create: (data) => api.post('events', data),
    update: (id, data) => api.put(`events/${id}`, data),
    delete: (id) => api.delete(`events/${id}`),
    reorder: (order) => api.post('events/reorder', { order }),
};

// ── Gallery ───────────────────────────────────────────────────
const GalleryAPI = {
    getPublic: () => api.get('gallery'),
    getAll: () => api.get('gallery'),
    upload: (formData) => api.upload('gallery', formData),
    uploadJSON: (data) => api.post('gallery', data),   // base64
    update: (id, data) => api.put(`gallery/${id}`, data),
    delete: (id) => api.delete(`gallery/${id}`),
    reorder: (order) => api.post('gallery/reorder', { order }),
};

// ── Donations ─────────────────────────────────────────────────
const DonationsAPI = {
    submit: (data) => api.post('donate', data),
    confirm: (id, pid) => api.patch(`donate/${id}`, { razorpay_payment_id: pid }),
    getAll: () => api.get('donations'),
    delete: (id) => api.delete(`donations/${id}`),
    clear: () => api.delete('donations'),
};

// ── Messages ──────────────────────────────────────────────────
const MessagesAPI = {
    submit: (data) => api.post('contact', data),
    getAll: () => api.get('messages'),
    markRead: (id) => api.patch(`messages/${id}`, { read: true }),
    markAllRead: () => api.patch('messages', {}),
    delete: (id) => api.delete(`messages/${id}`),
    clear: () => api.delete('messages'),
};

// ── Dashboard ─────────────────────────────────────────────────
const DashboardAPI = {
    getStats: () => api.get('dashboard'),
};

// ── Categories ────────────────────────────────────────────────
const CategoriesAPI = {
    getAll: () => api.get('categories'),
    create: (data) => api.post('categories', data),
    update: (id, data) => api.put(`categories/${id}`, data),
    delete: (id) => api.delete(`categories/${id}`),
};