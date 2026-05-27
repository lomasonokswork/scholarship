<?php
require 'db.php';

$subjectsCount = $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
$studentsCount = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

$latestConfig = null;
$stmt = $pdo->query('SELECT * FROM scholarship_config ORDER BY id DESC LIMIT 1');
if ($stmt)
    $latestConfig = $stmt->fetch();

// Only show Results/Groups tabs if there are actual calculated results
$hasResults = false;
if ($latestConfig) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM scholarship_results WHERE config_id = ?');
    $stmt->execute([$latestConfig['id']]);
    $hasResults = $stmt->fetchColumn() > 0;
}

$groups = [];
if ($studentsCount > 0) {
    $stmt = $pdo->query('SELECT DISTINCT group_name FROM students ORDER BY group_name');
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="lv">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stipendiju aprēķina sistēma</title>
    <link rel="icon"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23000'/%3E%3Crect x='25' y='25' width='50' height='55' rx='5' fill='%23fff'/%3E%3Ccircle cx='65' cy='65' r='13' fill='%23000'/%3E%3Ctext x='65' y='71' font-size='14' text-anchor='middle' fill='%23fff' font-weight='700'%3E%E2%82%AC%3C/text%3E%3C/svg%3E"
        type="image/svg+xml">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>
        <div class="header-brand">Stipendiju sistēma</div>
        <button class="hamburger" onclick="toggleSidebar()" aria-label="Navigācija">
            <span></span><span></span><span></span>
        </button>
        <div class="header-meta">v1.0 / <?= date('Y') ?></div>
    </header>

    <div class="app-shell">
        <nav class="sidebar" id="sidebar">
            <div class="nav-label">Navigācija</div>
            <button class="nav-item active" onclick="switchTab('settings', this)">Iestatījumi</button>
            <button class="nav-item<?= $hasResults ? '' : ' nav-hidden' ?>" id="btn-results"
                onclick="switchTab('results', this)">Rezultāti</button>
            <button class="nav-item<?= $hasResults ? '' : ' nav-hidden' ?>" id="btn-groups"
                onclick="switchTab('groups', this)">Grupu skats</button>
        </nav>

        <main class="content">

            <!-- ═══ TAB 1: SETTINGS ═══ -->
            <div id="tab-settings" class="tab-content active">
                <div class="page-title">01 / Iestatījumi</div>
                <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.75rem;">
                    <h2 style="margin-bottom:0;">Konfigurācija</h2>
                    <button type="button" id="btn-clear-config" class="btn-ghost" onclick="clearConfig()"
                        style="border-color:var(--accent-red);color:var(--accent-red);font-size:0.68rem;"
                        <?= !$latestConfig ? 'disabled' : '' ?>>
                        Notīrīt visus datus
                    </button>
                </div>

                <div class="upload-grid">
                    <div class="section-block">
                        <div class="section-header">
                            <h3>Mācību priekšmeti</h3><span class="section-tag">XLSX</span>
                        </div>
                        <div class="section-body">
                            <form id="form-subjects" enctype="multipart/form-data">
                                <div class="form-group">
                                    <div class="drop-zone" id="drop-subjects"
                                        onclick="document.getElementById('subjects_file').click()">
                                        <div class="drop-zone-icon">&#128196;</div>
                                        <div class="drop-zone-text">Ievelc failu šeit vai <strong>izvēlies
                                                failu</strong></div>
                                        <div class="drop-zone-filename" id="subjects-filename"></div>
                                    </div>
                                    <label for="subjects_file">Excel fails</label>
                                    <input type="file" id="subjects_file" name="subjects_file" accept=".xlsx,.xls"
                                        required>
                                    <small>A: nosaukums &nbsp;|&nbsp; B: VIMP vai PROF</small>
                                </div>
                                <button type="submit">Augšupielādēt</button>
                            </form>
                            <div id="subjects-status"></div>
                            <div class="status-bar mt1" id="subjects-count">
                                <?= $subjectsCount > 0 ? "✓ Priekšmeti: {$subjectsCount}" : "— Nav ielādētu priekšmetu" ?>
                            </div>
                        </div>
                    </div>
                    <div class="section-block">
                        <div class="section-header">
                            <h3>Izglītojamo vērtējumi</h3><span class="section-tag">E-KLASE</span>
                        </div>
                        <div class="section-body">
                            <form id="form-grades" enctype="multipart/form-data">
                                <div class="form-group">
                                    <div class="drop-zone" id="drop-grades"
                                        onclick="document.getElementById('grades_file').click()">
                                        <div class="drop-zone-icon">&#128196;</div>
                                        <div class="drop-zone-text">Ievelc failu šeit vai <strong>izvēlies
                                                failu</strong></div>
                                        <div class="drop-zone-filename" id="grades-filename"></div>
                                    </div>
                                    <label for="grades_file">E-klase Excel fails</label>
                                    <input type="file" id="grades_file" name="grades_file" accept=".xlsx,.xls" required>
                                    <small>Eksportēts tieši no E-klases</small>
                                </div>
                                <button type="submit">Augšupielādēt</button>
                            </form>
                            <div id="grades-status"></div>
                            <div class="status-bar mt1" id="grades-count">
                                <?= $studentsCount > 0 ? "✓ Izglītojamie: {$studentsCount}" : "— Nav ielādētu izglītojamo" ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-header">
                        <h3>Periods un budžets</h3><span class="section-tag">CONFIG</span>
                    </div>
                    <div class="section-body">
                        <form id="form-calculate">
                            <div class="flex-row mb1">
                                <button type="button" class="btn-ghost" onclick="setSemester(1)">1. semestris
                                    (sep–dec)</button>
                                <button type="button" class="btn-ghost" onclick="setSemester(2)">2. semestris
                                    (jan–jūn)</button>
                            </div>
                            <div class="semester-label" id="semester-label"></div>
                            <div class="form-row mt2">
                                <div>
                                    <label for="period_start">Sākuma datums</label>
                                    <input type="date" id="period_start" name="period_start"
                                        value="<?= $latestConfig ? date('Y-m-d', strtotime($latestConfig['period_start'])) : '2025-09-01' ?>"
                                        required>
                                </div>
                                <div>
                                    <label for="period_end">Beigu datums</label>
                                    <input type="date" id="period_end" name="period_end"
                                        value="<?= $latestConfig ? date('Y-m-d', strtotime($latestConfig['period_end'])) : '2025-12-31' ?>"
                                        required>
                                </div>
                                <div>
                                    <label for="monthly_budget">Mēneša budžets (EUR)</label>
                                    <input type="number" id="monthly_budget" name="monthly_budget" step="0.01" min="0"
                                        value="<?= $latestConfig ? $latestConfig['monthly_budget'] : '' ?>" required>
                                </div>
                            </div>
                            <h3 class="mt2">Stipendijas robežas</h3>
                            <div class="table-wrap mb1">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Vērtējuma diapazons</th>
                                            <th>Stipendija (EUR)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="brackets-tbody"></tbody>
                                </table>
                            </div>
                            <div class="flex-row">
                                <button type="submit">Aprēķināt stipendijas</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ═══ TAB 2: RESULTS ═══ -->
            <div id="tab-results" class="tab-content">
                <div class="page-title">02 / Rezultāti</div>
                <h2>Aprēķina rezultāti</h2>

                <div id="results-summary-container" class="hidden">
                    <div class="summary-strip mb1">
                        <div class="summary-cell">
                            <div class="summary-cell-label">Kopējā summa</div>
                            <div class="summary-cell-value" id="total-payout">—</div>
                        </div>
                        <div class="summary-cell">
                            <div class="summary-cell-label">Apstiprināts budžets</div>
                            <div class="summary-cell-value" id="budget-amount">—</div>
                        </div>
                        <div class="summary-cell">
                            <div class="summary-cell-label">Starpība</div>
                            <div class="summary-cell-value" id="budget-diff">—</div>
                        </div>
                    </div>
                </div>

                <div class="flex-row mb1">
                    <button onclick="exportResults()" id="btn-export" class="hidden">Eksportēt Excel</button>
                    <button onclick="window.print()" id="btn-print" class="btn-ghost hidden">Drukāt / PDF</button>
                </div>

                <div class="table-wrap">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Uzvārds</th>
                                <th>Vārds</th>
                                <th>Personas kods</th>
                                <th>Grupa</th>
                                <th>Vid. vērtējums</th>
                                <th>Stipendija (EUR)</th>
                            </tr>
                        </thead>
                        <tbody id="results-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- ═══ TAB 3: GROUPS ═══ -->
            <div id="tab-groups" class="tab-content">
                <div class="page-title">03 / Grupu skats</div>
                <h2>Grupu skats</h2>
                <div class="form-group" style="max-width:280px;">
                    <label for="group-select">Izvēlieties grupu</label>
                    <select id="group-select" onchange="showGroupView()">
                        <option value="">— Izvēlieties —</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= htmlspecialchars($group) ?>"><?= htmlspecialchars($group) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="group-view-container" class="hidden">
                    <div class="table-wrap mt2">
                        <table class="group-table" id="group-view-table">
                            <thead>
                                <tr id="group-header-row"></tr>
                            </thead>
                            <tbody id="group-tbody"></tbody>
                        </table>
                    </div>
                    <div class="flex-row mt2">
                        <button onclick="recalculateForGroup()">Pārrēķināt</button>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <footer>&copy; 2026 Draugiem Group &nbsp;/&nbsp; Stipendiju aprēķina sistēma v1.0</footer>

    <script>
        const DEFAULT_BRACKETS = [
            { from: 4.0, to: 5.0, amount: 50 }, { from: 5.0, to: 6.0, amount: 75 },
            { from: 6.0, to: 7.0, amount: 100 }, { from: 7.0, to: 8.0, amount: 125 },
            { from: 8.0, to: 9.0, amount: 150 }, { from: 9.0, to: 10.0, amount: 200 }
        ];
        let currentConfigId = <?= ($hasResults && $latestConfig) ? $latestConfig['id'] : 'null' ?>;
        let resultsData = [];

        // ── SIDEBAR TOGGLE (mobile) ──
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
        document.addEventListener('click', e => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !e.target.closest('.hamburger')) {
                sidebar.classList.remove('open');
            }
        });

        // ── TAB SWITCHING ──
        function switchTab(tabName, btn) {
            // Deactivate all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            // Remove active from visible nav items only
            document.querySelectorAll('.nav-item:not(.nav-hidden)').forEach(el => el.classList.remove('active'));
            // Activate target tab
            document.getElementById('tab-' + tabName).classList.add('active');
            if (btn) btn.classList.add('active');
            document.getElementById('sidebar').classList.remove('open');
            if (tabName === 'results' && currentConfigId) loadResults();
            if (tabName === 'groups' && currentConfigId) initGroupView();
        }

        // ── BRACKETS ──
        function initBrackets() {
            const tbody = document.getElementById('brackets-tbody');
            tbody.innerHTML = '';
            DEFAULT_BRACKETS.forEach(b => addBracketRow(b.from, b.to, b.amount));
        }
        function addBracketRow(from, to, amount) {
            const tbody = document.getElementById('brackets-tbody');
            const n = tbody.querySelectorAll('tr').length;
            const row = document.createElement('tr');
            row.innerHTML = `
            <td style="font-size:0.82rem;">${parseFloat(from).toFixed(1)} – ${parseFloat(to).toFixed(1)}</td>
            <td>
                <input type="hidden" name="brackets[${n}][from]" value="${from}">
                <input type="hidden" name="brackets[${n}][to]" value="${to}">
                <input type="number" name="brackets[${n}][amount]" value="${amount || 0}" step="0.01" min="0" placeholder="EUR">
            </td>`;
            tbody.appendChild(row);
        }

        // ── UPLOADS ──
        document.getElementById('form-subjects').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'upload_subjects');
            fd.append('subjects_file', document.getElementById('subjects_file').files[0]);
            const status = document.getElementById('subjects-status');
            const count = document.getElementById('subjects-count');
            status.innerHTML = '<span class="spinner"></span>';
            try {
                const d = await (await fetch('import.php', { method: 'POST', body: fd })).json();
                if (d.success) {
                    status.innerHTML = '';
                    if (d.count === 0) {
                        count.textContent = '⚠ Nav derīgu rīndņu — pārbaudiet kolonnas (A: nosaukums, B: VIMP/PROF)';
                        count.className = 'status-bar mt1 err';
                    } else {
                        count.textContent = `✓ Priekšmeti: ${d.count}`;
                        count.className = 'status-bar mt1 ok';
                    }
                    document.getElementById('form-subjects').reset();
                    document.getElementById('drop-subjects').classList.remove('has-file');
                    document.getElementById('subjects-filename').textContent = '';
                } else { status.innerHTML = `<div class="status-bar err mt1">✗ ${d.message}</div>`; }
            } catch (err) { status.innerHTML = `<div class="status-bar err mt1">✗ ${err.message}</div>`; }
        });

        document.getElementById('form-grades').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'upload_grades');
            fd.append('grades_file', document.getElementById('grades_file').files[0]);
            const status = document.getElementById('grades-status');
            const count = document.getElementById('grades-count');
            status.innerHTML = '<span class="spinner"></span>';
            try {
                const d = await (await fetch('import.php', { method: 'POST', body: fd })).json();
                if (d.success) {
                    status.innerHTML = '';
                    count.textContent = `✓ Izglītojamie: ${d.students}, vērtējumi: ${d.grades}`;
                    count.className = 'status-bar mt1 ok';
                    document.getElementById('form-grades').reset();
                    document.getElementById('drop-grades').classList.remove('has-file');
                    document.getElementById('grades-filename').textContent = '';
                } else { status.innerHTML = `<div class="status-bar err mt1">✗ ${d.message}</div>`; }
            } catch (err) { status.innerHTML = `<div class="status-bar err mt1">✗ ${err.message}</div>`; }
        });

        // ── CALCULATE ──
        document.getElementById('form-calculate').addEventListener('submit', async e => {
            e.preventDefault();
            const btn = document.querySelector('#form-calculate button[type="submit"]');
            const origText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Aprēķina...';
            const fd = new FormData(document.getElementById('form-calculate'));
            fd.append('action', 'calculate');
            try {
                const d = await (await fetch('import.php', { method: 'POST', body: fd })).json();
                if (d.success) {
                    currentConfigId = d.configId;
                    resultsData = [];

                    document.getElementById('btn-results').classList.remove('nav-hidden');
                    document.getElementById('btn-groups').classList.remove('nav-hidden');
                    document.getElementById('btn-export').classList.remove('hidden');
                    document.getElementById('btn-print').classList.remove('hidden');

                    // ENABLE CLEAR BUTTON WITHOUT REFRESH
                    document.getElementById('btn-clear-config').disabled = false;

                    switchTab('results', document.getElementById('btn-results'));
                    await loadResults();

                    showToast('Stipendijas veiksmīgi aprēķinātas!', 'ok');
                } else {
                    showToast('Kļūda: ' + d.message, 'err');
                }
            } catch (err) {
                showToast('Kļūda: ' + err.message, 'err');
            } finally {
                btn.disabled = false;
                btn.textContent = origText;
            }
        });

        // ── RESULTS ──
        async function loadResults() {
            if (!currentConfigId) return;
            try {
                const d = await (await fetch(`api.php?config_id=${currentConfigId}`)).json();
                if (!d.success) return;
                resultsData = d.results || [];
                displayResults(resultsData, d.totalBudget || 0, d.totalPayout || 0);
            } catch (e) { /* silent fail on result load */ }
        }

        function reasonClass(r) {
            const amt = parseFloat(r.scholarship_amount);
            if (amt === 0) return 'none';
            if (amt === 15) return 'warn';
            return '';
        }

        function displayResults(results, totalBudget, totalPayout) {
            const tbody = document.getElementById('results-tbody');
            tbody.innerHTML = '';
            const LABELS = ['Uzvārds', 'Vārds', 'Personas kods', 'Grupa', 'Vid. vērtējums', 'Stipendija (EUR)'];
            results.forEach(r => {
                const avg = r.average_grade !== null ? parseFloat(r.average_grade).toFixed(2) : '—';
                const amt = parseFloat(r.scholarship_amount).toFixed(2);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td data-label="${LABELS[0]}">${esc(r.last_name)}</td>
                <td data-label="${LABELS[1]}">${esc(r.first_name)}</td>
                <td data-label="${LABELS[2]}">${esc(r.personal_code)}</td>
                <td data-label="${LABELS[3]}">${esc(r.group_name)}</td>
                <td data-label="${LABELS[4]}" class="num">${avg}</td>
                <td data-label="${LABELS[5]}" class="num em">${amt}</td>`;
                tbody.appendChild(tr);

                // Reason row — only when not a normal bracket result
                if (r.scholarship_reason) {
                    const cls = reasonClass(r);
                    if (cls) {
                        const rr = document.createElement('tr');
                        rr.className = `reason-row ${cls}`;
                        rr.innerHTML = `<td colspan="6">${esc(r.scholarship_reason)}</td>`;
                        tbody.appendChild(rr);
                    }
                }
            });

            const diff = totalBudget - totalPayout;
            document.getElementById('total-payout').textContent = totalPayout.toFixed(2) + ' EUR';
            document.getElementById('budget-amount').textContent = totalBudget.toFixed(2) + ' EUR';
            const diffEl = document.getElementById('budget-diff');
            diffEl.textContent = Math.abs(diff).toFixed(2) + ' EUR (' + (diff >= 0 ? 'ietaupījums' : 'pārsniedz budžetu') + ')';
            diffEl.className = 'summary-cell-value ' + (diff >= 0 ? 'positive' : 'negative');
            document.getElementById('results-summary-container').classList.remove('hidden');
        }

        function exportResults() {
            if (!currentConfigId) { alert('Nav aprēķinu datu'); return; }
            window.location.href = `export.php?config_id=${currentConfigId}`;
        }

        // ── GROUP VIEW ──
        function initGroupView() {
            const sel = document.getElementById('group-select');
            if (sel.value) showGroupView();
        }
        async function showGroupView() {
            if (!currentConfigId) return;
            const groupName = document.getElementById('group-select').value;
            const container = document.getElementById('group-view-container');
            if (!groupName) { container.classList.add('hidden'); return; }
            container.classList.add('hidden');
            const fd = new FormData();
            fd.append('action', 'get_group_data');
            fd.append('group_name', groupName);
            fd.append('config_id', currentConfigId);
            try {
                const d = await (await fetch('import.php', { method: 'POST', body: fd })).json();
                if (!d.success) { alert('Kļūda: ' + d.message); return; }
                renderGroupTable(d.students, d.subjects);
                container.classList.remove('hidden');
            } catch (err) { alert('Kļūda: ' + err.message); }
        }
        function renderGroupTable(students, subjects) {
            const headerRow = document.getElementById('group-header-row');
            const tbody = document.getElementById('group-tbody');
            headerRow.innerHTML = '<th>Izglītojamais</th>';
            subjects.forEach(s => {
                const th = document.createElement('th');
                th.textContent = s;
                th.style.cssText = 'font-size:0.6rem;max-width:100px;word-break:break-word;';
                headerRow.appendChild(th);
            });
            headerRow.innerHTML += '<th>Vidējais</th><th>Stipendija</th>';
            tbody.innerHTML = '';
            students.forEach(student => {
                const tr = document.createElement('tr');
                let cells = `<td><strong>${esc(student.last_name)}</strong> ${esc(student.first_name)}</td>`;
                subjects.forEach(subj => {
                    const g = student.grades[subj];
                    if (g) {
                        cells += `<td style="white-space:nowrap;">${esc(g.display)}<br>
                        <label style="font-size:0.62rem;font-family:var(--font-mono);color:var(--gray-400);cursor:pointer;">
                            <input type="checkbox" ${g.excluded ? 'checked' : ''}
                                onchange="toggleExclusion(${student.id}, ${g.subject_id || 0}, this.checked)"
                                ${!g.subject_id ? 'disabled' : ''}> izsl.
                        </label></td>`;
                    } else {
                        cells += '<td style="color:var(--gray-400);">—</td>';
                    }
                });
                const avg = student.average !== null ? parseFloat(student.average).toFixed(2) : '—';
                cells += `<td><strong>${avg}</strong></td><td>${parseFloat(student.scholarship || 0).toFixed(2)}</td>`;
                tr.innerHTML = cells;
                tbody.appendChild(tr);
            });
        }
        async function toggleExclusion(studentId, subjectId, exclude) {
            if (!subjectId) return;
            const fd = new FormData();
            fd.append('action', 'toggle_exclusion');
            fd.append('config_id', currentConfigId);
            fd.append('student_id', studentId);
            fd.append('subject_id', subjectId);
            fd.append('exclude', exclude ? 'true' : 'false');
            await fetch('import.php', { method: 'POST', body: fd });
        }
        async function recalculateForGroup() {
            const btn = event.target; btn.disabled = true; btn.textContent = 'Aprēķina...';
            try {
                const fd = new FormData();
                fd.append('action', 'recalculate');
                fd.append('config_id', currentConfigId);
                const d = await (await fetch('import.php', { method: 'POST', body: fd })).json();
                if (d.success) { resultsData = []; await showGroupView(); }
                else { alert('Kļūda: ' + d.message); }
            } catch (err) { alert('Kļūda: ' + err.message); }
            finally { btn.disabled = false; btn.textContent = 'Pārrēķināt'; }
        }

        // ── SEMESTER ──
        function setSemester(sem) {
            const today = new Date();
            const ay = today.getMonth() >= 8 ? today.getFullYear() : today.getFullYear() - 1;
            document.getElementById('period_start').value = sem === 1 ? `${ay}-09-01` : `${ay + 1}-01-05`;
            document.getElementById('period_end').value = sem === 1 ? `${ay}-12-31` : `${ay + 1}-06-30`;
            updateSemesterLabel();
        }
        function updateSemesterLabel() {
            const start = new Date(document.getElementById('period_start').value);
            const end = new Date(document.getElementById('period_end').value);
            const label = document.getElementById('semester-label');
            if (!label || isNaN(start) || isNaN(end)) return;
            const sm = start.getMonth() + 1, em = end.getMonth() + 1, sy = start.getFullYear(), ey = end.getFullYear();
            let text;
            if (sm >= 9 && em <= 12 && sy === ey) text = `1. semestris — ${sy}./${sy + 1}. mācību gads`;
            else if (sm <= 6 && em <= 6 && sy === ey) text = `2. semestris — ${sy - 1}./${sy}. mācību gads`;
            else text = `Periods: ${sy}-${String(sm).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')} līdz ${ey}-${String(em).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
            label.textContent = text;
        }
        document.getElementById('period_start').addEventListener('change', updateSemesterLabel);
        document.getElementById('period_end').addEventListener('change', updateSemesterLabel);

        // ── CLEAR CONFIG ──
        async function clearConfig() {
            if (!confirm('Vai tiešām vēlaties dzēst visus datus? Tiks dzēsti: priekšmeti, izglītojamie, vērtējumi un aprēķinu rezultāti.')) return;
            const btn = document.getElementById('btn-clear-config');
            btn.disabled = true;
            btn.textContent = 'Notīra...';
            try {
                const fd = new FormData();
                fd.append('action', 'clear_all');
                const d = await (await fetch('import.php', { method: 'POST', body: fd })).json();
                if (d.success) {
                    currentConfigId = null;
                    resultsData = [];
                    // Hide nav items and buttons
                    document.getElementById('btn-results').classList.add('nav-hidden');
                    document.getElementById('btn-groups').classList.add('nav-hidden');
                    document.getElementById('btn-export').classList.add('hidden');
                    document.getElementById('btn-print').classList.add('hidden');
                    // Reset status bars
                    document.getElementById('subjects-count').textContent = '— Nav ielādētu priekšmetu';
                    document.getElementById('subjects-count').className = 'status-bar mt1';
                    document.getElementById('grades-count').textContent = '— Nav ielādētu izglītojamo';
                    document.getElementById('grades-count').className = 'status-bar mt1';
                    // Clear results table
                    document.getElementById('results-tbody').innerHTML = '';
                    document.getElementById('results-summary-container').classList.add('hidden');
                    // Clear results table and summary fully
                    document.getElementById('results-tbody').innerHTML = '';
                    document.getElementById('results-summary-container').classList.add('hidden');
                    // Reset group select
                    const gsel = document.getElementById('group-select');
                    if (gsel) gsel.selectedIndex = 0;
                    document.getElementById('group-view-container').classList.add('hidden');
                    // Switch back to settings
                    switchTab('settings', document.querySelector('.nav-item:not(.nav-hidden)'));
                    btn.disabled = true;
                    btn.textContent = 'Notīrīt visus datus';
                    showToast('Visi dati veiksmīgi dzēsti.', 'ok');
                } else {
                    showToast('Kłūda: ' + d.message, 'err');
                    btn.disabled = false;
                    btn.textContent = 'Notīrīt visus datus';
                }
            } catch (err) {
                showToast('Kłūda: ' + err.message, 'err');
                btn.disabled = false;
                btn.textContent = 'Notīrīt visus datus';
            }
        }

        // ── TOAST ──
        function showToast(msg, type) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast ' + (type || '');
            void t.offsetWidth;
            t.classList.add('show');
            clearTimeout(t._timer);
            t._timer = setTimeout(() => { t.classList.remove('show'); }, 3500);
        }

        function esc(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ── DROP ZONES ──
        function initDropZone(zoneId, inputId, filenameId) {
            const zone = document.getElementById(zoneId);
            const input = document.getElementById(inputId);
            const fnEl = document.getElementById(filenameId);

            function setFile(file) {
                if (!file) return;
                const ok = /\.(xlsx|xls)$/i.test(file.name);
                if (!ok) { zone.classList.remove('has-file'); fnEl.textContent = '✗ Tikai .xlsx vai .xls'; fnEl.style.color = 'var(--accent-red)'; return; }
                // Inject into input via DataTransfer
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                zone.classList.add('has-file');
                fnEl.textContent = '✓ ' + file.name;
                fnEl.style.color = '';
            }

            input.addEventListener('change', () => { if (input.files[0]) setFile(input.files[0]); });

            zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
            zone.addEventListener('dragleave', e => { if (!zone.contains(e.relatedTarget)) zone.classList.remove('drag-over'); });
            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                const file = e.dataTransfer.files[0];
                setFile(file);
            });
        }

        window.addEventListener('load', () => {
            initBrackets();
            initDropZone('drop-subjects', 'subjects_file', 'subjects-filename');
            initDropZone('drop-grades', 'grades_file', 'grades-filename');
            updateSemesterLabel();
            if (currentConfigId) {
                loadResults();
                document.getElementById('btn-results').classList.remove('nav-hidden');
                document.getElementById('btn-groups').classList.remove('nav-hidden');
                document.getElementById('btn-export').classList.remove('hidden');
                document.getElementById('btn-print').classList.remove('hidden');
            }
        });
    </script>

    <div id="toast" class="toast"></div>
</body>

</html>