<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action    = $_POST['action'] ?? '';
    $idDemande = (int) ($_POST['id_demande'] ?? 0);

    if ($action === 'update_status') {
        $allowedStatuses = ['en_attente', 'acceptee', 'rejetee'];
        $status = $_POST['statut'] ?? '';

        if ($idDemande > 0 && in_array($status, $allowedStatuses, true)) {
            $stmt = $pdo->prepare('UPDATE demandes SET statut = ? WHERE id_demande = ?');
            $stmt->execute([$status, $idDemande]);
            log_action($pdo, 'Statut demande #' . $idDemande . ' modifie: ' . $status);
            flash('success', 'Statut de la demande mis a jour.');
        } else {
            flash('danger', 'Statut invalide.');
        }
    }

    if ($action === 'schedule_rdv') {
        $date = $_POST['date_rdv'] ?? '';
        $time = $_POST['heure_rdv'] ?? '';

        if ($idDemande > 0 && $date && $time) {
            $stmt = $pdo->prepare('INSERT INTO rendez_vous (id_demande, date_rdv, heure_rdv, statut) VALUES (?, ?, ?, ?)');
            $stmt->execute([$idDemande, $date, $time, 'programme']);

            $update = $pdo->prepare("UPDATE demandes SET statut = 'acceptee' WHERE id_demande = ?");
            $update->execute([$idDemande]);

            log_action($pdo, 'Rendez-vous programme pour la demande #' . $idDemande . ' le ' . $date . ' a ' . $time);
            flash('success', 'Rendez-vous programme et demande acceptee.');
        } else {
            flash('danger', 'Merci de renseigner une date et une heure.');
        }
    }

    redirect_to('demandes.php');
}

$statusFilter = $_GET['statut'] ?? '';
$typeFilter   = $_GET['type']   ?? '';
$allowedStatusFilters = ['', 'en_attente', 'acceptee', 'rejetee'];
$allowedTypeFilters   = ['', 'etudiant',   'partenaire'];

if (!in_array($statusFilter, $allowedStatusFilters, true)) { $statusFilter = ''; }
if (!in_array($typeFilter,   $allowedTypeFilters,   true)) { $typeFilter   = ''; }

$conditions = [];
$params     = [];

if ($statusFilter) { $conditions[] = 'd.statut = ?';       $params[] = $statusFilter; }
if ($typeFilter)   { $conditions[] = 'd.type_demande = ?'; $params[] = $typeFilter; }

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$stmt  = $pdo->prepare("
    SELECT d.*,
        (SELECT COUNT(*) FROM rendez_vous r WHERE r.id_demande = d.id_demande) AS total_rdv
    FROM demandes d
    $where
    ORDER BY d.date_demande DESC
");
$stmt->execute($params);
$demandes = $stmt->fetchAll();

render_admin_header($pdo, 'Gestion des demandes', 'demandes');
?>

<!-- Filters -->
<div class="filter-panel">
    <form class="row g-3 align-items-end" method="GET">
        <div class="col-md-4">
            <label class="form-label" for="statut">Statut</label>
            <select class="form-select" id="statut" name="statut">
                <option value="">Tous les statuts</option>
                <option value="en_attente"  <?= $statusFilter === 'en_attente'  ? 'selected' : '' ?>>En attente</option>
                <option value="acceptee"    <?= $statusFilter === 'acceptee'    ? 'selected' : '' ?>>Acceptee</option>
                <option value="rejetee"     <?= $statusFilter === 'rejetee'     ? 'selected' : '' ?>>Rejetee</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="type">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">Tous les types</option>
                <option value="etudiant"    <?= $typeFilter === 'etudiant'   ? 'selected' : '' ?>>Etudiant</option>
                <option value="partenaire"  <?= $typeFilter === 'partenaire' ? 'selected' : '' ?>>Partenaire</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Filtrer</button>
            <a class="btn btn-outline-secondary" href="demandes.php">Reinitialiser</a>
        </div>
    </form>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Contact</th>
                    <th>Type</th>
                    <th>Sujet</th>
                    <th>Message</th>
                    <th>Statut</th>
                    <th>RDV</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($demandes as $demande): ?>
                <tr>
                    <td><span style="color:var(--muted)">#<?= (int) $demande['id_demande'] ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= e($demande['nom']) ?></div>
                        <div class="text-muted small"><?= e($demande['email']) ?></div>
                        <div class="text-muted small"><?= e($demande['telephone']) ?></div>
                    </td>
                    <td><?= e(ucfirst($demande['type_demande'])) ?></td>
                    <td style="max-width:160px;white-space:normal"><?= e($demande['sujet']) ?></td>
                    <td class="message-cell"><?= nl2br(e($demande['message'])) ?></td>
                    <td>
                        <span class="badge text-bg-<?= e(demande_status_class($demande['statut'])) ?> d-block mb-2">
                            <?= e(demande_status_label($demande['statut'])) ?>
                        </span>
                        <form class="d-flex gap-2" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action"     value="update_status">
                            <input type="hidden" name="id_demande" value="<?= (int) $demande['id_demande'] ?>">
                            <select class="form-select form-select-sm" name="statut" aria-label="Statut">
                                <option value="en_attente" <?= $demande['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                <option value="acceptee"   <?= $demande['statut'] === 'acceptee'   ? 'selected' : '' ?>>Acceptee</option>
                                <option value="rejetee"    <?= $demande['statut'] === 'rejetee'    ? 'selected' : '' ?>>Rejetee</option>
                            </select>
                            <button class="btn btn-sm btn-outline-primary" type="submit" title="Enregistrer le statut">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="text-muted small mb-2"><?= (int) $demande['total_rdv'] ?> RDV</div>
                        <form class="d-grid gap-2" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action"     value="schedule_rdv">
                            <input type="hidden" name="id_demande" value="<?= (int) $demande['id_demande'] ?>">
                            <input class="form-control form-control-sm" type="date" name="date_rdv"   required>
                            <input class="form-control form-control-sm" type="time" name="heure_rdv"  required>
                            <button class="btn btn-sm btn-outline-success" type="submit">
                                <i class="fas fa-calendar-plus me-1"></i>Programmer
                            </button>
                        </form>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary mb-2" href="emails.php?id_demande=<?= (int) $demande['id_demande'] ?>">
                            <i class="fas fa-envelope me-1"></i>Email
                        </a>
                        <div class="text-muted small"><?= e(format_datetime($demande['date_demande'])) ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$demandes): ?>
                <tr><td colspan="8" class="empty-state"><i class="fas fa-search"></i>Aucune demande trouvee.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_admin_footer(); ?>
