# WLC Phase 1 Student Architecture Contract

## 1. Executive Summary
This document serves as the binding architectural and security contract for Phase 1 (Student-First) of the Wahidin Learning Companion (WLC). Based on a forensic gap audit of the current repository, the existing system is strictly an Assistant/Management tool lacking isolated student sessions, identity protection, and the updated content methodologies. This contract defines a **Partial Architecture Replacement** focusing strictly on building the secure Student Lifecycle boundary without disrupting existing Assistant/Parent functionality.

## 2. Current Architecture Evidence
- **Authentication**: `VERIFIED` via JWT.
- **Identity Binding**: `NOT FOUND` on `GET` requests; `VERIFIED` partial implementation on `POST` requests locally, but `FAIL` in production (IDOR present).
- **Session Lifecycle**: `NOT FOUND`. Current data is atomic per row (`observasi` table).
- **Student UI**: `NOT FOUND` in this repository.
- **Methodology (SD vs SMP)**: `NOT FOUND`. All observations are treated equally.
- **Content Constructs**: `VERIFIED` legacy constructs are still active in the database.

## 3. Actor Model
- **Student (`PROPOSED`)**: Authenticated via JWT from external system (e.g. EduPath) mapped via `reference_id`. Owns their sessions and responses. Isolated from other students.
- **Assistant/Observer (`VERIFIED`)**: Authenticated via native login. Performs structured observations on assigned students.
- **Parent (`VERIFIED`)**: Public/Auth-less form submitter (`reflection.html`). Deferred for Phase 1 architectural overhaul.
- **Owner/Evaluator (`VERIFIED`)**: Super-users managing master data.

## 4. Identity & Authorization
**Trace Map (`PROPOSED`)**:
`JWT (role: siswa)` → `reference_id` → `siswa.id` → `wlc_sessions.siswa_id`

- **Student Ownership**: Ownership MUST be strictly derived from `$user['reference_id']` on the backend. Client-provided `siswaId` MUST be rejected or ignored if the role is `siswa`.
- **Cross-Student Read**: `PROPOSED` strict boundary in SQL: `WHERE siswa_id = ?` bound to JWT identity.
- **Cross-Student Write**: `PROPOSED` strict boundary. Payload `siswaId` manipulation must fail.
- **Privilege Escalation**: `PROPOSED` role-based routing wrapper.

## 5. Methodology
The system must explicitly separate data-gathering methodologies (`PROPOSED`):
- **TK / SD**: Structured Observation (Assistant-driven). Handled via existing `dashboard.html` but tracked cleanly.
- **SMP**: Student Self-Report (Questionnaire). Handled via new Student UI.
- **Parent**: Home-learning reflection (Deferred Phase 1).

*Implementation*: The `instrument` and `wlc_sessions` must record which methodology was used to ensure analytics treat self-reports and observations distinctly.

## 6. Instrument Versioning
(`PROPOSED`)
- **Instrument**: A collection of constructs for a specific methodology and tier.
- **Version**: Immutable integer (e.g., `v1`).
- **Construct**: The active observable dimensions.
- **Item**: The specific question/indicator.
- **Historical Immutability**: Modifying an item must create a new Version. Existing sessions bind to the version they were created with.

## 7. Session State Machine
(`PROPOSED`)
- **State: DRAFT**: Session created. Student can update responses.
- **State: SUBMITTED**: Student finalizes session. Changes locked.
- **State: LOCKED**: Evaluated/Processed. Read-only.
- **Rules**:
  - `CREATE`: Requires active student JWT. Binds `siswa_id`, `version`, and `methodology`.
  - `RESUME`: Returns the `DRAFT` session.
  - `DUPLICATE`: Only one `DRAFT` session permitted per student per instrument per timeframe.
  - `CONCURRENT`: Last-write-wins on items, but atomic session lock on submit.

## 8. Response Model
(`PROPOSED`)
- Hierarchy: `Session` → `Item` → `Response`.
- Validation:
  - Session must belong to JWT identity.
  - Session must be `DRAFT`.
  - Item must belong to Session's Instrument Version.

## 9. Autosave/Resume
(`PROPOSED`)
- **Server Authoritative**: State lives in SQLite.
- **Autosave**: Client fires `POST` / `PATCH` per item or batch.
- **Idempotency**: Saving the same item response multiple times updates the existing row in `wlc_responses`.
- **Resume**: Client calls `GET /api/student/session/active` on load to populate the UI.

## 10. Submit/Lock
(`PROPOSED`)
- **Duplicate Submit**: Database atomic transaction sets `status = 'SUBMITTED'`. Subsequent calls fail.
- **Post-Submit Modification**: Any save attempt on `SUBMITTED` session returns `403 Forbidden`.
- **Cross-Student Submit**: Returns `403` or `404` (session not found for user).

## 11. API Contract
(`PROPOSED`)
Prefix: `/api/v2/student/`

1. **GET `/session/active`**
   - Auth: JWT `siswa`
   - Res: Current DRAFT session or null.
2. **POST `/session`**
   - Auth: JWT `siswa`
   - Req: `{ methodology: 'SMP' }`
   - Res: New session ID. Idempotent if DRAFT exists.
3. **GET `/session/{id}/items`**
   - Auth: JWT `siswa` (Ownership validated)
   - Res: List of items and current saved responses.
4. **PUT `/session/{id}/response`**
   - Auth: JWT `siswa`
   - Req: `{ itemId: 12, skor: 4 }`
   - Lock: Fails if session != DRAFT.
5. **POST `/session/{id}/submit`**
   - Auth: JWT `siswa`
   - Lock: Transitions DRAFT → SUBMITTED.
6. **GET `/report/latest`**
   - Auth: JWT `siswa`
   - Res: Narrative report for the latest SUBMITTED session.

## 12. Database Contract
(`PROPOSED`)
Do not duplicate `siswa`. Add new tables:
- `wlc_instruments` (id, version, methodology, status)
- `wlc_constructs` (id, instrument_id, name)
- `wlc_items` (id, construct_id, text)
- `wlc_sessions` (id, siswa_id, instrument_id, status (DRAFT/SUBMITTED), created_at, submitted_at)
- `wlc_responses` (id, session_id, item_id, skor, updated_at)

## 13. Security Contract
- **IDOR**: All queries `WHERE siswa_id = ?` strictly bound to `$user['reference_id']`.
- **JWT Tampering**: Prevented via `JWT_SECRET` HS256 validation.
- **Mass Assignment**: API only accepts `itemId` and `skor`.
- **SQL Injection**: Strict PDO Parameter binding `?`.
- **XSS**: Input sanitization via `htmlspecialchars`.

## 14. Existing Compatibility
- `dashboard.html` (Assistant UI): `KEEP` (Phase 2 update).
- `reflection.html` (Parent UI): `KEEP` (Deferred).
- `share.html` (Report): `ADAPT` (Support new constructs).
- `users`, `siswa`: `KEEP`.
- `observasi`: `ADAPT` (Legacy Assistant data).
- `bank_soal`: `DEPRECATE/REPLACE` with versioned `wlc_items`.
- Monolithic `api.php`: `ADAPT` (Wrap existing, route `/api/v2/` to isolated handlers).

## 15. Hosting Constraints
- Language: Native PHP (No frameworks).
- Frontend: Vanilla JS + HTML5 (No Node/Vue).
- Database: SQLite (`wlc.db`).

## 16. Migration Boundary
1. Implement Database Schema changes (Sessions, Instruments).
2. Seed the 7 Product Constitution constructs.
3. Implement `api/v2/student/*` isolated endpoints.
4. Build `student.html` (Vanilla JS).
5. Do NOT touch legacy `/api/observasi` until Phase 2 (Assistant).

## 17. Security Test Matrix
- **Student A** reads `session` of **Student A**: `PASS` (200 OK)
- **Student A** reads `session` of **Student B**: `MUST FAIL` (404/403)
- **Student A** creates session for **Student B** (payload spoof): `MUST FAIL` (Creates for A)
- **Student A** submits response to `session` of **Student B**: `MUST FAIL` (404/403)
- **Student A** modifies `SUBMITTED` session: `MUST FAIL` (422/403)

## 18. Architecture Decision
**Partial Architecture Replacement**.
*Evidence*: The student lifecycle (sessions, saves, locking, methodology) fundamentally does not exist in the current atomic observation system. It must be built as a parallel, isolated pipeline (`/api/v2/student/`) within the existing PHP/SQLite ecosystem.

## 19. Implementation Phases
- **Phase 1A**: Database Migration & Content Seeding (Constructs).
- **Phase 1B**: Secure API Development (`/api/v2/student`).
- **Phase 1C**: Vanilla JS Frontend (`student.html`).
- **Phase 1D**: Dynamic E2E & IDOR Security Gates.

## 20. Risks/Unknowns
- Legacy data mapping from `bank_soal` to new `wlc_instruments` for historical certificates.

## 21. Acceptance Criteria
- No cross-student IDOR is possible via API manipulation.
- Student lifecycle (Draft -> Submitted) is robust and immutable.
- 7 new Dimensions are active, "Ekspresi Perilaku" is retired.
- Implementation uses strictly Native PHP and Vanilla JS.
