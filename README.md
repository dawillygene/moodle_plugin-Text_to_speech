# Moodle Text-to-Speech Plugin

[![Moodle](https://img.shields.io/badge/Moodle-3.5+-blue.svg)](https://moodle.org/)
[![License](https://img.shields.io/badge/License-GPL%20v3-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net/)

A comprehensive Moodle plugin that converts PDF documents to speech using both browser-based Web Speech API and AWS Polly integration. This plugin enhances accessibility for students with visual impairments and provides convenient audio learning options.

## 🎯 Project Overview

This open-source Moodle plugin was developed at **Dodoma University College of Informatics and Virtual Education (CIVE)** to improve accessibility and learning experiences through text-to-speech technology. The plugin supports multiple PDF text extraction methods and provides both client-side and server-side speech synthesis options.

## 👨‍💻 Contributors

**Lead Developer:**
- **ELIA WILLIAM MARIKI** [@dawillygene](https://github.com/dawillygene)
  - *Software Engineer & Project Lead*
  - *Dodoma University College of Informatics and Virtual Education (CIVE)*

**Co-Developers & Collaborators:**
- **HAROON AHMED ADLILLAH** [@haroon-ahmed92](https://github.com/haroon-ahmed92)
  - *Software Engineer - AWS Integration & Backend Development*
  - *Dodoma University College of Informatics and Virtual Education (CIVE)*

- **RAMADHAN ABDALLAH**
  - *IDIT (Instructional Design Information Technology) Specialist*
  - *Testing, Quality Assurance & Educational Integration*
  - *Dodoma University College of Informatics and Virtual Education (CIVE)*

## 🚀 Features

### Core Functionality
- ✅ **PDF Text Extraction** - Multiple methods including `pdftotext` command and PHP parsers
- ✅ **Browser-based TTS** - Web Speech API integration with playback controls
- ✅ **AWS Polly Integration** - Professional neural voice synthesis
- ✅ **Multi-language Support** - Automatic language detection and voice selection
- ✅ **Accessibility Features** - WCAG compliant interface for visually impaired users
- ✅ **Responsive Design** - Works on desktop, tablet, and mobile devices

### Advanced Features
- 🔄 **Real-time Processing** - Live PDF upload and immediate text extraction
- 🎛️ **Playback Controls** - Play, pause, stop, speed adjustment
- 💾 **Caching System** - Efficient storage of processed audio files
- 🌐 **Multiple TTS Engines** - Fallback support for different environments
- 📊 **Usage Analytics** - Track plugin usage and performance
- 🔐 **Security** - Secure file handling and user permission management

## 📋 Requirements

### System Requirements
- **Moodle:** 3.5 or higher
- **PHP:** 7.4 or higher
- **Web Server:** Apache/Nginx with appropriate modules
- **Database:** MySQL 5.7+ / PostgreSQL 9.6+

### PHP Extensions
```bash
# Required PHP extensions
php-json
php-curl
php-mbstring
php-xml
php-zip
```

### Optional Dependencies
```bash
# For enhanced PDF processing
poppler-utils (provides pdftotext command)
imagemagick
ghostscript

# For AWS Polly integration
composer (for AWS SDK)
```

### Browser Requirements
- **Chrome/Chromium:** 33+
- **Firefox:** 49+
- **Safari:** 14.1+
- **Edge:** 14+

## 🛠️ Installation

### Method 1: Manual Installation

1. **Download the plugin:**
```bash
cd /var/www/html/moodle/local/
git clone https://github.com/dawillygene/moodle-texttospeech.git texttospeech
```

2. **Set permissions:**
```bash
chmod -R 755 /var/www/html/moodle/local/texttospeech
chown -R www-data:www-data /var/www/html/moodle/local/texttospeech
```

3. **Install PDF processing tools:**
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install poppler-utils

# CentOS/RHEL
sudo yum install poppler-utils

# macOS
brew install poppler
```

4. **Log in to Moodle as administrator and navigate to:**
   - Site Administration → Notifications
   - Follow the installation prompts

### Method 2: Moodle Plugin Directory
1. Download the plugin ZIP from the Moodle Plugin Directory
2. Go to Site Administration → Plugins → Install plugins
3. Upload the ZIP file and follow installation steps

## ⚙️ Configuration

### Basic Configuration

1. **Navigate to plugin settings:**
   ```
   Site Administration → Plugins → Local plugins → Text to Speech
   ```

2. **Configure basic settings:**
   ```php
   // Enable/disable features
   $CFG->texttospeech_enabled = true;
   $CFG->texttospeech_browser_tts = true;
   $CFG->texttospeech_aws_polly = false;
   
   // PDF processing method
   $CFG->texttospeech_pdf_method = 'pdftotext'; // or 'phpparser'
   
   // File size limits
   $CFG->texttospeech_max_filesize = 52428800; // 50MB
   ```

### AWS Polly Configuration (Optional)

1. **Install AWS SDK via Composer:**
```bash
cd /var/www/html/moodle/local/texttospeech
composer require aws/aws-sdk-php
```

2. **Configure AWS credentials:**
```php
// In config.php or plugin settings
$CFG->aws_polly_key = 'YOUR_AWS_ACCESS_KEY';
$CFG->aws_polly_secret = 'YOUR_AWS_SECRET_KEY';
$CFG->aws_polly_region = 'us-east-1';
```

3. **Set up IAM permissions for Polly service**

### Security Configuration

```php
// Allowed file types
$CFG->texttospeech_allowed_types = array('pdf');

// Maximum processing time
$CFG->texttospeech_max_execution_time = 300; // 5 minutes

// Enable logging
$CFG->texttospeech_logging = true;
```

## 📖 Usage

### For Students

1. **Upload PDF document:**
   - Navigate to the Text-to-Speech tool in your course
   - Click "Upload PDF" and select your file
   - Wait for text extraction to complete

2. **Listen to content:**
   - Click the "Play" button to start audio playback
   - Use speed controls to adjust playback rate
   - Use pause/resume for better control

3. **Accessibility features:**
   - Keyboard navigation support (Space = play/pause, Arrow keys = speed)
   - Screen reader compatible interface
   - High contrast mode support

### For Educators

1. **Add to course:**
   - Go to course editing mode
   - Add "Text-to-Speech" activity
   - Configure allowed file types and settings

2. **Batch processing:**
   - Upload multiple PDFs for course materials
   - Set up automatic processing schedules
   - Monitor usage analytics

### For Administrators

1. **Monitor usage:**
   - View processing statistics
   - Check error logs
   - Manage user permissions

2. **Performance tuning:**
   - Configure caching settings
   - Adjust processing limits
   - Monitor server resources

## 🧪 Testing

The plugin includes comprehensive testing tools:

### PDF Extraction Test
```bash
# Access test interface
https://yourmoodle.com/local/texttospeech/test_pdf.php
```

### Browser TTS Test
```bash
# Test browser compatibility
https://yourmoodle.com/local/texttospeech/test_browser_tts.php
```

### AWS Integration Test
```bash
# Test AWS Polly integration
https://yourmoodle.com/local/texttospeech/test_aws_polly.php
```

## 🏗️ Architecture

### File Structure
```
/var/www/html/moodle/local/texttospeech/
├── README.md                 # This documentation
├── version.php              # Plugin version information
├── lang/                    # Language files
│   ├── en/
│   └── es/
├── classes/                 # Plugin classes
│   ├── pdf_processor.php    # PDF text extraction
│   ├── tts_engine.php      # TTS engine management
│   └── aws_polly.php       # AWS Polly integration
├── lib.php                 # Core plugin functions
├── index.php               # Main plugin interface
├── settings.php            # Plugin settings
├── tests/                  # Testing files
│   ├── test_pdf.php        # PDF extraction test
│   └── test_tts.php        # TTS functionality test
├── js/                     # JavaScript files
│   ├── tts_controls.js     # Playback controls
│   └── pdf_upload.js       # File upload handling
├── css/                    # Stylesheet files
│   └── styles.css          # Plugin styles
└── vendor/                 # Third-party libraries
    └── composer packages
```

### Technology Stack

**Backend:**
- PHP 7.4+ with extensions
- Moodle API integration
- AWS SDK for PHP
- PDF processing libraries

**Frontend:**
- HTML5 with semantic markup
- CSS3 with responsive design
- JavaScript (ES6+)
- Web Speech API
- AJAX for async operations

**External Services:**
- AWS Polly (optional)
- pdftotext utility
- Composer package management

## 🔧 Development

### Setting Up Development Environment

1. **Clone the repository:**
```bash
git clone https://github.com/dawillygene/moodle-texttospeech.git
cd moodle-texttospeech
```

2. **Install dependencies:**
```bash
composer install
npm install  # if using Node.js tools
```

3. **Set up testing environment:**
```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE moodle_test;"

# Configure test settings
cp config-dist.php config.php
# Edit config.php with test database settings
```

### Running Tests

```bash
# PHP unit tests
./vendor/bin/phpunit tests/

# JavaScript tests
npm test

# Integration tests
php tests/test_integration.php
```

### Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

1. **Fork the repository**
2. **Create a feature branch:** `git checkout -b feature/amazing-feature`
3. **Commit changes:** `git commit -m 'Add amazing feature'`
4. **Push to branch:** `git push origin feature/amazing-feature`
5. **Open a Pull Request**

### Code Standards

- Follow Moodle coding standards
- Use PHPDoc for all functions
- Write comprehensive tests
- Ensure accessibility compliance

## 🚨 Troubleshooting

### Common Issues

**PDF text extraction fails:**
```bash
# Check if pdftotext is installed
which pdftotext

# Install if missing
sudo apt-get install poppler-utils
```

**Browser TTS not working:**
- Ensure HTTPS is enabled
- Check browser compatibility
- Verify user interaction requirements

**AWS Polly errors:**
- Verify AWS credentials
- Check IAM permissions
- Monitor usage limits

**Performance issues:**
- Increase PHP memory limit
- Enable caching
- Monitor server resources

### Debug Mode

Enable debug mode for detailed error information:
```php
// In config.php
$CFG->debug = DEBUG_ALL;
$CFG->debugdisplay = 1;
$CFG->texttospeech_debug = true;
```

### Log Files

Check log files for errors:
```bash
# Moodle logs
tail -f /var/www/html/moodle/admin/cli/logs/

# Plugin-specific logs
tail -f /var/www/html/moodle/local/texttospeech/logs/debug.log
```

## 📊 Performance Optimization

### Caching Configuration
```php
// Enable aggressive caching
$CFG->texttospeech_cache_lifetime = 86400; // 24 hours
$CFG->texttospeech_cache_method = 'file'; // or 'redis', 'memcache'
```

### Resource Management
```php
// Limit concurrent processing
$CFG->texttospeech_max_concurrent = 5;

// Process queue management
$CFG->texttospeech_queue_enabled = true;
$CFG->texttospeech_queue_max_size = 100;
```

## 🔒 Security Considerations

- **File Validation:** All uploaded files are validated for type and content
- **User Permissions:** Proper capability checks for all operations
- **Input Sanitization:** All user inputs are sanitized and validated
- **Rate Limiting:** Prevents abuse through request limiting
- **Secure Storage:** Temporary files are stored securely and cleaned up

## 📄 License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Dodoma University College of Informatics and Virtual Education (CIVE)** for supporting this project
- **IDIT Department** for educational design guidance and testing
- **Moodle Community** for the excellent platform and documentation
- **AWS** for providing robust TTS services
- **Open Source Community** for various libraries and tools used

## 📞 Support

- **Documentation:** [Wiki Pages](https://github.com/dawillygene/moodle-texttospeech/wiki)
- **Issues:** [GitHub Issues](https://github.com/dawillygene/moodle-texttospeech/issues)
- **Discussions:** [GitHub Discussions](https://github.com/dawillygene/moodle-texttospeech/discussions)
- **Email:** elia.mariki@cive.ac.tz

## 🗺️ Roadmap

### Version 2.0 (Planned)
- [ ] Multi-language UI support
- [ ] Advanced voice customization
- [ ] Batch processing improvements
- [ ] Mobile app integration

### Version 2.1 (Future)
- [ ] AI-powered text preprocessing
- [ ] Advanced analytics dashboard
- [ ] Plugin marketplace integration
- [ ] Enterprise features

---

**Made with ❤️ at Dodoma University College of Informatics and Virtual Education (CIVE)**

*Enhancing accessibility and learning through technology*
