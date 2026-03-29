(function () {
  'use strict';

  // ========== LOGGING ==========
  function log(msg, data){ try{ console.log('[Hotspot-Editor]', msg, data||''); }catch(e){} }
  function warn(msg, data){ try{ console.warn('[Hotspot-Editor WARN]', msg, data||''); }catch(e){} }
  function err(msg, data){ try{ console.error('[Hotspot-Editor ERROR]', msg, data||''); }catch(e){} }

  // ========== URL HELPER ==========
  function stripAdmin(p){ return p.replace(/\/administrator(\/|$)/i, '/'); }
  function resolveMediaUrl(raw){
    var v = String(raw||'').trim();
    if(!v) return '';
    var hi = v.indexOf('#joomlaImage://');
    if(hi >= 0){
      var hash = v.substring(hi+1);
      var m = hash.match(/^joomlaImage:\/\/(?:local-)?(images\/[^?]+)/i);
      v = (m && m[1]) ? m[1] : v.substring(0,hi);
    }
    if(/^https?:\/\//i.test(v)){
      try{ var u=new URL(v); u.pathname=stripAdmin(u.pathname); return u.toString(); }
      catch(_){ return v; }
    }
    v = v.replace(/^\/?administrator\//i,'').replace(/^\//,'');
    try{
      var base=(document.querySelector('base')||{}).href || window.location.origin + window.location.pathname;
      var u2=new URL(v, base); u2.pathname=stripAdmin(u2.pathname); return u2.toString();
    }catch(_){ return '/'+v; }
  }

  // ========== MODE DETECTION ==========
  function isSingleMode(btn){
    log('=== MODE DETECTION START ===');

    // Methode 1: Prüfe das Mode-Select-Feld (zuverlässigste Methode)
    var form = btn.closest('form[name="adminForm"]') ||
               btn.closest('form') ||
               document.querySelector('form[name="adminForm"]') ||
               document.querySelector('form');

    if(form){
      var modeField = form.querySelector('select[name*="[mode]"], input[name*="[mode]"]');
      if(modeField){
        var modeValue = modeField.value;
        log('Mode Detection: Mode-Feld gefunden!', {value: modeValue});

        if(modeValue === 'single'){
          log('→ SINGLE MODE (via mode field)');
          return true;
        } else if(modeValue === 'multi'){
          log('→ MULTI MODE (via mode field)');
          return false;
        }
      } else {
        log('Mode Detection: Kein Mode-Feld im Formular gefunden');
      }
    } else {
      log('Mode Detection: Kein Formular gefunden');
    }

    // Methode 2: Prüfe ob Button in einer Scene-Row ist
    var current = btn;
    var maxDepth = 15;
    var depth = 0;

    while(current && current !== document.body && depth < maxDepth){
      depth++;

      if(current.querySelector){
        // Prüfe ob dieses Element scene_image Feld hat (Multi-Mode Scene)
        var hasSceneImage = current.querySelector('input[name*="[scene_image]"]');
        if(hasSceneImage){
          log('Mode Detection: Scene-Image gefunden → MULTI MODE');
          return false;
        }

        // Prüfe ob wir in einem Scene-Container sind
        var hasSceneMarker = current.querySelector('input[name*="[scenes]"]');
        if(hasSceneMarker){
          log('Mode Detection: Scene-Marker gefunden → MULTI MODE');
          return false;
        }
      }

      current = current.parentElement;
    }

    // Methode 3: Prüfe ob es single_image Feld gibt
    if(form){
      var hasSingleImage = form.querySelector('input[name*="[single_image]"]');
      var hasScenes = form.querySelector('[data-base-name*="[scenes]"]') ||
                     form.querySelector('input[name*="[scenes]"]');

      if(hasSingleImage && !hasScenes){
        log('Mode Detection: single_image vorhanden, scenes nicht → SINGLE MODE');
        return true;
      }

      if(hasSingleImage){
        log('Mode Detection: single_image vorhanden → vermutlich SINGLE MODE');
        // Aber prüfe ob scenes versteckt sind
        var scenesHidden = form.querySelector('[data-showon*="multi"]');
        if(scenesHidden){
          log('Mode Detection: scenes sind versteckt (showon=multi) → SINGLE MODE');
          return true;
        }
      }
    }

    // Methode 4: data-base-name Check
    var sceneCheck = btn.closest('[data-base-name*="[scenes]"]');
    if(sceneCheck){
      log('Mode Detection: data-base-name [scenes] gefunden → MULTI MODE');
      return false;
    }

    // Fallback
    log('Mode Detection: Keine eindeutige Erkennung → Fallback SINGLE MODE');
    return true; // Default Single-Mode
  }

  // ========== CONTEXT FINDER ==========
  function findContext(btn){
    var isSingle = isSingleMode(btn);

    log('findContext aufgerufen', {isSingle: isSingle, btnTag: btn.tagName});

    if(isSingle){
      // Single Mode: Der gesamte Form-Kontext
      // Mehrere Versuche mit Fallbacks

      var context = btn.closest('form[name="adminForm"]');
      if(context && context.tagName === 'FORM'){
        log('Single-Mode: Form gefunden via closest', context);
        return context;
      }

      context = btn.closest('form');
      if(context && context.tagName === 'FORM'){
        log('Single-Mode: Generic Form gefunden', context);
        return context;
      }

      context = document.querySelector('form[name="adminForm"]');
      if(context){
        log('Single-Mode: Form gefunden via querySelector', context);
        return context;
      }

      context = document.querySelector('form#module-form');
      if(context){
        log('Single-Mode: Module-Form gefunden', context);
        return context;
      }

      // Fallback: Suche nach Container mit Joomla-Formular-Klassen
      context = btn.closest('.options-form') ||
                btn.closest('[id*="jform"]') ||
                btn.closest('.control-group');

      if(context){
        log('Single-Mode: Container gefunden', {tag: context.tagName, id: context.id});
        return context;
      }

      // Letzter Fallback: document (aber NIE btn selbst!)
      log('Single-Mode: Fallback auf document');
      return document;

    } else {
      // Multi Mode: Die aktuelle Scene-Row
      // WICHTIG: Suche die Scene-Row, die den Button enthält UND ein scene_image Feld hat

      // Methode 1: Finde die nächste übergeordnete subform-repeatable-group die [scenes] enthält
      var current = btn;
      var maxDepth = 10;
      var depth = 0;

      while(current && current !== document.body && depth < maxDepth){
        depth++;
        current = current.parentElement;

        if(!current) break;

        // Prüfe ob dieses Element eine Scene-Row ist
        var isSceneRow = current.classList && (
                        current.classList.contains('subform-repeatable-group') ||
                        current.classList.contains('js-subform-repeatable-group') ||
                        current.classList.contains('subform-repeatable-item') ||
                        current.classList.contains('subform-repeatable__group'));

        if(isSceneRow){
          // Prüfe ob diese Row ein scene_image Feld hat (dann ist es eine Scene!)
          var hasSceneImage = current.querySelector('input[name*="[scene_image]"]');

          if(hasSceneImage){
            log('✓ Multi-Mode Scene-Row gefunden (mit scene_image)', current);
            return current;
          }
        }
      }

      // Fallback: Klassische Methode
      var sels = ['.subform-repeatable-group','.js-subform-repeatable-group'];
      for(var i=0;i<sels.length;i++){
        var r = btn.closest(sels[i]);
        if(r) {
          log('Multi-Mode Scene-Row gefunden (Fallback)', r);
          return r;
        }
      }

      var lastFallback = btn.closest('[data-base-name*="[scenes]"]') ||
                        btn.closest('.joomla-field-subform') ||
                        document; // NIE btn zurückgeben!
      log('Multi-Mode Last-Fallback Context', {tag: lastFallback.tagName});
      return lastFallback;
    }
  }

  // ========== HOTSPOT ROWS FINDER ==========
  function findHotspotRows(context, isSingle){
    log('Suche Hotspot-Zeilen', {
      isSingle: isSingle,
      contextTag: context ? context.tagName : 'n/a'
    });

    var selector;

    if(isSingle){
      // Single Mode: Suche nach [single_hotspots]
      selector = 'input[name*="[single_hotspots]"][name*="[pitch]"]';
    } else {
      // Multi Mode: Suche nach [hotspots] (aber NICHT [single_hotspots])
      selector = 'input[name*="[hotspots]"][name*="[pitch]"]';
    }

    log('Verwende Selector', selector);

    var pitchInputs = Array.from(context.querySelectorAll(selector));
    log('Gefundene Pitch-Inputs', {count: pitchInputs.length});

    // Filtere Single-Hotspots aus Multi-Mode aus
    if(!isSingle){
      var before = pitchInputs.length;
      pitchInputs = pitchInputs.filter(function(inp){
        var keep = inp.name.indexOf('[single_hotspots]') === -1;
        if(!keep) log('Filtere Single-Hotspot Input aus', inp.name);
        return keep;
      });
      log('Nach Filter', {before: before, after: pitchInputs.length});
    }

    var rows = [];

    pitchInputs.forEach(function(inp, idx){
      var row = inp.closest('.subform-repeatable-group, .subform-repeatable-item, .subform-repeatable__group');
      if(row){
        rows.push(row);
        log('Zeile ' + (idx+1) + ' gefunden', {
          inputName: inp.name,
          rowTag: row.tagName,
          rowClass: row.className
        });
      } else {
        warn('Keine Zeile für Input gefunden', inp.name);
      }
    });

    log('✓ Hotspot-Zeilen gesamt', {count: rows.length, isSingle: isSingle});
    return rows;
  }

  // ========== HOTSPOT CONTAINER FINDER ==========
  function getHotspotsContainer(context, isSingle){
    log('Suche Hotspot-Container', {
      isSingle: isSingle,
      contextTag: context.tagName,
      contextId: context.id,
      contextClass: context.className
    });

    var container = null;

    if(isSingle){
      // Single Mode: [single_hotspots] Container
      container = context.querySelector('[data-base-name*="[single_hotspots]"]');
      log('Single: Versuch 1 (data-base-name)', container);

      if(!container){
        var input = context.querySelector('input[name*="[single_hotspots]"]');
        if(input){
          container = input.closest('.subform-repeatable, .joomla-field-subform, .control-group');
          log('Single: Versuch 2 (via input)', container);
        }
      }
    } else {
      // Multi Mode: [hotspots] Container INNERHALB des Context
      // WICHTIG: Nicht das gesamte Dokument durchsuchen, sondern nur innerhalb context!

      log('Multi: Suche [hotspots] Container in Context');

      // Versuch 1: data-base-name
      var candidates = Array.from(context.querySelectorAll('[data-base-name*="[hotspots]"]'));
      log('Multi: Gefundene Kandidaten via data-base-name', {count: candidates.length});

      for(var i = 0; i < candidates.length; i++){
        var c = candidates[i];
        var baseName = c.getAttribute('data-base-name') || '';
        log('Multi: Prüfe Kandidat', {baseName: baseName});

        // Muss [hotspots] enthalten, aber NICHT [single_hotspots]
        if(baseName.indexOf('[hotspots]') >= 0 && baseName.indexOf('[single_hotspots]') === -1){
          container = c;
          log('Multi: Container gefunden via data-base-name', container);
          break;
        }
      }

      // Versuch 2: via Input-Feld
      if(!container){
        log('Multi: Versuch 2 - Suche via Input');
        var inputs = Array.from(context.querySelectorAll('input[name*="[hotspots]"]'));
        log('Multi: Gefundene Inputs', {count: inputs.length});

        for(var j = 0; j < inputs.length; j++){
          var inp = inputs[j];
          var name = inp.name || '';
          log('Multi: Prüfe Input', {name: name});

          if(name.indexOf('[hotspots]') >= 0 && name.indexOf('[single_hotspots]') === -1){
            container = inp.closest('.subform-repeatable, .joomla-field-subform, .control-group');
            log('Multi: Container gefunden via Input', container);
            break;
          }
        }
      }

      // Versuch 3: Suche ein Element mit Klasse "joomla-field-subform" das Hotspot-Inputs enthält
      if(!container){
        log('Multi: Versuch 3 - Suche .joomla-field-subform mit hotspot inputs');
        var subforms = Array.from(context.querySelectorAll('.joomla-field-subform, .subform-repeatable'));

        for(var k = 0; k < subforms.length; k++){
          var sf = subforms[k];
          var hasHotspotInput = sf.querySelector('input[name*="[hotspots]"]:not([name*="[single_hotspots]"])');

          if(hasHotspotInput){
            container = sf;
            log('Multi: Container gefunden via subform mit input', container);
            break;
          }
        }
      }
    }

    if(container){
      log('✓✓✓ Container gefunden!', {
        tag: container.tagName,
        id: container.id,
        class: container.className,
        baseName: container.getAttribute('data-base-name')
      });
      makeVisible(container);
    } else {
      err('✗✗✗ Container NICHT gefunden!', {
        contextTag: context.tagName,
        contextClass: context.className,
        isSingle: isSingle
      });
    }

    return container;
  }

  // ========== VISIBILITY HELPER ==========
  function makeVisible(element){
    var current = element;
    while(current && current !== document.body){
      if(current.classList && current.classList.contains('hidden')){
        current.classList.remove('hidden');
        if(current.style) current.style.display = '';
      }
      if(current.hasAttribute && current.hasAttribute('hidden')){
        current.removeAttribute('hidden');
      }
      current = current.parentElement;
    }
  }

  // ========== EMPTY ROW FINDER ==========
  function findEmptyRow(context, isSingle){
    var rows = findHotspotRows(context, isSingle);
    for(var i=0;i<rows.length;i++){
      var pf = rows[i].querySelector('input[name*="[pitch]"]');
      var yf = rows[i].querySelector('input[name*="[yaw]"]');
      if(pf && yf && !pf.value && !yf.value) {
        log('Leere Zeile gefunden', rows[i]);
        return rows[i];
      }
    }
    return null;
  }

  // ========== ADD BUTTON FINDER ==========
  function findAddButton(container){
    if(!container) return null;

    var sels = [
      'button.button-add','a.button-add',
      'button.js-subform-add','a.js-subform-add',
      '.group-add',
      'button[onclick*="return"]',
      'button[aria-label*="Hinzufügen"]',
      'button[title*="Hinzufügen"]',
      'button[aria-label*="Add"]',
      'button[title*="Add"]'
    ];

    for(var i=0;i<sels.length;i++){
      try {
        var b = container.querySelector(sels[i]);
        if(b) {
          log('Add-Button gefunden mit: ' + sels[i], b);
          return b;
        }
      } catch(e) {}
    }

    warn('Kein Add-Button gefunden!');
    return null;
  }

  // ========== FIELD VALUE SETTER ==========
  function setFieldValue(input, val){
    if(!input) return false;

    log('Setze Feld', {name: input.name, value: val});

    input.focus();
    input.value = val;

    // Events
    var events = ['input', 'change', 'blur', 'keyup'];
    events.forEach(function(evt){
      try {
        input.dispatchEvent(new Event(evt, {bubbles:true, cancelable:true}));
      } catch(e) {}
    });

    // jQuery
    if(typeof jQuery !== 'undefined'){
      try {
        jQuery(input).val(val).trigger('change').trigger('input').trigger('blur');
      } catch(e) {}
    }

    // Visual Feedback
    input.style.transition = 'all 0.3s';
    input.style.backgroundColor = '#d4edda';
    input.style.borderColor = '#28a745';
    setTimeout(function(){
      input.style.backgroundColor = '';
      input.style.borderColor = '';
    }, 2000);

    return true;
  }

  // ========== APPLY HOTSPOT (MAIN LOGIC) ==========
  async function applyHotspot(context, pitch, yaw, isSingle){
    pitch = String(pitch);
    yaw = String(yaw);

    log('=== STARTE HOTSPOT-ÜBERNAHME ===', {pitch: pitch, yaw: yaw, isSingle: isSingle});

    // 1) Leere Zeile?
    var empty = findEmptyRow(context, isSingle);
    if(empty){
      log('Leere Zeile gefunden, fülle aus');
      var p1 = empty.querySelector('input[name*="[pitch]"]');
      var y1 = empty.querySelector('input[name*="[yaw]"]');
      if(p1 && y1){
        setFieldValue(p1, pitch);
        await sleep(100);
        setFieldValue(y1, yaw);
        showConfirm('✓ Hotspot übernommen: Pitch '+pitch+', Yaw '+yaw);
        return true;
      }
    }

    // 2) Neue Zeile hinzufügen
    var container = getHotspotsContainer(context, isSingle);
    if(!container){
      err('Container nicht gefunden!');
      showError('✗ Container nicht gefunden. Bitte manuell: Pitch '+pitch+', Yaw '+yaw);
      return false;
    }

    var add = findAddButton(container);
    if(!add){
      err('Add-Button nicht gefunden!');
      showError('✗ Add-Button nicht gefunden. Bitte manuell: Pitch '+pitch+', Yaw '+yaw);
      return false;
    }

    log('Klicke Add-Button', add);
    var beforeCount = findHotspotRows(context, isSingle).length;
    log('Zeilen vor Klick:', beforeCount);

    add.click();

    // 3) Warte auf neue Zeile
    var newRow = await waitForNewRow(context, isSingle, beforeCount);

    if(!newRow){
      err('Neue Zeile nicht erkannt!');
      showError('✗ Neue Zeile nicht erkannt. Bitte manuell: Pitch '+pitch+', Yaw '+yaw);
      return false;
    }

    log('✓ Neue Zeile erstellt', newRow);

    // 4) Warte bis Felder bereit sind
    await sleep(800);

    // 5) Finde Felder
    var p2 = newRow.querySelector('input[name*="[pitch]"]');
    var y2 = newRow.querySelector('input[name*="[yaw]"]');

    if(!p2 || !y2){
      err('Felder nicht gefunden!');
      showError('✗ Felder nicht gefunden. Bitte manuell: Pitch '+pitch+', Yaw '+yaw);
      return false;
    }

    log('Felder gefunden', {pitch: p2.name, yaw: y2.name});

    // 6) Warte bis Felder bereit
    var attempts = 0;
    while((p2.disabled || p2.readOnly) && attempts < 20){
      log('Felder noch nicht bereit, warte...');
      await sleep(200);
      attempts++;
    }

    // 7) Setze Werte
    setFieldValue(p2, pitch);
    await sleep(200);
    setFieldValue(y2, yaw);

    // 8) Validierung
    await sleep(200);

    if(p2.value === pitch && y2.value === yaw){
      log('✓✓✓ ERFOLGREICH ✓✓✓');
      showConfirm('✓ Hotspot erfolgreich hinzugefügt: Pitch '+pitch+', Yaw '+yaw);

      // Visual Feedback
      try {
        newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        newRow.style.transition = 'background-color 0.5s';
        newRow.style.backgroundColor = '#d4edda';
        setTimeout(function(){
          newRow.style.backgroundColor = '';
        }, 2500);
      } catch(e) {}

      return true;
    } else {
      // Zweiter Versuch
      warn('Erster Versuch fehlgeschlagen, versuche nochmal...');

      p2.value = pitch;
      y2.value = yaw;
      p2.dispatchEvent(new Event('change', {bubbles:true}));
      y2.dispatchEvent(new Event('change', {bubbles:true}));

      await sleep(200);

      if(p2.value === pitch && y2.value === yaw){
        log('✓ Zweiter Versuch erfolgreich!');
        showConfirm('✓ Hotspot hinzugefügt (2. Versuch): Pitch '+pitch+', Yaw '+yaw);
        return true;
      } else {
        err('✗ Beide Versuche fehlgeschlagen!');
        showError('✗ Felder konnten nicht befüllt werden. Bitte manuell: Pitch '+pitch+', Yaw '+yaw);
        return false;
      }
    }
  }

  // ========== WAIT FOR NEW ROW ==========
  function waitForNewRow(context, isSingle, beforeCount){
    return new Promise(function(resolve){
      var maxAttempts = 50;
      var attempts = 0;

      var checkInterval = setInterval(function(){
        attempts++;
        var afterCount = findHotspotRows(context, isSingle).length;

        log('Warte auf neue Zeile... Versuch ' + attempts + ', Zeilen: ' + afterCount);

        if(afterCount > beforeCount){
          clearInterval(checkInterval);
          var rows = findHotspotRows(context, isSingle);
          var newRow = rows[rows.length - 1];
          log('Neue Zeile erkannt!', newRow);
          resolve(newRow);
        }

        if(attempts >= maxAttempts){
          clearInterval(checkInterval);
          warn('Timeout beim Warten auf neue Zeile');
          resolve(null);
        }
      }, 100);
    });
  }

  // ========== READ EXISTING HOTSPOTS ==========
  function readExistingHotspots(context, isSingle){
    var rows = findHotspotRows(context, isSingle);
    var hotspots = [];

    rows.forEach(function(row, idx){
      var pitchInput = row.querySelector('input[name*="[pitch]"]');
      var yawInput = row.querySelector('input[name*="[yaw]"]');
      var textInput = row.querySelector('input[name*="[text]"]');
      var targetInput = row.querySelector('input[name*="[target]"]');
      var typeSelect = row.querySelector('select[name*="[type]"]');

      if(!pitchInput || !yawInput) return;

      var pitch = parseFloat(pitchInput.value);
      var yaw = parseFloat(yawInput.value);

      if(isNaN(pitch) || isNaN(yaw)) return;

      hotspots.push({
        index: idx + 1,
        pitch: pitch,
        yaw: yaw,
        text: textInput ? textInput.value : '',
        target: targetInput ? targetInput.value : '',
        type: typeSelect ? typeSelect.value : 'scene'
      });
    });

    log('Hotspots gelesen:', hotspots);
    return hotspots;
  }

  // ========== UI HELPERS ==========
  function sleep(ms){ return new Promise(function(r){ setTimeout(r, ms); }); }

  function showConfirm(text){
    var bar = ensureBar();
    bar.textContent = text;
    bar.style.background = 'rgba(46,125,50,.95)';
    bar.style.color = '#fff';
    bar.style.display = 'block';
    clearTimeout(showConfirm.__to);
    showConfirm.__to = setTimeout(function(){ bar.style.display='none'; }, 5000);
  }

  function showError(text){
    var bar = ensureBar();
    bar.textContent = text;
    bar.style.background = 'rgba(211,47,47,.95)';
    bar.style.color = '#fff';
    bar.style.display = 'block';
    clearTimeout(showConfirm.__to);
    showConfirm.__to = setTimeout(function(){ bar.style.display='none'; }, 7000);
  }

  function ensureBar(){
    var id = 'hs-confirm-bar';
    var el = document.getElementById(id);
    if(el) return el;

    el = document.createElement('div');
    el.id = id;
    el.style.cssText = 'position:fixed;top:20px;right:20px;max-width:400px;padding:12px 16px;border-radius:6px;font-size:14px;display:none;font-weight:600;z-index:999999;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
    document.body.appendChild(el);
    return el;
  }

  // ========== MODAL & VIEWER ==========
  var state = { capture:false, context:null, viewer:null, isSingle:false };

  function ensureModal(){
    var modal = document.getElementById('hotspotEditorModal');
    if(modal) return modal;

    modal = document.createElement('div');
    modal.id = 'hotspotEditorModal';
    modal.className = 'hotspot-editor-modal';
    modal.innerHTML =
      '<div class="hotspot-editor-backdrop"></div>'+
      '<div class="hotspot-editor-dialog">'+
        '<div class="hotspot-editor-header">'+
          '<h3>Hotspot-Editor</h3>'+
          '<div class="hotspot-editor-tools">'+
            '<button type="button" class="btn btn-success js-hs-add"><i class="fa fa-plus"></i> Hotspot hinzufügen</button>'+
            '<button type="button" class="btn btn-info js-hs-refresh" style="margin-left:8px;"><i class="fa fa-sync"></i> Aktualisieren</button>'+
          '</div>'+
          '<button type="button" class="close" aria-label="Close">&times;</button>'+
        '</div>'+
        '<div class="hotspot-editor-body">'+
          '<div id="hotspotViewer" class="hotspot-viewer" style="min-height:60vh;height:60vh;width:100%;"></div>'+
          '<div id="hs-inline-hint" style="display:none;position:absolute;left:12px;bottom:12px;right:12px;background:rgba(33,150,243,.9);color:#fff;padding:10px;border-radius:8px;font-size:13px;z-index:100;"></div>'+
        '</div>'+
      '</div>';
    document.body.appendChild(modal);

    function close(){ modal.classList.remove('open'); disarm(); destroyViewer(); }
    modal.querySelector('.close').addEventListener('click', close);
    modal.querySelector('.hotspot-editor-backdrop').addEventListener('click', close);
    document.addEventListener('keydown', function(ev){
      if(ev.key==='Escape' && modal.classList.contains('open')){ ev.preventDefault(); close(); }
    });

    modal.querySelector('.js-hs-add').addEventListener('click', function(e){
      e.preventDefault(); e.stopPropagation(); arm();
    });

    modal.querySelector('.js-hs-refresh').addEventListener('click', function(e){
      e.preventDefault(); e.stopPropagation();
      if(state.viewer) refreshHotspots();
    });

    return modal;
  }

  function arm(){
    state.capture = true;
    document.body.style.cursor = 'crosshair';
    var b = document.querySelector('#hotspotEditorModal .js-hs-add');
    if(b){ b.classList.add('is-armed'); b.disabled = true; b.innerHTML = '<i class="fa fa-crosshairs"></i> Klicke ins Panorama…'; }
    var hint = document.getElementById('hs-inline-hint');
    if(hint){ hint.textContent = 'Klicke ins Panorama, um die Hotspot-Position zu setzen…'; hint.style.display='block'; }
  }

  function disarm(){
    state.capture = false;
    document.body.style.cursor = '';
    var b = document.querySelector('#hotspotEditorModal .js-hs-add');
    if(b){ b.classList.remove('is-armed'); b.disabled = false; b.innerHTML = '<i class="fa fa-plus"></i> Hotspot hinzufügen'; }
    var hint = document.getElementById('hs-inline-hint'); if(hint) hint.style.display='none';
  }

  function destroyViewer(){ if(state.viewer){ try{ state.viewer.destroy(); }catch(_){ } } state.viewer=null; }

  function refreshHotspots(){
    if(!state.viewer || !state.context) return;

    log('Aktualisiere Hotspots im Viewer');
    var existingHotspots = readExistingHotspots(state.context, state.isSingle);

    var customSpots = document.querySelectorAll('.editor-hotspot-marker');
    customSpots.forEach(function(spot){ spot.remove(); });

    existingHotspots.forEach(function(hs){
      addVisualHotspot(hs);
    });

    log('Hotspots aktualisiert', {count: existingHotspots.length});
  }

  function addVisualHotspot(hotspot){
    if(!state.viewer) return;

    try{
      var markerDiv = document.createElement('div');
      markerDiv.className = 'editor-hotspot-marker';
      markerDiv.setAttribute('data-index', hotspot.index);

      var numberSpan = document.createElement('span');
      numberSpan.className = 'hotspot-number';
      numberSpan.textContent = hotspot.index;
      markerDiv.appendChild(numberSpan);

      var tooltip = document.createElement('div');
      tooltip.className = 'hotspot-editor-tooltip';
      var tooltipContent = [];
      tooltipContent.push('<strong>#' + hotspot.index + '</strong>');
      if(hotspot.text) tooltipContent.push('Text: ' + hotspot.text);
      if(hotspot.target) tooltipContent.push('Ziel: ' + hotspot.target);
      tooltipContent.push('Typ: ' + (hotspot.type === 'info' ? 'Info' : 'Szene'));
      tooltip.innerHTML = tooltipContent.join('<br>');
      markerDiv.appendChild(tooltip);

      state.viewer.addHotSpot({
        type: 'custom',
        pitch: hotspot.pitch,
        yaw: hotspot.yaw,
        cssClass: 'editor-hotspot-wrapper',
        createTooltipFunc: function(hotSpotDiv){
          hotSpotDiv.appendChild(markerDiv);
        }
      });

      log('Visueller Hotspot hinzugefügt', {index: hotspot.index});
    }catch(e){
      warn('Fehler beim Hinzufügen des visuellen Hotspots', e);
    }
  }

  function initViewer(url){
    var host = document.getElementById('hotspotViewer');
    if(!host) return null;

    destroyViewer();
    if(typeof pannellum==='undefined' || !pannellum.viewer){
      err('pannellum.viewer not available');
      return null;
    }

    try{
      log('Initialisiere Viewer mit URL', url);

      var v = pannellum.viewer('hotspotViewer', {
        type:'equirectangular',
        panorama:url,
        autoLoad:true,
        showControls:true,
        compass:false,
        pitch:0,
        yaw:0,
        hotSpots: []
      });

      v.on('mouseup', function(ev){
        if(!state.capture) return;
        disarm();
        var c = v.mouseEventToCoords(ev);
        if(!c) return;
        var pitch = c[0].toFixed(3), yaw = c[1].toFixed(3);
        log('Hotspot-Koordinaten erfasst', {pitch: pitch, yaw: yaw});

        applyHotspot(state.context, pitch, yaw, state.isSingle).then(function(ok){
          if(ok){
            log('✓ Hotspot erfolgreich übernommen');
            setTimeout(function(){ refreshHotspots(); }, 1500);
          } else {
            err('Übernahme fehlgeschlagen');
          }
        });
      });

      v.on('load', function(){
        log('Viewer geladen');
        setTimeout(function(){
          var existingHotspots = readExistingHotspots(state.context, state.isSingle);
          existingHotspots.forEach(function(hs){
            addVisualHotspot(hs);
          });
        }, 300);
      });

      state.viewer = v;
      return v;
    }catch(e){
      err('Error initializing viewer: '+e.message);
      return null;
    }
  }

  // ========== BUTTON HANDLER ==========
  document.addEventListener('click', function(e){
    var t = e.target; if(!t) return;
    var btn = t.closest && t.closest('.hotspot-editor-button');
    if(!btn) return;

    e.preventDefault(); e.stopPropagation();

    var isSingle = isSingleMode(btn);
    var context = findContext(btn);

    log('=== HOTSPOT-EDITOR ÖFFNEN ===', {isSingle: isSingle, context: context});

    if(!context){
      alert('Kontext konnte nicht gefunden werden.');
      return;
    }

    state.context = context;
    state.isSingle = isSingle;

    // Bild-Feld finden - MIT BESSERER SUCHE
    var imageField = null;

    if(isSingle){
      imageField = context.querySelector('input[name*="[single_image]"]');
      log('Single-Mode: Suche [single_image]', imageField);
    } else {
      // Multi-Mode: Suche NUR innerhalb des Context (der Scene-Row)
      imageField = context.querySelector('input[name*="[scene_image]"]');
      log('Multi-Mode: Suche [scene_image] in Context', {
        field: imageField,
        fieldValue: imageField ? imageField.value : 'n/a',
        contextTag: context.tagName,
        contextClass: context.className
      });
    }

    if(!imageField){
      alert('Bild-Feld konnte nicht gefunden werden. Kontext: ' + (context.tagName || 'unknown'));
      return;
    }

    if(!imageField.value || imageField.value.trim() === ''){
      alert('Bitte zuerst ein Panoramabild auswählen.');
      return;
    }

    var imageUrl = resolveMediaUrl(imageField.value);
    log('Bild-URL aufgelöst', {
      rawValue: imageField.value,
      resolvedUrl: imageUrl
    });

    if(!imageUrl || imageUrl === ''){
      alert('Bild-URL konnte nicht aufgelöst werden: ' + imageField.value);
      return;
    }

    var modal = ensureModal();
    modal.classList.add('open');
    initViewer(imageUrl);
    disarm();
  }, true);

  document.addEventListener('DOMContentLoaded', function(){
    log('Hotspot-Editor script ready');
  });

})();
