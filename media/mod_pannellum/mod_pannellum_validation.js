/**
 * Backend Live-Validierung für mod_pannellum
 * Prüft Szenen-IDs und warnt bei Fehlkonfiguration
 * Version 2.0.5
 */
(function() {
  'use strict';

  // Warte auf DOM-Ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    // Prüfe ob wir im Backend sind
    if (!document.querySelector('.com_modules')) {
      return;
    }

    // Beobachte das Formular
    observeForm();
  }

  function observeForm() {
    // Finde das Hauptformular
    var form = document.querySelector('form[name="adminForm"]');
    if (!form) {
      setTimeout(observeForm, 500);
      return;
    }

    // Beobachte Änderungen am Default Scene Feld
    var defaultSceneField = form.querySelector('[name="jform[params][default_scene]"]');
    if (defaultSceneField) {
      defaultSceneField.addEventListener('blur', validateDefaultScene);
      defaultSceneField.addEventListener('change', validateDefaultScene);
    }

    // Beobachte Szenen-Subform für Änderungen
    var scenesContainer = form.querySelector('[data-group="scenes"]');
    if (scenesContainer) {
      // Mutation Observer für dynamische Änderungen
      var observer = new MutationObserver(function() {
        validateDefaultScene();
      });

      observer.observe(scenesContainer, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['value']
      });

      // Initiale Validierung
      validateDefaultScene();
    }
  }

  function validateDefaultScene() {
    var form = document.querySelector('form[name="adminForm"]');
    if (!form) return;

    var defaultSceneField = form.querySelector('[name="jform[params][default_scene]"]');
    if (!defaultSceneField) return;

    var defaultSceneId = defaultSceneField.value.trim();
    if (defaultSceneId === '') return;

    // Sammle alle vorhandenen Szenen-IDs
    var sceneIds = [];
    var sceneIdFields = form.querySelectorAll('[name*="[scene_id]"]');
    
    sceneIdFields.forEach(function(field) {
      var id = field.value.trim();
      if (id !== '') {
        sceneIds.push(id);
      }
    });

    // Entferne alte Validierungs-Nachrichten
    removeValidationMessage(defaultSceneField);

    // Prüfe ob die Default-Szene existiert
    if (sceneIds.length > 0 && !sceneIds.includes(defaultSceneId)) {
      showValidationError(
        defaultSceneField,
        'Warnung: Die Start-Szene "' + defaultSceneId + '" existiert nicht!\n' +
        'Verfügbare Szenen: ' + sceneIds.join(', ') + '\n\n' +
        'Das Modul wird automatisch zur ersten verfügbaren Szene wechseln.'
      );
    } else if (sceneIds.length > 0) {
      showValidationSuccess(
        defaultSceneField,
        'Start-Szene "' + defaultSceneId + '" gefunden ✓'
      );
    }
  }

  function showValidationError(field, message) {
    removeValidationMessage(field);

    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-warning validation-message';
    alertDiv.style.cssText = 'margin-top: 10px; padding: 10px; font-size: 13px;';
    alertDiv.innerHTML = '<strong>⚠️ Validierung:</strong><br>' + 
                         message.replace(/\n/g, '<br>');

    field.parentElement.appendChild(alertDiv);

    // Feld rot markieren
    field.style.borderColor = '#ffc107';
    field.style.backgroundColor = '#fff3cd';
  }

  function showValidationSuccess(field, message) {
    removeValidationMessage(field);

    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success validation-message';
    alertDiv.style.cssText = 'margin-top: 10px; padding: 8px; font-size: 12px;';
    alertDiv.textContent = message;

    field.parentElement.appendChild(alertDiv);

    // Feld grün markieren
    field.style.borderColor = '#198754';
    field.style.backgroundColor = '#d1e7dd';

    // Nach 3 Sekunden ausblenden
    setTimeout(function() {
      alertDiv.style.transition = 'opacity 0.5s';
      alertDiv.style.opacity = '0';
      setTimeout(function() {
        removeValidationMessage(field);
        field.style.borderColor = '';
        field.style.backgroundColor = '';
      }, 500);
    }, 3000);
  }

  function removeValidationMessage(field) {
    var existingAlert = field.parentElement.querySelector('.validation-message');
    if (existingAlert) {
      existingAlert.remove();
    }
  }

})();
