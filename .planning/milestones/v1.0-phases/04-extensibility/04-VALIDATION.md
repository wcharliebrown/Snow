---
phase: 4
slug: extensibility
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-06
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Custom `SnowTestRunner` (no external deps) |
| **Config file** | `tests/SnowTestRunner.php` |
| **Quick run command** | `php tests/test_all.php` |
| **Full suite command** | `php tests/test_all.php` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php tests/test_all.php`
- **After every plan wave:** Run `php tests/test_all.php`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** ~5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 4-??-01 | 01 | 0 | EXT-01 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |
| 4-??-02 | 01 | 0 | EXT-01 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |
| 4-??-03 | 01 | 0 | EXT-01 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |
| 4-??-04 | 01 | 0 | EXT-01 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |
| 4-??-05 | 01 | 0 | SEC-05 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |
| 4-??-06 | 01 | 0 | SEC-05 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |
| 4-??-07 | 01 | 0 | EXT-03 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |
| 4-??-08 | 01 | 0 | EXT-02 | unit | `php tests/test_all.php` | ❌ Wave 0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Task IDs will be filled in by planner once PLAN.md files exist.*

---

## Wave 0 Requirements

- [ ] `tests/test_extensibility.php` — stubs for EXT-01 hook execution, EXT-03 template rendering, SEC-05 OTP logic, EXT-02 page serving
- [ ] `tests/test_all.php` — must include `test_extensibility.php` via require

*Note: `tests/SnowTestRunner.php` already exists — no framework install needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Custom page served by renderPage() | EXT-02 | Requires live HTTP routing; no in-process test harness | 1. Create a custom page with URL path `/test-page`; 2. Navigate to `/test-page` in browser; 3. Verify page content renders |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
