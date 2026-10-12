---
version: alpha
colors:
  brand: "#8f1d1d"
  brand-soft: "#fff1f1"
  ink: "#263244"
  muted: "#64748b"
  canvas: "#f5f7f9"
  line: "#dfe3e8"
typography:
  sans:
    fontFamily: "Inter, Segoe UI, Arial, sans-serif"
  data:
    fontFamily: "Inter, Segoe UI, Arial, sans-serif"
rounded:
  control: "6px"
  surface: "8px"
spacing:
  compact: "8px"
  section: "16px"
components:
  table:
    density: "compact"
  card:
    elevation: "subtle"
---

## Overview

TOMECO is an operational civic interface for traffic staff. It should feel like a well-kept official record: compact, calm, legible, and trustworthy. The signature element is the ticket number rendered as the primary record anchor. Avoid oversized dashboard typography, decorative gradients, and consumer-app ornament.

## Colors

TOMECO red marks active navigation and primary record links. Neutral ink and ruled surfaces carry most of the interface. Green and amber are reserved for recorded/complete and missing/pending states.

## Typography

Use the existing system-first sans stack. Data tables use 10–12px supporting text with strong labels; page headings remain restrained.

## Layout

Tables prioritize scanning and horizontal stability. Complete records open on dedicated pages with a two-column desktop layout and one-column narrow layout.

## Elevation & Depth

Use borders for structure and subtle shadows only for temporary menus.

## Shapes

Controls use 6px corners; record surfaces use 8px corners. Pills are reserved for statuses.

## Components

Tables expose one explicit actions column. Evidence and signature panels always show a visible Recorded or Not recorded state.

Analytics Reports uses four compact summary cards, the existing red/blue activity chart, and overlapping vehicle circles as its signature. Period, View, and Chart type belong inside Performance Overview. Violation rankings use a semantic table with horizontal scrolling on narrow screens. The three vehicle circles show the leading types, with full labels and counts repeated below them and an explicit remainder for other or unrecorded types.

The shared shell and tokens remain owned by `public/css/dashboard.css`; `public/css/analytics-reports.css` supplies report layout variants using its canvas, panel, text, muted, border, and accent variables. Chart series retain the existing Analytics palette. Report controls accept native select popups and their keyboard behavior.

## Do's and Don'ts

Do preserve exact record values and readable status text. Do not hide primary actions behind row-wide click handlers or use modals for complete tickets.

Analytics uses one URL-backed range control (Today, Week, Month, Year, All) for cards, chart, vehicle trends, and violation rankings. Today uses Manila midnight, Week starts Monday, Month and Year start at calendar boundaries; ranges end at the current time. The chart has no separate Period selector. Existing View and Chart type selections persist across range changes.

My Profile uses a compact identity card with a round photo, calm personal-information and password cards, and a read-only account sidebar. The shared photo editor provides a preview, upload/change/remove buttons, and pending-save feedback. Existing account settings and permissions remain unchanged.

User creation and editing reuse the shared photo editor within a compact horizontal identity strip. Section headings use simple ruled dividers, quieter required/optional labels, and a responsive two-column field layout. The form retains existing role, team, and validation behavior.

Area Assignment uses a stable full-width search field with an explicit clear button, an area filter, and a primary Add area action. The compact table uses bounded assignment dropdowns, quieter area badges, and accessible per-enforcer save buttons. Native select behavior and existing assignment permissions remain unchanged.
