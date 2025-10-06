(function () {
  const modalId = 'mitarbeiterEditModal';
  const form = document.getElementById('mitarbeiterEditForm');
  const formFieldsContainer = document.getElementById('mitarbeiterFormFields');
  const formMessages = document.getElementById('mitarbeiterFormMessages');
  const hiddenIdField = document.getElementById('mitarbeiterEditId');
  const employeeRows = document.querySelectorAll('.dashboard-table tbody tr.mitarbeiter-row');

  if (!form || !formFieldsContainer || !hiddenIdField) {
    return;
  }

  const READONLY_FIELDS = new Set(['mitarbeiter_id', 'created_at', 'updated_at', 'erstellt_am', 'angelegt_am']);

  const STATUS_OPTIONS = [
    { value: 'Aktiv', label: 'Aktiv' },
    { value: 'Inaktiv', label: 'Inaktiv' }
  ];

  const BOOLEAN_OPTIONS = [
    { value: '1', label: 'Ja' },
    { value: '0', label: 'Nein' }
  ];

  employeeRows.forEach((row) => {
    row.addEventListener('dblclick', () => {
      const id = row.dataset.mitarbeiterId;
      if (!id) {
        return;
      }
      loadEmployee(id);
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!hiddenIdField.value) {
      return;
    }

    clearMessage();
    setSubmitting(true);

    try {
      const formData = new FormData(form);
      const payload = {};
      formData.forEach((value, key) => {
        payload[key] = value;
      });

      const response = await fetch('api/mitarbeiter_zentrale.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const result = await response.json().catch(() => null);
      if (!response.ok || !result || !result.success) {
        const message = (result && result.message) || 'Die Änderungen konnten nicht gespeichert werden.';
        showMessage('danger', message);
        setSubmitting(false);
        return;
      }

      showMessage('success', result.message || 'Die Änderungen wurden gespeichert.');
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } catch (error) {
      showMessage('danger', 'Beim Speichern ist ein Fehler aufgetreten.');
      setSubmitting(false);
    }
  });

  async function loadEmployee(id) {
    clearMessage();
    setFormDisabled(true);
    hiddenIdField.value = '';
    formFieldsContainer.innerHTML = '<p class="text-muted">Daten werden geladen …</p>';

    try {
      const response = await fetch(`api/mitarbeiter_zentrale.php?id=${encodeURIComponent(id)}`, {
        headers: {
          Accept: 'application/json'
        }
      });

      const result = await response.json().catch(() => null);
      if (!response.ok || !result || !result.success) {
        const message = (result && result.message) || 'Die Mitarbeiterdaten konnten nicht geladen werden.';
        formFieldsContainer.innerHTML = '';
        showMessage('danger', message);
        setFormDisabled(false);
        return;
      }

      renderForm(result.data, result.schema || []);
      hiddenIdField.value = result.data.mitarbeiter_id;
      setFormDisabled(false);
      openModal(modalId);
    } catch (error) {
      formFieldsContainer.innerHTML = '';
      showMessage('danger', 'Beim Laden der Mitarbeiterdaten ist ein Fehler aufgetreten.');
      setFormDisabled(false);
    }
  }

  function renderForm(data, schema) {
    const schemaMap = Array.isArray(schema)
      ? schema.reduce((accumulator, column) => {
          if (column && (column.Field || column.field)) {
            accumulator[column.Field || column.field] = column;
          }
          return accumulator;
        }, {})
      : {};

    formFieldsContainer.innerHTML = '';

    Object.entries(data || {}).forEach(([key, value]) => {
      if (READONLY_FIELDS.has(key)) {
        return;
      }

      const normalizedKey = key.toLowerCase();
      const column = schemaMap[key] || {};
      const columnType = (column.Type || column.type || '').toLowerCase();
      const isNullable = (column.Null || column.nullable || '').toUpperCase() === 'YES';
      const labelText = column.Comment || humanizeKey(key);

      const formGroup = document.createElement('div');
      formGroup.className = 'col-12 col-md-6';

      const label = document.createElement('label');
      label.className = 'form-label';
      label.setAttribute('for', `mitarbeiter_${key}`);
      label.textContent = labelText;

      const inputWrapper = document.createElement('div');
      inputWrapper.appendChild(label);

      const inputElement = createInputElement({
        key,
        value,
        columnType,
        isNullable,
        normalizedKey
      });

      inputWrapper.appendChild(inputElement);
      formGroup.appendChild(inputWrapper);
      formFieldsContainer.appendChild(formGroup);
    });
  }

  function createInputElement({ key, value, columnType, isNullable, normalizedKey }) {
    let element;

    if (key === 'status') {
      element = document.createElement('select');
      element.className = 'form-select';
      STATUS_OPTIONS.forEach((option) => {
        const optionElement = document.createElement('option');
        optionElement.value = option.value;
        optionElement.textContent = option.label;
        if (String(value || '') === option.value) {
          optionElement.selected = true;
        }
        element.appendChild(optionElement);
      });
    } else if (/tinyint\(1\)|bool|boolean/.test(columnType)) {
      element = document.createElement('select');
      element.className = 'form-select';
      const currentValue = value == null ? '' : String(value);
      const options = [...BOOLEAN_OPTIONS];
      if (isNullable) {
        options.unshift({ value: '', label: 'Nicht gesetzt' });
      }
      options.forEach((option) => {
        const optionElement = document.createElement('option');
        optionElement.value = option.value;
        optionElement.textContent = option.label;
        if (currentValue === option.value) {
          optionElement.selected = true;
        }
        element.appendChild(optionElement);
      });
    } else if (/text/.test(columnType) || /beschreibung|notiz|kommentar/.test(normalizedKey)) {
      element = document.createElement('textarea');
      element.className = 'form-control';
      element.rows = 3;
      element.value = value == null ? '' : value;
    } else {
      element = document.createElement('input');
      element.className = 'form-control';
      element.value = value == null ? '' : value;

      if (/int|decimal|double|float/.test(columnType)) {
        element.type = 'number';
        if (/decimal|double|float/.test(columnType)) {
          element.step = '0.01';
        }
      } else if (/date/.test(columnType)) {
        element.type = 'date';
      } else if (/time/.test(columnType)) {
        element.type = 'time';
      } else if (normalizedKey.includes('email')) {
        element.type = 'email';
      } else if (normalizedKey.includes('telefon') || normalizedKey.includes('phone')) {
        element.type = 'tel';
      } else if (normalizedKey.includes('farbe')) {
        element.type = 'color';
      } else {
        element.type = 'text';
      }
    }

    element.id = `mitarbeiter_${key}`;
    element.name = key;
    if (!isNullable) {
      element.required = true;
    }

    if (element instanceof HTMLInputElement && element.type === 'date' && value) {
      element.value = value;
    }

    if (element instanceof HTMLInputElement && element.type === 'number' && value !== null && value !== undefined && value !== '') {
      element.value = String(value);
    }

    return element;
  }

  function humanizeKey(key) {
    return key
      .replace(/_/g, ' ')
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function clearMessage() {
    if (!formMessages) {
      return;
    }
    formMessages.textContent = '';
    formMessages.classList.add('d-none');
    formMessages.classList.remove('alert-success', 'alert-danger');
  }

  function showMessage(type, message) {
    if (!formMessages) {
      return;
    }
    formMessages.textContent = message;
    formMessages.classList.remove('d-none', 'alert-success', 'alert-danger');
    formMessages.classList.add(type === 'success' ? 'alert-success' : 'alert-danger', 'alert');
  }

  function setSubmitting(isSubmitting) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) {
      return;
    }
    submitButton.disabled = isSubmitting;
    submitButton.dataset.originalText = submitButton.dataset.originalText || submitButton.textContent;
    submitButton.textContent = isSubmitting ? 'Speichern …' : submitButton.dataset.originalText;
  }

  function setFormDisabled(isDisabled) {
    Array.from(form.elements).forEach((element) => {
      element.disabled = isDisabled && element.type !== 'hidden';
    });
  }
})();
