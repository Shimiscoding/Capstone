# TOMECO UX Contract

## Canonical workflow

- Violation records use server pagination and preserve search/filter state in the query string.
- The actions column owns ticket navigation. “View complete ticket” opens a dedicated route; complete records are not shown in a modal.
- A driver name opens a dedicated driver history page. Drivers are grouped by normalized permit number, falling back to normalized full name only when no permit number exists.
- Ticket pages expose driver, permit, vehicle, citation, evidence, driver signature, and issuing-enforcer signature states.
- Citation summaries omit the section line when the citation has no section; recorded section numbers remain visible.
- Missing evidence is stated explicitly and is never represented by a broken image or empty space.
- Mobile multipart evidence uses the complete ordered `evidence_images[]` list (1–5), including a one-element list for a single photo. Only `evidence_images` is stored on the ticket; the redundant `evidence_image` database column is removed after migrating historical photos. Older request-only `evidence_image` inputs normalize into the collection and are ignored when a list is present. New files are encrypted under private storage and served only after authentication and ticket access checks. Historical embedded images remain readable. Source: `Api/ViolationController`, `TicketEvidence`, `TicketEvidenceController`, migration `2026_10_15_000100_consolidate_ticket_evidence`.
- Driver identity and registered-owner identity are independent. Observed and document plates are independent. Missing OR/CR fields stay unrecorded. Drawn `SIG_VECTOR_V1` signatures are rendered from bounded numeric coordinates; images retain their established format. The issuing officer and signature come from the authenticated account and are saved as a snapshot. Source: `Api/ViolationController`, `SignatureImage`, migration `2026_10_12_000300_preserve_mobile_registration_and_evidence`.

## Canonical UI ownership

| Capability | Owner | Behavior |
|---|---|---|
| Data table | `.users-table` with violation variants | Semantic table, internal horizontal overflow, server pagination |
| Actions menu | `.record-actions` | Native details/summary with one navigational action |
| Record detail | `dashboard.violation-ticket-show` | Dedicated page with URL and honest document title |
| Scrollbar | `public/css/dashboard.css` | Global application styling |

## Data integrity

Tickets use `Ticket_issued` for citation details and the total assessed fine, `violation_issued` for common options printed on the form, `violations_type` for legal sections, and `ticket_violation_items` for the selected form-option/section pairs. The API accepts a structured selected-offense list and retains legacy text and type-only submissions. Sections may be unrecorded; no legal reference is guessed. Ticket details show every captured offense and its recorded section, with explicit missing-reference states. Analytics counts distinct tickets per selected form option; several sections within No helmet count once for that ticket. Group shares may overlap. Existing ticket and citation IDs, encrypted values, total fines, and signatures survive normalization. See `TICKET-DATABASE.md` for migration and mobile integration details.

Enforcer duty has separate morning and afternoon records, unique by user/date/session. The existing attendance time fields configure morning duty; `attendance_afternoon_time_*` configures afternoon duty. Working days and the configured application timezone apply to both. Supervisors retain their single daily attendance.

The attendance restriction dialog and its existing time-input rows own schedule editing. Native time controls are accepted. The same dialog displays both duty windows, preserves failed submissions, restores focus to validation feedback, and prevents duplicate saves. Enforcer history uses the shared semantic table with an explicit Duty column.

Attendance history uses `components.attendance-history-table` and `public/js/attendance-history.js` for enforcer and supervisor records. Only admins may edit or delete records, enforced by `AdminAttendanceController` on every request. Delete removes one selected attendance record after an app-owned confirmation naming its date and duty; model events preserve the deletion in the audit log. It never deletes the staff account or other attendance records. If an enforcer has no remaining open duty, location sharing stops. After deletion, navigation returns to that person's history with a valid page and success feedback. Edit/Delete dialogs use native `dialog.showModal()` for focus trapping and background isolation, Escape/cancel returns focus to the trigger, Cancel receives initial deletion focus, duplicate submission is blocked, and failures preserve the entered values. Locations are supporting links under the corresponding timestamps; the table owns horizontal overflow within its card.

The authenticated officer attendance status API keeps `status` and `attendance` and adds `date`, `session`, `schedule`, and `sessions.morning`/`sessions.afternoon`. An open duty takes priority in the top-level status. Completed morning duty does not block afternoon Time In. A duty must be timed out before another starts. A missed Time Out outside its window needs an administrator's attendance correction.

The authenticated API user is the issuing enforcer. Ticket creation requires a driver signature, evidence image, and a saved enforcer profile signature. The enforcer signature is copied onto the ticket at issuance so later profile changes do not rewrite historical records. Source: `routes/api.php`, `Api\\ViolationController`, and violation migrations.

## Accessibility and recovery

The Analytics summary cards have an independent `summary` URL filter: Days covers the last 7 days, Month covers the current calendar month, Year covers the current calendar year, and All covers all records. Both summary and graph controls preserve each other's selections. This card-specific range supersedes the shared-range rule below for the four summary cards; vehicle and violation rankings continue to follow the graph period.

Analytics Reports keeps the existing period buckets from `DashboardController::analytics`: Daily shows 7 days, Monthly shows 12 months, and Yearly shows 5 years. All ticket summaries and rankings use that same issued-date range. Payment status follows the existing ticket detail convention (`dashboard/violation-ticket-show.blade.php`): paid tickets display as resolved and all others as pending. Active Enforcers counts distinct officer accounts attached to issued tickets within the range, not attendance or current online status. Vehicle shares use all period tickets as their denominator and disclose other or missing types. The period form preserves View and Chart type; those choices are stored in the URL. Reports use native select controls, a bounded top-five table, and the shared dashboard shell.

Actions use native links/buttons or summary controls, visible focus, text status alongside color, and named image alternatives. Empty and filtered-empty results provide a recovery instruction.

Section catalog: `violations_type` contains only `section_id`, `description`, and `violation_issued_id`. Ticket entry validates that a section belongs to the selected form option.

Analytics uses one URL-backed range control (Today, Week, Month, Year, All) for cards, chart, vehicle trends, and violation rankings. Today uses Manila midnight, Week starts Monday, Month and Year start at calendar boundaries; ranges end at the current time. The chart has no separate Period selector. Existing View and Chart type selections persist across range changes.

Violation rankings display every recorded form-option group in the selected analytics range, ordered by ticket count, without a top-five limit.

Only enforcers can receive operational areas. Admins assign any enforcer; supervisors assign their own team. User creation/editing and profile editing cannot change areas. Area Assignment is the sole website workflow for those changes.

Profile photos: admins upload in user creation/editing; users upload in their profile or the authenticated self-service API. JPG/PNG/WebP only, max 2 MB, dimensions 32–4096 px. Private photo delivery permits self, admins, and the assigned supervisor. Staff tables, location list/popups, and map markers share photos with initials/status fallback. Replacing/removing a photo deletes the previous file; photo URLs change on replacement.

My Profile uses a compact identity card with a round photo, calm personal-information and password cards, and a read-only account sidebar. The shared photo editor provides a preview, upload/change/remove buttons, and pending-save feedback. Existing account settings and permissions remain unchanged.

The header account-menu avatar uses the same saved profile photo and initials fallback as staff tables, for both admin and supervisor layouts.

User creation and editing reuse the shared photo editor within a compact horizontal identity strip. Section headings use simple ruled dividers, quieter required/optional labels, and a responsive two-column field layout. The form retains existing role, team, and validation behavior.

Area Assignment uses a stable full-width search field with an explicit clear button, an area filter, and a primary Add area action. The compact table uses bounded assignment dropdowns, quieter area badges, and accessible per-enforcer save buttons. Native select behavior and existing assignment permissions remain unchanged.
