/* نصوص مساعدة بسيطة للنظام */
document.addEventListener('DOMContentLoaded', function () {
  // تأكيد قبل الحذف أو الإجراءات الحساسة
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // إرسال النموذج تلقائياً عند تغيير الفلاتر
  document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
    el.addEventListener('change', function () {
      if (el.form) { el.form.submit(); }
    });
  });

  // تحديد الكل في جداول الاعتماد الجماعي
  document.querySelectorAll('[data-check-all]').forEach(function (master) {
    master.addEventListener('change', function () {
      var name = master.getAttribute('data-check-all');
      document.querySelectorAll('input[name="' + name + '"]').forEach(function (box) {
        box.checked = master.checked;
      });
    });
  });

  // توسيع مربعات النص تلقائياً
  document.querySelectorAll('textarea[data-grow]').forEach(function (area) {
    var grow = function () {
      area.style.height = 'auto';
      area.style.height = (area.scrollHeight + 2) + 'px';
    };
    area.addEventListener('input', grow);
    grow();
  });

  // زر الطباعة
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function (e) { e.preventDefault(); window.print(); });
  });

  // حفظ تلقائي للمسودة كل 60 ثانية (محرر الخطة)
  var planForm = document.getElementById('plan-form');
  if (planForm && planForm.dataset.autosave === '1') {
    var status = document.getElementById('autosave-status');
    setInterval(function () {
      var data = new FormData(planForm);
      data.set('action', 'save');
      data.set('ajax', '1');
      fetch(planForm.action, { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (status && j && j.ok) {
            var now = new Date();
            status.textContent = 'تم الحفظ التلقائي ' + now.toLocaleTimeString('ar-SA');
          }
        })
        .catch(function () {});
    }, 60000);
  }
  /* ---------------- منصة نافس: محرر الأسئلة ---------------- */
  var qSubject = document.getElementById('q-subject');
  var qGrade   = document.getElementById('q-grade');
  var filterBySubject = function () {
    var subject = qSubject ? qSubject.value : '0';
    var grade   = qGrade ? qGrade.value : '0';
    var matches = function (el) {
      var s = el.getAttribute('data-subject'), g = el.getAttribute('data-grade');
      return (subject === '0' || s === subject) && (grade === '0' || g === '0' || g === grade);
    };
    document.querySelectorAll('#q-skill optgroup, #q-passage option[data-subject]').forEach(function (el) {
      var ok = matches(el);
      el.hidden = !ok;
      el.disabled = !ok;
    });
    ['q-skill', 'q-passage'].forEach(function (id) {
      var select = document.getElementById(id);
      if (select && select.selectedOptions.length && select.selectedOptions[0].disabled) { select.value = '0'; }
    });
  };
  if (qSubject) {
    qSubject.addEventListener('change', filterBySubject);
    if (qGrade) { qGrade.addEventListener('change', filterBySubject); }
    filterBySubject();
  }

  var typeRadios = document.querySelectorAll('[data-qtype]');
  var applyType = function () {
    var checked = document.querySelector('[data-qtype]:checked');
    var type = checked ? checked.value : 'mcq';
    document.querySelectorAll('[data-type-block]').forEach(function (block) {
      block.classList.toggle('hidden', block.getAttribute('data-type-block') !== type);
    });
  };
  typeRadios.forEach(function (r) { r.addEventListener('change', applyType); });
  if (typeRadios.length) { applyType(); }

  var addOption = document.getElementById('add-option');
  if (addOption) {
    var letters = ['أ', 'ب', 'ج', 'د', 'هـ', 'و'];
    addOption.addEventListener('click', function () {
      var list = document.getElementById('options-list');
      var count = list.querySelectorAll('.option-row').length;
      if (count >= letters.length) { return; }
      var row = list.querySelector('.option-row').cloneNode(true);
      row.querySelector('input[type=radio]').value = count;
      row.querySelector('input[type=radio]').checked = false;
      row.querySelector('input[type=text]').name = 'options[' + count + ']';
      row.querySelector('input[type=text]').value = '';
      row.querySelector('.opt-letter').textContent = letters[count];
      list.appendChild(row);
    });
  }
});
