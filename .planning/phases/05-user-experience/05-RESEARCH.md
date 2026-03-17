# Phase 5: User Experience - Research

**Researched:** 2026-03-17
**Domain:** PHP server-side list filtering/sorting, scheduled row lifecycle, edit form layout customization
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Search model**
- Single text search box on the list view: LIKE '%term%' across all fields where `is_visible = 1`
- Submit button to trigger search (server-side, page reload — consistent with all other Snow pages)
- "Advanced search" toggle next to the search box: reveals a per-field input row where each visible field gets its own text input for field=value (LIKE) searches
- Advanced search and simple search are mutually exclusive (one or the other active at a time)

**Sort model**
- Clickable column headers sort the list: clicking sets `?sort=field_name&dir=asc` (or `dir=desc`) GET params
- Page reloads with sorted results — no JS required
- Sort applies after ACL filtering (sort on the filtered result set, not the raw DB result)

**Rendering approach**
- Everything is server-side with GET params: `?q=term&sort=field&dir=asc&status=active`
- No AJAX, no live filtering — consistent with the framework's established pattern
- GET params are preserved across sort/filter interactions (search term survives a column sort click)

### Claude's Discretion

- Advanced search toggle implementation (collapse/expand — Bootstrap collapse or plain CSS show/hide)
- Whether advanced search uses a separate form or inline within the main search form
- Column sort indicator (e.g. ▲/▼ arrows in header) styling
- Pagination approach for large result sets (if any)
- Filter config UI (SC2: visible columns + filter controls per table) — implement in admin-tables.php fields view; no separate config screen needed
- Edit form layout (SC3): `display_order` on `custom_table_fields` already drives field sequence; column width (half vs full row) can be a new `col_width` field or Claude's choice
- EXT-04 (staged activation): no existing scheduler — will need new `activate_at`/`deactivate_at`/`delete_at` columns on each custom table and a trigger mechanism (cron or on-page-load check); left to Claude's Discretion

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATA-02 | Admin can add custom fields to any custom table via admin UI | Fields view in admin-tables.php already handles field CRUD; SC2 (visible columns + filter controls) is an extension of the existing `is_visible` flag management |
| DATA-03 | Admin can customize the edit form layout for each table | `display_order` already exists on `custom_table_fields`; adding `col_width` column enables half/full row control |
| DATA-04 | User can search, sort, and filter records on any list view using UI controls | List view in admin-custom-table.php (lines 620–673) is the single extension target; WHERE clause built from GET params with allowlisted field names |
| EXT-04 | Admin can schedule row activation, deactivation, or deletion to occur on a specific future date | Requires new columns on every custom table + a scheduler entry point; on-page-load check pattern chosen (no external cron dependency) |
</phase_requirements>

---

## Summary

Phase 5 completes the v1 feature set by adding UX controls to the generic list view and enabling time-based row lifecycle management. The technical scope falls entirely within PHP/MySQL — no external libraries, no JavaScript frameworks, consistent with the zero-dependency mandate.

**Search and sort** are pure GET-param query construction. The list view already fetches all rows and applies ACL filtering; the change is to build a parameterized WHERE clause from `?q=` and `?adv[field]=` GET params before the SELECT, then ORDER BY a validated field name. The `is_visible` flag on `custom_table_fields` already identifies which columns participate in both display and search.

**Staged activation (EXT-04)** is the only area requiring new infrastructure. Because the framework has no background process mechanism, the cleanest zero-dependency approach is an on-page-load check: when any custom table list view loads, the framework calls a `processScheduledActions()` function that scans for rows where `activate_at <= NOW()`, `deactivate_at <= NOW()`, or `delete_at <= NOW()` and applies the appropriate status changes or deletes. This pattern is idempotent, requires no cron setup, and stays within the LAMP constraint.

**Primary recommendation:** Extend `admin-custom-table.php` list view section with WHERE-clause builder and sortable `<th>` links; add `activate_at`/`deactivate_at`/`delete_at` columns to custom tables via migration + `migrateExistingCustomTables()`; add `col_width` to `custom_table_fields`; process scheduled actions on list-view load.

---

## Standard Stack

### Core (already in project — no new dependencies)

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| PDO / MySQL | existing | Parameterized WHERE clause building | Already used for all DB access |
| Bootstrap 5 | existing | `collapse` component for advanced search panel, sort arrow icons | Already in all templates |
| PHP GET params | existing | Search/sort/filter state transport | Established pattern across all Snow pages |

### No New Libraries

This phase is intentionally zero-new-dependency. All required functionality (SQL LIKE, ORDER BY, DATETIME columns, Bootstrap collapse) is already available in the stack.

---

## Architecture Patterns

### Recommended File Changes

```
functions/
├── admin-custom-table.php   # Primary change: list view section extended
│                            #   + WHERE builder, sortable <th>, advanced search panel
│                            #   + schedule fields in add/edit forms
│                            #   + processScheduledActions() call on list load
├── admin-tables.php         # SC2: fields view gets col_width editing
│                            # SC3: no code change — display_order already used
database_schema.sql          # Phase 5 migration block appended at bottom
tests/
└── test_user_experience.php # New test file (Wave 0 stubs)
```

### Pattern 1: WHERE Clause Builder from GET Params

**What:** Dynamically build parameterized SQL WHERE/ORDER from validated GET params
**When to use:** Every list view load in admin-custom-table.php
**Key safety rule:** Field names for sort/search MUST be validated against the `$fields` array fetched from `custom_table_fields` — never pass raw GET values into SQL

```php
// Source: project pattern (inline with existing dbGetRows usage)

// Fetch visible fields for this table
$fields = dbGetRows(
    "SELECT * FROM custom_table_fields WHERE table_name = ? AND status = 'active' AND is_visible = 1 ORDER BY display_order, field_name",
    [$tableName]
);
$allowedFields = array_column($fields, 'field_name');

// Build WHERE clause
$whereParts = ['1=1'];
$whereParams = [];

// Simple search: LIKE across all visible fields
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $likeParts = [];
    foreach ($allowedFields as $col) {
        $likeParts[] = "`{$col}` LIKE ?";
        $whereParams[] = '%' . $q . '%';
    }
    if ($likeParts) {
        $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
    }
}

// Advanced search: per-field LIKE
$adv = (array)($_GET['adv'] ?? []);
foreach ($adv as $col => $val) {
    $val = trim($val);
    if ($val !== '' && in_array($col, $allowedFields, true)) {
        $whereParts[] = "`{$col}` LIKE ?";
        $whereParams[] = '%' . $val . '%';
    }
}

// ORDER BY — validate against allowlist + standard cols
$sortableFields = array_merge($allowedFields, ['id', 'status', 'created_at', 'modified_at']);
$sort = in_array($_GET['sort'] ?? '', $sortableFields, true) ? $_GET['sort'] : 'id';
$dir  = strtolower($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';

$whereClause = implode(' AND ', $whereParts);
$allRows = dbGetRows(
    "SELECT * FROM `{$tableName}` WHERE {$whereClause} ORDER BY `{$sort}` {$dir}",
    $whereParams
);
$rows = filterRowsByViewAccess($allRows);
```

### Pattern 2: Sortable Column Header Links

**What:** `<th>` renders as an anchor that sets `?sort=field&dir=asc` while preserving all other GET params
**Key detail:** Must carry `q`, `adv[]`, and status filter params through the link — or all state is lost on sort click

```php
// Build a sort link preserving current search state
function sortLink(string $field, string $label, array $currentGet, string $currentSort, string $currentDir): string {
    $params = array_filter([
        'q'      => $currentGet['q'] ?? '',
        'adv'    => $currentGet['adv'] ?? [],
        'status' => $currentGet['status'] ?? '',
    ]);
    $newDir = ($currentSort === $field && $currentDir === 'ASC') ? 'desc' : 'asc';
    $params['sort'] = $field;
    $params['dir']  = $newDir;
    $arrow = '';
    if ($currentSort === $field) {
        $arrow = $currentDir === 'ASC' ? ' &#9650;' : ' &#9660;';
    }
    $qs = http_build_query($params);
    return '<a href="?' . htmlspecialchars($qs) . '" class="text-white text-decoration-none">'
        . htmlspecialchars($label) . $arrow . '</a>';
}
```

### Pattern 3: Advanced Search Panel with Bootstrap Collapse

**What:** A hidden `div.collapse` panel with per-field inputs, toggled by a `data-bs-toggle="collapse"` button
**When to use:** Advanced mode is active when `$_GET['adv']` has at least one non-empty value; panel starts open in that state
**Key detail:** Advanced search form submits GET to the same page — no separate action needed

```php
// Detect if advanced search is currently active (has any populated adv fields)
$advActive = !empty(array_filter((array)($_GET['adv'] ?? [])));
$collapseShow = $advActive ? 'show' : '';
?>
<div class="collapse <?= $collapseShow ?>" id="advSearchPanel">
  <form method="get" action="/<?= htmlspecialchars($page['path']) ?>">
    <div class="row g-2 mb-2">
    <?php foreach ($fields as $f): ?>
      <div class="col-md-3">
        <label class="form-label form-label-sm"><?= htmlspecialchars($f['display_label']) ?></label>
        <input type="text" name="adv[<?= htmlspecialchars($f['field_name']) ?>]"
               class="form-control form-control-sm"
               value="<?= htmlspecialchars($_GET['adv'][$f['field_name']] ?? '') ?>">
      </div>
    <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <a href="/<?= htmlspecialchars($page['path']) ?>" class="btn btn-secondary btn-sm ms-1">Clear</a>
  </form>
</div>
```

### Pattern 4: Scheduled Actions (EXT-04) — On-Page-Load Trigger

**What:** A function called at the top of the list view that processes pending scheduled actions for the current table
**When to use:** Called once per list view load — idempotent (processed rows have timestamps set to NULL after firing)
**Why on-page-load:** Zero infrastructure dependencies; consistent with framework constraint; no cron needed

```php
function processScheduledActions(string $tableName): void {
    $now = date('Y-m-d H:i:s');

    // Activate rows past their activate_at date
    $hasActivateAt = dbGetRow(
        "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'activate_at'",
        [$tableName]
    )['n'] ?? 0;

    if ($hasActivateAt) {
        dbQuery(
            "UPDATE `{$tableName}` SET status = 'active', activate_at = NULL
             WHERE activate_at IS NOT NULL AND activate_at <= ? AND status != 'active'",
            [$now]
        );
        dbQuery(
            "UPDATE `{$tableName}` SET status = 'inactive', deactivate_at = NULL
             WHERE deactivate_at IS NOT NULL AND deactivate_at <= ? AND status = 'active'",
            [$now]
        );
        dbQuery(
            "DELETE FROM `{$tableName}`
             WHERE delete_at IS NOT NULL AND delete_at <= ?",
            [$now]
        );
    }
}
```

### Pattern 5: col_width for Edit Form Layout (DATA-03)

**What:** A new `col_width` column on `custom_table_fields` (values: `'half'` or `'full'`) that controls whether an edit form field spans 6 or 12 Bootstrap columns
**Default:** `'half'` (matching current hard-coded `col-md-6` behavior — backward compatible)

```php
// In renderCustomFieldInput area of edit/add form:
$colClass = ($f['col_width'] ?? 'half') === 'full' ? 'col-12' : 'col-md-6';
?>
<div class="<?= $colClass ?>">
    <label class="form-label">...</label>
    <?= renderCustomFieldInput($f, $value) ?>
</div>
```

### Anti-Patterns to Avoid

- **Interpolating raw GET field names into SQL:** Always validate sort/search field names against the `$allowedFields` array fetched from `custom_table_fields`. Never do `ORDER BY {$_GET['sort']}`.
- **Sorting before ACL filter:** The decision says sort applies after ACL filter. Fetch all rows matching WHERE, apply `filterRowsByViewAccess()`, then sort the PHP array — OR (preferred for correctness and performance) build WHERE at DB level, ACL-filter in PHP, and note that the DB-level ORDER BY applies to the pre-ACL result set. Given that ACL filtering may reorder the visible set, the current approach (DB ORDER BY + PHP ACL filter) is correct because users only see their allowed rows, already in the right order. This is fine unless ACL filtering removes rows that alter relative position — which doesn't affect sort correctness.
- **Losing GET params on sort clicks:** The sort link MUST embed all active filter params (`q`, `adv`, `status`) in the `href` query string. Omitting them clears the search on every sort click.
- **Using `$_GET['sort']` without allowlisting as a `status` filter field:** The `status` column is a standard column (not in `custom_table_fields`). Handle it as a special case in the WHERE builder, not as part of the field loop.
- **Setting activate_at/deactivate_at/delete_at via migration on pre-existing custom table rows:** `ALTER TABLE` only adds the column to the MySQL table schema; existing custom tables registered before Phase 5 also need the column. The existing `migrateExistingCustomTables()` function handles this pattern — extend it to add these three columns.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| GET param preservation in links | Manual string concatenation of query params | `http_build_query($params)` | Handles encoding, arrays (`adv[]`), nulls correctly |
| Collapse panel toggle | Custom JS toggle | Bootstrap 5 `data-bs-toggle="collapse"` | Already loaded, no new JS needed |
| SQL injection guard on sort field | Custom sanitizer | PHP `in_array($sort, $allowedFields, true)` | Simple, correct, no regex needed |
| Scheduler / background jobs | Custom daemon | On-page-load `processScheduledActions()` | Zero infra overhead; framework has no background process support |

---

## Common Pitfalls

### Pitfall 1: SQL Injection via Sort Field
**What goes wrong:** `ORDER BY {$_GET['sort']}` is passed directly to MySQL. PDO cannot parameterize identifiers (column names).
**Why it happens:** Developers assume `dbQuery()` protects against all injection; it only parameterizes values, not identifiers.
**How to avoid:** Build `$sortableFields` allowlist from `$fields` array + known standard columns. Validate `$_GET['sort']` against it with strict `in_array()` before use.
**Warning signs:** Any code path where a column name comes from user input without an allowlist check.

### Pitfall 2: GET Params Evaporating on Sort Click
**What goes wrong:** User searches for "smith", clicks a column header to sort — search clears because the sort `<a href>` only has `?sort=name&dir=asc`.
**Why it happens:** Sort links are built without carrying forward the search state.
**How to avoid:** `sortLink()` helper embeds all current filter state in every generated link. Test this interaction explicitly.
**Warning signs:** Clicking a column header clears the search box in the browser.

### Pitfall 3: Advanced Search Panel State Not Preserved
**What goes wrong:** Advanced search is open and populated; user sorts — panel collapses and inputs clear.
**Why it happens:** Advanced search state (`adv[]` params) not carried through sort links.
**How to avoid:** Include `adv` array in the sort link query string. Use `$_GET['adv'] ?? []` for bootstrap collapse `show` class determination on re-render.

### Pitfall 4: migrateExistingCustomTables() Not Extended for New Columns
**What goes wrong:** New custom tables get `activate_at`/`deactivate_at`/`delete_at` columns via provisioning; existing tables silently lack them, causing SQL errors on `processScheduledActions()`.
**Why it happens:** Phase 5 adds columns to `provisionCustomTable()` but forgets to add them to the migration loop.
**How to avoid:** Add the three datetime columns to the `$standardCols` map in `migrateExistingCustomTables()` alongside the existing Phase 2 columns.
**Warning signs:** PHP errors on list view load for tables created before Phase 5 migration.

### Pitfall 5: processScheduledActions() Running on Non-Upgraded Tables
**What goes wrong:** `processScheduledActions()` assumes `activate_at` exists; it runs before `migrateExistingCustomTables()` has had a chance to add the column on older tables.
**How to avoid:** Guard with an INFORMATION_SCHEMA check (as shown in the code example above) before executing the UPDATE/DELETE. Alternatively, call `migrateExistingCustomTables()` before `processScheduledActions()` — `admin-custom-table.php` already calls it at the top via `admin-tables.php`; confirm the call order.
**Note:** `admin-custom-table.php` does NOT currently call `migrateExistingCustomTables()` — only `admin-tables.php` does. The INFORMATION_SCHEMA guard inside `processScheduledActions()` is the safer approach.

### Pitfall 6: col_width Migration Breaks Existing Fields
**What goes wrong:** After adding `col_width` column to `custom_table_fields`, existing rows have NULL; code uses `$f['col_width']` without a default.
**Why it happens:** New column added without a DEFAULT and code doesn't handle NULL.
**How to avoid:** Add column as `VARCHAR(10) NOT NULL DEFAULT 'half'` in migration. Use `$f['col_width'] ?? 'half'` in template code.

### Pitfall 7: LIKE '%term%' Performance on Large Tables
**What goes wrong:** Full table scan on every search; acceptable for admin tools managing hundreds of rows, but degrades at tens of thousands.
**Context:** This is an admin tool, not a public search page. The decision is LIKE search — no full-text index needed for v1 scope.
**How to avoid:** Accept the limitation. Add a note in code comments that full-text indexing is a v2 enhancement.

---

## Code Examples

### Phase 5 Migration Block Pattern (matching Phase 4 pattern)

```sql
-- =============================================================================
-- Phase 5: User Experience — migration (2026-03-17)
-- =============================================================================

-- DATA-03: Add col_width to custom_table_fields
SET @preparedStatement = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE custom_table_fields ADD COLUMN col_width VARCHAR(10) NOT NULL DEFAULT 'half' AFTER display_order",
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'custom_table_fields'
      AND COLUMN_NAME  = 'col_width'
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- EXT-04: No new framework tables needed; activate_at/deactivate_at/delete_at
-- are added to each custom table individually via migrateExistingCustomTables()
-- and provisionCustomTable() — see functions/admin-tables.php
```

### Extending migrateExistingCustomTables() for EXT-04 Columns

```php
// In admin-tables.php migrateExistingCustomTables(), extend $standardCols:
$standardCols = [
    // ... existing Phase 2 cols ...
    'activate_at'   => 'DATETIME NULL',
    'deactivate_at' => 'DATETIME NULL',
    'delete_at'     => 'DATETIME NULL',
];
```

### http_build_query for State-Preserving Links

```php
// Source: PHP docs — http_build_query handles arrays (adv[]) correctly
$linkParams = array_filter([
    'q'      => $_GET['q'] ?? '',
    'adv'    => $_GET['adv'] ?? [],
    'sort'   => $newSortField,
    'dir'    => $newDir,
    'status' => $_GET['status'] ?? '',
], fn($v) => $v !== '' && $v !== []);
$href = '?' . http_build_query($linkParams);
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Fetch all rows, PHP-side filter | Build WHERE at DB level, PHP ACL filter | Phase 5 design decision | Correctness + performance for large tables |
| Hard-coded `ORDER BY id DESC` | Dynamic `ORDER BY {validated_field} {dir}` | Phase 5 | User can sort by any visible column |
| All fields render `col-md-6` | `col_width` field drives half/full | Phase 5 | Admin can make text/longtext fields span full row |
| No scheduled operations | `processScheduledActions()` on list load | Phase 5 | Rows change state automatically without cron |

---

## Open Questions

1. **Status filter dropdown (SC1)**
   - What we know: Locked decisions mention `?status=active` as a GET param; the status column is a standard field
   - What's unclear: Should the list view default to showing all statuses, or only `active`? Current code shows all rows (no status filter).
   - Recommendation: Add a status dropdown filter (`All / Active / Inactive`) to the search bar row; default to showing all (consistent with current behavior). This is within Claude's Discretion.

2. **Pagination**
   - What we know: CONTEXT.md leaves pagination to Claude's Discretion; current list loads all rows
   - What's unclear: No explicit row limit mentioned; admin tables may be small enough that pagination is unnecessary for v1
   - Recommendation: Omit pagination in Phase 5 (consistent with current pattern). Add a "showing N records" count which already exists. Add a comment noting pagination as a v2 enhancement.

3. **EXT-04: Where are schedule inputs shown?**
   - What we know: No explicit decision — left to Claude's Discretion
   - Recommendation: Add `activate_at`, `deactivate_at`, `delete_at` as optional datetime inputs in the add/edit form, grouped in a "Scheduling" card section below the main fields. Use `type="datetime-local"` input. These fields are not in `custom_table_fields` (they are standard columns like `status`) and should be rendered unconditionally whenever the columns exist.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Custom SnowTestRunner (PHP, no external deps) |
| Config file | none — runner is a plain class in `tests/SnowTestRunner.php` |
| Quick run command | `php /home/cb/Snow/tests/test_user_experience.php` |
| Full suite command | `php /home/cb/Snow/tests/test_all.php` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DATA-02 | `custom_table_fields` has `col_width` column after migration | unit/schema | `php tests/test_user_experience.php` | Wave 0 |
| DATA-03 | Edit form renders `col-12` for `col_width='full'`, `col-md-6` for `'half'` | unit | `php tests/test_user_experience.php` | Wave 0 |
| DATA-04 | WHERE builder produces correct SQL with `q` param across visible fields | unit | `php tests/test_user_experience.php` | Wave 0 |
| DATA-04 | WHERE builder produces correct SQL with `adv[field]` params | unit | `php tests/test_user_experience.php` | Wave 0 |
| DATA-04 | Sort field allowlist rejects unknown field names | unit | `php tests/test_user_experience.php` | Wave 0 |
| DATA-04 | Sort direction defaults to DESC when `dir` param is invalid | unit | `php tests/test_user_experience.php` | Wave 0 |
| EXT-04 | Custom table has `activate_at`, `deactivate_at`, `delete_at` columns after migration | schema | `php tests/test_user_experience.php` | Wave 0 |
| EXT-04 | `processScheduledActions()` sets status='active' for rows past activate_at | integration | `php tests/test_user_experience.php` | Wave 0 |
| EXT-04 | `processScheduledActions()` sets status='inactive' for rows past deactivate_at | integration | `php tests/test_user_experience.php` | Wave 0 |
| EXT-04 | `processScheduledActions()` deletes rows past delete_at | integration | `php tests/test_user_experience.php` | Wave 0 |

### Sampling Rate

- **Per task commit:** `php /home/cb/Snow/tests/test_user_experience.php`
- **Per wave merge:** `php /home/cb/Snow/tests/test_all.php`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `/home/cb/Snow/tests/test_user_experience.php` — covers DATA-02, DATA-03, DATA-04, EXT-04 stubs
- [ ] Update `/home/cb/Snow/tests/test_all.php` to `require_once` the new test file

---

## Sources

### Primary (HIGH confidence)

- Direct code review: `/home/cb/Snow/functions/admin-custom-table.php` — list view section (lines 620–677), edit form (lines 530–616), add form (lines 269–364)
- Direct code review: `/home/cb/Snow/functions/admin-tables.php` — `migrateExistingCustomTables()`, `provisionCustomTable()`, fields view (lines 459–564)
- Direct code review: `/home/cb/Snow/functions/database.php` — `dbGetRows()`, `dbQuery()` parameterization behavior
- Direct code review: `/home/cb/Snow/functions/acl.php` — `filterRowsByViewAccess()` signature and behavior
- Direct code review: `/home/cb/Snow/database_schema.sql` — `custom_table_fields` schema (is_visible, display_order, no col_width yet), Phase 4 migration pattern
- Direct code review: `/home/cb/Snow/.planning/phases/05-user-experience/05-CONTEXT.md` — all locked decisions
- PHP manual: `http_build_query()` handles array parameters in GET strings correctly (arrays become `key[0]=val` notation)
- Bootstrap 5 docs: `data-bs-toggle="collapse"` and `data-bs-target` — no custom JS needed; already loaded in project templates

### Secondary (MEDIUM confidence)

- Pattern inference: `processScheduledActions()` with INFORMATION_SCHEMA guard follows established pattern from `migrateExistingCustomTables()` in the same codebase
- Pattern inference: Migration block using `SET @preparedStatement = (SELECT IF(...))` / PREPARE/EXECUTE follows Phase 3 and Phase 4 established pattern

### Tertiary (LOW confidence)

- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components already in use in the project; no new libraries
- Architecture: HIGH — directly derived from existing code structure and locked decisions
- Pitfalls: HIGH — all pitfalls identified from direct code inspection of existing patterns
- EXT-04 scheduler approach: MEDIUM — on-page-load approach is Claude's Discretion; alternative (separate cron.php script) is equally valid but adds setup complexity

**Research date:** 2026-03-17
**Valid until:** Stable — this is all internal project code, not a moving external dependency. Valid indefinitely for this codebase.
