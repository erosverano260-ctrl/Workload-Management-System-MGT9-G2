<?php
/** Faculty schedule-change requests. A request never edits schedules until an admin approves it. */
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/schedule_helpers.php';

function ensure_schedule_change_schema(mysqli $c): void {
    $c->query("CREATE TABLE IF NOT EXISTS schedule_change_requests (
        request_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        schedule_ids TEXT NOT NULL,
        faculty_id INT NOT NULL,
        old_day VARCHAR(10) NOT NULL,
        old_start TIME NOT NULL,
        old_end TIME NOT NULL,
        new_day VARCHAR(10) NOT NULL,
        new_start TIME NOT NULL,
        new_end TIME NOT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        faculty_note VARCHAR(200) NULL,
        decision_note VARCHAR(200) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        decided_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_change_status (status), KEY idx_change_faculty (faculty_id), KEY idx_change_schedule (schedule_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $r = $c->query("SHOW COLUMNS FROM schedule_change_requests LIKE 'schedule_ids'");
    if ($r && $r->num_rows === 0) $c->query("ALTER TABLE schedule_change_requests ADD COLUMN schedule_ids TEXT NULL AFTER schedule_id");
}

/** Return all adjacent 30-minute records that form the visible class block. */
function schedule_block_rows(mysqli $c, int $scheduleId, int $facultyId): array {
    $q = $c->prepare("SELECT s.* FROM schedules s WHERE s.faculty_id=? AND s.day_of_week=(SELECT day_of_week FROM schedules WHERE schedule_id=? AND faculty_id=?)
        AND s.course_id=(SELECT course_id FROM schedules WHERE schedule_id=? AND faculty_id=?)
        AND s.room_id=(SELECT room_id FROM schedules WHERE schedule_id=? AND faculty_id=?)
        AND s.section <=> (SELECT section FROM schedules WHERE schedule_id=? AND faculty_id=?)
        AND s.class_type=(SELECT class_type FROM schedules WHERE schedule_id=? AND faculty_id=?) ORDER BY s.start_time");
    $q->bind_param('iiiiiiiiiii', $facultyId,$scheduleId,$facultyId,$scheduleId,$facultyId,$scheduleId,$facultyId,$scheduleId,$facultyId,$scheduleId,$facultyId);
    $q->execute(); $rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);
    $at=null; foreach($rows as $i=>$row) if((int)$row['schedule_id']===$scheduleId){$at=$i;break;}
    if($at===null) return [];
    $lo=$hi=$at;
    while($lo>0 && $rows[$lo-1]['end_time']===$rows[$lo]['start_time']) $lo--;
    while($hi<count($rows)-1 && $rows[$hi]['end_time']===$rows[$hi+1]['start_time']) $hi++;
    return array_slice($rows,$lo,$hi-$lo+1);
}

function schedule_change_conflict(mysqli $c, array $s, string $day, string $start, string $end, array $excludeIds): ?string {
    $excludeIds=array_values(array_unique(array_map('intval',$excludeIds)));
    if(!$excludeIds) return 'The selected class could not be identified.';
    $marks=implode(',',array_fill(0,count($excludeIds),'?'));
    $sql="SELECT s.room_id,s.faculty_id,s.section FROM schedules s WHERE s.day_of_week=? AND s.start_time<? AND s.end_time>? AND s.schedule_id NOT IN ($marks)
          AND (s.room_id=? OR (s.faculty_id=? AND s.faculty_id IS NOT NULL) OR (s.section=? AND ?<>'')) LIMIT 1";
    $q=$c->prepare($sql);
    $types='sss'.str_repeat('i',count($excludeIds)).'iiss';
    $params=array_merge([$day,$end,$start],$excludeIds,[(int)$s['room_id'],(int)$s['faculty_id'],(string)$s['section'],(string)$s['section']]);
    $bind=[]; $bind[]=$types; foreach($params as $k=>$v)$bind[]=&$params[$k]; call_user_func_array([$q,'bind_param'],$bind); $q->execute(); $hit=$q->get_result()->fetch_assoc();
    if(!$hit)return null;
    if((int)$hit['room_id']===(int)$s['room_id'])return 'That classroom is already occupied at the requested time.';
    if($s['faculty_id']!==null && (int)$hit['faculty_id']===(int)$s['faculty_id'])return 'You already have another class at the requested time.';
    return 'The section already has another class at the requested time.';
}

function decide_schedule_change(mysqli $c, int $requestId, bool $approve, string $note): ?string {
    $c->begin_transaction();
    try {
        $q=$c->prepare("SELECT * FROM schedule_change_requests WHERE request_id=? AND status='Pending' FOR UPDATE");
        $q->bind_param('i',$requestId);$q->execute();$r=$q->get_result()->fetch_assoc();
        if(!$r){$c->rollback();return 'This request is no longer pending.';}
        $ids=json_decode((string)($r['schedule_ids']??''),true); if(!is_array($ids)||!$ids)$ids=[(int)$r['schedule_id']];
        $ids=array_values(array_unique(array_map('intval',$ids)));$marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$c->prepare("SELECT * FROM schedules WHERE schedule_id IN ($marks) AND faculty_id=? ORDER BY start_time");
        $types=str_repeat('i',count($ids)).'i';$params=array_merge($ids,[(int)$r['faculty_id']]);$bind=[];$bind[]=$types;foreach($params as $k=>$v)$bind[]=&$params[$k];call_user_func_array([$q,'bind_param'],$bind);$q->execute();$segments=$q->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($segments)!==count($ids)){$c->rollback();return 'One or more assigned class records no longer exist.';}
        $oldMin=PHP_INT_MAX;$oldMax=0;
        foreach($segments as $seg){if($seg['day_of_week']!==$r['old_day']){$c->rollback();return 'The original schedule has changed. Ask faculty to submit a new request.';}$oldMin=min($oldMin,timeToMin($seg['start_time']));$oldMax=max($oldMax,timeToMin($seg['end_time']));}
        if(sprintf('%02d:%02d:00',intdiv($oldMin,60),$oldMin%60)!==$r['old_start'] || sprintf('%02d:%02d:00',intdiv($oldMax,60),$oldMax%60)!==$r['old_end']){$c->rollback();return 'The original schedule has changed. Ask faculty to submit a new request.';}
        if($approve){
            $conflict=schedule_change_conflict($c,$segments[0],$r['new_day'],$r['new_start'],$r['new_end'],$ids);
            if($conflict){$c->rollback();return $conflict;}
            $newMin=timeToMin($r['new_start']);
            $u=$c->prepare("UPDATE schedules SET day_of_week=?, start_time=?, end_time=? WHERE schedule_id=?");
            foreach($segments as $seg){$offset=timeToMin($seg['start_time'])-$oldMin;$dur=timeToMin($seg['end_time'])-timeToMin($seg['start_time']);$a=$newMin+$offset;$b=$a+$dur;$st=sprintf('%02d:%02d:00',intdiv($a,60),$a%60);$en=sprintf('%02d:%02d:00',intdiv($b,60),$b%60);$u->bind_param('sssi',$r['new_day'],$st,$en,$seg['schedule_id']);$u->execute();}
        }
        $status=$approve?'Approved':'Rejected';$u=$c->prepare("UPDATE schedule_change_requests SET status=?,decision_note=?,decided_at=CURRENT_TIMESTAMP WHERE request_id=?");$u->bind_param('ssi',$status,$note,$requestId);$u->execute();$c->commit();
        notify($c,'faculty',(int)$r['faculty_id'],$approve?'success':'warning','Your schedule-change request was '.strtolower($status).'.'.($note!==''?' Chair: '.$note:''),'schedule.php');return null;
    }catch(Throwable $e){$c->rollback();return 'Could not process this request. Please try again.';}
}
