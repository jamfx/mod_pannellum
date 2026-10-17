/**
 * mod_pannellum – Backend, Galerie-Modus: blendet beim Speichern eine Fortschrittsanzeige ein,
 * weil dabei die Vorschaubilder erzeugt werden und das einige Sekunden dauern kann.
 * @license GNU General Public License version 3 or later
 */
(function () {
  'use strict';

  var SAVE_TASKS = ['module.apply', 'module.save', 'module.save2new', 'module.save2copy'];

  function t(key, fallback) {
    return (window.Joomla && Joomla.Text && typeof Joomla.Text._ === 'function' && Joomla.Text._(key)) || fallback;
  }

  function isGalleryMode(form) {
    var field = form.querySelector('[name="jform[params][mode]"]');

    return !!field && field.value === 'gallery';
  }

  function hasEntries(form) {
    return form.querySelectorAll('[name*="[gallery_item_image]"]').length > 0;
  }

  function showOverlay() {
    if (document.getElementById('mod-pannellum-saving')) {
      return;
    }

    var style = document.createElement('style');
    style.textContent =
      '#mod-pannellum-saving{position:fixed;top:0;left:0;right:0;bottom:0;z-index:100000;display:flex;' +
      'align-items:center;justify-content:center;background:rgba(0,0,0,.6);}' +
      '#mod-pannellum-saving .box{max-width:420px;margin:16px;padding:24px 28px;border-radius:8px;background:#fff;' +
      'color:#222;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,.35);}' +
      '#mod-pannellum-saving .spinner{width:40px;height:40px;margin:0 auto 14px;border:4px solid #ccc;' +
      'border-top-color:#1a73e8;border-radius:50%;animation:mod-pannellum-spin 1s linear infinite;}' +
      '@keyframes mod-pannellum-spin{to{transform:rotate(360deg);}}' +
      '@media (prefers-reduced-motion:reduce){#mod-pannellum-saving .spinner{animation-duration:3s;}}';
    document.head.appendChild(style);

    var overlay = document.createElement('div');
    overlay.id = 'mod-pannellum-saving';
    overlay.setAttribute('role', 'alert');
    overlay.setAttribute('aria-live', 'assertive');

    var box = document.createElement('div');
    box.className = 'box';

    var spinner = document.createElement('div');
    spinner.className = 'spinner';

    var title = document.createElement('strong');
    title.textContent = t('MOD_PANNELLUM_JS_SAVING_TITLE', 'Thumbnails are being created…');

    var text = document.createElement('p');
    text.style.margin = '8px 0 0';
    text.textContent = t('MOD_PANNELLUM_JS_SAVING_TEXT', 'Please do not close this page. This can take a moment for large panoramas.');

    box.appendChild(spinner);
    box.appendChild(title);
    box.appendChild(text);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
  }

  function init() {
    var form = document.getElementById('module-form') || document.querySelector('form[name="adminForm"]');

    if (!form) {
      return;
    }

    // Joomla löst beim Speichern ein submit-Ereignis aus (nach der Formularprüfung)
    form.addEventListener('submit', function () {
      var taskField = form.querySelector('[name="task"]');
      var task = taskField ? taskField.value : '';

      if (SAVE_TASKS.indexOf(task) !== -1 && isGalleryMode(form) && hasEntries(form)) {
        showOverlay();
      }
    });

    // Beim Zurücknavigieren (Browser-Cache) die Anzeige wieder entfernen
    window.addEventListener('pageshow', function () {
      var overlay = document.getElementById('mod-pannellum-saving');

      if (overlay) {
        overlay.remove();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
