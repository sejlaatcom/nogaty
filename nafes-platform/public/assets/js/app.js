/* منصة نافس — سلوكيات الواجهة العامة */
(function () {
  'use strict';

  var meta = function (name) { var m = document.querySelector('meta[name="' + name + '"]'); return m ? m.content : ''; };
  window.Nafes = {
    csrf: meta('csrf-token'),
    base: meta('base-url'),
    url: function (path) { return (this.base || '') + path; },
    post: function (path, data) {
      var body = data instanceof FormData ? data : new URLSearchParams(data || {});
      if (body.append && !body.has('_token')) { body.append('_token', this.csrf); }
      return fetch(this.url(path), {
        method: 'POST', body: body, credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': this.csrf }
      }).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
    },
    get: function (path) {
      return fetch(this.url(path), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;

    /* ---------- القائمة الجانبية: طي على الحاسب، وفتح منزلق على الجوال ---------- */
    var isMobile = function () { return window.matchMedia('(max-width: 991.98px)').matches; };
    try { if (localStorage.getItem('nafes.sidebar') === 'collapsed' && !isMobile()) { body.classList.add('sidebar-collapsed'); } } catch (e) {}
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (isMobile()) { body.classList.toggle('sidebar-open'); return; }
        body.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('nafes.sidebar', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'); } catch (e) {}
      });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
      el.addEventListener('click', function () { body.classList.remove('sidebar-open'); });
    });

    /* ---------- نافذة تأكيد العمليات الحساسة ---------- */
    var modalEl = document.getElementById('confirmModal');
    var modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
    var pending = null;
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-confirm]');
      if (!el || el.dataset.confirmed === '1') { return; }
      e.preventDefault();
      var text = el.getAttribute('data-confirm');
      if (!modal) { if (window.confirm(text)) { proceed(el); } return; }
      modalEl.querySelector('[data-confirm-text]').textContent = text;
      pending = el;
      modal.show();
    });
    if (modalEl) {
      modalEl.querySelector('[data-confirm-ok]').addEventListener('click', function () {
        modal.hide();
        if (pending) { proceed(pending); pending = null; }
      });
    }
    function proceed(el) {
      el.dataset.confirmed = '1';
      if (el.tagName === 'A') { window.location = el.href; return; }
      if (el.form) {
        if (el.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = el.name; h.value = el.value; el.form.appendChild(h); }
        if (el.getAttribute('formaction')) { el.form.action = el.getAttribute('formaction'); }
        el.form.submit();
        return;
      }
      el.click();
    }

    /* ---------- منع الإرسال المزدوج للنماذج ---------- */
    document.querySelectorAll('form[data-once]').forEach(function (form) {
      form.addEventListener('submit', function () {
        form.querySelectorAll('button[type=submit],button:not([type])').forEach(function (b) { b.disabled = true; });
      });
    });

    /* ---------- عناصر مساعدة ---------- */
    document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
      el.addEventListener('change', function () { if (el.form) { el.form.submit(); } });
    });
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
      master.addEventListener('change', function () {
        document.querySelectorAll('input[name="' + master.dataset.checkAll + '"]').forEach(function (b) {
          if (!b.closest('tr') || b.closest('tr').style.display !== 'none') { b.checked = master.checked; }
        });
      });
    });

    /* ---------- الجداول: بحث فوري وفرز وتصفية ---------- */
    document.querySelectorAll('table[data-table]').forEach(function (table) {
      var tbody = table.tBodies[0];
      if (!tbody) { return; }
      var tools = document.querySelector('[data-table-tools="' + table.id + '"]');
      var search = tools ? tools.querySelector('[data-table-search]') : null;
      var filters = tools ? tools.querySelectorAll('[data-table-filter]') : [];
      var counter = tools ? tools.querySelector('[data-table-count]') : null;

      var apply = function () {
        var q = search ? search.value.trim().toLowerCase() : '';
        var shown = 0;
        Array.prototype.forEach.call(tbody.rows, function (row) {
          if (row.classList.contains('empty-row')) { return; }
          var ok = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
          filters.forEach(function (f) {
            if (f.value !== '' && ok) {
              var cell = row.cells[parseInt(f.dataset.tableFilter, 10)];
              ok = cell && (cell.dataset.value || cell.textContent).trim() === f.value;
            }
          });
          row.style.display = ok ? '' : 'none';
          if (ok) { shown++; }
        });
        if (counter) { counter.textContent = shown; }
      };
      if (search) { search.addEventListener('input', apply); }
      filters.forEach(function (f) { f.addEventListener('change', apply); });

      Array.prototype.forEach.call(table.tHead ? table.tHead.rows[0].cells : [], function (th, index) {
        if (th.dataset.sort === 'false' || th.querySelector('input')) { return; }
        th.classList.add('sortable');
        th.addEventListener('click', function () {
          var asc = !th.classList.contains('sort-asc');
          table.querySelectorAll('th').forEach(function (h) { h.classList.remove('sort-asc', 'sort-desc'); });
          th.classList.add(asc ? 'sort-asc' : 'sort-desc');
          var rows = Array.prototype.slice.call(tbody.rows).filter(function (r) { return !r.classList.contains('empty-row'); });
          rows.sort(function (a, b) {
            var x = a.cells[index] ? (a.cells[index].dataset.value || a.cells[index].textContent).trim() : '';
            var y = b.cells[index] ? (b.cells[index].dataset.value || b.cells[index].textContent).trim() : '';
            var nx = parseFloat(x.replace(/[^\d.\-]/g, '')), ny = parseFloat(y.replace(/[^\d.\-]/g, ''));
            var cmp = (!isNaN(nx) && !isNaN(ny) && /^[\d.\-\s٪%\/]+$/.test(x) && /^[\d.\-\s٪%\/]+$/.test(y)) ? nx - ny : x.localeCompare(y, 'ar');
            return asc ? cmp : -cmp;
          });
          rows.forEach(function (r) { tbody.appendChild(r); });
        });
      });
    });

    /* ---------- الإشعارات ---------- */
    var countEl = document.querySelector('[data-notif-count]');
    var listEl = document.querySelector('[data-notif-list]');
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    var loadNotifications = function () {
      if (!countEl) { return; }
      Nafes.get('/notifications/feed').then(function (j) {
        if (!j || !j.ok) { return; }
        countEl.textContent = j.unread > 99 ? '99+' : j.unread;
        countEl.classList.toggle('d-none', j.unread === 0);
        if (!j.items.length) { listEl.innerHTML = '<div class="p-3 text-muted small">لا توجد إشعارات.</div>'; return; }
        listEl.innerHTML = j.items.map(function (n) {
          return '<a class="item ' + (n.is_read ? '' : 'unread') + '" href="' + esc(Nafes.url('/notifications/' + n.id + '/open')) + '">' +
            '<strong class="d-block">' + esc(n.title) + '</strong>' + (n.body ? '<span>' + esc(n.body) + '</span><br>' : '') +
            '<small>' + esc(n.ago) + '</small></a>';
        }).join('');
      }).catch(function () {});
    };
    loadNotifications();
    if (countEl) { setInterval(loadNotifications, 90000); }

    /* ---------- معاينة الصور قبل الرفع ---------- */
    document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
      input.addEventListener('change', function () {
        var img = document.querySelector(input.dataset.preview);
        if (img && input.files && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.classList.remove('d-none'); }
      });
    });
  });
})();
