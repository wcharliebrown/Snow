<?php
/**
 * Database Functions for Snow Framework
 */

// Global database connection
$dbConnection = null;

/**
 * Get database connection
 */
function getDbConnection() {
    global $dbConnection;
    
    if ($dbConnection === null) {
        $host = getenv('DB_HOST');
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        
        try {
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $dbConnection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (function_exists('logError')) {
                logError('Database connection failed: ' . $e->getMessage());
            }
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $dbConnection;
}

/**
 * Execute a query and return results
 */
function dbQuery($sql, $params = []) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Database query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
        }
        throw new Exception('Database query failed');
    }
}

/**
 * Get a single row
 */
function dbGetRow($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Get multiple rows
 */
function dbGetRows($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert a row and return the ID
 */
function dbInsert($table, $data) {
    $fields = array_keys($data);
    $placeholders = array_fill(0, count($fields), '?');
    
    $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    try {
        $db = getDbConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($data));
        return $db->lastInsertId();
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Database insert failed: ' . $e->getMessage());
        }
        throw new Exception('Database insert failed');
    }
}

/**
 * Update a row
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    $fields = [];
    $params = [];
    
    foreach ($data as $field => $value) {
        $fields[] = "$field = ?";
        $params[] = $value;
    }
    
    $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $where";
    $params = array_merge($params, $whereParams);
    
    try {
        $db = getDbConnection();
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Database update failed: ' . $e->getMessage());
        }
        throw new Exception('Database update failed');
    }
}

/**
 * Delete a row
 */
function dbDelete($table, $where, $params = []) {
    $sql = "DELETE FROM $table WHERE $where";
    
    try {
        $stmt = dbQuery($sql, $params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Database delete failed: ' . $e->getMessage());
        }
        throw new Exception('Database delete failed');
    }
}

/**
 * Check if a table exists
 */
function dbTableExists($tableName) {
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?";
    $result = dbGetRow($sql, [$tableName]);
    return $result && (int)$result['cnt'] > 0;
}

/**
 * Get table structure
 */
function dbGetTableStructure($tableName) {
    $sql = "DESCRIBE $tableName";
    return dbGetRows($sql);
}

/**
 * Create a table
 */
function dbCreateTable($tableName, $fields) {
    $sql = "CREATE TABLE $tableName (";
    $fieldDefinitions = [];
    
    foreach ($fields as $fieldName => $fieldDef) {
        $fieldDefinitions[] = "$fieldName $fieldDef";
    }
    
    $sql .= implode(', ', $fieldDefinitions);
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $db = getDbConnection();
        $stmt = $db->prepare($sql);
        return $stmt->execute();
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Table creation failed: ' . $e->getMessage());
        }
        throw new Exception('Table creation failed');
    }
}

/**
 * Add a column to a table
 */
function dbAddColumn($tableName, $columnName, $definition) {
    $sql = "ALTER TABLE $tableName ADD COLUMN $columnName $definition";
    
    try {
        $stmt = dbQuery($sql);
        return true;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Column addition failed: ' . $e->getMessage());
        }
        throw new Exception('Column addition failed');
    }
}

/**
 * Drop a column from a table
 */
function dbDropColumn($tableName, $columnName) {
    $sql = "ALTER TABLE $tableName DROP COLUMN $columnName";
    
    try {
        $stmt = dbQuery($sql);
        return true;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Column drop failed: ' . $e->getMessage());
        }
        throw new Exception('Column drop failed');
    }
}

/**
 * Add an index to a table
 */
function dbAddIndex($tableName, $indexName, $columns, $unique = false) {
    $indexType = $unique ? "UNIQUE" : "";
    $sql = "ALTER TABLE $tableName ADD $indexType INDEX $indexName (" . implode(', ', $columns) . ")";
    
    try {
        $stmt = dbQuery($sql);
        return true;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Index creation failed: ' . $e->getMessage());
        }
        throw new Exception('Index creation failed');
    }
}

/**
 * Drop an index from a table
 */
function dbDropIndex($tableName, $indexName) {
    $sql = "ALTER TABLE $tableName DROP INDEX $indexName";
    
    try {
        $stmt = dbQuery($sql);
        return true;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Index drop failed: ' . $e->getMessage());
        }
        throw new Exception('Index drop failed');
    }
}

/**
 * Begin a transaction
 */
function dbBeginTransaction() {
    $db = getDbConnection();
    return $db->beginTransaction();
}

/**
 * Commit a transaction
 */
function dbCommit() {
    $db = getDbConnection();
    return $db->commit();
}

/**
 * Rollback a transaction
 */
function dbRollback() {
    $db = getDbConnection();
    return $db->rollBack();
}
?>