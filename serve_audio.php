<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$filename = required_param('file', PARAM_FILE);

// Validate filename
if (!preg_match('/^[a-f0-9]{32}\.mp3$/', $filename)) {
    header('HTTP/1.0 400 Bad Request');
    die('Invalid filename format');
}

global $CFG;
$file_path = $CFG->cachedir . '/local_texttospeech/' . $filename;

if (!file_exists($file_path)) {
    header('HTTP/1.0 404 Not Found');
    die('Audio file not found: ' . $filename);
}

// Serve the file
header('Content-Type: audio/mpeg');
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="tts_audio.mp3"');
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');

readfile($file_path);
exit;
