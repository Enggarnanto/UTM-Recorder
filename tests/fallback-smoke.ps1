$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$script = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\whatsapp.js') -Raw
$script64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($script))
$adminScript = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\wa-admin.js') -Raw
$admin64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($adminScript))
$adminCss = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\admin.css') -Raw
$adminCss64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($adminCss))
$waCss = Get-Content -LiteralPath (Join-Path $root 'utm-traffic-recorder\assets\whatsapp.css') -Raw
$waCss64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($waCss))
$html = @"
<!doctype html><html><body><pre id="result">PENDING</pre><script>
try {
  new Function(atob('$admin64'));
  var style = document.createElement('style'); style.textContent = atob('$adminCss64'); document.head.appendChild(style);
  var noticeFixture = document.createElement('div'); noticeFixture.id = 'wpbody-content';
  noticeFixture.innerHTML = '<div class="utr-admin"><header class="utr-header"><div><h1>CTA WA</h1><span class="utr-version-badge">v2.6.10</span><div class="notice notice-success"><p>Pengaturan berhasil disimpan.</p></div></div></header></div>';
  document.body.appendChild(noticeFixture);
  var noticeVisible = getComputedStyle(noticeFixture.querySelector('.notice p')).color === 'rgb(30, 41, 59)';
  function testPosition(doc, gap) {
    var css = doc.createElement('style'); css.textContent = atob('$waCss64'); doc.head.appendChild(css);
    var widget = doc.createElement('div'); widget.id='utr-wa-widget'; widget.className='utr-wa-widget utr-wa-widget-left';
    widget.innerHTML='<button class="utr-wa-launcher">Chat</button>'; doc.body.appendChild(widget);
    var left = Math.abs(widget.getBoundingClientRect().left - gap) < 1;
    widget.className='utr-wa-widget utr-wa-widget-right';
    var right = Math.abs(doc.documentElement.clientWidth - widget.getBoundingClientRect().right - gap) < 1;
    widget.className='utr-wa-widget utr-wa-widget-left';
    return left && right && Math.abs(widget.getBoundingClientRect().left - gap) < 1;
  }
  var desktopPosition = testPosition(document, 18);
  var mobileFrame=document.createElement('iframe'); mobileFrame.style.width='375px'; mobileFrame.style.height='500px'; document.body.appendChild(mobileFrame);
  var mobilePosition = testPosition(mobileFrame.contentDocument, 14);
  var whatsapp = new Function('window', 'document', 'sessionStorage', atob('$script64'));
  function run(options, setup) {
    setup = setup || {};
    var link = {href: setup.href || 'https://wa.me/6200000000001', dataset: setup.dataset || {}};
    var click;
    var doc = {nodeType: 9, querySelectorAll: function () { return [link]; }, getElementById: function () { return null; }, addEventListener: function (type, callback) { if(type === 'click') click = callback; }};
    var win = {utrWhatsappConfig: Object.assign({message: 'Halo', enhanceLinks: true, fallbackEnabled: true, sourceSeparator: '+', accounts: []}, options || {}), location: new URL('https://example.com/kelas/'), utrTracking: {session_id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', visitor_id: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'}, localStorage: {getItem: function () { return null; }}, navigator: {}};
    if (setup.query) win.location = new URL('https://example.com/kelas/' + setup.query);
    win.localStorage.getItem = function () { return JSON.stringify(setup.localUtm || null); };
    whatsapp(win, doc, {getItem: function () { return JSON.stringify(setup.sessionUtm || null); }});
    if (setup.nextUtm) {
      setup.localUtm = setup.nextUtm;
      click({target: {closest: function () { return link; }}});
      click({target: {closest: function () { return link; }}});
    }
    return new URL(link.href).searchParams.get('text');
  }
  var expected = ['Halo\n\nSumber: https://example.com/kelas/', 'Halo\n\nSumber: https://example.com/kelas/+aaaaaaaa', 'Halo\n\nSumber: https://example.com/kelas/+AAAAAAAA+BBBBBBBB', 'Halo\n\nAsal: https://example.com/custom/ | aaaaaaaa | bbbbbbbb', 'Halo'];
  var actual = [run({fallbackIdFields: []}), run({fallbackIdFields: ['session_id']}), run({fallbackIdFields: ['session_id','visitor_id'], sourceCase: 'upper'}), run({fallbackIdFields: ['session_id','visitor_id'], sourceSeparator: ' | ', fallbackLabel: 'Asal', fallbackUrlMode: 'custom', fallbackCustomUrl: 'https://example.com/custom/'}), run({fallbackEnabled: false, fallbackIdFields: ['session_id']})];
  expected.push('Halo GADS / aaaaaaaa / bbbbbbbb / Promo Oktober /  / {unknown}', 'Akun kedua', 'Pesan lama GADS', 'Term A & B', '[gads] Halo Gurulesprivate, saya ingin mendaftar. cust_id: gads', 'Pesan lama tanpa variabel');
  actual.push(
    run({fallbackEnabled: false, sourceFields: [], accounts: [{id: 1, phone: '6200000000001', message: 'Halo {utm_source} / {session_id} / {visitor_id} / {custom} / {utm_campaign} / {unknown}', message_custom: 'Promo Oktober'}]}, {query: '?utm_source=GADS'}),
    run({fallbackEnabled: false, sourceFields: [], accounts: [{id: 1, phone: '6200000000001', message_custom: 'Akun pertama'}, {id: 2, phone: '6200000000001', message_custom: 'Akun kedua'}]}, {dataset: {utrAccount: '2', utrMessageEncoded: encodeURIComponent('{custom}')}}),
    run({fallbackEnabled: false, sourceFields: [], message: '{custom}'}, {href: 'https://wa.me/6200000000001?text=' + encodeURIComponent('Pesan lama {utm_source}'), query: '?utm_source=GADS'}),
    run({fallbackEnabled: false, sourceFields: [], message: 'Term {utm_term}'}, {query: '?utm_term=A%20%26%20B'}),
    run({fallbackEnabled: false, sourceFields: [], accounts: [{id: 1, phone: '6200000000001', message_custom: 'gads'}]}, {href: 'https://wa.me/6200000000001?text=' + encodeURIComponent('[{utm_source}] Halo Gurulesprivate, saya ingin mendaftar. cust_id: {custom}'), query: '?utm_source=gads'}),
    run({fallbackEnabled: false, sourceFields: [], message: 'Pesan akun baru'}, {href: 'https://wa.me/6200000000001?text=' + encodeURIComponent('Pesan lama tanpa variabel')})
  );
  expected.push('Default GADS', 'Default', 'Pesan akun', 'Default', 'Halo', 'Halo session', 'Halo terbaru', '[GADS] AAAAAAAA BBBBBBBB EXACT Promo', 'Kosong:', null);
  actual.push(
    run({message: 'Default {utm_source}', fallbackEnabled: false, sourceFields: [], accounts: [{id: 1, phone: '6200000000001', message: ''}]}, {dataset: {utrAccount:'1', utrMessageEncoded:''}, query:'?utm_source=GADS'}),
    run({message: 'Default', fallbackEnabled: false, sourceFields: [], accounts: [{id: 1, phone: '6200000000001', message: '  '}]}, {dataset: {utrAccount:'1', utrMessageEncoded:encodeURIComponent('  ')}}),
    run({message: 'Default', fallbackEnabled: false, sourceFields: [], accounts: [{id: 1, phone: '6200000000001', message: 'Pesan akun'}]}, {dataset: {utrAccount:'1', utrMessageEncoded:encodeURIComponent('Pesan akun')}}),
    run({message: 'Default', fallbackEnabled: false, sourceFields: []}, {href:'https://wa.me/6200000000001?text=%20%20'}),
    run({fallbackEnabled: false, sourceFields: []}),
    run({message:'Halo {utm_source}', fallbackEnabled:false, sourceFields:[], sourceMode:'session'}, {localUtm:{utm_source:'browser'}, sessionUtm:{utm_source:'session'}}),
    run({message:'Halo {utm_source}', fallbackEnabled:false, sourceFields:[]}, {localUtm:{utm_source:'lama'}, nextUtm:{utm_source:'terbaru'}}),
    run({message:'[{ UTM_SOURCE }] {{session_id}} {browser_id} {utm_matchtype} {custom}', fallbackEnabled:false, sourceFields:[], sourceCase:'upper', accounts:[{id:1,phone:'6200000000001',message_custom:'Promo'}]}, {query:'?utm_source=gads&utm_matchtype=exact'}),
    run({message:'Kosong: {utm_campaign}', fallbackEnabled:false, sourceFields:[]}),
    run({message:'Default', enhanceLinks:false, fallbackEnabled:false, sourceFields:[]})
  );
  document.getElementById('result').textContent = noticeVisible && desktopPosition && mobilePosition && actual.every(function (value, index) { return value === expected[index]; }) ? 'FALLBACK_TEST_PASS' : 'FALLBACK_TEST_FAIL ' + JSON.stringify({actual: actual, expected: expected, noticeVisible: noticeVisible, desktopPosition:desktopPosition, mobilePosition:mobilePosition});
} catch (error) { document.getElementById('result').textContent = 'FALLBACK_TEST_FAIL ' + error.message; }
</script></body></html>
"@
$htmlPath = Join-Path $env:TEMP ('utr-fallback-' + [guid]::NewGuid().ToString('N') + '.html')
$profilePath = Join-Path $env:TEMP ('utr-fallback-profile-' + [guid]::NewGuid().ToString('N'))
$stdoutPath = Join-Path $env:TEMP ('utr-fallback-out-' + [guid]::NewGuid().ToString('N') + '.txt')
$stderrPath = Join-Path $env:TEMP ('utr-fallback-err-' + [guid]::NewGuid().ToString('N') + '.txt')
try {
  [IO.File]::WriteAllText($htmlPath, $html, [Text.UTF8Encoding]::new($false))
  $chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
  if (-not (Test-Path -LiteralPath $chrome)) { $chrome = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' }
  $uri = [Uri]::new($htmlPath).AbsoluteUri
  $process = Start-Process -FilePath $chrome -ArgumentList @('--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', "--user-data-dir=$profilePath", '--dump-dom', $uri) -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath
  $output = [IO.File]::ReadAllText($stdoutPath)
  $result = [regex]::Match($output, '<pre id="result">([^<]+)</pre>')
  if (-not $result.Success -or $result.Groups[1].Value -ne 'FALLBACK_TEST_PASS') { throw "Browser fallback test failed (exit $($process.ExitCode)): $($result.Groups[1].Value)" }
  Write-Output 'Browser fallback test: PASS'
} finally {
  Remove-Item -LiteralPath $htmlPath, $stdoutPath, $stderrPath -ErrorAction SilentlyContinue
  $tempRoot = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
  if ($profilePath.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $profilePath)) {
    Remove-Item -LiteralPath $profilePath -Recurse -Force -ErrorAction SilentlyContinue
  }
}
