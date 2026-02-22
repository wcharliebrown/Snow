<?php
/**
 * Report System for Snow Framework
 */

/**
 * Derive PHP class name from report slug
 * e.g. 'pages_list' → 'PagesListReport'
 */
function reportClassNameFromName($name) {
    return str_replace('_', '', ucwords($name, '_')) . 'Report';
}

/**
 * Absolute path to the report's PHP file
 */
function getReportFilePath($name) {
    $slug = preg_replace('/[^a-z0-9_]/i', '_', $name);
    return dirname(__DIR__) . '/reports/' . $slug . '.php';
}

/**
 * Load a report from its PHP class file and return as a data array.
 * Returns null if the file or class does not exist.
 */
function reportArrayFromFile($name) {
    $path      = getReportFilePath($name);
    $className = reportClassNameFromName($name);
    if (!file_exists($path)) return null;
    if (!class_exists($className)) require_once $path;
    if (!class_exists($className)) return null;

    $obj = new $className();
    return [
        'name'              => $name,
        'description'       => $obj->description(),
        'sql_table'         => $obj->sql_table(),
        'sql_fields'        => $obj->sql_fields(),
        'sql_where'         => $obj->sql_where(),
        'sql_order'         => $obj->sql_order(),
        'rows_per_page'     => (int)$obj->rows_per_page(),
        'output_format'     => $obj->output_format(),
        'html_header'       => $obj->html_header(),
        'html_row_template' => $obj->html_row_template(),
        'html_footer'       => $obj->html_footer(),
        'status'            => 'active',
    ];
}

/**
 * Write a report data array to its PHP class file in reports/.
 */
function writeReportToFile($data) {
    $name      = $data['name'] ?? '';
    if (!$name) return false;
    $path      = getReportFilePath($name);
    $className = reportClassNameFromName($name);

    $fields = ['name', 'description', 'sql_table', 'sql_fields', 'sql_where',
               'sql_order', 'rows_per_page', 'output_format', 'html_header',
               'html_row_template', 'html_footer'];

    $content = "<?php\nclass {$className} {\n";
    foreach ($fields as $field) {
        $value    = $data[$field] ?? ($field === 'rows_per_page' ? 20 : null);
        $exported = var_export($value, true);
        $content .= "    public function {$field}() {\n        return {$exported};\n    }\n";
    }
    $content .= "}\n";

    return file_put_contents($path, $content) !== false;
}

/**
 * Sync the DB row for a report from its file (file wins).
 * Creates the DB row if it does not exist yet.
 */
function syncReportFromFile($name) {
    $fileData = reportArrayFromFile($name);
    if (!$fileData) return false;

    $existing = dbGetRow("SELECT id FROM report_templates WHERE name = ?", [$name]);
    if ($existing) {
        $update = $fileData;
        unset($update['name']);
        dbUpdate('report_templates', $update, 'name = ?', [$name]);
    } else {
        dbInsert('report_templates', $fileData);
    }
    return true;
}

/**
 * Get report by name
 */
function getReportByName($reportName) {
    $sql = "SELECT * FROM report_templates WHERE name = ? AND status = 'active'";
    return dbGetRow($sql, [$reportName]);
}

/**
 * Get all reports
 */
function getAllReports($status = 'active') {
    $sql = "SELECT * FROM report_templates WHERE status = ? ORDER BY name";
    return dbGetRows($sql, [$status]);
}

/**
 * Render a report
 */
function renderReport($report, $params = []) {
    // Build SQL query
    $sql = buildReportSQL($report, $params);
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = $report['rows_per_page'] ?: 20;
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countSQL = "SELECT COUNT(*) as total FROM ($sql) as count_query";
    $countResult = dbGetRow($countSQL, $params);
    $totalRows = $countResult['total'];
    
    // Get data rows
    $dataSQL = $sql . " LIMIT $perPage OFFSET $offset";
    $rows = dbGetRows($dataSQL, $params);
    
    // Process row template for each row
    $rowHTML = '';
    foreach ($rows as $row) {
        $rowHTML .= processTokens($report['html_row_template'], $row);
    }
    
    // Build complete HTML
    $html = $report['html_header'] . $rowHTML . $report['html_footer'];
    
    // Add pagination if needed
    if ($totalRows > $perPage) {
        $html .= buildPagination($page, $perPage, $totalRows, $report['name']);
    }
    
    return $html;
}

/**
 * Build report SQL from template
 */
function buildReportSQL($report, $params = []) {
    $sql = "SELECT " . ($report['sql_fields'] ?: '*') . " FROM " . $report['sql_table'];
    
    // Add WHERE clause if specified
    if (!empty($report['sql_where'])) {
        $sql .= " WHERE " . $report['sql_where'];
    }
    
    // Add ORDER BY if specified
    if (!empty($report['sql_order'])) {
        $sql .= " ORDER BY " . $report['sql_order'];
    }
    
    return $sql;
}

/**
 * Save report template (DB + file)
 */
function saveReport($data) {
    if (isset($data['id']) && $data['id']) {
        $result = dbUpdate('report_templates', $data, 'id = ?', [$data['id']]);
    } else {
        $data['created_date'] = date('Y-m-d H:i:s');
        $result = dbInsert('report_templates', $data);
    }
    writeReportToFile($data);
    return $result;
}

/**
 * Delete report
 */
function deleteReport($reportId) {
    return dbUpdate('report_templates', ['status' => 'deleted'], 'id = ?', [$reportId]);
}

/**
 * Export report as CSV
 */
function exportReportCSV($report, $params = []) {
    $sql = buildReportSQL($report, $params);
    $rows = dbGetRows($sql, $params);
    
    if (empty($rows)) {
        return false;
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report['name'] . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add header row
    $headers = array_keys($rows[0]);
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Build pagination HTML
 */
function buildPagination($currentPage, $perPage, $totalRows, $reportName) {
    $totalPages = ceil($totalRows / $perPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav class="pagination-nav"><ul class="pagination">';
    
    // Previous link
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?report=' . $reportName . '&page=' . ($currentPage - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?report=' . $reportName . '&page=1">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($page = $startPage; $page <= $endPage; $page++) {
        if ($page == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="?report=' . $reportName . '&page=' . $page . '">' . $page . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?report=' . $reportName . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next link
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="?report=' . $reportName . '&page=' . ($currentPage + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Search reports
 */
function searchReports($searchTerm, $limit = 50) {
    $sql = "SELECT * FROM report_templates 
            WHERE (name LIKE ? OR description LIKE ?) 
            AND status = 'active' 
            ORDER BY name 
            LIMIT ?";
    
    $searchParam = "%$searchTerm%";
    return dbGetRows($sql, [$searchParam, $searchParam, $limit]);
}

/**
 * Get report statistics
 */
function getReportStats() {
    $stats = [
        'total_reports' => 0,
        'active_reports' => 0,
        'html_reports' => 0,
        'csv_reports' => 0
    ];
    
    $sql = "SELECT 
            COUNT(*) as total_reports,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_reports,
            SUM(CASE WHEN output_format = 'html' THEN 1 ELSE 0 END) as html_reports,
            SUM(CASE WHEN output_format = 'csv' THEN 1 ELSE 0 END) as csv_reports
            FROM report_templates";
    
    $result = dbGetRow($sql);
    if ($result) {
        $stats = array_merge($stats, $result);
    }
    
    return $stats;
}

/**
 * Validate report template
 */
function validateReport($data) {
    $errors = [];
    
    // Check required fields
    if (empty($data['name'])) {
        $errors[] = 'Report name is required';
    }
    
    if (empty($data['sql_table'])) {
        $errors[] = 'SQL table is required';
    }
    
    if (empty($data['html_row_template'])) {
        $errors[] = 'HTML row template is required';
    }
    
    // Test SQL syntax
    if (!empty($data['sql_table'])) {
        try {
            $testSQL = "SELECT * FROM " . $data['sql_table'] . " LIMIT 1";
            dbQuery($testSQL);
        } catch (Exception $e) {
            $errors[] = 'Invalid SQL table: ' . $e->getMessage();
        }
    }
    
    return $errors;
}

/**
 * Duplicate a report (DB + file)
 */
function duplicateReport($reportId, $newName) {
    $original = dbGetRow("SELECT * FROM report_templates WHERE id = ?", [$reportId]);
    if (!$original) {
        return false;
    }

    $newReport = $original;
    unset($newReport['id']);
    $newReport['name']         = $newName;
    $newReport['created_date'] = date('Y-m-d H:i:s');

    $newId = dbInsert('report_templates', $newReport);
    writeReportToFile($newReport);
    return $newId;
}
?>