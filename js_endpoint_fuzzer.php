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
    <link rel="stylesheet" href="/assets/endpoint_fuzz.css">
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
