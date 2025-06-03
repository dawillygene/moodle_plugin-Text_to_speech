<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../vendor/autoload.php');

use Aws\Polly\PollyClient;
use Smalot\PdfParser\Parser;

class local_texttospeech_manager {
    private $polly;
    private $cacheDir;
    private $speed;
    private $voiceId;

    public function __construct() {
        // Load AWS credentials from Moodle config first
        $awsKey = get_config('local_texttospeech', 'aws_access_key');
        $awsSecret = get_config('local_texttospeech', 'aws_secret_key');
        $region = get_config('local_texttospeech', 'aws_region') ?: 'us-east-1';
        
        // Fallback to .env file
        if (empty($awsKey) || empty($awsSecret)) {
            $envFile = __DIR__ . '/../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, 'AWS_ACCESS_KEY_ID=') === 0) {
                        $awsKey = trim(str_replace(['AWS_ACCESS_KEY_ID=', '"', "'"], '', $line));
                    }
                    if (strpos($line, 'AWS_SECRET_ACCESS_KEY=') === 0) {
                        $awsSecret = trim(str_replace(['AWS_SECRET_ACCESS_KEY=', '"', "'"], '', $line));
                    }
                }
            }
        }
        
        // Fallback to local config file
        if (empty($awsKey) || empty($awsSecret)) {
            $configFile = __DIR__ . '/../aws_config.local.php';
            if (file_exists($configFile)) {
                $config = include $configFile;
                $awsKey = $config['aws_access_key_id'] ?? '';
                $awsSecret = $config['aws_secret_access_key'] ?? '';
            }
        }
        
        if (empty($awsKey) || empty($awsSecret)) {
            throw new Exception("AWS credentials not configured");
        }
        
        $this->polly = new PollyClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ],
        ]);

        global $CFG;
        $this->cacheDir = $CFG->cachedir . '/local_texttospeech';
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $this->speed = 'medium';
        $this->voiceId = 'Joanna';
    }

    public function extract_text_from_pdf($file_path) {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($file_path);
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

    // Download file from Moodle's pluginfile.php with proper session handling
    public function download_moodle_file($file_url) {
        global $CFG, $USER;
        
        $temp_file = tempnam(sys_get_temp_dir(), 'moodle_pdf_');
        
        // Use cURL to download with session cookies
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $file_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle TTS Plugin');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Include all cookies from current session
        $cookie_header = '';
        foreach ($_COOKIE as $name => $value) {
            $cookie_header .= $name . '=' . $value . '; ';
        }
        if ($cookie_header) {
            curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookie_header, '; '));
        }
        
        $file_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("TTS cURL: HTTP Code: $http_code, Error: $error");
        
        if ($http_code === 200 && $file_content !== false && strlen($file_content) > 0) {
            file_put_contents($temp_file, $file_content);
            return $temp_file;
        }
        
        error_log("TTS Download failed: HTTP $http_code, Content length: " . strlen($file_content));
        return false;
    }

    public function text_to_speech($text, $voice = 'Joanna', $speed = 'medium') {
        $this->voiceId = $voice;
        $this->speed = $speed;
        
        $cleanText = trim(str_replace(["\r", "\n"], ' ', $text));
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
        
        if (!$cleanText || strlen($cleanText) < 5) {
            return ['error' => 'Text too short or empty'];
        }

        $cacheKey = md5($cleanText . $this->speed . $this->voiceId);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.mp3';
        
        // Check if cache directory exists and is writable
        if (!file_exists($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                return ['error' => 'Cannot create cache directory'];
            }
        }
        
        if (!is_writable($this->cacheDir)) {
            return ['error' => 'Cache directory not writable: ' . $this->cacheDir];
        }
        
        if (file_exists($cacheFile)) {
            return ['success' => true, 'file' => $cacheFile, 'cached' => true];
        }

        $chunks = $this->split_text($cleanText);
        $combined = '';
        $errors = [];

        foreach ($chunks as $index => $chunk) {
            try {
                $result = $this->polly->synthesizeSpeech([
                    'Text' => $this->generate_ssml($chunk),
                    'OutputFormat' => 'mp3',
                    'VoiceId' => $this->voiceId,
                    'Engine' => 'neural',
                    'TextType' => 'ssml'
                ]);
                
                $chunkData = $result['AudioStream']->getContents();
                if ($chunkData) {
                    $combined .= $chunkData;
                }
                
            } catch (Exception $e) {
                $errors[] = "Chunk " . ($index + 1) . ": " . $e->getMessage();
                continue;
            }
        }

        if ($combined && strlen($combined) > 1000) {
            $bytesWritten = file_put_contents($cacheFile, $combined);
            if ($bytesWritten === false) {
                return ['error' => 'Failed to write audio file to cache'];
            }
            
            // Verify file was created
            if (!file_exists($cacheFile)) {
                return ['error' => 'Audio file was not created successfully'];
            }
            
            // Estimate duration (roughly 150 words per minute, 5 chars per word)
            $estimated_duration = round((strlen($cleanText) / 5) / 150, 1);
            
            return [
                'success' => true, 
                'file' => $cacheFile, 
                'size' => $bytesWritten, 
                'chunks' => count($chunks),
                'duration' => $estimated_duration
            ];
        }

        return ['error' => 'Failed to generate audio. Errors: ' . implode('; ', $errors)];
    }

    private function split_text($text, $maxLength = 2500) {
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

    private function generate_ssml($text) {
        $cleanText = htmlspecialchars($text, ENT_XML1, 'UTF-8');
        return "<speak><prosody rate='{$this->speed}'>{$cleanText}</prosody></speak>";
    }

    public function get_available_voices() {
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

    public function serve_audio_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: inline; filename="tts_audio.mp3"');
        readfile($file_path);
        return true;
    }
}
