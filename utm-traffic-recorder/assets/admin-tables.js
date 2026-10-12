(function () {
  'use strict';

  function setup(table) {
    var head = table.tHead && table.tHead.rows[0];
    var body = table.tBodies[0];
    if (!head || !body || body.rows.length < 2) return;

    var rows = Array.prototype.slice.call(body.rows);
    var original = new WeakMap();
    rows.forEach(function (row, index) { original.set(row, index); });
    var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
    var accountTable = table.classList.contains('utr-account-table');
    var state = {column: -1, direction: ''};
    var mobileSelect;
    var mobileDirection;
    if (accountTable) table.addEventListener('utr:orderchange', function () {
      rows = Array.prototype.slice.call(body.rows);
      rows.forEach(function (row, index) { original.set(row, index); });
    });

    function sortableValue(row, index) {
      var cell = row.cells[index];
      if (!cell) return '';
      return (cell.getAttribute('data-sort') || cell.textContent || '').trim();
    }

    function update(column, direction) {
      state.column = column;
      state.direction = direction;
      var sorted = rows.slice();
      if (direction) {
        var label = head.cells[column].textContent.trim();
        var dateColumn = label === 'Waktu' || label === 'Aktivitas terakhir';
        sorted.sort(function (a, b) {
          if (dateColumn) {
            return direction === 'asc' ? original.get(b) - original.get(a) : original.get(a) - original.get(b);
          }
          var first = sortableValue(a, column);
          var second = sortableValue(b, column);
          var compared = collator.compare(first, second);
          if (!compared) compared = original.get(a) - original.get(b);
          return direction === 'asc' ? compared : -compared;
        });
      }
      sorted.forEach(function (row) { body.appendChild(row); });
      Array.prototype.forEach.call(head.cells, function (cell, index) {
        cell.setAttribute('aria-sort', direction && index === column ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');
        var button = cell.querySelector('.utr-sort-button');
        if (button) button.setAttribute('title', direction && index === column ? 'Klik lagi untuk mengubah urutan' : 'Urutkan kolom ' + cell.textContent.trim());
      });
      if (accountTable) {
        table.setAttribute('data-utr-sorted', direction ? '1' : '0');
        table.dispatchEvent(new CustomEvent('utr:sortchange', {detail: {sorted: !!direction}}));
      }
      if (mobileSelect) {
        mobileSelect.value = direction ? String(column) : '';
        mobileDirection.textContent = direction === 'asc' ? 'Naik ↑' : direction === 'desc' ? 'Turun ↓' : 'Urutan awal';
      }
    }

    var mobileOptions = [];
    Array.prototype.forEach.call(head.cells, function (cell, index) {
      var label = cell.textContent.trim();
      if (!label || (accountTable && (label === 'Avatar' || label === 'Aksi'))) return;
      mobileOptions.push({index: index, label: label});
      cell.setAttribute('aria-sort', 'none');
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'utr-sort-button';
      button.textContent = label;
      button.setAttribute('aria-label', 'Urutkan kolom ' + label);
      var icon = document.createElement('span');
      icon.className = 'utr-sort-icon';
      icon.setAttribute('aria-hidden', 'true');
      button.appendChild(icon);
      cell.textContent = '';
      cell.appendChild(button);
      button.addEventListener('click', function () {
        var next = state.column !== index ? 'asc' : state.direction === 'asc' ? 'desc' : state.direction === 'desc' ? '' : 'asc';
        update(index, next);
      });
    });

    if (mobileOptions.length) {
      var mobile = document.createElement('div');
      mobile.className = 'utr-mobile-sort';
      var mobileLabel = document.createElement('label');
      mobileLabel.textContent = 'Urutkan';
      mobileSelect = document.createElement('select');
      var initial = document.createElement('option');
      initial.value = '';
      initial.textContent = 'Urutan awal';
      mobileSelect.appendChild(initial);
      mobileOptions.forEach(function (item) {
        var option = document.createElement('option');
        option.value = String(item.index);
        option.textContent = item.label;
        mobileSelect.appendChild(option);
      });
      mobileLabel.appendChild(mobileSelect);
      mobile.appendChild(mobileLabel);
      mobileDirection = document.createElement('button');
      mobileDirection.type = 'button';
      mobileDirection.className = 'button';
      mobileDirection.textContent = 'Urutan awal';
      mobile.appendChild(mobileDirection);
      mobileSelect.addEventListener('change', function () {
        update(this.value === '' ? -1 : Number(this.value), this.value === '' ? '' : 'asc');
      });
      mobileDirection.addEventListener('click', function () {
        if (state.column < 0) return;
        update(state.column, state.direction === 'asc' ? 'desc' : state.direction === 'desc' ? '' : 'asc');
      });
      table.parentNode.insertBefore(mobile, table);
    }
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.utr-admin table.utr-table'), setup);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
