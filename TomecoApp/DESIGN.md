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

## Do's and Don'ts

Do preserve exact record values and readable status text. Do not hide primary actions behind row-wide click handlers or use modals for complete tickets.
