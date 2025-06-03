<?php
require_once(__DIR__ . '/../../config.php');

// Check if vendor autoload exists
$vendor_path = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendor_path)) {
    echo "<div style='color: red; padding: 20px; border: 2px solid red; margin: 10px;'>";
    echo "<h2>⚠️ Missing Dependencies</h2>";
    echo "<p>Please install required packages:</p>";
    echo "<pre>composer require aws/aws-sdk-php smalot/pdfparser languagedetection/languagedetection</pre>";
    echo "<p>Vendor autoload not found at: " . $vendor_path . "</p>";
    echo "</div>";
    exit;
}

require_once($vendor_path);

use Aws\Polly\PollyClient;
use Smalot\PdfParser\Parser;
use LanguageDetector\LanguageDetector;

class MoodlePDFToSpeech {
    private $polly;
    private $cacheDir;
    private $speed;
    private $voiceId;
    private $detector;

    public function __construct() {
        // Load AWS credentials from environment variables or config
        $awsKey = getenv('AWS_ACCESS_KEY_ID') ?: '';
        $awsSecret = getenv('AWS_SECRET_ACCESS_KEY') ?: '';
        
        // Fallback to local config file (check .local.php first)
        if (empty($awsKey) || empty($awsSecret)) {
            $localConfigFile = __DIR__ . '/aws_config.local.php';
            $configFile = __DIR__ . '/aws_config.php';
            
            if (file_exists($localConfigFile)) {
                $config = include $localConfigFile;
                $awsKey = $config['aws_access_key_id'] ?? '';
                $awsSecret = $config['aws_secret_access_key'] ?? '';
            } elseif (file_exists($configFile)) {
                $config = include $configFile;
                $awsKey = $config['aws_access_key_id'] ?? '';
                $awsSecret = $config['aws_secret_access_key'] ?? '';
            }
        }
        
        // Fallback to Moodle config if available
        if (empty($awsKey) && function_exists('get_config')) {
            $awsKey = get_config('local_texttospeech', 'aws_access_key');
            $awsSecret = get_config('local_texttospeech', 'aws_secret_key');
        }
        
        if (empty($awsKey) || empty($awsSecret)) {
            throw new Exception("AWS credentials not configured. Please create aws_config.local.php file or set AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY environment variables.");
        }
        
        $this->polly = new PollyClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ],
        ]);

        $this->cacheDir = __DIR__ . '/tts_cache';
        if (!file_exists($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0775, true)) {
                throw new Exception("Failed to create cache directory: " . $this->cacheDir);
            }
        }
        
        // Check if directory is writable
        if (!is_writable($this->cacheDir)) {
            chmod($this->cacheDir, 0775);
            if (!is_writable($this->cacheDir)) {
                // Try more permissive permissions
                chmod($this->cacheDir, 0777);
                if (!is_writable($this->cacheDir)) {
                    throw new Exception("Cache directory is not writable: " . $this->cacheDir . ". Please run: chmod 777 " . $this->cacheDir);
                }
            }
        }

        $this->speed = 'medium';
        $this->voiceId = 'Joanna';
        $this->detector = new LanguageDetector();
    }

    private function splitText($text, $maxLength = 2500) {
        $chunks = [];
        $text = trim($text);
        
        while (strlen($text) > $maxLength) {
            $splitIndex = strrpos(substr($text, 0, $maxLength), '. ');
            if ($splitIndex === false) {
                $splitIndex = strrpos(substr($text, 0, $maxLength), ' ');
            }
            $splitIndex = $splitIndex ?: $maxLength;
            
            $chunk = trim(substr($text, 0, $splitIndex));
            if ($chunk) {
                $chunks[] = $chunk;
            }
            $text = trim(substr($text, $splitIndex));
        }
        
        if ($text) {
            $chunks[] = $text;
        }
        
        return $chunks;
    }

    public function extractText($pdfPath) {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $pages = $pdf->getPages();
            
            $allText = '';
            foreach ($pages as $page) {
                $pageText = $page->getText();
                $allText .= $pageText . "\n\n";
            }
            
            return trim($allText);
        } catch (Exception $e) {
            return false;
        }
    }

    public function detectLanguage($text) {
        try {
            return $this->detector->detect($text)->getLanguage();
        } catch (Exception $e) {
            return 'en';
        }
    }

    private function generateSsml($text) {
        $cleanText = htmlspecialchars($text, ENT_XML1, 'UTF-8');
        return "<speak><prosody rate='{$this->speed}'>{$cleanText}</prosody></speak>";
    }

    private function cacheKey($text) {
        return md5($text . $this->speed . $this->voiceId);
    }

    public function textToSpeech($text) {
        $cleanText = trim(str_replace(["\r", "\n"], ' ', $text));
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
        
        if (!$cleanText || strlen($cleanText) < 5) {
            return ['error' => 'Text too short or empty'];
        }

        $cacheFile = $this->cacheDir . '/' . $this->cacheKey($cleanText) . '.mp3';
        
        if (file_exists($cacheFile)) {
            return ['success' => true, 'file' => $cacheFile, 'cached' => true];
        }

        $chunks = $this->splitText($cleanText);
        $combined = '';
        $errors = [];

        foreach ($chunks as $index => $chunk) {
            try {
                $result = $this->polly->synthesizeSpeech([
                    'Text' => $this->generateSsml($chunk),
                    'OutputFormat' => 'mp3',
                    'VoiceId' => $this->voiceId,
                    'Engine' => 'neural',
                    'TextType' => 'ssml'
                ]);
                
                $chunkData = $result['AudioStream']->getContents();
                if ($chunkData) {
                    $combined .= $chunkData;
                } else {
                    $errors[] = "Empty audio data for chunk " . ($index + 1);
                }
                
            } catch (Exception $e) {
                $errors[] = "Chunk " . ($index + 1) . ": " . $e->getMessage();
                error_log("Polly Error for chunk " . ($index + 1) . ": " . $e->getMessage());
                continue;
            }
        }

        if ($combined && strlen($combined) > 1000) {
            $bytesWritten = file_put_contents($cacheFile, $combined);
            if ($bytesWritten === false) {
                return ['error' => 'Failed to write audio file to cache'];
            }
            return ['success' => true, 'file' => $cacheFile, 'size' => $bytesWritten, 'chunks' => count($chunks)];
        }

        return ['error' => 'Failed to generate audio. Errors: ' . implode('; ', $errors)];
    }

    public function setVoice($language) {
        $voices = [
            'en' => 'Joanna', 'fr' => 'Celine', 'es' => 'Lucia',
            'de' => 'Vicki', 'it' => 'Bianca', 'ja' => 'Mizuki', 'hi' => 'Aditi'
        ];
        $this->voiceId = $voices[substr($language, 0, 2)] ?? 'Joanna';
    }

    public function setVoiceById($voiceId) {
        $this->voiceId = $voiceId;
    }

    public function setSpeed($speed) {
        $validSpeeds = ['x-slow', 'slow', 'medium', 'fast', 'x-fast'];
        if (in_array($speed, $validSpeeds)) {
            $this->speed = $speed;
        }
    }

    public function testConnection() {
        try {
            $result = $this->polly->synthesizeSpeech([
                'Text' => 'Test connection',
                'OutputFormat' => 'mp3',
                'VoiceId' => 'Joanna',
                'Engine' => 'neural'
            ]);
            $audioData = $result['AudioStream']->getContents();
            return ['success' => true, 'message' => 'AWS Polly connection successful', 'audio_size' => strlen($audioData)];
        } catch (Exception $e) {
            return ['error' => 'AWS Polly connection failed: ' . $e->getMessage()];
        }
    }

    public function getAvailableVoices() {
        return [
            'Joanna' => 'English (US) - Female',
            'Matthew' => 'English (US) - Male', 
            'Amy' => 'English (UK) - Female',
            'Brian' => 'English (UK) - Male',
            'Celine' => 'French - Female',
            'Mathieu' => 'French - Male',
            'Lucia' => 'Spanish - Female',
            'Enrique' => 'Spanish - Male',
            'Vicki' => 'German - Female',
            'Hans' => 'German - Male'
        ];
    }

    public function getCacheInfo() {
        return [
            'directory' => $this->cacheDir,
            'exists' => file_exists($this->cacheDir),
            'writable' => is_writable($this->cacheDir),
            'permissions' => file_exists($this->cacheDir) ? substr(sprintf('%o', fileperms($this->cacheDir)), -4) : 'N/A'
        ];
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $tts = new MoodlePDFToSpeech();
        
        switch ($_POST['action']) {
            case 'test_connection':
                $result = $tts->testConnection();
                echo json_encode($result);
                break;
                
            case 'generate_audio':
                $text = $_POST['text'] ?? '';
                $voice = $_POST['voice'] ?? 'Joanna';
                $speed = $_POST['speed'] ?? 'medium';
                
                if (strlen($text) < 5) {
                    echo json_encode(['success' => false, 'error' => 'Text is too short (minimum 5 characters)']);
                    break;
                }
                
                $tts->setVoice($voice);
                $tts->setSpeed($speed);
                
                $result = $tts->textToSpeech($text);
                
                if (isset($result['success']) && $result['success']) {
                    // Fix: Use correct web path instead of str_replace
                    $audioUrl = '/moodle/local/texttospeech/tts_cache/' . basename($result['file']);
                    $response = [
                        'success' => true, 
                        'audio_url' => $audioUrl,
                        'cached' => $result['cached'] ?? false,
                        'size' => $result['size'] ?? 0,
                        'chunks' => $result['chunks'] ?? 1
                    ];
                    echo json_encode($response);
                } else {
                    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Unknown error']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Main page
echo "<h2>AWS Polly PDF Text-to-Speech Test</h2>";

// Test with a sample PDF
$test_pdf_path = __DIR__ . '/dawilly.pdf';

if (!file_exists($test_pdf_path)) {
    echo "<p style='color: orange;'>PDF file not found at: " . $test_pdf_path . "</p>";
    echo "<p>Please upload a test PDF file to this location.</p>";
    exit;
}

echo "<p>Found PDF file: " . $test_pdf_path . " (Size: " . filesize($test_pdf_path) . " bytes)</p>";

try {
    echo "<p>Extracting text from PDF...</p>";
    
    $tts = new MoodlePDFToSpeech();
    $text = $tts->extractText($test_pdf_path);
    
    if (!empty($text)) {
        echo "<p style='color: green;'>✅ PDF text extraction successful!</p>";
        echo "<p>Text length: " . strlen($text) . " characters</p>";
        echo "<h3>First 500 characters:</h3>";
        echo "<textarea rows='10' cols='80'>" . htmlspecialchars(substr($text, 0, 500)) . "...</textarea>";
        
        // Take first 200 characters for TTS test
        $test_text = substr($text, 0, 300);
        
        echo "<hr>";
        echo "<h2>AWS Polly Text-to-Speech Test</h2>";
        echo "<p>Testing TTS with first 300 characters:</p>";
        echo "<p><em>" . htmlspecialchars($test_text) . "</em></p>";
        
        // Voice selection
        echo '<div style="margin: 10px 0;">';
        echo '<label>Voice: <select id="voice-select">';
        foreach ($tts->getAvailableVoices() as $voiceId => $voiceName) {
            $selected = ($voiceId === 'Joanna') ? 'selected' : '';
            echo "<option value='{$voiceId}' {$selected}>{$voiceName}</option>";
        }
        echo '</select></label>';
        echo '</div>';
        
        // Speed selection
        echo '<div style="margin: 10px 0;">';
        echo '<label>Speed: <select id="speed-select">';
        $speeds = ['x-slow' => 'Extra Slow', 'slow' => 'Slow', 'medium' => 'Medium', 'fast' => 'Fast', 'x-fast' => 'Extra Fast'];
        foreach ($speeds as $speed => $label) {
            $selected = ($speed === 'medium') ? 'selected' : '';
            echo "<option value='{$speed}' {$selected}>{$label}</option>";
        }
        echo '</select></label>';
        echo '</div>';
        
        // Controls
        echo '<div id="tts-controls">';
        echo '<button onclick="generateAndPlay()" style="padding: 10px; margin: 5px; background: #007cba; color: white; border: none; border-radius: 4px;">🔊 Generate & Play Audio</button>';
        echo '<button onclick="stopAudio()" style="padding: 10px; margin: 5px; background: #dc3545; color: white; border: none; border-radius: 4px;">⏹ Stop</button>';
        echo '</div>';
        
        echo '<div id="tts-status" style="margin: 10px 0; padding: 10px; background: #e9ecef; border-radius: 4px;">Ready to generate audio</div>';
        
        echo '<audio id="audio-player" controls style="width: 100%; margin: 10px 0; display: none;"></audio>';
        
        $js_test_text = json_encode($test_text, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        
        echo '<script>';
        echo 'var testText = ' . $js_test_text . ';';
        echo 'var audioPlayer = document.getElementById("audio-player");';
        echo 'var statusDiv = document.getElementById("tts-status");';
        
        echo 'function updateStatus(message) {';
        echo '    statusDiv.innerHTML = message;';
        echo '    console.log("TTS:", message);';
        echo '}';
        
        echo 'function testConnection() {';
        echo '    updateStatus("🔧 Testing AWS Polly connection...");';
        echo '    ';
        echo '    var formData = new FormData();';
        echo '    formData.append("action", "test_connection");';
        echo '    ';
        echo '    fetch(window.location.href, {';
        echo '        method: "POST",';
        echo '        body: formData';
        echo '    })';
        echo '    .then(response => response.json())';
        echo '    .then(data => {';
        echo '        if (data.success) {';
        echo '            updateStatus("✅ " + data.message);';
        echo '        } else {';
        echo '            updateStatus("❌ Connection failed: " + data.error);';
        echo '        }';
        echo '    })';
        echo '    .catch(error => {';
        echo '        updateStatus("❌ Network error: " + error.message);';
        echo '    });';
        echo '}';
        
        echo 'function generateAndPlay() {';
        echo '    var voice = document.getElementById("voice-select").value;';
        echo '    var speed = document.getElementById("speed-select").value;';
        echo '    ';
        echo '    updateStatus("🔄 Generating audio with AWS Polly... (Voice: " + voice + ", Speed: " + speed + ")");';
        echo '    ';
        echo '    var formData = new FormData();';
        echo '    formData.append("action", "generate_audio");';
        echo '    formData.append("text", testText);';
        echo '    formData.append("voice", voice);';
        echo '    formData.append("speed", speed);';
        echo '    ';
        echo '    fetch(window.location.href, {';
        echo '        method: "POST",';
        echo '        body: formData';
        echo '    })';
        echo '    .then(response => {';
        echo '        if (!response.ok) {';
        echo '            throw new Error("HTTP " + response.status + ": " + response.statusText);';
        echo '        }';
        echo '        return response.json();';
        echo '    })';
        echo '    .then(data => {';
        echo '        console.log("Server response:", data);';
        echo '        if (data.success) {';
        echo '            var message = "✅ Audio generated successfully!";';
        echo '            if (data.cached) message += " (from cache)";';
        echo '            if (data.size) message += " Size: " + Math.round(data.size/1024) + "KB";';
        echo '            if (data.chunks) message += " Chunks: " + data.chunks;';
        echo '            updateStatus(message);';
        echo '            ';
        echo '            audioPlayer.src = data.audio_url + "?t=" + Date.now();';
        echo '            audioPlayer.style.display = "block";';
        echo '            audioPlayer.load();';
        echo '            ';
        echo '            var playPromise = audioPlayer.play();';
        echo '            if (playPromise !== undefined) {';
        echo '                playPromise.then(function() {';
        echo '                    console.log("Audio playback started successfully");';
        echo '                }).catch(function(e) {';
        echo '                    updateStatus("❌ Audio playback failed: " + e.message);';
        echo '                });';
        echo '            }';
        echo '        } else {';
        echo '            updateStatus("❌ Error: " + data.error);';
        echo '        }';
        echo '    })';
        echo '    .catch(error => {';
        echo '        console.error("Fetch error:", error);';
        echo '        updateStatus("❌ Network error: " + error.message);';
        echo '    });';
        echo '}';
        
        echo 'function stopAudio() {';
        echo '    audioPlayer.pause();';
        echo '    audioPlayer.currentTime = 0;';
        echo '    updateStatus("⏹ Audio stopped");';
        echo '}';
        
        echo '</script>';
        
        echo '<p style="color: green;">✅ Complete PDF-to-Speech workflow with AWS Polly ready!</p>';
        echo '<p><strong>Full text length:</strong> ' . strlen($text) . ' characters</p>';
        echo '<p><strong>Estimated reading time:</strong> ~' . round(str_word_count($text) / 200) . ' minutes</p>';
        
        echo '<p><strong>Features:</strong></p>';
        echo '<ul>';
        echo '<li>✅ Reliable AWS Polly neural voices</li>';
        echo '<li>✅ Multiple language support</li>';
        echo '<li>✅ Speed control</li>';
        echo '<li>✅ Audio caching for performance</li>';
        echo '<li>✅ Handles large texts by chunking</li>';
        echo '</ul>';
        
    } else {
        echo "<p style='color: red;'>❌ Failed to extract text from PDF</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><strong>Next step:</strong> Install dependencies and integrate into your main Moodle plugin</p>";

// Add plugin installation status and link
echo "<hr>";
echo "<h3>🔧 Moodle Plugin Integration</h3>";

// Check if plugin is properly installed
$plugin_installed = false;
if (function_exists('get_config')) {
    $plugin_version = get_config('local_texttospeech', 'version');
    if ($plugin_version) {
        $plugin_installed = true;
        echo "<p style='color: green;'>✅ Plugin is installed (Version: " . $plugin_version . ")</p>";
    }
}

if (!$plugin_installed) {
    echo "<p style='color: orange;'>⚠️ Plugin not yet installed in Moodle</p>";
}

echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007cba; margin: 10px 0;'>";
echo "<h4>🚀 To complete the Moodle integration:</h4>";
echo "<ol>";
echo "<li><strong>Install the plugin:</strong><br>";
echo "Go to <a href='/moodle/admin/index.php' target='_blank'>Site Administration → Notifications</a> to install/upgrade the plugin</li>";
echo "<li><strong>Configure AWS credentials:</strong><br>";
echo "Go to <a href='/moodle/admin/settings.php?section=local_texttospeech' target='_blank'>Site Administration → Plugins → Local plugins → Text to Speech</a></li>";
echo "<li><strong>Test on any PDF:</strong><br>";
echo "Upload a PDF to any course and visit the file page - you should see the 'Read PDF Aloud' button</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0;'>";
echo "<h4>📁 Plugin Files Created:</h4>";
echo "<ul>";
echo "<li>✅ version.php - Plugin version information</li>";
echo "<li>✅ settings.php - Admin configuration page</li>";
echo "<li>✅ lib.php - Core integration hooks</li>";
echo "<li>✅ classes/tts_manager.php - Main TTS functionality</li>";
echo "<li>✅ ajax.php - AJAX request handler</li>";
echo "<li>✅ js/tts_integration.js - Frontend JavaScript</li>";
echo "<li>✅ styles.css - Plugin styles</li>";
echo "<li>✅ lang/en/local_texttospeech.php - Language strings</li>";
echo "</ul>";
echo "</div>";
?>
