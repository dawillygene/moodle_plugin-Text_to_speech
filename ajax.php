<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/tts_manager.php');

require_login();
    
$action = required_param('action', PARAM_TEXT);

header('Content-Type: application/json');

try {
    $tts_manager = new local_texttospeech_manager();
    
    switch ($action) {
        case 'generate_from_text':
            $text = required_param('text', PARAM_RAW);
            $voice = optional_param('voice', 'Joanna', PARAM_TEXT);
            $speed = optional_param('speed', 'medium', PARAM_TEXT);
            
            if (strlen($text) < 10) {
                echo json_encode(['success' => false, 'error' => 'Text is too short']);
                break;
            }
            
            // Process the entire text (remove the 1000 character limit)
            $result = $tts_manager->text_to_speech($text, $voice, $speed);
            
            if (isset($result['success']) && $result['success']) {
                $audio_url = new moodle_url('/local/texttospeech/serve_audio.php', 
                    array('file' => basename($result['file'])));
                
                echo json_encode([
                    'success' => true,
                    'audio_url' => $audio_url->out(false),
                    'text_length' => strlen($text),
                    'audio_duration' => $result['duration'] ?? 'unknown',
                    'chunks' => $result['chunks'] ?? 1
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Unknown error']);
            }
            break;
            
        case 'get_voices':
            echo json_encode([
                'success' => true,
                'voices' => $tts_manager->get_available_voices()
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
