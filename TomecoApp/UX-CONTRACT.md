# TOMECO UX Contract

## Canonical workflow

- Violation records use server pagination and preserve search/filter state in the query string.
- The actions column owns ticket navigation. “View complete ticket” opens a dedicated route; complete records are not shown in a modal.
- A driver name opens a dedicated driver history page. Drivers are grouped by normalized permit number, falling back to normalized full name only when no permit number exists.
- Ticket pages expose driver, permit, vehicle, citation, evidence, driver signature, and issuing-enforcer signature states.
- Missing evidence is stated explicitly and is never represented by a broken image or empty space.

## Canonical UI ownership

| Capability | Owner | Behavior |
|---|---|---|
| Data table | `.users-table` with violation variants | Semantic table, internal horizontal overflow, server pagination |
| Actions menu | `.record-actions` | Native details/summary with one navigational action |
| Record detail | `dashboard.violation-ticket-show` | Dedicated page with URL and honest document title |
| Scrollbar | `public/css/dashboard.css` | Global application styling |

## Data integrity

The authenticated API user is the issuing enforcer. Ticket creation requires a driver signature, evidence image, and a saved enforcer profile signature. The enforcer signature is copied onto the ticket at issuance so later profile changes do not rewrite historical records. Source: `routes/api.php`, `Api\\ViolationController`, and violation migrations.

## Accessibility and recovery

Actions use native links/buttons or summary controls, visible focus, text status alongside color, and named image alternatives. Empty and filtered-empty results provide a recovery instruction.
