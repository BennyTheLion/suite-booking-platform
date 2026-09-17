<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// XAMPP's bundled PHP often has no working openssl.cnf on the process env; the web-push
// library needs one to sign VAPID tokens. Point it at a config that ships with PHP itself.
function ensure_openssl_conf(): void {
    if (getenv('OPENSSL_CONF') !== false) return;
    foreach ([
        __DIR__ . '/../vendor-config/openssl.cnf',
        'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
        'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
    ] as $candidate) {
        if (is_file($candidate)) { putenv('OPENSSL_CONF=' . $candidate); return; }
    }
}

function save_push_subscription(int $siteId, int $adminId, array $sub): void {
    $endpoint = $sub['endpoint'] ?? '';
    $p256dh = $sub['keys']['p256dh'] ?? '';
    $auth = $sub['keys']['auth'] ?? '';
    if (!$endpoint || !$p256dh || !$auth) return;

    $existing = db_one('SELECT id FROM push_subscriptions WHERE endpoint = ?', [$endpoint]);
    if ($existing) {
        db_run('UPDATE push_subscriptions SET site_id=?, admin_id=?, p256dh=?, auth=? WHERE id=?', [$siteId, $adminId, $p256dh, $auth, $existing['id']]);
    } else {
        db_run('INSERT INTO push_subscriptions (site_id, admin_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?, ?)', [$siteId, $adminId, $endpoint, $p256dh, $auth]);
    }
}

function delete_push_subscription(string $endpoint): void {
    db_run('DELETE FROM push_subscriptions WHERE endpoint = ?', [$endpoint]);
}

// Sends a push notification to every admin device subscribed for this site. Best-effort:
// unreachable/expired subscriptions are pruned, everything else is swallowed so a push failure
// never blocks the booking flow (email + in-panel notification already covered that).
function send_push_notifications(int $siteId, string $title, string $body, string $url = ''): void {
    if (!defined('VAPID_PUBLIC_KEY') || VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') return;

    $subs = db_all('SELECT * FROM push_subscriptions WHERE site_id = ?', [$siteId]);
    if (!$subs) return;

    ensure_openssl_conf();

    try {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);
    } catch (\Throwable $e) {
        return;
    }

    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

    foreach ($subs as $s) {
        try {
            $subscription = Subscription::create([
                'endpoint' => $s['endpoint'],
                'keys' => ['p256dh' => $s['p256dh'], 'auth' => $s['auth']],
            ]);
            $webPush->queueNotification($subscription, $payload);
        } catch (\Throwable $e) {
            // malformed subscription row — drop it
            delete_push_subscription($s['endpoint']);
        }
    }

    try {
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && ($report->isSubscriptionExpired())) {
                delete_push_subscription($report->getEndpoint());
            }
        }
    } catch (\Throwable $e) {
        // network/transport failure — never let this break the booking flow
    }
}
