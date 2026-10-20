(function () {
    'use strict';
    var root = document.querySelector('[data-art-editor]');
    if (!root) return;

    var next = {node: 0, edge: 0, presentation: 0};
    ['node', 'edge', 'presentation'].forEach(function (type) {
        var rows = root.querySelectorAll('[data-art-list="' + type + '"] [data-art-row]');
        next[type] = rows.length;
    });

    function nodeData() {
        return Array.from(root.querySelectorAll('[data-art-row="node"]')).map(function (row) {
            var key = row.querySelector('[data-art-key]').value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '-');
            var name = row.querySelector('[name$="[nombre]"]').value.trim();
            var cycle = row.querySelector('[name$="[ciclo]"]').value;
            var offer = row.querySelector('[data-art-offer]');
            return {key: key, label: 'Ciclo ' + cycle + ' · ' + name, offer: offer};
        }).filter(function (node) { return node.key; });
    }

    function mentions(offer) {
        if (!offer) return [];
        var option = offer.options[offer.selectedIndex];
        try {
            var parsed = JSON.parse(option && option.dataset.mentions || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) { return []; }
    }

    function updateMentions(select, options, emptyLabel) {
        var current = select.value;
        select.replaceChildren();
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = emptyLabel;
        select.appendChild(empty);
        options.forEach(function (value) {
            var o = document.createElement('option');
            o.value = String(value);
            o.textContent = String(value);
            select.appendChild(o);
        });
        if (options.includes(current)) select.value = current;
    }

    function updateNodeMentions(row) {
        updateMentions(
            row.querySelector('[data-art-mention]'),
            mentions(row.querySelector('[data-art-offer]')),
            'Sin mención específica'
        );
    }

    function updateEdgeMentions(row, nodes) {
        var origin = row.querySelector('[data-art-edge="origen"]').value;
        var matched = nodes.find(function (n) { return n.key === origin; });
        updateMentions(row.querySelector('[data-art-edge-mention]'), mentions(matched && matched.offer), 'Cualquier mención');
    }

    function updateEdgeOptions() {
        var nodes = nodeData();
        root.querySelectorAll('[data-art-row="edge"]').forEach(function (row) {
            ['origen', 'destino'].forEach(function (type) {
                var select = row.querySelector('[data-art-edge="' + type + '"]');
                var current = select.value;
                select.replaceChildren();
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Seleccionar etapa';
                select.appendChild(placeholder);
                nodes.forEach(function (node) {
                    var option = document.createElement('option');
                    option.value = node.key;
                    option.textContent = node.label;
                    select.appendChild(option);
                });
                select.value = nodes.some(function (node) { return node.key === current; }) ? current : '';
            });
            updateEdgeMentions(row, nodes);
        });
    }

    root.addEventListener('click', function (event) {
        var add = event.target.closest('[data-art-add]');
        if (add && root.contains(add)) {
            var type = add.dataset.artAdd;
            var template = root.querySelector('[data-art-template="' + type + '"]');
            var list = root.querySelector('[data-art-list="' + type + '"]');
            if (!template || !list) return;
            var node = template.content.firstElementChild.cloneNode(true);
            var index = String(next[type]++);
            node.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.split('__INDEX__').join(index);
            });
            if (type === 'node') {
                node.querySelector('[data-art-key]').value = 'etapa-' + (Number(index) + 1);
            }
            list.appendChild(node);
            if (type === 'edge') updateEdgeOptions();
            var input = node.querySelector('input,select,textarea');
            if (input) input.focus();
            return;
        }
        var remove = event.target.closest('[data-art-remove]');
        if (remove && root.contains(remove)) {
            var row = remove.closest('[data-art-row]');
            var type = row.dataset.artRow;
            row.remove();
            if (type === 'node') updateEdgeOptions();
        }
    });

    root.addEventListener('change', function (event) {
        var row = event.target.closest('[data-art-row]');
        if (!row) return;
        if (row.dataset.artRow === 'node') {
            if (event.target.matches('[data-art-offer]')) updateNodeMentions(row);
            updateEdgeOptions();
        }
        if (row.dataset.artRow === 'edge' && event.target.matches('[data-art-edge="origen"]')) {
            updateEdgeMentions(row, nodeData());
        }
    });
    root.addEventListener('input', function (event) {
        if (event.target.matches('[data-art-key], [name$="[nombre]"], [name$="[ciclo]"]')) {
            updateEdgeOptions();
        }
    });
    updateEdgeOptions();
}());
