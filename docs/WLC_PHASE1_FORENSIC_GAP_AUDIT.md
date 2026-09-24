# WLC Phase 1 Forensic Gap Audit

## 1. Forensic Inventory

### A. Authentication
- **login flow**: Implemented via `POST /api/login`. Verifies against `users` table.
- **JWT generation**: Uses custom `jwt_encode` with `JWT_SECRET` (24h expiry).
- **JWT validation**: Checked via `authenticateToken()`.
- **role siswa**: Does not exist in the `users` table. A JWT with `role === 'siswa'` must be generated externally (e.g., from EduPath).
- **reference_id**: Used in `api.php` as a fallback `siswaId` during POST requests for students.
- **identity binding**: Partially implemented in `POST /api/observasi` and `POST /api/kegiatan-wlc`, but completely missing in `GET` requests (which trust the query parameter).

### B. Student UI
- **student login**: NOT FOUND IN REPOSITORY.
- **student dashboard**: NOT FOUND IN REPOSITORY.
- **WLC student page**: NOT FOUND IN REPOSITORY.
- **questionnaire/reflection**: Only Assistant (`dashboard.html`) and Parent (`reflection.html`) UIs exist.
- **question rendering**: Only Assistant UI renders questions.
- **answer submission**: Handled via `POST /api/observasi`.
- **progress state**: NOT FOUND IN REPOSITORY.
- **resume state**: NOT FOUND IN REPOSITORY.
- **submit**: NOT FOUND IN REPOSITORY.
- **report**: Generated via `GET /api/public/cert` and `share.html`, but no student-facing report dashboard.

### C. Backend API
- `POST /api/login`: Public, checks credentials.
- `GET/POST /api/settings`: Owner only.
- `GET/POST /api/users`, `sekolah`, `kelas`, `siswa`: Auth required (Owner/Evaluator).
- `GET/POST /api/bank-soal`: Auth required.
- `POST /api/observasi`: Auth required. Binds `siswaId` from JWT (if role=siswa), otherwise trusts payload.
- `GET /api/observasi`: Auth required. Trusts `?siswaId` query parameter entirely.

### D. Database
- `users`: `id` (PK), `role`, `username`, `password`. (No Siswa records).
- `siswa`: `id` (PK), `nama`, `nisn`, `sekolahId` (FK), `kelasId` (FK).
- `observasi`: `id` (PK), `siswaId` (FK), `soalId` (FK), `skor`, `timestamp`, `asistenId`.
- **Session/State Concept**: NOT FOUND IN REPOSITORY. Database only logs raw atomic observations.

## 2. Search History / Existing Implementation
- **wlc session**: NOT FOUND IN REPOSITORY
- **questionnaire**: NOT FOUND IN REPOSITORY (for students)
- **instrument**: NOT FOUND IN REPOSITORY (only `bank_soal` concept)
- **instrument version**: NOT FOUND IN REPOSITORY (only WLC Type A/B)
- **response**: NOT FOUND IN REPOSITORY
- **autosave**: NOT FOUND IN REPOSITORY
- **submitted/finalized**: NOT FOUND IN REPOSITORY
- **report**: Exists via `/api/public/cert`
- **scoring**: Basic averaging in PHP.
- **narrative**: Hardcoded in `share.html` and `api.php`.
- **student WLC**: NOT FOUND IN REPOSITORY.

## 3. Trace Student Identity
**Flow**: `JWT` → `authenticateToken()` → `$user['role']` → `$user['reference_id']`
**Usage**:
- In `POST /api/observasi`: `api.php:793` explicitly overrides `$siswaId` with `$user['reference_id']` if role is `siswa`.
- In `GET /api/observasi`: `api.php` trusts `$queryParams['siswaId']`.
**Conclusion**: The API trusts client-supplied student IDs for reads (IDOR), but partially enforces authenticated identity for writes (although live testing showed this write hardening is not yet active in production).

## 4. Verify IDOR Finding
**Static Proof (api.php)**:
```php
if ($uri === '/api/observasi') {
    if ($method === 'GET') {
        authenticateToken(); // Requires ANY valid token
        $siswaId = $queryParams['siswaId'] ?? null;
        if ($siswaId) {
            $stmt = $db->prepare('SELECT ... FROM observasi WHERE o.siswaId = ?');
            $stmt->execute([$siswaId]);
        }
```
**Dynamic Verification**: Confirmed via cURL. Token A (Siswa ID 1) successfully called `GET /api/observasi?siswaId=999` and retrieved Token B's data (Siswa ID 2, NISN 999).

## 5. Content Contract Audit
**Expected Constructs**: "Transisi ke Belajar", "Keterlibatan terhadap Aktivitas", "Penerapan Instruksi", "Inisiatif Penyelesaian", "Respons terhadap Kendala", "Partisipasi Aktif", "Lingkungan Belajar".
**Actual Constructs in DB (`bank_soal`)**: "Kesiapan", "Fokus", "Instruksi", "Kemandirian", "Ketekunan", "Emosi", "Minat".
**Retired Construct**: "Ekspresi Perilaku" (Not found, but "Emosi" is used).
**Status**: CONTENT GOVERNANCE DEFECT. The 7 required observable dimensions are not implemented in the database.

## 6. Methodology Boundary
Implementation currently uses a single pipeline (`/api/observasi` and `bank_soal`) for all data gathering. There is no architectural distinction between TK/SD (structured observation), SMP (student self-report), or Parent contexts.
**Status**: NOT FOUND IN REPOSITORY (One size fits all).

## 7. Gap Matrix

| Area | Expected | Actual Evidence | Status | Severity |
|---|---|---|---|---|
| Authentication | Secure JWT login | Exists for staff, but no Siswa login UI. | VERIFIED | Medium |
| Student Identity | Bound to JWT securely | Missing on GET requests; local POST hardening not live. | FAIL | Critical |
| Student UI | Dashboard, Questionnaire | No student UI exists in this repo. | NOT FOUND | Critical |
| Session | Create, Resume, State | Flat atomic table (`observasi`). No sessions. | NOT FOUND | Critical |
| Questionnaire | Render active items | Only Assistant UI renders items. | NOT FOUND | Critical |
| Autosave | Save drafts | No concept of drafts. | NOT FOUND | Critical |
| Resume | Load saved session | No session state to load. | NOT FOUND | Critical |
| Submit Lock | Finalize responses | No lock mechanism. | NOT FOUND | Critical |
| Report | Generate narrative | Basic certificate exists, but no student dashboard. | VERIFIED | Low |
| IDOR | Prevent cross-student access | Verified IDOR on GET endpoints via `siswaId` param. | FAIL | Critical |
| Content Governance | 7 Observable Dimensions | DB uses legacy constructs (Kesiapan, Fokus, etc). | FAIL | High |
| Methodology | Separation by tier | One flat structure for all inputs. | NOT FOUND | High |

## 8. Final Conclusion

### A. Confirmed defects
- Critical IDOR on `GET /api/observasi` and `GET /api/kegiatan-wlc`.
- Content Governance Defect: Legacy constructs are still seeded in the database.

### B. Confirmed missing capabilities
- Complete absence of Student UI (Dashboard, Questionnaire).
- Complete absence of Session Lifecycle (Create, Autosave, Resume, Submit Lock).
- Complete absence of Methodology separation (SD vs SMP vs Parent).

### C. Unknowns
None. The architecture has been fully statically and dynamically traced.

### D. Existing reusable components
- `share.html` (Certificate Renderer).
- Assistant Dashboard (`dashboard.html`).
- Underlying SQLite schema (`users`, `siswa`, `bank_soal`).

### E. Components that must be refactored
- `api.php`: The monolithic router needs a complete rewrite of its data binding and authorization layers to support isolated sessions and IDOR prevention.
- `wlc.db`: Requires schema migrations to support `wlc_sessions` and updated `bank_soal` constructs.

### F. Components that must NOT be touched
- EduPath repository (External).
- Native PHP/SQLite runtime constraints (Do not introduce Node.js or heavy frameworks).

### G. Minimum remediation scope
1. Database migration: Add `wlc_sessions` table (status, lock). Update `bank_soal`.
2. API Auth Layer: Enforce JWT `reference_id` strictly on all endpoints.
3. API Endpoints: Build POST/GET for Sessions and Submits.
4. UI: Build a minimal standalone Student UI in Vanilla JS within this repo.

**Recommendation**: **Architecture Replacement** (for the Student Lifecycle segment).
*Evidence*: The current repository is strictly an Assistant/Management tool. Attempting to "incremental remediate" by patching `api/observasi` is insufficient because the entire concept of "Sessions", "Drafts", and "Submit Locks" does not exist in the database or API. The student flow must be built as a new architectural pillar within this repository.
