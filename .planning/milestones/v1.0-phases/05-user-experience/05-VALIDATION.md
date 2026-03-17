---
phase: 5
slug: user-experience
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-17
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Custom SnowTestRunner (PHP, no external deps) |
| **Config file** | none — runner is a plain class in `tests/SnowTestRunner.php` |
| **Quick run command** | `php /home/cb/Snow/tests/test_user_experience.php` |
| **Full suite command** | `php /home/cb/Snow/tests/test_all.php` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php /home/cb/Snow/tests/test_user_experience.php`
- **After every plan wave:** Run `php /home/cb/Snow/tests/test_all.php`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** ~5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 5-W0-01 | Wave 0 | 0 | DATA-02 | schema | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-02 | Wave 0 | 0 | DATA-03 | unit | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-03 | Wave 0 | 0 | DATA-04 | unit | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-04 | Wave 0 | 0 | DATA-04 | unit | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-05 | Wave 0 | 0 | DATA-04 | unit | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-06 | Wave 0 | 0 | DATA-04 | unit | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-07 | Wave 0 | 0 | EXT-04 | schema | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-08 | Wave 0 | 0 | EXT-04 | integration | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-09 | Wave 0 | 0 | EXT-04 | integration | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |
| 5-W0-10 | Wave 0 | 0 | EXT-04 | integration | `php tests/test_user_experience.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/test_user_experience.php` — stubs for DATA-02, DATA-03, DATA-04, EXT-04
- [ ] `tests/test_all.php` — updated to `require_once` the new test file

*Wave 0 creates the test file and registers it with the full suite.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Search box renders on list view with submit button and Advanced toggle | DATA-04 | HTML rendering requires browser/DOM | Load a custom table list view in browser; verify search box and Advanced toggle visible |
| Advanced search panel expands/collapses correctly | DATA-04 | Bootstrap collapse behavior requires browser | Click Advanced toggle; verify panel expands; populate a field; submit; verify panel stays open |
| Column headers render as clickable sort links with arrows | DATA-04 | HTML rendering requires browser/DOM | Load list view; click a column header; verify URL updates with `sort` and `dir` params; verify arrow indicator |
| Search term preserved after sort click | DATA-04 | GET param state requires browser interaction | Search for a term; click a column header to sort; verify search term remains in box and results still filtered |
| col_width='full' renders as full-width in edit form | DATA-03 | HTML rendering requires browser/DOM | Set a field's col_width to 'full' in admin; open edit form; verify field spans full row |
| Schedule inputs appear in add/edit form when columns exist | EXT-04 | HTML rendering requires browser/DOM | Open add/edit form for a custom table; verify activate_at, deactivate_at, delete_at datetime inputs visible |
| Row auto-activates when activate_at is reached | EXT-04 | Requires time to pass or manual date manipulation | Set activate_at to a past datetime; load list view; verify row status changed to 'active' |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
