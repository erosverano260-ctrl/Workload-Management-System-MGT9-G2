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

## Printed forms (copied from the reference workbooks)
Admin → **Print Forms** has four tabs; the faculty portal has the first one for the signed-in instructor only. Every form carries the logos, reference-code box and the **TUV NORD** badge in the upper-right corner.

| Form | Paper | Based on |
|---|---|---|
| Instructor's Class Program (timetable + workload + signature block) | 8.5 × 13 in | INSTRUCTORS-CLASS-PROGRAM-SEM2 |
| Student's Class Program per section (+ subjects for loading) | Letter | BSIT_CLASS_LIST_PER_SECTION-SEM2 |
| Room Utilization (all rooms, or one) | 13 × 8.5 in landscape | ROOM-UTILIZATION-SEM-2 |
| Faculty Workload Matrix (admin only) | A4 | HAIDE_FACULTY_WORKLOAD-MATRIX |

School name, college, department, program, term / S.Y., reference codes, revision date and prepared-by wording live in `includes/print_config.php`. Logos are in `assets/` (`logo-pit.png`, `logo-cote.png`, `tuv-nord.png`). In the print dialog turn on "Background graphics" so the cell colours print.

Load maths follows the matrix: lab hours count at 0.7; regular load is 21 units for 2 preparations, 18 for 3–4, 15 for 5+; a course code ending in `L` (e.g. `IT 101L`) is treated as a lab.

## Security notes
- No demo password any more. Passwords are hashed; 5 failed sign-ins pause login for 60 s.
- Every POST form has a CSRF token; deleting a schedule is now a POST (was a GET link).
- The scheduler checks room **and instructor** double-booking on the server, not just in the browser.
- Before going live: change the MySQL `root` user/password in `includes/db.php`, serve over HTTPS, and delete `setup.php` if you like.

## Removed
`enrollment.php`, `includes/data.php` (the old JSON-file enrollment store) and the unused `Faculty_schedule.php` were dropped: nothing linked to them, and they used a different data source from the rest of the system.
