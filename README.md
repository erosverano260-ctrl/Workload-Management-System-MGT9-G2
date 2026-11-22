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

### Excel downloads (long bond, 8.5 × 13 in)
Admin → **Print Forms** has **⬇ Excel: all forms** (one workbook: every section, every instructor, all rooms and the workload matrix, one sheet each) and a button for the current tab (e.g. all rooms). Instructors get **⬇ Download Excel** on their own class-program page. Every sheet is set to long bond (Excel's "Folio 8.5 × 13 in"), fit to one page wide; Room Utilization is landscape. The files are written by `includes/xlsx_writer.php` + `includes/xlsx_forms.php`. The logos and TUV NORD badge are not embedded in the Excel version (the badge text is).

Load maths follows the matrix: lab hours count at 0.7; regular load is 21 units for 2 preparations, 18 for 3–4, 15 for 5+; a course code ending in `L` (e.g. `IT 101L`) is treated as a lab.

## Approvals and notifications (chair <-> faculty)
- **Faculty** request a designation (*My Designations*) or add 1-hour overload sessions (*Overload Sessions*). The chair (admin) is notified straight away.
- **Admin -> Approvals** lists everything waiting: designation requests, and overload sessions grouped per instructor (with *Approve all / Reject all*). Each decision can carry a note. The instructor gets a notification (bell in the sidebar, refreshed every 30 s) and sees the chair's note next to the request.
- **Admin -> Designations** sets each designation's hours and service credit. Anything with *Chair* or *Dean* in its name always earns 0 service credit.
- Nothing to import: the `notifications` table and the `decided_at` / `decision_note` columns are created automatically on first use (`sql/approvals_migration.sql` does the same by hand).

### Load rules (one place: `faculty_load()` in `includes/approvals.php`)
| Item | Rule |
|---|---|
| Total hours | lecture hours + lab hours x 0.7 |
| Number of preparations | each subject counted once (lecture + lab of the same subject = 1, any number of sections) |
| Regular load | 1-2 preps = 21 hrs, 3-4 = 18, 5-6 = 15 |
| Teaching overload | total hours - regular load (never below 0) |
| Overload claim | whole hours of the overload = number of **1-hour** sessions the instructor may designate; the chair can only approve 1-hour sessions, never more than the claim, never overlapping a class |
| Teaching service credit | the part of the overload below 1 hour |
| Service credits | from approved designations, Chair / Dean excluded; extra pay, **not** inside the 40 hours |
| 40-hour week | regular teaching load + designation hours + other functions (research / extension / IMP / consultation fill the rest) = 40. The chair cannot approve a designation that pushes teaching + designations past 40 |

The class program (print and Excel) shows all of it, including a **Claim for Teaching Overload** section with the approved sessions and total session hours.

## Security notes
- No demo password any more. Passwords are hashed; 5 failed sign-ins pause login for 60 s.
- Every POST form has a CSRF token; deleting a schedule is now a POST (was a GET link).
- The scheduler checks room **and instructor** double-booking on the server, not just in the browser.
- Before going live: change the MySQL `root` user/password in `includes/db.php`, serve over HTTPS, and delete `setup.php` if you like.

## Removed
`enrollment.php`, `includes/data.php` (the old JSON-file enrollment store) and the unused `Faculty_schedule.php` were dropped: nothing linked to them, and they used a different data source from the rest of the system.
