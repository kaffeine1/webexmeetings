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
 * English language strings for mod_webexmeetings
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General
$string['modulename'] = 'Webex Meeting';
$string['modulenameplural'] = 'Webex Meetings';
$string['pluginname'] = 'Webex Meetings';
$string['pluginadministration'] = 'Webex Meetings Administration';
$string['modulename_help'] = 'The Webex Meetings module allows you to create and manage Cisco Webex meetings directly from Moodle. Attendance is tracked automatically.';
$string['modulename_link'] = 'mod/webexmeetings/view';
$string['webexmeetings:addinstance'] = 'Add a new Webex Meeting';
$string['webexmeetings:view'] = 'View Webex Meeting';
$string['webexmeetings:viewattendance'] = 'View attendance report';
$string['webexmeetings:exportattendance'] = 'Export attendance data';
$string['webexmeetings:managemeeting'] = 'Manage meeting settings';
$string['webexmeetings:syncattendance'] = 'Sync attendance from Webex';
$string['webexmeetings:deleteattendance'] = 'Delete attendance records';

// Form
$string['meetingname'] = 'Meeting name';
$string['scheduling'] = 'Schedule';
$string['starttime'] = 'Start time';
$string['endtime'] = 'End time';
$string['meetingpassword'] = 'Meeting password';
$string['meetingpassword_help'] = 'Optional password for the Webex meeting. If left empty, Webex may generate one automatically.';
$string['recurringmeeting'] = 'Recurring meeting';
$string['attendancesection'] = 'Attendance tracking';
$string['trackattendance'] = 'Track attendance';
$string['trackattendance_help'] = 'If enabled, participant attendance will be automatically synced from Webex.';
$string['minduration'] = 'Minimum duration for attendance';
$string['minduration_help'] = 'Minimum time a participant must be present to count as attended.';
$string['nominimum'] = 'No minimum';
$string['endtimebeforestart'] = 'End time must be after start time.';

// View
$string['meetingdetails'] = 'Meeting details';
$string['joinmeeting'] = 'Join Webex Meeting';
$string['nojoinurl'] = 'Join URL is not available. The meeting may not have been created correctly on Webex.';
$string['meetingnotstarted'] = 'Not started';
$string['meetinginprogress'] = 'In progress';
$string['meetingended'] = 'Ended';
$string['attendancesummary'] = 'Attendance summary';
$string['noattendancedata'] = 'No attendance data available. Data may not be available until approximately 24 hours after the meeting ends.';
$string['viewfullreport'] = 'View full report';
$string['syncnow'] = 'Sync now';
$string['unmatchedusers'] = 'Unmatched users ({$a})';

// Attendance
$string['attendancereport'] = 'Attendance report';
$string['jointime'] = 'Join time';
$string['leavetime'] = 'Leave time';
$string['duration'] = 'Duration';
$string['sessions'] = 'Sessions';
$string['viewsessions'] = 'View sessions';
$string['present'] = 'Present';
$string['absent'] = 'Absent';
$string['enrolled'] = 'Enrolled';
$string['attendancerate'] = 'Attendance rate';
$string['attendancerecords'] = 'Attendance records';
$string['exportcsv'] = 'Export CSV';

// Sync
$string['syncattendance'] = 'Sync attendance';
$string['syncattendancetask'] = 'Sync Webex attendance data';
$string['syncsuccess'] = 'Successfully synced attendance for {$a} participants.';
$string['syncerror'] = 'Error syncing attendance: {$a}';
$string['nomeetingid'] = 'This meeting does not have a Webex meeting ID. It may not have been created correctly.';
$string['lastsync'] = 'Last sync';

// Unmatched users
$string['unmatcheduserspage'] = 'Unmatched Webex users';
$string['unmatchedusersdesc'] = 'These Webex participants could not be automatically matched to Moodle users. You can manually map them below.';
$string['nounmatchedusers'] = 'No unmatched users found.';
$string['maptouser'] = 'Map to Moodle user';
$string['selectuser'] = 'Select a user...';
$string['map'] = 'Map';
$string['usermapped'] = 'User successfully mapped.';

// Settings
$string['apisettings'] = 'Webex API settings';
$string['apisettings_desc'] = 'Configure the credentials for connecting to the Cisco Webex API. You can use either a Bot token or OAuth2 credentials.';
$string['auth_method'] = 'Authentication method';
$string['auth_method_desc'] = 'Choose how to authenticate with the Webex API.';
$string['auth_bot'] = 'Bot token';
$string['auth_oauth'] = 'OAuth2 (Client credentials + Refresh token)';
$string['bottoken'] = 'Bot token';
$string['bottoken_desc'] = 'Enter the Bot access token from the Webex Developer Portal.';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'OAuth2 Client ID from the Webex integration.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'OAuth2 Client Secret from the Webex integration.';
$string['refreshtoken'] = 'Refresh token';
$string['refreshtoken_desc'] = 'OAuth2 Refresh token. This is used to automatically obtain new access tokens.';
$string['siteurl'] = 'Webex site URL';
$string['siteurl_desc'] = 'Your Webex site URL (e.g., sitename.webex.com). Leave empty to use the default.';
$string['testconnection'] = 'Test connection';
$string['connectionsuccessful'] = 'Connection to Webex API successful!';
$string['connectionfailed'] = 'Connection to Webex API failed: {$a}';
$string['defaultsettings'] = 'Default meeting settings';
$string['defaultsettings_desc'] = 'Default values for new Webex meeting activities.';
$string['defaulttrackattendance'] = 'Track attendance by default';
$string['defaulttrackattendance_desc'] = 'If enabled, attendance tracking will be enabled by default for new meetings.';
$string['defaultminduration'] = 'Default minimum duration';
$string['defaultminduration_desc'] = 'Default minimum duration for attendance.';
$string['syncsettings'] = 'Sync settings';
$string['syncsettings_desc'] = 'Settings for the automatic attendance sync task.';
$string['synclookbackdays'] = 'Sync lookback days';
$string['synclookbackdays_desc'] = 'How many days back to look for meetings to sync.';
$string['debugmode'] = 'Debug mode';
$string['debugmode_desc'] = 'Enable extra debug logging for Webex API calls.';

// Errors
$string['missingcredentials'] = 'Webex API credentials are not configured. Please check plugin settings.';
$string['failedtogetaccesstoken'] = 'Failed to obtain access token from Webex.';
$string['meetingcreationerror'] = 'Error creating Webex meeting: {$a}';
$string['meetingupdateerror'] = 'Error updating Webex meeting: {$a}';
$string['meetingdeleteerror'] = 'Error deleting Webex meeting: {$a}';

// Events
$string['eventmeetingcreated'] = 'Webex meeting created';
$string['eventmeetingjoined'] = 'Webex meeting joined';
$string['eventattendanceviewed'] = 'Webex attendance viewed';
$string['eventattendancesynced'] = 'Webex attendance synced';

// Index
$string['nomeetings'] = 'There are no Webex meetings in this course.';

// Privacy
$string['privacy:metadata:webexmeetings_attendance'] = 'Attendance summary data for Webex meetings.';
$string['privacy:metadata:webexmeetings_attendance:userid'] = 'The ID of the user who attended.';
$string['privacy:metadata:webexmeetings_attendance:join_time'] = 'The time the user first joined.';
$string['privacy:metadata:webexmeetings_attendance:leave_time'] = 'The time the user last left.';
$string['privacy:metadata:webexmeetings_attendance:duration'] = 'Total duration of attendance.';
$string['privacy:metadata:webexmeetings_sessions'] = 'Individual session data for Webex meetings.';
$string['privacy:metadata:webexmeetings_sessions:userid'] = 'The ID of the user.';
$string['privacy:metadata:webexmeetings_sessions:join_time'] = 'The time the user joined.';
$string['privacy:metadata:webexmeetings_sessions:leave_time'] = 'The time the user left.';
$string['privacy:metadata:webexmeetings_sessions:duration'] = 'Duration of the session.';
$string['privacy:metadata:webexmeetings_sessions:ip_address'] = 'IP address of the user.';
$string['privacy:metadata:webex'] = 'Data shared with Cisco Webex for meeting management.';
$string['privacy:metadata:webex:email'] = 'User email address.';
$string['privacy:metadata:webex:fullname'] = 'User full name.';
