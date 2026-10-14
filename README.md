# EE Workload Management System — Admin + Faculty portal

Two separate areas, one login page:

| Area | URL | Who | Can do |
|---|---|---|---|
| **Admin** | `/admin/` | Department administrators | Everything: courses, classrooms, instructors (incl. their portal logins), build/print schedules, own password |
| **Faculty portal** | `/faculty/` | Instructors | **Read-only** own dashboard + own weekly timetable (printable), edit **own** availability/specialty, change **own** password. Nothing else. |

Each role is bounced to its own area if it opens the other's URL. All admin JSON endpoints in `data/` are admin-only.

## Setup (XAMPP)
1. Copy this folder into `htdocs/ee-workload-system`.
2. phpMyAdmin → import `sql/workload_db.sql`.
3. Run `sql/instructors_migration.sql` once.
4. Run `sql/roles_migration.sql` once (adds the `admins` table + instructor login columns).
5. Open `http://localhost/ee-workload-system/` → **Create the administrator account** (`setup.php`; it locks itself once an admin exists).
6. Admin → **Instructors**: new instructors get a portal login automatically (a temporary password is shown **once**). For existing instructors press **Reset password**. Instructors must choose their own password at first sign-in.

Database settings: `includes/db.php`.

## Security notes
- No demo password any more. Passwords are hashed; 5 failed sign-ins pause login for 60 s.
- Every POST form has a CSRF token; deleting a schedule is now a POST (was a GET link).
- The scheduler checks room **and instructor** double-booking on the server, not just in the browser.
- Before going live: change the MySQL `root` user/password in `includes/db.php`, serve over HTTPS, and delete `setup.php` if you like.

## Removed
`enrollment.php`, `includes/data.php` (the old JSON-file enrollment store) and the unused `Faculty_schedule.php` were dropped: nothing linked to them, and they used a different data source from the rest of the system.
