# EE Workload Management System

1. Copy this folder into `htdocs` (XAMPP), e.g. `htdocs/ee-workload-system`.
2. phpMyAdmin: import `sql/workload_db.sql` (creates database `workload_system`) if you do not already have it.
3. phpMyAdmin: run `sql/instructors_migration.sql` once (adds instructor columns + starter instructors).
4. Open http://localhost/ee-workload-system/login.php — demo login: any username / password `faculty123`.
5. Hard-refresh (Ctrl+F5) the first time.

Database settings: `includes/db.php`.
