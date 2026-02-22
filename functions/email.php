<?php
/**
 * Email System for Snow Framework
 */

/**
 * Send email template
 */
function sendEmailTemplate($templateName, $to, $data = []) {
    $template = getEmailTemplate($templateName);
    if (!$template) {
        logError("Email template not found: $templateName");
        return false;
    }
    
    // Process template tokens
    $subject = processTokens($template['subject'], $data);
    $body = processTokens($template['body'], $data);
    $from = processTokens($template['from_address'], $data);
    $bcc = processTokens($template['bcc'], $data);
    
    // Create unsubscribe token if needed
    $unsubscribeToken = null;
    if ($template['allow_unsubscribe']) {
        $unsubscribeToken = createUnsubscribeToken($to, $templateName);
        $unsubscribeLink = getenv('SITE_URL') . '/unsubscribe?token=' . $unsubscribeToken;
        $body .= '<br><br><small><a href="' . $unsubscribeLink . '">Unsubscribe</a></small>';
    }
    
    // Send email
    $success = sendEmail($to, $subject, $body, $from, $bcc);
    
    if ($success) {
        logEmail($to, $subject, $templateName);
    }
    
    return $success;
}

/**
 * Send email directly
 */
function sendEmail($to, $subject, $body, $from = null, $bcc = null) {
    // Set headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ($from ?: getenv('SMTP_FROM')),
    ];
    
    if ($bcc) {
        $headers[] = 'BCC: ' . $bcc;
    }
    
    // Use SMTP if configured, otherwise use mail()
    if (getenv('SMTP_HOST') && function_exists('mail')) {
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    // Fallback to PHP mail
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Get email template by name
 */
function getEmailTemplate($templateName) {
    $sql = "SELECT * FROM email_templates WHERE name = ? AND status = 'active'";
    return dbGetRow($sql, [$templateName]);
}

/**
 * Get all email templates
 */
function getAllEmailTemplates($status = 'active') {
    $sql = "SELECT * FROM email_templates WHERE status = ? ORDER BY name";
    return dbGetRows($sql, [$status]);
}

/**
 * Save email template
 */
function saveEmailTemplate($data) {
    if (isset($data['id']) && $data['id']) {
        return dbUpdate('email_templates', $data, 'id = ?', [$data['id']]);
    } else {
        $data['created_date'] = date('Y-m-d H:i:s');
        return dbInsert('email_templates', $data);
    }
}

/**
 * Delete email template
 */
function deleteEmailTemplate($templateId) {
    return dbUpdate('email_templates', ['status' => 'deleted'], 'id = ?', [$templateId]);
}

/**
 * Create unsubscribe token
 */
function createUnsubscribeToken($email, $templateName) {
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 year'));
    
    // Get user ID from email
    $user = dbGetRow("SELECT id FROM users WHERE email = ?", [$email]);
    $userId = $user ? $user['id'] : null;
    
    // Save unsubscribe token
    $unsubscribeData = [
        'email' => $email,
        'user_id' => $userId,
        'template_name' => $templateName,
        'token' => $token,
        'status' => 'active',
        'created_date' => date('Y-m-d H:i:s'),
        'expiry_date' => $expiry
    ];
    
    return dbInsert('unsubscribe_tokens', $unsubscribeData);
}

/**
 * Process unsubscribe request
 */
function processUnsubscribe($token) {
    $sql = "SELECT * FROM unsubscribe_tokens WHERE token = ? AND status = 'active' AND expiry_date > NOW()";
    $unsubscribe = dbGetRow($sql, [$token]);
    
    if (!$unsubscribe) {
        return false;
    }
    
    // Mark as unsubscribed
    dbUpdate('unsubscribe_tokens', ['status' => 'unsubscribed'], 'id = ?', [$unsubscribe['id']]);
    
    // Update user preferences if user exists
    if ($unsubscribe['user_id']) {
        dbUpdate('users', ['email_subscriptions' => 0], 'id = ?', [$unsubscribe['user_id']]);
    }
    
    logInfo("Email unsubscribed: {$unsubscribe['email']} from template: {$unsubscribe['template_name']}");
    return $unsubscribe;
}

/**
 * Check if email is unsubscribed from template
 */
function isUnsubscribed($email, $templateName) {
    $sql = "SELECT COUNT(*) as count 
            FROM unsubscribe_tokens 
            WHERE email = ? AND template_name = ? AND status = 'unsubscribed'";
    
    $result = dbGetRow($sql, [$email, $templateName]);
    return $result['count'] > 0;
}

/**
 * Get unsubscribe statistics
 */
function getUnsubscribeStats() {
    $stats = [
        'total_tokens' => 0,
        'active_tokens' => 0,
        'unsubscribed_tokens' => 0,
        'expired_tokens' => 0
    ];
    
    $sql = "SELECT
            COUNT(*) as total_tokens,
            COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active_tokens,
            COALESCE(SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END), 0) as unsubscribed_tokens,
            COALESCE(SUM(CASE WHEN expiry_date < NOW() THEN 1 ELSE 0 END), 0) as expired_tokens
            FROM unsubscribe_tokens";
    
    $result = dbGetRow($sql);
    if ($result) {
        $stats = array_merge($stats, $result);
    }
    
    return $stats;
}

/**
 * Clean up expired unsubscribe tokens
 */
function cleanupExpiredUnsubscribeTokens() {
    $sql = "DELETE FROM unsubscribe_tokens WHERE expiry_date < NOW()";
    $stmt = dbQuery($sql);
    return $stmt->rowCount();
}

/**
 * Validate email template
 */
function validateEmailTemplate($data) {
    $errors = [];
    
    // Check required fields
    if (empty($data['name'])) {
        $errors[] = 'Template name is required';
    }
    
    if (empty($data['subject'])) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($data['body'])) {
        $errors[] = 'Body is required';
    }
    
    // Validate email addresses
    if (!empty($data['from_address']) && !filter_var($data['from_address'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid from address';
    }
    
    return $errors;
}

/**
 * Test email template
 */
function testEmailTemplate($templateName, $testEmail) {
    $template = getEmailTemplate($templateName);
    if (!$template) {
        return false;
    }
    
    $testData = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $testEmail,
        'test_mode' => true
    ];
    
    return sendEmailTemplate($templateName, $testEmail, $testData);
}

/**
 * Get email delivery statistics
 */
function getEmailStats($startDate = null, $endDate = null) {
    $stats = [
        'total_sent' => 0,
        'total_templates' => 0,
        'most_used_template' => null
    ];
    
    $whereClause = "1=1";
    $params = [];
    
    if ($startDate) {
        $whereClause .= " AND created_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereClause .= " AND created_date <= ?";
        $params[] = $endDate;
    }
    
    // Get email logs count
    $emailLogFile = SNOW_LOGS . '/email.log';
    if (file_exists($emailLogFile)) {
        $lines = file($emailLogFile, FILE_IGNORE_NEW_LINES);
        $stats['total_sent'] = count($lines);
    }
    
    // Get template count
    $sql = "SELECT COUNT(*) as count FROM email_templates WHERE status = 'active'";
    $result = dbGetRow($sql);
    $stats['total_templates'] = $result['count'];
    
    return $stats;
}
?>