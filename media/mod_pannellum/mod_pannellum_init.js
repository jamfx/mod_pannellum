// Frontend-Initialisierung für mod_pannellum (Single & Multi) mit Font Awesome Icon-Support und Info-Hotspots
(function () {
  'use strict';

  function readConfigFor(el) {
    var cfgNode = document.getElementById(el.id + '-config');
    if (!cfgNode) {
      console.warn('[pannellum] Config node missing for', el.id);
      return null;
    }
    try {
      return JSON.parse(cfgNode.textContent || cfgNode.innerText || '{}');
    } catch (e) {
      console.error('[pannellum] Failed to parse config JSON:', e);
      return null;
    }
  }

  function ensureScenesHaveType(scenes) {
    var out = {};
    Object.keys(scenes).forEach(function (k) {
      var s = scenes[k] || {};
      if (!s.type) s.type = 'equirectangular';
      out[k] = s;
    });
    return out;
  }

  /**
   * Aktualisiert die Bildbeschreibung basierend auf Szenen-Daten
   */
  function updateDescription(wrapperId, descriptionData) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) {
      console.warn('[pannellum] Wrapper nicht gefunden:', wrapperId);
      return;
    }

    // Entferne alte Beschreibungen
    var oldDescriptions = wrapper.querySelectorAll('.pannellum-description-overlay');
    oldDescriptions.forEach(function(desc) {
      desc.parentNode.removeChild(desc);
    });

    // Prüfe, ob Beschreibung angezeigt werden soll
    if (!descriptionData || !descriptionData.showDescription || !descriptionData.descriptionText) {
      return; // Keine Beschreibung anzeigen
    }

    // Konvertiere Hex zu RGBA
    function hexToRgba(hex, alpha) {
      hex = hex.replace('#', '');
      if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
      }
      var r = parseInt(hex.substring(0, 2), 16);
      var g = parseInt(hex.substring(2, 4), 16);
      var b = parseInt(hex.substring(4, 6), 16);
      return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    // Erstelle neue Beschreibung
    var description = document.createElement('div');
    description.className = 'pannellum-description-overlay pannellum-description-' + descriptionData.descriptionPosition;
    
    var fontSize = descriptionData.descriptionFontSizeValue + descriptionData.descriptionFontSizeUnit;
    var bgColor = hexToRgba(descriptionData.descriptionBgColor, descriptionData.descriptionBgOpacity);
    
    var positionStyle = descriptionData.descriptionPosition === 'top' ? 'top: 0;' : 'bottom: 0;';
    
    description.style.cssText = 
      'position: absolute; ' +
      positionStyle +
      'left: 0; ' +
      'right: 0; ' +
      'padding: 15px 20px; ' +
      'background: ' + bgColor + '; ' +
      'color: ' + descriptionData.descriptionTextColor + '; ' +
      'font-size: ' + fontSize + '; ' +
      'font-weight: 600; ' +
      'text-align: ' + descriptionData.descriptionTextAlign + '; ' +
      'z-index: 10; ' +
      'pointer-events: none;';
    
    description.textContent = descriptionData.descriptionText;
    
    // Füge Beschreibung zum Wrapper hinzu
    wrapper.appendChild(description);
  }

  function createErrorOverlay(el, msg) {
    var box = document.createElement('div');
    box.style.position = 'relative';
    box.style.width = '100%';
    box.style.minHeight = '320px';

    var overlay = document.createElement('div');
    overlay.style.position = 'absolute';
    overlay.style.inset = '0';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.padding = '16px';
    overlay.style.background = 'rgba(0,0,0,0.05)';
    overlay.style.fontFamily = 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
    overlay.style.fontSize = '14px';
    overlay.style.textAlign = 'center';
    overlay.textContent = msg;

    box.appendChild(overlay);
    el.innerHTML = '';
    el.appendChild(box);
  }

  /**
   * Verarbeitet Hotspots und fügt Font Awesome Icons hinzu
   */
  function processHotspots(hotspots) {
    if (!hotspots || !Array.isArray(hotspots)) return [];

    return hotspots.map(function(hotspot) {
      // Info-Hotspots: Spezielle Behandlung mit HTML-Box
      if (hotspot.type === 'info') {
        hotspot.type = 'info';
        delete hotspot.sceneId; // Stelle sicher, dass kein sceneId gesetzt ist

        // WICHTIG: Erstelle IMMER HTML-Info-Box für Info-Hotspots
        // auch wenn kein Text vorhanden ist
        hotspot.createTooltipFunc = createInfoBox;
        hotspot.createTooltipArgs = {
          text: hotspot.text || '', // Auch leerer Text ist OK
          boxWidth: hotspot.boxWidth || 40,
          boxHeight: hotspot.boxHeight || 40,
          iconClass: hotspot.iconClass,
          iconColor: hotspot.iconColor || '#2196F3',
          iconSize: hotspot.iconSize || 18
        };

        return hotspot;
      }

      // Scene-Hotspots mit Icons
      if (hotspot.iconClass) {
        hotspot.createTooltipFunc = function(hotSpotDiv, args) {
          // Entferne default Pannellum styling
          hotSpotDiv.classList.remove('pnlm-hotspot');
          hotSpotDiv.classList.add('custom-icon-hotspot');

          // Hole Werte oder verwende Defaults
          var iconColor = args.iconColor || hotspot.iconColor || '#0066cc';
          var iconSize = args.iconSize || hotspot.iconSize || 20;

          // Erstelle Icon-Container
          var iconContainer = document.createElement('div');
          iconContainer.className = 'hotspot-icon-wrapper';
          iconContainer.style.cssText = 'width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.9); border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,0.3); cursor: pointer; transition: all 0.3s ease;';

          // Erstelle das Icon-Element
          var icon = document.createElement('span');
          var iconClass = args.iconClass || hotspot.iconClass;

          // Prüfe, ob es bereits "icon-" oder "fa" enthält
          if (iconClass.indexOf('icon-') === 0 || iconClass.indexOf('fa') === 0) {
            icon.className = iconClass;
          } else {
            icon.className = 'icon-' + iconClass;
          }

          icon.setAttribute('aria-hidden', 'true');
          icon.style.cssText = 'font-size: ' + iconSize + 'px; color: ' + iconColor + ';';

          iconContainer.appendChild(icon);
          hotSpotDiv.appendChild(iconContainer);

          // Hover-Effekt
          hotSpotDiv.addEventListener('mouseenter', function() {
            iconContainer.style.transform = 'scale(1.15)';
            iconContainer.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';
          });

          hotSpotDiv.addEventListener('mouseleave', function() {
            iconContainer.style.transform = 'scale(1)';
            iconContainer.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
          });

          // Tooltip hinzufügen, falls Text vorhanden
          if (args.text || hotspot.text) {
            var tooltip = document.createElement('span');
            tooltip.className = 'hotspot-custom-tooltip';
            // GEÄNDERT: innerHTML statt textContent für HTML-Unterstützung
            tooltip.innerHTML = args.text || hotspot.text;
            tooltip.style.cssText = 'position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(-8px); background: rgba(0,0,0,0.85); color: white; padding: 6px 12px; border-radius: 4px; font-size: 13px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;';

            hotSpotDiv.appendChild(tooltip);

            hotSpotDiv.addEventListener('mouseenter', function() {
              tooltip.style.opacity = '1';
            });

            hotSpotDiv.addEventListener('mouseleave', function() {
              tooltip.style.opacity = '0';
            });
          }
        };

        hotspot.createTooltipArgs = {
          iconClass: hotspot.iconClass,
          iconColor: hotspot.iconColor,
          iconSize: hotspot.iconSize,
          text: hotspot.text
        };
      }

      return hotspot;
    });
  }

  /**
   * Erstellt eine HTML-Info-Box für Info-Hotspots
   */
  function createInfoBox(hotSpotDiv, args) {
    hotSpotDiv.classList.remove('pnlm-hotspot');
    hotSpotDiv.classList.add('info-hotspot');

    // Hole Werte oder verwende Defaults
    var iconColor = args.iconColor || '#2196F3';
    var iconSize = args.iconSize || 18;

    // Info-Icon (Punkt)
    var iconWrapper = document.createElement('div');
    iconWrapper.className = 'info-hotspot-icon';
    iconWrapper.style.cssText = 'width: 32px; height: 32px; background: ' + iconColor + '; border: 3px solid white; border-radius: 50%; box-shadow: 0 3px 10px rgba(0,0,0,0.4); cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;';

    // "i" Symbol oder custom Icon
    var iconContent = document.createElement('span');
    if (args.iconClass) {
      iconContent.className = args.iconClass;
      iconContent.style.cssText = 'color: white; font-size: ' + iconSize + 'px;';
    } else {
      iconContent.textContent = 'i';
      iconContent.style.cssText = 'color: white; font-weight: bold; font-size: 20px; font-family: serif;';
    }
    iconWrapper.appendChild(iconContent);

    hotSpotDiv.appendChild(iconWrapper);

    // Info-Box (anfangs versteckt)
    var infoBox = document.createElement('div');
    infoBox.className = 'info-hotspot-box';

    var boxWidth = args.boxWidth || 40;
    var boxHeight = args.boxHeight || 40;

    infoBox.style.cssText =
      'position: fixed; ' +
      'left: 50%; ' +
      'top: 50%; ' +
      'transform: translate(-50%, -50%); ' +
      'width: ' + boxWidth + '%; ' +
      'max-width: 800px; ' +
      'height: ' + boxHeight + '%; ' +
      'max-height: 600px; ' +
      'background: rgba(255, 255, 255, 0.98); ' +
      'border-radius: 12px; ' +
      'box-shadow: 0 8px 32px rgba(0,0,0,0.3); ' +
      'padding: 0; ' +
      'z-index: 2147483647; ' +
      'display: none; ' +
      'flex-direction: column; ' +
      'overflow: hidden;';

    // Header
    var header = document.createElement('div');
    header.style.cssText = 'background: #2196F3; color: white; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;';

    var title = document.createElement('h3');
    title.textContent = 'Information';
    title.style.cssText = 'margin: 0; font-size: 18px; font-weight: 600;';

    var closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label', 'Schließen');
    closeBtn.style.cssText = 'background: none; border: none; color: white; font-size: 32px; line-height: 1; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s;';

    closeBtn.addEventListener('mouseenter', function() {
      closeBtn.style.opacity = '0.7';
    });
    closeBtn.addEventListener('mouseleave', function() {
      closeBtn.style.opacity = '1';
    });

    header.appendChild(title);
    header.appendChild(closeBtn);

    // Content (verwendet innerHTML für HTML-Unterstützung - bereits sicher durch safehtml filter)
    var content = document.createElement('div');
    content.className = 'info-hotspot-content';
    content.innerHTML = args.text || '<p style="color: #999; font-style: italic;">Keine Information verfügbar</p>';
    content.style.cssText = 'padding: 20px; overflow-y: auto; flex: 1; color: #333; line-height: 1.6; font-size: 15px;';

    infoBox.appendChild(header);
    infoBox.appendChild(content);

    // Backdrop
    var backdrop = document.createElement('div');
    backdrop.className = 'info-hotspot-backdrop';
    backdrop.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2147483646; display: none;';

    // Events
    var isOpen = false;

    function openBox() {
      // Prüfe, ob Pannellum im Fullscreen-Modus ist
      var fullscreenElement = document.fullscreenElement || 
                              document.webkitFullscreenElement || 
                              document.mozFullScreenElement || 
                              document.msFullscreenElement;
      
      var targetContainer = document.body;
      
      // Entferne alte Elemente aus dem DOM (falls vorhanden)
      if (backdrop.parentNode) {
        backdrop.parentNode.removeChild(backdrop);
      }
      if (infoBox.parentNode) {
        infoBox.parentNode.removeChild(infoBox);
      }
      
      // Wenn Fullscreen aktiv ist, füge in Fullscreen-Container ein
      if (fullscreenElement) {
        targetContainer = fullscreenElement;
        // Passe die Position für Fullscreen an
        infoBox.style.position = 'absolute';
        backdrop.style.position = 'absolute';
        console.log('[pannellum] Info-Box im Fullscreen-Modus geöffnet');
      } else {
        // Normal-Modus
        infoBox.style.position = 'fixed';
        backdrop.style.position = 'fixed';
        console.log('[pannellum] Info-Box im Normal-Modus geöffnet');
      }
      
      // Füge Elemente zum richtigen Container hinzu
      targetContainer.appendChild(backdrop);
      targetContainer.appendChild(infoBox);
      
      backdrop.style.display = 'block';
      infoBox.style.display = 'flex';
      isOpen = true;

      // Animation
      setTimeout(function() {
        backdrop.style.opacity = '1';
        infoBox.style.opacity = '1';
      }, 10);
    }

    function closeBox() {
      backdrop.style.opacity = '0';
      infoBox.style.opacity = '0';

      setTimeout(function() {
        backdrop.style.display = 'none';
        infoBox.style.display = 'none';
        isOpen = false;
        
        // Entferne Elemente komplett aus dem DOM
        if (backdrop.parentNode) {
          backdrop.parentNode.removeChild(backdrop);
        }
        if (infoBox.parentNode) {
          infoBox.parentNode.removeChild(infoBox);
        }
      }, 300);
    }

    iconWrapper.addEventListener('click', function(e) {
      e.stopPropagation();
      if (!isOpen) openBox();
    });

    closeBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      closeBox();
    });

    backdrop.addEventListener('click', closeBox);

    // ESC-Taste
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && isOpen) {
        closeBox();
      }
    });

    // Hover-Effekt auf Icon
    iconWrapper.addEventListener('mouseenter', function() {
      iconWrapper.style.transform = 'scale(1.15)';
      iconWrapper.style.boxShadow = '0 4px 14px rgba(0,0,0,0.5)';
    });

    iconWrapper.addEventListener('mouseleave', function() {
      iconWrapper.style.transform = 'scale(1)';
      iconWrapper.style.boxShadow = '0 3px 10px rgba(0,0,0,0.4)';
    });

    // Transitions
    backdrop.style.transition = 'opacity 0.3s ease';
    infoBox.style.transition = 'opacity 0.3s ease';
    backdrop.style.opacity = '0';
    infoBox.style.opacity = '0';

    // Elemente werden dynamisch beim Öffnen eingefügt
  }

  function boot(el) {
    var cfg = readConfigFor(el);
    if (!cfg) {
      createErrorOverlay(el, 'Konfiguration konnte nicht gelesen werden.');
      return;
    }

    console.log('[pannellum] init', { id: el.id, cfg: cfg });

    var container = el.id;
    var wrapperId = 'pannellum-wrapper-' + el.id.replace('pannellum-', '');

    try {
      if (cfg.type === 'single') {
        if (!cfg.panorama) {
          console.warn('[pannellum] Single: kein Panorama gesetzt');
          createErrorOverlay(el, 'Kein Panoramabild gesetzt.');
          return;
        }

        // Verarbeite Hotspots für Single-Mode
        var hotspots = cfg.hotSpots || [];
        console.log('[pannellum] Single-Mode Hotspots (vor Verarbeitung)', hotspots);
        hotspots = processHotspots(hotspots);
        console.log('[pannellum] Single-Mode Hotspots (nach Verarbeitung)', hotspots);

        pannellum.viewer(container, {
          type: 'equirectangular',
          panorama: cfg.panorama,
          autoLoad: !!cfg.autoLoad,
          yaw: +cfg.yaw || 0,
          pitch: +cfg.pitch || 0,
          hfov: +cfg.hfov || 100,
          hotSpots: hotspots
        });
        return;
      }

      if (cfg.type === 'multi') {
        if (!cfg.scenes || !Object.keys(cfg.scenes).length) {
          console.warn('[pannellum] Multi: keine Szenen gefunden');
          createErrorOverlay(el, 'Keine Szenen konfiguriert.');
          return;
        }

        var scenes = ensureScenesHaveType(cfg.scenes);

        // Verarbeite Hotspots für jede Szene
        Object.keys(scenes).forEach(function(sceneKey) {
          var scene = scenes[sceneKey];
          if (scene.hotSpots) {
            console.log('[pannellum] Multi-Mode Hotspots für Szene ' + sceneKey + ' (vor Verarbeitung)', scene.hotSpots);
            scene.hotSpots = processHotspots(scene.hotSpots);
            console.log('[pannellum] Multi-Mode Hotspots für Szene ' + sceneKey + ' (nach Verarbeitung)', scene.hotSpots);
          }
        });

        var first = (cfg.default && cfg.default.firstScene) ? cfg.default.firstScene : Object.keys(scenes)[0];

        // Zusätzliche Validierung
        var firstScene = scenes[first];
        if (!firstScene || !firstScene.panorama) {
          console.warn('[pannellum] Multi: erste Szene ohne Panorama', { first: first, firstScene: firstScene });
          createErrorOverlay(el, 'Erste Szene hat kein Panoramabild.');
          return;
        }

        var viewerCfg = {
          "default": {
            firstScene: first,
            autoLoad: true
          },
          scenes: scenes
        };

        console.log('[pannellum] creating multi viewer', viewerCfg);
        var v = pannellum.viewer(container, viewerCfg);

        // Event-Listener für Szenenwechsel (um Bildbeschreibung zu aktualisieren)
        try {
          v.on('scenechange', function (sceneId) {
            console.log('[pannellum] scene changed to:', sceneId);
            
            // Aktualisiere Bildbeschreibung
            var currentScene = cfg.scenes[sceneId];
            if (currentScene && currentScene.description) {
              updateDescription(wrapperId, currentScene.description);
            } else {
              // Keine Beschreibung für diese Szene
              updateDescription(wrapperId, null);
            }
          });
        } catch (e) {
          console.warn('[pannellum] Could not attach scenechange listener:', e);
        }
        
        return;
      }

      console.warn('[pannellum] Unbekannter Typ', cfg.type);
      createErrorOverlay(el, 'Unbekannter Viewer-Typ.');
    } catch (e) {
      console.error('[pannellum] Fehler beim Initialisieren:', e);
      createErrorOverlay(el, 'Fehler beim Initialisieren des Viewers. Details in der Konsole.');
    }
  }

  function whenVisible(el, cb) {
    if (!('IntersectionObserver' in window)) {
      setTimeout(function () { cb(el); }, 50);
      return;
    }
    var once = false;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (ent) {
        if (!once && ent.isIntersecting) {
          once = true;
          io.disconnect();
          cb(el);
        }
      });
    }, { root: null, threshold: 0.1 });
    io.observe(el);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pannellum-module').forEach(function (el) {
      whenVisible(el, boot);
    });
  });
})();
