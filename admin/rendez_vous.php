<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $idRdv  = (int) ($_POST['id_rdv'] ?? 0);

    if ($action === 'update_rdv') {
        $allowedStatuses = ['programme', 'effectue', 'annule'];
        $status = $_POST['statut']    ?? '';
        $date   = $_POST['date_rdv']  ?? '';
        $time   = $_POST['heure_rdv'] ?? '';

        if ($idRdv > 0 && $date && $time && in_array($status, $allowedStatuses, true)) {
            $stmt = $pdo->prepare('UPDATE rendez_vous SET date_rdv = ?, heure_rdv = ?, statut = ? WHERE id_rdv = ?');
            $stmt->execute([$date, $time, $status, $idRdv]);
            log_action($pdo, 'Rendez-vous #' . $idRdv . ' modifie: ' . $status);
            flash('success', 'Rendez-vous mis a jour.');
        } else {
            flash('danger', 'Informations du rendez-vous invalides.');
        }
    }

    redirect_to('rendez_vous.php');
}

$statusFilter        = $_GET['statut'] ?? '';
$allowedStatusFilters = ['', 'programme', 'effectue', 'annule'];

if (!in_array($statusFilter, $allowedStatusFilters, true)) { $statusFilter = ''; }

$where  = $statusFilter ? 'WHERE r.statut = ?' : '';
$params = $statusFilter ? [$statusFilter] : [];

$stmt = $pdo->prepare("
    SELECT r.*, d.nom, d.email, d.telephone, d.type_demande, d.sujet
    FROM rendez_vous r
    LEFT JOIN demandes d ON d.id_demande = r.id_demande
    $where
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
");
$stmt->execute($params);
$rdvs = $stmt->fetchAll();

render_admin_header($pdo, 'Gestion des rendez-vous', 'rendez_vous');
?>

<!-- Filters -->
<div class="filter-panel">
    <form class="row g-3 align-items-end" method="GET">
        <div class="col-md-5">
            <label class="form-label" for="statut">Statut</label>
            <select class="form-select" id="statut" name="statut">
                <option value="">Tous les statuts</option>
                <option value="programme" <?= $statusFilter === 'programme' ? 'selected' : '' ?>>Programme</option>
                <option value="effectue"  <?= $statusFilter === 'effectue'  ? 'selected' : '' ?>>Effectue</option>
                <option value="annule"    <?= $statusFilter === 'annule'    ? 'selected' : '' ?>>Annule</option>
            </select>
        </div>
        <div class="col-md-7 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Filtrer</button>
            <a class="btn btn-outline-secondary" href="rendez_vous.php">Reinitialiser</a>
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
                    <th>Demande</th>
                    <th>Date et heure</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rdvs as $rdv): ?>
                <tr>
                    <td><span style="color:var(--muted)">#<?= (int) $rdv['id_rdv'] ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= e($rdv['nom'] ?? 'Demande supprimee') ?></div>
                        <div class="text-muted small"><?= e($rdv['email']) ?></div>
                        <div class="text-muted small"><?= e($rdv['telephone']) ?></div>
                    </td>
                    <td>
                        <div><?= e($rdv['sujet']) ?></div>
                        <div class="text-muted small"><?= e(ucfirst((string) $rdv['type_demande'])) ?></div>
                    </td>
                    <td>
                        <form class="row g-2 align-items-center" method="POST" id="rdv-form-<?= (int) $rdv['id_rdv'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action"     value="update_rdv">
                            <input type="hidden" name="id_rdv"     value="<?= (int) $rdv['id_rdv'] ?>">
                            <div class="col-12 col-xl-6">
                                <input class="form-control form-control-sm" type="date" name="date_rdv"  value="<?= e($rdv['date_rdv']) ?>" required>
                            </div>
                            <div class="col-12 col-xl-6">
                                <input class="form-control form-control-sm" type="time" name="heure_rdv" value="<?= e(substr((string) $rdv['heure_rdv'], 0, 5)) ?>" required>
                            </div>
                        </form>
                    </td>
                    <td>
                        <span class="badge text-bg-<?= e(rdv_status_class($rdv['statut'])) ?> d-block mb-2">
                            <?= e(rdv_status_label($rdv['statut'])) ?>
                        </span>
                        <select class="form-select form-select-sm" name="statut" form="rdv-form-<?= (int) $rdv['id_rdv'] ?>">
                            <option value="programme" <?= $rdv['statut'] === 'programme' ? 'selected' : '' ?>>Programme</option>
                            <option value="effectue"  <?= $rdv['statut'] === 'effectue'  ? 'selected' : '' ?>>Effectue</option>
                            <option value="annule"    <?= $rdv['statut'] === 'annule'    ? 'selected' : '' ?>>Annule</option>
                        </select>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" type="submit" form="rdv-form-<?= (int) $rdv['id_rdv'] ?>">
                            <i class="fas fa-save me-1"></i>Enregistrer
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rdvs): ?>
                <tr><td colspan="6" class="empty-state"><i class="fas fa-calendar-times"></i>Aucun rendez-vous trouve.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_admin_footer(); ?>
