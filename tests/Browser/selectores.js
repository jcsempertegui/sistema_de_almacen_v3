document.addEventListener('DOMContentLoaded', async () => {
    const resultados = [];
    const pausa = () => new Promise(resolve => setTimeout(resolve, 30));
    const comprobar = (nombre, condicion) => { resultados.push({ nombre, correcto: !!condicion }); if (!condicion) throw new Error(nombre); };
    const escribir = (input, texto) => { input.value = texto; input.dispatchEvent(new Event('input', { bubbles: true })); };
    const tecla = (input, key) => input.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
    try {
        await pausa();
        comprobar('Sin errores JavaScript de la página', !window.erroresPrueba.length);
        comprobar('Los selectores están disponibles', document.querySelectorAll('.sga-selector').length > 0);
        if (window.escenarioPrueba === 'entrega') {
            const select = document.querySelector('select[name="trabajador_id"]');
            const componente = select.nextElementSibling;
            const input = componente.querySelector('[role="combobox"]');
            input.focus(); escribir(input, 'mend jos');
            comprobar('Búsqueda parcial por palabras, sin tildes y en distinto orden', componente.querySelectorAll('[role="option"]').length === 1);
            tecla(input, 'ArrowDown'); tecla(input, 'Enter');
            comprobar('El teclado guarda el ID del trabajador, no el texto', !!select.value && input.value.includes('José Carlos'));
            const original = select.value;
            escribir(input, 'persona inexistente zzz');
            comprobar('Texto libre no se convierte en una referencia válida', !input.checkValidity() && select.value === original);
            comprobar('Se informa cuando no hay coincidencias', componente.textContent.includes('Sin coincidencias'));
            tecla(input, 'Escape');
            comprobar('Escape conserva la selección anterior y cierra la lista', select.value === original && input.getAttribute('aria-expanded') === 'false' && input.value.includes('José Carlos'));
            componente.querySelector('.sga-selector-limpiar').click();
            comprobar('Limpiar mantiene la validación obligatoria', !select.value && !select.checkValidity());
            const multi = document.querySelector('select[multiple]');
            multi.add(new Option('Herramienta Alfa', 'u1')); multi.add(new Option('Herramienta Beta', 'u2'));
            await pausa();
            const cm = multi.nextElementSibling, im = cm.querySelector('input');
            escribir(im, 'alfa'); tecla(im, 'ArrowDown'); tecla(im, 'Enter');
            comprobar('Selección múltiple mediante búsqueda sin Ctrl', [...multi.selectedOptions].some(o => o.value === 'u1') && cm.querySelectorAll('.sga-selector-etiqueta').length === 1);
            cm.querySelector('.sga-selector-etiqueta button').click();
            comprobar('Quitar etiqueta actualiza el select real', ![...multi.selectedOptions].some(o => o.value === 'u1'));
            const elegido = select.options[1]; elegido.disabled = true;
            await pausa(); escribir(input, elegido.textContent);
            comprobar('No se sugieren opciones deshabilitadas', componente.querySelectorAll('[role="option"]').length === 0);
        }
        if (window.escenarioPrueba === 'unidad') {
            const producto = document.getElementById('producto_id');
            const ubicacion = document.getElementById('ubicacion_id');
            const productos = [...producto.options].filter(o => o.value);
            producto.value = productos[0].value; producto.dispatchEvent(new Event('change', { bubbles: true }));
            await pausa();
            const permitidas = [...ubicacion.options].filter(o => o.value && !o.disabled && !o.hidden);
            comprobar('Las ubicaciones se limitan al producto elegido', permitidas.length === 1 && permitidas[0].dataset.producto === producto.value);
            ubicacion.value = permitidas[0].value; ubicacion.dispatchEvent(new Event('change', { bubbles: true }));
            const ubicacionAnterior = ubicacion.value;
            producto.value = productos[1].value; producto.dispatchEvent(new Event('change', { bubbles: true }));
            await pausa();
            comprobar('Cambiar producto limpia el destino incompatible', !ubicacion.value && !ubicacion.nextElementSibling.querySelector('input').value);
            const input = ubicacion.nextElementSibling.querySelector('input');
            escribir(input, '');
            comprobar('Las sugerencias no muestran ubicaciones del otro producto', ![...ubicacion.nextElementSibling.querySelectorAll('[role="option"]')].some(o => o.textContent.includes(permitidas[0].textContent)) && [...ubicacion.options].find(o => o.value === ubicacionAnterior).disabled);
        }
        const container = document.getElementById(window.escenarioPrueba === 'entrega' ? 'operation-lineas' : window.escenarioPrueba === 'remito' ? 'inventory-lineas' : 'correccion-lineas');
        if (container) {
        const previas = container.querySelectorAll('fieldset').length;
        document.getElementById('add-line').click();
        await pausa();
        comprobar('Añadir líneas funciona', container.querySelectorAll('fieldset').length === previas + 1);
        comprobar('Las líneas nuevas también permiten buscar', !!container.lastElementChild.querySelector('.sga-selector input'));
        const ids = [...document.querySelectorAll('.sga-selector input')].map(i => i.id);
        comprobar('Los controles dinámicos tienen identificadores únicos', ids.length === new Set(ids).size);
        const indexes = [...container.lastElementChild.querySelectorAll('select[name]')].map(s => s.name);
        comprobar('Se conservan los índices enviados al servidor', indexes.every(n => n.includes('[' + previas + ']')));
        if (window.escenarioPrueba === 'ajuste') {
            comprobar('Clonar detalles no duplica buscadores', container.lastElementChild.querySelectorAll('.sga-selector').length === container.lastElementChild.querySelectorAll('select.sga-selector-nativo').length);
        }
        }
        comprobar('No se provocaron nuevos errores JavaScript', !window.erroresPrueba.length);
    } catch (error) { resultados.push({ error: error.message }); }
    const salida = document.createElement('pre'); salida.id = 'resultado-pruebas';
    salida.textContent = JSON.stringify({ escenario: window.escenarioPrueba, ancho: innerWidth, resultados, correcto: resultados.length > 0 && resultados.every(r => r.correcto === true) });
    document.body.append(salida);
});
