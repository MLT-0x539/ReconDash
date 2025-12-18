<?php
// JavaScript De-obfuscator, Un-minifier & Beautifier
// Supports URL input, file upload, and direct code paste

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

class JSDeobfuscator {
    private $js_code = '';
    private $errors = [];
    
    public function __construct($code = '') {
        $this->js_code = $code;
    }
    
    public function process() {
        if (empty($this->js_code)) {
            $this->errors[] = "No JavaScript code provided";
            return false;
        }
    
        $this->js_code = $this->decodeObfuscation();
        $this->js_code = $this->beautify();
        return true;
    }
    
    private function decodeObfuscation() {
        $code = $this->js_code;
        $code = preg_replace_callback('/\\\\x([0-9A-Fa-f]{2})/', function($matches) {
            return chr(hexdec($matches[1]));
        }, $code);

        $code = preg_replace_callback('/\\\\u([0-9A-Fa-f]{4})/', function($matches) {
            return mb_convert_encoding('&#' . hexdec($matches[1]) . ';', 'UTF-8', 'HTML-ENTITIES');
        }, $code);

        $code = preg_replace_callback('/\\\\([0-7]{1,3})/', function($matches) {
            return chr(octdec($matches[1]));
        }, $code);

        $code = str_replace("']['", "." , $code);
        $code = str_replace('"]["', "." , $code);
        return $code;
    }
    
    private function beautify() {
        $code = $this->js_code;
        $indent = 0;
        $indent_str = '    '; 
        $in_string = false;
        $string_char = '';
        $result = '';
        $length = strlen($code);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $code[$i];
            $next_char = ($i + 1 < $length) ? $code[$i + 1] : '';
            $prev_char = ($i > 0) ? $code[$i - 1] : '';
            if (($char === '"' || $char === "'" || $char === '`') && $prev_char !== '\\') {
                if (!$in_string) {
                    $in_string = true;
                    $string_char = $char;
                } else if ($char === $string_char) {
                    $in_string = false;
                }
                $result .= $char;
                continue;
            }

            if ($in_string) {
                $result .= $char;
                continue;
            }

            switch ($char) {
                case '{':
                case '[':
                    $result .= $char . "\n";
                    $indent++;
                    $result .= str_repeat($indent_str, $indent);
                    break;
                    
                case '}':
                case ']':
                    $result = rtrim($result);
                    $indent--;
                    if ($indent < 0) $indent = 0;
                    $result .= "\n" . str_repeat($indent_str, $indent) . $char;
                    if ($next_char !== ';' && $next_char !== ',' && $next_char !== ')') {
                        $result .= "\n" . str_repeat($indent_str, $indent);
                    }
                    break;
                    
                case ';':
                    $result .= $char;
                    if ($next_char !== ' ' && $next_char !== "\n" && $next_char !== '}') {
                        $result .= "\n" . str_repeat($indent_str, $indent);
                    }
                    break;
                    
                case ',':
                    $result .= $char . ' ';
                    break;
                    
                case ':':
                    $result .= $char . ' ';
                    break;
                    
                case '(':
                    $result .= $char;
                    break;
                    
                case ')':
                    $result .= $char;
                    if ($next_char === '{') {
                        $result .= ' ';
                    }
                    break;
                    
                default:
                    if ($char === ' ' && $prev_char === ' ') {
                        continue;
                    }
                    $result .= $char;
            }
        }
        
        $result = preg_replace("/\n\n+/", "\n\n", $result);    
        return trim($result);
    }
    
    public function getCode() {
        return $this->js_code;
    }
    
    public function getErrors() {
        return $this->errors;
    }
}

$output = '';
$errors = [];
$input_method = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $js_code = '';
    
    if (!empty($_POST['url'])) {
        $input_method = 'URL';
        $url = filter_var($_POST['url'], FILTER_VALIDATE_URL);
        
        if ($url) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $js_code = @file_get_contents($url, false, $context);
            
            if ($js_code === false) {
                $errors[] = "Failed to fetch JavaScript from URL";
            }
        } else {
            $errors[] = "Invalid URL provided";
        }
    }
      
    else if (!empty($_FILES['js_file']['tmp_name'])) {
        $input_method = 'File Upload';
        $file = $_FILES['js_file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === 'js' || $ext === 'txt') {
                $js_code = file_get_contents($file['tmp_name']);
            } else {
                $errors[] = "Please upload a .js or .txt file";
            }
        } else {
            $errors[] = "File upload failed with error code: " . $file['error'];
        }
    }
      
    else if (!empty($_POST['js_code'])) {
        $input_method = 'Direct Paste';
        $js_code = $_POST['js_code'];
    }
    else {
        $errors[] = "No input provided. Please use one of the three input methods.";
    }

    if (!empty($js_code) && empty($errors)) {
        $deobfuscator = new JSDeobfuscator($js_code);
        
        if ($deobfuscator->process()) {
            $output = $deobfuscator->getCode();
        } else {
            $errors = array_merge($errors, $deobfuscator->getErrors());
        }
    }
}
?>
  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JavaScript De-obfuscator & Beautifier</title>
  <link rel="stylesheet" href="/assets/deob_unminify_beautify.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>JS de-obfuscator, unminifier, & beautifier</h1>
            <p>Un-minify, de-obfuscate, and beautify JavaScript code from URL, file, or direct paste</p>
        </div>
        
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <strong>Error:</strong>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($output)): ?>
                <div class="success">
                    <strong>Success!</strong> JavaScript code processed using: <?php echo htmlspecialchars($input_method); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="input-section">
                    <div class="info-box">
                        <strong>Choose one input method:</strong> You can fetch JS from a URL, upload a .js file, or paste code directly.
                    </div>
                    
                    <div class="input-method">
                        <h3><b>Option 1:</b>b> Fetch from URL</h3>
                        <input type="text" 
                               name="url" 
                               placeholder="https://example.com/script.js"
                               value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>">
                    </div>
                    
                    <div class="input-method">
                        <h3><b>Option 2:</b> Upload JavaScript File</h3>
                        <input type="file" name="js_file" accept=".js,.txt">
                    </div>
                    
                    <div class="input-method">
                        <h3><b>Option 3:</b> Paste JavaScript Code</h3>
                        <textarea name="js_code" placeholder="Paste your JavaScript code here..."><?php echo isset($_POST['js_code']) ? htmlspecialchars($_POST['js_code']) : ''; ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">Process JavaScript</button>
            </form>
            
            <?php if (!empty($output)): ?>
                <div class="output-section">
                    <h2>Beautified Output:</h2>
                    <button class="copy-btn" onclick="copyToClipboard()">Copy to Clipboard</button>
                    <pre class="code-output" id="output-code"><?php echo htmlspecialchars($output); ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="/js/clipboard_copy.js">
</body>
</html>
