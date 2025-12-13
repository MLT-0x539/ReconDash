<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes timeout

class JSEndpointCrawler {
    private $baseUrl;
    private $jsFiles = [];
    private $endpoints = [];
    private $visitedUrls = [];
    
    public function __construct($url) {
        $this->baseUrl = $this->normalizeUrl($url);
    }
    
    private function normalizeUrl($url) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "https://" . $url;
        }
        return rtrim($url, '/');
    }
    
    private function makeAbsoluteUrl($url, $base) {
        // Already absolute
        if (preg_match("~^(?:f|ht)tps?://~i", $url)) {
            return $url;
        }
        
        // Protocol relative
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        $parsedBase = parse_url($base);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';
        
        // Absolute path
        if (strpos($url, '/') === 0) {
            return $scheme . '://' . $host . $url;
        }
        
        // Relative path
        $path = $parsedBase['path'] ?? '/';
        $path = substr($path, 0, strrpos($path, '/') + 1);
        return $scheme . '://' . $host . $path . $url;
    }
    
    public function crawl() {
        try {
            $html = $this->fetchContent($this->baseUrl);
            if (!$html) {
                throw new Exception("Failed to fetch the URL");
            }
            
            $this->extractJSFiles($html, $this->baseUrl);
            $this->analyzeJSFiles();
            
            return [
                'jsFiles' => $this->jsFiles,
                'endpoints' => $this->endpoints
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function fetchContent($url) {
        if (in_array($url, $this->visitedUrls)) {
            return null;
        }
        $this->visitedUrls[] = $url;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode == 200) ? $content : null;
    }
    
    private function extractJSFiles($html, $baseUrl) {
        // Match script tags with src attribute
        preg_match_all('/<script[^>]+src=["\'](.*?)["\']/', $html, $matches);
        
        foreach ($matches[1] as $src) {
            if (strpos($src, 'data:') === 0) continue;
            
            $absoluteUrl = $this->makeAbsoluteUrl($src, $baseUrl);
            if (!in_array($absoluteUrl, $this->jsFiles)) {
                $this->jsFiles[] = $absoluteUrl;
            }
        }
        
        // Also check for inline scripts with URLs
        preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $inlineScripts);
        foreach ($inlineScripts[1] as $script) {
            $this->extractEndpointsFromJS($script, 'inline-script');
        }
    }
    
    private function analyzeJSFiles() {
        foreach ($this->jsFiles as $jsFile) {
            $content = $this->fetchContent($jsFile);
            if ($content) {
                $this->extractEndpointsFromJS($content, $jsFile);
            }
        }
    }
    
    private function extractEndpointsFromJS($jsContent, $source) {
        $patterns = [
            // API endpoints with quotes
            '/["\']([\/][a-zA-Z0-9_\-\/\{\}\.]+)["\']/',
            // Full URLs
            '/["\']((https?:)?\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}[^\s"\']*)["\']/',
            // fetch() and axios calls
            '/(?:fetch|axios|ajax)\s*\(\s*["\']([^"\']+)["\']/',
            // $.ajax, $.get, $.post
            '/\$\.(?:ajax|get|post)\s*\(\s*["\']([^"\']+)["\']/',
            // XMLHttpRequest.open
            '/\.open\s*\(\s*["\'][^"\']+["\']\s*,\s*["\']([^"\']+)["\']/',
            // API endpoints in variables
            '/(?:url|endpoint|api|path)\s*[:=]\s*["\']([^"\']+)["\']/',
            // Template literals with URLs
            '/`([^`]*(?:\/api|\/v\d|http)[^`]*)`/',
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $jsContent, $matches);
            
            foreach ($matches[1] as $match) {
                $match = trim($match);
                
                // Skip common false positives
                if (empty($match) || 
                    strpos($match, 'javascript:') === 0 ||
                    strpos($match, 'mailto:') === 0 ||
                    strpos($match, '#') === 0 ||
                    $match === '/' ||
                    strlen($match) < 4) {
                    continue;
                }
                
                // Check if it looks like a valid endpoint
                if (preg_match('/^(https?:)?\/\//', $match) || 
                    preg_match('/^\/[a-zA-Z0-9]/', $match) ||
                    preg_match('/\/(api|v\d|endpoint)/', $match)) {
                    
                    $endpoint = [
                        'url' => $match,
                        'source' => basename($source),
                        'type' => $this->categorizeEndpoint($match)
                    ];
                    
                    // Avoid duplicates
                    $exists = false;
                    foreach ($this->endpoints as $existing) {
                        if ($existing['url'] === $match) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $this->endpoints[] = $endpoint;
                    }
                }
            }
        }
    }
    
    private function categorizeEndpoint($url) {
        if (preg_match('/\/(api|v\d)\//', $url)) return 'API';
        if (preg_match('/\.(json|xml)/', $url)) return 'Data';
        if (preg_match('/^https?:\/\//', $url)) return 'External';
        return 'Endpoint';
    }
}

// Handle form submission
$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $crawler = new JSEndpointCrawler($_POST['url']);
    $results = $crawler->crawl();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JavaScript Endpoint Crawler</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .subtitle {
            opacity: 0.9;
            font-size: 0.95em;
        }
        
        .form-section {
            padding: 30px;
            background: #f8f9fa;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        input[type="text"] {
            flex: 1;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .results {
            padding: 30px;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            opacity: 0.9;
            font-size: 0.9em;
        }
        
        .section-title {
            font-size: 1.5em;
            margin: 30px 0 15px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .file-list, .endpoint-list {
            list-style: none;
        }
        
        .file-item, .endpoint-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            word-break: break-all;
        }
        
        .endpoint-item {
            display: grid;
            gap: 8px;
        }
        
        .endpoint-url {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #333;
        }
        
        .endpoint-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #666;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75em;
        }
        
        .badge-api { background: #e3f2fd; color: #1976d2; }
        .badge-data { background: #f3e5f5; color: #7b1fa2; }
        .badge-external { background: #fff3e0; color: #f57c00; }
        .badge-endpoint { background: #e8f5e9; color: #388e3c; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>JavaScript Endpoint Crawler</h1>
            <p class="subtitle">Discover API endpoints and URLs hidden in JavaScript files</p>
        </header>
        
        <div class="form-section">
            <form method="POST">
                <div class="input-group">
                    <input 
                        type="text" 
                        name="url" 
                        placeholder="Enter URL (e.g., example.com or https://example.com)" 
                        value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>"
                        required
                    >
                    <button type="submit">Crawl</button>
                </div>
            </form>
        </div>
        
        <?php if ($results): ?>
        <div class="results">
            <?php if (isset($results['error'])): ?>
                <div class="error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($results['error']); ?>
                </div>
            <?php else: ?>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($results['jsFiles']); ?></div>
                        <div class="stat-label">JS Files Found</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($results['endpoints']); ?></div>
                        <div class="stat-label">Endpoints Discovered</div>
                    </div>
                </div>
                
                <?php if (!empty($results['jsFiles'])): ?>
                    <h2 class="section-title">JavaScript Files</h2>
                    <ul class="file-list">
                        <?php foreach ($results['jsFiles'] as $file): ?>
                            <li class="file-item"><?php echo htmlspecialchars($file); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($results['endpoints'])): ?>
                    <h2 class="section-title">Discovered Endpoints</h2>
                    <ul class="endpoint-list">
                        <?php foreach ($results['endpoints'] as $endpoint): ?>
                            <li class="endpoint-item">
                                <div class="endpoint-url"><?php echo htmlspecialchars($endpoint['url']); ?></div>
                                <div class="endpoint-meta">
                                    <span class="badge badge-<?php echo strtolower($endpoint['type']); ?>">
                                        <?php echo htmlspecialchars($endpoint['type']); ?>
                                    </span>
                                    <span>Source: <?php echo htmlspecialchars($endpoint['source']); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No endpoints found in the JavaScript files.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
