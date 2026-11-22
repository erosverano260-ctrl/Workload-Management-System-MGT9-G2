<?php
/** Load rules (40-hour week, service credits, overload claim) and the chair's approve / reject logic. */
require_once __DIR__ . '/print_helpers.php';

const FACULTY_WEEK_HOURS = 40;        // total faculty time per week: regular teaching load + other functions + designation

/** Chair and Dean designations never earn service credit. */
function designation_is_chair_or_dean(string $name): bool { return (bool)preg_match('/\b(chair|dean)\b/i', $name); }
function designation_credit(array $d): float { return designation_is_chair_or_dean((string)$d['name']) ? 0.0 : (float)$d['service_credit']; }

function session_hours(array $s): float { return (timeToMin($s['end_time']) - timeToMin($s['start_time'])) / 60; }

/**
 * Everything about one instructor's load, in one place.
 *   lec / lab / comp        lecture hrs, lab hrs, total = lec + lab x 0.7
 *   preps / allowed         number of preparations (each subject once) and the regular load it allows (21 / 18 / 15)
 *   overload                comp - allowed (never negative)
 *   claim / credit          whole overload hours that can be claimed as 1-hour sessions / the leftover below 1 = Teaching Service Credit
 *   serviceCredit           from approved designations (Chair / Dean excluded); NOT part of the 40 hours
 *   regular + other + desHrs = weekTotal   (the 40-hour week; overload and service credits sit outside it)
 */
function faculty_load(array $entries, array $designations, array $sessions): array {
    $w = workload_summary($entries);
    $desHrs = 0.0; $svc = 0.0;
    foreach ($designations as $d) { $desHrs += (float)$d['hours']; $svc += designation_credit($d); }

    $regular = min($w['comp'], $w['allowed']);
    $other   = max(0.0, FACULTY_WEEK_HOURS - $regular - $desHrs);     // research / extension / IMP / consultation take the rest
    $appr = 0.0; $pend = 0.0;
    foreach ($sessions as $s) {
        if ($s['status'] === 'Approved') $appr += session_hours($s);
        elseif ($s['status'] === 'Pending') $pend += session_hours($s);
    }
    return $w + [
        'desHrs' => round($desHrs, 1), 'serviceCredit' => round($svc, 1),
        'regular' => round($regular, 1), 'other' => round($other, 1),
        'weekTotal' => round($regular + $other + $desHrs, 1),
        'over40' => round(max(0.0, $regular + $desHrs - FACULTY_WEEK_HOURS), 1),
        'sessApproved' => $appr, 'sessPending' => $pend,
        'claimLeft' => max(0.0, $w['claim'] - $appr - $pend),
    ];
}

/** Load for one instructor straight from the database (all sessions, so Pending / Approved are both known). */
function faculty_load_for(mysqli $c, int $facultyId): array {
    return faculty_load(load_program_entries($c, 'faculty', $facultyId), load_designations($c, $facultyId), load_overload_sessions($c, $facultyId));
}

/* ====================== the chair's decisions ====================== */

/** Approve or reject a designation request. Returns an error message, or null when it worked. */
function decide_designation(mysqli $c, int $fdId, bool $approve, string $note): ?string {
    $st = $c->prepare("SELECT fd.faculty_id, fd.status, d.name, d.hours FROM faculty_designations fd JOIN designations d ON d.designation_id = fd.designation_id WHERE fd.fd_id = ?");
    $st->bind_param('i', $fdId); $st->execute();
    $r = $st->get_result()->fetch_assoc();
    if (!$r) return 'That request no longer exists.';
    if ($r['status'] !== 'Pending') return 'That request was already decided.';
    $fid = (int)$r['faculty_id'];

    if ($approve) {                                         // the 40-hour week is a hard limit
        $L = faculty_load_for($c, $fid);
        if ($L['regular'] + $L['desHrs'] + (float)$r['hours'] > FACULTY_WEEK_HOURS)
            return 'Approving "' . $r['name'] . '" would put this instructor over the ' . FACULTY_WEEK_HOURS . '-hour week (teaching ' . nf($L['regular']) . ' + designations ' . nf($L['desHrs']) . ' + this ' . nf($r['hours']) . '). Reject it or lower its hours first.';
    }
    $status = $approve ? 'Approved' : 'Rejected';
    $st = $c->prepare("UPDATE faculty_designations SET status = ?, decided_at = NOW(), decision_note = ? WHERE fd_id = ? AND status = 'Pending'");
    $n = $note !== '' ? $note : null;
    $st->bind_param('ssi', $status, $n, $fdId); $st->execute();

    notify($c, 'faculty', $fid, $approve ? 'approved' : 'rejected',
        'Your designation request "' . $r['name'] . '" was ' . strtolower($status) . ' by the chair.' . ($note !== '' ? ' Note: ' . $note : ''), 'designations.php');
    return null;
}

/** Approve or reject one overload session. Approval is refused unless it is exactly 1 hour, still within the claimable hours, and free of clashes. */
function decide_session(mysqli $c, int $sessionId, bool $approve, string $note): ?string {
    $st = $c->prepare("SELECT session_id, faculty_id, day_of_week, start_time, end_time, status FROM overload_sessions WHERE session_id = ?");
    $st->bind_param('i', $sessionId); $st->execute();
    $s = $st->get_result()->fetch_assoc();
    if (!$s) return 'That session no longer exists.';
    if ($s['status'] !== 'Pending') return 'That session was already decided.';
    $fid = (int)$s['faculty_id'];
    $label = $s['day_of_week'] . ' ' . date('g:i A', strtotime($s['start_time'])) . ' - ' . date('g:i A', strtotime($s['end_time']));

    if ($approve) {
        if (abs(session_hours($s) - 1.0) > 0.001) return 'Only 1-hour sessions can be accepted (' . $label . ' is ' . nf(session_hours($s)) . ' hours).';
        $entries = load_program_entries($c, 'faculty', $fid);
        $L = faculty_load($entries, load_designations($c, $fid), load_overload_sessions($c, $fid));
        if ($L['sessApproved'] + 1 > $L['claim'] + 0.001)
            return 'This instructor only has ' . nf($L['claim']) . ' overload hour(s) to claim and ' . nf($L['sessApproved']) . ' are already approved.';
        $a = timeToMin($s['start_time']); $b = timeToMin($s['end_time']);
        foreach ($entries as $e) if ($e['day_of_week'] === $s['day_of_week'] && $a < timeToMin($e['end_time']) && $b > timeToMin($e['start_time'])) return 'That hour overlaps one of the instructor\'s classes.';
        foreach (load_overload_sessions($c, $fid) as $o) {
            if ((int)$o['session_id'] !== $sessionId && $o['status'] === 'Approved' && $o['day_of_week'] === $s['day_of_week'] && $a < timeToMin($o['end_time']) && $b > timeToMin($o['start_time'])) return 'That hour overlaps another approved overload session.';
        }
    }
    $status = $approve ? 'Approved' : 'Rejected';
    $st = $c->prepare("UPDATE overload_sessions SET status = ?, decided_at = NOW(), decision_note = ? WHERE session_id = ? AND status = 'Pending'");
    $n = $note !== '' ? $note : null;
    $st->bind_param('ssi', $status, $n, $sessionId); $st->execute();

    notify($c, 'faculty', $fid, $approve ? 'approved' : 'rejected',
        'Your overload session (' . $label . ') was ' . strtolower($status) . ' by the chair.' . ($note !== '' ? ' Note: ' . $note : ''), 'overload.php');
    return null;
}
