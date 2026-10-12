(function () {
  'use strict';

  var config = window.utrWhatsappConfig;
  if (!config) return;

  var allFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_match_type'];
  var fields = Array.isArray(config.fields) ? allFields.filter(function (field) { return config.fields.indexOf(field) !== -1; }) : allFields;
  var sourceChoices = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_match_type', 'session_id', 'visitor_id'];
  var sourceFields = Array.isArray(config.sourceFields) ? sourceChoices.filter(function (field) { return config.sourceFields.indexOf(field) !== -1; }) : ['utm_source', 'session_id'];
  var trackLinks = typeof config.trackLinks === 'boolean' ? config.trackLinks : !!config.enhanceLinks;
  var originals = new WeakMap();

  function clean(value) {
    return String(value || '').replace(/[\x00-\x1f\x7f]+/g, ' ').trim().slice(0, 191);
  }

  function messageParagraphs(value) {
    return String(value || '').replace(/%0D%0A|%0A|%0D/gi, '\n').replace(/\\r\\n|\\n/g, '\n').replace(/\r\n?/g, '\n').trim();
  }

  function configuredMessage(link, account) {
    var data = link.dataset || {};
    if (data.utrAccount) {
      if (typeof data.utrMessageEncoded === 'string') {
        try { return decodeURIComponent(data.utrMessageEncoded); } catch (error) { /* Fall back to the plain attribute. */ }
      }
      return data.utrMessage || '';
    }
    return account && account.message || config.message || '';
  }

  function selectedUtm(raw) {
    var values = {};
    allFields.forEach(function (field) { values[field] = clean(raw && raw[field]); });
    return values;
  }

  function currentUtm(mode) {
    var params = new URLSearchParams(window.location.search);
    var incoming = {};
    params.forEach(function (value, name) {
      var field = name.toLowerCase();
      if ((allFields.indexOf(field) !== -1 || field === 'utm_matchtype') && !Object.prototype.hasOwnProperty.call(incoming, field)) incoming[field] = value;
    });
    if (Object.keys(incoming).length) {
      incoming.utm_match_type = incoming.utm_match_type || incoming.utm_matchtype || '';
      return selectedUtm(incoming);
    }
    if (mode === 'session') {
      if (window.utrSessionWriteFailed && window.utrSessionUtm) return selectedUtm(window.utrSessionUtm);
      try {
        var activeSession = JSON.parse(sessionStorage.getItem('utr_session_v2') || 'null');
        if (activeSession) return selectedUtm(activeSession);
      } catch (error) { /* Storage can be disabled. */ }
      if (window.utrWaSessionUtm) return selectedUtm(window.utrWaSessionUtm);
      if (window.utrUrlUtm) return selectedUtm(window.utrUrlUtm);
      if (window.utrSessionUtm) return selectedUtm(window.utrSessionUtm);
      try { return selectedUtm(JSON.parse(sessionStorage.getItem('utr_wa_session_utm_v1') || 'null')); }
      catch (error) { return {}; }
    }
    if (window.utrLocalWriteFailed && window.utrWaUtm) return selectedUtm(window.utrWaUtm);
    try {
      var savedVisitor = JSON.parse(window.localStorage.getItem('utr_visitor_v1') || 'null');
      if (savedVisitor) return selectedUtm(savedVisitor);
    } catch (error) { /* Storage can be disabled. */ }
    if (window.utrWaUtm) return selectedUtm(window.utrWaUtm);
    if (window.utrUrlUtm) return selectedUtm(window.utrUrlUtm);
    if (window.utrTracking) {
      return selectedUtm(window.utrTracking);
    }
    try {
      var saved = JSON.parse(window.localStorage.getItem('utr_wa_utm_v1') || 'null');
      if (saved) return selectedUtm(saved);
    } catch (error) { /* Storage can be disabled. */ }
    return {};
  }

  function sourceText() {
    if (!sourceFields.length) return '';
    var utm = currentUtm(config.sourceMode === 'session' ? 'session' : 'local');
    var parts = [];
    var selectedUtmFields = sourceFields.filter(function (field) { return allFields.indexOf(field) !== -1; });
    selectedUtmFields.forEach(function (field) {
      if (utm[field]) parts.push(formatSourceValue(utm[field]));
    });
    var session = window.utrTracking && window.utrTracking.session_id || '';
    var visitor = window.utrTracking && window.utrTracking.visitor_id || '';
    if (!session || !visitor) {
      try {
        var savedSession = JSON.parse(sessionStorage.getItem('utr_session_v2') || 'null');
        session = session || savedSession && savedSession.session_id || '';
      } catch (error) { /* Storage can be disabled. */ }
      try {
        var savedVisitor = JSON.parse(window.localStorage.getItem('utr_visitor_v1') || 'null');
        visitor = visitor || savedVisitor && savedVisitor.visitor_id || '';
      } catch (error) { /* Storage can be disabled. */ }
    }
    var validId = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
    if (sourceFields.indexOf('session_id') !== -1 && validId.test(session)) parts.push(formatSourceValue(session.slice(0, 8)));
    if (sourceFields.indexOf('visitor_id') !== -1 && validId.test(visitor)) parts.push(formatSourceValue(visitor.slice(0, 8)));
    var separator = typeof config.sourceSeparator === 'string' ? config.sourceSeparator : '+';
    return parts.join(separator);
  }

  function formatSourceValue(value) {
    var mode = config.sourceCase;
    if (mode === 'upper') return value.toLocaleUpperCase();
    if (mode === 'lower') return value.toLocaleLowerCase();
    if (mode === 'title') return value.toLocaleLowerCase().replace(/(^|[\s_-])([a-z])/g, function (match, prefix, letter) { return prefix + letter.toLocaleUpperCase(); });
    return value;
  }

  function sourceLine() {
    var utm = currentUtm(config.sourceMode === 'session' ? 'session' : 'local');
    var hasUtm = allFields.some(function (field) { return !!utm[field]; });
    if (!hasUtm) {
      if (config.fallbackEnabled === false) return '';
      var page = new URL(window.location.href);
      allFields.forEach(function (field) { page.searchParams.delete(field); });
      page.searchParams.delete('utm_matchtype');
      var fallbackUrl = page.href;
      if (config.fallbackUrlMode === 'custom' && config.fallbackCustomUrl) {
        try {
          var custom = new URL(config.fallbackCustomUrl);
          if (custom.protocol === 'https:' || custom.protocol === 'http:') fallbackUrl = custom.href;
        } catch (error) { /* Use the current page URL. */ }
      }
      var fallbackLabel = String(config.fallbackLabel || 'Sumber').trim().replace(/[\s:]+$/, '') || 'Sumber';
      return fallbackLabel + ': ' + fallbackUrl;
    }
    var value = sourceText();
    if (!value) return '';
    var label = String(config.sourceLabel || 'Sumber').trim().replace(/[\s:]+$/, '') || 'Sumber';
    return label + ': ' + value;
  }

  function isWhatsAppUrl(url) {
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return false;
    var host = url.hostname.toLowerCase();
    var path = url.pathname.toLowerCase();
    if (host === 'api.whatsapp.com' || host === 'web.whatsapp.com') {
      return path === '/send' || path === '/send/';
    }
    if (host === 'wa.me') {
      return path === '/send' || path === '/send/' || /^\/[0-9]+\/?$/.test(path);
    }
    return false;
  }

  function phoneFromUrl(url) {
    return String(url.searchParams.get('phone') || (url.pathname.match(/^\/([0-9]+)\/?$/) || [])[1] || '').replace(/[^0-9]/g, '');
  }

  function accountForPhone(phone) {
    return (config.accounts || []).find(function (account) { return account.phone === phone; }) || null;
  }

  function prepareLink(link) {
    if (!link || !link.href || (!config.enhanceLinks && !(link.dataset && link.dataset.utrAccount))) return;
    var current = link.href;
    var previous = originals.get(link);
    var base = previous && current === previous.generated ? previous.base : current;
    var url;
    try { url = new URL(base); } catch (error) { return; }
    if (!isWhatsAppUrl(url)) return;

    var existing = url.searchParams.get('text');
    var account = accountForPhone(phoneFromUrl(url));
    var message = messageParagraphs(existing && existing.trim() ? existing : configuredMessage(link, account));
    var line = sourceLine();
    var finalMessage = message + (message && line ? '\n\n' : '') + line;
    if (finalMessage) url.searchParams.set('text', finalMessage);
    else url.searchParams.delete('text');
    var generated = url.href;
    originals.set(link, {base: base, generated: generated, sourceLine: line});
    if (current !== generated) link.href = generated;
  }

  function recordClick(link) {
    if (!config.recordEndpoint || !link || !link.href) return;
    if (!trackLinks && !(link.dataset && link.dataset.utrAccount)) return;
    var url;
    try { url = new URL(link.href); } catch (error) { return; }
    if (!isWhatsAppUrl(url)) return;
    var phone = phoneFromUrl(url);
    if (!/^[1-9][0-9]{6,14}$/.test(phone)) return;
    var account = accountForPhone(phone);
    var utm = currentUtm('local');
    var session = window.utrTracking && window.utrTracking.session_id || '';
    var visitor = window.utrTracking && window.utrTracking.visitor_id || '';
    try {
      if (!session) {
        var savedSession = JSON.parse(sessionStorage.getItem('utr_session_v2') || 'null');
        session = savedSession && savedSession.session_id || '';
      }
    } catch (error) { /* Storage can be disabled. */ }
    try {
      if (!visitor) {
        var savedVisitor = JSON.parse(window.localStorage.getItem('utr_visitor_v1') || 'null');
        visitor = savedVisitor && savedVisitor.visitor_id || '';
      }
    } catch (error) { /* Storage can be disabled. */ }
    var validId = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
    if (!validId.test(visitor) || !validId.test(session)) return;
    var prepared = originals.get(link);
    var payload = {
      account_id: link.dataset && link.dataset.utrAccount || (account && account.id) || 0,
      phone: phone,
      link_type: link.dataset && link.dataset.utrType || 'existing',
      page_path: window.location.pathname,
      visitor_id: visitor,
      session_id: session,
      message_source: prepared && link.href === prepared.generated ? prepared.sourceLine : ''
    };
    allFields.forEach(function (field) { payload[field] = fields.indexOf(field) !== -1 ? utm[field] || '' : ''; });
    var body = JSON.stringify(payload);
    if (window.navigator && window.navigator.sendBeacon) {
      try {
        if (window.navigator.sendBeacon(config.recordEndpoint, new Blob([body], {type: 'application/json'}))) return;
      } catch (error) { /* Fall back to fetch. */ }
    }
    if (window.fetch) {
      window.fetch(config.recordEndpoint, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: body, credentials: 'omit', keepalive: true}).catch(function () {});
    }
  }

  function scan(root) {
    if (root.nodeType === 1 && root.matches && root.matches('a[href]')) prepareLink(root);
    if (root.querySelectorAll) {
      Array.prototype.forEach.call(root.querySelectorAll('a[href]'), prepareLink);
    }
  }

  scan(document);

  var widget = document.getElementById('utr-wa-widget');
  if (widget) {
    var launcher = widget.querySelector('.utr-wa-launcher');
    var panel = widget.querySelector('.utr-wa-panel');
    var closeButton = widget.querySelector('.utr-wa-close');
    function setOpen(open) {
      panel.hidden = !open;
      launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
      launcher.setAttribute('aria-label', open ? 'Tutup pilihan chat WhatsApp' : 'Buka pilihan chat WhatsApp');
      if (open) { var first = panel.querySelector('a[href]'); if (first) first.focus(); }
    }
    launcher.addEventListener('click', function () { setOpen(panel.hidden); });
    closeButton.addEventListener('click', function () { setOpen(false); launcher.focus(); });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) { setOpen(false); launcher.focus(); }
    });
    document.addEventListener('click', function (event) {
      if (!panel.hidden && !widget.contains(event.target)) setOpen(false);
    });
  }
  document.addEventListener('click', function (event) {
    var target = event.target;
    var link = target && target.closest ? target.closest('a[href]') : null;
    if (link) {
      prepareLink(link);
      recordClick(link);
    }
  }, true);

  if (config.enhanceLinks && window.MutationObserver) {
    var observer = new window.MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.type === 'attributes') prepareLink(mutation.target);
        Array.prototype.forEach.call(mutation.addedNodes || [], scan);
      });
    });
    observer.observe(document.documentElement, {subtree: true, childList: true, attributes: true, attributeFilter: ['href']});
  }
}());
