# Phase 5: User Experience - Context

**Gathered:** 2026-03-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Admins can search, sort, and filter records on any managed table list view without writing per-table code; any row can be scheduled for automatic activation, deactivation, or deletion on a future date. Edit form layout and list view column/filter customization are also in scope (DATA-02 through DATA-04, EXT-04).

</domain>

<decisions>
## Implementation Decisions

### Search model
- Single text search box on the list view: LIKE '%term%' across all fields where `is_visible = 1`
- Submit button to trigger search (server-side, page reload — consistent with all other Snow pages)
- "Advanced search" toggle next to the search box: reveals a per-field input row where each visible field gets its own text input for field=value (LIKE) searches
- Advanced search and simple search are mutually exclusive (one or the other active at a time)

### Sort model
- Clickable column headers sort the list: clicking sets `?sort=field_name&dir=asc` (or `dir=desc`) GET params
- Page reloads with sorted results — no JS required
- Sort applies after ACL filtering (sort on the filtered result set, not the raw DB result)

### Rendering approach
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

</decisions>

<specifics>
## Specific Ideas

- Advanced search toggle sits next to the simple search box — a small "Advanced" link or button that reveals the per-field form
- The simple search and advanced search are clearly distinct modes (not stacked), keeping the default view uncluttered

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `admin-custom-table.php` list view (line 621–673): current list view to extend — add search bar above table, sortable `<th>` links, apply GET param filters before the SELECT
- `custom_table_fields` table: `is_visible`, `display_order`, `field_name`, `display_label`, `field_type` — use to drive search columns, column headers, and advanced search inputs
- `filterRowsByViewAccess()` in `acl.php`: applies after DB fetch — sort/search should be done at DB level (before ACL filter) for correctness; ACL filter runs last on the result set
- `dbGetRows()` in `database.php`: use with parameterized WHERE clause built dynamically from GET params
- Bootstrap 5 `collapse` component: available for advanced search toggle panel

### Established Patterns
- GET `?msg=` flash messages: same pattern for all pages — search/sort params will also live in GET
- `ORDER BY id DESC` default in list view: replace with dynamic `ORDER BY {field} {dir}` based on GET params (with allowlist validation)
- `is_visible` flag on fields: already gates which columns appear in the list — use same flag to decide which fields are searchable and appear in advanced search form

### Integration Points
- `admin-custom-table.php` list view section: add search bar + advanced panel above table; build WHERE clause from GET params; add sortable `<th>` links; pass sort/search params through pagination links
- `admin-tables.php` fields view: already manages `is_visible`, `display_order` per field — SC2 (customize visible columns) is effectively done; SC3 (edit form layout) may need a `col_width` or similar column
- EXT-04 (staged activation): no existing scheduler — will need new `activate_at`/`deactivate_at`/`delete_at` columns on each custom table and a trigger mechanism (cron or on-page-load check); no decisions captured here — left to Claude's Discretion

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 05-user-experience*
*Context gathered: 2026-03-17*
