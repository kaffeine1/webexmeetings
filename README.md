# Moodle Webex Meetings Plugin (`mod_webexmeetings`)

This is a Moodle activity module that allows teachers to create and manage Webex meetings directly within their Moodle courses.

## Requirements
- Moodle 4.1 or later
- Webex account with API access
- Configured Webex OAuth integration

## Installation
1. Clone or copy this repository into your Moodle installation under `mod/webexmeetings`.
2. Log in as an administrator and complete the installation process.
3. Configure the plugin settings with your Webex API credentials.

### Connect Webex
Initial OAuth authorisation and token renewal are managed inside Moodle.
Register the displayed callback in a Webex Integration, save its Client ID and
Client Secret, then use **Webex connection > Connect Webex** to grant consent.
See [OAuth setup and verification](OAUTH.md) for permission profiles, safety and
rollback instructions. No manual refresh-token transfer is required.

## Features
- Create, update, and delete Webex meetings from Moodle
- Automatic synchronization of meeting attendance back to Moodle grades
- Secure OAuth2 authentication

## License
This plugin is licensed under the GNU General Public License v3 or later. See the `LICENSE` file for details.
