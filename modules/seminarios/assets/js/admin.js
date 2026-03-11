(function ($) {
    function reindexInputs(rows, pattern, replacer) {
        rows.each(function (idx, row) {
            $(row)
                .find('input, textarea')
                .each(function (_, input) {
                    var name = $(input).attr('name');
                    if (name) {
                        $(input).attr('name', name.replace(pattern, replacer(idx)));
                    }
                });
        });
    }

    function setupEncuentros() {
        var table = $('#seminario-encuentros');
        var tbody = table.find('tbody');
        var addBtn = $('#seminario-add-row');

        function addRow() {
            var index = tbody.find('tr').length;
            var html = '' +
                '<tr>' +
                '<td><input type="date" name="_seminario_encuentros_sincronicos[' + index + '][fecha]" class="regular-text"></td>' +
                '<td><input type="time" name="_seminario_encuentros_sincronicos[' + index + '][hora_inicio]" class="regular-text"></td>' +
                '<td><input type="time" name="_seminario_encuentros_sincronicos[' + index + '][hora_fin]" class="regular-text"></td>' +
                '<td>' +
                '<select name="_seminario_encuentros_sincronicos[' + index + '][plataforma]" class="plataforma-select" style="width: 120px;">' +
                '<option value="zoom">Zoom</option>' +
                '<option value="otro">Otro</option>' +
                '</select>' +
                '<input type="text" name="_seminario_encuentros_sincronicos[' + index + '][plataforma_otro]" placeholder="Especificar..." class="regular-text" style="width: 120px; margin-top: 4px; display: none;">' +
                '</td>' +
                '<td><button type="button" class="button link-button seminario-remove-row">Eliminar</button></td>' +
                '</tr>';
            tbody.append(html);
        }

        function togglePlataformaOtro(selectEl) {
            var input = selectEl.closest('td').find('input[name*="plataforma_otro"]');
            if (selectEl.val() === 'otro') {
                input.show();
            } else {
                input.hide().val('');
            }
        }

        addBtn.on('click', function (e) {
            e.preventDefault();
            addRow();
        });

        tbody.on('click', '.seminario-remove-row', function (e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            reindexInputs(tbody.find('tr'), /_seminario_encuentros_sincronicos\[[0-9]+\]/, function (idx) {
                return '_seminario_encuentros_sincronicos[' + idx + ']';
            });
        });

        tbody.on('change', '.plataforma-select', function () {
            togglePlataformaOtro($(this));
        });

        // Inicializar select existentes
        tbody.find('.plataforma-select').each(function () {
            togglePlataformaOtro($(this));
        });
    }

    function setupObjetivos() {
        var table = $('#seminario-objetivos');
        var tbody = table.find('tbody');
        var addBtn = $('#seminario-add-objetivo');

        function reindex() {
            reindexInputs(tbody.find('tr'), /_seminario_objetivos_especificos\[[0-9]+\]/, function (idx) {
                return '_seminario_objetivos_especificos[' + idx + ']';
            });
        }

        function addRow() {
            var index = tbody.find('tr').length;
            var html = '' +
                '<tr>' +
                '<td class="seminario-drag-handle">&#x2630;</td>' +
                '<td><input type="text" name="_seminario_objetivos_especificos[' + index + ']" class="large-text"></td>' +
                '<td><button type="button" class="button link-button seminario-remove-objetivo">Eliminar</button></td>' +
                '</tr>';
            tbody.append(html);
            reindex();
        }

        addBtn.on('click', function (e) {
            e.preventDefault();
            addRow();
        });

        tbody.on('click', '.seminario-remove-objetivo', function (e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            reindex();
        });

        if ($.fn.sortable) {
            tbody.sortable({
                handle: '.seminario-drag-handle',
                update: reindex,
            });
        }
    }

    function setupUnidades() {
        var table = $('#seminario-unidades');
        var tbody = table.find('tbody');
        var addBtn = $('#seminario-add-unidad');

        function reindex() {
            tbody.find('tr').each(function (idx, row) {
                $(row)
                    .find('input, textarea')
                    .each(function (_, input) {
                        var name = $(input).attr('name');
                        if (name) {
                            name = name.replace(/_seminario_unidades_academicas\[[0-9]+\]\[titulo\]/, '_seminario_unidades_academicas[' + idx + '][titulo]');
                            name = name.replace(/_seminario_unidades_academicas\[[0-9]+\]\[contenido\]/, '_seminario_unidades_academicas[' + idx + '][contenido]');
                            $(input).attr('name', name);
                        }
                    });
            });
        }

        function addRow() {
            var index = tbody.find('tr').length;
            var html = '' +
                '<tr>' +
                '<td class="seminario-drag-handle">&#x2630;</td>' +
                '<td><input type="text" name="_seminario_unidades_academicas[' + index + '][titulo]" class="regular-text"></td>' +
                '<td><textarea name="_seminario_unidades_academicas[' + index + '][contenido]" class="large-text" rows="3"></textarea></td>' +
                '<td><button type="button" class="button link-button seminario-remove-unidad">Eliminar</button></td>' +
                '</tr>';
            tbody.append(html);
            reindex();
        }

        addBtn.on('click', function (e) {
            e.preventDefault();
            addRow();
        });

        tbody.on('click', '.seminario-remove-unidad', function (e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            reindex();
        });

        if ($.fn.sortable) {
            tbody.sortable({
                handle: '.seminario-drag-handle',
                update: reindex,
            });
        }
    }

    function setupDocentes() {
        var input = $('#seminario-docente-search');
        var results = $('#seminario-docente-results');
        var selected = $('#seminario-docentes-selected');
        var timer = null;
        var settings = window.SEMINARIO_ADMIN || {};

        if (!input.length || !results.length || !selected.length) {
            return;
        }

        function addDocente(id, title) {
            if (selected.find('li[data-id="' + id + '"]').length) {
                return;
            }
            var html = '' +
                '<li data-id="' + id + '">' +
                title +
                ' <button type="button" class="button-link seminario-remove-docente">Quitar</button>' +
                '<input type="hidden" name="_seminario_docentes[]" value="' + id + '">' +
                '</li>';
            selected.append(html);
        }

        function renderResults(items) {
            if (!items || !items.length) {
                results.empty();
                return;
            }
            var html = '<ul style="margin:0;padding-left:20px;">';
            items.forEach(function (item) {
                html += '<li><button type="button" class="button-link" data-id="' + item.id + '" data-title="' + item.title + '">' + item.title + '</button></li>';
            });
            html += '</ul>';
            results.html(html);
        }

        function search(term) {
            var data = new FormData();
            data.append('action', 'flacso_seminario_search_docentes');
            data.append('nonce', settings.searchNonce || '');
            data.append('term', term);

            fetch(settings.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
            })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r && r.success) {
                        renderResults(r.data);
                    } else {
                        renderResults([]);
                    }
                })
                .catch(function () { renderResults([]); });
        }

        input.on('input', function () {
            var term = input.val().trim();
            clearTimeout(timer);
            if (term.length < 2) {
                results.empty();
                return;
            }
            timer = setTimeout(function () {
                search(term);
            }, 250);
        });

        results.on('click', 'button[data-id]', function (e) {
            e.preventDefault();
            var btn = $(this);
            addDocente(btn.data('id'), btn.data('title'));
            results.empty();
            input.val('');
        });

        selected.on('click', '.seminario-remove-docente', function (e) {
            e.preventDefault();
            $(this).closest('li').remove();
        });
    }

    $(function () {
        setupEncuentros();
        setupObjetivos();
        setupUnidades();
        setupDocentes();
    });
})(jQuery);
