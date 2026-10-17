# Violation group audit — 15 October 2026

Scope: retain all 13 printed TOMECO ticket groups and all 73 supplied descriptions,
without inventing section references, changing assessed fines, or rewriting issued
tickets. The group IDs remain unchanged; 13 is the ID of Others, not its count.

## Basis and limits

`database/data/violation_sections.php` is a user-supplied transcription. Its header
explicitly does not establish current legal validity or statutory fines. This review
compares that text with the printed group labels. It is an application mapping,
not an official certification that the section numbers or groups are legally correct.

The [City of Tacloban ordinance directory](https://tacloban.gov.ph/ordinance/) and
[2024 Citizen's Charter](https://tacloban.gov.ph/wp-content/uploads/2024/07/CC-2024-3rd-Edition.pdf)
were checked for source references. They do not supply a verified crosswalk between
these 73 entries and the 13 ticket checkboxes. Before claiming official accuracy,
obtain the current ordinance schedule and an approved TOMECO section-to-checkbox list.

## Result for the supplied catalog

| Printed group | Sections | Count |
| --- | --- | ---: |
| Driving without D/L | 20 | 1 |
| Unregistered Vehicle | 19 | 1 |
| Illegal parking | 7, 50 | 2 |
| Disregarding Traffic Sign | 38, 42, 43, 44 | 4 |
| Obstruction | 16, 48, 49, 71 | 4 |
| Truck Ban | No supplied section explicitly establishes a truck ban | 0 |
| Operating Along National Highway | No supplied section explicitly establishes this restriction | 0 |
| No helmet | No matching description supplied | 0 |
| Defective Head Light | Section 5 covers additional accessories and cannot be classified entirely here | 0 |
| Anti-Littering | No matching description supplied | 0 |
| Anti-Smoking | No matching description supplied | 0 |
| Anti-Urinating and Defecating | No matching description supplied | 0 |
| Others | All remaining sections, reviewed below | 61 |

Section 19 is treated as the registration category because its supplied wording
concerns invalid registration and current registration plates. This does not make
every plate-related offense an unregistered-vehicle offense.

## Review of the remaining 61 sections

These sets cover every remaining section exactly once. Others is a valid printed
category for offenses outside the twelve specific labels; its size is not a defect.

| Sections | Why the supplied description does not fit one specific printed group |
| --- | --- |
| 1, 6, 25, 26, 27, 28, 30, 31, 32, 33, 34, 35, 37, 39, 41 | Right of way, signaling, turns, overtaking, lane use, crossing or slowing rules; the descriptions do not establish disregard of a posted sign |
| 2, 11, 45 | Unsafe passenger carriage or excess passengers |
| 3 | Combines driving and parking on a sidewalk; assigning the entire entry to parking would lose the driving alternative |
| 4, 10 | Plate visibility or unauthorized improvised plates does not by itself establish unregistered status |
| 5 | Combines defective headlights, taillights, stop lights, wipers and other accessories; a headlight-only checkbox is too narrow |
| 8 | Failure to carry a registration receipt does not by itself establish an unregistered vehicle |
| 9, 12, 13, 36, 51, 52, 53, 57, 58, 62 | Vehicle condition, noise, brakes, lighting, smoke or unauthorized equipment; these are not exclusively defective headlights |
| 14, 56, 66 | Cargo capacity or truck markings does not establish a truck ban |
| 15, 29, 40, 63 | Reckless driving, racing, speeding or impairment |
| 17, 18, 21, 22, 23, 59, 60, 64, 65, 68, 69 | License signature, validity, possession, fraud, class, production, conductor permits or licensing misuse; none is identical to driving without first securing a driver's license |
| 24, 55 | Private/for-hire use or commercial identification |
| 46, 47 | Prohibited-zone loading or passenger solicitation does not explicitly establish obstruction or a posted traffic sign |
| 54 | Highway use with metallic tires without a permit; not a general national-highway restriction |
| 61 | Uncovered cargo does not explicitly describe littering or spillage |
| 67 | Conduct toward authorities or passengers, including bribery |
| 70 | Prohibited route is not necessarily a national highway or truck ban |
| 72, 73 | Refusal to convey passengers or excess fares |

## Applying the correction

`2026_10_15_000000_correct_ambiguous_violation_groups` moves only the supplied
section 5 and section 21 records from their previous broad groups to Others.
It preserves IDs, descriptions, the 13 printed options, ticket rows, selected
historical groups, captured offense text, evidence, signatures and fines.
Locally edited descriptions and custom group mappings are left for manual review.

Refresh mobile catalog caches after applying the migration. A client must use the
current `violation_issued_id` returned for its selected section. The API continues
to reject mismatched section/group pairs. A printed option with no supplied section
can still be selected as a form-only citation with a null section; no section number
should be inferred from a different offense.
