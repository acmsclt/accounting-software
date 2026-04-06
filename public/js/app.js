// public/js/app.js — Global SaaS UI JavaScript

'use strict';

// ── Theme ───────────────────────────────────────────────────────────────────
const themeToggle = document.getElementById('themeToggle');
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next    = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        themeToggle.classList.toggle('dark', next === 'dark');
        themeToggle.querySelector('.theme-toggle-knob').textContent = next === 'dark' ? '🌙' : '☀️';
        document.cookie = `theme=${next};path=/;max-age=31536000`;
    });
}

// ── Sidebar Collapse ─────────────────────────────────────────────────────────
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const icon    = document.getElementById('toggleIcon');
    sidebar.classList.toggle('collapsed');
    const collapsed = sidebar.classList.contains('collapsed');
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    if (icon) icon.textContent = collapsed ? '›' : '‹';
    document.cookie = `sidebar_collapsed=${collapsed ? '1' : '0'};path=/;max-age=31536000`;
}

function toggleMobileSidebar() {
    document.getElementById('sidebar')?.classList.toggle('mobile-open');
}

// ── Dropdowns ─────────────────────────────────────────────────────────────────
function setupDropdown(triggerId, menuId) {
    const btn  = document.getElementById(triggerId);
    const menu = document.getElementById(menuId);
    if (!btn || !menu) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('open');
        // Close other dropdowns
        document.querySelectorAll('.dropdown-menu.open').forEach(m => {
            if (m !== menu) m.classList.remove('open');
        });
    });
}
setupDropdown('notifBtn',   'notifMenu');
setupDropdown('profileBtn', 'profileMenu');

document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
});

// ── Branch Switcher ───────────────────────────────────────────────────────────
function toggleBranchDropdown() {
    const dd = document.getElementById('branchDropdown');
    if (dd) dd.style.display = dd.style.display === 'none' ? '' : 'none';
}

function switchBranch(branchId, branchName) {
    const fd = new FormData();
    fd.append('branch_id', branchId);
    fd.append('_token', document.querySelector('#csrfToken')?.value || '');

    fetch('/branches/switch', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(`Switched to ${branchName}`, 'success');
                setTimeout(() => location.reload(), 600);
            }
        })
        .catch(() => showToast('Could not switch branch.', 'error'));
}

// ── Toasts ───────────────────────────────────────────────────────────────────
function showToast(message, type = 'info', title = '') {
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const tc    = document.getElementById('toastContainer') || (() => {
        const d = Object.assign(document.createElement('div'), { className: 'toast-container' });
        document.body.appendChild(d);
        return d;
    })();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${icons[type] || 'ℹ️'}</div>
        <div class="toast-content">
            ${title ? `<div class="toast-title">${title}</div>` : ''}
            <div class="toast-msg">${message}</div>
        </div>
        <span class="toast-close" onclick="this.closest('.toast').remove()">×</span>
    `;
    tc.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 400);
    }, 4500);
}
window.showToast = showToast; // Expose globally

// ── AJAX Form Helper ──────────────────────────────────────────────────────────
function ajaxPost(url, data, csrfToken) {
    const fd = new FormData();
    fd.append('_token', csrfToken || document.querySelector('[name=_token]')?.value || '');
    if (data instanceof FormData) {
        for (const [k, v] of data.entries()) fd.append(k, v);
    } else {
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    }
    return fetch(url, { method: 'POST', body: fd }).then(r => r.json());
}
window.ajaxPost = ajaxPost;

// ── Modals ────────────────────────────────────────────────────────────────────
function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}
window.openModal  = openModal;
window.closeModal = closeModal;

// Close on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// ── Data Tables: Search ───────────────────────────────────────────────────────
document.querySelectorAll('[data-search-table]').forEach(input => {
    const tableId = input.dataset.searchTable;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        document.querySelectorAll(`#${tableId} tbody tr`).forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});

// ── Confirm Delete ────────────────────────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
        if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});

// ── Number formatting ─────────────────────────────────────────────────────────
function formatCurrency(amount, currency = 'USD', decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        style:                 'currency',
        currency:              currency,
        minimumFractionDigits: decimals,
    }).format(amount);
}
window.formatCurrency = formatCurrency;

// ── Auto-format amounts on blur ───────────────────────────────────────────────
document.querySelectorAll('input[data-type=currency]').forEach(input => {
    input.addEventListener('blur', function () {
        const val = parseFloat(this.value.replace(/[^0-9.]/g, ''));
        if (!isNaN(val)) this.value = val.toFixed(2);
    });
});

// ── Animate numbers (counter) ─────────────────────────────────────────────────
function animateCount(el, target, duration = 800, prefix = '', suffix = '') {
    let start = 0;
    const startTime  = performance.now();
    const update = (now) => {
        const elapsed  = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const ease     = 1 - Math.pow(1 - progress, 3);
        el.textContent = prefix + Math.round(start + (target - start) * ease).toLocaleString() + suffix;
        if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
}

// Animate stat cards on load
document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseFloat(el.dataset.count);
    if (!isNaN(target)) animateCount(el, target, 1000);
});

// ── Responsive: mobile topbar ─────────────────────────────────────────────────
function checkMobile() {
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn) {
        mobileBtn.style.display = window.innerWidth <= 768 ? 'block' : 'none';
    }
}
checkMobile();
window.addEventListener('resize', checkMobile);

// ── Close mobile sidebar on nav item click ────────────────────────────────────
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar')?.classList.remove('mobile-open');
        }
    });
});
