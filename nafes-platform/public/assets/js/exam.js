/* واجهة الاختبار: التنقل، الحفظ التلقائي، العمل دون اتصال، المؤقت، والتسليم */
(function () {
  'use strict';
  var form = document.getElementById('exam');
  if (!form) { return; }
  var attemptId = form.dataset.attempt;
  var storeKey = 'nafes.attempt.' + attemptId;
  var sections = Array.prototype.slice.call(form.querySelectorAll('.exam-q'));
  var questions = sections.filter(function (s) { return s.dataset.tq; });
  var navButtons = form.querySelectorAll('#qnav [data-goto]');
  var statusEl = document.getElementById('net-status');
  var current = 0, dirty = {}, flagsDirty = {}, saving = false, submitting = false, closed = false;

  /* ---------- قراءة إجابة سؤال حسب نوعه ---------- */
  function valueOf(sec) {
    var type = sec.dataset.type;
    if (type === 'single' || type === 'true_false') {
      var r = sec.querySelector('input[type=radio]:checked');
      return r ? r.value : null;
    }
    if (type === 'multiple') {
      var v = Array.prototype.map.call(sec.querySelectorAll('input[type=checkbox]:not([data-flag]):checked'), function (c) { return c.value; });
      return v.length ? v : null;
    }
    if (type === 'matching') {
      var pairs = {}, any = false;
      sec.querySelectorAll('select').forEach(function (s) {
        var m = s.name.match(/\[(\d+)\]$/);
        if (m && s.value) { pairs[m[1]] = s.value; any = true; }
      });
      return any ? pairs : null;
    }
    if (type === 'ordering') {
      if (!sec.dataset.touched) { return null; }
      return Array.prototype.map.call(sec.querySelectorAll('.sort-item input[type=hidden]'), function (h) { return h.value; });
    }
    if (type === 'short_answer') {
      var t = sec.querySelector('input[type=text]');
      return t && t.value.trim() !== '' ? t.value : null;
    }
    return null;
  }

  function applyValue(sec, val) {
    var type = sec.dataset.type;
    if (val === null || val === undefined) { return; }
    if (type === 'single' || type === 'true_false') {
      var r = sec.querySelector('input[type=radio][value="' + val + '"]');
      if (r) { r.checked = true; }
    } else if (type === 'multiple') {
      sec.querySelectorAll('input[type=checkbox]:not([data-flag])').forEach(function (c) { c.checked = val.indexOf(c.value) !== -1; });
    } else if (type === 'matching') {
      sec.querySelectorAll('select').forEach(function (s) {
        var m = s.name.match(/\[(\d+)\]$/);
        if (m && val[m[1]]) { s.value = val[m[1]]; }
      });
    } else if (type === 'ordering') {
      var list = sec.querySelector('[data-sortable]');
      val.forEach(function (id) {
        var h = list.querySelector('input[value="' + id + '"]');
        if (h) { list.appendChild(h.closest('.sort-item')); }
      });
      sec.dataset.touched = '1';
    } else if (type === 'short_answer') {
      var t = sec.querySelector('input[type=text]');
      if (t) { t.value = val; }
    }
  }

  /* ---------- الحالة والتنقل ---------- */
  function refresh() {
    var answered = 0, flagged = 0;
    questions.forEach(function (sec, i) {
      var has = valueOf(sec) !== null;
      var fl = sec.querySelector('[data-flag]').checked;
      if (has) { answered++; }
      if (fl) { flagged++; }
      navButtons[i].classList.toggle('answered', has);
      navButtons[i].classList.toggle('flagged', fl);
    });
    document.getElementById('answered-count').textContent = answered;
    document.getElementById('progress-bar').style.width = (questions.length ? answered / questions.length * 100 : 0) + '%';
    document.getElementById('sum-answered').textContent = answered;
    document.getElementById('sum-blank').textContent = questions.length - answered;
    document.getElementById('sum-flagged').textContent = flagged;
    return { answered: answered, blank: questions.length - answered, flagged: flagged };
  }

  function show(i) {
    current = Math.max(0, Math.min(sections.length - 1, i));
    sections.forEach(function (s, k) { s.classList.toggle('active', k === current); });
    navButtons.forEach(function (b, k) { b.classList.toggle('current', k === current); });
    if (current === sections.length - 1) { refresh(); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  form.addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (!b) { return; }
    if (b.dataset.step) { show(current + parseInt(b.dataset.step, 10)); }
    if (b.dataset.goto !== undefined) { show(parseInt(b.dataset.goto, 10)); }
    if (b.hasAttribute('data-review')) { show(sections.length - 1); }
    if (b.dataset.move) {
      var item = b.closest('.sort-item'), list = item.parentNode;
      var sib = b.dataset.move === '-1' ? item.previousElementSibling : item.nextElementSibling;
      if (sib) { b.dataset.move === '-1' ? list.insertBefore(item, sib) : list.insertBefore(sib, item); }
      var sec = b.closest('.exam-q');
      sec.dataset.touched = '1';
      markDirty(sec);
    }
  });

  /* ---------- التخزين المحلي والحفظ ---------- */
  function local() {
    try { return JSON.parse(localStorage.getItem(storeKey) || '{}'); } catch (e) { return {}; }
  }
  function persistLocal() {
    try {
      var data = local();
      data.answers = data.answers || {};
      data.flags = data.flags || {};
      Object.keys(dirty).forEach(function (k) { data.answers[k] = dirty[k]; });
      Object.keys(flagsDirty).forEach(function (k) { data.flags[k] = flagsDirty[k]; });
      data.pending = Object.keys(dirty).length + Object.keys(flagsDirty).length > 0;
      localStorage.setItem(storeKey, JSON.stringify(data));
    } catch (e) {}
  }
  function setStatus(kind, text) {
    statusEl.className = 'net-status ' + (kind === 'ok' ? 'text-success' : kind === 'warn' ? 'text-warning' : 'text-danger');
    statusEl.innerHTML = '<i class="bi bi-' + (kind === 'ok' ? 'cloud-check' : kind === 'warn' ? 'cloud-arrow-up' : 'wifi-off') + '"></i> ' + text;
  }

  var debounce = null;
  function markDirty(sec) {
    var v = valueOf(sec);
    dirty[sec.dataset.tq] = v === null ? '' : v;
    persistLocal();
    refresh();
    setStatus('warn', 'غير محفوظ بعد…');
    clearTimeout(debounce);
    debounce = setTimeout(flush, 1500);
  }

  function flush() {
    if (saving || closed) { return Promise.resolve(); }
    var answers = dirty, flags = flagsDirty;
    if (!Object.keys(answers).length && !Object.keys(flags).length) { return Promise.resolve(); }
    saving = true;
    dirty = {}; flagsDirty = {};
    return fetch(Nafes.url(form.dataset.saveUrl), {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': Nafes.csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ answers: answers, flags: flags })
    }).then(function (r) {
      return r.json().then(function (j) { return { status: r.status, j: j }; });
    }).then(function (res) {
      saving = false;
      if (res.status === 409 || (res.j && res.j.closed)) {
        closed = true;
        try { localStorage.removeItem(storeKey); } catch (e) {}
        window.location = Nafes.url('/student/attempts/' + attemptId + '/done');
        return;
      }
      if (res.status === 401 || res.status === 419) { throw new Error('session'); }
      if (!res.j || !res.j.ok) { throw new Error('save'); }
      try { var d = local(); d.answers = {}; d.flags = {}; d.pending = false; localStorage.setItem(storeKey, JSON.stringify(d)); } catch (e) {}
      if (typeof res.j.seconds === 'number') { syncTimer(res.j.seconds); }
      setStatus('ok', 'تم الحفظ ' + new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' }));
    }).catch(function (err) {
      saving = false;
      Object.keys(answers).forEach(function (k) { if (!(k in dirty)) { dirty[k] = answers[k]; } });
      Object.keys(flags).forEach(function (k) { if (!(k in flagsDirty)) { flagsDirty[k] = flags[k]; } });
      persistLocal();
      setStatus('err', err && err.message === 'session' ? 'انتهت الجلسة — سجّل الدخول من نافذة أخرى ثم عُد' : 'لا يوجد اتصال — إجاباتك محفوظة على جهازك وستُرسل تلقائياً');
    });
  }

  questions.forEach(function (sec) {
    sec.addEventListener('change', function (e) {
      if (e.target.hasAttribute('data-flag')) {
        flagsDirty[sec.dataset.tq] = e.target.checked ? 1 : 0;
        persistLocal();
        refresh();
        clearTimeout(debounce);
        debounce = setTimeout(flush, 800);
        return;
      }
      markDirty(sec);
    });
    var t = sec.querySelector('input[type=text]');
    if (t) { t.addEventListener('input', function () { clearTimeout(t._d); t._d = setTimeout(function () { markDirty(sec); }, 700); }); }
  });

  // استعادة ما لم يُرسل من جلسة سابقة (انقطاع الاتصال أو إغلاق المتصفح)
  (function restore() {
    var d = local();
    if (!d.pending) { return; }
    Object.keys(d.answers || {}).forEach(function (k) {
      var sec = form.querySelector('.exam-q[data-tq="' + k + '"]');
      if (sec) { applyValue(sec, d.answers[k] === '' ? null : d.answers[k]); dirty[k] = d.answers[k]; }
    });
    Object.keys(d.flags || {}).forEach(function (k) {
      var sec = form.querySelector('.exam-q[data-tq="' + k + '"]');
      if (sec) { sec.querySelector('[data-flag]').checked = !!d.flags[k]; flagsDirty[k] = d.flags[k]; }
    });
    setStatus('warn', 'تمت استعادة إجابات غير مرسلة…');
    setTimeout(flush, 500);
  })();

  window.addEventListener('online', function () { setStatus('warn', 'عاد الاتصال — جارٍ الإرسال…'); flush(); });
  window.addEventListener('offline', function () { setStatus('err', 'لا يوجد اتصال — تابع الإجابة، ستُحفظ إجاباتك على جهازك'); });
  setInterval(flush, (parseInt(form.dataset.autosave, 10) || 20) * 1000);
  window.addEventListener('beforeunload', function (e) {
    if (!submitting && (Object.keys(dirty).length || Object.keys(flagsDirty).length)) { flush(); e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- المؤقت (العرض فقط — التحقق الفعلي على الخادم) ---------- */
  var timerEl = document.getElementById('timer'), endAt = null, tick = null;
  function syncTimer(seconds) { endAt = Date.now() + seconds * 1000; }
  if (timerEl && form.dataset.seconds !== undefined) {
    syncTimer(parseInt(form.dataset.seconds, 10));
    var render = function () {
      var s = Math.max(0, Math.round((endAt - Date.now()) / 1000));
      var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
      timerEl.querySelector('span').textContent = (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s % 60).padStart(2, '0');
      timerEl.classList.toggle('warn', s <= 300);
      if (s <= 0) { clearInterval(tick); doSubmit(true); }
    };
    tick = setInterval(render, 1000);
    render();
  }

  /* ---------- التسليم ---------- */
  var modalEl = document.getElementById('submitModal');
  var modal = new bootstrap.Modal(modalEl);
  document.getElementById('submit-btn').addEventListener('click', function () {
    var st = refresh();
    document.getElementById('submit-text').innerHTML =
      (st.blank ? '<div class="alert alert-warning mb-2">لم تُجب عن <b>' + st.blank + '</b> سؤال.</div>' : '<div class="alert alert-success mb-2">أجبت عن جميع الأسئلة.</div>') +
      (st.flagged ? '<p class="mb-2">لديك <b>' + st.flagged + '</b> سؤال معلّم للمراجعة.</p>' : '') +
      '<p class="mb-0">بعد التسليم لا يمكن تعديل الإجابات. هل تريد التسليم الآن؟</p>';
    modal.show();
  });
  document.getElementById('confirm-submit').addEventListener('click', function () { modal.hide(); doSubmit(false); });

  function doSubmit(auto) {
    if (submitting) { return; }
    submitting = true;
    document.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    setStatus('warn', auto ? 'انتهى الوقت — جارٍ التسليم…' : 'جارٍ التسليم…');
    // ترتيب لم يُلمس لا يُرسل كإجابة
    questions.forEach(function (sec) {
      if (sec.dataset.type === 'ordering' && !sec.dataset.touched) {
        sec.querySelectorAll('.sort-item input[type=hidden]').forEach(function (h) { h.disabled = true; });
      }
    });
    // لا نحذف النسخة المحلية هنا؛ تُحذف في صفحة تأكيد التسليم بعد نجاحه
    flush().finally(function () { form.submit(); });
  }

  refresh();
  show(0);
})();
