<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_admin();

$stats = [
    'total_demandes' => (int) $pdo->query('SELECT COUNT(*) FROM demandes')->fetchColumn(),
    'etudiants'      => (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE type_demande = 'etudiant'")->fetchColumn(),
    'partenaires'    => (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE type_demande = 'partenaire'")->fetchColumn(),
    'rdv_programmes' => (int) $pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut = 'programme'")->fetchColumn(),
];

$statusCounts = $pdo->query("
    SELECT statut, COUNT(*) AS total
    FROM demandes
    GROUP BY statut
")->fetchAll();

$latestDemandes = $pdo->query("
    SELECT id_demande, nom, type_demande, statut, date_demande
    FROM demandes
    ORDER BY date_demande DESC
    LIMIT 8
")->fetchAll();

$upcomingRdv = $pdo->query("
    SELECT r.id_rdv, r.date_rdv, r.heure_rdv, r.statut, d.nom
    FROM rendez_vous r
    LEFT JOIN demandes d ON d.id_demande = r.id_demande
    WHERE r.statut = 'programme'
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    LIMIT 5
")->fetchAll();

render_admin_header($pdo, 'Tableau de bord', 'dashboard');
?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Total demandes</div>
            <div class="stat-value"><?= $stats['total_demandes'] ?></div>
            <i class="fas fa-file-alt stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Etudiants</div>
            <div class="stat-value"><?= $stats['etudiants'] ?></div>
            <i class="fas fa-user-graduate stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Partenaires</div>
            <div class="stat-value"><?= $stats['partenaires'] ?></div>
            <i class="fas fa-handshake stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">RDV programmes</div>
            <div class="stat-value"><?= $stats['rdv_programmes'] ?></div>
            <i class="fas fa-calendar-check stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Latest requests -->
    <section class="col-xl-8">
        <div class="panel">
            <div class="panel-header">
                <h2>Dernieres demandes</h2>
                <a class="btn btn-sm btn-outline-primary" href="demandes.php">Voir tout</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($latestDemandes as $d): ?>
                        <tr>
                            <td><span style="color:var(--muted)">#<?= (int) $d['id_demande'] ?></span></td>
                            <td class="fw-semibold"><?= e($d['nom']) ?></td>
                            <td><?= e(ucfirst($d['type_demande'])) ?></td>
                            <td>
                                <span class="badge text-bg-<?= e(demande_status_class($d['statut'])) ?>">
                                    <?= e(demande_status_label($d['statut'])) ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= e(format_datetime($d['date_demande'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$latestDemandes): ?>
                        <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>Aucune demande pour le moment.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Sidebar panels -->
    <aside class="col-xl-4">
        <div class="panel mb-4">
            <div class="panel-header"><h2>Demandes par statut</h2></div>
            <div class="panel-body">
                <?php foreach ($statusCounts as $row): ?>
                    <div class="status-row">
                        <span class="status-name"><?= e(demande_status_label($row['statut'])) ?></span>
                        <span class="badge text-bg-<?= e(demande_status_class($row['statut'])) ?>"><?= (int) $row['total'] ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$statusCounts): ?>
                    <p class="text-muted mb-0" style="font-size:.85rem">Aucune donnee disponible.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Prochains RDV</h2>
                <a class="btn btn-sm btn-outline-primary" href="rendez_vous.php">Gerer</a>
            </div>
            <div class="panel-body">
                <?php foreach ($upcomingRdv as $rdv): ?>
                    <div class="rdv-item">
                        <div class="rdv-name"><?= e($rdv['nom'] ?? 'Demande supprimee') ?></div>
                        <div class="rdv-time"><i class="fas fa-clock me-1"></i><?= e(format_date($rdv['date_rdv'])) ?> a <?= e(substr($rdv['heure_rdv'], 0, 5)) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$upcomingRdv): ?>
                    <div class="empty-state"><i class="fas fa-calendar-times"></i>Aucun rendez-vous programme.</div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<?php render_admin_footer(); ?>
