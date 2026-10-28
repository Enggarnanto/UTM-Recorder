// UTM Recorder receiver. Configure Script Properties before running setupSheet.
const UTR_COLUMNS = [
  'event_key', 'site_id', 'site_url', 'id', 'occurred_at', 'timezone',
  'visitor_id', 'session_id', 'account_id', 'account_name', 'phone',
  'link_type', 'page_path', 'utm_source', 'utm_medium', 'utm_campaign',
  'utm_term', 'utm_content', 'utm_match_type', 'message_source',
  'message_source_recorded', 'received_at_utc'
];
// Display labels follow the click database fields; payload keys stay unchanged.
const UTR_HEADERS = [
  'ID Unik Sinkronisasi', 'ID Website', 'URL Website', 'ID Klik',
  'Waktu Klik (UTC)', 'Zona Waktu', 'ID Browser', 'ID Sesi',
  'ID Akun WA', 'Nama Akun WA', 'Nomor WA Tujuan', 'Jenis Link WA',
  'Halaman Klik', 'UTM Source', 'UTM Medium', 'UTM Campaign',
  'UTM Term', 'UTM Content', 'UTM Match Type', 'Sumber dalam Pesan WA',
  'Status Sumber Terekam (1/0)', 'Waktu Diterima Sheets (UTC)'
];

function utrFormatHeader(sheet) {
  sheet.getRange(1, 1, 1, UTR_HEADERS.length).setValues([UTR_HEADERS])
    .setFontWeight('bold').setBackground('#e8f0fe').setWrap(true);
  sheet.setFrozenRows(1);
  sheet.setColumnWidths(1, UTR_HEADERS.length, 170);
}

function utrJson(value) {
  return ContentService.createTextOutput(JSON.stringify(value))
    .setMimeType(ContentService.MimeType.JSON);
}

function utrSheet() {
  const id = PropertiesService.getScriptProperties().getProperty('SPREADSHEET_ID');
  if (!id) throw new Error('Set SPREADSHEET_ID first.');
  const book = SpreadsheetApp.openById(id);
  const sheet = book.getSheetByName('Data Klik WA') || book.insertSheet('Data Klik WA');
  if (sheet.getLastRow() === 0) {
    utrFormatHeader(sheet);
  }
  const headers = sheet.getRange(1, 1, 1, UTR_COLUMNS.length).getValues()[0];
  if (headers.join('|') === UTR_COLUMNS.join('|')) {
    // Upgrade the original headers in place; existing click rows are preserved.
    utrFormatHeader(sheet);
  } else if (headers.join('|') !== UTR_HEADERS.join('|')) {
    throw new Error('Column headers do not match.');
  }
  return sheet;
}

// Run once from the editor to authorize and create the table. No test click is inserted.
function setupSheet() {
  const lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try { utrSheet(); SpreadsheetApp.flush(); }
  finally { lock.releaseLock(); }
}

function doGet() {
  return utrJson({ok: true, service: 'UTM Recorder Sheets receiver', version: 2});
}

function doPost(e) {
  const lock = LockService.getScriptLock();
  let locked = false;
  try {
    if (!e || !e.postData || e.postData.contents.length > 500000) throw new Error('Invalid request.');
    const body = JSON.parse(e.postData.contents);
    const siteId = String(body.site_id || '');
    if (!/^[a-zA-Z0-9_-]{1,64}$/.test(siteId)) throw new Error('Invalid site_id.');
    const expected = PropertiesService.getScriptProperties().getProperty('SITE_KEY_' + siteId);
    if (!expected || expected.length < 32 || typeof body.token !== 'string' || body.token !== expected) {
      return utrJson({ok: false, error: 'Unauthorized'});
    }
    if (typeof body.site_url !== 'string' || !/^https?:\/\/[^\s]+$/i.test(body.site_url)) throw new Error('Invalid site_url.');
    const connectionTest = body.action === 'test';
    if (!connectionTest && (!Array.isArray(body.events) || body.events.length < 1 || body.events.length > 100)) {
      throw new Error('Send between 1 and 100 events.');
    }
    (connectionTest ? [] : body.events).forEach(function(event) {
      if (!event || !/^[1-9][0-9]{0,19}$/.test(String(event.id))) throw new Error('Invalid event id.');
      if (typeof event.occurred_at !== 'string' || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(event.occurred_at)) {
        throw new Error('occurred_at must use YYYY-MM-DD HH:mm:ss in UTC.');
      }
      UTR_COLUMNS.forEach(function(column) {
        const value = event[column];
        if (value !== undefined && value !== null && (typeof value === 'object' || String(value).length > 4096)) {
          throw new Error('Invalid field: ' + column);
        }
      });
    });
    locked = lock.tryLock(20000);
    if (!locked) return utrJson({ok: false, error: 'Busy. Retry later.'});
    const sheet = utrSheet();
    if (connectionTest) {
      return utrJson({ok: true, site_id: siteId, service: 'UTM Recorder Sheets receiver', version: 2});
    }
    const count = sheet.getLastRow() - 1;
    const seen = new Set(count ? sheet.getRange(2, 1, count, 1).getValues().map(function(row) { return String(row[0]); }) : []);
    const rows = [];
    const accepted = [];
    const received = new Date().toISOString();
    body.events.forEach(function(event) {
      const key = siteId + ':' + String(event.id);
      accepted.push(String(event.id));
      if (seen.has(key)) return;
      const record = Object.assign({}, event, {
        event_key: key, site_id: siteId, site_url: body.site_url,
        timezone: 'UTC', received_at_utc: received
      });
      rows.push(UTR_COLUMNS.map(function(column) {
        const value = String(record[column] == null ? '' : record[column]);
        // Preserve text and prevent spreadsheet formula execution.
        return /^[=+\-@\s]/.test(value) ? "'" + value : value;
      }));
      seen.add(key);
    });
    if (rows.length) {
      const range = sheet.getRange(sheet.getLastRow() + 1, 1, rows.length, UTR_COLUMNS.length);
      range.setNumberFormat('@');
      range.setValues(rows);
      SpreadsheetApp.flush();
    }
    return utrJson({ok: true, site_id: siteId, accepted_ids: accepted, inserted: rows.length});
  } catch (error) {
    // Do not expose spreadsheet identifiers or credentials in public responses.
    return utrJson({ok: false, error: 'Request failed. Check configuration and payload, then retry.'});
  } finally {
    if (locked) lock.releaseLock();
  }
}
