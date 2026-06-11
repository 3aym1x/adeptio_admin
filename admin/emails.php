<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_admin();

$selectedDemandeId = (int) ($_GET['id_demande'] ?? $_POST['id_demande'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $idDemande = (int) ($_POST['id_demande'] ?? 0);
    $subject   = trim($_POST['sujet']   ?? '');
    $content   = trim($_POST['contenu'] ?? '');

    if ($idDemande <= 0 || $subject === '' || $content === '') {
        flash('danger', 'Merci de choisir une demande et de remplir le sujet et le contenu.');
        redirect_to('emails.php' . ($idDemande ? '?id_demande=' . $idDemande : ''));
    }

    $demandeStmt = $pdo->prepare('SELECT id_demande, nom, email FROM demandes WHERE id_demande = ?');
    $demandeStmt->execute([$idDemande]);
    $demande = $demandeStmt->fetch();

    if (!$demande || empty($demande['email'])) {
        flash('danger', 'Demande introuvable ou email manquant.');
        redirect_to('emails.php');
    }

    $insert = $pdo->prepare('INSERT INTO emails (id_demande, sujet, contenu) VALUES (?, ?, ?)');
    $insert->execute([$idDemande, $subject, $content]);

    $headers = "From: contact@adeptio.ma\r\nContent-Type: text/plain; charset=UTF-8";
    $sent = function_exists('mail') ? @mail($demande['email'], $subject, $content, $headers) : false;

    log_action($pdo, 'Email enregistre pour la demande #' . $idDemande . ': ' . $subject);
    flash($sent ? 'success' : 'warning', $sent ? 'Email envoye et enregistre.' : 'Email enregistre. Envoi SMTP non confirme sur cet environnement local.');
    redirect_to('emails.php?id_demande=' . $idDemande);
}

$demandes = $pdo->query("
    SELECT id_demande, nom, email, sujet
    FROM demandes
    ORDER BY date_demande DESC
")->fetchAll();

$selectedDemande = null;
if ($selectedDemandeId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM demandes WHERE id_demande = ?');
    $stmt->execute([$selectedDemandeId]);
    $selectedDemande = $stmt->fetch() ?: null;
}

$emails = $pdo->query("
    SELECT e.*, d.nom, d.email
    FROM emails e
    LEFT JOIN demandes d ON d.id_demande = e.id_demande
    ORDER BY e.date_envoi DESC
")->fetchAll();

render_admin_header($pdo, 'Gestion des emails', 'emails');
?>
<div class="row g-4">
    <!-- Compose -->
    <section class="col-xl-5">
        <div class="panel">
            <div class="panel-header"><h2>Composer un email</h2></div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="id_demande">Demande</label>
                        <select class="form-select" id="id_demande" name="id_demande" required
                                onchange="window.location='emails.php?id_demande=' + this.value">
                            <option value="">Choisir une demande</option>
                            <?php foreach ($demandes as $d): ?>
                                <option value="<?= (int) $d['id_demande'] ?>" <?= $selectedDemandeId === (int) $d['id_demande'] ? 'selected' : '' ?>>
                                    #<?= (int) $d['id_demande'] ?> — <?= e($d['nom']) ?> (<?= e($d['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($selectedDemande): ?>
                        <div class="contact-preview mb-3">
                            <div class="name"><?= e($selectedDemande['nom']) ?></div>
                            <div class="email"><?= e($selectedDemande['email']) ?></div>
                            <div class="subj"><?= e($selectedDemande['sujet']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="sujet">Sujet</label>
                        <input class="form-control" id="sujet" type="text" name="sujet"
                               value="<?= $selectedDemande ? 'Reponse a votre demande ADEPTIO' : '' ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="contenu">Contenu</label>
                        <textarea class="form-control" id="contenu" name="contenu" rows="9" required><?= $selectedDemande ? e("Bonjour " . $selectedDemande['nom'] . ",\n\nNous vous remercions pour votre demande. \n\nCordialement,\nADEPTIO Conseil") : '' ?></textarea>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-paper-plane me-2"></i>Envoyer
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- History -->
    <section class="col-xl-7">
        <div class="panel">
            <div class="panel-header"><h2>Historique des emails</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Destinataire</th>
                            <th>Sujet</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($emails as $email): ?>
                        <tr>
                            <td><span style="color:var(--muted)">#<?= (int) $email['id_email'] ?></span></td>
                            <td>
                                <div class="fw-semibold"><?= e($email['nom'] ?? 'Demande supprimee') ?></div>
                                <div class="text-muted small"><?= e($email['email']) ?></div>
                            </td>
                            <td>
                                <div><?= e($email['sujet']) ?></div>
                                <div class="text-muted small message-cell"><?= nl2br(e($email['contenu'])) ?></div>
                            </td>
                            <td class="text-muted small"><?= e(format_datetime($email['date_envoi'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$emails): ?>
                        <tr><td colspan="4" class="empty-state"><i class="fas fa-inbox"></i>Aucun email enregistre.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php render_admin_footer(); ?>
