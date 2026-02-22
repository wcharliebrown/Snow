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
 * Generic logging function
 */
function logMessage($level, $message) {
    $logFile = (defined('SNOW_LOGS') ? SNOW_LOGS : dirname(__DIR__) . '/logs') . '/' . strtolower($level) . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = function_exists('getCurrentUserId') ? getCurrentUserId() : 'system';
    $sessionId = session_id() ?? 'no_session';
    
    $logEntry = sprintf(
        "[%s] [%s] [User:%s] [Session:%s] %s\n",
        $timestamp,
        $level,
        $userId,
        $sessionId,
        $message
    );
    
    // Create log directory if it doesn't exist
    if (!is_dir(SNOW_LOGS)) {
        mkdir(SNOW_LOGS, 0755, true);
    }
    
    // Write to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also write to main log file
    $mainLogFile = SNOW_LOGS . '/snow.log';
    file_put_contents($mainLogFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Get log entries
 */
function getLogEntries($level = null, $limit = 100, $offset = 0) {
    $logFile = SNOW_LOGS . '/snow.log';
    
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $entries = [];
    
    // Reverse array to get newest first
    $lines = array_reverse($lines);
    
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        // Parse log entry
        if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] \[User:([^\]]*)\] \[Session:([^\]]*)\] (.+)$/', $line, $matches)) {
            $entry = [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'user_id' => $matches[3],
                'session_id' => $matches[4],
                'message' => $matches[5]
            ];
            
            // Filter by level if specified
            if ($level && $entry['level'] !== $level) {
                continue;
            }
            
            $entries[] = $entry;
        }
    }
    
    // Apply pagination
    return array_slice($entries, $offset, $limit);
}

/**
 * Search log entries
 */
function searchLogEntries($searchTerm, $level = null, $limit = 100, $offset = 0) {
    $entries = getLogEntries($level, $limit * 2, $offset);
    $results = [];
    
    foreach ($entries as $entry) {
        if (stripos($entry['message'], $searchTerm) !== false ||
            stripos($entry['user_id'], $searchTerm) !== false ||
            stripos($entry['session_id'], $searchTerm) !== false) {
            $results[] = $entry;
        }
    }
    
    return array_slice($results, 0, $limit);
}

/**
 * Clear log files
 */
function clearLogs($level = null) {
    if ($level) {
        $logFile = SNOW_LOGS . '/' . strtolower($level) . '.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    } else {
        // Clear all log files
        $logFiles = glob(SNOW_LOGS . '/*.log');
        foreach ($logFiles as $file) {
            unlink($file);
        }
    }
    
    return true;
}

/**
 * Get log statistics
 */
function getLogStats() {
    $stats = [
        'total_entries' => 0,
        'error_count' => 0,
        'info_count' => 0,
        'traffic_count' => 0,
        'email_count' => 0,
        'last_entry' => null
    ];
    
    $logFile = SNOW_LOGS . '/snow.log';
    if (!file_exists($logFile)) {
        return $stats;
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        if (preg_match('/^\[([^\]]*)\] \[([^\]]*)\]/', $line, $matches)) {
            $level = $matches[2];
            $timestamp = $matches[1];
            
            $stats['total_entries']++;
            $stats['last_entry'] = $timestamp;
            
            switch ($level) {
                case 'ERROR':
                    $stats['error_count']++;
                    break;
                case 'INFO':
                    $stats['info_count']++;
                    break;
                case 'TRAFFIC':
                    $stats['traffic_count']++;
                    break;
                case 'EMAIL':
                    $stats['email_count']++;
                    break;
            }
        }
    }
    
    return $stats;
}
?>