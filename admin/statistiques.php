<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_admin();

// Auto-create visites table on first use
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS visites (
        id_visite   INT AUTO_INCREMENT PRIMARY KEY,
        date_visite DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_hash     VARCHAR(64)  NOT NULL,
        page        VARCHAR(500)          DEFAULT '/',
        referrer    VARCHAR(500)          DEFAULT '',
        INDEX idx_date (date_visite),
        INDEX idx_ip   (ip_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) { /* table exists or no DDL right — continue */ }

// ── Visitor stats ────────────────────────────────────────────────────
$visitsToday  = (int) $pdo->query("SELECT COUNT(*)               FROM visites WHERE DATE(date_visite) = CURDATE()")->fetchColumn();
$visitsWeek   = (int) $pdo->query("SELECT COUNT(*)               FROM visites WHERE date_visite >= DATE_SUB(NOW(), INTERVAL 7  DAY)")->fetchColumn();
$visitsMonth  = (int) $pdo->query("SELECT COUNT(*)               FROM visites WHERE date_visite >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$uniqueToday  = (int) $pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visites WHERE DATE(date_visite) = CURDATE()")->fetchColumn();
$uniqueWeek   = (int) $pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visites WHERE date_visite >= DATE_SUB(NOW(), INTERVAL 7  DAY)")->fetchColumn();
$uniqueMonth  = (int) $pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visites WHERE date_visite >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Visits per day – last 30 days
$rawDays = $pdo->query("
    SELECT DATE(date_visite) AS jour,
           COUNT(*)               AS total,
           COUNT(DISTINCT ip_hash) AS uniques
    FROM visites
    WHERE date_visite >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(date_visite)
")->fetchAll();
$dayMap = [];
foreach ($rawDays as $r) { $dayMap[$r['jour']] = $r; }

$chartDays = $chartVisits = $chartUniq = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartDays[]   = date('d/m', strtotime($d));
    $chartVisits[] = (int) ($dayMap[$d]['total']   ?? 0);
    $chartUniq[]   = (int) ($dayMap[$d]['uniques'] ?? 0);
}

// Visits by hour today
$rawHours = $pdo->query("
    SELECT HOUR(date_visite) AS h, COUNT(*) AS n
    FROM visites WHERE DATE(date_visite) = CURDATE()
    GROUP BY h
")->fetchAll();
$hourMap = array_column($rawHours, 'n', 'h');
$chartHourLabels = $chartHourData = [];
for ($h = 0; $h <= 23; $h++) {
    $chartHourLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . 'h';
    $chartHourData[]   = (int) ($hourMap[$h] ?? 0);
}

// ── Demandes stats ───────────────────────────────────────────────────
$totalDemandes   = (int) $pdo->query("SELECT COUNT(*) FROM demandes")->fetchColumn();
$nbEtudiants     = (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE type_demande = 'etudiant'")->fetchColumn();
$nbPartenaires   = (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE type_demande = 'partenaire'")->fetchColumn();
$nbAcceptees     = (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'acceptee'")->fetchColumn();
$nbEnAttente     = (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'en_attente'")->fetchColumn();
$nbRejetees      = (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'rejetee'")->fetchColumn();
$demandesMonth   = (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE date_demande >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Demandes per month (last 6 months), split by type
$rawMonths = $pdo->query("
    SELECT DATE_FORMAT(date_demande, '%Y-%m') AS m,
           SUM(type_demande = 'etudiant')   AS etudiants,
           SUM(type_demande = 'partenaire') AS partenaires
    FROM demandes
    WHERE date_demande >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY m ORDER BY m
")->fetchAll();
$monthMap = [];
foreach ($rawMonths as $r) { $monthMap[$r['m']] = $r; }

$chartMonthLabels = $chartMonthEtu = $chartMonthPart = [];
for ($i = 5; $i >= 0; $i--) {
    $mk = date('Y-m', strtotime("-{$i} months"));
    $chartMonthLabels[] = date('M Y', strtotime($mk . '-01'));
    $chartMonthEtu[]    = (int) ($monthMap[$mk]['etudiants']   ?? 0);
    $chartMonthPart[]   = (int) ($monthMap[$mk]['partenaires'] ?? 0);
}

// ── RDV stats ────────────────────────────────────────────────────────
$totalRdv    = (int) $pdo->query("SELECT COUNT(*) FROM rendez_vous")->fetchColumn();
$rdvProg     = (int) $pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut = 'programme'")->fetchColumn();
$rdvEff      = (int) $pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut = 'effectue'")->fetchColumn();
$rdvAnn      = (int) $pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut = 'annule'")->fetchColumn();

$tauxAccept  = $totalDemandes > 0 ? round($nbAcceptees / $totalDemandes * 100) : 0;

// JSON data for Chart.js
$json = fn($v) => json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP);

render_admin_header($pdo, 'Statistiques', 'statistiques');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js">
<style>
.section-title {
    align-items: center;
    color: var(--muted);
    display: flex;
    font-size: 0.7rem;
    font-weight: 600;
    gap: 10px;
    letter-spacing: 0.1em;
    margin-bottom: 14px;
    text-transform: uppercase;
}
.section-title::after {
    background: var(--border);
    content: '';
    flex: 1;
    height: 1px;
}
</style>

<!-- ── KPI Visits ───────────────────────────────────────────── -->
<p class="section-title"><i class="fas fa-eye me-1"></i>Visites du site</p>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card-stat">
            <div class="stat-label">Aujourd'hui</div>
            <div class="stat-value"><?= number_format($visitsToday) ?></div>
            <div class="stat-sub"><?= number_format($uniqueToday) ?> visiteurs uniques</div>
            <i class="fas fa-eye stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card-stat">
            <div class="stat-label">Cette semaine</div>
            <div class="stat-value"><?= number_format($visitsWeek) ?></div>
            <div class="stat-sub"><?= number_format($uniqueWeek) ?> visiteurs uniques</div>
            <i class="fas fa-chart-line stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card-stat">
            <div class="stat-label">Ce mois</div>
            <div class="stat-value"><?= number_format($visitsMonth) ?></div>
            <div class="stat-sub"><?= number_format($uniqueMonth) ?> visiteurs uniques</div>
            <i class="fas fa-calendar-alt stat-icon"></i>
        </div>
    </div>
</div>

<!-- ── KPI Demandes ─────────────────────────────────────────── -->
<p class="section-title"><i class="fas fa-file-alt me-1"></i>Demandes &amp; rendez-vous</p>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Total demandes</div>
            <div class="stat-value"><?= number_format($totalDemandes) ?></div>
            <div class="stat-sub"><?= number_format($demandesMonth) ?> ce mois</div>
            <i class="fas fa-file-alt stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Etudiants</div>
            <div class="stat-value"><?= number_format($nbEtudiants) ?></div>
            <div class="stat-sub"><?= $totalDemandes > 0 ? round($nbEtudiants / $totalDemandes * 100) : 0 ?>% du total</div>
            <i class="fas fa-user-graduate stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Partenaires</div>
            <div class="stat-value"><?= number_format($nbPartenaires) ?></div>
            <div class="stat-sub"><?= $totalDemandes > 0 ? round($nbPartenaires / $totalDemandes * 100) : 0 ?>% du total</div>
            <i class="fas fa-handshake stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Taux d'acceptation</div>
            <div class="stat-value"><?= $tauxAccept ?>%</div>
            <div class="stat-sub"><?= number_format($nbAcceptees) ?> acceptees / <?= number_format($totalRdv) ?> RDV</div>
            <i class="fas fa-check-circle stat-icon"></i>
        </div>
    </div>
</div>

<!-- ── Row 1: Visit trend + Demandes type ──────────────────── -->
<div class="row g-4 mb-4">

    <div class="col-xl-8">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3><i class="fas fa-chart-area me-2" style="color:var(--cyan);font-size:.8rem"></i>Visites — 30 derniers jours</h3>
                <span class="chart-pill">Total &amp; Uniques</span>
            </div>
            <div class="chart-body">
                <div class="chart-wrap h-300">
                    <canvas id="chartVisits"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3><i class="fas fa-chart-pie me-2" style="color:var(--cyan);font-size:.8rem"></i>Demandes par type</h3>
                <span class="chart-pill"><?= number_format($totalDemandes) ?> total</span>
            </div>
            <div class="chart-body">
                <div class="chart-wrap h-240 donut-wrap">
                    <canvas id="chartType"></canvas>
                    <div class="donut-center">
                        <div class="dc-val"><?= number_format($totalDemandes) ?></div>
                        <div class="dc-label">demandes</div>
                    </div>
                </div>
                <!-- legend -->
                <div class="d-flex gap-3 justify-content-center mt-3">
                    <div class="d-flex align-items-center gap-2" style="font-size:.8rem">
                        <span style="width:10px;height:10px;border-radius:50%;background:#00e5ff;display:inline-block"></span>
                        <span style="color:var(--muted-light)">Etudiants <strong style="color:var(--white)"><?= $nbEtudiants ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="font-size:.8rem">
                        <span style="width:10px;height:10px;border-radius:50%;background:rgba(0,229,255,.35);display:inline-block"></span>
                        <span style="color:var(--muted-light)">Partenaires <strong style="color:var(--white)"><?= $nbPartenaires ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 2: Monthly bar + RDV donut ──────────────────────── -->
<div class="row g-4 mb-4">

    <div class="col-xl-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3><i class="fas fa-chart-bar me-2" style="color:var(--cyan);font-size:.8rem"></i>Demandes par mois</h3>
                <span class="chart-pill">6 derniers mois</span>
            </div>
            <div class="chart-body">
                <div class="chart-wrap h-260">
                    <canvas id="chartMonths"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3><i class="fas fa-calendar-check me-2" style="color:var(--cyan);font-size:.8rem"></i>Statut des demandes</h3>
                <span class="chart-pill"><?= number_format($totalDemandes) ?> total</span>
            </div>
            <div class="chart-body">
                <div class="chart-wrap h-200 donut-wrap">
                    <canvas id="chartStatut"></canvas>
                    <div class="donut-center">
                        <div class="dc-val"><?= $tauxAccept ?>%</div>
                        <div class="dc-label">acceptees</div>
                    </div>
                </div>
                <div class="d-flex gap-3 justify-content-center flex-wrap mt-3">
                    <div class="d-flex align-items-center gap-2" style="font-size:.78rem">
                        <span style="width:9px;height:9px;border-radius:2px;background:#fde68a;display:inline-block"></span>
                        <span style="color:var(--muted-light)">En attente <strong style="color:var(--white)"><?= $nbEnAttente ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="font-size:.78rem">
                        <span style="width:9px;height:9px;border-radius:2px;background:#86efac;display:inline-block"></span>
                        <span style="color:var(--muted-light)">Acceptees <strong style="color:var(--white)"><?= $nbAcceptees ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="font-size:.78rem">
                        <span style="width:9px;height:9px;border-radius:2px;background:#fca5a5;display:inline-block"></span>
                        <span style="color:var(--muted-light)">Rejetees <strong style="color:var(--white)"><?= $nbRejetees ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 3: Hourly today + RDV donut ─────────────────────── -->
<div class="row g-4 mb-4">

    <div class="col-xl-8">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3><i class="fas fa-clock me-2" style="color:var(--cyan);font-size:.8rem"></i>Visites par heure — aujourd'hui</h3>
                <span class="chart-pill"><?= number_format($visitsToday) ?> visites</span>
            </div>
            <div class="chart-body">
                <div class="chart-wrap h-240">
                    <canvas id="chartHours"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3><i class="fas fa-calendar-alt me-2" style="color:var(--cyan);font-size:.8rem"></i>Rendez-vous</h3>
                <span class="chart-pill"><?= number_format($totalRdv) ?> total</span>
            </div>
            <div class="chart-body">
                <div class="chart-wrap h-200 donut-wrap">
                    <canvas id="chartRdv"></canvas>
                    <div class="donut-center">
                        <div class="dc-val"><?= number_format($totalRdv) ?></div>
                        <div class="dc-label">total RDV</div>
                    </div>
                </div>
                <div class="d-flex gap-3 justify-content-center flex-wrap mt-3">
                    <div class="d-flex align-items-center gap-2" style="font-size:.78rem">
                        <span style="width:9px;height:9px;border-radius:2px;background:#00e5ff;display:inline-block"></span>
                        <span style="color:var(--muted-light)">Programmes <strong style="color:var(--white)"><?= $rdvProg ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="font-size:.78rem">
                        <span style="width:9px;height:9px;border-radius:2px;background:#86efac;display:inline-block"></span>
                        <span style="color:var(--muted-light)">Effectues <strong style="color:var(--white)"><?= $rdvEff ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="font-size:.78rem">
                        <span style="width:9px;height:9px;border-radius:2px;background:#64748b;display:inline-block"></span>
                        <span style="color:var(--muted-light)">Annules <strong style="color:var(--white)"><?= $rdvAnn ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Chart.js ─────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Global defaults ──────────────────────────────────────────
Chart.defaults.color          = '#64748b';
Chart.defaults.borderColor    = 'rgba(0,229,255,0.07)';
Chart.defaults.font.family    = 'Inter, system-ui, sans-serif';
Chart.defaults.font.size      = 11;

const CYAN    = '#00e5ff';
const CYAN_DIM = 'rgba(0,229,255,0.15)';
const GREEN   = '#86efac';
const YELLOW  = '#fde68a';
const RED     = '#fca5a5';
const SLATE   = '#475569';

const tooltip = {
    backgroundColor : '#161b22',
    borderColor     : 'rgba(0,229,255,0.2)',
    borderWidth     : 1,
    titleColor      : '#e2e8f0',
    bodyColor       : '#94a3b8',
    padding         : 12,
    cornerRadius    : 8,
};

const scalesXY = {
    x: { grid: { color: 'rgba(0,229,255,0.06)' }, ticks: { color: '#475569' } },
    y: { grid: { color: 'rgba(0,229,255,0.06)' }, ticks: { color: '#475569' }, beginAtZero: true },
};

// ── Data from PHP ────────────────────────────────────────────
const chartDays      = <?= $json($chartDays) ?>;
const chartVisits    = <?= $json($chartVisits) ?>;
const chartUniq      = <?= $json($chartUniq) ?>;
const chartHourLbls  = <?= $json($chartHourLabels) ?>;
const chartHourData  = <?= $json($chartHourData) ?>;
const chartMonthLbls = <?= $json($chartMonthLabels) ?>;
const chartMonthEtu  = <?= $json($chartMonthEtu) ?>;
const chartMonthPart = <?= $json($chartMonthPart) ?>;

// ── 1. Visits 30 days (line) ──────────────────────────────────
new Chart(document.getElementById('chartVisits'), {
    type: 'line',
    data: {
        labels: chartDays,
        datasets: [
            {
                label          : 'Visites totales',
                data           : chartVisits,
                borderColor    : CYAN,
                backgroundColor: 'rgba(0,229,255,0.07)',
                fill           : true,
                tension        : 0.4,
                pointRadius    : 3,
                pointHoverRadius: 6,
                pointBackgroundColor: CYAN,
                borderWidth    : 2,
            },
            {
                label          : 'Visiteurs uniques',
                data           : chartUniq,
                borderColor    : 'rgba(0,229,255,0.4)',
                backgroundColor: 'transparent',
                fill           : false,
                tension        : 0.4,
                pointRadius    : 2,
                pointHoverRadius: 5,
                borderDash     : [6, 4],
                borderWidth    : 1.5,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { labels: { color: '#94a3b8', boxWidth: 12 } }, tooltip },
        scales: scalesXY,
    },
});

// ── 2. Demandes by type (doughnut) ─────────────────────────────
new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: {
        labels  : ['Etudiants', 'Partenaires'],
        datasets: [{
            data           : [<?= (int)$nbEtudiants ?>, <?= (int)$nbPartenaires ?>],
            backgroundColor: [CYAN, 'rgba(0,229,255,0.3)'],
            borderColor    : '#111418',
            borderWidth    : 4,
            hoverBorderColor: CYAN,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: { ...tooltip, callbacks: {
                label: ctx => ` ${ctx.label}: ${ctx.parsed} demandes`
            }},
        },
    },
});

// ── 3. Monthly demandes (grouped bar) ──────────────────────────
new Chart(document.getElementById('chartMonths'), {
    type: 'bar',
    data: {
        labels  : chartMonthLbls,
        datasets: [
            {
                label          : 'Etudiants',
                data           : chartMonthEtu,
                backgroundColor: 'rgba(0,229,255,0.7)',
                borderRadius   : 5,
                borderSkipped  : false,
            },
            {
                label          : 'Partenaires',
                data           : chartMonthPart,
                backgroundColor: 'rgba(0,229,255,0.25)',
                borderRadius   : 5,
                borderSkipped  : false,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { labels: { color: '#94a3b8', boxWidth: 12 } }, tooltip },
        scales: scalesXY,
    },
});

// ── 4. Demandes by status (doughnut) ───────────────────────────
new Chart(document.getElementById('chartStatut'), {
    type: 'doughnut',
    data: {
        labels  : ['En attente', 'Acceptees', 'Rejetees'],
        datasets: [{
            data           : [<?= (int)$nbEnAttente ?>, <?= (int)$nbAcceptees ?>, <?= (int)$nbRejetees ?>],
            backgroundColor: [YELLOW, GREEN, RED],
            borderColor    : '#111418',
            borderWidth    : 4,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip,
        },
    },
});

// ── 5. Hourly visits today (bar) ───────────────────────────────
new Chart(document.getElementById('chartHours'), {
    type: 'bar',
    data: {
        labels  : chartHourLbls,
        datasets: [{
            label          : 'Visites',
            data           : chartHourData,
            backgroundColor: chartHourData.map((v, i) =>
                v === Math.max(...chartHourData) && v > 0 ? CYAN : CYAN_DIM
            ),
            borderRadius   : 4,
            borderSkipped  : false,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip },
        scales: {
            ...scalesXY,
            x: { ...scalesXY.x, ticks: { color: '#475569', maxRotation: 0 } },
        },
    },
});

// ── 6. RDV by status (doughnut) ────────────────────────────────
new Chart(document.getElementById('chartRdv'), {
    type: 'doughnut',
    data: {
        labels  : ['Programmes', 'Effectues', 'Annules'],
        datasets: [{
            data           : [<?= (int)$rdvProg ?>, <?= (int)$rdvEff ?>, <?= (int)$rdvAnn ?>],
            backgroundColor: [CYAN, GREEN, SLATE],
            borderColor    : '#111418',
            borderWidth    : 4,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: { display: false },
            tooltip,
        },
    },
});
</script>

<?php render_admin_footer(); ?>
