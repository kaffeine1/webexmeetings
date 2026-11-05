# Recording Notice

The general settings provide two defaults: participants and assigned staff.
These defaults apply only to new activities. Each activity stores its own
two Yes/No choices. Both No means no notice. Existing activities initially
retain No; changing the general defaults never enables notices in bulk.

Participants are student roles/archetypes. Assigned staff include Moodle
teaching/management roles and the Sailfor presenter, tutor, trainer,
scientific, moderator, staff and expert roles. Site administrators use the
staff switch. Users in both groups receive one notice if either switch is on.

The notice applies before the normal join link and before the host start
command. Acceptance requires an authenticated POST and session key. Cancel
returns to the activity. The core Moodle dialog has an HTML form fallback.
The general text is plain text; empty text uses the standard notice.

This is a local informational confirmation. It does not enable or disable
recording, send consent to Webex, provide a legal consent audit, change
meeting roles or implement webinar mode. Direct access to a Webex URL
outside Moodle is not controlled by this confirmation.

For non-recurring activities, Faddy closes join and organiser entry at the
scheduled end time. It does not terminate an ongoing Webex call. Entry before
the scheduled start remains subject to the existing Webex host/site policy.
Recurring activities are labelled as a series: the first occurrence end is
not treated as the end of the whole series. Availability remains managed by
Webex. The meeting password is retained for API operations but is not rendered
on the activity page, including for administrators.
