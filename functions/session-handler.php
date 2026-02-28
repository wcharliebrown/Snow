<?php
/**
 * DB-backed session handler for Snow Framework (SEC-02)
 * Stores sessions in MySQL sessions table with SELECT FOR UPDATE locking.
 */

class SnowSessionHandler implements SessionHandlerInterface {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        // Commit any open transaction from read() if write() was not called
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
        return true;
    }

    public function read(string $id): string|false {
        // BEGIN TRANSACTION — SELECT FOR UPDATE only locks within a transaction
        // Without a transaction, MySQL autocommit releases the lock immediately
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }

        $stmt = $this->db->prepare(
            "SELECT data FROM sessions WHERE session_id = ? AND expires_at > NOW() FOR UPDATE"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // Return empty string (not false) when no row — PHP requires this for new sessions
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool {
        $ttl = (int)(ini_get('session.gc_maxlifetime') ?: 1440);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt = $this->db->prepare(
            "INSERT INTO sessions (session_id, user_id, data, ip_address, user_agent, expires_at)
             VALUES (?, NULL, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
               data = VALUES(data),
               expires_at = VALUES(expires_at)"
        );

        $result = $stmt->execute([$id, $data, $ip, $ua, $ttl]);

        // Commit the transaction opened in read()
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }

        return $result;
    }

    public function destroy(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_id = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}

/**
 * Force-logout a specific session by session_id.
 * Call from admin UI. The browser holding that session will find no data on next request.
 */
function forceLogoutSession(string $sessionId): bool {
    $stmt = getDbConnection()->prepare("DELETE FROM sessions WHERE session_id = ?");
    return $stmt->execute([$sessionId]);
}

/**
 * Force-logout all sessions for a given user_id.
 */
function forceLogoutUser(int $userId): bool {
    $stmt = getDbConnection()->prepare("DELETE FROM sessions WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

/**
 * Get all active sessions (for admin sessions viewer).
 * Does NOT expose raw session_id in the returned data — use id column for force-logout.
 */
function getActiveSessions(): array {
    $sql = "SELECT s.id, s.user_id,
                   CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                   u.email AS user_email,
                   s.ip_address, s.user_agent,
                   s.created_at, s.expires_at
            FROM sessions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.expires_at > NOW()
            ORDER BY s.created_at DESC";
    return dbGetRows($sql);
}
