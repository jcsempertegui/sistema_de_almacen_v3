/* Selectores progresivos: solo buscan entre las opciones autorizadas del formulario. */
(() => {
    'use strict';
    const controles = new WeakMap();
    let secuencia = 0;
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const entidad = /(?:^|\[)(?:trabajador_id|producto_id|categoria_id|choose_category|unidad_medida_id|ubicacion_id|padre_id|atributo_id|rol_id|prestamo_id|item_prestamo_id|unidad_individual_id|unit_ids|location_ids)(?:\]|$)/;

    function mejorar(select) {
        if (controles.has(select) || select.dataset.busqueda === 'no' || select.size > 1 && !select.multiple) return;
        if (!select.multiple && !entidad.test(select.name) && select.options.length < 8) return;
        if (!select.id || document.getElementById(select.id) !== select) select.id = `sga-opciones-${++secuencia}`;
        const id = `${select.id}-busqueda`;
        const etiquetas = [...select.labels];
        const etiquetaEnvuelta = select.closest('label');
        if (etiquetaEnvuelta && !etiquetas.includes(etiquetaEnvuelta)) etiquetas.push(etiquetaEnvuelta);
        const nombre = select.getAttribute('aria-label') || etiquetas.map(label => [...label.childNodes].filter(n => n.nodeType === Node.TEXT_NODE).map(n => n.textContent).join(' ').trim()).filter(Boolean).join(' ') || 'Seleccionar opción';
        const caja = document.createElement('div');
        caja.className = 'sga-selector';
        const chips = document.createElement('div');
        chips.className = 'sga-selector-elegidos';
        const fila = document.createElement('div');
        fila.className = 'sga-selector-campo';
        const entrada = document.createElement('input');
        entrada.id = id;
        entrada.type = 'text';
        entrada.autocomplete = 'off';
        entrada.spellcheck = false;
        entrada.setAttribute('role', 'combobox');
        entrada.setAttribute('aria-label', nombre);
        entrada.setAttribute('aria-autocomplete', 'list');
        entrada.setAttribute('aria-expanded', 'false');
        entrada.setAttribute('aria-controls', `${id}-lista`);
        if (select.hasAttribute('aria-describedby')) entrada.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        const limpiar = document.createElement('button');
        limpiar.type = 'button';
        limpiar.className = 'sga-selector-limpiar';
        limpiar.textContent = '×';
        limpiar.setAttribute('aria-label', `Limpiar ${nombre}`);
        const lista = document.createElement('div');
        lista.id = `${id}-lista`;
        lista.className = 'sga-selector-lista';
        lista.setAttribute('role', 'listbox');
        lista.setAttribute('aria-label', nombre);
        if (select.multiple) lista.setAttribute('aria-multiselectable', 'true');
        lista.hidden = true;
        const estado = document.createElement('span');
        estado.className = 'sga-selector-estado';
        estado.setAttribute('role', 'status');
        estado.setAttribute('aria-live', 'polite');
        fila.append(entrada, limpiar);
        caja.append(chips, fila, lista, estado);
        select.after(caja);
        select.classList.add('sga-selector-nativo');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        etiquetas.forEach(label => { label.htmlFor = id; });
        let resultados = [], activo = -1, abierto = false;
        let cache = [];
        const opciones = () => [...select.options];
        const habilitada = o => !o.disabled && !o.hidden && !(o.parentElement.tagName === 'OPTGROUP' && o.parentElement.disabled);
        const seleccion = () => opciones().filter(o => o.selected && o.value);
        const textoActual = () => !select.multiple ? seleccion()[0]?.textContent.trim() || '' : '';

        function cerrar(restaurar = true) {
            abierto = false;
            lista.hidden = true;
            entrada.setAttribute('aria-expanded', 'false');
            entrada.removeAttribute('aria-activedescendant');
            if (restaurar) entrada.value = textoActual();
            entrada.setCustomValidity('');
        }
        function marcar(indice) {
            activo = indice;
            [...lista.querySelectorAll('[role="option"]')].forEach((elemento, i) => elemento.classList.toggle('es-activo', i === activo));
            if (activo >= 0) {
                const nodo = lista.querySelectorAll('[role="option"]')[activo];
                entrada.setAttribute('aria-activedescendant', nodo.id);
                nodo.scrollIntoView({ block: 'nearest' });
            } else entrada.removeAttribute('aria-activedescendant');
        }
        function mostrar() {
            if (select.disabled) return;
            abierto = true;
            lista.hidden = false;
            entrada.setAttribute('aria-expanded', 'true');
            const consulta = normalizar(entrada.value);
            const palabras = consulta.split(/\s+/).filter(Boolean);
            const coincidencias = cache.filter(({ opcion, texto }) => opcion.value && habilitada(opcion) && palabras.every(p => texto.includes(p)));
            coincidencias.sort((a, b) => Number(!a.texto.startsWith(consulta)) - Number(!b.texto.startsWith(consulta)) || a.orden - b.orden);
            resultados = coincidencias.slice(0, 60).map(r => r.opcion);
            lista.replaceChildren();
            resultados.forEach((opcion, i) => {
                const elemento = document.createElement('div');
                elemento.id = `${id}-opcion-${i}`;
                elemento.setAttribute('role', 'option');
                elemento.setAttribute('aria-selected', String(opcion.selected));
                elemento.className = 'sga-selector-opcion';
                elemento.textContent = (select.multiple && opcion.selected ? '✓ ' : '') + opcion.textContent.trim();
                elemento.addEventListener('pointerdown', e => e.preventDefault());
                elemento.addEventListener('click', e => { e.preventDefault(); elegir(opcion); });
                lista.append(elemento);
            });
            const mensaje = !coincidencias.length ? 'Sin coincidencias. Prueba con otro nombre o código.' : `${coincidencias.length} coincidencia${coincidencias.length === 1 ? '' : 's'}${coincidencias.length > 60 ? '. Mostrando las primeras 60; escribe más para afinar.' : ''}`;
            estado.textContent = mensaje;
            if (!resultados.length) {
                const vacio = document.createElement('div');
                vacio.className = 'sga-selector-vacio';
                vacio.textContent = mensaje;
                lista.append(vacio);
            }
            marcar(-1);
        }
        function emitir() {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            sincronizar();
        }
        function elegir(opcion) {
            if (!habilitada(opcion) || select.disabled) return;
            if (select.multiple) opcion.selected = !opcion.selected;
            else select.value = opcion.value;
            emitir();
            entrada.value = select.multiple ? '' : textoActual();
            entrada.setCustomValidity('');
            if (select.multiple) mostrar();
            else cerrar();
            entrada.focus();
        }
        function sincronizar() {
            cache = opciones().map((opcion, orden) => ({ opcion, orden, texto: normalizar(opcion.textContent) }));
            entrada.disabled = select.disabled;
            limpiar.disabled = select.disabled;
            if (select.disabled) cerrar();
            const sinSeleccion = opciones().find(o => !o.value)?.textContent.trim();
            entrada.placeholder = select.disabled ? 'No disponible' : select.multiple ? 'Escribe para añadir opciones…' : select.required ? 'Escribe un nombre o código…' : `Buscar…${sinSeleccion ? ' · ' + sinSeleccion : ''}`;
            entrada.setAttribute('aria-required', String(select.required));
            chips.replaceChildren();
            if (select.multiple) seleccion().forEach(opcion => {
                const chip = document.createElement('span');
                chip.className = 'sga-selector-etiqueta';
                const texto = document.createElement('span');
                texto.textContent = opcion.textContent.trim();
                const quitar = document.createElement('button');
                quitar.type = 'button';
                quitar.textContent = '×';
                quitar.disabled = select.disabled;
                quitar.setAttribute('aria-label', `Quitar ${opcion.textContent.trim()}`);
                quitar.addEventListener('click', e => { e.preventDefault(); opcion.selected = false; emitir(); entrada.focus(); });
                chip.append(texto, quitar);
                chips.append(chip);
            });
            limpiar.hidden = !seleccion().length && !entrada.value;
            if (!abierto) entrada.value = textoActual();
            if (abierto) mostrar();
        }
        entrada.addEventListener('focus', () => { if (!abierto) entrada.select(); });
        entrada.addEventListener('click', () => { if (!abierto) { entrada.value = ''; mostrar(); } });
        entrada.addEventListener('input', () => {
            limpiar.hidden = !entrada.value && !seleccion().length;
            entrada.setCustomValidity(entrada.value && entrada.value !== textoActual() ? 'Selecciona una coincidencia de la lista.' : '');
            mostrar();
        });
        entrada.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!abierto) { entrada.value = ''; mostrar(); }
                if (resultados.length) marcar(e.key === 'ArrowDown' ? (activo + 1) % resultados.length : (activo <= 0 ? resultados.length : activo) - 1);
            } else if (e.key === 'Enter' && abierto) {
                e.preventDefault();
                if (resultados.length) elegir(resultados[activo >= 0 ? activo : 0]);
            } else if (e.key === 'Escape') { e.preventDefault(); cerrar(); }
            else if (e.key === 'Tab') cerrar();
        });
        caja.addEventListener('focusout', () => setTimeout(() => { if (!caja.contains(document.activeElement)) cerrar(); }, 0));
        limpiar.addEventListener('click', e => {
            e.preventDefault();
            opciones().forEach(o => { o.selected = false; });
            select.selectedIndex = -1;
            entrada.value = '';
            emitir();
            cerrar();
            entrada.focus();
        });
        select.addEventListener('change', sincronizar);
        select.addEventListener('focus', () => entrada.focus());
        select.addEventListener('invalid', e => {
            e.preventDefault();
            let padre = select.parentElement;
            while (padre) { if (padre.tagName === 'DETAILS') padre.open = true; padre = padre.parentElement; }
            entrada.focus();
            entrada.setCustomValidity(select.validationMessage || 'Selecciona una opción.');
            entrada.reportValidity();
        });
        select.form?.addEventListener('reset', () => setTimeout(() => { cerrar(false); sincronizar(); }, 0));
        new MutationObserver(sincronizar).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'label', 'selected', 'required'] });
        controles.set(select, { sincronizar });
        sincronizar();
        if (document.activeElement === select) entrada.focus();
    }
    const iniciar = raiz => {
        if (raiz.matches?.('select')) mejorar(raiz);
        raiz.querySelectorAll?.('select').forEach(mejorar);
    };
    iniciar(document);
    new MutationObserver(cambios => cambios.forEach(cambio => cambio.addedNodes.forEach(nodo => { if (nodo.nodeType === Node.ELEMENT_NODE && !nodo.closest('.sga-selector')) iniciar(nodo); })))
        .observe(document.body, { childList: true, subtree: true });
    document.addEventListener('pointerdown', e => {
        document.querySelectorAll('.sga-selector').forEach(caja => {
            if (!caja.contains(e.target)) caja.querySelector('[role="combobox"]')?.blur();
        });
    });
})();
