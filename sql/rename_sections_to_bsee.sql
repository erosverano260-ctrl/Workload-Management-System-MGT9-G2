-- Run once if your database already has classes saved with the old BSIT section names.
-- Turns BSIT-1A into BSEE-1A, BSIT-2B into BSEE-2B, and so on. Nothing else is touched.
UPDATE `schedules` SET `section` = REPLACE(`section`, 'BSIT-', 'BSEE-') WHERE `section` LIKE 'BSIT-%';
