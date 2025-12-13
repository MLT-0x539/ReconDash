<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);
ini_set('memory_limit', '512M');

class WebCrawler {
    private $baseUrl;
    private $baseDomain;
    private $visitedUrls = [];
    private $discoveredFiles = [];
    private $maxDepth = 3;
    private $maxUrls = 100;
    
    public function __construct($url, $maxDepth = 3, $maxUrls = 100) {
        $this->baseUrl = rtrim($url, '/');
        $parsed = parse_url($url);
        $this->baseDomain = $parsed['host'];
        $this->maxDepth = $maxDepth;
        $this->maxUrls = $maxUrls;
    }
    
    public function crawl() {
        $this->crawlUrl($this->baseUrl, 0);
        return $this->discoveredFiles;
    }
    
    private function crawlUrl($url, $depth) {
        if ($depth > $this->maxDepth || count($this->visitedUrls) >= $this->maxUrls) {
            return;
        }
        
        $normalizedUrl = $this->normalizeUrl($url);
        
        if (in_array($normalizedUrl, $this->visitedUrls)) {
            return;
        }
        
        $this->visitedUrls[] = $normalizedUrl;
        
        $content = $this->fetchUrl($url);
        if ($content === false) {
            return;
        }
        
        // Add this file to discovered files
        $this->addDiscoveredFile($url);
        
        // Extract links from the page
        $links = $this->extractLinks($content, $url);
        
        foreach ($links as $link) {
            if (count($this->visitedUrls) >= $this->maxUrls) {
                break;
            }
            $this->crawlUrl($link, $depth + 1);
        }
    }
    
    private function fetchUrl($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode == 200) ? $content : false;
    }
    
    private function extractLinks($html, $currentUrl) {
        $links = [];
        
        // Extract href attributes
        preg_match_all('/<a[^>]+href=["\'](.*?)["\'][^>]*>/i', $html, $matches);
        
        foreach ($matches[1] as $link) {
            $absoluteUrl = $this->makeAbsoluteUrl($link, $currentUrl);
            
            if ($this->isValidUrl($absoluteUrl)) {
                $links[] = $absoluteUrl;
            }
        }
        
        // Also look for form actions
        preg_match_all('/<form[^>]+action=["\'](.*?)["\'][^>]*>/i', $html, $formMatches);
        
        foreach ($formMatches[1] as $link) {
            $absoluteUrl = $this->makeAbsoluteUrl($link, $currentUrl);
            
            if ($this->isValidUrl($absoluteUrl)) {
                $links[] = $absoluteUrl;
            }
        }
        
        return array_unique($links);
    }
    
    private function makeAbsoluteUrl($url, $base) {
        // Remove anchor
        $url = preg_replace('/#.*$/', '', $url);
        
        // Already absolute
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        
        $baseParsed = parse_url($base);
        
        // Protocol-relative URL
        if (strpos($url, '//') === 0) {
            return $baseParsed['scheme'] . ':' . $url;
        }
        
        // Absolute path
        if (strpos($url, '/') === 0) {
            return $baseParsed['scheme'] . '://' . $baseParsed['host'] . $url;
        }
        
        // Relative path
        $basePath = preg_replace('/\/[^\/]*$/', '/', $base);
        return $basePath . $url;
    }
    
    private function normalizeUrl($url) {
        // Remove query string and fragment for comparison
        return preg_replace('/[?#].*$/', '', $url);
    }
    
    private function isValidUrl($url) {
        if (empty($url) || strpos($url, 'javascript:') === 0 || strpos($url, 'mailto:') === 0) {
            return false;
        }
        
        $parsed = parse_url($url);
        
        // Must be same domain
        if (!isset($parsed['host']) || $parsed['host'] !== $this->baseDomain) {
            return false;
        }
        
        return true;
    }
    
    private function addDiscoveredFile($url) {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        // Identify file types
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        $fileInfo = [
            'url' => $url,
            'path' => $path,
            'extension' => $extension,
            'has_query' => isset($parsed['query']),
            'query_string' => $parsed['query'] ?? ''
        ];
        
        $this->discoveredFiles[] = $fileInfo;
    }
    
    public function getVisitedCount() {
        return count($this->visitedUrls);
    }
}

class ParameterFuzzer {
    private $commonParams = [
        'id', 'page', 'user', 'name', 'search', 'query', 'q', 'category', 'cat',
        'action', 'view', 'file', 'path', 'url', 'redirect', 'return', 'src',
        'debug', 'admin', 'mode', 'type', 'lang', 'language', 'sort', 'order',
        'limit', 'offset', 'start', 'end', 'date', 'time', 'email', 'username',
        'password', 'token', 'key', 'api_key', 'session', 'sid', 'callback',
        'data', 'value', 'content', 'text', 'message', 'title', 'description'
    ];
    
    public function fuzzFile($url, $method = 'GET', $customParams = []) {
        $paramsToTest = empty($customParams) ? $this->commonParams : $customParams;
        $results = [];
        
        foreach ($paramsToTest as $param) {
            $result = $this->testParameter($url, $param, $method);
            if ($result['interesting']) {
                $results[] = $result;
            }
            
            // Small delay to avoid overwhelming the server
            usleep(100000); // 100ms
        }
        
        return $results;
    }
    
    private function testParameter($url, $param, $method = 'GET') {
        $testValue = 'test123';
        $baselineResponse = $this->makeRequest($url, [], $method);
        
        if ($baselineResponse === false) {
            return [
                'param' => $param,
                'interesting' => false,
                'reason' => 'baseline_failed'
            ];
        }
        
        $testResponse = $this->makeRequest($url, [$param => $testValue], $method);
        
        if ($testResponse === false) {
            return [
                'param' => $param,
                'interesting' => false,
                'reason' => 'test_failed'
            ];
        }
        
        $result = [
            'param' => $param,
            'method' => $method,
            'baseline_length' => strlen($baselineResponse['body']),
            'test_length' => strlen($testResponse['body']),
            'baseline_code' => $baselineResponse['code'],
            'test_code' => $testResponse['code'],
            'interesting' => false,
            'reason' => ''
        ];
        
        // Check if response changed
        if ($testResponse['code'] !== $baselineResponse['code']) {
            $result['interesting'] = true;
            $result['reason'] = 'status_code_changed';
        } elseif (abs(strlen($testResponse['body']) - strlen($baselineResponse['body'])) > 10) {
            $result['interesting'] = true;
            $result['reason'] = 'response_length_changed';
        } elseif ($this->containsParamInResponse($testResponse['body'], $param, $testValue)) {
            $result['interesting'] = true;
            $result['reason'] = 'param_reflected';
        } elseif ($this->hasErrorMessage($testResponse['body'])) {
            $result['interesting'] = true;
            $result['reason'] = 'error_message_detected';
        }
        
        return $result;
    }
    
    private function makeRequest($url, $params = [], $method = 'GET') {
        if ($method === 'GET' && !empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($body === false) {
            return false;
        }
        
        return [
            'body' => $body,
            'code' => $code
        ];
    }
    
    private function containsParamInResponse($body, $param, $value) {
        return (stripos($body, $value) !== false || stripos($body, $param) !== false);
    }
    
    private function hasErrorMessage($body) {
        $errorPatterns = [
            '/error/i',
            '/warning/i',
            '/exception/i',
            '/undefined/i',
            '/invalid/i',
            '/missing/i',
            '/required/i'
        ];
        
        foreach ($errorPatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function getCommonParams() {
        return $this->commonParams;
    }
}

$crawlResults = null;
$fuzzResults = null;
$error = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] === '1') {
        // Step 1: Crawl website
        $url = filter_var($_POST['url'] ?? '', FILTER_SANITIZE_URL);
        $maxDepth = intval($_POST['max_depth'] ?? 3);
        $maxUrls = intval($_POST['max_urls'] ?? 100);
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Please provide a valid URL.';
        } else {
            $crawler = new WebCrawler($url, $maxDepth, $maxUrls);
            $discoveredFiles = $crawler->crawl();
            
            $crawlResults = [
                'url' => $url,
                'visited' => $crawler->getVisitedCount(),
                'files' => $discoveredFiles
            ];
            
            $step = 2;
        }
    } elseif (isset($_POST['step']) && $_POST['step'] === '2') {
        // Step 2: Fuzz parameters
        $selectedFiles = $_POST['selected_files'] ?? [];
        $method = $_POST['method'] ?? 'GET';
        $customParams = [];
        
        if (!empty($_POST['custom_params'])) {
            $customParams = array_map('trim', explode("\n", $_POST['custom_params']));
            $customParams = array_filter($customParams);
        }
        
        if (empty($selectedFiles)) {
            $error = 'Please select at least one file to fuzz.';
            $step = 2;
        } else {
            $fuzzer = new ParameterFuzzer();
            $fuzzResults = [];
            
            foreach ($selectedFiles as $fileUrl) {
                $results = $fuzzer->fuzzFile($fileUrl, $method, $customParams);
                
                if (!empty($results)) {
                    $fuzzResults[$fileUrl] = $results;
                }
            }
            
            $step = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Crawler & Parameter Fuzzer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 30px;
        }
        
        h1 {
            color: #1e3c72;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .step-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        
        .step-item:not(:last-child)::after {
            content: '→';
            position: absolute;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
            font-size: 24px;
        }
        
        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .step-item.active .step-number {
            background: #1e3c72;
            color: white;
        }
        
        .step-title {
            font-size: 14px;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="url"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="url"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #1e3c72;
        }
        
        textarea {
            resize: vertical;
            font-family: monospace;
        }
        
        .inline-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        button {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #1e3c72;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .file-card {
            background: #f9f9f9;
            border: 2px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            transition: border-color 0.3s;
        }
        
        .file-card:hover {
            border-color: #1e3c72;
        }
        
        .file-card input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .file-path {
            font-family: monospace;
            font-size: 13px;
            color: #333;
            word-break: break-all;
        }
        
        .file-ext {
            display: inline-block;
            background: #1e3c72;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-top: 5px;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .results-table th,
        .results-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .results-table th {
            background: #1e3c72;
            color: white;
            font-weight: 600;
        }
        
        .results-table tr:hover {
            background: #f5f5f5;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .result-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .result-header {
            background: #f8f9fa;
            padding: 15px;
            font-weight: 600;
            border-bottom: 1px solid #ddd;
        }
        
        .result-body {
            padding: 15px;
        }
        
        .param-result {
            background: #f9f9f9;
            border-left: 3px solid #1e3c72;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Website Crawler & Parameter Fuzzer</h1>
        <p class="subtitle">Discover files and fuzz for hidden parameters</p>
        
        <div class="steps">
            <div class="step-item <?= $step >= 1 ? 'active' : '' ?>">
                <div class="step-number">1</div>
                <div class="step-title">Crawl Website</div>
            </div>
            <div class="step-item <?= $step >= 2 ? 'active' : '' ?>">
                <div class="step-number">2</div>
                <div class="step-title">Select Files</div>
            </div>
            <div class="step-item <?= $step >= 3 ? 'active' : '' ?>">
                <div class="step-number">3</div>
                <div class="step-title">View Results</div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <form method="POST">
                <input type="hidden" name="step" value="1">
                
                <div class="form-group">
                    <label for="url">Target URL:</label>
                    <input type="url" id="url" name="url" placeholder="https://example.com" required>
                </div>
                
                <div class="inline-fields">
                    <div class="form-group">
                        <label for="max_depth">Maximum Crawl Depth:</label>
                        <input type="number" id="max_depth" name="max_depth" value="3" min="1" max="10">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_urls">Maximum URLs to Crawl:</label>
                        <input type="number" id="max_urls" name="max_urls" value="100" min="10" max="500">
                    </div>
                </div>
                
                <button type="submit">Start Crawling</button>
            </form>
        <?php endif; ?>
        
        <?php if ($step === 2 && $crawlResults): ?>
            <div class="info-box">
                <strong>Crawl Complete!</strong><br>
                Target: <?= htmlspecialchars($crawlResults['url']) ?><br>
                URLs Visited: <?= $crawlResults['visited'] ?><br>
                Files Discovered: <?= count($crawlResults['files']) ?>
            </div>
            
            <form method="POST">
                <input type="hidden" name="step" value="2">
                
                <div class="form-group">
                    <label>Select Files to Fuzz:</label>
                    <div class="files-grid">
                        <?php foreach ($crawlResults['files'] as $idx => $file): ?>
                            <div class="file-card">
                                <label>
                                    <input type="checkbox" name="selected_files[]" value="<?= htmlspecialchars($file['url']) ?>" checked>
                                    <div class="file-path"><?= htmlspecialchars($file['path']) ?></div>
                                    <?php if ($file['extension']): ?>
                                        <span class="file-ext">.<?= htmlspecialchars($file['extension']) ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="method">HTTP Method:</label>
                    <select id="method" name="method">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="custom_params">Custom Parameters (optional, one per line):</label>
                    <textarea id="custom_params" name="custom_params" rows="6" placeholder="Leave empty to use default common parameters&#10;Or add your own like:&#10;user_id&#10;api_token&#10;custom_param"></textarea>
                </div>
                
                <button type="submit">Start Fuzzing</button>
            </form>
        <?php endif; ?>
        
        <?php if ($step === 3 && $fuzzResults !== null): ?>
            <div class="info-box">
                <strong>Fuzzing Complete!</strong><br>
                Files Fuzzed: <?= count($fuzzResults) ?><br>
                Interesting Parameters Found: <?= array_sum(array_map('count', $fuzzResults)) ?>
            </div>
            
            <?php if (empty($fuzzResults)): ?>
                <div class="no-results">
                    No interesting parameters found. This could mean:<br>
                    - The files don't accept the tested parameters<br>
                    - The server responds identically regardless of parameters<br>
                    - The parameters require specific authentication
                </div>
            <?php else: ?>
                <?php foreach ($fuzzResults as $fileUrl => $params): ?>
                    <div class="result-section">
                        <div class="result-header">
                            📄 <?= htmlspecialchars($fileUrl) ?>
                        </div>
                        <div class="result-body">
                            <?php foreach ($params as $param): ?>
                                <div class="param-result">
                                    <strong>Parameter:</strong> <code><?= htmlspecialchars($param['param']) ?></code>
                                    <span class="badge badge-info"><?= htmlspecialchars($param['method']) ?></span>
                                    
                                    <?php if ($param['reason'] === 'status_code_changed'): ?>
                                        <span class="badge badge-warning">Status Code Changed</span>
                                        <div style="margin-top: 8px;">
                                            Baseline: <?= $param['baseline_code'] ?> → Test: <?= $param['test_code'] ?>
                                        </div>
                                    <?php elseif ($param['reason'] === 'response_length_changed'): ?>
                                        <span class="badge badge-success">Response Length Changed</span>
                                        <div style="margin-top: 8px;">
                                            Baseline: <?= $param['baseline_length'] ?> bytes → Test: <?= $param['test_length'] ?> bytes
                                        </div>
                                    <?php elseif ($param['reason'] === 'param_reflected'): ?>
                                        <span class="badge badge-danger">Parameter Reflected</span>
                                        <div style="margin-top: 8px;">
                                            The parameter or its value appears in the response
                                        </div>
                                    <?php elseif ($param['reason'] === 'error_message_detected'): ?>
                                        <span class="badge badge-warning">Error Message Detected</span>
                                        <div style="margin-top: 8px;">
                                            The response contains error-related keywords
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="step" value="1">
                <button type="submit">Start New Scan</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
