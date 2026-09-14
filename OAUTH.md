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
   host email as required by the customer's account.
5. Open **Webex connection** and select **Connect Webex**. Sign in to Cisco
   with the intended host or an appropriately authorised Webex administrator.
6. Approve access. The browser returns to Moodle, which exchanges the code
   and stores the tokens. Successful connection selects OAuth authentication.
7. Run **Test connection** separately. It reads the Webex account profile;
   it does not create a meeting or certify attendance collection. Test those
   functions with an agreed test meeting and participants before rollout.

The administrator must remain logged in to the same Moodle instance during
authorisation. A pending request lasts ten minutes. Starting again in the
same session replaces the previous pending request. A refused, expired or
invalid callback does not replace the saved connection.

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

- Only users with `moodle/site:config` can connect or remove a connection.
- Connection changes and explicit connection tests require POST and a Moodle
  sesskey. The external callback instead validates a random, single-use state
  bound to the initiating administrator session and uses PKCE S256.
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
