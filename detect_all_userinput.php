<?php
// Web Input Vector Analyzer
// Identifies all forms of user input on a target URL

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

class InputVectorAnalyzer {
    private $url;
    private $html;
    private $headers;
    private $results = [];
    
    public function __construct($url) {
        $this->url = $url;
    }
    
    public function analyze() {
        if (!$this->fetchPage()) {
            return false;
        }
        
        $this->analyzeForms();
        $this->analyzeGetParameters();
        $this->analyzeHeaders();
        $this->analyzeCookies();
        $this->analyzeFileUploads();
        $this->analyzeJsonEndpoints();
        $this->analyzeWebSockets();
        
        return $this->results;
    }
    
    private function fetchPage() {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if (curl_errno($ch)) {
            $this->results['error'] = curl_error($ch);
            curl_close($ch);
            return false;
        }
        
        $this->headers = substr($response, 0, $headerSize);
        $this->html = substr($response, $headerSize);
        curl_close($ch);
        
        return true;
    }
    
    private function analyzeForms() {
        $forms = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        $formElements = $dom->getElementsByTagName('form');
        
        foreach ($formElements as $form) {
            $formData = [
                'action' => $form->getAttribute('action') ?: $this->url,
                'method' => strtoupper($form->getAttribute('method') ?: 'GET'),
                'inputs' => []
            ];
            
            $inputs = $form->getElementsByTagName('input');
            foreach ($inputs as $input) {
                $formData['inputs'][] = [
                    'name' => $input->getAttribute('name'),
                    'type' => $input->getAttribute('type') ?: 'text',
                    'value' => $input->getAttribute('value'),
                ];
            }
            
            $textareas = $form->getElementsByTagName('textarea');
            foreach ($textareas as $textarea) {
                $formData['inputs'][] = [
                    'name' => $textarea->getAttribute('name'),
                    'type' => 'textarea',
                    'value' => $textarea->nodeValue,
                ];
            }
            
            $selects = $form->getElementsByTagName('select');
            foreach ($selects as $select) {
                $options = [];
                foreach ($select->getElementsByTagName('option') as $option) {
                    $options[] = $option->getAttribute('value');
                }
                $formData['inputs'][] = [
                    'name' => $select->getAttribute('name'),
                    'type' => 'select',
                    'options' => $options,
                ];
            }
            
            $forms[] = $formData;
        }
        
        $this->results['forms'] = $forms;
    }
    
    private function analyzeGetParameters() {
        $params = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        
        // Extract from links
        $links = $dom->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, '?') !== false) {
                $queryString = parse_url($href, PHP_URL_QUERY);
                if ($queryString) {
                    parse_str($queryString, $parsed);
                    foreach ($parsed as $key => $value) {
                        if (!isset($params[$key])) {
                            $params[$key] = [
                                'found_in' => [],
                                'example_values' => []
                            ];
                        }
                        $params[$key]['found_in'][] = 'link: ' . htmlspecialchars($href);
                        if (!in_array($value, $params[$key]['example_values'])) {
                            $params[$key]['example_values'][] = $value;
                        }
                    }
                }
            }
        }
        
        // Extract from current URL
        $currentQuery = parse_url($this->url, PHP_URL_QUERY);
        if ($currentQuery) {
            parse_str($currentQuery, $parsed);
            foreach ($parsed as $key => $value) {
                if (!isset($params[$key])) {
                    $params[$key] = [
                        'found_in' => [],
                        'example_values' => []
                    ];
                }
                $params[$key]['found_in'][] = 'current URL';
                $params[$key]['example_values'][] = $value;
            }
        }
        
        $this->results['get_parameters'] = $params;
    }
    
    private function analyzeHeaders() {
        $userInputHeaders = [
            'User-Agent' => 'Browser identification',
            'Referer' => 'Previous page URL',
            'Accept' => 'Content type preferences',
            'Accept-Language' => 'Language preferences',
            'Accept-Encoding' => 'Encoding preferences',
            'X-Forwarded-For' => 'Client IP (proxy)',
            'X-Real-IP' => 'Client IP',
            'Origin' => 'Request origin',
            'Host' => 'Target hostname',
            'Authorization' => 'Authentication token',
            'X-Requested-With' => 'AJAX identifier',
            'Content-Type' => 'Request body type',
        ];
        
        $this->results['user_controllable_headers'] = $userInputHeaders;
    }
    
    private function analyzeCookies() {
        $cookies = [];
        $headerLines = explode("\r\n", $this->headers);
        
        foreach ($headerLines as $line) {
            if (stripos($line, 'Set-Cookie:') === 0) {
                $cookieStr = substr($line, 12);
                $parts = explode(';', $cookieStr);
                $cookiePair = explode('=', trim($parts[0]), 2);
                
                if (count($cookiePair) === 2) {
                    $cookies[] = [
                        'name' => trim($cookiePair[0]),
                        'value' => trim($cookiePair[1]),
                        'attributes' => array_slice($parts, 1),
                    ];
                }
            }
        }
        
        $this->results['cookies'] = $cookies;
    }
    
    private function analyzeFileUploads() {
        $uploads = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        
        $inputs = $dom->getElementsByTagName('input');
        foreach ($inputs as $input) {
            if ($input->getAttribute('type') === 'file') {
                $uploads[] = [
                    'name' => $input->getAttribute('name'),
                    'accept' => $input->getAttribute('accept'),
                    'multiple' => $input->hasAttribute('multiple'),
                ];
            }
        }
        
        $this->results['file_uploads'] = $uploads;
    }
    
    private function analyzeJsonEndpoints() {
        $jsonEndpoints = [];
        
        // Look for API endpoints in JavaScript
        if (preg_match_all('/(?:fetch|axios|ajax)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $this->html, $matches)) {
            foreach ($matches[1] as $endpoint) {
                if (strpos($endpoint, '/api') !== false || strpos($endpoint, '.json') !== false) {
                    $jsonEndpoints[] = $endpoint;
                }
            }
        }
        
        $this->results['potential_json_endpoints'] = array_unique($jsonEndpoints);
    }
    
    private function analyzeWebSockets() {
        $websockets = [];
        
        if (preg_match_all('/new\s+WebSocket\s*\(\s*[\'"]([^\'"]+)[\'"]/', $this->html, $matches)) {
            $websockets = $matches[1];
        }
        
        $this->results['websockets'] = $websockets;
    }
}

// Handle form submission
$results = null;
$targetUrl = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $targetUrl = $_POST['url'];
    
    // Validate URL
    if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        $error = 'Invalid URL format';
    } else {
        $analyzer = new InputVectorAnalyzer($targetUrl);
        $results = $analyzer->analyze();
        
        if (isset($results['error'])) {
            $error = $results['error'];
            $results = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Input Vector Analyzer</title>
    <link rel="stylesheet" href="/css/input_detect.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Web Input Vector Analyzer</h1>
            <p>Discover all forms of user input on any webpage</p>
        </div>
        
        <div class="form-container">
            <form method="POST">
                <div class="input-group">
                    <input type="text" name="url" placeholder="Enter target URL (e.g., https://example.com)" 
                           value="<?= htmlspecialchars($targetUrl) ?>" required>
                    <button type="submit">Analyze</button>
                </div>
                <div class="warning">
                    Only analyze websites you own or have permission to test. Unauthorized testing may be illegal.
                </div>
            </form>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($results): ?>
            <div class="results">
                <!-- Forms -->
                <div class="result-section">
                    <div class="result-header">
                        HTML Forms
                        <span class="result-count"><?= count($results['forms']) ?></span>
                    </div>
                    <div class="result-content">
                        <?php if (empty($results['forms'])): ?>
                            <div class="empty">No forms found</div>
                        <?php else: ?>
                            <?php foreach ($results['forms'] as $i => $form): ?>
                                <div class="item">
                                    <div class="item-title">
                                        <span class="badge <?= strtolower($form['method']) ?>"><?= $form['method'] ?></span>
                                        Form #<?= $i + 1 ?>
                                    </div>
                                    <div class="item-detail"><strong>Action:</strong> <?= htmlspecialchars($form['action']) ?></div>
                                    <div class="item-detail"><strong>Inputs:</strong></div>
                                    <?php foreach ($form['inputs'] as $input): ?>
                                        <div class="item-detail">
                                            • <code><?= htmlspecialchars($input['name']) ?></code> 
                                            (<?= htmlspecialchars($input['type']) ?>)
                                            <?php if (!empty($input['value'])): ?>
                                                - Default: "<?= htmlspecialchars($input['value']) ?>"
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- GET Parameters -->
                <div class="result-section">
                    <div class="result-header">
                        GET Parameters
                        <span class="result-count"><?= count($results['get_parameters']) ?></span>
                    </div>
                    <div class="result-content">
                        <?php if (empty($results['get_parameters'])): ?>
                            <div class="empty">No GET parameters found</div>
                        <?php else: ?>
                            <?php foreach ($results['get_parameters'] as $param => $data): ?>
                                <div class="item">
                                    <div class="item-title">
                                        <span class="badge get">GET</span>
                                        <code><?= htmlspecialchars($param) ?></code>
                                    </div>
                                    <div class="item-detail"><strong>Example values:</strong> <?= htmlspecialchars(implode(', ', $data['example_values'])) ?></div>
                                    <div class="item-detail"><strong>Found in:</strong> <?= count($data['found_in']) ?> location(s)</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- File Uploads -->
                <div class="result-section">
                    <div class="result-header">
                        File Upload Inputs
                        <span class="result-count"><?= count($results['file_uploads']) ?></span>
                    </div>
                    <div class="result-content">
                        <?php if (empty($results['file_uploads'])): ?>
                            <div class="empty">No file upload inputs found</div>
                        <?php else: ?>
                            <?php foreach ($results['file_uploads'] as $upload): ?>
                                <div class="item">
                                    <div class="item-title">
                                        <span class="badge file">FILE</span>
                                        <code><?= htmlspecialchars($upload['name']) ?></code>
                                    </div>
                                    <?php if ($upload['accept']): ?>
                                        <div class="item-detail"><strong>Accepted types:</strong> <?= htmlspecialchars($upload['accept']) ?></div>
                                    <?php endif; ?>
                                    <div class="item-detail"><strong>Multiple:</strong> <?= $upload['multiple'] ? 'Yes' : 'No' ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Cookies -->
                <div class="result-section">
                    <div class="result-header">
                        Cookies (Set by Server)
                        <span class="result-count"><?= count($results['cookies']) ?></span>
                    </div>
                    <div class="result-content">
                        <?php if (empty($results['cookies'])): ?>
                            <div class="empty">No cookies set by server</div>
                        <?php else: ?>
                            <?php foreach ($results['cookies'] as $cookie): ?>
                                <div class="item">
                                    <div class="item-title">
                                        <code><?= htmlspecialchars($cookie['name']) ?></code>
                                    </div>
                                    <div class="item-detail"><strong>Attributes:</strong> <?= htmlspecialchars(implode(', ', $cookie['attributes'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- User-Controllable Headers -->
                <div class="result-section">
                    <div class="result-header">
                        User-Controllable HTTP Headers
                        <span class="result-count"><?= count($results['user_controllable_headers']) ?></span>
                    </div>
                    <div class="result-content">
                        <?php foreach ($results['user_controllable_headers'] as $header => $desc): ?>
                            <div class="item">
                                <div class="item-title"><code><?= htmlspecialchars($header) ?></code></div>
                                <div class="item-detail"><?= htmlspecialchars($desc) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- JSON Endpoints -->
                <?php if (!empty($results['potential_json_endpoints'])): ?>
                <div class="result-section">
                    <div class="result-header">
                        Potential JSON/API Endpoints
                        <span class="result-count"><?= count($results['potential_json_endpoints']) ?></span>
                    </div>
                    <div class="result-content">
                        <?php foreach ($results['potential_json_endpoints'] as $endpoint): ?>
                            <div class="item">
                                <div class="item-title"><code><?= htmlspecialchars($endpoint) ?></code></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- WebSockets -->
                <?php if (!empty($results['websockets'])): ?>
                <div class="result-section">
                    <div class="result-header">
                        WebSocket Connections
                        <span class="result-count"><?= count($results['websockets']) ?></span>
                    </div>
                    <div class="result-content">
                        <?php foreach ($results['websockets'] as $ws): ?>
                            <div class="item">
                                <div class="item-title"><code><?= htmlspecialchars($ws) ?></code></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
