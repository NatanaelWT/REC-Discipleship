<script>
(function () {
  var stewardKnownNames = {!! $historicalNamesJson !!};
  var stewardCountList = document.querySelector('[data-steward-count-list]');
  var stewardChipStyle = 'display:inline-block;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#0f172a;font-size:13px';
  var splitStewardNames = function (value) {
    var result = [];
    String(value || '').split(/\r\n?|\n/).forEach(function (part) {
      var name = String(part || '').trim();
      if (name !== '') {
        result.push(name);
      }
    });
    return result;
  };
  var compareStewardNames = function (a, b) {
    try {
      return String(a).localeCompare(String(b), 'id', { sensitivity: 'base' });
    } catch (error) {
      var lowerA = String(a).toLowerCase();
      var lowerB = String(b).toLowerCase();
      if (lowerA === lowerB) {
        return 0;
      }
      return lowerA < lowerB ? -1 : 1;
    }
  };
  var renderStewardCounts = function () {
    if (!stewardCountList) {
      return;
    }
    var counts = {};
    var nameMap = {};
    if (Array.isArray(stewardKnownNames)) {
      stewardKnownNames.forEach(function (name) {
        var label = String(name || '').trim();
        if (label !== '') {
          nameMap[label] = true;
        }
      });
    }
    document.querySelectorAll('[data-steward-count-field="1"]').forEach(function (field) {
      splitStewardNames(field.value).forEach(function (name) {
        counts[name] = (counts[name] || 0) + 1;
        nameMap[name] = true;
      });
    });
    var names = Object.keys(nameMap);
    names.sort(function (a, b) {
      var countDiff = (counts[b] || 0) - (counts[a] || 0);
      if (countDiff !== 0) {
        return countDiff;
      }
      return compareStewardNames(a, b);
    });
    stewardCountList.innerHTML = '';
    if (names.length === 0) {
      var empty = document.createElement('span');
      empty.setAttribute('data-steward-count-empty', '1');
      empty.style.cssText = 'color:#64748b;font-size:13px';
      empty.textContent = 'Belum ada riwayat penatalayan.';
      stewardCountList.appendChild(empty);
      return;
    }
    names.forEach(function (name) {
      var chip = document.createElement('span');
      chip.className = 'worship-steward-count-chip';
      chip.setAttribute('data-steward-name', name);
      chip.style.cssText = stewardChipStyle;
      chip.textContent = name + ' (' + String(counts[name] || 0) + ')';
      stewardCountList.appendChild(chip);
    });
  };
  document.querySelectorAll('[data-steward-count-field="1"]').forEach(function (field) {
    field.addEventListener('input', renderStewardCounts);
    field.addEventListener('change', renderStewardCounts);
  });
  renderStewardCounts();
  var fields = document.querySelectorAll('textarea.worship-steward-cell');
  var resizeField = function (field) {
    if (!field) {
      return;
    }
    field.style.height = 'auto';
    field.style.height = field.scrollHeight + 'px';
  };
  if (fields && fields.length > 0) {
    fields.forEach(function (field) {
      resizeField(field);
      field.addEventListener('input', function () {
        resizeField(field);
      });
    });
  }
  var trainingInputs = document.querySelectorAll('.worship-steward-training-input');
  var formatTrainingDate = function (value) {
    if (!value) {
      return '';
    }
    var parts = value.split('-');
    if (parts.length !== 3) {
      return value;
    }
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10);
    var day = parseInt(parts[2], 10);
    if (!year || !month || !day) {
      return value;
    }
    var date = new Date(Date.UTC(year, month - 1, day));
    if (Number.isNaN(date.getTime())) {
      return value;
    }
    try {
      return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(date);
    } catch (error) {
      return value;
    }
  };
  var updateTrainingPreview = function (input) {
    if (!input) {
      return;
    }
    var field = input.closest('.worship-steward-training-field');
    if (!field) {
      return;
    }
    var preview = field.querySelector('.worship-steward-training-preview');
    if (!preview) {
      return;
    }
    var formatted = formatTrainingDate(input.value);
    preview.textContent = formatted || preview.getAttribute('data-empty') || '';
  };
  if (trainingInputs && trainingInputs.length > 0) {
    trainingInputs.forEach(function (input) {
      updateTrainingPreview(input);
      input.addEventListener('input', function () {
        updateTrainingPreview(input);
      });
      input.addEventListener('change', function () {
        updateTrainingPreview(input);
      });
    });
  }
})();
</script>
