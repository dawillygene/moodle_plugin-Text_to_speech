<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/tts_manager.php');

/**
 * Hook to add TTS functionality - called before head is rendered
 */
function local_texttospeech_before_http_headers() {
    global $PAGE, $CFG;
    
    if (!get_config('local_texttospeech', 'enable_tts')) {
        return;
    }

    // Check various conditions where PDFs might be displayed
    $include_tts = false;
    
    // Check URL patterns
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, 'pluginfile.php') !== false && strpos($request_uri, '.pdf') !== false) {
        $include_tts = true;
    }
    
    // Check page types
    $page_contexts = ['mod-resource-view', 'mod-folder-view', 'pluginfile'];
    foreach ($page_contexts as $context) {
        if (strpos($PAGE->pagetype, $context) !== false) {
            $include_tts = true;
            break;
        }
    }
    
    // Always include on course pages and resource pages
    if (strpos($PAGE->pagetype, 'course-view') !== false || 
        strpos($PAGE->pagetype, 'mod-') !== false) {
        $include_tts = true;
    }
    
    if ($include_tts) {
        // Add CSS in head
        $PAGE->requires->css('/local/texttospeech/styles.css');
    }
}

/**
 * Hook to add JavaScript after page content
 */
function local_texttospeech_before_footer() {
    global $PAGE, $CFG;
    
    if (!get_config('local_texttospeech', 'enable_tts')) {
        return;
    }

    // Check if we should include TTS (same logic as before)
    $include_tts = false;
    
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, 'pluginfile.php') !== false && strpos($request_uri, '.pdf') !== false) {
        $include_tts = true;
    }
    
    $page_contexts = ['mod-resource-view', 'mod-folder-view', 'pluginfile'];
    foreach ($page_contexts as $context) {
        if (strpos($PAGE->pagetype, $context) !== false) {
            $include_tts = true;
            break;
        }
    }
    
    if (strpos($PAGE->pagetype, 'course-view') !== false || 
        strpos($PAGE->pagetype, 'mod-') !== false) {
        $include_tts = true;
    }
    
    if ($include_tts) {
        // Add inline styles and JavaScript
        echo '<style>
        .tts-controls {
            margin: 10px 0;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .tts-controls-page {
            position: fixed !important;
            top: 10px !important;
            right: 10px !important;
            z-index: 9999 !important;
            max-width: 300px !important;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important;
        }
        .tts-panel {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .tts-btn {
            background: #007cba;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .tts-btn:hover { background: #005a8b; }
        .tts-btn:disabled { background: #6c757d; cursor: not-allowed; }
        .tts-voice-select, .tts-speed-select {
            padding: 6px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: white;
        }
        .tts-status {
            flex: 1;
            min-width: 200px;
            padding: 8px;
            font-size: 14px;
        }
        .tts-audio-player { width: 100%; margin-top: 10px; }
        @media (max-width: 768px) {
            .tts-panel { flex-direction: column; align-items: stretch; }
            .tts-voice-select, .tts-speed-select { width: 100%; }
            .tts-controls-page { 
                position: fixed !important;
                top: 5px !important;
                left: 5px !important;
                right: 5px !important;
                max-width: none !important;
            }
        }
        </style>';
        
        // Add TTS endpoint URL and JavaScript
        $tts_url = new moodle_url('/local/texttospeech/ajax.php');
        
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>';
        echo '<script>
        var TTS_ENDPOINT_URL = "' . $tts_url->out() . '";
        var ttsInitialized = false;

        // Configure PDF.js
        if (typeof pdfjsLib !== "undefined") {
            pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
        }

        function initTTS() {
            if (ttsInitialized) return;
            ttsInitialized = true;
            
            setTimeout(addTTSToPageElements, 500);
            setTimeout(addTTSToPageElements, 2000);
        }

        function addTTSToPageElements() {
            var pdfElements = [];
            
            // Look for PDF links
            var links = document.querySelectorAll("a[href*=\'.pdf\']");
            pdfElements = pdfElements.concat(Array.from(links));
            
            // Look for pluginfile.php URLs with PDF
            var pluginfileLinks = document.querySelectorAll("a[href*=\'pluginfile.php\'][href*=\'.pdf\']");
            pdfElements = pdfElements.concat(Array.from(pluginfileLinks));
            
            // Check if current page is a PDF
            if (window.location.href.includes(".pdf") || window.location.href.includes("pluginfile.php")) {
                addTTSToCurrentPage();
            }
            
            pdfElements.forEach(function(element) {
                addTTSButton(element);
            });
        }

        function addTTSToCurrentPage() {
            if (document.querySelector(".tts-controls-page")) return;
            
            var controlsDiv = document.createElement("div");
            controlsDiv.className = "tts-controls tts-controls-page";
            
            var currentUrl = window.location.href;
            
            var panelHTML = \'<div class="tts-panel">\' +
                \'<button class="tts-btn tts-play-btn" onclick="extractAndReadPDF(\\\'\' + currentUrl + \'\\\', this)">\' +
                \'🔊 Read PDF Aloud</button>\' +
                \'<select class="tts-voice-select">\' +
                \'<option value="Joanna">Joanna (EN-US Female)</option>\' +
                \'<option value="Matthew">Matthew (EN-US Male)</option>\' +
                \'<option value="Amy">Amy (EN-UK Female)</option>\' +
                \'<option value="Brian">Brian (EN-UK Male)</option>\' +
                \'</select>\' +
                \'<select class="tts-speed-select">\' +
                \'<option value="slow">Slow</option>\' +
                \'<option value="medium" selected>Medium</option>\' +
                \'<option value="fast">Fast</option>\' +
                \'</select>\' +
                \'<div class="tts-status">Ready to read PDF</div>\' +
                \'<audio class="tts-audio-player" controls style="display:none; width:100%; margin-top:10px;"></audio>\' +
                \'</div>\';
            
            controlsDiv.innerHTML = panelHTML;
            document.body.appendChild(controlsDiv);
        }

        function addTTSButton(pdfElement) {
            if (pdfElement.nextElementSibling && pdfElement.nextElementSibling.classList.contains("tts-controls")) {
                return;
            }
            
            var pdfUrl = pdfElement.href || pdfElement.src || pdfElement.data;
            if (!pdfUrl) return;
            
            var controlsDiv = document.createElement("div");
            controlsDiv.className = "tts-controls";
            
            var panelHTML = \'<div class="tts-panel">\' +
                \'<button class="tts-btn tts-play-btn" onclick="extractAndReadPDF(\\\'\' + pdfUrl + \'\\\', this)">\' +
                \'🔊 Read PDF Aloud</button>\' +
                \'<select class="tts-voice-select">\' +
                \'<option value="Joanna">Joanna (EN-US Female)</option>\' +
                \'<option value="Matthew">Matthew (EN-US Male)</option>\' +
                \'<option value="Amy">Amy (EN-UK Female)</option>\' +
                \'<option value="Brian">Brian (EN-UK Male)</option>\' +
                \'</select>\' +
                \'<select class="tts-speed-select">\' +
                \'<option value="slow">Slow</option>\' +
                \'<option value="medium" selected>Medium</option>\' +
                \'<option value="fast">Fast</option>\' +
                \'</select>\' +
                \'<div class="tts-status"></div>\' +
                \'<audio class="tts-audio-player" controls style="display:none; width:100%; margin-top:10px;"></audio>\' +
                \'</div>\';
            
            controlsDiv.innerHTML = panelHTML;
            pdfElement.parentNode.insertBefore(controlsDiv, pdfElement.nextSibling);
        }

        function extractAndReadPDF(pdfUrl, button) {
            var controlsDiv = button.closest(".tts-controls");
            var statusDiv = controlsDiv.querySelector(".tts-status");
            var audioPlayer = controlsDiv.querySelector(".tts-audio-player");
            var voiceSelect = controlsDiv.querySelector(".tts-voice-select");
            var speedSelect = controlsDiv.querySelector(".tts-speed-select");
            
            statusDiv.innerHTML = "📄 Extracting text from PDF...";
            button.disabled = true;
            
            if (typeof pdfjsLib === "undefined") {
                statusDiv.innerHTML = "❌ PDF.js not loaded. Please refresh the page.";
                button.disabled = false;
                return;
            }
            
            // Extract text using PDF.js
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                statusDiv.innerHTML = "📖 Reading PDF pages... (1/" + pdf.numPages + ")";
                
                var extractTextPromises = [];
                // Process all pages (remove the 5-page limit)
                
                for (var i = 1; i <= pdf.numPages; i++) {
                    extractTextPromises.push(
                        pdf.getPage(i).then(function(page) {
                            return page.getTextContent().then(function(textContent) {
                                return textContent.items.map(function(item) {
                                    return item.str;
                                }).join(" ");
                            });
                        })
                    );
                }
                
                Promise.all(extractTextPromises).then(function(pagesText) {
                    var fullText = pagesText.join("\\n\\n");
                    
                    if (fullText.trim().length < 10) {
                        statusDiv.innerHTML = "❌ No readable text found in PDF";
                        button.disabled = false;
                        return;
                    }
                    
                    statusDiv.innerHTML = "🎤 Generating speech for " + fullText.length + " characters...";
                    generateTTSFromText(fullText, voiceSelect.value, speedSelect.value, statusDiv, audioPlayer);
                    button.disabled = false;
                });
                
            }).catch(function(error) {
                console.error("PDF extraction error:", error);
                statusDiv.innerHTML = "❌ Could not read PDF: " + error.message;
                button.disabled = false;
            });
        }

        function generateTTSFromText(text, voice, speed, statusDiv, audioPlayer) {
            var formData = new FormData();
            formData.append("action", "generate_from_text");
            formData.append("text", text);
            formData.append("voice", voice);
            formData.append("speed", speed);
            
            // Show progress for longer texts
            if (text.length > 2000) {
                statusDiv.innerHTML = "🎤 Processing " + text.length + " characters (this may take a moment)...";
            }
            
            fetch(TTS_ENDPOINT_URL, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error("HTTP " + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    var duration = data.audio_duration ? " (~" + data.audio_duration + " min)" : "";
                    var chunks = data.chunks > 1 ? " [" + data.chunks + " chunks]" : "";
                    statusDiv.innerHTML = "✅ Full audio ready! (" + data.text_length + " characters)" + duration + chunks;
                    
                    var audioUrl = data.audio_url;
                    if (audioUrl.indexOf("?") === -1) {
                        audioUrl += "?t=" + Date.now();
                    } else {
                        audioUrl += "&t=" + Date.now();
                    }
                    
                    audioPlayer.src = audioUrl;
                    audioPlayer.style.display = "block";
                    audioPlayer.load();
                    
                    audioPlayer.play().then(function() {
                        statusDiv.innerHTML += " 🎵 Playing full audio...";
                    }).catch(function(e) {
                        console.log("Playback error:", e);
                        statusDiv.innerHTML += " (Click play button)";
                    });
                } else {
                    statusDiv.innerHTML = "❌ Error: " + data.error;
                }
            })
            .catch(function(error) {
                console.error("TTS Error:", error);
                statusDiv.innerHTML = "❌ Error: " + error.message;
            });
        }

        // Initialize when DOM is ready
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initTTS);
        } else {
            initTTS();
        }
        </script>';
    }
}

/**
 * Extend navigation
 */
function local_texttospeech_extend_navigation(global_navigation $navigation) {
    // Add any navigation items if needed
}
