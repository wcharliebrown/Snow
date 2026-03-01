<?php
/**
 * Row-level access control functions for Snow Framework.
 * Phase 2: Access Control.
 *
 * Public API:
 *   getUserGroupIds(?int $userId): array
 *   canViewRow(array $row, ?int $userId): bool
 *   canEditRow(array $row, ?int $userId): bool
 *   filterRowsByViewAccess(array $rows, ?int $userId): array
 *   formatGroupIds(?string $groupIds): string
 */

/**
 * Return an array of group IDs (integers) for the given user.
 *
 * Uses a static cache to ensure getUserGroups() is called at most once per
 * user per request.
 *
 * @param int|null $userId  User ID; if null, falls back to getCurrentUserId().
 * @return int[]            Sequential array of group IDs.
 */
function getUserGroupIds(?int $userId = null): array
{
    static $cache = [];

    if ($userId === null) {
        $userId = getCurrentUserId();
    }

    if (!$userId) {
        return [];
    }

    if (!array_key_exists($userId, $cache)) {
        $groups = getUserGroups($userId);
        $cache[$userId] = array_map('intval', array_column($groups, 'id'));
    }

    return $cache[$userId];
}

/**
 * Determine whether the given user may view a row.
 *
 * Returns true when:
 *   - the user has the admin_access permission (admin bypass), or
 *   - row['view_groups'] is NULL / empty string (open to all), or
 *   - the user belongs to at least one of the groups listed in view_groups.
 *
 * @param array    $row     Row data; must contain 'view_groups' key.
 * @param int|null $userId  User ID; defaults to current session user.
 * @return bool
 */
function canViewRow(array $row, ?int $userId = null): bool
{
    if (hasPermission('admin_access', $userId)) {
        return true;
    }

    if (empty($row['view_groups'])) {
        return true;
    }

    $allowed = array_filter(array_map('intval', explode(',', $row['view_groups'])));

    if (empty($allowed)) {
        return true;
    }

    return !empty(array_intersect(getUserGroupIds($userId), $allowed));
}

/**
 * Determine whether the given user may edit a row.
 *
 * Returns true when:
 *   - the user has the admin_access permission (admin bypass), or
 *   - row['edit_groups'] is NULL / empty string (open to all), or
 *   - the user belongs to at least one of the groups listed in edit_groups.
 *
 * @param array    $row     Row data; must contain 'edit_groups' key.
 * @param int|null $userId  User ID; defaults to current session user.
 * @return bool
 */
function canEditRow(array $row, ?int $userId = null): bool
{
    if (hasPermission('admin_access', $userId)) {
        return true;
    }

    if (empty($row['edit_groups'])) {
        return true;
    }

    $allowed = array_filter(array_map('intval', explode(',', $row['edit_groups'])));

    if (empty($allowed)) {
        return true;
    }

    return !empty(array_intersect(getUserGroupIds($userId), $allowed));
}

/**
 * Filter an array of rows to only those the given user may view.
 *
 * Keys are reset so the returned array is always sequential (0-indexed).
 *
 * @param array    $rows    Array of row arrays, each with a 'view_groups' key.
 * @param int|null $userId  User ID; defaults to current session user.
 * @return array            Sequential array of rows the user may view.
 */
function filterRowsByViewAccess(array $rows, ?int $userId = null): array
{
    return array_values(array_filter($rows, fn($row) => canViewRow($row, $userId)));
}

/**
 * Format a comma-separated string of group IDs as a human-readable HTML string.
 *
 * Returns a muted "All Users" span for NULL or empty input.
 * Otherwise queries user_groups_list for names and returns them comma-separated
 * (HTML-escaped).
 *
 * @param string|null $groupIds  Comma-separated group ID string, or null/empty.
 * @return string                HTML string.
 */
function formatGroupIds(?string $groupIds): string
{
    if (empty($groupIds)) {
        return '<span class="text-muted">All Users</span>';
    }

    $ids = array_filter(array_map('intval', explode(',', $groupIds)));

    if (empty($ids)) {
        return '<span class="text-muted">All Users</span>';
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = dbGetRows(
        "SELECT name FROM user_groups_list WHERE id IN ($placeholders) ORDER BY name",
        $ids
    );

    $names = array_column($rows, 'name');

    return htmlspecialchars(implode(', ', $names));
}
