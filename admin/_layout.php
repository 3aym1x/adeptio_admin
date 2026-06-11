<?php
function render_admin_header(PDO $pdo, string $title, string $active): void
{
    $admin = current_admin($pdo);
    $flash = pull_flash();
    $links = [
        'dashboard'   => ['Dashboard',        'dashboard.php',    'fa-home'],
        'demandes'    => ['Demandes',          'demandes.php',     'fa-file-alt'],
        'rendez_vous' => ['Rendez-vous',       'rendez_vous.php',  'fa-calendar-alt'],
        'emails'      => ['Emails',            'emails.php',       'fa-envelope'],
        'historique'   => ['Historique',        'historique.php',    'fa-history'],
        'statistiques' => ['Statistiques',      'statistiques.php',  'fa-chart-bar'],
    ];
    ?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — ADEPTIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-shell">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="fas fa-bolt"></i></div>
            <span class="sidebar-brand-name">Adeptio</span>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($links as $key => [$label, $href, $icon]): ?>
                <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <i class="fas <?= e($icon) ?> nav-icon"></i>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt nav-icon"></i>
                <span>Deconnexion</span>
            </a>
        </div>
    </aside>

    <!-- Main -->
    <div class="main-content">
        <header class="topbar">
            <h1 class="topbar-title"><?= e($title) ?></h1>
            <div class="topbar-user">
                <div class="topbar-avatar"><i class="fas fa-user"></i></div>
                <span class="topbar-user-name"><?= e($admin['nom'] ?? 'Admin') ?></span>
            </div>
        </header>

        <div class="page-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible mb-4" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
    <?php
}

function render_admin_footer(): void
{
    ?>
        </div><!-- /page-body -->
    </div><!-- /main-content -->
</div><!-- /admin-shell -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
