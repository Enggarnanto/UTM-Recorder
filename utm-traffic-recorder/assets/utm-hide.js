(function () {
  'use strict';

  var fields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_match_type', 'utm_matchtype'];
  var initial = new URLSearchParams(window.location.search);
  var captured = {};
  var names = [];
  initial.forEach(function (value, name) {
    var field = name.toLowerCase();
    if (fields.indexOf(field) === -1) return;
    names.push(name);
    if (!Object.prototype.hasOwnProperty.call(captured, field)) captured[field] = value;
  });
  if (!names.length) return;

  captured.utm_match_type = captured.utm_match_type || captured.utm_matchtype || '';
  window.utrUrlUtm = captured;

  try {
    if (!window.history || !window.history.replaceState) return;
    var url = new URL(window.location.href);
    names.forEach(function (name) { url.searchParams.delete(name); });
    window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
  } catch (error) { /* The original URL and captured attribution remain available. */ }
}());
