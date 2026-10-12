(function () {
  'use strict';
  if (!window.utrConfig || !window.utrConfig.endpoint || document.body.classList.contains('logged-in')) return;

  var visitorKey = 'utr_visitor_v1';
  var visitorCookie = 'utr_vid_v1';
  var sessionKey = 'utr_session_v2';
  var waVisitorKey = 'utr_wa_utm_v1';
  var waSessionKey = 'utr_wa_session_utm_v1';
  var legacyKey = 'utr_first_touch_v1';
  var allFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_match_type'];
  var fields = Array.isArray(window.utrConfig.fields) ? allFields.filter(function (field) { return window.utrConfig.fields.indexOf(field) !== -1; }) : allFields;
  var params = new URLSearchParams(window.location.search);
  var captured = window.utrUrlUtm && typeof window.utrUrlUtm === 'object' ? window.utrUrlUtm : null;
  var queryUtm = {};
  params.forEach(function (value, name) {
    var field = name.toLowerCase();
    if ((allFields.indexOf(field) !== -1 || field === 'utm_matchtype') && !Object.prototype.hasOwnProperty.call(queryUtm, field)) queryUtm[field] = value;
  });
  var hasUrlUtm = Object.keys(queryUtm).length > 0;
  function cleanUtm(value) {
    return String(value || '').replace(/[\x00-\x1f\x7f]+/g, ' ').trim().slice(0, 191);
  }
  function paramValue(field) {
    var value = hasUrlUtm ? queryUtm[field] || (field === 'utm_match_type' ? queryUtm.utm_matchtype : '') : captured && (captured[field] || (field === 'utm_match_type' ? captured.utm_matchtype : ''));
    return cleanUtm(value);
  }
  var hasNewUtm = hasUrlUtm || !!(captured && (allFields.some(function (field) { return Object.prototype.hasOwnProperty.call(captured, field); }) || Object.prototype.hasOwnProperty.call(captured, 'utm_matchtype')));

  function uuid() {
    if (!window.crypto || !window.crypto.getRandomValues) return '';
    var bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    var hex = Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
  }

  function read(storage, key) {
    try { return JSON.parse(storage.getItem(key) || 'null'); } catch (error) { return null; }
  }

  function write(storage, key, value) {
    try {
      var serialized = JSON.stringify(value);
      if (storage.getItem(key) !== serialized) storage.setItem(key, serialized);
      return storage.getItem(key) === serialized;
    } catch (error) { return false; }
  }

  function readVisitorCookie() {
    try {
      var item = document.cookie.split(';').map(function (part) { return part.trim(); }).find(function (part) { return part.indexOf(visitorCookie + '=') === 0; });
      return item ? item.slice(visitorCookie.length + 1) : '';
    } catch (error) { return ''; }
  }

  function writeVisitorCookie(id) {
    try {
      document.cookie = visitorCookie + '=' + id + '; Max-Age=31536000; Path=/; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
    } catch (error) { /* Cookies can be disabled. */ }
  }

  var sessionStore = null;
  try { sessionStore = window.sessionStorage; } catch (error) { /* Storage can be disabled. */ }
  var legacy = read(sessionStore, legacyKey);
  var storedVisitor = null;
  try { storedVisitor = read(window.localStorage, visitorKey); } catch (error) { /* Storage can be disabled. */ }
  var oldWaVisitor = null;
  try { oldWaVisitor = read(window.localStorage, waVisitorKey); } catch (error) { /* Storage can be disabled. */ }
  var validId = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
  var cookieId = readVisitorCookie();
  var visitor = {
    visitor_id: storedVisitor && validId.test(storedVisitor.visitor_id) ? storedVisitor.visitor_id : validId.test(cookieId) ? cookieId : uuid(),
    utm_snapshot_version: 2
  };
  var mergeOldVisitor = oldWaVisitor && (!storedVisitor || storedVisitor.utm_snapshot_version !== 2);
  allFields.forEach(function (field) {
    var previous = mergeOldVisitor && typeof oldWaVisitor[field] === 'string' ? oldWaVisitor[field] : storedVisitor && typeof storedVisitor[field] === 'string' ? storedVisitor[field] : legacy && legacy[field] || '';
    visitor[field] = cleanUtm(previous);
  });
  if (!visitor.visitor_id) return;
  if (cookieId !== visitor.visitor_id) writeVisitorCookie(visitor.visitor_id);

  // A new tagged URL replaces the complete active UTM set.
  if (hasNewUtm) {
    allFields.forEach(function (field) { visitor[field] = paramValue(field); });
  }
  var visitorSaved = false;
  try {
    visitorSaved = write(window.localStorage, visitorKey, visitor);
    if (visitorSaved) window.localStorage.removeItem(waVisitorKey);
  } catch (error) { /* Storage can be disabled. */ }
  window.utrLocalWriteFailed = !visitorSaved;

  var session = read(sessionStore, sessionKey);
  var oldWaSession = read(sessionStore, waSessionKey);
  var newSession = !session || !validId.test(session.session_id);
  if (newSession) {
    session = legacy && validId.test(legacy.session_id) ? {
      session_id: legacy.session_id,
      landing_path: legacy.landing_path,
      referrer_host: legacy.referrer_host,
      referrer_url: ''
    } : {
      session_id: uuid(),
      landing_path: window.location.pathname,
      referrer_host: '',
      referrer_url: ''
    };
    if (!legacy || !validId.test(legacy.session_id)) {
      try {
        var referrer = document.referrer ? new URL(document.referrer) : null;
        if (referrer && (referrer.protocol === 'https:' || referrer.protocol === 'http:')) {
          session.referrer_host = referrer.hostname;
          session.referrer_url = referrer.href;
        }
      } catch (error) { /* Browsers may omit or restrict the referrer. */ }
    }
    if (!session.session_id) return;
  }
  var mergeOldSession = oldWaSession && oldWaSession.session_id === session.session_id && session.utm_snapshot_version !== 2;
  allFields.forEach(function (field) {
    if (hasNewUtm) {
      session[field] = paramValue(field);
    } else if (mergeOldSession && typeof oldWaSession[field] === 'string') {
      session[field] = cleanUtm(oldWaSession[field]);
    } else if (newSession) {
      session[field] = cleanUtm(legacy && typeof legacy[field] === 'string' ? legacy[field] : '');
    } else {
      session[field] = cleanUtm(typeof session[field] === 'string' ? session[field] : '');
    }
  });
  session.utm_snapshot_version = 2;
  var sessionSaved = write(sessionStore, sessionKey, session);
  window.utrSessionWriteFailed = !sessionSaved;
  if (sessionSaved) {
    try { sessionStore.removeItem(waSessionKey); } catch (error) { /* Storage can be disabled. */ }
  }
  if (legacy) {
    try { sessionStore.removeItem(legacyKey); } catch (error) { /* Storage can be disabled. */ }
  }

  window.utrWaUtm = visitor;
  window.utrWaSessionUtm = session;

  var attribution = Object.assign({}, visitor, {
    session_id: session.session_id,
    landing_path: session.landing_path,
    referrer_host: session.referrer_host,
    referrer_url: session.referrer_url || ''
  });
  window.utrTracking = attribution;
  window.utrSessionUtm = session;
  var payload = Object.assign({}, attribution, {page_path: window.location.pathname});
  allFields.forEach(function (field) { if (fields.indexOf(field) === -1) payload[field] = ''; });
  fetch(window.utrConfig.endpoint, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload),
    credentials: 'omit',
    keepalive: true
  }).catch(function () { /* Tracking must never interrupt the page. */ });
}());
