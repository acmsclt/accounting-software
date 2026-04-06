/**
 * AccountingPro — App Tour Engine
 * Pure-JS guided tour with spotlight, tooltips, and step sequencing.
 * No external dependencies.
 */

;(function (window) {
    'use strict';

    // ── Tour step definitions ─────────────────────────────────────────────
    const TOURS = {

        // ── Dashboard ──────────────────────────────────────────────────────
        dashboard: [
            {
                target:  null,   // Centred modal intro
                title:   '👋 Welcome to AccountingPro!',
                content: 'This quick tour will walk you through the key features of your accounting dashboard. You can skip at any time.',
                align:   'center',
            },
            {
                target:  '#sidebar',
                title:   '🗂️ Main Navigation',
                content: 'The sidebar gives you access to all modules — Invoices, Customers, Expenses, Reports, and more. It collapses for more screen space.',
                align:   'right',
            },
            {
                target:  '.sidebar-context',
                title:   '🏢 Company & Branch Switcher',
                content: 'Click here to switch between branches. All data is automatically filtered to the selected branch.',
                align:   'right',
            },
            {
                target:  '.stat-grid',
                title:   '📊 KPI Summary Cards',
                content: 'These cards show real-time financial highlights — revenue, expenses, outstanding invoices, and net profit for the current period.',
                align:   'bottom',
            },
            {
                target:  '.topbar-search',
                title:   '🔍 Global Search',
                content: 'Quickly search customers, invoices, products, and more — without leaving the current page.',
                align:   'bottom',
            },
            {
                target:  '.theme-toggle',
                title:   '🌙 Dark / Light Mode',
                content: 'Toggle between dark and light themes. Your preference is saved automatically.',
                align:   'bottom',
            },
            {
                target:  '.branch-pill',
                title:   '🏢 Active Branch Indicator',
                content: 'This shows your currently active branch. Click to switch branches instantly. All reports and entries will use this branch.',
                align:   'bottom',
            },
            {
                target:  'a[href="/invoices"]',
                title:   '🧾 Invoices',
                content: 'Create, track, and send professional invoices. Record payments and automate reminders.',
                align:   'right',
            },
            {
                target:  'a[href="/reports"]',
                title:   '📈 360° Reports',
                content: 'Access 22 different financial reports — P&L, Balance Sheet, Aging, Tax Summary — all exportable as PDF, Excel, CSV, or JSON.',
                align:   'right',
            },
            {
                target:  'a[href="/import/new"]',
                title:   '📥 Data Import',
                content: 'Import customers, products, expenses and more from Google Sheets or CSV files. A guided 5-step wizard handles everything.',
                align:   'right',
            },
            {
                target:  'a[href="/users"]',
                title:   '👥 User Management',
                content: 'Invite team members, assign roles, control branch access, and set granular per-permission overrides.',
                align:   'right',
            },
            {
                target:  'a[href="/roles"]',
                title:   '🔐 Roles & Permissions',
                content: 'Create custom roles with a visual permission matrix. 47 granular permissions across 14 modules.',
                align:   'right',
            },
            {
                target:  null,
                title:   '🎉 You\'re all set!',
                content: 'You now know the essentials. Explore each module, or click a section in the sidebar to get started. You can restart this tour anytime from the Help menu.',
                align:   'center',
            },
        ],

        // ── Reports ───────────────────────────────────────────────────────
        reports: [
            {
                target:  '.report-card-grid',
                title:   '📊 Report Library',
                content: 'All 22 reports are grouped by category. Click any card to open and run the report.',
                align:   'top',
            },
            {
                target:  '#filterCard',
                title:   '📅 Date Range & Branch Filter',
                content: 'All reports support date range filtering and branch-specific filtering. Use the quick-period buttons for fast selection.',
                align:   'bottom',
            },
            {
                target:  '#exportBtn',
                title:   '📤 Export Options',
                content: 'Every report can be exported as PDF (formatted), Excel (.xlsx), CSV, or JSON. Use print mode for quick hard copies.',
                align:   'bottom',
            },
        ],

        // ── Import wizard ─────────────────────────────────────────────────
        import: [
            {
                target:  '.wizard-steps',
                title:   '📥 5-Step Import Wizard',
                content: 'The import wizard guides you through: choosing your source, configuring options, previewing data, mapping columns, and running the import.',
                align:   'bottom',
            },
            {
                target:  '#sourceGoogleSheets',
                title:   '🔗 Google Sheets Support',
                content: 'Paste any public Google Sheets share URL — AccountingPro will fetch and parse the data automatically. No API key needed.',
                align:   'right',
            },
        ],

        // ── Roles/permissions ─────────────────────────────────────────────
        roles: [
            {
                target:  '.roles-grid',
                title:   '🔐 Role Cards',
                content: 'Each card shows a role\'s permission coverage, module access badges, and user count. Click "Edit Permissions" to manage.',
                align:   'bottom',
            },
            {
                target:  '.data-table',
                title:   '📋 Comparison Matrix',
                content: 'The table below shows all roles side-by-side for every module. ✓ = full access, fraction = partial, ✕ = no access.',
                align:   'top',
            },
        ],

        // ── Users ─────────────────────────────────────────────────────────
        users: [
            {
                target:  '#userTable',
                title:   '👥 Team Members',
                content: 'All users in your company are listed here with their active roles and branch access.',
                align:   'bottom',
            },
            {
                target:  'a[href="/users/invite"]',
                title:   '✉️ Invite Users',
                content: 'Click to send email invitations. Assign a role and branch access before sending.',
                align:   'bottom',
            },
        ],
    };

    // ── Tour class ────────────────────────────────────────────────────────
    class AppTour {
        constructor() {
            this.steps       = [];
            this.current     = 0;
            this.tourName    = '';
            this.overlay     = null;
            this.spotlight   = null;
            this.tooltip     = null;
            this.onComplete  = null;
            this._resizeHandler = null;
        }

        start(tourName, options = {}) {
            const steps = TOURS[tourName];
            if (!steps || steps.length === 0) return;
            this.steps    = steps;
            this.tourName = tourName;
            this.current  = 0;
            this.onComplete = options.onComplete || null;
            this._build();
            this._show(0);
            document.body.classList.add('tour-active');
        }

        _build() {
            // Remove any existing tour
            this._destroy();

            // Overlay
            this.overlay = el('div', { id: 'tourOverlay', className: 'tour-overlay' });
            document.body.appendChild(this.overlay);
            this.overlay.addEventListener('click', () => this._next());

            // Spotlight hole
            this.spotlight = el('div', { className: 'tour-spotlight' });
            document.body.appendChild(this.spotlight);

            // Tooltip card
            this.tooltip = el('div', { className: 'tour-tooltip', id: 'tourTooltip' });
            this.tooltip.innerHTML = this._tooltipHTML();
            document.body.appendChild(this.tooltip);

            // Keyboard nav
            this._keyHandler = (e) => {
                if (e.key === 'ArrowRight' || e.key === 'Enter') this._next();
                if (e.key === 'ArrowLeft') this._prev();
                if (e.key === 'Escape') this.skip();
            };
            document.addEventListener('keydown', this._keyHandler);

            // Resize handler
            this._resizeHandler = () => this._positionSpotlight(this.current);
            window.addEventListener('resize', this._resizeHandler);
        }

        _tooltipHTML() {
            const step  = this.steps[this.current] || {};
            const total = this.steps.length;
            const isFirst = this.current === 0;
            const isLast  = this.current === total - 1;
            const pct     = Math.round((this.current / (total - 1)) * 100);

            const dots = this.steps.map((_, i) => {
                return `<span class="tour-dot ${i === this.current ? 'active' : i < this.current ? 'done' : ''}"></span>`;
            }).join('');

            return `
            <div class="tour-tooltip-inner">
                <button class="tour-close" onclick="AppTour.skip()" title="Skip tour">×</button>
                <div class="tour-step-badge">${this.current + 1} / ${total}</div>
                <h3 class="tour-title">${step.title || ''}</h3>
                <p class="tour-content">${step.content || ''}</p>
                <div class="tour-progress">
                    <div class="tour-progress-fill" style="width:${pct}%"></div>
                </div>
                <div class="tour-dots">${dots}</div>
                <div class="tour-actions">
                    <button class="tour-btn tour-btn-skip" onclick="AppTour.skip()">Skip Tour</button>
                    <div style="display:flex;gap:8px;">
                        ${!isFirst ? `<button class="tour-btn tour-btn-prev" onclick="AppTour.prev()">← Prev</button>` : ''}
                        <button class="tour-btn tour-btn-next" onclick="AppTour.next()">
                            ${isLast ? '🎉 Finish' : 'Next →'}
                        </button>
                    </div>
                </div>
            </div>`;
        }

        _show(index) {
            this.current = Math.max(0, Math.min(index, this.steps.length - 1));
            const step   = this.steps[this.current];

            // Update tooltip content
            this.tooltip.innerHTML = this._tooltipHTML();

            // Position spotlight + tooltip
            this._positionSpotlight(this.current);

            // Animate in
            requestAnimationFrame(() => {
                this.tooltip.classList.add('visible');
                this.spotlight.classList.add('visible');
            });
        }

        _positionSpotlight(index) {
            const step   = this.steps[index];
            const target = step.target ? document.querySelector(step.target) : null;

            if (!target) {
                // Centred modal, no spotlight
                this.spotlight.style.cssText = 'display:none;';
                this.tooltip.style.cssText   = '';
                this.tooltip.className = 'tour-tooltip tour-tooltip-center visible';
                return;
            }

            this.tooltip.className = 'tour-tooltip visible';

            // Scroll target into view
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });

            setTimeout(() => {
                const r = target.getBoundingClientRect();
                const pad = 10;

                // Spotlight
                this.spotlight.style.cssText = `
                    display:block;
                    top:${r.top    - pad + window.scrollY}px;
                    left:${r.left  - pad + window.scrollX}px;
                    width:${r.width  + pad * 2}px;
                    height:${r.height + pad * 2}px;
                `;

                // Tooltip positioning
                const vw = window.innerWidth;
                const vh = window.innerHeight;
                const tw = 340; // tooltip width
                const th = 280; // approx height
                const align = step.align || 'bottom';

                let top, left;

                if (align === 'right') {
                    top  = r.top + window.scrollY + (r.height / 2) - th / 2;
                    left = r.right + pad * 2 + window.scrollX;
                    if (left + tw > vw) { left = r.left - tw - pad * 2 + window.scrollX; }
                } else if (align === 'left') {
                    top  = r.top + window.scrollY + (r.height / 2) - th / 2;
                    left = r.left - tw - pad * 2 + window.scrollX;
                    if (left < 0) { left = r.right + pad * 2 + window.scrollX; }
                } else if (align === 'top') {
                    top  = r.top + window.scrollY - th - pad * 2;
                    left = r.left + window.scrollX + (r.width / 2) - tw / 2;
                } else { // bottom (default)
                    top  = r.bottom + window.scrollY + pad * 2;
                    left = r.left   + window.scrollX + (r.width / 2) - tw / 2;
                }

                // Clamp to viewport
                left = Math.max(10, Math.min(left, vw - tw - 10));
                top  = Math.max(10, top);

                this.tooltip.style.cssText = `top:${top}px;left:${left}px;`;
            }, 350); // wait for scroll
        }

        _next() {
            if (this.current < this.steps.length - 1) {
                this._show(this.current + 1);
            } else {
                this.finish();
            }
        }
        _prev() {
            if (this.current > 0) this._show(this.current - 1);
        }

        finish() {
            this._markSeen();
            if (this.onComplete) this.onComplete();
            this._destroy();
        }

        skip() {
            this._markSeen();
            this._destroy();
        }

        _markSeen() {
            // Store in localStorage
            const seen = JSON.parse(localStorage.getItem('ap_tours_seen') || '{}');
            seen[this.tourName] = true;
            localStorage.setItem('ap_tours_seen', JSON.stringify(seen));

            // Also tell server (best-effort)
            fetch('/tour/complete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `tour=${this.tourName}&_token=${document.querySelector('[name=_token]')?.value || ''}`,
            }).catch(() => {});
        }

        _destroy() {
            this.overlay?.remove();
            this.spotlight?.remove();
            this.tooltip?.remove();
            this.overlay = this.spotlight = this.tooltip = null;
            document.body.classList.remove('tour-active');
            if (this._keyHandler) document.removeEventListener('keydown', this._keyHandler);
            if (this._resizeHandler) window.removeEventListener('resize', this._resizeHandler);
        }

        static hasSeen(tourName) {
            const seen = JSON.parse(localStorage.getItem('ap_tours_seen') || '{}');
            return !!seen[tourName];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    function el(tag, props = {}) {
        const e = document.createElement(tag);
        Object.assign(e, props);
        return e;
    }

    // ── Global singleton ──────────────────────────────────────────────────
    const tourInstance = new AppTour();

    window.AppTour = {
        start:   (name, opts) => tourInstance.start(name, opts),
        next:    ()           => tourInstance._next(),
        prev:    ()           => tourInstance._prev(),
        finish:  ()           => tourInstance.finish(),
        skip:    ()           => tourInstance.skip(),
        hasSeen: (name)       => AppTour.hasSeen(name),
        tours:   TOURS,
    };

    // ── Auto-start tour on first visit ────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const page = document.body.dataset.tour;
        if (!page) return;
        if (AppTour.hasSeen(page)) return;

        // Small delay to let page fully paint
        setTimeout(() => tourInstance.start(page), 800);
    });

})(window);
