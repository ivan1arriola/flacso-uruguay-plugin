/* Editor dinámico de equipos académicos para Ofertas y Cohortes. */
(function () {
    'use strict';

    function editorOf(element) {
        return element.closest('[data-flacso-team-editor]');
    }

    function ensureDeleteDialog() {
        var existing = document.querySelector('[data-flacso-team-delete-dialog]');
        if (existing) return existing;

        var overlay = document.createElement('div');
        overlay.className = 'flacso-team-delete-overlay';
        overlay.dataset.flacsoTeamDeleteDialog = '';
        overlay.hidden = true;
        overlay.innerHTML = '<div class="flacso-team-delete-dialog" role="alertdialog" aria-modal="true" aria-labelledby="flacso-team-delete-title" aria-describedby="flacso-team-delete-description flacso-team-delete-note">' +
            '<span class="flacso-team-delete-dialog__icon" aria-hidden="true"><span class="dashicons dashicons-trash"></span></span>' +
            '<h2 id="flacso-team-delete-title">¿Eliminar este grupo docente?</h2>' +
            '<p id="flacso-team-delete-description">Vas a quitar el grupo <strong data-team-delete-name></strong> junto con sus integrantes.</p>' +
            '<p id="flacso-team-delete-note" class="flacso-team-delete-dialog__note">El cambio se aplicará cuando guardes la Oferta Académica o Cohorte.</p>' +
            '<div class="flacso-team-delete-dialog__actions"><button type="button" class="button button-secondary" data-team-delete-cancel>Cancelar</button> <button type="button" class="button flacso-team-delete-dialog__confirm" data-team-delete-confirm>Eliminar grupo</button></div>' +
            '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    var deleteDialog = ensureDeleteDialog();
    var pendingDelete = null;
    var returnFocus = null;

    function changed() {
        document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'));
    }

    function closeTeamDeleteDialog(focusTarget) {
        deleteDialog.hidden = true;
        pendingDelete = null;
        var target = focusTarget || returnFocus;
        returnFocus = null;
        if (target && target.isConnected) target.focus();
    }

    function openTeamDeleteDialog(team, trigger) {
        if (!team || !editorOf(trigger) || team.getAttribute('data-team-type') === 'coordinacion_academica') return;
        pendingDelete = team;
        returnFocus = trigger;
        var nameInput = team.querySelector('input[name$="[nombre]"]');
        var heading = team.querySelector('.flacso-team-group__heading strong');
        var name = (nameInput && nameInput.value.trim()) || (heading && heading.textContent.trim()) || 'Grupo docente';
        deleteDialog.querySelector('[data-team-delete-name]').textContent = '“' + name + '”';
        deleteDialog.hidden = false;
        deleteDialog.querySelector('[data-team-delete-cancel]').focus();
    }

    deleteDialog.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : event.target.parentElement;
        if (target === deleteDialog || target.closest('[data-team-delete-cancel]')) {
            event.preventDefault();
            closeTeamDeleteDialog();
            return;
        }
        if (!target.closest('[data-team-delete-confirm]')) return;
        event.preventDefault();
        if (!pendingDelete || !pendingDelete.isConnected) {
            closeTeamDeleteDialog();
            return;
        }
        var editor = editorOf(pendingDelete);
        var next = pendingDelete.nextElementSibling;
        var focusTarget = (next && next.querySelector('[data-remove-team]')) || (editor && editor.querySelector('[data-add-team]')) || null;
        pendingDelete.remove();
        closeTeamDeleteDialog(focusTarget);
        changed();
    });

    document.addEventListener('keydown', function (event) {
        if (deleteDialog.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeTeamDeleteDialog();
            return;
        }
        if (event.key !== 'Tab') return;
        var controls = Array.from(deleteDialog.querySelectorAll('button:not([disabled])'));
        if (!controls.length) {
            event.preventDefault();
            return;
        }
        var first = controls[0];
        var last = controls[controls.length - 1];
        if (event.shiftKey && (document.activeElement === first || !deleteDialog.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !deleteDialog.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });

    function nextMemberIndex(container) {
        var max = -1;
        container.querySelectorAll('[data-member-row] select[name]').forEach(function (select) {
            var match = select.name.match(/\[(\d+)\]\[id\]$/);
            if (match) max = Math.max(max, parseInt(match[1], 10));
        });
        return max + 1;
    }

    function nextGroupIndex(list) {
        var max = -1;
        list.querySelectorAll('[data-team-group]').forEach(function (group) {
            var value = parseInt(group.getAttribute('data-group-index') || '-1', 10);
            if (!isNaN(value)) max = Math.max(max, value);
        });
        return max + 1;
    }

    function initPersonSearch(input) {
        var $ = window.jQuery;
        if (!$ || !$.fn.autocomplete || input.dataset.personReady) return;
        var row = input.closest('[data-member-row]');
        var select = row && row.querySelector('[data-person-select]');
        var feedback = row && row.querySelector('[data-person-feedback]');
        if (!select) return;
        input.dataset.personReady = '1';
        var selectedName = input.value;

        $(input).on('input', function () {
            if (input.value !== selectedName && select.value) {
                select.value = '';
                selectedName = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        $(input).autocomplete({
            minLength: 2,
            delay: 250,
            classes: { 'ui-autocomplete': 'flacso-team-autocomplete' },
            source: function (request, respond) {
                var editor = editorOf(input);
                if (!editor || !window.ajaxurl) {
                    respond([]);
                    return;
                }
                if (feedback) feedback.textContent = 'Buscando personas…';
                $.ajax({
                    url: window.ajaxurl,
                    dataType: 'json',
                    timeout: 10000,
                    data: {
                        action: 'flacso_search_team_people',
                        nonce: editor.dataset.personNonce,
                        post_id: editor.dataset.postId,
                        term: request.term,
                        page: 1
                    }
                }).done(function (payload) {
                    if (!payload || !payload.success) {
                        if (feedback) feedback.textContent = (payload && payload.data && payload.data.message) || 'No se pudo realizar la búsqueda.';
                        respond([]);
                        return;
                    }
                    var group = input.closest('[data-team-group]');
                    var otherIds = Array.from((group && group.querySelectorAll('[data-person-select]')) || [])
                        .filter(function (other) { return other !== select && other.value; })
                        .map(function (other) { return String(other.value); });
                    var results = (payload.data.results || []).filter(function (person) {
                        return otherIds.indexOf(String(person.id)) === -1;
                    }).map(function (person) {
                        return {
                            label: person.name + (person.email ? ' — ' + person.email : ''),
                            value: person.name,
                            id: person.id,
                            name: person.name,
                            email: person.email || '',
                            avatar: person.avatar || ''
                        };
                    });
                    if (feedback) {
                        feedback.textContent = payload.data.more
                            ? 'Mostrando las primeras coincidencias. Escribí más para afinar la búsqueda.'
                            : (results.length ? 'Elegí una persona de la lista.' : 'No hay personas disponibles con ese criterio.');
                    }
                    respond(results);
                }).fail(function () {
                    if (feedback) feedback.textContent = 'No se pudo conectar. Intentá nuevamente.';
                    respond([]);
                });
            },
            select: function (event, ui) {
                event.preventDefault();
                var person = ui.item;
                select.replaceChildren();
                select.add(new Option('— Seleccionar persona —', ''));
                var option = new Option(person.name, String(person.id), true, true);
                option.dataset.name = person.name;
                option.dataset.email = person.email;
                option.dataset.avatar = person.avatar;
                select.add(option);
                input.value = person.name;
                selectedName = person.name;
                if (feedback) feedback.textContent = 'Persona seleccionada: ' + person.name;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                changed();
            }
        });

        var autocomplete = $(input).autocomplete('instance');
        if (autocomplete) {
            autocomplete._renderItem = function (ul, item) {
                var node = $('<li>');
                var wrapper = $('<div>').text(item.name);
                if (item.email) $('<small>').addClass('flacso-team-person-email').text(item.email).appendTo(wrapper);
                return node.append(wrapper).appendTo(ul);
            };
        }
    }

    function eventElement(event) {
        return event.target instanceof Element ? event.target : event.target.parentElement;
    }

    document.addEventListener('click', function (event) {
        var target = eventElement(event);
        var addMember = target.closest('[data-add-member]');
        if (addMember) {
            event.preventDefault();
            var members = addMember.closest('.flacso-team-members');
            var editor = editorOf(addMember);
            var template = editor && editor.querySelector('template[data-member-template]');
            var memberList = members && members.querySelector('[data-member-list]');
            if (!members || !template || !memberList) return;
            var index = nextMemberIndex(members);
            var prefix = members.getAttribute('data-members-prefix') || '';
            memberList.insertAdjacentHTML('beforeend', template.innerHTML.split('__MEMBER_NAME__').join(prefix + '[' + index + ']'));
            var added = memberList.querySelector('[data-member-row]:last-child [data-person-search]');
            if (added) {
                initPersonSearch(added);
                added.focus();
            }
            changed();
            return;
        }

        var removeMember = target.closest('[data-remove-member]');
        if (removeMember) {
            event.preventDefault();
            var member = removeMember.closest('[data-member-row]');
            if (member) member.remove();
            changed();
            return;
        }

        var addTeam = target.closest('[data-add-team]');
        if (addTeam) {
            event.preventDefault();
            var teamEditor = editorOf(addTeam);
            var list = teamEditor && teamEditor.querySelector('[data-team-list]');
            var kind = addTeam.getAttribute('data-add-team');
            var teamTemplate = teamEditor && teamEditor.querySelector('template[data-team-template="' + kind + '"]');
            if (!list || !teamTemplate) return;
            var insertInto = teamEditor.getAttribute('data-flacso-team-editor') === 'offer'
                ? list.querySelector('[data-team-zone="custom"] [data-team-zone-list]')
                : list;
            if (!insertInto) return;
            var groupIndex = nextGroupIndex(list);
            insertInto.insertAdjacentHTML('beforeend', teamTemplate.innerHTML.split('__GROUP__').join(String(groupIndex)));
            var group = insertInto.querySelector('[data-team-group]:last-child');
            if (group) {
                var first = group.querySelector('input:not([type="hidden"]),select,textarea,button');
                if (first) first.focus();
            }
            changed();
            return;
        }

        var removeTeam = target.closest('[data-remove-team]');
        if (removeTeam) {
            event.preventDefault();
            openTeamDeleteDialog(removeTeam.closest('[data-team-group]'), removeTeam);
        }
    });

    document.addEventListener('change', function (event) {
        if (eventElement(event).closest('[data-flacso-team-editor]')) changed();
    });

    document.querySelectorAll('[data-flacso-team-editor] [data-person-search]').forEach(initPersonSearch);
}());
