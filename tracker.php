<?php
/**
 * ADEPTIO – Visitor tracker
 *
 * Add this one-liner to every page of your main website (before </body>):
 *
 *   <script>
 *     (function(){
 *       var i = new Image();
 *       i.src = '/adeptio_admin/tracker.php?page=' + encodeURIComponent(location.pathname)
 *                + '&ref=' + encodeURIComponent(document.referrer);
 *     })();
 *   </script>
 *
 * Or via an img tag (no JS required):
 *   <img src="/adeptio_admin/tracker.php" style="position:absolute;width:0;height:0;opacity:0" alt="">
 */

// Don't start a session here — tracker must be lightweight and stateless.

require_once __DIR__ . '/config/database.php';

// Auto-create table once (removed once table exists — cheap ALTER guard).
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
} catch (PDOException $e) { /* already exists */ }

// Privacy: hash the IP so we never store raw addresses.
$rawIp  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rawIp  = explode(',', $rawIp)[0]; // take first IP if behind proxy
$ipHash = hash('sha256', trim($rawIp) . 'adeptio_tracker_v1');

// Filter obvious bots via User-Agent.
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$botKeywords = ['bot', 'crawl', 'spider', 'slurp', 'googlebot', 'bingbot', 'yandex',
                'baidu', 'duckduck', 'semrush', 'ahref', 'majestic', 'wget', 'curl'];
foreach ($botKeywords as $kw) {
    if (str_contains($ua, $kw)) {
        respond_pixel();
    }
}

// Deduplicate: same unique visitor counted once per 30-minute window.
$check = $pdo->prepare(
    "SELECT id_visite FROM visites
     WHERE ip_hash = ? AND date_visite > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
     LIMIT 1"
);
$check->execute([$ipHash]);

if (!$check->fetch()) {
    $page    = substr($_GET['page']    ?? '/', 0, 500);
    $referer = substr($_GET['ref']     ?? '', 0, 500);

    $stmt = $pdo->prepare(
        "INSERT INTO visites (ip_hash, page, referrer) VALUES (?, ?, ?)"
    );
    $stmt->execute([$ipHash, $page, $referer]);
}

respond_pixel();

// ────────────────────────────────────────────────────────────────────
function respond_pixel(): never
{
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    // 1×1 transparent PNG
    echo base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
    );
    exit;
}
