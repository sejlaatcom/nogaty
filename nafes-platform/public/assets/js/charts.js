/* إنشاء الرسوم البيانية من سمة data-chart (إعداد Chart.js بصيغة JSON) */
(function () {
  'use strict';
  if (!window.Chart) { return; }
  var css = getComputedStyle(document.documentElement);
  Chart.defaults.font.family = css.getPropertyValue('--font') || 'Tajawal, sans-serif';
  Chart.defaults.color = '#475569';
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.tooltip.rtl = true;
  Chart.defaults.plugins.legend.rtl = true;
  document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
    try {
      var cfg = JSON.parse(canvas.dataset.chart);
      cfg.options = cfg.options || {};
      cfg.options.maintainAspectRatio = false;
      cfg.options.responsive = true;
      if (cfg.percent) {
        cfg.options.scales = cfg.options.scales || {};
        var axis = cfg.options.indexAxis === 'y' ? 'x' : 'y';
        cfg.options.scales[axis] = Object.assign({ min: 0, max: 100, ticks: { callback: function (v) { return v + '٪'; } } }, cfg.options.scales[axis] || {});
        cfg.options.plugins = cfg.options.plugins || {};
        cfg.options.plugins.tooltip = { callbacks: { label: function (c) { return (c.dataset.label ? c.dataset.label + ': ' : '') + (c.parsed[axis] ?? c.parsed) + '٪'; } } };
      }
      new Chart(canvas, cfg);
    } catch (e) { console.error('chart', e); }
  });
})();
