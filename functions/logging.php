<?php
/**
 * Logging Functions for Snow Framework
 */

/**
 * Log an error message
 */
function logError($message) {
    logMessage('ERROR', $message);
}

/**
 * Log an informational message
 */
function logInfo($message) {
    if (getenv('LOG_LEVEL') >= 2) {
        logMessage('INFO', $message);
    }
}

/**
 * Log traffic information
 */
function logTraffic($path) {
    if (getenv('LOG_TRAFFIC') == '1') {
        $message = sprintf(
            "Path: %s | Method: %s | IP: %s | User-Agent: %s",
            $path,
            $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN'
        );
        logMessage('TRAFFIC', $message);
    }
}

/**
 * Log email sent
 */
function logEmail($to, $subject, $template = '') {
    if (getenv('LOG_EMAIL') == '1') {
        $message = sprintf(
            "To: %s | Subject: %s | Template: %s",
            $to,
            $subject,
            $template
        );
        logMessage('EMAIL', $message);
    }
}

/**
 * Generic logging function — writes to activity_log DB table (SEC-04).
 * Falls back to flat file if DB is unavailable. Never calls logError() internally
 * (would cause recursive loop if DB is down — see PITFALL-4).
 */
function logMessage($level, $message, array $context = []) {
    // --- DB logging (primary path) ---
    try {
        // Only attempt DB if getDbConnection is available and DB is reachable.
        // Do NOT call getDbConnection() if we're already inside a DB error handler.
        if (function_exists('getDbConnection')) {
            $db = getDbConnection();
            $stmt = $db->prepare(
                "INSERT INTO activity_log
                    (level, event_type, message, user_id, session_id, ip_address, context)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $userId    = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
            $sessionId = (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE)
                         ? session_id()
                         : null;
            $ip        = $_SERVER['REMOTE_ADDR'] ?? null;
            $eventType = strtolower($level);

            $stmt->execute([
                strtoupper($level),
                $eventType,
                $message,
                $userId,
                $sessionId,
                $ip,
                !empty($context) ? json_encode($context) : null,
            ]);
            // DB write succeeded — flat-file write below acts as secondary audit trail
        }
    } catch (Exception $e) {
        // DB unavailable — fall through to flat-file below.
        // IMPORTANT: Do NOT call logError() here — that would recurse.
        // Silence the exception and write to file directly.
    }

    // --- Flat-file logging (always runs; acts as fallback and secondary trail) ---
    $logDir = defined('SNOW_LOGS') ? SNOW_LOGS : dirname(__DIR__) . '/logs';
    $logFile = $logDir . '/snow.log';

    $timestamp = date('Y-m-d H:i:s');
    $fileUserId    = function_exists('getCurrentUserId') ? (getCurrentUserId() ?? 'system') : 'system';
    $fileSessionId = (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE)
                 ? session_id()
                 : 'no_session';

    $logEntry = sprintf(
        "[%s] [%s] [User:%s] [Session:%s] %s\n",
        $timestamp,
        strtoupper($level),
        $fileUserId,
        $fileSessionId,
        $message
    );

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // Also write to level-specific file (preserves existing behavior for log parsing tools)
    $levelFile = $logDir . '/' . strtolower($level) . '.log';
    @file_put_contents($levelFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Get log entries from activity_log table (replaces flat-file parsing).
 * Returns entries newest-first.
 */
function getLogEntries($level = null, $limit = 100, $offset = 0) {
    try {
        $sql = "SELECT al.id, al.level, al.event_type, al.message,
                       al.user_id, al.session_id, al.ip_address, al.context,
                       al.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                       u.email AS user_email
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id";

        $params = [];
        if ($level) {
            $sql .= " WHERE al.level = ?";
            $params[] = strtoupper($level);
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        return dbGetRows($sql, $params);
    } catch (Exception $e) {
        return [];  // Return empty on DB failure — do not recurse into logError
    }
}

/**
 * Search log entries in activity_log table.
 */
function searchLogEntries($searchTerm, $level = null, $limit = 100, $offset = 0) {
    try {
        $like = '%' . $searchTerm . '%';
        $sql = "SELECT al.id, al.level, al.event_type, al.message,
                       al.user_id, al.session_id, al.ip_address, al.context,
                       al.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                       u.email AS user_email
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE (al.message LIKE ? OR al.ip_address LIKE ?
                       OR u.email LIKE ? OR u.first_name LIKE ?)";

        $params = [$like, $like, $like, $like];

        if ($level) {
            $sql .= " AND al.level = ?";
            $params[] = strtoupper($level);
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        return dbGetRows($sql, $params);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Clear log entries from activity_log table (and flat-log files).
 */
function clearLogs($level = null) {
    // Clear DB log entries
    try {
        if ($level) {
            dbQuery("DELETE FROM activity_log WHERE level = ?", [strtoupper($level)]);
        } else {
            dbQuery("DELETE FROM activity_log");
        }
    } catch (Exception $e) {
        // Ignore DB failure
    }

    // Also clear flat-log files (legacy cleanup)
    $logDir = defined('SNOW_LOGS') ? SNOW_LOGS : dirname(__DIR__) . '/logs';
    if ($level) {
        $logFile = $logDir . '/' . strtolower($level) . '.log';
        if (file_exists($logFile)) { @unlink($logFile); }
    } else {
        $logFiles = glob($logDir . '/*.log');
        if ($logFiles) {
            foreach ($logFiles as $file) { @unlink($file); }
        }
    }

    return true;
}

/**
 * Get log statistics from activity_log table.
 */
function getLogStats() {
    try {
        $sql = "SELECT
                    COUNT(*) AS total_entries,
                    SUM(CASE WHEN level = 'ERROR' THEN 1 ELSE 0 END) AS error_count,
                    SUM(CASE WHEN level = 'INFO' THEN 1 ELSE 0 END) AS info_count,
                    SUM(CASE WHEN level = 'TRAFFIC' THEN 1 ELSE 0 END) AS traffic_count,
                    SUM(CASE WHEN level = 'EMAIL' THEN 1 ELSE 0 END) AS email_count,
                    MAX(created_at) AS last_entry
                FROM activity_log";
        $row = dbGetRow($sql);
        return $row ?: ['total_entries' => 0, 'error_count' => 0, 'info_count' => 0,
                        'traffic_count' => 0, 'email_count' => 0, 'last_entry' => null];
    } catch (Exception $e) {
        return ['total_entries' => 0, 'error_count' => 0, 'info_count' => 0,
                'traffic_count' => 0, 'email_count' => 0, 'last_entry' => null];
    }
}
?>
