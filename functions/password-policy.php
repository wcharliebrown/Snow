<?php
/**
 * Password Policy Functions for Snow Framework (SEC-03)
 *
 * Provides policy read/write, strength validation, expiry checking,
 * reuse prevention, and password history recording.
 *
 * getPasswordPolicy()           — fetch config or return safe defaults
 * savePasswordPolicy()          — update the single config row
 * validatePasswordStrength()    — check new password against policy; returns array of error strings
 * isPasswordExpired()           — check if user's password is older than max_age_days
 * isPasswordReused()            — check if new password matches recent history
 * recordPasswordChange()        — save new hash to history; update password_changed_at on users
 */

/**
 * Get the current password policy.
 * Returns safe defaults if the table has no rows (first-boot or misconfiguration).
 */
function getPasswordPolicy(): array {
    $row = dbGetRow("SELECT * FROM password_policy LIMIT 1");
    if (!$row) {
        return ['min_length' => 8, 'max_age_days' => 0, 'prevent_reuse_count' => 0];
    }
    return $row;
}

/**
 * Save (upsert) the password policy.
 * Only one config row ever exists — update it if present, insert if missing.
 *
 * @param int $minLength           Minimum characters (1-128)
 * @param int $maxAgeDays          Days before expiry; 0 = never expires
 * @param int $preventReuseCount   Number of previous hashes to check; 0 = no reuse check
 * @param int|null $updatedBy      User ID making the change
 */
function savePasswordPolicy(int $minLength, int $maxAgeDays, int $preventReuseCount, ?int $updatedBy = null): bool {
    $existing = dbGetRow("SELECT id FROM password_policy LIMIT 1");

    $data = [
        'min_length'            => max(1, min(128, $minLength)),
        'max_age_days'          => max(0, $maxAgeDays),
        'prevent_reuse_count'   => max(0, $preventReuseCount),
        'updated_by'            => $updatedBy,
    ];

    if ($existing) {
        return dbUpdate('password_policy', $data, 'id = ?', [$existing['id']]);
    } else {
        return (bool) dbInsert('password_policy', $data);
    }
}

/**
 * Validate a new password against the current policy.
 *
 * @return array  Array of error message strings. Empty array = password is valid.
 */
function validatePasswordStrength(string $password): array {
    $policy = getPasswordPolicy();
    $errors = [];

    if (strlen($password) < (int)$policy['min_length']) {
        $errors[] = 'Password must be at least ' . (int)$policy['min_length'] . ' characters.';
    }

    return $errors;
}

/**
 * Check if a user's password has exceeded max_age_days.
 * Returns false if max_age_days is 0 (policy disabled) or if password_changed_at is NULL.
 *
 * @param int $userId
 * @return bool  true = expired (must change), false = not expired
 */
function isPasswordExpired(int $userId): bool {
    $policy = getPasswordPolicy();
    $maxAgeDays = (int)$policy['max_age_days'];

    if ($maxAgeDays === 0) {
        return false;  // Expiry disabled
    }

    $row = dbGetRow("SELECT password_changed_at FROM users WHERE id = ?", [$userId]);
    if (!$row || empty($row['password_changed_at'])) {
        return false;  // NULL = not expired (existing users before this feature was added)
    }

    $ageInDays = (time() - strtotime($row['password_changed_at'])) / 86400;
    return $ageInDays > $maxAgeDays;
}

/**
 * Check if a new password matches any of the user's recent password hashes.
 * Returns false if prevent_reuse_count is 0 (policy disabled).
 *
 * @param int    $userId
 * @param string $newPassword  Plain-text password to check
 * @return bool  true = password was recently used (reject), false = OK to use
 */
function isPasswordReused(int $userId, string $newPassword): bool {
    $policy = getPasswordPolicy();
    $count = (int)$policy['prevent_reuse_count'];

    if ($count === 0) {
        return false;  // Reuse check disabled
    }

    $history = dbGetRows(
        "SELECT password_hash FROM password_history
         WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
        [$userId, $count]
    );

    foreach ($history as $h) {
        if (password_verify($newPassword, $h['password_hash'])) {
            return true;  // Match found in recent history
        }
    }

    return false;
}

/**
 * Record a password change:
 *  1. Insert new hash into password_history
 *  2. Update users.password_changed_at to now
 *
 * Call this after every successful password change (changePassword, completePasswordReset).
 *
 * @param int    $userId
 * @param string $newHash  The bcrypt hash (not the plain-text password)
 */
function recordPasswordChange(int $userId, string $newHash): void {
    dbInsert('password_history', [
        'user_id'       => $userId,
        'password_hash' => $newHash,
    ]);

    dbUpdate('users', ['password_changed_at' => date('Y-m-d H:i:s')], 'id = ?', [$userId]);
}
