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
  /* ---------------- منصة نافس: فصول الاختبار حسب الصف ---------------- */
  var testGrade = document.getElementById('test-grade');
  if (testGrade) {
    var filterClasses = function () {
      document.querySelectorAll('#test-classes [data-grade]').forEach(function (chip) {
        var ok = chip.getAttribute('data-grade') === testGrade.value;
        chip.classList.toggle('hidden', !ok);
        if (!ok) { chip.querySelector('input').checked = false; }
      });
    };
    testGrade.addEventListener('change', filterClasses);
    filterClasses();
  }
  /* ---------------- منصة نافس: شاشة الاختبار ---------------- */
  var exam = document.getElementById('exam');
  if (exam) {
    var sections = Array.prototype.slice.call(exam.querySelectorAll('.tq'));
    var navButtons = exam.querySelectorAll('#exam-nav [data-goto]');
    var saveStatus = document.getElementById('save-status');
    var current = 0;
    var submitting = false;
    exam.classList.add('js');

    var show = function (index) {
      current = Math.max(0, Math.min(sections.length, index));
      sections.forEach(function (s, i) { s.classList.toggle('active', i === current); });
      navButtons.forEach(function (b, i) { b.classList.toggle('current', i === current); });
      exam.classList.toggle('reviewing', current === sections.length);
      if (current === sections.length) { updateSummary(); }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    var answeredCount = function () {
      return sections.filter(function (s) { return s.querySelector('input[type=radio]:checked'); }).length;
    };

    var updateSummary = function () {
      var left = sections.length - answeredCount();
      var flagged = sections.filter(function (s) { return s.querySelector('[data-flag]:checked'); }).length;
      var text = left > 0 ? 'لم تُجب عن ' + left + ' سؤال. ' : 'أجبت عن جميع الأسئلة. ';
      if (flagged > 0) { text += 'لديك ' + flagged + ' سؤال مميز للمراجعة. '; }
      document.getElementById('submit-summary').textContent = text + 'لن تتمكن من تعديل إجاباتك بعد التسليم.';
    };

    var post = function (data) {
      data.append('_token', exam.dataset.token);
      data.append('attempt', exam.dataset.attempt);
      if (saveStatus) { saveStatus.textContent = 'جارٍ الحفظ…'; }
      return fetch(exam.dataset.answerUrl, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.closed) { window.location.reload(); return; }
          if (saveStatus) { saveStatus.textContent = j.ok ? 'تم الحفظ ✓' : (j.error || 'تعذر الحفظ'); }
        })
        .catch(function () { if (saveStatus) { saveStatus.textContent = 'تعذر الحفظ — تحقق من الاتصال، ستُرسل إجاباتك عند التسليم.'; } });
    };

    sections.forEach(function (section, i) {
      var qid = section.dataset.question;
      section.querySelectorAll('input[type=radio]').forEach(function (radio) {
        radio.addEventListener('change', function () {
          navButtons[i].classList.add('answered');
          document.getElementById('answered-count').textContent = answeredCount();
          var d = new FormData(); d.append('question', qid); d.append('option', radio.value); post(d);
        });
      });
      var flag = section.querySelector('[data-flag]');
      if (flag) {
        flag.addEventListener('change', function () {
          navButtons[i].classList.toggle('flagged', flag.checked);
          var d = new FormData(); d.append('question', qid); d.append('flag', flag.checked ? '1' : '0'); post(d);
        });
      }
    });

    exam.querySelectorAll('[data-step]').forEach(function (b) {
      b.addEventListener('click', function () { show(current + parseInt(b.dataset.step, 10)); });
    });
    exam.querySelectorAll('[data-finish]').forEach(function (b) {
      b.addEventListener('click', function () { show(sections.length); });
    });
    navButtons.forEach(function (b) {
      b.addEventListener('click', function () { show(parseInt(b.dataset.goto, 10)); });
    });

    exam.addEventListener('submit', function (e) {
      if (submitting) { return; }
      var left = sections.length - answeredCount();
      if (!window.confirm(left > 0 ? 'لم تُجب عن ' + left + ' سؤال. هل تريد التسليم؟' : 'تسليم الإجابات؟')) {
        e.preventDefault();
        return;
      }
      submitting = true;
    });
    window.addEventListener('beforeunload', function (e) {
      if (!submitting && answeredCount() < sections.length) { e.preventDefault(); e.returnValue = ''; }
    });

    var timer = document.getElementById('exam-timer');
    if (timer && exam.dataset.seconds !== undefined) {
      var end = Date.now() + parseInt(exam.dataset.seconds, 10) * 1000;
      var tick = function () {
        var s = Math.max(0, Math.round((end - Date.now()) / 1000));
        var m = Math.floor(s / 60);
        timer.querySelector('span').textContent = m + ':' + String(s % 60).padStart(2, '0');
        timer.classList.toggle('warn', s <= 300);
        if (s <= 0) {
          clearInterval(handle);
          submitting = true;
          exam.submit();
        }
      };
      var handle = setInterval(tick, 1000);
      tick();
    }

    show(0);
  }
});
