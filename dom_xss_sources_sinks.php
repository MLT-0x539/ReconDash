<?php
// script to identify all potential DOM-based sources/sinks for XSS
// Remote JS file passed as input

// Config
$timeout = 10; 

$sources = [
    'document.URL',
    'document.documentURI',
    'document.baseURI',
    'location.href',
    'location.search',
    'location.hash',
    'location.pathname',
    'document.cookie',
    'document.referrer',
    'window.name',
    'history.pushState',
    'history.replaceState',
    'localStorage',
    'sessionStorage',
    'document.domain',
];

$sinks = [
    'eval(',
    'setTimeout(',
    'setInterval(',
    'Function(',
    'innerHTML',
    'outerHTML',
    'document.write(',
    'document.writeln(',
    'insertAdjacentHTML(',
    '.src',
    '.href',
    '.action',
    '.formAction',
    '.location',
    'execScript(',
    'setImmediate(',
    'execCommand(',
    'setAttribute(',
    'setAttributeNode(',
    '.innerText',
    '.textContent',
];

function fetchJavaScript($url, $timeout) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'Mozilla/5.0 (DOM XSS Scanner)',
        ]
    ]);
    
    $content = @file_get_contents($url, false, $ctx);
    
    if ($content === false) {
        throw new Exception("Failed to fetch URL: $url");
    }
    
    return $content;
}

function scanJavaScript($content, $sources, $sinks) {
    $lines = explode("\n", $content);
    $results = [
        'sources' => [],
        'sinks' => [],
    ];
    
    foreach ($lines as $lineNum => $line) {
        $lineNumber = $lineNum + 1;
        $trimmedLine = trim($line);
        
        if (empty($trimmedLine) || strpos($trimmedLine, '//') === 0 || strpos($trimmedLine, '/*') === 0) {
            continue;
        }
    
        foreach ($sources as $source) {
            if (stripos($line, $source) !== false) {
                $results['sources'][] = [
                    'name' => $source,
                    'line_number' => $lineNumber,
                    'code' => $trimmedLine,
                ];
            }
        }
      
        foreach ($sinks as $sink) {
            if (stripos($line, $sink) !== false) {
                $results['sinks'][] = [
                    'name' => $sink,
                    'line_number' => $lineNumber,
                    'code' => $trimmedLine,
                ];
            }
        }
    }
    return $results;
}

function formatOutput($url, $results) {
    $output = "<!DOCTYPE html>
<html>
<head>
    <title>DOM XSS Scan Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .url { background: #ecf0f1; padding: 10px; border-radius: 4px; word-break: break-all; margin: 20px 0; }
        .summary { background: #3498db; color: white; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .summary span { font-weight: bold; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #34495e; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .code { font-family: 'Courier New', monospace; background: #f8f8f8; padding: 5px; border-left: 3px solid #3498db; }
        .sources th { background: #e67e22; }
        .sinks th { background: #e74c3c; }
        .no-results { color: #27ae60; font-weight: bold; padding: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>DOM-Based XSS Vulnerability Scanner</h1>
        <div class='url'><strong>Analyzed File:</strong> " . htmlspecialchars($url) . "</div>
        
        <div class='summary'>
            <strong>Summary:</strong> 
            Found <span>" . count($results['sources']) . "</span> potential sources and 
            <span>" . count($results['sinks']) . "</span> potential sinks
        </div>";
    
    // Display Sources
    $output .= "<h2>DOM XSS Sources (User Input)</h2>";
    if (count($results['sources']) > 0) {
        $output .= "<table class='sources'>
            <tr>
                <th>Line</th>
                <th>Source</th>
                <th>Code</th>
            </tr>";
        
        foreach ($results['sources'] as $item) {
            $output .= "<tr>
                <td><strong>{$item['line_number']}</strong></td>
                <td>{$item['name']}</td>
                <td class='code'>" . htmlspecialchars($item['code']) . "</td>
            </tr>";
        }
        
        $output .= "</table>";
    } else {
        $output .= "<div class='no-results'>No DOM XSS sources detected</div>";
    }
    
    // Display Sinks
    $output .= "<h2>DOM XSS Sinks (Dangerous Operations)</h2>";
    if (count($results['sinks']) > 0) {
        $output .= "<table class='sinks'>
            <tr>
                <th>Line</th>
                <th>Sink</th>
                <th>Code</th>
            </tr>";
        
        foreach ($results['sinks'] as $item) {
            $output .= "<tr>
                <td><strong>{$item['line_number']}</strong></td>
                <td>{$item['name']}</td>
                <td class='code'>" . htmlspecialchars($item['code']) . "</td>
            </tr>";
        }
        
        $output .= "</table>";
    } else {
        $output .= "<div class='no-results'>No DOM XSS sinks detected</div>";
    }
    
    $output .= "
        <div style='margin-top: 30px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;'>
            <strong>Note:</strong> This is a basic pattern-matching scanner. Manual review is required to determine if vulnerabilities actually exist. Not all detected items are necessarily vulnerable.
        </div>
    </div>
</body>
</html>";
    
    return $output;
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
        $url = $_POST['url'];
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL provided");
        }
        
        // Fetch and scan JavaScript
        $content = fetchJavaScript($url, $timeout);
        $results = scanJavaScript($content, $sources, $sinks);
        
        // Output results
        echo formatOutput($url, $results);
        
    } else {
        // Display input form
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>DOM XSS Scanner</title>
            <link rel="stylesheet" href="/assets/dom_source_sink.css">
        </head>
        <body>
            <div class="container">
                <h1>DOM XSS Scanner</h1>
                <p class="subtitle">Analyze JavaScript files for potential XSS vulnerabilities</p>
                
                <form method="POST">
                    <label for="url">JavaScript File URL:</label>
                    <input type="text" id="url" name="url" placeholder="https://example.com/script.js" required>
                    <button type="submit">Scan File</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }
} catch (Exception $e) {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; margin: 20px;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    echo "<a href='" . $_SERVER['PHP_SELF'] . "' style='margin: 20px; display: inline-block;'>← Go Back</a>";
}
?>
