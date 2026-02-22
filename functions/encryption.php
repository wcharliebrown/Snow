<?php
/**
 * Encryption and Key Management for Snow Framework
 */

/**
 * Encrypt a string
 */
function encryptString($data, $keyName = 'default') {
    $key = getEncryptionKey($keyName);
    if (!$key) {
        logError("Encryption key not found: $keyName");
        return false;
    }
    
    $method = 'AES-256-CBC';
    $ivLength = openssl_cipher_iv_length($method);
    $iv = openssl_random_pseudo_bytes($ivLength);
    
    $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        logError("Encryption failed for key: $keyName");
        return false;
    }
    
    // Combine IV and encrypted data
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt a string
 */
function decryptString($encryptedData, $keyName = 'default') {
    $key = getEncryptionKey($keyName);
    if (!$key) {
        logError("Encryption key not found: $keyName");
        return false;
    }
    
    $method = 'AES-256-CBC';
    $ivLength = openssl_cipher_iv_length($method);
    
    // Decode and separate IV and encrypted data
    $data = base64_decode($encryptedData);
    if (strlen($data) < $ivLength) {
        logError("Invalid encrypted data format");
        return false;
    }
    
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    
    $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        logError("Decryption failed for key: $keyName");
        return false;
    }
    
    return $decrypted;
}

/**
 * Get encryption key from file
 */
function getEncryptionKey($keyName) {
    $keyFile = SNOW_KEYS . '/' . $keyName . '.key';
    
    if (!file_exists($keyFile)) {
        return false;
    }
    
    $key = file_get_contents($keyFile);
    return trim($key);
}

/**
 * Create new encryption key
 */
function createEncryptionKey($keyName, $description = '') {
    $keyFile = SNOW_KEYS . '/' . $keyName . '.key';
    
    // Create keys directory if it doesn't exist
    if (!is_dir(SNOW_KEYS)) {
        mkdir(SNOW_KEYS, 0700, true);
    }
    
    // Generate random key (32 bytes for AES-256)
    $key = bin2hex(random_bytes(32));
    
    // Save key file with restricted permissions
    if (file_put_contents($keyFile, $key) === false) {
        return false;
    }
    
    chmod($keyFile, 0600);
    
    // Save key record in database
    $keyData = [
        'name' => $keyName,
        'description' => $description,
        'status' => 'active',
        'created_date' => date('Y-m-d H:i:s')
    ];
    
    return dbInsert('encryption_keys', $keyData);
}

/**
 * Delete encryption key
 */
function deleteEncryptionKey($keyName) {
    $keyFile = SNOW_KEYS . '/' . $keyName . '.key';
    
    // Delete file
    if (file_exists($keyFile)) {
        unlink($keyFile);
    }
    
    // Mark as deleted in database
    return dbUpdate('encryption_keys', ['status' => 'deleted'], 'name = ?', [$keyName]);
}

/**
 * List all encryption keys
 */
function getEncryptionKeys() {
    $sql = "SELECT * FROM encryption_keys WHERE status = 'active' ORDER BY name";
    return dbGetRows($sql);
}

/**
 * Encrypt database field
 */
function encryptField($value, $keyName = 'default') {
    if (empty($value)) {
        return $value;
    }
    
    $encrypted = encryptString($value, $keyName);
    if ($encrypted === false) {
        logError("Failed to encrypt field value");
        return $value;
    }
    
    return $encrypted;
}

/**
 * Decrypt database field
 */
function decryptField($encryptedValue, $keyName = 'default') {
    if (empty($encryptedValue)) {
        return $encryptedValue;
    }
    
    $decrypted = decryptString($encryptedValue, $keyName);
    if ($decrypted === false) {
        logError("Failed to decrypt field value");
        return $encryptedValue;
    }
    
    return $decrypted;
}

/**
 * Encrypt array of data for specific table fields
 */
function encryptTableData($tableName, $data) {
    // Get encrypted fields for this table
    $encryptedFields = getEncryptedFields($tableName);
    
    if (empty($encryptedFields)) {
        return $data;
    }
    
    foreach ($encryptedFields as $field) {
        if (isset($data[$field])) {
            $keyName = $tableName . '_' . $field;
            $data[$field] = encryptField($data[$field], $keyName);
        }
    }
    
    return $data;
}

/**
 * Decrypt array of data for specific table fields
 */
function decryptTableData($tableName, $data) {
    // Get encrypted fields for this table
    $encryptedFields = getEncryptedFields($tableName);
    
    if (empty($encryptedFields)) {
        return $data;
    }
    
    foreach ($encryptedFields as $field) {
        if (isset($data[$field])) {
            $keyName = $tableName . '_' . $field;
            $data[$field] = decryptField($data[$field], $keyName);
        }
    }
    
    return $data;
}

/**
 * Get encrypted fields for a table
 */
function getEncryptedFields($tableName) {
    $sql = "SELECT field_name FROM table_encryption WHERE table_name = ? AND status = 'active'";
    $results = dbGetRows($sql, [$tableName]);
    
    $fields = [];
    foreach ($results as $result) {
        $fields[] = $result['field_name'];
    }
    
    return $fields;
}

/**
 * Add field encryption for a table
 */
function addFieldEncryption($tableName, $fieldName, $keyName = null) {
    if (!$keyName) {
        $keyName = $tableName . '_' . $fieldName;
    }
    
    // Create key if it doesn't exist
    if (!getEncryptionKey($keyName)) {
        createEncryptionKey($keyName, "Encryption key for $tableName.$fieldName");
    }
    
    // Save encryption record
    $encryptionData = [
        'table_name' => $tableName,
        'field_name' => $fieldName,
        'key_name' => $keyName,
        'status' => 'active',
        'created_date' => date('Y-m-d H:i:s')
    ];
    
    return dbInsert('table_encryption', $encryptionData);
}

/**
 * Remove field encryption for a table
 */
function removeFieldEncryption($tableName, $fieldName) {
    return dbUpdate('table_encryption', ['status' => 'deleted'], 'table_name = ? AND field_name = ?', [$tableName, $fieldName]);
}

/**
 * Encrypt existing data in a table field
 */
function encryptExistingData($tableName, $fieldName) {
    $keyName = $tableName . '_' . $fieldName;
    
    // Get all records
    $sql = "SELECT id, $fieldName FROM $tableName";
    $records = dbGetRows($sql);
    
    foreach ($records as $record) {
        if (!empty($record[$fieldName])) {
            $encryptedValue = encryptField($record[$fieldName], $keyName);
            $updateSQL = "UPDATE $tableName SET $fieldName = ? WHERE id = ?";
            dbQuery($updateSQL, [$encryptedValue, $record['id']]);
        }
    }
    
    return count($records);
}

/**
 * Decrypt existing data in a table field
 */
function decryptExistingData($tableName, $fieldName) {
    $keyName = $tableName . '_' . $fieldName;
    
    // Get all records
    $sql = "SELECT id, $fieldName FROM $tableName";
    $records = dbGetRows($sql);
    
    foreach ($records as $record) {
        if (!empty($record[$fieldName])) {
            $decryptedValue = decryptField($record[$fieldName], $keyName);
            $updateSQL = "UPDATE $tableName SET $fieldName = ? WHERE id = ?";
            dbQuery($updateSQL, [$decryptedValue, $record['id']]);
        }
    }
    
    return count($records);
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate password reset token
 */
function generatePasswordResetToken() {
    return generateSecureToken(32) . '-' . time();
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    $minLength = getenv('PASSWORD_MIN_LENGTH') ?: 8;
    $errors = [];
    
    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least $minLength characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}
?>