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
});
