<?php
/**
 * Notifications + the schema the approval workflow needs. Everything is created on first use,
 * so nothing has to be imported (sql/approvals_migration.sql does the same by hand).
 */

if (!function_exists('ensure_approval_schema')) {
    /** Creates the notifications table and adds decided_at / decision_note to the two request tables. Safe to call any number of times. */
    function ensure_approval_schema(mysqli $c): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $c->query("CREATE TABLE IF NOT EXISTS notifications (
                notification_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_role  VARCHAR(10)  NOT NULL,
                user_id    INT          NOT NULL,
                type       VARCHAR(30)  NOT NULL DEFAULT 'info',
                message    VARCHAR(255) NOT NULL,
                link       VARCHAR(120) NULL,
                is_read    TINYINT(1)   NOT NULL DEFAULT 0,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_notif_user (user_role, user_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
            foreach (['faculty_designations', 'overload_sessions'] as $t) {
                foreach (['decided_at' => 'TIMESTAMP NULL DEFAULT NULL', 'decision_note' => 'VARCHAR(255) NULL DEFAULT NULL'] as $col => $def) {
                    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '$col'");
                    if ($r && $r->num_rows === 0) $c->query("ALTER TABLE `$t` ADD COLUMN `$col` $def");
                }
            }
        } catch (Throwable $e) { /* the app keeps working; approvals just won't show notes */ }
    }
}

if (!function_exists('notify')) {
    /** One notification for one person. $role = 'admin' | 'faculty'. Never throws: a failed notification must not break the action that caused it. */
    function notify(mysqli $c, string $role, int $userId, string $type, string $message, string $link = ''): void {
        try {
            ensure_approval_schema($c);
            $message = mb_substr($message, 0, 250);
            $st = $c->prepare("INSERT INTO notifications (user_role, user_id, type, message, link) VALUES (?, ?, ?, ?, ?)");
            if (!$st) return;
            $st->bind_param('sisss', $role, $userId, $type, $message, $link);
            $st->execute();
        } catch (Throwable $e) {}
    }
}

if (!function_exists('notify_admins')) {
    /** Tell every administrator (the chair). */
    function notify_admins(mysqli $c, string $type, string $message, string $link = ''): void {
        try {
            ensure_approval_schema($c);
            $notified = [];
            foreach (['admins', 'admin'] as $tbl) {
                try {
                    $res = $c->query("SELECT admin_id FROM `{$tbl}`");
                    if ($res) {
                        foreach ($res->fetch_all(MYSQLI_ASSOC) as $a) {
                            $aid = (int)($a['admin_id'] ?? 0);
                            if ($aid > 0 && !in_array($aid, $notified, true)) {
                                $notified[] = $aid;
                                notify($c, 'admin', $aid, $type, $message, $link);
                            }
                        }
                    }
                } catch (Throwable $e) {}
            }
            if (!$notified && function_exists('currentUserId')) {
                $curId = (int)currentUserId();
                if ($curId > 0) {
                    notify($c, 'admin', $curId, $type, $message, $link);
                }
            }
        } catch (Throwable $e) {}
    }
}

if (!function_exists('unread_count')) {
    function unread_count(mysqli $c, string $role, int $userId): int {
        try {
            ensure_approval_schema($c);
            $st = $c->prepare("SELECT COUNT(*) AS n FROM notifications WHERE user_role = ? AND user_id = ? AND is_read = 0");
            $st->bind_param('si', $role, $userId);
            $st->execute();
            return (int)$st->get_result()->fetch_assoc()['n'];
        } catch (Throwable $e) { return 0; }
    }
}

if (!function_exists('pending_approval_count')) {
    /** Requests waiting for the chair (designations + overload sessions). */
    function pending_approval_count(mysqli $c): int {
        try {
            ensure_approval_schema($c);
            $a = (int)$c->query("SELECT COUNT(*) AS n FROM faculty_designations WHERE status = 'Pending'")->fetch_assoc()['n'];
            $b = (int)$c->query("SELECT COUNT(*) AS n FROM overload_sessions WHERE status = 'Pending'")->fetch_assoc()['n'];
            return $a + $b;
        } catch (Throwable $e) { return 0; }
    }
}

if (!function_exists('time_ago')) {
    /** "5 min ago", "2 h ago", "Nov 21" */
    function time_ago(string $ts): string {
        $t = strtotime($ts); if (!$t) return '';
        $d = time() - $t;
        if ($d < 60)     return 'just now';
        if ($d < 3600)   return intdiv($d, 60) . ' min ago';
        if ($d < 86400)  return intdiv($d, 3600) . ' h ago';
        if ($d < 604800) return intdiv($d, 86400) . ' d ago';
        return date('M j', $t);
    }
}

if (!function_exists('notifications_handle')) {
    /** Shared by admin/notifications.php and faculty/notifications.php. Handles ?count=1, ?go=ID and "mark all read" BEFORE any output. */
    function notifications_handle(mysqli $c, string $role, int $uid): void {
        ensure_approval_schema($c);
        if (isset($_GET['count'])) {
            header('Content-Type: application/json');
            echo json_encode(['unread' => unread_count($c, $role, $uid)]);
            exit;
        }
        if (isset($_GET['go'])) {                                    // open one: mark it read, then jump to where it points
            $id = (int)$_GET['go'];
            $st = $c->prepare("SELECT link FROM notifications WHERE notification_id = ? AND user_role = ? AND user_id = ?");
            $st->bind_param('isi', $id, $role, $uid); $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if ($row) {
                $st = $c->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_role = ? AND user_id = ?");
                $st->bind_param('isi', $id, $role, $uid); $st->execute();
                if ($row['link'] !== null && preg_match('/^[a-z_]+\.php(\?[\w=&.-]*)?$/', (string)$row['link'])) redirect($row['link']);
            }
            redirect('notifications.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $st = $c->prepare("UPDATE notifications SET is_read = 1 WHERE user_role = ? AND user_id = ?");
            $st->bind_param('si', $role, $uid); $st->execute();
            flash_set('success', 'All notifications marked as read.');
            redirect('notifications.php');
        }
    }
}

if (!function_exists('notifications_render')) {
    function notifications_render(mysqli $c, string $role, int $uid): void {
        $st = $c->prepare("SELECT notification_id, type, message, link, is_read, created_at FROM notifications WHERE user_role = ? AND user_id = ? ORDER BY notification_id DESC LIMIT 100");
        $st->bind_param('si', $role, $uid); $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $flash = flash_get();
        $unread = count(array_filter($rows, fn($r) => !(int)$r['is_read']));
        $icons = ['approved' => '✔', 'rejected' => '✖', 'request' => '✎', 'overload' => '＋', 'designation' => '★'];
    ?>
    <link rel="stylesheet" href="<?= asset('portal.css') ?>">
    <section class="page-section notif-page">
    <div class="section-heading">
        <div><span class="eyebrow">NOTIFICATIONS</span><h2>Notifications</h2></div>
        <?php if ($unread): ?>
        <form method="post"><?= csrf_field() ?><button type="submit" class="primary-btn">Mark all as read (<?= $unread ?>)</button></form>
        <?php endif; ?>
    </div>
    <?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

    <?php if (!$rows): ?>
        <div class="empty">You're all caught up. New requests and decisions will show up here.</div>
    <?php else: ?>
    <div class="notif-list">
      <?php foreach ($rows as $n): $unreadRow = !(int)$n['is_read']; ?>
      <a class="notif <?= $unreadRow ? 'unread' : '' ?> t-<?= h($n['type']) ?>" href="notifications.php?go=<?= (int)$n['notification_id'] ?>">
        <span class="notif-ic"><?= $icons[$n['type']] ?? '●' ?></span>
        <span class="notif-body"><strong><?= h($n['message']) ?></strong><small><?= h(time_ago($n['created_at'])) ?></small></span>
        <?php if ($unreadRow): ?><span class="notif-dot" aria-label="unread"></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    </section>
    <?php }
}