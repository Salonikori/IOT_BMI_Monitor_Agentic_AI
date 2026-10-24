/* ============================================================
   assets/js/app.js
   Client-side logic:
     - Auto-refresh dashboard (polling every 5 seconds)
     - Chart.js graph rendering
     - PDF download via html2pdf
     - Mobile sidebar toggle
   ============================================================ */

/* ============================================================
   SIDEBAR MOBILE TOGGLE
   ============================================================ */
const hamburger = document.getElementById('hamburger');
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');

if (hamburger) {
    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });
}

/* ============================================================
   DASHBOARD AUTO-REFRESH (polling every 5 seconds)
   Fetches latest BMI record and updates the stat cards live
   ============================================================ */
function startDashboardPolling() {
    // Only run on the dashboard page
    if (!document.getElementById('bmi-value')) return;

    const refresh = () => {
        fetch('/bmi_monitor/api/get_latest.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Update stat cards
                setText('bmi-value',    data.bmi);
                setText('height-value', data.height);
                setText('weight-value', data.weight);

                // Update BMI badge
                const badge = document.getElementById('bmi-badge');
                if (badge) {
                    badge.textContent = data.category;
                    badge.style.background = hexToRgba(data.color, 0.15);
                    badge.style.color       = data.color;
                    badge.style.border      = `1px solid ${hexToRgba(data.color, 0.4)}`;
                }

                // Update suggestion text
                setText('health-suggestion', data.suggestion);

                // Update AI recommendation card (from the ml_service model), if present
                const reco = data.recommendation;
                if (reco && reco.overall_recommendation) {
                    setText('reco-overall',   reco.overall_recommendation);
                    setText('reco-calories',  reco.recommended_calories);
                    setText('reco-protein',   reco.protein_g_per_day);
                    setText('reco-water',     Number(reco.water_l_per_day).toFixed(1));
                    setText('reco-sleep',     reco.sleep_hours);
                    setText('reco-exercise',  reco.exercise_plan);
                    setText('reco-diet',      reco.diet_recommendation);
                }

                // Update timestamp
                const ts = document.getElementById('last-updated');
                if (ts) ts.textContent = 'Last updated: ' + formatDateTime(data.recorded_at);
            })
            .catch(err => console.warn('Polling error:', err));
    };

    refresh();                   // run immediately
    setInterval(refresh, 5000); // then every 5 seconds
}

/* ============================================================
   CHARTS (BMI over time + Weight over time)
   ============================================================ */
function initCharts() {
    const bmiCtx    = document.getElementById('bmiChart');
    const weightCtx = document.getElementById('weightChart');

    if (!bmiCtx && !weightCtx) return; // not on a chart page

    fetch('/bmi_monitor/api/get_history.php?limit=30')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.records.length) return;

            const labels  = data.records.map(r => formatDate(r.recorded_at));
            const bmis    = data.records.map(r => parseFloat(r.bmi));
            const weights = data.records.map(r => parseFloat(r.weight));

            const chartDefaults = {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        titleColor: '#e2e8f0',
                        bodyColor: '#94a3b8',
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 11 } },
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 11 } },
                    }
                }
            };

            // BMI chart (line with gradient fill)
            if (bmiCtx) {
                const bmiGradient = bmiCtx.getContext('2d').createLinearGradient(0, 0, 0, 200);
                bmiGradient.addColorStop(0,   'rgba(6,214,160,0.3)');
                bmiGradient.addColorStop(1,   'rgba(6,214,160,0)');

                new Chart(bmiCtx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'BMI',
                            data: bmis,
                            borderColor: '#06d6a0',
                            backgroundColor: bmiGradient,
                            borderWidth: 2.5,
                            pointBackgroundColor: '#06d6a0',
                            pointRadius: 4,
                            tension: 0.4,
                            fill: true,
                        }]
                    },
                    options: {
                        ...chartDefaults,
                        // Draw horizontal reference lines for BMI zones
                        plugins: {
                            ...chartDefaults.plugins,
                            annotation: undefined, // (optional plugin)
                        }
                    }
                });
            }

            // Weight chart (bar)
            if (weightCtx) {
                new Chart(weightCtx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Weight (kg)',
                            data: weights,
                            backgroundColor: 'rgba(59,130,246,0.5)',
                            borderColor: '#3b82f6',
                            borderWidth: 1.5,
                            borderRadius: 5,
                        }]
                    },
                    options: chartDefaults
                });
            }
        })
        .catch(err => console.warn('Chart data error:', err));
}

/* ============================================================
   PDF DOWNLOAD
   Uses html2pdf.js to convert the dashboard to a PDF report
   ============================================================ */
function downloadPDF() {
    const element = document.getElementById('pdf-area');
    if (!element) { alert('Nothing to download.'); return; }

    const opt = {
        margin:       [10, 10, 10, 10],
        filename:     'bmi-report.pdf',
        image:        { type: 'jpeg', quality: 0.95 },
        html2canvas:  { scale: 2, backgroundColor: '#0a0f1e' },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save();
}

/* ============================================================
   CONFIRM DIALOG helper (for delete actions)
   ============================================================ */
function confirmDelete(url, message) {
    if (confirm(message || 'Are you sure you want to delete this record?')) {
        window.location.href = url;
    }
}

/* ============================================================
   EMAIL REPORT MODAL
   Reusable across dashboard.php, history.php and
   admin/user_records.php. Any button with the class
   "js-email-btn" and the right data-* attributes will open it:
     data-record-id, data-bmi, data-color, data-category,
     data-height, data-weight, data-date, data-person
   ============================================================ */
let _emailModalRecordId = null;

function ensureEmailModal() {
    if (document.getElementById('email-modal')) return;

    const wrap = document.createElement('div');
    wrap.innerHTML = `
    <div class="modal-overlay" id="email-modal">
      <div class="modal-box">
        <div class="modal-header">
          <div style="display:flex;gap:14px;align-items:flex-start;">
            <div class="modal-header-icon">&#9993;</div>
            <div>
              <div class="modal-title">Email BMI Report</div>
              <div class="modal-subtitle">Send this reading to any email address</div>
            </div>
          </div>
          <button class="modal-close" onclick="closeEmailModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
          <div class="modal-preview">
            <div class="modal-preview-bmi" id="email-preview-bmi">--</div>
            <div class="modal-preview-meta" id="email-preview-meta"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Recipient Email</label>
            <input type="email" id="email-to" class="form-control" placeholder="e.g. patient@example.com" autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label">Recipient Name <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
            <input type="text" id="email-name" class="form-control" placeholder="e.g. John Doe" autocomplete="off">
          </div>
          <div class="form-group" style="margin-bottom:6px;">
            <label class="form-label">Note <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
            <textarea id="email-note" class="form-control" rows="3" maxlength="500" placeholder="Add a short message for the recipient..." style="resize:vertical;"></textarea>
          </div>
          <p class="form-error" id="email-error"></p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" onclick="closeEmailModal()">Cancel</button>
          <button class="btn btn-mail" id="email-send-btn" onclick="submitEmailModal()">&#128228; Send Email</button>
        </div>
      </div>
    </div>`;
    document.body.appendChild(wrap.firstElementChild);

    // Close when clicking the dark backdrop
    document.getElementById('email-modal').addEventListener('click', (e) => {
        if (e.target.id === 'email-modal') closeEmailModal();
    });
    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeEmailModal();
    });
    // Submit on Enter inside the email field
    document.getElementById('email-to').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); submitEmailModal(); }
    });
}

function showEmailModalForRecord(d) {
    ensureEmailModal();
    _emailModalRecordId = d.recordId;

    const bmiEl = document.getElementById('email-preview-bmi');
    bmiEl.textContent = d.bmi;
    bmiEl.style.color = d.color || '#06d6a0';

    document.getElementById('email-preview-meta').innerHTML =
        `<strong>${d.person}</strong> &middot; ${d.category}<br>` +
        `${d.height} cm &middot; ${d.weight} kg<br>` +
        `<span style="color:var(--muted)">${d.date}</span>`;

    document.getElementById('email-to').value   = d.email || '';
    document.getElementById('email-name').value = d.email ? (d.person || '') : '';
    document.getElementById('email-note').value = '';
    document.getElementById('email-error').classList.remove('show');

    const overlay = document.getElementById('email-modal');
    overlay.classList.add('show');
    setTimeout(() => {
        const input = document.getElementById('email-to');
        input.focus();
        if (d.email) input.select();
    }, 180);
}

// Opens the modal from a table row button (data-* attributes already known)
function openEmailModal(btn) {
    showEmailModalForRecord(btn.dataset);
}

// Opens the modal for the dashboard's "latest reading" — fetches the
// freshest record first so it always matches what auto-refresh shows
function openEmailModalForLatest(triggerBtn) {
    if (triggerBtn) { triggerBtn.classList.add('btn-loading'); triggerBtn.disabled = true; }

    fetch('/bmi_monitor/api/get_latest.php')
        .then(r => r.json())
        .then(data => {
            if (triggerBtn) { triggerBtn.classList.remove('btn-loading'); triggerBtn.disabled = false; }
            if (!data.success || !data.record_id) {
                showToast('No BMI record yet — take a reading first.', 'error');
                return;
            }
            showEmailModalForRecord({
                recordId: data.record_id,
                bmi:      Number(data.bmi).toFixed(1),
                color:    data.color,
                category: data.category,
                height:   Number(data.height).toFixed(1),
                weight:   Number(data.weight).toFixed(1),
                date:     formatDateTime(data.recorded_at),
                person:   data.person || 'You',
            });
        })
        .catch(() => {
            if (triggerBtn) { triggerBtn.classList.remove('btn-loading'); triggerBtn.disabled = false; }
            showToast('Could not load the latest record. Please try again.', 'error');
        });
}

function closeEmailModal() {
    const overlay = document.getElementById('email-modal');
    if (overlay) overlay.classList.remove('show');
}

function submitEmailModal() {
    const to    = document.getElementById('email-to').value.trim();
    const name  = document.getElementById('email-name').value.trim();
    const note  = document.getElementById('email-note').value.trim();
    const errEl = document.getElementById('email-error');
    const btn   = document.getElementById('email-send-btn');

    if (!to || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
        errEl.textContent = '⚠️ Please enter a valid email address.';
        errEl.classList.add('show');
        return;
    }
    errEl.classList.remove('show');
    btn.classList.add('btn-loading');
    btn.disabled = true;

    fetch('/bmi_monitor/api/send_email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            record_id: _emailModalRecordId,
            to_email:  to,
            to_name:   name,
            note:      note
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.classList.remove('btn-loading');
        btn.disabled = false;
        if (data.success) {
            closeEmailModal();
            showToast(data.message || 'Email sent successfully.', 'success');
        } else {
            errEl.textContent = '⚠️ ' + (data.message || 'Failed to send email.');
            errEl.classList.add('show');
        }
    })
    .catch(() => {
        btn.classList.remove('btn-loading');
        btn.disabled = false;
        errEl.textContent = '⚠️ Network error. Please check your connection and try again.';
        errEl.classList.add('show');
    });
}

/* ============================================================
   TOAST NOTIFICATIONS
   showToast('message', 'success' | 'error')
   ============================================================ */
function showToast(message, type = 'success') {
    let stack = document.getElementById('toast-stack');
    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'toast-stack';
        stack.className = 'toast-stack';
        document.body.appendChild(stack);
    }

    const toast = document.createElement('div');
    toast.className = 'toast' + (type === 'error' ? ' toast-error' : '');
    toast.innerHTML = `<span class="toast-icon">${type === 'error' ? '⚠️' : '✅'}</span><span>${message}</span>`;
    stack.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-leaving');
        setTimeout(() => toast.remove(), 250);
    }, 4500);
}

/* ============================================================
   Wire up any [data-js="email-btn"] buttons present on load
   ============================================================ */
function bindEmailButtons() {
    document.querySelectorAll('.js-email-btn').forEach(btn => {
        btn.addEventListener('click', () => openEmailModal(btn));
    });
}

/* ============================================================
   HELPER FUNCTIONS
   ============================================================ */

// Safely set element text content
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

// Convert hex colour to rgba string
function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1,3), 16);
    const g = parseInt(hex.slice(3,5), 16);
    const b = parseInt(hex.slice(5,7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

// Format ISO datetime to readable date
function formatDate(dt) {
    return new Date(dt).toLocaleDateString('en-IN', { day:'2-digit', month:'short' });
}

// Format ISO datetime to readable full datetime
function formatDateTime(dt) {
    return new Date(dt).toLocaleString('en-IN', { dateStyle:'medium', timeStyle:'short' });
}

/* ============================================================
   INIT — run on DOM ready
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    startDashboardPolling();
    initCharts();
    bindEmailButtons();
});
