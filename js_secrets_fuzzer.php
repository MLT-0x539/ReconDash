<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

function crawlForJavaScript($url) {
    $jsFiles = [];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200 || !$html) {
        return ['error' => "Failed to fetch URL. HTTP Code: $httpCode"];
    }
    
    $parsedUrl = parse_url($url);
    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    
    // Find script tags with src attribute
    preg_match_all('/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
    
    foreach ($matches[1] as $jsUrl) {
        // Convert relative URLs to absolute
        if (strpos($jsUrl, '//') === 0) {
            $jsUrl = $parsedUrl['scheme'] . ':' . $jsUrl;
        } elseif (strpos($jsUrl, '/') === 0) {
            $jsUrl = $baseUrl . $jsUrl;
        } elseif (!preg_match('/^https?:\/\//i', $jsUrl)) {
            $jsUrl = rtrim($url, '/') . '/' . ltrim($jsUrl, '/');
        }
        
        if (!in_array($jsUrl, $jsFiles)) {
            $jsFiles[] = $jsUrl;
        }
    }
    
    // Also find inline script references
    preg_match_all('/["\']([^"\']*\.js(?:\?[^"\']*)?)["\']/', $html, $inlineMatches);
    
    foreach ($inlineMatches[1] as $jsUrl) {
        if (strpos($jsUrl, '//') === 0) {
            $jsUrl = $parsedUrl['scheme'] . ':' . $jsUrl;
        } elseif (strpos($jsUrl, '/') === 0) {
            $jsUrl = $baseUrl . $jsUrl;
        } elseif (!preg_match('/^https?:\/\//i', $jsUrl)) {
            $jsUrl = rtrim($url, '/') . '/' . ltrim($jsUrl, '/');
        }
        
        if (!in_array($jsUrl, $jsFiles) && preg_match('/^https?:\/\//i', $jsUrl)) {
            $jsFiles[] = $jsUrl;
        }
    }
    
    return $jsFiles;
}

function fetchJavaScriptContent($jsUrl) {
    $ch = curl_init($jsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode == 200) ? $content : false;
}

function searchInContent($content, $keywords) {
    $matches = [];
    
    foreach ($keywords as $keyword) {
        $keyword = trim($keyword);
        if (empty($keyword)) continue;
        
        if (stripos($content, $keyword) !== false) {
            // Count occurrences
            $count = substr_count(strtolower($content), strtolower($keyword));
            $matches[] = [
                'keyword' => $keyword,
                'count' => $count
            ];
        }
    }
    
    return $matches;
}

$results = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = filter_var($_POST['url'] ?? '', FILTER_SANITIZE_URL);
    $keywords = [];
    
    // Get keywords from text input
    if (!empty($_POST['wordlist_text'])) {
        $textKeywords = explode("\n", $_POST['wordlist_text']);
        $keywords = array_merge($keywords, $textKeywords);
    }
    
    // Get keywords from file upload
    if (isset($_FILES['wordlist_file']) && $_FILES['wordlist_file']['error'] === UPLOAD_ERR_OK) {
        $fileContent = file_get_contents($_FILES['wordlist_file']['tmp_name']);
        $fileKeywords = explode("\n", $fileContent);
        $keywords = array_merge($keywords, $fileKeywords);
    }
    
    // Clean keywords
    $keywords = array_map('trim', $keywords);
    $keywords = array_filter($keywords);
    $keywords = array_unique($keywords);
    
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Please provide a valid URL.';
    } elseif (empty($keywords)) {
        $error = 'Please provide a wordlist (file or text).';
    } else {
        // Step 1: Crawl for JS files
        $jsFiles = crawlForJavaScript($url);
        
        if (isset($jsFiles['error'])) {
            $error = $jsFiles['error'];
        } else {
            $results['url'] = $url;
            $results['js_files'] = $jsFiles;
            $results['keyword_count'] = count($keywords);
            $results['matches'] = [];
            
            // Step 2: Search each JS file
            foreach ($jsFiles as $jsUrl) {
                $content = fetchJavaScriptContent($jsUrl);
                
                if ($content !== false) {
                    $matches = searchInContent($content, $keywords);
                    
                    if (!empty($matches)) {
                        $results['matches'][$jsUrl] = $matches;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JS File Crawler & Wordlist Scanner</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
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
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            resize: vertical;
            font-family: monospace;
        }
        
        input[type="file"] {
            padding: 10px;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .results {
            margin-top: 30px;
        }
        
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box strong {
            color: #667eea;
        }
        
        .js-file {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .js-file-url {
            color: #667eea;
            font-weight: 600;
            word-break: break-all;
            margin-bottom: 10px;
        }
        
        .match {
            background: #d4edda;
            border-left: 3px solid #28a745;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 3px;
        }
        
        .keyword {
            font-weight: 600;
            color: #155724;
        }
        
        .count {
            color: #666;
            font-size: 13px;
        }
        
        .no-matches {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>JS File Crawler & Wordlist Scanner</h1>
        <p class="subtitle">Crawl a website for JavaScript files and search them for specific keywords</p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="url">Target URL:</label>
                <input type="url" id="url" name="url" placeholder="https://example.com" required 
                       value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="wordlist_file">Upload Wordlist File (optional):</label>
                <input type="file" id="wordlist_file" name="wordlist_file" accept=".txt">
            </div>
            
            <div class="form-group">
                <label for="wordlist_text">Or Enter Keywords (one per line):</label>
                <textarea id="wordlist_text" name="wordlist_text" rows="8" 
                          placeholder="api_key&#10;password&#10;secret&#10;token&#10;admin"><?= htmlspecialchars($_POST['wordlist_text'] ?? '') ?></textarea>
            </div>
            
            <button type="submit">Scan JavaScript Files</button>
        </form>
        
        <?php if (!empty($results)): ?>
            <div class="results">
                <h2>Scan Results</h2>
                
                <div class="info-box">
                    <strong>Target URL:</strong> <?= htmlspecialchars($results['url']) ?><br>
                    <strong>JavaScript Files Found:</strong> <?= count($results['js_files']) ?><br>
                    <strong>Keywords Searched:</strong> <?= $results['keyword_count'] ?><br>
                    <strong>Files with Matches:</strong> <?= count($results['matches']) ?>
                </div>
                
                <?php if (empty($results['matches'])): ?>
                    <div class="no-matches">
                       No matches found in any JavaScript files.
                    </div>
                <?php else: ?>
                    <h3>Matches Found:</h3>
                    <?php foreach ($results['matches'] as $jsUrl => $matches): ?>
                        <div class="js-file">
                            <div class="js-file-url"><?= htmlspecialchars($jsUrl) ?></div>
                            <?php foreach ($matches as $match): ?>
                                <div class="match">
                                    <span class="keyword"><?= htmlspecialchars($match['keyword']) ?></span>
                                    <span class="count">(<?= $match['count'] ?> occurrence<?= $match['count'] > 1 ? 's' : '' ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (count($results['js_files']) > 0): ?>
                    <h3>All JavaScript Files:</h3>
                    <?php foreach ($results['js_files'] as $jsFile): ?>
                        <div class="js-file">
                            <div class="js-file-url"><?= htmlspecialchars($jsFile) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
