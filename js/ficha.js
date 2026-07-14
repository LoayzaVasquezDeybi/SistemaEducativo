async function inicializarFicha() {
    const result = await cargarDatos('ficha', 'combo');
    const select = document.getElementById('ficha-matricula');
    if (!select || !result || !result.success) return;
    select.innerHTML = '<option value="">Seleccione matrícula</option>';
    result.matriculas.forEach(m => select.innerHTML += `<option value="${m.id_matricula}">#${m.id_matricula} - ${m.apellido}, ${m.nombre} (${m.dni || 'S/D'})</option>`);
}

async function cargarFicha() {
    const id = document.getElementById('ficha-matricula').value;
    if (!id) return alert('Seleccione una matrícula.');
    const response = await fetch(`./api/ficha.php?action=obtener&id_matricula=${encodeURIComponent(id)}`);
    const result = await response.json();
    if (!result.success) return alert('Error: ' + (result.error || result.message));

    const f = result.ficha;
    const texto = valor => String(valor ?? '').replace(/[&<>'"]/g, caracter => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'})[caracter]);
    const fecha = valor => {
        if (!valor) return '-';
        const partes = String(valor).split('-');
        return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : texto(valor);
    };
    const estadoClase = String(f.estado_matricula || '').toLowerCase() === 'activo' ? 'activo' : '';
    const apoderados = result.apoderados.map(a => `<tr><td>${texto(`${a.nombres || ''} ${a.apellidos || ''}`.trim())}</td><td>${texto(a.parentesco || '-')}</td><td>${texto(a.dni || 'S/D')}</td><td>${texto(a.email || '-')}</td></tr>`).join('') || '<tr><td colspan="4" class="ficha-sin-datos">Sin apoderados registrados</td></tr>';
    const pagos = result.pagos.map(p => `<tr><td>${texto(p.concepto)}</td><td class="ficha-monto">S/ ${Number(p.monto || 0).toFixed(2)}</td><td>${fecha(p.fecha_pago)}</td><td>${texto(p.metodo || '-')}</td><td>${texto(p.estado_pago || '-')}</td></tr>`).join('') || '<tr><td colspan="5" class="ficha-sin-datos">Sin pagos registrados</td></tr>';

    document.getElementById('ficha-contenido').innerHTML = `
        <article class="ficha-documento" data-ficha-generada="true">
            <header class="ficha-documento-header">
                <div class="ficha-logo">IE</div>
                <div class="ficha-institucion"><div class="ficha-republica">Institución Educativa N.° 22237</div><h1>José Yataco Pachas</h1><p>Ficha oficial de matrícula</p></div>
                <div class="ficha-numero"><span>N.° de ficha</span><strong>${String(f.id_matricula).padStart(5, '0')}</strong></div>
            </header>
            <div class="ficha-resumen">
                <div><span>Periodo académico</span><strong>${texto(f.periodo || '-')}</strong></div>
                <div><span>Fecha de matrícula</span><strong>${fecha(f.fecha_matricula)}</strong></div>
                <div><span>Estado</span><strong class="ficha-estado ${estadoClase}">${texto(f.estado_matricula || '-')}</strong></div>
            </div>
            <section class="ficha-seccion">
                <h2><span>01</span> Datos del estudiante</h2>
                <div class="ficha-datos-grid">
                    <div class="ficha-dato ficha-dato-amplio"><span>Apellidos y nombres</span><strong>${texto(`${f.apellido || ''}, ${f.nombre || ''}`)}</strong></div>
                    <div class="ficha-dato"><span>Código del estudiante</span><strong>${texto(f.codigo_estudiante || '-')}</strong></div>
                    <div class="ficha-dato"><span>DNI</span><strong>${texto(f.dni || 'S/D')}</strong></div>
                    <div class="ficha-dato"><span>Fecha de nacimiento</span><strong>${fecha(f.fecha_nacimiento)}</strong></div>
                    <div class="ficha-dato"><span>Grado</span><strong>${texto(f.grado || '-')}</strong></div>
                    <div class="ficha-dato"><span>Sección</span><strong>${texto(f.seccion || '-')}</strong></div>
                    <div class="ficha-dato"><span>Estado del estudiante</span><strong>${texto(f.estado || '-')}</strong></div>
                </div>
            </section>
            <section class="ficha-seccion">
                <h2><span>02</span> Apoderado(s)</h2>
                <div class="table-wrap"><table class="ficha-tabla"><thead><tr><th>Apellidos y nombres</th><th>Parentesco</th><th>DNI</th><th>Correo electrónico</th></tr></thead><tbody>${apoderados}</tbody></table></div>
            </section>
            <section class="ficha-seccion">
                <h2><span>03</span> Registro de pagos</h2>
                <div class="table-wrap"><table class="ficha-tabla"><thead><tr><th>Concepto</th><th>Monto</th><th>Fecha</th><th>Método</th><th>Estado</th></tr></thead><tbody>${pagos}</tbody></table></div>
            </section>
            <footer class="ficha-firmas">
                <div><span></span><strong>Firma del padre, madre o apoderado</strong><small>DNI: ____________________</small></div>
                <div><span></span><strong>Responsable de matrícula</strong><small>Sello de la institución</small></div>
            </footer>
            <p class="ficha-pie">Documento generado por el Sistema de Gestión Escolar · IE N.° 22237</p>
        </article>`;
}

function imprimirFicha() {
    const ficha = document.querySelector('#ficha-contenido [data-ficha-generada="true"]');
    if (!ficha) return alert('Primero seleccione una matrícula y genere la ficha.');
    document.body.classList.add('imprimiendo-ficha');
    window.print();
    document.body.classList.remove('imprimiendo-ficha');
}
