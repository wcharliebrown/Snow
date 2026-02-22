<?php
/**
 * Template System for Snow Framework - Simple Approach
 */

/**
 * Render a template with data
 */
function renderTemplate($templateFile, $data = []) {
    $templatePath = SNOW_TEMPLATES . '/' . $templateFile;
    
    if (!file_exists($templatePath)) {
        logError("Template not found: $templateFile");
        echo "<h1>Template Error</h1><p>Template '$templateFile' not found.</p>";
        return;
    }
    
    // Read template content
    $content = file_get_contents($templatePath);
    
    // Process token replacements
    $content = processTokens($content, $data);
    
    echo $content;
}

/**
 * Process token replacements in template
 */
function processTokens($content, $data) {
    // Replace loops {{#each array}}...{{/each}} FIRST, recursing into each item
    $content = preg_replace_callback('/\{\{#each\s+(\w+)\}\}(.*?)\{\{\/each\}\}/s', function($matches) use ($data) {
        $arrayKey = $matches[1];
        $template = $matches[2];

        if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) {
            return '';
        }

        $result = '';
        foreach ($data[$arrayKey] as $index => $item) {
            $itemContent = str_replace('{{@index}}', (string)$index, $template);
            if (is_array($item)) {
                // Recurse so nested {{#if}}/{{else}} blocks inside loops are resolved
                $itemContent = processTokens($itemContent, $item);
            } elseif (is_scalar($item)) {
                $itemContent = str_replace('{{this}}', (string)$item, $itemContent);
            }
            $result .= $itemContent;
        }

        return $result;
    }, $content);

    // Replace unless blocks {{#unless condition}}...{{/unless}}
    $content = preg_replace_callback('/\{\{#unless\s+(\w+)\}\}(.*?)\{\{\/unless\}\}/s', function($matches) use ($data) {
        $condition = $matches[1];
        $blockContent = $matches[2];
        return empty($data[$condition]) ? $blockContent : '';
    }, $content);

    // Replace conditional blocks {{#if condition}}...{{else}}...{{/if}}
    // Uses innermost-first matching (body must not contain a nested {{#if) plus a loop so
    // each pass resolves the deepest remaining blocks until none are left.
    do {
        $prev = $content;
        $content = preg_replace_callback(
            '/\{\{#if\s+(\w+)\}\}((?:(?!\{\{#if\s).)*?)\{\{\/if\}\}/s',
            function($matches) use ($data) {
                $condition = $matches[1];
                $blockContent = $matches[2];
                $parts = explode('{{else}}', $blockContent, 2);
                $ifPart   = $parts[0];
                $elsePart = isset($parts[1]) ? $parts[1] : '';
                return !empty($data[$condition]) ? $ifPart : $elsePart;
            },
            $content
        );
    } while ($content !== $prev);

    // Replace object properties {{object.property}}
    $content = preg_replace_callback('/\{\{(\w+)\.(\w+)\}\}/', function($matches) use ($data) {
        $object   = $matches[1];
        $property = $matches[2];
        if (isset($data[$object]) && is_array($data[$object]) && isset($data[$object][$property])) {
            return (string)$data[$object][$property];
        }
        return '';
    }, $content);

    // Replace simple variables LAST
    foreach ($data as $key => $value) {
        if (!is_array($value) && !is_object($value)) {
            $content = str_replace('{{' . $key . '}}', (string)$value, $content);
        }
    }

    // Strip any remaining unresolved tokens (e.g. unknown variables, stray {{/if}}, {{else}})
    $content = preg_replace('/\{\{[^}]*\}\}/', '', $content);

    return $content;
}

/**
 * Process embedded reports {{report_name}}
 */
function processEmbeddedReports($content) {
    return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) {
        $reportName = $matches[1];
        
        // Check if this is a report name (not a regular template variable)
        $report = getReportByName($reportName);
        if ($report) {
            return renderReport($report);
        }
        
        // Return original if not a report
        return $matches[0];
    }, $content);
}
?>