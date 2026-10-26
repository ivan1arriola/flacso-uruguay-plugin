/**
 * Editor de Tablas de precios: filas, columnas propias y vista previa.
 * Las celdas de columnas propias se sincronizan desde la lista de columnas.
 */
document.addEventListener('DOMContentLoaded', function () {
  const editor = document.querySelector('[data-price-editor]');
  if (!editor) return;

  const rowsContainer = editor.querySelector('[data-price-rows]');
  const columnsContainer = editor.querySelector('[data-extra-columns]');
  const rowTemplate = editor.querySelector('[data-price-row-template]');
  const columnTemplate = editor.querySelector('[data-column-template]');
  const cellTemplate = editor.querySelector('[data-extra-cell-template]');
  const usdToggle = editor.querySelector('[data-usd-toggle]');
  const count = editor.querySelector('[data-row-count]');
  const emptyState = editor.querySelector('[data-empty-state]');
  const preview = editor.querySelector('[data-price-preview]');
  const noteText = editor.querySelector('[name="flacso_price_note_text"]');
  const noteFinePrint = editor.querySelector('[name="flacso_price_note_fine_print"]');

  const rows = () => Array.from(rowsContainer.querySelectorAll('[data-price-row]'));
  const columns = () => Array.from(columnsContainer.querySelectorAll('[data-extra-column]')).map((element) => ({
    id: element.dataset.extraColumn,
    title: element.querySelector('[data-column-title]').value.trim(),
  }));
  const fromTemplate = (template, replacements) => {
    let html = template.innerHTML;
    Object.entries(replacements).forEach(([token, value]) => { html = html.replaceAll(token, value); });
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    return wrapper.firstElementChild;
  };
  const columnLabel = (column) => column.title || 'Columna sin nombre';

  /** Deja en cada fila exactamente una celda por columna propia, en orden. */
  const syncCells = () => {
    const currentColumns = columns();
    rows().forEach((row) => {
      const values = row.querySelector('[data-row-values]');
      const cells = new Map(Array.from(values.querySelectorAll('[data-extra-cell]')).map((cell) => [cell.dataset.extraCell, cell]));
      currentColumns.forEach((column) => {
        let cell = cells.get(column.id);
        if (!cell) cell = fromTemplate(cellTemplate, { __COL__: column.id });
        cell.querySelector('[data-extra-label]').textContent = columnLabel(column);
        values.appendChild(cell);
        cells.delete(column.id);
      });
      cells.forEach((cell) => cell.remove());
    });
  };

  const renumber = () => {
    rows().forEach((row, index) => {
      row.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/flacso_price_rows\[[^\]]+\]/, `flacso_price_rows[${index}]`);
      });
      const radio = row.querySelector('input[name="flacso_price_featured"]');
      if (radio) radio.value = String(index);
    });
    columnsContainer.querySelectorAll('[data-extra-column]').forEach((column, index) => {
      column.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/flacso_price_columns\[[^\]]+\]/, `flacso_price_columns[${index}]`);
      });
    });
  };

  const field = (row, name) => row.querySelector(`[name$="[${name}]"]`)?.value.trim() || '';

  /** Arma la tabla con nodos de texto: nada de lo escrito se interpreta como HTML. */
  const renderPreview = () => {
    if (!preview) return;
    const showUsd = Boolean(usdToggle?.checked);
    const currentColumns = columns();
    const element = (tag, className, text) => {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text !== undefined) node.textContent = text;
      return node;
    };
    const header = (title, subtitle) => {
      const th = element('th', '', title);
      if (subtitle) th.appendChild(element('span', '', subtitle));
      return th;
    };
    const multiline = (parent, text) => {
      text.split('\n').forEach((line, index) => {
        if (index) parent.appendChild(document.createElement('br'));
        parent.appendChild(document.createTextNode(line));
      });
    };

    const filled = rows().filter((row) => Array.from(row.querySelectorAll('input[type="text"]')).some((input) => input.value.trim() !== ''));
    preview.replaceChildren();
    if (!filled.length) {
      preview.appendChild(element('p', 'description', 'Completá al menos una fila para ver la tabla.'));
      return;
    }

    const table = element('table', 'flacso-price-preview__table');
    const headRow = table.createTHead().insertRow();
    headRow.appendChild(header('Concepto'));
    headRow.appendChild(header('Valor en $', 'residentes en Uruguay'));
    if (showUsd) headRow.appendChild(header('Valor en U$S', 'residentes en el exterior'));
    currentColumns.forEach((column) => headRow.appendChild(header(columnLabel(column))));

    const body = table.createTBody();
    filled.forEach((row) => {
      const tr = body.insertRow();
      if (row.querySelector('input[name="flacso_price_featured"]')?.checked) tr.className = 'is-highlighted';
      const concept = element('td');
      concept.dataset.label = 'Concepto';
      concept.appendChild(element('strong', '', field(row, 'concepto')));
      if (field(row, 'detalle')) concept.appendChild(element('small', '', field(row, 'detalle')));
      tr.appendChild(concept);

      const cells = [['Valor en pesos', field(row, 'uyu')]];
      if (showUsd) cells.push(['Valor en dólares', field(row, 'usd')]);
      currentColumns.forEach((column) => {
        cells.push([columnLabel(column), row.querySelector(`[data-extra-cell="${column.id}"] input`)?.value.trim() || '']);
      });
      cells.forEach(([label, value]) => {
        const td = element('td', '', value);
        td.dataset.label = label;
        tr.appendChild(td);
      });
    });
    preview.appendChild(table);

    const text = noteText?.value.trim() || '';
    const finePrint = noteFinePrint?.value.trim() || '';
    if (text || finePrint) {
      const note = element('div', 'flacso-price-preview__note');
      if (text) multiline(note.appendChild(element('p')), text);
      if (finePrint) multiline(note.appendChild(element('small')), finePrint);
      preview.appendChild(note);
    }
  };

  const refresh = () => {
    syncCells();
    renumber();
    const currentRows = rows();
    count.textContent = `${currentRows.length} ${currentRows.length === 1 ? 'fila' : 'filas'}`;
    emptyState.hidden = currentRows.length > 0;
    editor.dataset.showUsd = usdToggle && usdToggle.checked ? '1' : '0';

    currentRows.forEach((row, index) => {
      const up = row.querySelector('[data-move-row="up"]');
      const down = row.querySelector('[data-move-row="down"]');
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === currentRows.length - 1;
      row.classList.toggle('is-featured', Boolean(row.querySelector('input[name="flacso_price_featured"]')?.checked));
    });
    renderPreview();
  };

  editor.querySelector('[data-add-price-row]')?.addEventListener('click', () => {
    const row = fromTemplate(rowTemplate, { __INDEX__: String(rows().length) });
    if (!row) return;
    rowsContainer.appendChild(row);

    if (rows().length === 1) {
      const radio = row.querySelector('input[name="flacso_price_featured"]');
      if (radio) radio.checked = true;
    }

    refresh();
    row.querySelector('input[type="text"]')?.focus();
  });

  editor.querySelector('[data-add-column]')?.addEventListener('click', () => {
    const id = `col_${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
    const column = fromTemplate(columnTemplate, { __COL__: id, __CINDEX__: String(columns().length) });
    if (!column) return;
    columnsContainer.appendChild(column);
    refresh();
    column.querySelector('[data-column-title]')?.focus();
  });

  columnsContainer.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-column]');
    if (!removeButton) return;
    const column = removeButton.closest('[data-extra-column]');
    const id = column?.dataset.extraColumn;
    const hasValues = Array.from(rowsContainer.querySelectorAll(`[data-extra-cell="${id}"] input`)).some((input) => input.value.trim() !== '');
    if (hasValues && !window.confirm('Esta columna tiene valores cargados. ¿Quitarla de todas las filas?')) return;
    column?.remove();
    refresh();
  });

  rowsContainer.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-price-row]');
    if (removeButton) {
      const row = removeButton.closest('[data-price-row]');
      const wasFeatured = row?.querySelector('input[name="flacso_price_featured"]')?.checked;
      row?.remove();
      if (wasFeatured) {
        const firstRadio = rowsContainer.querySelector('input[name="flacso_price_featured"]');
        if (firstRadio) firstRadio.checked = true;
      }
      refresh();
      return;
    }

    const moveButton = event.target.closest('[data-move-row]');
    if (!moveButton) return;
    const row = moveButton.closest('[data-price-row]');
    if (!row) return;

    if (moveButton.dataset.moveRow === 'up' && row.previousElementSibling) {
      rowsContainer.insertBefore(row, row.previousElementSibling);
    }
    if (moveButton.dataset.moveRow === 'down' && row.nextElementSibling) {
      rowsContainer.insertBefore(row.nextElementSibling, row);
    }
    refresh();
    moveButton.focus();
  });

  rowsContainer.addEventListener('change', (event) => {
    if (event.target.matches('input[name="flacso_price_featured"]')) refresh();
  });
  rowsContainer.addEventListener('input', renderPreview);
  columnsContainer.addEventListener('input', () => { syncCells(); renderPreview(); });
  [noteText, noteFinePrint].forEach((input) => input?.addEventListener('input', renderPreview));
  usdToggle?.addEventListener('change', refresh);
  refresh();
});
