/**
 * mod_pannellum – Galerie-Modus: Aktivierung des Pannellum-Viewers per Klick
 * (Inline oder Modal). Vanilla JavaScript, es ist immer nur ein Viewer aktiv.
 * @license GNU General Public License version 3 or later
 */
(function () {
  'use strict';

  // Seitenweiter Zustand – auch über mehrere Galerie-Instanzen hinweg
  window.modPannellumActiveViewer = window.modPannellumActiveViewer || null;

  // Aufräum-Funktion des aktuell aktiven Viewers (stellt Kachel bzw. Modal wieder her)
  var activeCleanup = null;
  var modal = null;

  function closeActive(restoreFocus) {
    if (window.modPannellumActiveViewer) {
      try {
        window.modPannellumActiveViewer.destroy();
      } catch (e) { /* bereits zerstört */ }
      window.modPannellumActiveViewer = null;
    }

    if (activeCleanup) {
      var cleanup = activeCleanup;
      activeCleanup = null;
      cleanup(restoreFocus);
    }
  }

  function viewerOptions(item, gallery) {
    var opts = {
      type: 'equirectangular',
      panorama: item.dataset.image,
      yaw: parseFloat(item.dataset.yaw) || 0,
      pitch: parseFloat(item.dataset.pitch) || 0,
      hfov: parseFloat(item.dataset.hfov) || 100,
      minHfov: 30,
      autoLoad: true,
      autoRotate: 0,
      showFullscreenCtrl: item.dataset.fullscreen === '1'
    };

    if (item.dataset.autorotate === '1') {
      opts.autoRotate = parseFloat(gallery.dataset.autoRotateSpeed) || 2;
      opts.autoRotateInactivityDelay = parseInt(gallery.dataset.autoRotateDelay, 10) || 3000;
    }

    if (gallery.dataset.showCompass === '1') {
      opts.compass = true;
    }

    return opts;
  }

  function createViewer(container, item, gallery) {
    window.modPannellumActiveViewer = window.pannellum.viewer(container, viewerOptions(item, gallery));
  }

  /* ---------- Inline ---------- */
  function openInline(item, gallery) {
    closeActive(false);

    var container = document.createElement('div');
    container.className = 'mod-pannellum-gallery__viewer';

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'mod-pannellum-gallery__close-btn';
    closeBtn.textContent = '×';
    closeBtn.setAttribute('aria-label', gallery.dataset.labelClose || 'Close');

    var role = item.getAttribute('role');
    item.classList.add('is-active');
    item.removeAttribute('role');
    item.setAttribute('tabindex', '-1');
    item.appendChild(container);
    item.appendChild(closeBtn);

    closeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      closeActive(true);
    });

    activeCleanup = function (restoreFocus) {
      container.remove();
      closeBtn.remove();
      item.classList.remove('is-active');
      if (role) {
        item.setAttribute('role', role);
      }
      item.setAttribute('tabindex', '0');
      if (restoreFocus) {
        item.focus();
      }
    };

    createViewer(container, item, gallery);
    closeBtn.focus();
  }

  /* ---------- Modal ---------- */
  function ensureModal(gallery) {
    if (modal) {
      return modal;
    }

    var root = document.createElement('div');
    root.className = 'mod-pannellum-gallery-modal';
    root.id = 'mod-pannellum-gallery-modal';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');

    var backdrop = document.createElement('div');
    backdrop.className = 'mod-pannellum-gallery-modal__backdrop';

    var content = document.createElement('div');
    content.className = 'mod-pannellum-gallery-modal__content';

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'mod-pannellum-gallery-modal__close';
    closeBtn.textContent = '×';

    var viewer = document.createElement('div');
    viewer.className = 'mod-pannellum-gallery-modal__viewer';
    viewer.id = 'mod-pannellum-gallery-modal-viewer';

    var caption = document.createElement('div');
    caption.className = 'mod-pannellum-gallery-modal__caption';

    content.appendChild(closeBtn);
    content.appendChild(viewer);
    content.appendChild(caption);
    root.appendChild(backdrop);
    root.appendChild(content);
    document.body.appendChild(root);

    backdrop.addEventListener('click', function () { closeActive(true); });
    closeBtn.addEventListener('click', function () { closeActive(true); });

    // Fokus im Modal halten
    root.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') {
        return;
      }
      var focusable = root.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])');
      if (!focusable.length) {
        return;
      }
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    modal = { root: root, content: content, closeBtn: closeBtn, viewer: viewer, caption: caption };
    return modal;
  }

  function openModal(item, gallery) {
    closeActive(false);

    var m = ensureModal(gallery);
    m.closeBtn.setAttribute('aria-label', gallery.dataset.labelClose || 'Close');
    m.content.style.height = gallery.dataset.modalHeight || '80vh';
    m.caption.textContent = item.dataset.title || '';
    m.root.setAttribute('aria-label', item.dataset.title || '');
    m.root.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    activeCleanup = function (restoreFocus) {
      m.root.style.display = 'none';
      m.viewer.innerHTML = '';
      document.body.style.overflow = '';
      if (restoreFocus) {
        item.focus();
      }
    };

    createViewer(m.viewer, item, gallery);
    m.closeBtn.focus();
  }

  /* ---------- Aktivierung ---------- */
  function activate(item) {
    var gallery = item.closest('.mod-pannellum-gallery');

    if (!gallery || typeof window.pannellum === 'undefined' || item.classList.contains('is-active')) {
      return;
    }

    if (gallery.dataset.viewerMode === 'modal') {
      openModal(item, gallery);
    } else {
      openInline(item, gallery);
    }
  }

  function init() {
    document.querySelectorAll('.mod-pannellum-gallery__item').forEach(function (item) {
      item.addEventListener('click', function () {
        activate(item);
      });

      item.addEventListener('keydown', function (e) {
        // Nur Tasten auf der Kachel selbst, nicht auf Elementen des Viewers
        if (e.target !== item) {
          return;
        }
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          activate(item);
        }
      });
    });
  }

  // Escape schließt den aktiven Viewer (Inline oder Modal)
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Escape' || e.key === 'Esc') && window.modPannellumActiveViewer) {
      closeActive(true);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
