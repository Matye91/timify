const state = {
    user: null,
    projects: [],
    entries: [],
    report: [],
    reportWeekStart: startOfWeek(new Date()),
    runningEntry: null,
    runningEntryTimer: {
        id: null,
        baseSeconds: 0,
        syncedAt: performance.now(),
    },
    authMode: 'login',
    activeView: 'tracker',
    reportNavigationBound: false,
};

const els = {
    authPanel: document.querySelector('#authPanel'),
    appContent: document.querySelector('#appContent'),
    authForm: document.querySelector('#authForm'),
    authMessage: document.querySelector('#authMessage'),
    userPill: document.querySelector('#userPill'),
    descriptionInput: document.querySelector('#descriptionInput'),
    projectSelect: document.querySelector('#projectSelect'),
    timerForm: document.querySelector('#timerForm'),
    timerDisplay: document.querySelector('#timerDisplay'),
    timerButton: document.querySelector('#timerButton'),
    entriesList: document.querySelector('#entriesList'),
    weekTotal: document.querySelector('#weekTotal'),
    projectForm: document.querySelector('#projectForm'),
    projectList: document.querySelector('#projectList'),
    reportTotals: document.querySelector('#reportTotals'),
    reportChart: document.querySelector('#reportChart'),
    reportWeekLabel: document.querySelector('#reportWeekLabel'),
    previousReportWeek: document.querySelector('#previousReportWeek'),
    nextReportWeek: document.querySelector('#nextReportWeek'),
    viewTitle: document.querySelector('.view-title'),
};

async function api(path, options = {}) {
    const response = await fetch(path, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options,
    });
    let data = {};
    try {
        data = await response.json();
    } catch (error) {
        data = { error: 'The server did not return a valid JSON response.' };
    }
    if (!response.ok) {
        throw new Error(data.error || 'Something went wrong.');
    }
    return data;
}

function formatSeconds(totalSeconds) {
    const total = Math.max(0, Number(totalSeconds) || 0);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = Math.floor(total % 60);
    return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
}

function formatTime(dateString) {
    const date = new Date(`${String(dateString).replace(' ', 'T')}Z`);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function startOfWeek(date) {
    const weekStart = new Date(date);
    weekStart.setHours(0, 0, 0, 0);
    const day = weekStart.getDay();
    const mondayOffset = day === 0 ? -6 : 1 - day;
    weekStart.setDate(weekStart.getDate() + mondayOffset);
    return weekStart;
}

function addDays(date, days) {
    const nextDate = new Date(date);
    nextDate.setDate(nextDate.getDate() + days);
    return nextDate;
}

function toUtcSql(date) {
    return date.toISOString().slice(0, 19).replace('T', ' ');
}

function formatWeekRange(start) {
    const end = addDays(start, 6);
    const sameYear = start.getFullYear() === end.getFullYear();
    const options = sameYear
        ? { month: 'short', day: 'numeric' }
        : { month: 'short', day: 'numeric', year: 'numeric' };
    const startText = start.toLocaleDateString([], options);
    const endText = end.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
    return `${startText} - ${endText}`;
}

function ensureReportControls() {
    if (!els.reportWeekLabel || !els.previousReportWeek || !els.nextReportWeek) {
        const reportGrid = document.querySelector('.report-grid');
        if (!reportGrid || !reportGrid.parentElement) {
            return;
        }

        const toolbar = document.createElement('div');
        toolbar.className = 'report-toolbar';
        toolbar.innerHTML = `
            <button class="icon-button" id="previousReportWeek" type="button" aria-label="Previous week">&lt;</button>
            <strong id="reportWeekLabel">This week</strong>
            <button class="icon-button" id="nextReportWeek" type="button" aria-label="Next week">&gt;</button>
        `;
        reportGrid.parentElement.insertBefore(toolbar, reportGrid);

        els.reportWeekLabel = toolbar.querySelector('#reportWeekLabel');
        els.previousReportWeek = toolbar.querySelector('#previousReportWeek');
        els.nextReportWeek = toolbar.querySelector('#nextReportWeek');
    }

    if (!state.reportNavigationBound && els.previousReportWeek && els.nextReportWeek) {
        els.previousReportWeek.addEventListener('click', () => {
            state.reportWeekStart = addDays(state.reportWeekStart, -7);
            loadReport();
        });

        els.nextReportWeek.addEventListener('click', () => {
            state.reportWeekStart = addDays(state.reportWeekStart, 7);
            loadReport();
        });

        state.reportNavigationBound = true;
    }
}

function utcToLocalInput(dateString) {
    if (!dateString) {
        return '';
    }

    const date = new Date(`${String(dateString).replace(' ', 'T')}Z`);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
}

function localInputToUtcSql(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toISOString().slice(0, 19).replace('T', ' ');
}

function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

function setView(view) {
    state.activeView = view;
    document.querySelectorAll('.nav-item').forEach((button) => {
        button.classList.toggle('active', button.dataset.view === view);
    });
    document.querySelectorAll('.view').forEach((section) => section.classList.remove('active'));
    document.querySelector(`#${view}View`).classList.add('active');
    els.viewTitle.textContent = view === 'tracker' ? 'Time Tracker' : view[0].toUpperCase() + view.slice(1);
    if (view === 'reports') {
        loadReport();
    }
}

function renderShell() {
    const loggedIn = Boolean(state.user);
    els.authPanel.classList.toggle('hidden', loggedIn);
    els.appContent.classList.toggle('hidden', !loggedIn);
    els.userPill.textContent = loggedIn ? initials(state.user.name) : '';
}

function renderProjects() {
    els.projectSelect.innerHTML = state.projects
        .map((project) => `<option value="${project.id}">${escapeHtml(project.name)}</option>`)
        .join('');

    if (state.projects.length === 0) {
        els.projectSelect.innerHTML = '<option value="">Create a project first</option>';
    }

    els.projectList.innerHTML = state.projects.length
        ? state.projects.map(projectRow).join('')
        : '<div class="empty-state">No projects yet. Add your first project above.</div>';
}

function projectRow(project) {
    return `
        <form class="project-row" data-id="${project.id}">
            <span class="project-dot" style="background:${project.color}"></span>
            <input name="name" value="${escapeHtml(project.name)}" maxlength="120" required>
            <input name="color" type="color" value="${project.color}">
            <button class="small-button" type="submit">Save</button>
            <button class="danger-button" data-delete-project="${project.id}" type="button">Delete</button>
        </form>
    `;
}

function renderEntries() {
    state.runningEntry = state.entries.find((entry) => !entry.ended_at) || null;

    if (state.runningEntry) {
        state.runningEntryTimer = {
            id: state.runningEntry.id,
            baseSeconds: Number(state.runningEntry.seconds) || 0,
            syncedAt: performance.now(),
        };
    } else {
        state.runningEntryTimer = {
            id: null,
            baseSeconds: 0,
            syncedAt: performance.now(),
        };
    }

    if (state.runningEntry) {
        els.timerButton.textContent = 'Stop';
        els.timerButton.classList.add('stop');
        els.descriptionInput.value = state.runningEntry.description || '';
        els.projectSelect.value = state.runningEntry.project_id;
    } else {
        els.timerButton.textContent = 'Start';
        els.timerButton.classList.remove('stop');
    }

    els.entriesList.innerHTML = state.entries.length
        ? state.entries.map(entryRow).join('')
        : '<div class="empty-state">No time entries yet. Pick a project and press Start.</div>';

    const weekStart = new Date();
    weekStart.setDate(weekStart.getDate() - 7);
    const weekSeconds = state.entries
        .filter((entry) => {
            const datePart = String(entry.started_at).slice(0, 10);
            return datePart && new Date(`${datePart}T00:00:00`) >= weekStart;
        })
        .reduce((sum, entry) => sum + Number(entry.seconds), 0);
    els.weekTotal.textContent = `Week total: ${formatSeconds(weekSeconds)}`;
    updateTimerDisplay();
}

function entryRow(entry) {
    const options = state.projects.map((project) => `
        <option value="${project.id}" ${Number(project.id) === Number(entry.project_id) ? 'selected' : ''}>
            ${escapeHtml(project.name)}
        </option>
    `).join('');

    return `
        <form class="entry-row" data-id="${entry.id}">
            <input name="description" value="${escapeHtml(entry.description || '')}" maxlength="500" placeholder="No description">
            <select name="project_id" required>${options}</select>
            <div class="entry-time-edit">
                <input name="started_at" type="datetime-local" value="${utcToLocalInput(entry.started_at)}" required>
                <input name="ended_at" type="datetime-local" value="${utcToLocalInput(entry.ended_at)}" aria-label="End time">
            </div>
            <div class="entry-duration">${formatSeconds(entry.seconds)}</div>
            <div class="entry-actions">
                <button class="small-button" type="submit">Save</button>
                <button class="danger-button" data-delete-entry="${entry.id}" type="button">Delete</button>
            </div>
        </form>
    `;
}

async function loadAll() {
    const [projects, entries] = await Promise.all([
        api('../api/projects.php'),
        api('../api/time_entries.php'),
    ]);
    state.projects = projects.projects;
    state.entries = entries.entries;
    renderProjects();
    renderEntries();
}

async function loadReport() {
    ensureReportControls();
    const weekStart = state.reportWeekStart;
    const weekEnd = addDays(weekStart, 7);
    const params = new URLSearchParams({
        start: toUtcSql(weekStart),
        end: toUtcSql(weekEnd),
    });
    const data = await api(`../api/reports.php?${params.toString()}`);
    state.report = data.projects;
    renderReport();
}

function renderReport() {
    if (els.reportWeekLabel) {
        els.reportWeekLabel.textContent = formatWeekRange(state.reportWeekStart);
    }

    els.reportTotals.innerHTML = state.report.length
        ? state.report.map((project) => `
            <div class="total-row">
                <span class="project-dot" style="background:${project.color}"></span>
                <strong>${escapeHtml(project.name)}</strong>
                <span>${formatSeconds(project.seconds)}</span>
            </div>
        `).join('')
        : '<div class="empty-state">No project data yet.</div>';

    drawChart();
}

function drawChart() {
    const canvas = els.reportChart;
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = 46;
    const chartWidth = width - padding * 2;
    const chartHeight = height - padding * 2;
    const maxSeconds = Math.max(...state.report.map((project) => Number(project.seconds)), 1);

    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    ctx.strokeStyle = '#dfe7ef';
    ctx.lineWidth = 1;

    for (let i = 0; i <= 4; i += 1) {
        const y = padding + (chartHeight / 4) * i;
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
    }

    if (!state.report.length) {
        ctx.fillStyle = '#748292';
        ctx.font = '18px Arial';
        ctx.fillText('No data yet', padding, height / 2);
        return;
    }

    const gap = 18;
    const barWidth = Math.max(36, (chartWidth - gap * (state.report.length - 1)) / state.report.length);

    state.report.forEach((project, index) => {
        const seconds = Number(project.seconds);
        const barHeight = (seconds / maxSeconds) * (chartHeight - 32);
        const x = padding + index * (barWidth + gap);
        const y = padding + chartHeight - barHeight;

        ctx.fillStyle = project.color;
        ctx.fillRect(x, y, barWidth, barHeight);

        ctx.fillStyle = '#25313d';
        ctx.font = '14px Arial';
        ctx.fillText(formatSeconds(seconds), x, y - 8);
        ctx.fillStyle = '#748292';
        ctx.fillText(project.name.slice(0, 14), x, height - 18);
    });
}

function updateTimerDisplay() {
    if (!state.runningEntry) {
        els.timerDisplay.textContent = '00:00:00';
        return;
    }

    const timer = state.runningEntryTimer;
    const clientSeconds = (performance.now() - timer.syncedAt) / 1000;
    els.timerDisplay.textContent = formatSeconds(timer.baseSeconds + clientSeconds);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

document.querySelectorAll('.auth-tab').forEach((button) => {
    button.addEventListener('click', () => {
        state.authMode = button.dataset.mode;
        document.querySelectorAll('.auth-tab').forEach((tab) => tab.classList.toggle('active', tab === button));
        document.querySelectorAll('.register-only').forEach((item) => {
            item.classList.toggle('hidden', state.authMode !== 'register');
        });
        els.authMessage.textContent = '';
    });
});

document.querySelectorAll('.nav-item').forEach((button) => {
    button.addEventListener('click', () => setView(button.dataset.view));
});

ensureReportControls();

els.authForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    els.authMessage.textContent = '';
    const body = Object.fromEntries(new FormData(els.authForm));
    try {
        const data = await api(`../api/auth.php?action=${state.authMode}`, {
            method: 'POST',
            body: JSON.stringify(body),
        });
        state.user = data.user;
        renderShell();
        await loadAll();
    } catch (error) {
        els.authMessage.textContent = error.message;
    }
});

document.querySelector('.logout-button').addEventListener('click', async () => {
    await api('../api/auth.php?action=logout', { method: 'POST', body: '{}' });
    state.user = null;
    state.projects = [];
    state.entries = [];
    renderShell();
});

els.timerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (state.runningEntry) {
        await api('../api/time_entries.php', {
            method: 'PATCH',
            body: JSON.stringify({ id: state.runningEntry.id, action: 'stop' }),
        });
    } else {
        await api('../api/time_entries.php', {
            method: 'POST',
            body: JSON.stringify({
                project_id: els.projectSelect.value,
                description: els.descriptionInput.value,
            }),
        });
    }
    await loadAll();
});

els.projectForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(els.projectForm));
    await api('../api/projects.php', { method: 'POST', body: JSON.stringify(body) });
    els.projectForm.reset();
    els.projectForm.elements.color.value = '#2563eb';
    await loadAll();
});

els.projectList.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const body = Object.fromEntries(new FormData(form));
    body.id = form.dataset.id;
    await api('../api/projects.php', { method: 'PATCH', body: JSON.stringify(body) });
    await loadAll();
});

els.projectList.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-delete-project]');
    if (!button) {
        return;
    }
    if (!confirm('Delete this project and its time entries?')) {
        return;
    }
    await api('../api/projects.php', {
        method: 'DELETE',
        body: JSON.stringify({ id: button.dataset.deleteProject }),
    });
    await loadAll();
});

els.entriesList.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const body = Object.fromEntries(new FormData(form));
    body.id = form.dataset.id;
    body.action = 'update';
    body.started_at = localInputToUtcSql(body.started_at);
    body.ended_at = localInputToUtcSql(body.ended_at);

    await api('../api/time_entries.php', {
        method: 'PATCH',
        body: JSON.stringify(body),
    });
    await loadAll();
});

els.entriesList.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-delete-entry]');
    if (!button) {
        return;
    }

    if (!confirm('Delete this time entry?')) {
        return;
    }

    await api('../api/time_entries.php', {
        method: 'DELETE',
        body: JSON.stringify({ id: button.dataset.deleteEntry }),
    });
    await loadAll();
});

setInterval(updateTimerDisplay, 1000);

(async function init() {
    document.querySelectorAll('.register-only').forEach((item) => item.classList.add('hidden'));
    try {
        const data = await api('../api/auth.php?action=me');
        state.user = data.user;
        renderShell();
        if (state.user) {
            await loadAll();
        }
    } catch (error) {
        state.user = null;
        renderShell();
        els.authMessage.textContent = 'Check the MySQL setup, then refresh this page.';
    }
})();
