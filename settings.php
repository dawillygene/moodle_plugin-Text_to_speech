<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_texttospeech', get_string('pluginname', 'local_texttospeech'));

    $settings->add(new admin_setting_configcheckbox(
        'local_texttospeech/enable_tts',
        get_string('enable_tts', 'local_texttospeech'),
        get_string('enable_tts_desc', 'local_texttospeech'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_texttospeech/aws_access_key',
        get_string('aws_access_key', 'local_texttospeech'),
        'AWS Access Key for Polly service',
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_texttospeech/aws_secret_key',
        get_string('aws_secret_key', 'local_texttospeech'),
        'AWS Secret Key for Polly service',
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'local_texttospeech/aws_region',
        get_string('aws_region', 'local_texttospeech'),
        'AWS Region for Polly service',
        'us-east-1',
        array(
            'us-east-1' => 'US East (N. Virginia)',
            'us-west-2' => 'US West (Oregon)',
            'eu-west-1' => 'Europe (Ireland)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)'
        )
    ));

    $ADMIN->add('localplugins', $settings);
}
