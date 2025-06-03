# Moodle Plugin: Text to Speech

**Author:** dawillygene  
**Repository:** [@dawillygene/moodle_plugin-Text_to_speech](https://github.com/dawillygene/moodle_plugin-Text_to_speech)

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [File Structure](#file-structure)
- [Dependencies](#dependencies)
- [Development Notes](#development-notes)
- [Version](#version)
- [License](#license)

---

## Overview

This Moodle local plugin adds Text-to-Speech (TTS) functionality to your Moodle site. It enables users to listen to PDF content and other resources using synthesized voices via AWS Polly.

## Features

- Adds TTS controls for PDFs and course resources/pages.
- Allows selection of different voices and speech speeds.
- Integrates with AWS Polly for high-quality speech synthesis.
- Provides settings for AWS credentials and region.
- Customizable UI for TTS controls.

## Installation

1. Clone or download the repository into your Moodle `local/` directory as `texttospeech`.
2. Run the Moodle upgrade process.
3. Configure plugin settings via Site Administration.

## Configuration

The plugin settings page lets you configure:
- Enable/disable TTS.
- AWS Access Key and Secret Key for Polly.
- AWS Region (e.g., US East, US West, EU, Asia Pacific).

These can be set in `settings.php`.

## Usage

Once enabled:
- TTS controls appear on PDFs and supported pages.
- Users can select their preferred voice and speed.
- Clicking "Read PDF Aloud" extracts text and plays audio using AWS Polly.

## File Structure

- `lib.php`: Main plugin hooks, adds CSS/JS and TTS controls to Moodle pages.
- `ajax.php`: Handles AJAX requests for TTS generation and voice listing.
- `settings.php`: Admin settings page for AWS credentials and options.
- `version.php`: Plugin version and Moodle compatibility.
- `composer.json`: PHP dependencies (see below).
- `README.md`: Placeholder for basic info.
- Additional folders (`classes/`, `js/`, `lang/`) contain code, scripts, and language strings (see repo).

## Dependencies

Defined in `composer.json`:
- `aws/aws-sdk-php` (for AWS Polly)
- `smalot/pdfparser` (for PDF text extraction)
- `landrok/language-detector` (for language detection)

## Development Notes

### Key Functions

- `local_texttospeech_before_http_headers()`  
  Inserts CSS for TTS controls if TTS is enabled and the context matches PDFs/resources.

- `local_texttospeech_before_footer()`  
  Adds TTS UI and JavaScript for extracting and reading PDFs aloud.

- `local_texttospeech_extend_navigation()`  
  (Stub) For extending Moodle navigation if needed.

### AJAX API (`ajax.php`)

- `generate_from_text`: Accepts text, voice, and speed, returns a generated audio URL.
- `get_voices`: Returns a list of available voices.

### Example Snippet

```php
// Add TTS controls to PDF links
$PAGE->requires->css('/local/texttospeech/styles.css');
...
```

## Version

- **Current Version:** v1.1
- **Moodle Required:** 2020110900 or higher

## License

Include your project's license here.

---

*Documentation generated automatically. For the most up-to-date and complete information, visit the [project repository](https://github.com/dawillygene/moodle_plugin-Text_to_speech).*
