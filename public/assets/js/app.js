/* =====================================================================
   نظام الأرشفة الإلكترونية — سكربت الواجهة
   ===================================================================== */
(function () {
  'use strict';

  // ------------------------- القائمة الجانبية -------------------------
  const sidebar  = document.getElementById('sidebar');
  const backdrop = document.getElementById('backdrop');
  document.querySelectorAll('[data-toggle-sidebar]').forEach(function (b) {
    b.addEventListener('click', function () {
      sidebar && sidebar.classList.toggle('open');
      backdrop && backdrop.classList.toggle('show');
    });
  });
  backdrop && backdrop.addEventListener('click', function () {
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
  });

  // ----------------------------- النوافذ -----------------------------
  window.openModal = function (id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
  };
  window.closeModal = function (id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
  };
  document.querySelectorAll('[data-modal]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      const target = el.getAttribute('data-modal');
      // تعبئة الحقول من سمات data-set-*
      const box = document.getElementById(target);
      if (box) {
        Array.from(el.attributes).forEach(function (a) {
          if (a.name.indexOf('data-set-') === 0) {
            const field = box.querySelector('[name="' + a.name.replace('data-set-', '') + '"]');
            if (field) {
              if (field.type === 'checkbox') { field.checked = a.value === '1'; }
              else { field.value = a.value; }
            }
          }
        });
      }
      openModal(target);
    });
  });
  document.querySelectorAll('.modal').forEach(function (m) {
    m.addEventListener('click', function (e) {
      if (e.target === m) { closeModal(m.id); }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.open').forEach(function (m) { closeModal(m.id); });
    }
  });

  // --------------------------- تأكيد الحذف ---------------------------
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });

  // ------------------------- القوائم المنسدلة -------------------------
  document.querySelectorAll('[data-dropdown]').forEach(function (t) {
    t.addEventListener('click', function (e) {
      e.stopPropagation();
      const parent = t.closest('.dropdown');
      const wasOpen = parent.classList.contains('open');
      document.querySelectorAll('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
      if (!wasOpen) { parent.classList.add('open'); }
    });
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
  });

  // ------------------------------ التبويبات ---------------------------
  document.querySelectorAll('[data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const group = btn.closest('.tabs');
      const name  = btn.getAttribute('data-tab');
      group.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      document.querySelectorAll('[data-tab-panel]').forEach(function (p) {
        if (p.closest('.tab-scope') === group.closest('.tab-scope')) {
          p.classList.toggle('hide', p.getAttribute('data-tab-panel') !== name);
        }
      });
    });
  });

  // ---------------------------- شجرة الأرشفة --------------------------
  document.querySelectorAll('.tree .toggle').forEach(function (t) {
    t.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const li = t.closest('li');
      li.classList.toggle('collapsed');
      t.textContent = li.classList.contains('collapsed') ? '▶' : '▼';
      try {
        const key = 'folder-collapsed-' + (li.dataset.id || '');
        li.classList.contains('collapsed')
          ? localStorage.setItem(key, '1')
          : localStorage.removeItem(key);
      } catch (err) { /* التخزين غير متاح */ }
    });
  });
  try {
    document.querySelectorAll('.tree li[data-id]').forEach(function (li) {
      if (localStorage.getItem('folder-collapsed-' + li.dataset.id) === '1') {
        li.classList.add('collapsed');
        const t = li.querySelector(':scope > .node .toggle');
        if (t) { t.textContent = '▶'; }
      }
    });
  } catch (err) { /* تجاهل */ }

  // ------------------------ صفوف المرفقات الديناميكية ------------------------
  window.addFileRow = function () {
    const box = document.getElementById('file-rows');
    if (!box) { return; }
    const row = document.createElement('div');
    row.className = 'flex mb-sm';
    row.innerHTML =
      '<input type="file" name="attachments[]" style="flex:2">' +
      '<input type="text" name="attachment_titles[]" placeholder="وصف المرفق (اختياري)" style="flex:1">' +
      '<button type="button" class="btn outline sm" onclick="this.parentNode.remove()">حذف</button>';
    box.appendChild(row);
  };

  window.addDriveRow = function () {
    const box = document.getElementById('drive-rows');
    if (!box) { return; }
    const row = document.createElement('div');
    row.className = 'flex mb-sm';
    row.innerHTML =
      '<input type="url" name="drive_urls[]" placeholder="https://drive.google.com/file/d/..." style="flex:2" dir="ltr">' +
      '<input type="text" name="drive_titles[]" placeholder="اسم الملف" style="flex:1">' +
      '<select name="drive_groups[]" style="flex:0 0 110px">' +
        '<option value="document">مستند</option><option value="image">صورة</option>' +
        '<option value="video">فيديو</option><option value="audio">صوت</option>' +
        '<option value="archive">مضغوط</option><option value="other">أخرى</option>' +
      '</select>' +
      '<button type="button" class="btn outline sm" onclick="this.parentNode.remove()">حذف</button>';
    box.appendChild(row);
  };

  // -------------------------- اقتراحات البحث --------------------------
  const searchInput = document.getElementById('global-search');
  const suggestBox  = document.getElementById('search-suggest');
  if (searchInput && suggestBox) {
    let timer = null;
    searchInput.addEventListener('input', function () {
      clearTimeout(timer);
      const q = searchInput.value.trim();
      if (q.length < 2) { suggestBox.classList.remove('open'); return; }
      timer = setTimeout(function () {
        fetch('index.php?r=search/suggest&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (items) {
            if (!items.length) { suggestBox.classList.remove('open'); return; }
            const menu = suggestBox.querySelector('.dropdown-menu');
            menu.innerHTML = items.map(function (i) {
              return '<a href="' + i.url + '"><span class="badge muted">' + i.type + '</span> ' + i.label + '</a>';
            }).join('');
            suggestBox.classList.add('open');
          })
          .catch(function () { suggestBox.classList.remove('open'); });
      }, 280);
    });
    document.addEventListener('click', function (e) {
      if (!suggestBox.contains(e.target) && e.target !== searchInput) {
        suggestBox.classList.remove('open');
      }
    });
  }

  // ---------------------- تحديث عدّاد الإشعارات ----------------------
  const badge = document.getElementById('notif-count');
  if (badge) {
    setInterval(function () {
      fetch('index.php?r=notifications/count')
        .then(function (r) { return r.json(); })
        .then(function (d) {
          badge.textContent = d.count > 99 ? '99+' : d.count;
          badge.style.display = d.count > 0 ? 'grid' : 'none';
        })
        .catch(function () { /* تجاهل */ });
    }, 60000);
  }

  // --------------------------- نسخ إلى الحافظة ---------------------------
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      const text = b.getAttribute('data-copy');
      const done = function () {
        const original = b.textContent;
        b.textContent = 'تم النسخ ✓';
        setTimeout(function () { b.textContent = original; }, 1600);
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(done).catch(function () { fallback(text, done); });
      } else { fallback(text, done); }
    });
  });
  function fallback(text, cb) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); cb(); } catch (e) { /* تجاهل */ }
    document.body.removeChild(ta);
  }

  // ------------------------ إخفاء التنبيهات تلقائياً ------------------------
  document.querySelectorAll('.alert.success').forEach(function (a) {
    setTimeout(function () {
      a.style.transition = 'opacity .4s';
      a.style.opacity = '0';
      setTimeout(function () { a.remove(); }, 420);
    }, 6000);
  });

  // ---------------------- منع الإرسال المزدوج للنماذج ----------------------
  document.querySelectorAll('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function () {
      const btn = f.querySelector('button[type=submit]');
      if (btn) { setTimeout(function () { btn.disabled = true; btn.textContent = 'جاري الحفظ...'; }, 10); }
    });
  });

  // ------------------ تحديد الكل في جداول الصلاحيات ------------------
  document.querySelectorAll('[data-check-all]').forEach(function (master) {
    master.addEventListener('change', function () {
      const scope = document.querySelector(master.getAttribute('data-check-all'));
      if (!scope) { return; }
      scope.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = master.checked; });
    });
  });
})();
