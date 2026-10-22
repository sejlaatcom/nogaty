/* محرر السؤال: تبديل النوع، تحميل التصنيفات، إدارة الخيارات، إدراج الرموز، والمعاينة */
(function () {
  'use strict';
  var form = document.getElementById('qform');
  if (!form) { return; }
  var typeSel = document.getElementById('f-type');
  var box = document.getElementById('options');
  var subject = document.getElementById('f-subject');
  var grade = document.getElementById('f-grade');
  var skillSel = document.getElementById('f-skill');
  var indSel = document.getElementById('f-indicator');
  var passSel = document.getElementById('f-passage');
  var tree = [];

  /* ---------- إظهار الأجزاء حسب النوع ---------- */
  function applyType() {
    var t = typeSel.value;
    box.dataset.type = t;
    document.querySelectorAll('[data-for]').forEach(function (el) {
      el.classList.toggle('d-none', el.dataset.for.split(' ').indexOf(t) === -1);
    });
    document.querySelectorAll('[data-label-for]').forEach(function (el) {
      el.classList.toggle('d-none', el.dataset.labelFor !== t);
    });
    box.querySelectorAll('.c-single').forEach(function (el) { el.classList.toggle('d-none', t !== 'single'); });
    box.querySelectorAll('.c-multi').forEach(function (el) { el.classList.toggle('d-none', t !== 'multiple'); });
    box.querySelectorAll('.o-match').forEach(function (el) { el.classList.toggle('d-none', t !== 'matching'); });
    box.querySelectorAll('.o-feedback').forEach(function (el) { el.classList.toggle('d-none', t !== 'single' && t !== 'multiple'); });
    box.querySelectorAll('.o-body').forEach(function (el) { el.placeholder = t === 'matching' ? 'العنصر' : (t === 'ordering' ? 'عنصر' : 'نص الخيار'); });
  }

  /* ---------- إعادة ترقيم الصفوف بعد الإضافة/الحذف/النقل ---------- */
  function renumber() {
    Array.prototype.forEach.call(box.querySelectorAll('.opt-row'), function (row, i) {
      row.querySelector('.num').textContent = i + 1;
      row.querySelector('.c-single').value = i;
      row.querySelector('.c-multi').value = i;
      row.querySelector('.o-body').name = 'opt[' + i + '][body]';
      row.querySelector('.o-match').name = 'opt[' + i + '][match]';
      row.querySelector('.o-feedback').name = 'opt[' + i + '][feedback]';
    });
  }

  document.getElementById('add-opt').addEventListener('click', function () {
    var rows = box.querySelectorAll('.opt-row');
    if (rows.length >= 8) { return; }
    var clone = rows[0].cloneNode(true);
    clone.querySelectorAll('input[type=text],input:not([type])').forEach(function (i) { i.value = ''; });
    clone.querySelectorAll('input[type=radio],input[type=checkbox]').forEach(function (i) { i.checked = false; });
    box.appendChild(clone);
    renumber();
    applyType();
  });
  box.addEventListener('click', function (e) {
    var btn = e.target.closest('button');
    if (!btn) { return; }
    var row = btn.closest('.opt-row');
    if (btn.hasAttribute('data-opt-remove')) {
      if (box.querySelectorAll('.opt-row').length > 2) { row.remove(); renumber(); }
    } else if (btn.dataset.optMove) {
      var dir = parseInt(btn.dataset.optMove, 10);
      var sib = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
      if (sib) { dir < 0 ? box.insertBefore(row, sib) : box.insertBefore(sib, row); renumber(); }
    }
  });

  /* ---------- إدراج الرموز في النص ---------- */
  document.querySelectorAll('[data-insert]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.closest('[data-insert-target]').dataset.insertTarget);
      var s = target.selectionStart, v = target.value, ins = btn.dataset.insert;
      target.value = v.slice(0, s) + ins + v.slice(target.selectionEnd);
      target.focus();
      target.selectionStart = target.selectionEnd = s + ins.length;
    });
  });

  /* ---------- التصنيفات المتسلسلة ---------- */
  function fillSkills(keepSkill, keepInd) {
    skillSel.innerHTML = '<option value="">— بدون مهارة —</option>';
    tree.forEach(function (d) {
      var g = document.createElement('optgroup');
      g.label = d.name;
      d.skills.forEach(function (s) {
        var o = new Option((s.code ? s.code + ' — ' : '') + s.name, s.id);
        if (String(s.id) === String(keepSkill)) { o.selected = true; }
        g.appendChild(o);
      });
      skillSel.appendChild(g);
    });
    fillIndicators(keepInd);
  }
  function fillIndicators(keep) {
    indSel.innerHTML = '<option value="">— بدون —</option>';
    tree.forEach(function (d) {
      d.skills.forEach(function (s) {
        if (String(s.id) !== skillSel.value) { return; }
        s.indicators.forEach(function (i) {
          var o = new Option((i.code ? i.code + ' ' : '') + i.description, i.id);
          if (String(i.id) === String(keep)) { o.selected = true; }
          indSel.appendChild(o);
        });
      });
    });
  }
  function loadTree(first) {
    Nafes.get('/taxonomy/tree?subject=' + subject.value + '&grade=' + grade.value).then(function (j) {
      tree = j.domains || [];
      fillSkills(first ? form.dataset.skill : '', first ? form.dataset.indicator : '');
      passSel.innerHTML = '<option value="">— بدون —</option>';
      (j.passages || []).forEach(function (p) {
        var o = new Option(p.title, p.id);
        if (first && String(p.id) === form.dataset.passage) { o.selected = true; }
        passSel.appendChild(o);
      });
    });
  }
  subject.addEventListener('change', function () { loadTree(false); });
  grade.addEventListener('change', function () { loadTree(false); });
  skillSel.addEventListener('change', function () { fillIndicators(''); });

  /* ---------- المعاينة ---------- */
  document.getElementById('preview-btn').addEventListener('click', function () {
    var data = new FormData(form);
    data.delete('media[]');
    Nafes.post('/questions/preview', data).then(function (j) {
      document.getElementById('pv-student').innerHTML = j.student || '';
      document.getElementById('pv-teacher').innerHTML = j.teacher || '';
      new bootstrap.Modal(document.getElementById('previewModal')).show();
    });
  });

  typeSel.addEventListener('change', applyType);
  applyType();
  loadTree(true);
})();
