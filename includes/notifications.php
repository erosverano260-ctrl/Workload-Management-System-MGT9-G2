<?php
/**
 * In-app notifications for instructors (e.g. "The chair approved your request").
 * The `notifications` table is created automatically the first time it is needed; sql/notifications_migration.sql does the same by hand.
 * Messages are plain ASCII on purpose: the database tables use the latin1 character set.
 */

function notif_ensure(mysqli $c): void {
    static $done = false;
    if ($done) return;
    $c->query("CREATE TABLE IF NOT EXISTS `notifications` (
        `notif_id`   int(11)      NOT NULL AUTO_INCREMENT,
        `faculty_id` int(11)      NOT NULL,
        `kind`       varchar(20)  NOT NULL DEFAULT 'info',
        `title`      varchar(150) NOT NULL,
        `body`       varchar(500) NOT NULL DEFAULT '',
        `link`       varchar(100) NOT NULL DEFAULT '',
        `is_read`    tinyint(1)   NOT NULL DEFAULT 0,
        `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`notif_id`),
        KEY `idx_faculty_read` (`faculty_id`, `is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    $done = true;
}

/** Send a notification to one instructor. $kind: 'approved', 'rejected' or 'info'. */
function notif_add(mysqli $c, int $facultyId, string $kind, string $title, string $body = '', string $link = ''): void {
    notif_ensure($c);
    $title = mb_substr($title, 0, 150); $body = mb_substr($body, 0, 500);
    $st = $c->prepare("INSERT INTO notifications (faculty_id, kind, title, body, link) VALUES (?, ?, ?, ?, ?)");
    $st->bind_param('issss', $facultyId, $kind, $title, $body, $link);
    $st->execute();
}

function notif_unread_count(mysqli $c, int $facultyId): int {
    try {
        notif_ensure($c);
        $st = $c->prepare("SELECT COUNT(*) FROM notifications WHERE faculty_id = ? AND is_read = 0");
        $st->bind_param('i', $facultyId); $st->execute();
        return (int)$st->get_result()->fetch_row()[0];
    } catch (Throwable $e) { return 0; }                    // never break a page over the bell
}

/** Newest first. $unreadOnly = true for just the ones not seen yet. */
function notif_recent(mysqli $c, int $facultyId, int $limit = 50, bool $unreadOnly = false): array {
    try {
        notif_ensure($c);
        $sql = "SELECT notif_id, kind, title, body, link, is_read, created_at FROM notifications WHERE faculty_id = ?"
             . ($unreadOnly ? " AND is_read = 0" : "") . " ORDER BY notif_id DESC LIMIT " . (int)$limit;
        $st = $c->prepare($sql);
        $st->bind_param('i', $facultyId); $st->execute();
        return $st->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Throwable $e) { return []; }
}

function notif_mark_all_read(mysqli $c, int $facultyId): void {
    try {
        notif_ensure($c);
        $st = $c->prepare("UPDATE notifications SET is_read = 1 WHERE faculty_id = ? AND is_read = 0");
        $st->bind_param('i', $facultyId); $st->execute();
    } catch (Throwable $e) { /* ignore */ }
}

/** How many faculty requests are waiting for the chair (designations + overload sessions). */
function requests_pending_count(mysqli $c): int {
    try {
        $a = (int)$c->query("SELECT COUNT(*) FROM faculty_designations WHERE status = 'Pending'")->fetch_row()[0];
        $b = (int)$c->query("SELECT COUNT(*) FROM overload_sessions WHERE status = 'Pending'")->fetch_row()[0];
        return $a + $b;
    } catch (Throwable $e) { return 0; }
}

/** "5 min ago", "2 hrs ago", "Nov 21, 1:29 PM" */
function notif_ago(string $ts): string {
    $t = strtotime($ts); if (!$t) return '';
    $d = time() - $t;
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return floor($d / 60) . ' min ago';
    if ($d < 86400)  return floor($d / 3600) . ' hr' . (floor($d / 3600) == 1 ? '' : 's') . ' ago';
    return date('M j, g:i A', $t);
}
