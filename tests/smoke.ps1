$ErrorActionPreference = 'Stop'
# Nomor 6200000000001-4 adalah fixture yang sengaja tidak valid dan tidak dapat dihubungi.
$root = Split-Path -Parent $PSScriptRoot
$tracker = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\tracker.js') -Raw
$whatsapp = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\whatsapp.js') -Raw
$utmHide = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\utm-hide.js') -Raw
$whatsappCss = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\whatsapp.css') -Raw
$tracker64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($tracker))
$whatsapp64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($whatsapp))
$utmHide64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($utmHide))
$whatsappCss64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($whatsappCss))
$html = @"
<!doctype html><html><body><pre id="result">PENDING</pre><script>
try {
  var tracker = new Function('window', 'document', 'sessionStorage', 'fetch', atob('$tracker64'));
  var whatsapp = new Function('window', 'document', 'sessionStorage', atob('$whatsapp64'));
  var utmHide = new Function('window', 'document', atob('$utmHide64'));
  var cleanWindow = {location: new URL('https://example.com/promo?ref=x&utm_source=GADS&utm_medium=CPC&utm_campaign=A&utm_content=B&utm_term=C&utm_matchtype=exact&coupon=123#paket')};
  cleanWindow.history = {state: {test: 1}, replaceState: function (state, title, path) { this.state = state; cleanWindow.location = new URL(path, cleanWindow.location.href); }};
  utmHide(cleanWindow, {readyState: 'complete'});
  var hiderOk = cleanWindow.location.href === 'https://example.com/promo?ref=x&coupon=123#paket' && cleanWindow.utrUrlUtm.utm_source === 'GADS' && cleanWindow.utrUrlUtm.utm_term === 'C' && cleanWindow.utrUrlUtm.utm_match_type === 'exact' && cleanWindow.history.state.test === 1;
  var data = new Map();
  var browserData = new Map();
  var hits = [];
  var clicks = [];
  var storage = {getItem: function (key) { return data.get(key) || null; }, setItem: function (key, value) { data.set(key, value); }, removeItem: function (key) { data.delete(key); }};
  var browserStorage = {getItem: function (key) { return browserData.get(key) || null; }, setItem: function (key, value) { browserData.set(key, value); }};
  function message(link) { return new URL(link.href).searchParams.get('text'); }
  function page(path, enhance, format) {
    var location = new URL('https://example.com' + path);
    var win = {utrConfig: {endpoint: 'https://example.com/hit'}, utrWhatsappConfig: {message: 'Halo', enhanceLinks: enhance !== false, recordEndpoint: 'https://example.com/wa-click', accounts: [{id: 9, phone: '6200000000003', message: 'Halo admin\n\nSaya ingin tanya.'}]}, location: location, localStorage: browserStorage, crypto: window.crypto, navigator: {}, fetch: function (endpoint, request) { clicks.push(JSON.parse(request.body)); return Promise.resolve({}); }};
    Object.assign(win.utrWhatsappConfig, format || {});
    var links = [
      {href: 'https://api.whatsapp.com/send?phone=6200000000001'},
      {href: 'https://api.whatsapp.com/send?phone=6200000000002'},
      {href: 'https://wa.me/send?phone=6200000000001'},
      {href: 'https://wa.me/send?phone=6200000000002&text=Halo%2C%20saya%20ingin%20bertanya%20tentang%20program.%0A%0A'},
      {href: 'https://wa.me/6200000000001', dataset: {utrAccount: '7', utrType: 'shortcode', utrMessage: 'Pesan akun'}},
      {href: 'https://wa.me/6200000000001', dataset: {utrAccount: '8', utrType: 'floating', utrMessage: 'Baris pertama\n\nBaris kedua'}},
      {href: 'https://api.whatsapp.com/send?phone=6200000000003'}
    ];
    var doc = {nodeType: 9, body: {classList: {contains: function () { return false; }}}, referrer: 'https://search.example/landing?q=abc', querySelectorAll: function () { return links; }, getElementById: function () { return null; }, addEventListener: function (name, fn) { this.click = fn; }, documentElement: {}};
    win.MutationObserver = function (callback) { doc.observeMutation = callback; this.observe = function () {}; };
    tracker(win, doc, storage, function (endpoint, request) { hits.push(JSON.parse(request.body)); return Promise.resolve({}); });
    whatsapp(win, doc, storage);
    var before = links[3].href;
    doc.click({target: {closest: function () { return links[3]; }}});
    doc.click({target: {closest: function () { return links[0]; }}});
    doc.click({target: {closest: function () { return links[4]; }}});
    doc.click({target: {closest: function () { return links[6]; }}});
    var dynamic = {nodeType: 1, href: 'https://api.whatsapp.com/send?phone=6200000000004&text=Pesan%20baru', matches: function () { return true; }};
    if (doc.observeMutation) doc.observeMutation([{type: 'childList', addedNodes: [dynamic]}]);
    return {messages: links.map(message), urls: links.map(function (link) { return new URL(link.href); }), dynamic: message(dynamic), noDuplicate: before === links[3].href};
  }
  var noUtm = page('/produk');
  var tagged = page('/promo?utm_source=GADS&utm_medium=CPC&utm_campaign=CAMPAIGN_A&utm_content=ADS_GROUP_A&utm_term=KEYWORD_A&utm_matchtype=exact');
  var carried = page('/checkout');
  var replaced = page('/baru?utm_source=email&utm_match_type=phrase');
  var off = page('/off', false);
  var style = document.createElement('style');
  style.textContent = atob('$whatsappCss64');
  document.head.appendChild(style);
  document.body.insertAdjacentHTML('beforeend', '<div id="utr-wa-widget" class="utr-wa-widget utr-wa-widget-left"><div id="utr-wa-panel" class="utr-wa-panel" hidden><button class="utr-wa-close">Tutup</button><a href="https://wa.me/6200000000001">Akun</a></div><button class="utr-wa-launcher" aria-expanded="false">Chat WhatsApp</button></div>');
  var actualAccountLink = document.querySelector('#utr-wa-panel a');
  actualAccountLink.dataset.utrAccount = '13';
  actualAccountLink.dataset.utrMessageEncoded = encodeURIComponent('Halo kak\n\nParagraf kedua');
  window.utrWhatsappConfig = {message: 'Halo', enhanceLinks: true, recordEndpoint: ''};
  whatsapp(window, document, sessionStorage);
  var widget = document.getElementById('utr-wa-widget');
  var panel = document.getElementById('utr-wa-panel');
  var launcher = widget.querySelector('.utr-wa-launcher');
  var leftPosition = getComputedStyle(widget).left;
  launcher.click();
  var opened = !panel.hidden && launcher.getAttribute('aria-expanded') === 'true';
  widget.querySelector('.utr-wa-close').click();
  widget.classList.remove('utr-wa-widget-left');
  widget.classList.add('utr-wa-widget-right');
  var widgetOk = opened && panel.hidden && launcher.getAttribute('aria-expanded') === 'false' && leftPosition === '18px' && getComputedStyle(widget).right === '18px' && new URL(actualAccountLink.href).searchParams.get('text').startsWith('Halo kak\n\nParagraf kedua\n\nSumber: ');
  var firstSession = hits[0].session_id;
  var firstVisitor = hits[0].visitor_id;
  var sessionSuffix = '+' + firstSession.slice(0, 8);
  data.clear();
  var returnWindow = {utrConfig: {endpoint: 'https://example.com/hit'}, location: new URL('https://example.com/return'), localStorage: browserStorage, crypto: window.crypto};
  tracker(returnWindow, {body: {classList: {contains: function () { return false; }}}, referrer: ''}, storage, function (endpoint, request) { hits.push(JSON.parse(request.body)); return Promise.resolve({}); });
  var newSessionOk = hits[5].visitor_id === firstVisitor && hits[5].session_id !== firstSession && hits[5].utm_source === 'email' && hits[5].utm_medium === '' && JSON.parse(browserStorage.getItem('utr_visitor_v1')).visitor_id === firstVisitor;
  var ok = noUtm.messages[0] === 'Halo\n\nSumber: https://example.com/produk' + sessionSuffix &&
    noUtm.messages[3] === 'Halo, saya ingin bertanya tentang program.\n\nSumber: https://example.com/produk' + sessionSuffix &&
    tagged.messages[0] === 'Halo\n\nSumber: GADS' + sessionSuffix &&
    carried.messages[0] === tagged.messages[0] &&
    replaced.messages[0] === 'Halo\n\nSumber: email' + sessionSuffix &&
    tagged.messages[3] === 'Halo, saya ingin bertanya tentang program.\n\nSumber: GADS' + sessionSuffix &&
    tagged.urls[0].searchParams.get('phone') === '6200000000001' &&
    tagged.urls[1].searchParams.get('phone') === '6200000000002' &&
    tagged.urls[2].searchParams.get('phone') === '6200000000001' &&
    tagged.urls[3].searchParams.get('phone') === '6200000000002' &&
    tagged.messages[4] === 'Pesan akun\n\nSumber: GADS' + sessionSuffix &&
    tagged.messages[5] === 'Baris pertama\n\nBaris kedua\n\nSumber: GADS' + sessionSuffix && tagged.noDuplicate &&
    tagged.messages[6] === 'Halo admin\n\nSaya ingin tanya.\n\nSumber: GADS' + sessionSuffix &&
    tagged.dynamic === 'Pesan baru\n\nSumber: GADS' + sessionSuffix &&
    clicks.length === 17 && clicks[4].utm_source === 'GADS' && clicks[4].utm_content === 'ADS_GROUP_A' && clicks[4].utm_term === 'KEYWORD_A' && clicks[4].utm_match_type === 'exact' && clicks[4].message_source === 'Sumber: GADS' + sessionSuffix && clicks[6].account_id === '7' && clicks[6].link_type === 'shortcode' && clicks[7].account_id === 9 && clicks[7].link_type === 'existing' && clicks[16].account_id === '7' && off.messages[0] === null &&
    hits[0].referrer_url === 'https://search.example/landing?q=abc' && hits[0].referrer_host === 'search.example' && hits[1].utm_match_type === 'exact' && hits[3].utm_match_type === 'phrase' && hits[3].utm_source === 'email' && hits[3].utm_medium === '' &&
    new Set(hits.map(function (hit) { return hit.session_id; })).size === 2 && widgetOk && hiderOk && newSessionOk;
  var custom = page('/custom?utm_source=google&utm_medium=cpc', true, {sourceLabel: 'Asal', sourceCase: 'upper', sourceFields: ['utm_source', 'utm_medium', 'session_id', 'visitor_id'], sourceSeparator: ' | '});
  var customSuffix = hits[hits.length - 1].session_id.slice(0, 8).toUpperCase() + ' | ' + hits[hits.length - 1].visitor_id.slice(0, 8).toUpperCase();
  var customOk = custom.messages[0] === 'Halo\n\nAsal: GOOGLE | CPC | ' + customSuffix && clicks[clicks.length - 4].message_source === 'Asal: GOOGLE | CPC | ' + customSuffix;
  var noSource = page('/no-source', true, {sourceFields: []});
  customOk = customOk && noSource.messages[0] === 'Halo' && clicks[clicks.length - 4].message_source === '';
  document.getElementById('result').textContent = ok && customOk ? 'UTR_TEST_PASS' : 'UTR_TEST_FAIL: ' + JSON.stringify({noUtm: noUtm.messages, tagged: tagged.messages, carried: carried.messages, replaced: replaced.messages, custom: custom.messages, noSource: noSource.messages, hits: hits});
} catch (error) { document.getElementById('result').textContent = 'UTR_TEST_FAIL: ' + error.message; }
</script></body></html>
"@
$htmlPath = Join-Path $env:TEMP ('utr-smoke-' + [guid]::NewGuid().ToString('N') + '.html')
$profilePath = Join-Path $env:TEMP ('utr-profile-' + [guid]::NewGuid().ToString('N'))
$stdoutPath = Join-Path $env:TEMP ('utr-stdout-' + [guid]::NewGuid().ToString('N') + '.txt')
$stderrPath = Join-Path $env:TEMP ('utr-stderr-' + [guid]::NewGuid().ToString('N') + '.txt')
Set-Content -LiteralPath $htmlPath -Value $html -Encoding UTF8
$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
if (-not (Test-Path -LiteralPath $chrome)) { $chrome = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' }
try {
  $uri = [Uri]::new($htmlPath).AbsoluteUri
  $process = Start-Process -FilePath $chrome -ArgumentList @('--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', "--user-data-dir=$profilePath", '--dump-dom', $uri) -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath
  $output = Get-Content -LiteralPath $stdoutPath
  $result = ($output | Select-String '<pre id="result">' | Select-Object -First 1).Line
  if ($result -notmatch 'UTR_TEST_PASS') { throw "Browser smoke test failed (exit $($process.ExitCode)): $result $(Get-Content -LiteralPath $stderrPath -Tail 3)" }
  Write-Output 'Browser smoke test: PASS'
} finally {
  Remove-Item -LiteralPath $htmlPath -ErrorAction SilentlyContinue
  Remove-Item -LiteralPath $stdoutPath -ErrorAction SilentlyContinue
  Remove-Item -LiteralPath $stderrPath -ErrorAction SilentlyContinue
  $tempRoot = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
  if ($profilePath.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $profilePath)) {
    Remove-Item -LiteralPath $profilePath -Recurse -Force -ErrorAction SilentlyContinue
  }
}
