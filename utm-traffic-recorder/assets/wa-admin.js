(function ($) {
  'use strict';
  $(function () {
    var frame;
    $('#utr-avatar-select').on('click', function () {
      if (frame) { frame.open(); return; }
      frame = wp.media({title: 'Pilih avatar WhatsApp', button: {text: 'Gunakan avatar'}, library: {type: 'image'}, multiple: false});
      frame.on('select', function () {
        var image = frame.state().get('selection').first().toJSON();
        $('#utr-avatar-id').val(image.id);
        var src = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
        $('#utr-avatar-preview').empty().append($('<img>', {src: src, alt: ''}));
      });
      frame.open();
    });
    $('#utr-avatar-remove').on('click', function () {
      $('#utr-avatar-id').val('0');
      $('#utr-avatar-preview').html('<span class="utr-admin-avatar-fallback">WA</span>');
    });
    $('.utr-delete-link').on('click', function (event) {
      if (!window.confirm($(this).data('confirm'))) event.preventDefault();
    });

    $('.utr-insert-newline').on('click', function () {
      var field = document.getElementById($(this).data('target'));
      if (!field) return;
      field.focus();
      var start = field.selectionStart;
      var end = field.selectionEnd;
      if (typeof field.setRangeText === 'function') field.setRangeText('\n', start, end, 'end');
      else field.value = field.value.slice(0, start) + '\n' + field.value.slice(end);
      $(field).trigger('input');
    });

    function updatePreview() {
      var message = ($('#utr-account-message').val() || '').trim() || ($('#utr-global-message').val() || '').trim();
      var sample = {utm_source: 'GADS', utm_medium: 'CPC', utm_campaign: 'PROMO_A', utm_content: 'GRUP_A', utm_term: 'KURSUS', utm_match_type: 'EXACT', session_id: 'a1b2c3d4', visitor_id: 'e5f6a7b8'};
      sample.custom = $('#utr-message-custom').val() || '';
      var mode = $('#utr-source-case').val();
      function format(value) {
        if (mode === 'upper') return value.toLocaleUpperCase();
        if (mode === 'lower') return value.toLocaleLowerCase();
        if (mode === 'title') return value.toLocaleLowerCase().replace(/(^|[\s_-])([a-z])/g, function (match, prefix, letter) { return prefix + letter.toLocaleUpperCase(); });
        return value;
      }
      function resolve(value, custom) {
        return value.replace(/\{\{\s*(utm_source|utm_medium|utm_campaign|utm_term|utm_content|utm_match_type|utm_matchtype|session_id|visitor_id|browser_id|custom)\s*\}\}|\{\s*(utm_source|utm_medium|utm_campaign|utm_term|utm_content|utm_match_type|utm_matchtype|session_id|visitor_id|browser_id|custom)\s*\}/gi, function (token, doubleKey, singleKey) {
          var key = (doubleKey || singleKey).toLowerCase();
          if (key === 'browser_id') key = 'visitor_id';
          if (key === 'utm_matchtype') key = 'utm_match_type';
          return key === 'custom' ? custom : format(sample[key] || '');
        });
      }
      message = resolve(message, sample.custom).trim();
      var parts = $('input[name="source_fields[]"]:checked').map(function () { return format(sample[this.value] || ''); }).get();
      var label = ($('#utr-source-label').val() || 'Sumber').trim().replace(/[\s:]+$/, '') || 'Sumber';
      var line = parts.length ? label + ': ' + parts.join($('#utr-source-separator').val()) : '';
      $('#utr-account-message-preview').text(message + (message && line ? '\n\n' : '') + line);
      var defaultMessage = resolve(($('#utr-global-message').val() || '').trim(), '').trim();
      $('#utr-global-message-preview').text(defaultMessage + (defaultMessage && line ? '\n\n' : '') + line);
      $('#utr-panel-title-preview').text($('#utr-panel-title').val() || '');
      $('#utr-greeting-preview').text($('#utr-greeting').val() || '');
      var fallbackEnabled = $('#utr-fallback-enabled').is(':checked');
      var fallbackMode = $('input[name="fallback_url_mode"]:checked').val();
      $('#utr-fallback-options').toggle(fallbackEnabled);
      $('#utr-fallback-custom-url').closest('label').toggle(fallbackMode === 'custom');
      $('#utr-fallback-custom-url').prop('disabled', !(fallbackEnabled && fallbackMode === 'custom'));
      var fallbackUrl = fallbackMode === 'custom' ? ($('#utr-fallback-custom-url').val().trim() || 'https://example.com/halaman/') : 'https://example.com/halaman/';
      var fallbackLabel = ($('#utr-fallback-label').val() || 'Sumber').trim().replace(/[\s:]+$/, '') || 'Sumber';
      var fallbackIds = $('input[name="fallback_id_fields[]"]:checked').map(function () { return format(sample[this.value] || ''); }).get();
      var fallbackLine = fallbackEnabled ? fallbackLabel + ': ' + [fallbackUrl].concat(fallbackIds).join($('#utr-source-separator').val()) : '';
      $('#utr-fallback-message-preview').text(fallbackEnabled ? message + (message ? '\n\n' : '') + fallbackLine : 'Baris sumber tidak ditambahkan.');
    }
    $('#utr-account-message, #utr-global-message, #utr-panel-title, #utr-greeting, #utr-message-custom, #utr-source-label, #utr-source-separator, #utr-fallback-label, #utr-fallback-custom-url').on('input', updatePreview);
    $('#utr-message-variable-insert').on('click', function () {
      var key = $('#utr-message-variable').val();
      var field = document.getElementById('utr-account-message');
      if (!key || !field) return;
      var token = '{' + key + '}';
      var start = field.selectionStart;
      var end = field.selectionEnd;
      if (field.value.length - (end - start) + token.length > field.maxLength) return;
      field.focus();
      if (typeof field.setRangeText === 'function') field.setRangeText(token, start, end, 'end');
      else field.value = field.value.slice(0, start) + token + field.value.slice(end);
      $(field).trigger('input');
    });
    $('#utr-source-case, input[name="source_fields[]"], input[name="fallback_id_fields[]"], #utr-fallback-enabled, input[name="fallback_url_mode"]').on('change', updatePreview);
    updatePreview();

    function updateFloatingPreview() {
      var preview = document.getElementById('utr-floating-preview-launcher');
      if (!preview) return;
      preview.style.setProperty('--utr-launcher-bg', $('#utr-floating-bg').val());
      preview.style.setProperty('--utr-launcher-text', $('#utr-floating-text').val());
      preview.style.setProperty('--utr-launcher-accent', $('#utr-floating-accent').val());
      var size = Number($('#utr-floating-size').val()) || 100;
      preview.style.setProperty('--utr-launcher-scale', String(size / 100));
      $('#utr-floating-size-value').text(size + '%');
    }
    $('#utr-floating-bg, #utr-floating-text, #utr-floating-accent, #utr-floating-size').on('input change', updateFloatingPreview);
    updateFloatingPreview();

    $('#utr-account-sortable').on('change', '.utr-floating-account-toggle', function () {
      var checkbox = $(this);
      var enabled = checkbox.is(':checked');
      var row = checkbox.closest('tr');
      var text = row.find('.utr-table-toggle-text');
      checkbox.prop('disabled', true);
      $('#utr-order-status').text('Menyimpan tampilan akun...');
      $.post(window.utrWaAdmin.ajaxUrl, {action: 'utr_wa_toggle_floating_account', _ajax_nonce: window.utrWaAdmin.nonce, account_id: row.data('accountId'), enabled: enabled ? '1' : '0'}).done(function (response) {
        if (response && response.success) {
          text.text(enabled ? 'Aktif' : 'Nonaktif');
          $('#utr-order-status').text('Tampilan akun tersimpan.');
        } else {
          checkbox.prop('checked', !enabled);
          $('#utr-order-status').text('Gagal menyimpan tampilan akun. Coba lagi.');
        }
      }).fail(function () {
        checkbox.prop('checked', !enabled);
        $('#utr-order-status').text('Gagal menyimpan tampilan akun. Coba lagi.');
      }).always(function () { checkbox.prop('disabled', false); });
    });

    var list = $('#utr-account-sortable');
    if (list.length && window.utrWaAdmin) {
      var accountTable = list.closest('table');
      accountTable.on('utr:sortchange', function () {
        var sorted = accountTable.attr('data-utr-sorted') === '1';
        list.find('.utr-drag-handle, .utr-move-up, .utr-move-down').prop('disabled', sorted);
        if (list.data('ui-sortable')) list.sortable(sorted ? 'disable' : 'enable');
        $('#utr-order-status').text(sorted ? 'Tabel diurutkan sementara. Klik judul kolom lagi sampai urutan prioritas kembali.' : 'Urutan prioritas akun ditampilkan.');
      });
      function orderIds() { return list.children('tr').map(function () { return String($(this).data('accountId')); }).get(); }
      var lastSaved = orderIds();
      var saving = false;
      function restoreOrder() {
        lastSaved.forEach(function (id) { list.append(list.children('tr[data-account-id="' + id + '"]')); });
        $('#utr-order-status').text('Gagal menyimpan urutan. Coba lagi.');
      }
      function saveOrder() {
        if (accountTable.attr('data-utr-sorted') === '1') return;
        if (saving) return;
        var order = orderIds();
        saving = true;
        if (list.data('ui-sortable')) list.sortable('disable');
        $('#utr-order-status').text('Menyimpan urutan...');
        $.post(window.utrWaAdmin.ajaxUrl, {action: 'utr_wa_reorder', _ajax_nonce: window.utrWaAdmin.nonce, order: order}).done(function (response) {
          if (response && response.success) { lastSaved = order; accountTable[0].dispatchEvent(new CustomEvent('utr:orderchange')); $('#utr-order-status').text('Urutan akun tersimpan.'); }
          else restoreOrder();
        }).fail(restoreOrder).always(function () { saving = false; if (list.data('ui-sortable') && accountTable.attr('data-utr-sorted') !== '1') list.sortable('enable'); });
      }
      list.find('.utr-account-name').each(function () {
        var controls = $('<span class="utr-order-controls" aria-label="Ubah prioritas akun"></span>');
        $('<button type="button" class="utr-move-up" aria-label="Naikkan prioritas">↑</button>').appendTo(controls);
        $('<button type="button" class="utr-move-down" aria-label="Turunkan prioritas">↓</button>').appendTo(controls);
        $(this).append(controls);
      });
      list.on('click', '.utr-move-up, .utr-move-down', function () {
        if (accountTable.attr('data-utr-sorted') === '1') return;
        if (saving) return;
        var row = $(this).closest('tr');
        var sibling = $(this).hasClass('utr-move-up') ? row.prev('tr') : row.next('tr');
        if (!sibling.length) return;
        if ($(this).hasClass('utr-move-up')) row.insertBefore(sibling);
        else row.insertAfter(sibling);
        $(this).focus();
        saveOrder();
      });
      list.on('keydown', '.utr-drag-handle', function (event) {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
        event.preventDefault();
        if (saving) return;
        var row = $(this).closest('tr');
        var sibling = event.key === 'ArrowUp' ? row.prev('tr') : row.next('tr');
        if (!sibling.length) return;
        if (event.key === 'ArrowUp') row.insertBefore(sibling);
        else row.insertAfter(sibling);
        $(this).focus();
        saveOrder();
      });
      if ($.fn.sortable) {
        list.sortable({
          handle: '.utr-drag-handle',
          cancel: 'a, input, textarea, select, option',
          axis: 'y',
          items: '> tr',
          helper: function (event, row) {
            var cells = row.children();
            var clone = row.clone();
            clone.children().each(function (index) { $(this).width(cells.eq(index).width()); });
            return clone;
          },
          update: saveOrder
        });
      }
    }
  });
}(jQuery));
