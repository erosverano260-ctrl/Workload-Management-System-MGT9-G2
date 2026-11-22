-- Optional, run once. The sample data stored one Monday class (EE301, BSEE-1A, room 1) as two
-- 30-minute rows (ids 38 and 39). This joins them into a single 7:00-8:00 AM block.
UPDATE `schedules` SET `end_time` = '08:00:00' WHERE `schedule_id` = 38 AND `end_time` = '07:30:00';
DELETE FROM `schedules` WHERE `schedule_id` = 39 AND `start_time` = '07:30:00' AND `course_id` = 2;
