---
version: alpha
name: TOMECO
description: Compact operational records for traffic staff in Tacloban.
colors:
  primary: "#b42318"
  background: "#f8fafc"
  surface: "#ffffff"
  text: "#0f172a"
  muted: "#64748b"
  border: "#e2e8f0"
typography:
  sans:
    fontFamily: "Segoe UI, Tahoma, Arial, sans-serif"
rounded:
  control: "6px"
  surface: "8px"
spacing:
  section: "16px"
components:
  table:
    backgroundColor: surface
    textColor: text
  dialog:
    backgroundColor: surface
    textColor: text
---

## Overview

The interface follows an official attendance register: compact rows, readable
timestamps, restrained red navigation, and clear record actions. It serves traffic
staff using the existing English website in the Philippines. The daily pair of
Time In and Time Out windows is the attendance workflow's organizing element.
Preserve the existing product shell rather than adding a separate visual system.

## Colors

Runtime tokens remain owned by `public/css/theme-tokens.css`, imported by both
`dashboard.css` and `auth.css`. This document mirrors
its palette: primary maps to `--accent`, background to `--bg`, surface to
`--panel`, text to `--text`, muted to `--muted`, and border to `--line`.

## Typography

Reuse the existing system sans stack, compact table text, and tabular timestamps.
Labels and explicit status text carry meaning alongside color.

## Layout

Reuse the shared desktop sidebar and narrow overlay navigation. Tables scroll
horizontally within their cards. Attendance settings use the existing responsive
dialog with one Time In row, one Time Out row, and working-day checkboxes.

## Elevation & Depth

Borders organize records. Temporary menus and dialogs use the established shadows.

## Shapes

Keep existing control and surface radii; status badges retain their current shape.

## Components

`components.page-loader` provides the shared navigation loading screen in the
authentication, administrator/cashier, and supervisor layouts. `page-loader.css`
uses the canonical palette and system font: an opaque white surface, the TOMECO
seal, and a small red indeterminate spinner. Its native dialog sits above other
dialogs and keeps background controls inert while navigation is pending. Reduced
motion leaves the indicator static and disables fades. The shared screen fades
in and out over 180ms; navigation itself is never delayed. A per-tab boolean
carries the screen into the destination so it can fade out when the DOM and deferred
scripts are ready. Remote maps and images never hold the entire page behind it.

Attendance history is owned by `components.attendance-history-table`. Schedule
editing is owned by `supervisor.attendance` and `.attendance-restriction-modal`.
Native time and select controls retain platform-owned popups. Dialog validation
shows text errors, retains entered values, and focuses feedback. Escape and Cancel
close the dialog and return focus to its trigger. Existing semantic controls and
focus styles remain canonical. No new animation is introduced.

Authentication uses `components.auth-layout` and `components.auth-field`, with
styles scoped to `.auth-split-page` in `auth.css`. Login, administrator sign-up,
forgot password, and reset password share the same split-screen layout with an
opaque white form panel and the existing red primary action. Below 860px, the
photo panel yields to a single form column. The showcase uses a photo-backed variant: a smaller headline,
one short description, and the existing office footer over the supplied office
photo (`public/images/auth-office.png`). A 60% navy overlay (`--auth-photo-overlay`
in the shared auth showcase) keeps the white text readable without fading the content;
the photo fills the left panel with a centered cover crop.
Dashboard previews and decorative status dots are omitted across these screens.
Registration retains a compact two-column field grid on phones. At the
user's request, auth screens are locked to the dynamic viewport with no
document or panel scrolling. Compact controls and spacing fit ordinary laptop
and phone heights; the shared auth script scales the complete form only when
needed for short viewports or expanded validation feedback, keeping all actions
visible.
The system sans face serves form text; a heavier, tightly spaced display treatment
uses the same face for the showcase headline. Shared tokens map `typography.sans`
to `--font-sans`, `rounded.control` to `--radius-control`, and `rounded.surface`
to `--radius-surface`. Authentication hover/active and focus treatments live in
the scoped auth stylesheet. Password recovery reuses the shared fields, inline
validation, password visibility controls, and busy feedback.

## Do's and Don'ts

Supervisor Home uses three compact summary cards above an area-distribution
register and quick actions, followed by a paginated team directory. Coverage bars
describe assigned members rather than live presence. `supervisor-home.css` owns
this scoped variant and consumes the shared theme tokens; its red accent, system
type, borders, and spacing follow administrator Home. At phone widths summary
cards become compact rows and panels stack; the directory owns horizontal scroll.

The administrator Home uses four compact metric cards, a seven-day bar chart
beside quick actions, and a recent-ticket table. The latest day carries the red
chart accent; earlier bars use a pale version of that same accent. Keep fine
amounts above small tinted status labels, without dots. `home-dashboard.css`
owns this page variant and consumes the canonical theme tokens directly.

Use one daily schedule for new enforcer attendance. Keep historical morning and
afternoon labels truthful. Do not relabel old records, invent missing timestamps,
or introduce a second daily clock-in through the afternoon schedule.
