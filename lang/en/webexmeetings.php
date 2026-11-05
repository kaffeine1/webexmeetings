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
$string['apisettings_desc'] = 'Configure the credentials for connecting to the Cisco Webex API. Enter your Personal Access Token or alternatively, use OAuth2 credentials.';
$string['auth_method'] = 'Authentication method';
$string['auth_method_desc'] = 'Choose how to authenticate with the Webex API.';
$string['auth_bot'] = 'Personal / Bot Token';
$string['auth_oauth'] = 'OAuth 2.0';
$string['personaltoken'] = 'Personal Access Token / Integration Token';
$string['personaltoken_desc'] = 'Enter the Webex token to authorize meeting creation. You can generate this from the developer portal or from a Webex Integration.';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'OAuth2 Client ID from the Webex integration.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'OAuth2 Client Secret from the Webex integration.';
$string['refreshtoken'] = 'Refresh token';
$string['refreshtoken_desc'] = 'OAuth2 Refresh token. This is used to automatically obtain new access tokens.';
$string['siteurl'] = 'Webex site URL';
$string['siteurl_desc'] = 'Your Webex site URL (e.g., sitename.webex.com). Leave empty to use the default.';
$string['apihostemail'] = 'Default Host Email API';
$string['apihostemail_desc'] = 'For an authorised Webex administrator scheduling on behalf of another host, enter that host\'s Webex email. With the host permission profile, leave this empty: meetings belong to the Webex account that authorised the connection.';
$string['testconnection'] = 'Test connection';
$string['connectionsuccessful'] = 'Webex identity and meeting read access verified. Meeting creation and attendance still require a live test.';
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

// OAuth connection administration.
$string['oauthmanage'] = 'Webex connection';
$string['oauthredirecturi'] = 'Redirect URI';
$string['oauthscopes'] = 'Webex permissions';
$string['oauthscopeprofile'] = 'Webex permission profile';
$string['oauthscopehost'] = 'Meetings of the authorising account';
$string['oauthscopeorganization'] = 'Organisation meetings (Webex administrator)';
$string['oauthconnect'] = 'Connect Webex';
$string['webexnavigation'] = 'Webex';
$string['oauthdelegateuser'] = 'User authorised to connect Webex';
$string['oauthdelegatewarning'] = 'This user can authorise or replace the Webex connection for the entire Moodle site. Clear the selection to remove this permission.';
$string['oauthdelegatesaved'] = 'The authorised Webex user has been updated.';
$string['oauthinvaliddelegate'] = 'Select an active, confirmed Moodle user.';
$string['oauthdisconnect'] = 'Remove local connection';
$string['oauthdisconnectconfirm'] = 'Remove the saved OAuth tokens from this Moodle site? Meeting management and attendance synchronisation will stop until Webex is connected again. The authorisation in Cisco is not revoked. Client ID and Client Secret will be retained.';
$string['oauthdisconnected'] = 'The local Webex connection has been removed.';
$string['oauthconnected'] = 'Webex authorisation saved successfully.';
$string['oauthconnectedat'] = 'Authorisation saved on';
$string['oauthconnectionstored'] = 'OAuth credentials are saved. Connection testing is available separately.';
$string['oauthconnectioninactive'] = 'OAuth credentials are saved, but a different authentication method is selected.';
$string['oauthnotconnected'] = 'There is no saved OAuth connection.';
$string['oauthmissingclient'] = 'Save the Webex Client ID and Client Secret in the plugin settings before connecting.';
$string['oauthinvalidprofile'] = 'The Webex permission profile is invalid.';
$string['oauthhttpsrequired'] = 'An HTTPS Moodle address is required for Webex authorisation.';
$string['oauthinvalidstate'] = 'This authorisation does not match the current Moodle session. Start a new connection from the Webex connection page.';
$string['oauthexpiredstate'] = 'The authorisation request has expired. Start a new connection.';
$string['oauthdenied'] = 'Webex authorisation was cancelled or denied. The previous connection has not been replaced.';
$string['oauthinvalidcode'] = 'Webex did not return a valid authorisation code. Start a new connection.';
$string['oauthconfigchanged'] = 'The Webex connection settings have changed. Save the settings and authorise Webex again.';
$string['oauthmissingscopes'] = 'Webex has not granted all the required permissions. Check the integration permissions and authorise again.';
$string['oauthbusy'] = 'Another request is updating the Webex connection. Try again shortly.';
$string['oauthforcedsettings'] = 'The connection is controlled by config.php settings and cannot be updated here.';
$string['oauthrequestfailed'] = 'The Webex token request failed. Check the credentials, permissions, network connection and registered Redirect URI. No provider response or token is displayed.';
$string['oauthinvalidresponse'] = 'Webex did not return a complete, valid token response. The saved connection has not been replaced.';
$string['oauthsavefailed'] = 'The Webex tokens could not be saved. Start a new authorisation.';
$string['oauthpostrequired'] = 'This action must be submitted using the button on the Webex connection page.';
$string['oauthtestfailed'] = 'Webex connection test failed. Check the saved credentials, permissions and network connection.';
$string['meetingcreationerror'] = 'Error creating Webex meeting: {$a}';
$string['invalidmeetingduration'] = 'The meeting must last between 10 minutes and 24 hours.';
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
$string['startmeetingashost'] = 'Start as organiser';
$string['personalizedjoin'] = 'Personal Webex entry';
$string['personalizedjoin_desc'] = 'Generate a five-minute browser entry link using the signed-in Faddy user\'s name and unique email, so attendance can be matched automatically. Requires Webex API access. No anonymous fallback is used if generation fails. Existing anonymous attendance is not reassigned. Disabled by default for controlled rollout.';
$string['participantidentityunavailable'] = 'A personal Webex link requires a valid email belonging to one active Faddy account only. Ask an administrator to check your account.';
$string['personaljoinunavailable'] = 'Unable to generate your personal Webex entry link. Check the Webex connection and access policies, then try again. No anonymous entry has been substituted.';
$string['disclaimer_participants'] = 'Show recording notice to participants';
$string['disclaimer_assignees'] = 'Show recording notice to assigned staff';
$string['disclaimer_participants_help'] = 'Ask students to confirm the recording notice before entering this activity. This activity setting overrides the default for new activities.';
$string['disclaimer_assignees_help'] = 'Ask teachers, tutors and other assigned staff to confirm the recording notice. A user in both groups sees one notice when either setting is enabled.';
$string['disclaimerdefaults_desc'] = 'Default for new activities only. Existing activity settings are not changed. This does not enable Webex recording.';
$string['recordingdisclaimertitle'] = 'Recording notice';
$string['recordingdisclaimertext'] = 'Recording notice text';
$string['recordingdisclaimertext_desc'] = 'Plain text, not HTML. Leave empty to use the standard notice. Confirmation does not start recording or transmit consent to Webex.';
$string['recordingdisclaimerdefault'] = 'This session may be recorded. By entering the virtual room you accept that the session may be recorded.';
$string['recordingdisclaimeraccept'] = 'OK, enter the room';
$string['meetingscheduleended'] = 'Scheduled meeting time ended';
$string['meetingaccessclosed'] = 'Meeting access closed';
$string['meetingaccessended'] = 'The scheduled end time of this activity has passed. Entry and organiser launch are no longer available from Faddy. Attendance reports remain available.';
$string['startmeetingnotallowed'] = 'Only a Moodle administrator or authorised Webex delegate who can manage this activity may start it as organiser.';
$string['startmeetingunavailable'] = 'Unable to obtain the organiser link. Check the Webex connection, meeting host and site access policies, then try again.';
