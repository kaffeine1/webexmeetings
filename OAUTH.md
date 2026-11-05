# Webex OAuth connection

This version completes initial Webex authorisation inside Moodle. No Postman
callback or manual refresh-token transfer is needed. Existing saved refresh
tokens and personal tokens are not changed by the upgrade.

## Administrator setup

1. Open **Site administration > Plugins > Activity modules > Webex Meetings**.
2. Copy the **Redirect URI** displayed there. Register that exact HTTPS URL in
   a Webex **Integration** owned by the customer's organisation.
3. Register the scopes corresponding to the chosen permission profile.
4. Enter the integration's **Client ID** and **Client Secret**, choose the
   permission profile and save Moodle settings. Configure the Webex site and
   host email as required by the customer's account. For the first host-profile
   test, leave the optional host email empty so Webex uses the account granting
   consent. Scheduling for a different host requires the organisation profile
   and separate acceptance.
5. In **Webex connection**, select **User authorised to connect Webex** and
   choose the customer's existing, active Moodle account. This grants no
   Moodle administration capability. Give that user the **Webex connection**
   page URL, not the Client Secret or an OAuth URL from another browser.
6. The selected user signs in to Moodle, opens **Webex connection**, selects
   **Connect Webex**, and signs in to Cisco with the intended licensed host
   account. They approve access in the same browser. The callback exchanges
   the code and stores the tokens; successful connection selects OAuth.
7. The authorising user can run **Test connection** from the same connection page after consent;
   the Moodle administrator can repeat it. It reads the Webex account profile
   and the meeting list; it does not create a meeting or certify attendance collection. Test those
   functions with an agreed test meeting and participants before rollout.

## First live acceptance on gsetest

Do not use a real course as the first test. After the selected Moodle user has
authorised a licensed Webex host account, run **Test connection**. Then create a
non-recurring 15-30 minute test activity in a test course. Confirm that the
activity has a Webex join link and that it appears in the host's Webex calendar.
Have the host and a second, identifiable participant join, end the meeting,
and run **Sync now** from the activity. Check the participant and duration in
the attendance report, or the unmatched-user list if their Webex email does
not correspond to their Moodle email. Only after this test should the plugin
be considered ready for other instances.

If creation fails, Moodle now rejects the activity rather than saving one
without a Webex link. A successful connection test alone does not prove that
the Webex account is licensed to host meetings or that Cisco returns
attendance for the test session. Recurring meetings need a separate acceptance
test before they are used with attendance tracking.

The selected user must remain logged in to the same Moodle instance and
browser during authorisation; they do not need Moodle site configuration
privileges. A pending request lasts ten minutes. Starting again in the same
session replaces the previous pending request. Changing or removing the
selected user invalidates pending requests but does not remove an existing
Webex connection. A refused, expired or invalid callback does not replace the
saved connection. An administrator may also connect directly, as before.

Canonical callback:

```text
https://YOUR-MOODLE-HOST/mod/webexmeetings/oauth_callback.php
```

The displayed value also includes a Moodle subdirectory when applicable.
`test_connection.php` is not a callback. Do not register a different hostname,
HTTP URL, extra query parameters or a different trailing slash.

## Permission profiles

Host profile, for meetings owned by the account granting consent:

```text
meeting:schedules_read meeting:schedules_write meeting:participants_read spark:people_read
```

Organisation profile, only when administration of other hosts is required:

```text
meeting:admin_schedule_read meeting:admin_schedule_write meeting:admin_participants_read spark:people_read
```

Administrative scopes require corresponding Webex organisation/site privileges;
they do not grant those privileges to an ordinary user. The account/site must
also support the requested Meetings APIs. The profile-read scope is used by
the connection test. Neither recording access nor `spark:all` is requested.

Changing client credentials, permission profile or Moodle URL after an
integrated authorisation requires a new connection. Register any new scopes
in Cisco before requesting them. Previously imported legacy refresh tokens
remain usable; their cached access tokens are bound to their credentials.

## Token lifecycle and safety

- Only an administrator with `moodle/site:config` can select the delegated
  Moodle user, edit integration credentials, or remove the local connection.
  The selected active, confirmed Moodle user may only start and complete the
  site-wide Webex authorisation. This user can replace the Webex identity used
  for new meetings, so delegate only the intended Webex host account owner.
- Connection changes and explicit connection tests require POST and a Moodle
  sesskey. The external callback instead validates a random, single-use state
  bound to the initiating Moodle user's session and uses PKCE S256.
- OAuth endpoints are fixed to Webex, with TLS verification, no redirects
  for token requests, and bounded connection/request timeouts. Moodle's HTTP
  security and proxy configuration remain in effect.
- Token renewal uses a Moodle lock and fresh DB settings. Access tokens are
  reused until near expiry; rotated refresh/access tokens are saved together
  in a DB transaction, shared by web and cron nodes. Use a lock factory that
  coordinates all nodes and consistent Moodle configuration across them.
- Token values are not displayed on the connection page, returned in errors,
  included in config history by this code, or written to plugin debug files.
  PHP 8.2 sensitive-parameter attributes also hide OAuth arguments in traces.
  Existing `config_plugins` credentials and new access tokens are server-side
  secrets: protect database access and backups accordingly.
- The editable refresh-token setting has been removed to prevent an older
  settings form from overwriting a token rotated by another request. Existing
  stored values are retained. Reauthorisation is the supported replacement.
- A routine token refresh does not cancel an administrator's pending consent.
  A newer connection or explicit disconnection invalidates older callbacks.
- Forced `config.php` settings that prevent token updates are rejected by the
  connection workflow rather than silently overridden.
- **Remove local connection** retains Client ID/Secret but deletes local OAuth
  tokens and stops their use. It does not revoke the Cisco authorisation and
  does not fall back to an old personal token. Revoke the grant in Webex as well
  when required by the organisation's policy.

This update stops the old debug-file writes; it does not delete existing
`webex_debug.txt` files or rewrite Git history. Review and remove historic
sensitive logs under the applicable retention/access policy and rotate any
exposed credentials. Configure infrastructure logs not to retain OAuth callback
query strings; the browser callback necessarily receives a short-lived code.

## Verification and rollback

Run the Moodle PHPUnit suite in an isolated test installation:

```sh
vendor/bin/phpunit mod/webexmeetings/tests/oauth_test.php
vendor/bin/phpunit mod/webexmeetings/tests/webex_api_test.php
```

Tests use fake Webex responses with real Moodle settings, transactions and
locks. They do not use customer credentials or contact Cisco. Final acceptance
requires a real consent flow and an agreed test meeting.

Back up the instance's current plugin configuration and Git revision before
deployment. Upgrade on one node only, after all web/cron nodes have identical
code. There are no new tables or schema changes. Before rolling back a used
OAuth connection, preserve the latest refresh token: an older backup token
may no longer work after rotation. Use Moodle's supported upgrade/version
handling and coordinate any DB version/config restoration; merely switching
to a lower plugin version can cause a downgrade error.

## References

- [Webex authentication](https://developer.webex.com/admin/docs/authentication)
- [Webex PKCE](https://developer.webex.com/create/docs/login-with-webex)
- [Webex Meetings permissions](https://developer.webex.com/meeting/docs/meetings)
