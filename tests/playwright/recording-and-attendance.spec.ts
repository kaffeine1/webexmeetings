// Synthetic local fixtures only. No Webex account or production session is used.
const fs = require('node:fs');
const {test, expect} = require(process.env.SEN6_PLAYWRIGHT_MODULE || '@playwright/test');
const fixturePath = process.env.WEBEX_QA_FIXTURE;
if (!fixturePath) throw new Error('WEBEX_QA_FIXTURE must point to a synthetic local fixture');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
if (typeof fixture.meetingPassword !== 'string' || !fixture.meetingPassword.length) {
    throw new Error('A non-empty synthetic meetingPassword is required');
}
if (!/^http:\/\/(127\.0\.0\.1|localhost):\d+$/.test(fixture.baseURL)) {
    throw new Error('This destructive fixture test runs only on localhost');
}

test.describe.configure({mode: 'serial'});
test.beforeEach(async ({context}) => {
    await context.route('**/*', route => new URL(route.request().url()).origin === fixture.baseURL ?
        route.continue() : route.abort());
});
async function login(page, username) {
    await page.goto(fixture.baseURL + '/login/index.php');
    await page.locator('#username').fill(username);
    // Moodle replaces the password input when its asynchronous visibility toggle loads.
    await page.locator('.toggle-sensitive-wrapper #password').waitFor();
    await page.locator('#password').fill(fixture.password);
    const post = page.waitForRequest(request => request.method() === 'POST' && request.url().includes('/login/index.php'));
    await page.locator('#loginbtn').click();
    const sent = new URLSearchParams((await post).postData());
    expect(sent.get('username')).toBe(username);
    expect(sent.get('password') === fixture.password).toBe(true);
    await page.waitForURL(url => !url.pathname.includes('/login/'));
}

async function assertPasswordHidden(page) {
    expect((await page.content()).includes(fixture.meetingPassword)).toBe(false);
    await expect(page.locator('body')).not.toContainText(fixture.meetingPassword);
}

test('personal entry can be enabled and rejects ambiguous email without anonymous fallback', async ({page, context}) => {
    if (!fixture.apiStatePath || !fixture.adminemail) throw new Error('Synthetic entry API fixture required');
    const state = () => JSON.parse(fs.readFileSync(fixture.apiStatePath, 'utf8'));
    const writeState = value => fs.writeFileSync(fixture.apiStatePath, JSON.stringify(value));
    writeState({constructs: 0, requests: [], fail: false});
    let externalEntries = 0;
    await context.route('https://example.webex.com/**', async route => {
        externalEntries++;
        await route.fulfill({status: 200, contentType: 'text/html', body: '<h1>Synthetic personal Webex entry</h1>'});
    });
    const settingsURL = fixture.baseURL + '/admin/settings.php?section=modsettingwebexmeetings';
    await login(page, fixture.admin);
    await page.goto(settingsURL);
    const setting = page.locator('input[type=checkbox][name="s_mod_webexmeetings_personalized_join"]');
    await expect(setting).not.toBeChecked();
    await setting.check();
    await page.getByRole('button', {name: /Save changes|Salva modifiche/}).click();
    await page.goto(settingsURL);
    await expect(setting).toBeChecked();
    try {
        await page.goto(fixture.baseURL + '/mod/webexmeetings/view.php?id=' + fixture.cmid);
        const opened = context.waitForEvent('page');
        await page.locator('a[href*="/mod/webexmeetings/join.php"]').click();
        const entry = await opened;
        await expect(entry.getByRole('dialog')).toBeVisible();
        expect(state().constructs).toBe(0);
        await entry.getByRole('dialog').getByRole('button', {name: /OK, (enter|accedi)/}).click();
        await expect(entry.getByRole('heading', {name: 'Synthetic personal Webex entry'})).toBeVisible();
        expect(state().constructs).toBe(1);
        expect(state().requests).toHaveLength(1);
        expect(state().requests[0].email).toBe(fixture.adminemail);
        expect(state().requests[0].meetingid).toBe('synthetic-series');
        await entry.close();
        writeState({...state(), fail: true});
        const failedOpened = context.waitForEvent('page');
        await page.locator('a[href*="/mod/webexmeetings/join.php"]').click();
        const failedEntry = await failedOpened;
        await expect(failedEntry.getByRole('dialog')).toBeVisible();
        expect(state().constructs).toBe(1);
        await failedEntry.getByRole('dialog').getByRole('button', {name: /OK, (enter|accedi)/}).click();
        await expect(failedEntry.getByText(/Unable to generate your personal|Impossibile generare il tuo link personale/)).toBeVisible();
        expect(state().constructs).toBe(2);
        expect(state().requests).toHaveLength(2);
        expect(externalEntries).toBe(1);
        await failedEntry.close();
        await context.clearCookies();
        await login(page, fixture.student);
        await page.goto(fixture.baseURL + '/mod/webexmeetings/join.php?id=' + fixture.cmid);
        await page.getByRole('dialog').getByRole('button', {name: /OK, (enter|accedi)/}).click();
        await expect(page.getByText(/requires a valid email belonging to one active|richiede un indirizzo email valido associato a un solo/i)).toBeVisible();
        expect(externalEntries).toBe(1);
        expect(state().constructs).toBe(2);
        expect(new URL(page.url()).origin).toBe(fixture.baseURL);
    } finally {
        writeState({constructs: 0, requests: [], fail: false});
        await context.clearCookies();
        await login(page, fixture.admin);
        await page.goto(settingsURL);
        await setting.uncheck();
        await page.getByRole('button', {name: /Save changes|Salva modifiche/}).click();
    }
});

test('explicit mapping validates CSRF, persists and imports a single presence', async ({page}) => {
    await login(page, fixture.admin);
    const url = fixture.baseURL + '/mod/webexmeetings/unmatched_users.php?id=' + fixture.cmid;
    await page.goto(url);
    const form = page.locator('tr').filter({hasText: 'synthetic@guest.webex.localhost'}).locator('form');
    await expect(form).toBeVisible();
    const mapid = await form.locator('[name=mapid]').inputValue();
    await page.request.post(url, {form: {mapid, userid: String(fixture.studentid), sesskey: 'invalid'}});
    await page.reload();
    await expect(form).toBeVisible();
    await form.locator('select[name=userid]').selectOption(String(fixture.studentid));
    await form.locator('input[type=submit]').click();
    await expect(page.locator('tr').filter({hasText: 'synthetic@guest.webex.localhost'})).toHaveCount(0);
    await page.reload();
    await expect(page.locator('tr').filter({hasText: 'synthetic@guest.webex.localhost'})).toHaveCount(0);
    await page.goto(fixture.baseURL + '/mod/webexmeetings/view.php?id=' + fixture.cmid);
    await expect(page.locator('tr').filter({hasText: 'student@example.invalid'})).toHaveCount(1);
});

test('recording notice cancels safely and accepts only an explicit POST', async ({page}) => {
    let joins = 0;
    await page.route('https://example.webex.com/**', async route => {
        joins++;
        await route.fulfill({status: 200, contentType: 'text/html', body: '<h1>Synthetic Webex destination</h1>'});
    });
    await login(page, fixture.student);
    const url = fixture.baseURL + '/mod/webexmeetings/join.php?id=' + fixture.cmid;
    await page.goto(url);
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await page.screenshot({path: test.info().outputPath('recording-desktop.png'), fullPage: true});
    await dialog.getByRole('button', {name: /Cancel|Annulla/}).click();
    await page.waitForURL(/view\.php/);
    expect(joins).toBe(0);
    await page.goto(url + '&recordingaccepted=1');
    await expect(page.getByText(/Synthetic Webex destination/)).toHaveCount(0);
    expect(joins).toBe(0);
    await page.setViewportSize({width: 390, height: 844});
    await page.goto(url);
    await expect(dialog).toBeVisible();
    await page.screenshot({path: test.info().outputPath('recording-mobile.png'), fullPage: true});
    await dialog.getByRole('button', {name: /OK, (enter|accedi)/}).click();
    await expect(page.getByRole('heading', {name: 'Synthetic Webex destination'})).toBeVisible();
    expect(joins).toBe(1);
});

test('students cannot access manual matching', async ({page}) => {
    await login(page, fixture.student);
    await page.goto(fixture.baseURL + '/mod/webexmeetings/unmatched_users.php?id=' + fixture.cmid);
    await expect(page.locator('select[name=userid]')).toHaveCount(0);
    await expect(page.locator('[name=mapid]')).toHaveCount(0);
});

test('organiser must confirm before any host API call', async ({page}) => {
    await login(page, fixture.admin);
    await page.goto(fixture.baseURL + '/mod/webexmeetings/view.php?id=' + fixture.cmid);
    await page.getByRole('button', {name: /Start as organiser|Avvia come organizzatore/}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', {name: /Cancel|Annulla/}).click();
    await page.waitForURL(/view\.php/);
    await expect(page.getByRole('button', {name: /Start as organiser|Avvia come organizzatore/})).toBeVisible();
});

test('ended meeting hides entry and host commands and rejects direct controllers', async ({page}) => {
    await login(page, fixture.admin);
    await page.goto(fixture.baseURL + '/mod/webexmeetings/view.php?id=' + fixture.expiredcmid);
    await expect(page.getByRole('button', {name: /Meeting access closed|Accesso alla riunione chiuso/})).toBeDisabled();
    await page.screenshot({path: test.info().outputPath('ended-meeting.png'), fullPage: true});
    await expect(page.getByRole('button', {name: /Start as organiser|Avvia come organizzatore/})).toHaveCount(0);
    await expect(page.locator('a[href*="/mod/webexmeetings/join.php"]')).toHaveCount(0);
    await assertPasswordHidden(page);
    const sesskey = await page.evaluate(() => (window as any).M.cfg.sesskey);
    const start = await page.request.post(fixture.baseURL + '/mod/webexmeetings/start.php?id=' + fixture.expiredcmid,
        {form: {sesskey, recordingaccepted: '1'}, maxRedirects: 0});
    expect(await start.text()).toMatch(/scheduled end time|orario di fine previsto/i);
    const join = await page.request.get(fixture.baseURL + '/mod/webexmeetings/join.php?id=' + fixture.expiredcmid,
        {maxRedirects: 0});
    expect(await join.text()).toMatch(/scheduled end time|orario di fine previsto/i);
});

test('students see no password and cannot join an ended meeting', async ({page}) => {
    await login(page, fixture.student);
    for (const cmid of [fixture.cmid, fixture.expiredcmid]) {
        await page.goto(fixture.baseURL + '/mod/webexmeetings/view.php?id=' + cmid);
        await assertPasswordHidden(page);
    }
    await page.goto(fixture.baseURL + '/mod/webexmeetings/join.php?id=' + fixture.expiredcmid);
    await expect(page.getByText(/scheduled end time|orario di fine previsto/i)).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
});

test('recurring series is not labelled ended after its first occurrence', async ({page}) => {
    await login(page, fixture.admin);
    await page.goto(fixture.baseURL + '/mod/webexmeetings/view.php?id=' + fixture.recurringcmid);
    await expect(page.locator('a[href*="/mod/webexmeetings/join.php"]')).toHaveCount(1);
    await expect(page.getByRole('button', {name: /Start as organiser|Avvia come organizzatore/})).toBeVisible();
    await expect(page.getByText(/Scheduled meeting time ended|Orario della riunione terminato/)).toHaveCount(0);
    await assertPasswordHidden(page);
    await page.goto(fixture.baseURL + '/mod/webexmeetings/join.php?id=' + fixture.recurringcmid);
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', {name: /Cancel|Annulla/}).click();
    await page.waitForURL(/view\.php/);
    await page.getByRole('button', {name: /Start as organiser|Avvia come organizzatore/}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', {name: /Cancel|Annulla/}).click();
    await page.waitForURL(/view\.php/);
});

test('future meeting keeps previous early-entry behaviour on both controllers', async ({page}) => {
    await login(page, fixture.admin);
    await page.goto(fixture.baseURL + '/mod/webexmeetings/join.php?id=' + fixture.futurecmid);
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', {name: /Cancel|Annulla/}).click();
    await page.waitForURL(/view\.php/);
    await page.getByRole('button', {name: /Start as organiser|Avvia come organizzatore/}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', {name: /Cancel|Annulla/}).click();
    await page.waitForURL(/view\.php/);
});
