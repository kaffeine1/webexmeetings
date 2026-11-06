# Webex Local Validation

The spec runs only against synthetic fixtures on localhost. It must not run
against gsetest or production: the manual matching scenario changes test data.

Provide WEBEX_QA_FIXTURE with a JSON file outside the repository containing:
baseURL, admin, student, password, cmid and studentid. Both accounts must be
synthetic; the student must be enrolled in the test activity. The activity
must enable both recording notices and contain one unmatched identity with
email synthetic@guest.webex.localhost. Its join URL must be
https://example.webex.com/join/synthetic; Playwright intercepts it locally.
No real Webex credentials or grant are needed.
For creation-time validation, provide courseid and pastStart with the
day/month/year/hour/minute select values for a past date in the synthetic
admin's timezone (minute must be available in the date widget). The browser
submits the real creation form, checks the field-level warning and retained
values, and verifies that no API constructor was called.
The personal-entry setting must initially be disabled. For its rejection
scenario, add a second synthetic active account with the student's exact
email (it need not be enrolled). The spec enables the setting via the admin
form, checks that ambiguous identity blocks entry before any API request,
then restores the option to disabled. Never seed this ambiguity on a real
site. The disposable PHP development server must load
tests/fixtures/entry_api_stub.php after Moodle setup, with
CFG->webexqa_entry_state pointing to a private JSON file outside the webroot.
That guarded test double runs only at http://127.0.0.1:8122 with database
webex22_qa and PHP cli-server. Add apiStatePath (the runner-readable path to
that JSON) and adminemail to the browser fixture. The spec exercises the
actual view/new-tab/notice/join controllers with success and failure from
this explicit fake API, and verifies identity and no fallback. It does not
call the real API class; that class has native tests and a separate live
Webex probe. The browser spec does not certify real Webex attendance.
For the organiser pre-consent scenario, set auth_method=oauth on this
disposable fixture without configuring any client secret or token: the test
cancels before the API constructor. The admin must be allowed to manage it.

Use Playwright Test, with SEN6_PLAYWRIGHT_MODULE optionally pointing to its
installed module. Assertions cover CSRF rejection, explicit matching and
presence display, cancellation of the notice, rejection of GET acceptance,
explicit confirmation, denial of manual matching to students and the host
confirmation before any host API request.

No production fixtures, credentials or recorded sessions are included here.
Running the spec with Playwright is not certification of Sen6 UI import.

The access-window scenarios also require expiredcmid and recurringcmid:
activities of the same course whose first scheduled occurrence has ended,
respectively non-recurring and recurring. Supply meetingPassword with a
synthetic password stored in all fixtures. Assertions ensure it is never
rendered, expired entry is closed in UI and controllers, and later recurring
occurrences are not blocked by the first scheduled end.
The futurecmid fixture must be a single meeting with a future start/end in
the same course; early-entry checks must reach the notice, not a closed
activity error. Test the stored password with HTML-sensitive characters:
the spec checks both the HTML source and decoded body text.
