var TTS_ENDPOINT_URL = '';

function init_tts(endpoint_url) {
    TTS_ENDPOINT_URL = endpoint_url;
    
    // Wait for page to load
    document.addEventListener('DOMContentLoaded', function() {
        addTTSToPageElements();
    });
    
    // Also try immediately in case DOM is already ready
    setTimeout(addTTSToPageElements, 1000);
    
    // Monitor for dynamically loaded PDFs
    setTimeout(addTTSToPageElements, 3000);
}

function addTTSToPageElements() {
    // Find PDF elements in various contexts
    var pdfElements = [];
    
    // Look for direct PDF links
    var links = document.querySelectorAll('a[href*=".pdf"]');
    pdfElements = pdfElements.concat(Array.from(links));
    
    // Look for pluginfile.php URLs with PDF
    var pluginfileLinks = document.querySelectorAll('a[href*="pluginfile.php"][href*=".pdf"]');
    pdfElements = pdfElements.concat(Array.from(pluginfileLinks));
    
    // Look for PDF embeds/objects
    var embeds = document.querySelectorAll('embed[src*=".pdf"], object[data*=".pdf"]');
    pdfElements = pdfElements.concat(Array.from(embeds));
    
    // Look for iframe with PDF (including pluginfile.php)
    var iframes = document.querySelectorAll('iframe[src*=".pdf"], iframe[src*="pluginfile.php"]');
    pdfElements = pdfElements.concat(Array.from(iframes));
    
    // Special handling for current page if it's a PDF
    if (window.location.href.includes('.pdf') || window.location.href.includes('pluginfile.php')) {
        // Check if current page is displaying a PDF
        var pdfViewer = document.querySelector('embed[type="application/pdf"], object[type="application/pdf"]');
        if (pdfViewer || document.querySelector('iframe')) {
            // Add TTS to the page directly
            addTTSToCurrentPage();
        }
    }
    
    pdfElements.forEach(function(element) {
        addTTSButton(element);
    });
}

function addTTSToCurrentPage() {
    // Add TTS controls to the current page if it's a PDF
    if (document.querySelector('.tts-controls-page')) {
        return; // Already added
    }
    
    var controlsDiv = document.createElement('div');
    controlsDiv.className = 'tts-controls tts-controls-page';
    controlsDiv.style.position = 'fixed';
    controlsDiv.style.top = '10px';
    controlsDiv.style.right = '10px';
    controlsDiv.style.zIndex = '9999';
    controlsDiv.style.maxWidth = '300px';
    
    var currentUrl = window.location.href;
    
    controlsDiv.innerHTML = `
        <div class="tts-panel">
            <button class="tts-btn tts-play-btn" onclick="generateTTSAudio('${currentUrl}', this)">
                🔊 Read PDF Aloud
            </button>
            <select class="tts-voice-select">
                <option value="Joanna">Joanna (EN-US Female)</option>
                <option value="Matthew">Matthew (EN-US Male)</option>
                <option value="Amy">Amy (EN-UK Female)</option>
                <option value="Brian">Brian (EN-UK Male)</option>
            </select>
            <select class="tts-speed-select">
                <option value="slow">Slow</option>
                <option value="medium" selected>Medium</option>
                <option value="fast">Fast</option>
            </select>
            <div class="tts-status">Ready to read PDF</div>
            <audio class="tts-audio-player" controls style="display:none; width:100%; margin-top:10px;"></audio>
        </div>
    `;
    
    document.body.appendChild(controlsDiv);
}

function addTTSButton(pdfElement) {
    // Don't add multiple buttons
    if (pdfElement.nextElementSibling && pdfElement.nextElementSibling.classList.contains('tts-controls')) {
        return;
    }
    
    var pdfUrl = pdfElement.href || pdfElement.src || pdfElement.data;
    if (!pdfUrl) return;
    
    var controlsDiv = document.createElement('div');
    controlsDiv.className = 'tts-controls';
    controlsDiv.innerHTML = `
        <div class="tts-panel">
            <button class="tts-btn tts-play-btn" onclick="generateTTSAudio('${pdfUrl}', this)">
                🔊 Read PDF Aloud
            </button>
            <select class="tts-voice-select">
                <option value="Joanna">Joanna (EN-US Female)</option>
                <option value="Matthew">Matthew (EN-US Male)</option>
                <option value="Amy">Amy (EN-UK Female)</option>
                <option value="Brian">Brian (EN-UK Male)</option>
            </select>
            <select class="tts-speed-select">
                <option value="slow">Slow</option>
                <option value="medium" selected>Medium</option>
                <option value="fast">Fast</option>
            </select>
            <div class="tts-status"></div>
            <audio class="tts-audio-player" controls style="display:none; width:100%; margin-top:10px;"></audio>
        </div>
    `;
    
    pdfElement.parentNode.insertBefore(controlsDiv, pdfElement.nextSibling);
}

function generateTTSAudio(pdfUrl, button) {
    var controlsDiv = button.closest('.tts-controls');
    var statusDiv = controlsDiv.querySelector('.tts-status');
    var audioPlayer = controlsDiv.querySelector('.tts-audio-player');
    var voiceSelect = controlsDiv.querySelector('.tts-voice-select');
    var speedSelect = controlsDiv.querySelector('.tts-speed-select');
    
    statusDiv.innerHTML = '🔄 Processing PDF and generating audio...';
    button.disabled = true;
    
    var formData = new FormData();
    formData.append('action', 'extract_and_generate');
    formData.append('file_url', pdfUrl);
    formData.append('voice', voiceSelect.value);
    formData.append('speed', speedSelect.value);
    
    fetch(TTS_ENDPOINT_URL, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin' // Important for Moodle session handling
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = `✅ Audio generated! (${data.preview_length} of ${data.text_length} characters)`;
            audioPlayer.src = data.audio_url;
            audioPlayer.style.display = 'block';
            audioPlayer.load();
            
            // Auto-play
            var playPromise = audioPlayer.play();
            if (playPromise !== undefined) {
                playPromise.catch(function(e) {
                    console.log('Auto-play prevented:', e);
                });
            }
        } else {
            statusDiv.innerHTML = '❌ Error: ' + data.error;
        }
    })
    .catch(error => {
        statusDiv.innerHTML = '❌ Network error: ' + error.message;
    })
    .finally(() => {
        button.disabled = false;
    });
}
