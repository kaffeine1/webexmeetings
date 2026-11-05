# Personal Webex Entry

Personal entry is an opt-in setting under Site administration > Plugins >
Activity modules > Webex Meetings. Enable **Personal Webex entry** only after
testing the connected Webex site. It is disabled by default, including on
existing installations. Disabling it restores the previous common join link.

When enabled, the plugin generates a five-minute browser join link via
`POST /meetings/join`, supplying the authenticated Moodle account's unique
email and full name. Neither identity nor meeting ID comes from request
parameters. The recording notice, course access rules and scheduled-end
checks run before link generation. A participant never receives the host
start link. The bearer-style URLs are not stored, rendered or logged by the
plugin; responses use no-store and no-referrer headers.

The existing attendance import automatically matches an email to one active
Moodle account. A personal join link provides Webex with that email instead
of relying on a generic guest entry. Test that the actual participant report
retains it: successful link generation alone is not an end-to-end attendance
test. Names alone, usernames inferred from emails, and anonymous guest email
addresses are not identity proofs. Existing anonymous records are not
reassigned retrospectively. Manual association remains available for genuine
exceptions and later imports of the same saved identity.

Invalid, shared or ambiguous email addresses stop personal entry, as do API
failures or an unsafe destination. There is no automatic fallback to an
anonymous link. Administrator intervention is required to correct an account
or deliberately disable the option. A personal link is a short-lived bearer
credential and must not be forwarded. Matching by email does not itself prove
that the participant controls that mailbox or attended personally.

Webex documents these generated links for web browsers, not mobile apps.
Mandatory sign-in policies in the customer's Control Hub still apply. The
plugin does not change organisation settings or grant a host/cohost role.
Manual entry from an unrelated invitation or the native application is not
guaranteed to carry the Moodle identity.

## Validation

1. Use separate synthetic active accounts with unique email addresses.
2. Enable the setting on the pilot only.
3. Enter a non-expired activity, confirming the notice if configured.
4. Check the attendee name and email in Webex, without host permissions.
5. End a short meeting, synchronise attendance and verify automatic matching,
   duration and a repeated import without duplicates.
6. Verify cancellation, expired activity, ambiguous email and API failure:
   none must result in anonymous or organiser entry.
7. Disable the option and verify restoration of the previous entry flow.

Source: [Webex Join a Meeting API](https://developer.webex.com/meeting/docs/api/v1/meetings/join-a-meeting).
