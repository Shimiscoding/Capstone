# Android Studio implementation prompt

Use this prompt in the Android Studio coding agent for the TOMECO mobile project:

> Review the existing TOMECO Android app without changing its current ticket workflow. Implement reliable capture and upload for the issuing enforcer signature, motorist/violator signature, and one evidence photo.
>
> Backend contract:
> - Authenticated requests use the existing Sanctum bearer token.
> - `GET /api/auth/profile` returns `data.signature`.
> - `PUT /api/auth/signature` accepts JSON `{ "signature": "data:image/png;base64,..." }` and saves the enforcer profile signature.
> - `POST /api/violations` requires the existing ticket fields plus `signature` (motorist signature) and `evidence_image` (photo). Both are data URLs. The server snapshots the logged-in enforcer's saved signature; ticket creation returns HTTP 422 with `errors.enforcer_signature` when the profile signature is missing.
>
> Add an Enforcer Settings screen with a signature pad, Clear and Save signature actions, a preview of the saved signature, loading/error/success states, and persistence through `PUT /api/auth/signature`. Refresh the profile after saving. Disable ticket submission when the profile has no enforcer signature and provide a direct “Set up signature” action.
>
> On the ticket form, add camera/gallery evidence capture with Android runtime permissions, EXIF orientation correction, downscaling to a maximum 1600px edge, JPEG compression around 80%, preview, replace/remove actions, and conversion to `data:image/jpeg;base64,...`. Keep the motorist signature pad and convert it to `data:image/png;base64,...`. Reject blank signature canvases and missing evidence before network submission.
>
> Send the exact keys `evidence_image` and `signature`. Do not send the enforcer signature from the form; it comes from the authenticated profile and is snapshotted by the server. Map Laravel 422 field errors next to the relevant controls, including `enforcer_signature`, `evidence_image`, and `signature`. Prevent duplicate submission, keep captured data after recoverable failures, show upload progress, and only clear the form after HTTP 201.
>
> Follow the app's existing architecture (ViewModel/repository/API service, Compose or XML as already used), avoid introducing a parallel networking stack, add unit tests for data-URL conversion and validation, and add an integration/UI test covering signature setup → evidence capture → ticket creation. Never log tokens or base64 image contents.
