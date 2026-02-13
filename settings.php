<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings for mod_webexmeetings
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Heading: Webex API Settings
    $settings->add(new admin_setting_heading(
        'mod_webexmeetings/apiheading',
        get_string('apisettings', 'webexmeetings'),
        get_string('apisettings_desc', 'webexmeetings')
    ));
    
    // Authentication method
    $authoptions = array(
        'bot' => get_string('auth_bot', 'webexmeetings'),
        'oauth' => get_string('auth_oauth', 'webexmeetings'),
    );
    $settings->add(new admin_setting_configselect(
        'mod_webexmeetings/auth_method',
        get_string('auth_method', 'webexmeetings'),
        get_string('auth_method_desc', 'webexmeetings'),
        'bot',
        $authoptions
    ));
    
    // Bot token
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_webexmeetings/bot_token',
        get_string('bottoken', 'webexmeetings'),
        get_string('bottoken_desc', 'webexmeetings'),
        ''
    ));
    
    // OAuth2 Client ID
    $settings->add(new admin_setting_configtext(
        'mod_webexmeetings/client_id',
        get_string('clientid', 'webexmeetings'),
        get_string('clientid_desc', 'webexmeetings'),
        ''
    ));
    
    // OAuth2 Client Secret
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_webexmeetings/client_secret',
        get_string('clientsecret', 'webexmeetings'),
        get_string('clientsecret_desc', 'webexmeetings'),
        ''
    ));
    
    // Refresh Token
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_webexmeetings/refresh_token',
        get_string('refreshtoken', 'webexmeetings'),
        get_string('refreshtoken_desc', 'webexmeetings'),
        ''
    ));
    
    // Site URL
    $settings->add(new admin_setting_configtext(
        'mod_webexmeetings/site_url',
        get_string('siteurl', 'webexmeetings'),
        get_string('siteurl_desc', 'webexmeetings'),
        '',
        PARAM_TEXT
    ));
    
    // Test connection button
    $testconnectionurl = new moodle_url('/mod/webexmeetings/test_connection.php');
    $testconnectionbutton = '<a href="' . $testconnectionurl->out() . '" class="btn btn-secondary">' . 
        get_string('testconnection', 'webexmeetings') . '</a>';
    $settings->add(new admin_setting_heading(
        'mod_webexmeetings/testconnection',
        '',
        $testconnectionbutton
    ));
    
    // Heading: Default meeting settings
    $settings->add(new admin_setting_heading(
        'mod_webexmeetings/defaultsheading',
        get_string('defaultsettings', 'webexmeetings'),
        get_string('defaultsettings_desc', 'webexmeetings')
    ));
    
    // Default track attendance
    $settings->add(new admin_setting_configcheckbox(
        'mod_webexmeetings/default_track_attendance',
        get_string('defaulttrackattendance', 'webexmeetings'),
        get_string('defaulttrackattendance_desc', 'webexmeetings'),
        1
    ));
    
    // Default minimum duration
    $duroptions = array(
        0 => get_string('nominimum', 'webexmeetings'),
        5 => '5 ' . get_string('minutes'),
        10 => '10 ' . get_string('minutes'),
        15 => '15 ' . get_string('minutes'),
        30 => '30 ' . get_string('minutes'),
        60 => '60 ' . get_string('minutes'),
    );
    $settings->add(new admin_setting_configselect(
        'mod_webexmeetings/default_min_duration',
        get_string('defaultminduration', 'webexmeetings'),
        get_string('defaultminduration_desc', 'webexmeetings'),
        10,
        $duroptions
    ));
    
    // Heading: Sync settings
    $settings->add(new admin_setting_heading(
        'mod_webexmeetings/syncheading',
        get_string('syncsettings', 'webexmeetings'),
        get_string('syncsettings_desc', 'webexmeetings')
    ));
    
    // Sync lookback days
    $daysoptions = array();
    for ($i = 1; $i <= 30; $i++) {
        $daysoptions[$i] = $i . ' ' . get_string('days');
    }
    $settings->add(new admin_setting_configselect(
        'mod_webexmeetings/sync_lookback_days',
        get_string('synclookbackdays', 'webexmeetings'),
        get_string('synclookbackdays_desc', 'webexmeetings'),
        7,
        $daysoptions
    ));
    
    // Debug mode
    $settings->add(new admin_setting_configcheckbox(
        'mod_webexmeetings/debug_mode',
        get_string('debugmode', 'webexmeetings'),
        get_string('debugmode_desc', 'webexmeetings'),
        0
    ));
}
