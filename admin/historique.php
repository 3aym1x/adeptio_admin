<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_admin();

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT l.*, a.nom AS admin_nom, a.email AS admin_email
        FROM logs l
        LEFT JOIN admins a ON a.id_admin = l.id_admin
        WHERE l.action LIKE ?
        ORDER BY l.date_action DESC
        LIMIT 200
    ");
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt = $pdo->query("
        SELECT l.*, a.nom AS admin_nom, a.email AS admin_email
        FROM logs l
        LEFT JOIN admins a ON a.id_admin = l.id_admin
        ORDER BY l.date_action DESC
        LIMIT 200
    ");
}

$logs = $stmt->fetchAll();

render_admin_header($pdo, 'Historique', 'historique');
?>

<div class="filter-panel">
    <form class="row g-3 align-items-end" method="GET">
        <div class="col-md-8">
            <label class="form-label" for="q">Recherche</label>
            <input class="form-control" id="q" type="search" name="q"
                   value="<?= e($search) ?>" placeholder="Rechercher dans les actions...">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>Rechercher</button>
            <a class="btn btn-outline-secondary" href="historique.php">Reinitialiser</a>
        </div>
    </form>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><span style="color:var(--muted)">#<?= (int) $log['id_log'] ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= e($log['admin_nom'] ?? 'Systeme') ?></div>
                        <div class="text-muted small"><?= e($log['admin_email']) ?></div>
                    </td>
                    <td><?= e($log['action']) ?></td>
                    <td class="text-muted small"><?= e(format_datetime($log['date_action'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="4" class="empty-state"><i class="fas fa-history"></i>Aucune action trouvee.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_admin_footer(); ?>
