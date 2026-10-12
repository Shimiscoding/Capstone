# Ticket database and mobile API

The four tables are `Ticket_issued`, `violation_issued`, `violations_type`, and
`ticket_violation_items`.
`users.id` remains the issuing officer account referenced by `Ticket_issued.enforcer_id`.
No additional driver or vehicle tables are introduced; the existing ticket captures
driver and vehicle details at issuance. The existing `Violation` model and routes
remain available for compatibility and now represent a row in `Ticket_issued`.

## Ownership

| Table | Owns |
| --- | --- |
| `Ticket_issued` | Ticket ID, issuing enforcer, driver/vehicle details, location, issued timestamp, payment status, total assessed fine, encrypted evidence and signatures |
| `violation_issued` | Common options printed on the real form: ID, label, sort order |
| `violations_type` | `section_id` (primary key), `description`, `violation_issued_id` (related form option) |
| `ticket_violation_items` | Ticket ID, selected form-option ID, selected section ID, captured offense text, remarks |

`Ticket_issued` has many `ticket_violation_items`. Each item references one common
form option and optionally one legal section. Selecting two offenses creates two
items while the driver, vehicle, officer, date, and total fine are stored once.
Each section belongs to one form option through `violation_issued_id`. The API rejects mismatched form/section pairs. A section not recorded on a ticket is nullable.

The fine is the assessed **total for the ticket**, retained exactly for existing tickets.
It is not a per-section rate. In particular, a historical ticket with five checkbox
offenses does not justify inventing five separate fine amounts. Fine suggestions in
the existing Settings screen remain configuration, not statutory catalog rates.

Each ticket/form-option/section pair is unique, including form-only selections.
An indexed generated `section_key` treats a null section as zero for uniqueness;
it is not entered by the client. Ticket deletion cascades to its cited rows; a type
referenced by a ticket cannot be deleted. Description snapshots intentionally
preserve what was cited if the catalog description changes later.

## Mobile entry

`GET /api/violation-form-options` returns the 13 printed form options,
including Others, with their IDs and labels. `GET /api/violation-types` returns
legal-section entries. Both require the existing Sanctum authentication.
The section endpoint also supplies `form_option_records` and the old label-only
`form_options` field. Filters: `form_group` (related form option), `search`,
and `page`. Results are paginated (100 entries per page); follow `meta.last_page`.
Use the short real-form checklist; fetch matching choices for an ambiguous group.
Use the catalog search for Others. A null section on a ticket means the section was not recorded; do not infer one in the mobile app.

Submit one ticket to `POST /api/violations`, keeping the existing driver, vehicle,
fine, evidence, and signature fields and adding:

```json
{
  "violations": [
    {"violation_issued_id": 3, "violations_type_id": 7, "remarks": "Optional details"},
    {"violation_issued_id": 8, "violations_type_id": null}
  ]
}
```

IDs above are illustrative: use IDs returned by the catalog endpoint. Submit only
selected offenses. An empty list, repeated pair or nonexistent ID is
rejected. Do not send `violation_type` together with `violations`. The authenticated
user supplies the enforcer identity and saved signature as before. Issuance is one
transaction. Responses retain the old `violation_type` display text and add
`issued_violations`, which now contains `TicketViolationItem` records with each
referenced `type` and `form_option`. The response relation name is retained for
compatibility. Selecting Others without a section requires descriptive remarks.
Earlier type-only structured submissions still work using convenience form defaults.

Old clients may continue submitting `violation_type` as text. Known comma-separated
checkbox lists become individual unclassified offenses. A description containing
commas is preserved whole unless every component is a recognized form label. Old
labels are never automatically assigned a legal section. Updating a ticket with
recorded sections or multiple offenses requires the structured list.

There is no mobile application source or web ticket-entry form in this repository.
The backend supports the checklist workflow; the separate mobile app must adopt
the catalog endpoint and structured payload to record exact sections.

## Analytics

Ticket totals, officer counts, vehicle counts, and payment status use `Ticket_issued`.
Violation rankings join `ticket_violation_items` to `violation_issued` and group by
the selected form-option ID and label, independently of the section catalog.
Each ticket counts once per group, even if it cites two sections in that group.
Different No helmet sections therefore share one No helmet row. A ticket containing
No helmet and Illegal parking appears in both rows; percentages can exceed 100%
when added together. The remainder counts distinct tickets outside the displayed
groups rather than subtracting overlapping group counts.

## Catalog provenance and migration

All 73 supplied descriptions remain verbatim in `violations_type`. The catalog has exactly three columns: `section_id`, `description`, `violation_issued_id`. Legacy placeholder entries are removed; their ticket snapshots remain and their section links become null. Sections without a supported common form mapping belong to Others. The supplied list has no No helmet section.
No current legal validity or fine schedules are asserted by these seed entries.
Existing unknown form labels are filed under Others with their original cited text
preserved. Specific sections remain
visible on ticket details and searchable in the catalog.

The migrations rename the old table in place and move offense labels into linked
rows. This preserves IDs, timestamps, encrypted bytes, total fines, and officer
references. `scripts/inspect_ticket_database.php --backup` saves an encrypted local
snapshot under `storage/app/private/ticket-backups` before migration. Restoring that
snapshot needs the same application encryption key. The normalization migration's
rollback reconstructs the old text field from cited description snapshots.
Recorded ordinance/section references and remarks are included in the reconstructed
text so a rollback does not silently discard those details.

The four-table correction is a new forward migration. It renames the previous
offense table to `ticket_violation_items`, preserves citation IDs, type references,
timestamps and snapshots, then creates `violation_issued` as the form-option
catalog. It removes the duplicated `form_group` column from `violations_type`.
Before this correction, the backup script captures ticket rows, cited rows, catalog
rows, schemas, indexes and foreign keys. `--summary --verify` compares the preserved
values with the latest backup. The four-table rollback restores the previous
layout; it refuses a rollback if multiple form options for one section would be lost.

The simplification migration replaces old catalog IDs with actual section numbers and preserves all ticket snapshots. Clients must refresh their section catalog: `violations_type_id` now refers to `section_id`. Catalog keys, ordinance codes, active flags, and timestamps are removed from the section table. The encrypted backup retains the old metadata.

Approved section grouping: Driving without D/L (20, 21); Unregistered Vehicle (19); Illegal parking (7, 50); Disregarding Traffic Sign (38, 42, 43, 44); Obstruction (16, 48, 49, 71); Defective Head Light (5). Remaining supplied sections belong to Others. Sec. 5 is assigned to Defective Head Light by the user despite covering other accessories. Existing ticket selections stay unchanged, so catalog regrouping does not rewrite historical analytics.

Form options no longer have an active flag: every stored option is selectable.
