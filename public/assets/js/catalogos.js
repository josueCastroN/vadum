(function () {
  'use strict';

  // =============================================================
  // Utilidades de red (fetch JSON con credenciales + BOM safe)
  // =============================================================
  async function apiGet(ruta, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const url = `../api/index.php?ruta=${encodeURIComponent(ruta)}${qs ? `&${qs}` : ''}`;
    const resp = await fetch(url, { credentials: 'include', headers: { 'Accept': 'application/json' } });
    const txt = await resp.text();
    const clean = txt.replace(/^\uFEFF|^\u200B|^\ufeff/, '');
    let data;
    try { data = clean ? JSON.parse(clean) : {}; } catch (e) {
      console.error('Error parseando JSON GET', ruta, clean);
      throw new Error('Respuesta inválida del servidor');
    }
    if (!resp.ok || data.ok === false) {
      throw new Error(data.error || `Error ${resp.status}`);
    }
    return data;
  }

  async function apiPost(ruta, body = {}) {
    const url = `../api/index.php?ruta=${encodeURIComponent(ruta)}`;
    const resp = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(body)
    });
    const txt = await resp.text();
    const clean = txt.replace(/^\uFEFF|^\u200B|^\ufeff/, '');
    let data;
    try { data = clean ? JSON.parse(clean) : {}; } catch (e) {
      console.error('Error parseando JSON POST', ruta, clean);
      throw new Error('Respuesta inválida del servidor');
    }
    if (!resp.ok || data.ok === false) {
      throw new Error(data.error || `Error ${resp.status}`);
    }
    return data;
  }

  // Helper UI
  const UI = window.VADUM_UI || {};
  const toast = (m) => (UI.mostrarToast ? UI.mostrarToast(m) : alert(m));

  // Debounce simple
  function debounce(fn, ms = 300) {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  // =============================================================
  // PUESTOS
  // =============================================================
  const elPuestos = {
    tbody: null,
    buscar: null,
    btnNuevo: null,
    modal: null,
    campoId: null,
    campoNombre: null,
    btnGuardar: null
  };

  function renderPuestos(items = []) {
    elPuestos.tbody.innerHTML = '';
    items.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p.nombre}</td>
        <td>${p.activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
        <td>
          <button class="btn btn-sm btn-primary me-1" data-edit="${p.id}"><i class="fas fa-pen"></i></button>
          <button class="btn btn-sm btn-danger" data-del="${p.id}"><i class="fas fa-trash"></i></button>
        </td>`;
      elPuestos.tbody.appendChild(tr);
    });
  }

  async function cargarPuestos() {
    const buscar = (elPuestos.buscar.value || '').trim();
    const { puestos } = await apiGet('puestos/lista', { buscar });
    renderPuestos(puestos);
  }

  function abrirModalPuesto(puesto = null) {
    elPuestos.campoId.value = puesto ? puesto.id : '';
    elPuestos.campoNombre.value = puesto ? puesto.nombre : '';
    const titulo = document.getElementById('tituloModalPuesto');
    if (titulo) titulo.textContent = puesto ? 'Editar Puesto' : 'Nuevo Puesto';
    const modal = bootstrap.Modal.getOrCreateInstance(elPuestos.modal);
    modal.show();
  }

  function wirePuestos() {
    elPuestos.tbody = document.querySelector('#tablaPuestos tbody');
    elPuestos.buscar = document.getElementById('buscarPuesto');
    elPuestos.btnNuevo = document.getElementById('btnNuevoPuesto');
    elPuestos.modal = document.getElementById('modalPuesto');
    elPuestos.campoId = document.getElementById('puestoId');
    elPuestos.campoNombre = document.getElementById('puestoNombre');
    elPuestos.btnGuardar = document.getElementById('btnGuardarPuesto');
    if (!elPuestos.tbody) return;

    cargarPuestos().catch(console.error);
    elPuestos.buscar.addEventListener('input', debounce(cargarPuestos, 250));
    elPuestos.btnNuevo.addEventListener('click', () => abrirModalPuesto());

    elPuestos.btnGuardar.addEventListener('click', async () => {
      const id = elPuestos.campoId.value.trim();
      const nombre = elPuestos.campoNombre.value.trim();
      if (!nombre) { toast('Escribe el nombre del puesto'); return; }
      try {
        if (id) await apiPost('puestos/renombrar', { id: Number(id), nuevo_nombre: nombre });
        else await apiPost('puestos/crear', { nombre });
        bootstrap.Modal.getInstance(elPuestos.modal)?.hide();
        toast('Puesto guardado');
        cargarPuestos();
      } catch (e) { toast(e.message || 'Error guardando puesto'); }
    });

    elPuestos.tbody.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('button'); if (!btn) return;
      if (btn.dataset.edit) {
        // Obtener nombre desde la fila
        const tr = btn.closest('tr');
        const nombre = tr?.children?.[0]?.textContent || '';
        abrirModalPuesto({ id: btn.dataset.edit, nombre });
      } else if (btn.dataset.del) {
        if (!confirm('¿Eliminar este puesto?')) return;
        try { await apiPost('puestos/eliminar', { id: Number(btn.dataset.del) }); toast('Puesto eliminado'); cargarPuestos(); } catch (e) { toast(e.message); }
      }
    });
  }

  // =============================================================
  // REGIONES
  // =============================================================
  const elRegiones = {
    tbody: null,
    buscar: null,
    btnNuevo: null,
    modal: null,
    campoId: null,
    campoNombre: null,
    btnGuardar: null
  };

  function renderRegiones(items = []) {
    elRegiones.tbody.innerHTML = '';
    items.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.nombre}</td>
        <td>
          <button class="btn btn-sm btn-primary me-1" data-edit="${r.id}"><i class="fas fa-pen"></i></button>
          <button class="btn btn-sm btn-danger" data-del="${r.id}"><i class="fas fa-trash"></i></button>
        </td>`;
      elRegiones.tbody.appendChild(tr);
    });
  }

  async function cargarRegiones() {
    const buscar = (elRegiones.buscar.value || '').trim();
    const { regiones } = await apiGet('regiones/lista', { buscar });
    renderRegiones(regiones);
    // Mantener selects dependientes (Puntos)
    poblarSelectRegionesPuntos(regiones);
  }

  function abrirModalRegion(region = null) {
    elRegiones.campoId.value = region ? region.id : '';
    elRegiones.campoNombre.value = region ? region.nombre : '';
    const t = document.getElementById('tituloModalRegion');
    if (t) t.textContent = region ? 'Editar Región' : 'Nueva Región';
    bootstrap.Modal.getOrCreateInstance(elRegiones.modal).show();
  }

  function wireRegiones() {
    elRegiones.tbody = document.querySelector('#tablaRegiones tbody');
    elRegiones.buscar = document.getElementById('buscarRegion');
    elRegiones.btnNuevo = document.getElementById('btnNuevaRegion');
    elRegiones.modal = document.getElementById('modalRegion');
    elRegiones.campoId = document.getElementById('regionId');
    elRegiones.campoNombre = document.getElementById('regionNombre');
    elRegiones.btnGuardar = document.getElementById('btnGuardarRegion');
    if (!elRegiones.tbody) return;

    cargarRegiones().catch(console.error);
    elRegiones.buscar.addEventListener('input', debounce(cargarRegiones, 250));
    elRegiones.btnNuevo.addEventListener('click', () => abrirModalRegion());
    elRegiones.btnGuardar.addEventListener('click', async () => {
      const id = elRegiones.campoId.value.trim();
      const nombre = elRegiones.campoNombre.value.trim();
      if (!nombre) { toast('Escribe el nombre de la región'); return; }
      try {
        if (id) await apiPost('regiones/renombrar', { id: Number(id), nuevo_nombre: nombre });
        else await apiPost('regiones/crear', { nombre });
        bootstrap.Modal.getInstance(elRegiones.modal)?.hide();
        toast('Región guardada');
        cargarRegiones();
      } catch (e) { toast(e.message || 'Error guardando región'); }
    });

    elRegiones.tbody.addEventListener('click', async ev => {
      const btn = ev.target.closest('button'); if (!btn) return;
      if (btn.dataset.edit) {
        const tr = btn.closest('tr');
        const nombre = tr?.children?.[0]?.textContent || '';
        abrirModalRegion({ id: btn.dataset.edit, nombre });
      } else if (btn.dataset.del) {
        if (!confirm('¿Eliminar esta región?')) return;
        try { await apiPost('regiones/eliminar', { id: Number(btn.dataset.del) }); toast('Región eliminada'); cargarRegiones(); } catch (e) { toast(e.message); }
      }
    });
  }

  // =============================================================
  // PUNTOS
  // =============================================================
  const elPuntos = {
    tbody: null,
    buscar: null,
    filtroRegion: null,
    btnNuevo: null,
    modal: null,
    campoId: null,
    campoNombre: null,
    campoRegion: null,
    btnGuardar: null,
  };

  function renderPuntos(items = []) {
    elPuntos.tbody.innerHTML = '';
    items.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p.nombre}</td>
        <td>${p.region}</td>
        <td>
          <button class="btn btn-sm btn-primary me-1" data-edit='${JSON.stringify(p)}'><i class="fas fa-pen"></i></button>
          <button class="btn btn-sm btn-danger" data-del="${p.id}"><i class="fas fa-trash"></i></button>
        </td>`;
      elPuntos.tbody.appendChild(tr);
    });
  }

  async function cargarPuntos() {
    const buscar = (elPuntos.buscar.value || '').trim();
    const region = (elPuntos.filtroRegion.value || '').trim();
    const { puntos } = await apiGet('puntos/lista', { buscar, region });
    renderPuntos(puntos);
  }

  function poblarSelectRegionesPuntos(regiones = []) {
    if (!elPuntos.filtroRegion || !elPuntos.campoRegion) return;
    const currentFilter = elPuntos.filtroRegion.value;
    const currentModal = elPuntos.campoRegion.value;
    const opts = ['<option value="">Todas las regiones</option>']
      .concat(regiones.map(r => `<option value="${r.nombre}">${r.nombre}</option>`));
    elPuntos.filtroRegion.innerHTML = opts.join('');
    elPuntos.campoRegion.innerHTML = regiones.map(r => `<option value="${r.nombre}">${r.nombre}</option>`).join('');
    if (currentFilter) elPuntos.filtroRegion.value = currentFilter;
    if (currentModal) elPuntos.campoRegion.value = currentModal;
  }

  function abrirModalPunto(punto = null) {
    elPuntos.campoId.value = punto ? punto.id : '';
    elPuntos.campoNombre.value = punto ? punto.nombre : '';
    elPuntos.campoRegion.value = punto ? punto.region : (elPuntos.campoRegion.value || '');
    const t = document.getElementById('tituloModalPunto');
    if (t) t.textContent = punto ? 'Editar Punto' : 'Nuevo Punto';
    bootstrap.Modal.getOrCreateInstance(elPuntos.modal).show();
  }

  function wirePuntos() {
    elPuntos.tbody = document.querySelector('#tablaPuntos tbody');
    elPuntos.buscar = document.getElementById('buscarPunto');
    elPuntos.filtroRegion = document.getElementById('filtroRegionPunto');
    elPuntos.btnNuevo = document.getElementById('btnNuevoPunto');
    elPuntos.modal = document.getElementById('modalPunto');
    elPuntos.campoId = document.getElementById('puntoId');
    elPuntos.campoNombre = document.getElementById('puntoNombre');
    elPuntos.campoRegion = document.getElementById('puntoRegion');
    elPuntos.btnGuardar = document.getElementById('btnGuardarPunto');
    if (!elPuntos.tbody) return;

    // Cargar regiones para selects, luego puntos
    apiGet('regiones/lista').then(({ regiones }) => {
      poblarSelectRegionesPuntos(regiones);
      cargarPuntos();
    }).catch(console.error);

    elPuntos.buscar.addEventListener('input', debounce(cargarPuntos, 250));
    elPuntos.filtroRegion.addEventListener('change', cargarPuntos);
    elPuntos.btnNuevo.addEventListener('click', () => abrirModalPunto());
    elPuntos.btnGuardar.addEventListener('click', async () => {
      const id = elPuntos.campoId.value.trim();
      const nombre = elPuntos.campoNombre.value.trim();
      const region = elPuntos.campoRegion.value.trim();
      if (!nombre || !region) { toast('Completa nombre y región'); return; }
      try {
        if (id) await apiPost('puntos/renombrar', { id: Number(id), nuevo_nombre: nombre, nueva_region: region });
        else await apiPost('puntos/crear', { nombre, region });
        bootstrap.Modal.getInstance(elPuntos.modal)?.hide();
        toast('Punto guardado');
        cargarPuntos();
      } catch (e) { toast(e.message || 'Error guardando punto'); }
    });

    elPuntos.tbody.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('button'); if (!btn) return;
      if (btn.dataset.edit) {
        const punto = JSON.parse(btn.dataset.edit);
        abrirModalPunto(punto);
      } else if (btn.dataset.del) {
        if (!confirm('¿Eliminar este punto?')) return;
        try { await apiPost('puntos/eliminar', { id: Number(btn.dataset.del) }); toast('Punto eliminado'); cargarPuntos(); } catch (e) { toast(e.message); }
      }
    });
  }

  // =============================================================
  // EJERCICIOS (Evaluación Física) + Plantilla 0–10 por edades
  // =============================================================
  const elEj = {
    tbody: null,
    btnNuevo: null
  };

  const EDADES_PLANTILLA = [
    [18,25],[26,30],[31,35],[36,40],[41,45],[46,50],[51,55]
  ];
  const CATEGORIAS = [
    { etiqueta: 'IRREGULAR', puntos: 4 },
    { etiqueta: 'REGULAR',   puntos: 6 },
    { etiqueta: 'BIEN',      puntos: 7 },
    { etiqueta: 'MUY BIEN',  puntos: 8 },
    { etiqueta: 'EXCELENTE', puntos: 10 }
  ];

  function renderEjercicios(items = []) {
    elEj.tbody.innerHTML = '';
    items.forEach(e => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${e.clave}</td>
        <td>${e.nombre}</td>
        <td>${e.unidad}</td>
        <td>${e.mejor_mayor ? 'Sí' : 'No'}</td>
        <td>${e.activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
        <td>
          <button class="btn btn-sm btn-primary me-1" data-edit='${JSON.stringify(e)}'><i class="fas fa-pen"></i></button>
          <button class="btn btn-sm btn-danger" data-del='${e.id}'><i class="fas fa-trash"></i></button>
        </td>`;
      elEj.tbody.appendChild(tr);
    });
  }

  async function cargarEjercicios() {
    const { ejercicios } = await apiGet('fisicas/ejercicios');
    renderEjercicios(ejercicios);
  }

  function construirTablaPlantilla(container, reglasExistentes, unidad) {
    const table = document.createElement('table');
    table.className = 'table table-sm table-bordered align-middle';
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');
    trh.innerHTML = `
      <th rowspan="2" class="text-center">Edad</th>
      <th colspan="2" class="text-center">Irregular (4)</th>
      <th colspan="2" class="text-center">Regular (6)</th>
      <th colspan="2" class="text-center">Bien (7)</th>
      <th colspan="2" class="text-center">Muy Bien (8)</th>
      <th colspan="2" class="text-center">Excelente (10)</th>`;
    const trh2 = document.createElement('tr');
    for (let i = 0; i < 5; i++) trh2.innerHTML += `<th>Desde</th><th>Hasta</th>`;
    thead.appendChild(trh); thead.appendChild(trh2);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    EDADES_PLANTILLA.forEach(([emin, emax]) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${emin}-${emax}</td>`;
      CATEGORIAS.forEach(cat => {
        // Encuentra una regla existente que cubra este rango de edad y etiqueta
        let r = null;
        if (Array.isArray(reglasExistentes)) {
          r = reglasExistentes.find(rr => rr.etiqueta === cat.etiqueta && Number(rr.edad_min) <= emin && Number(rr.edad_max) >= emax);
        }
        const vmin = r ? r.valor_min : '';
        const vmax = r ? r.valor_max : '';
        tr.innerHTML += `
          <td><input type="number" step="any" class="form-control form-control-sm" data-emin="${emin}" data-emax="${emax}" data-etq="${cat.etiqueta}" data-campo="min" placeholder="${unidad==='segundos'?'seg':''}" value="${vmin}"></td>
          <td><input type="number" step="any" class="form-control form-control-sm" data-emin="${emin}" data-emax="${emax}" data-etq="${cat.etiqueta}" data-campo="max" placeholder="${unidad==='segundos'?'seg':''}" value="${vmax}"></td>`;
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.innerHTML = '';
    container.appendChild(table);
  }

  function crearModalEjercicio() {
    const html = `
      <div class="modal fade" id="modalEjercicio" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl"><div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="tituloModalEjercicio">Nuevo Ejercicio</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label">Clave</label>
                <input id="ejClave" type="text" class="form-control" placeholder="p. ej. corrida">
              </div>
              <div class="col-md-5">
                <label class="form-label">Nombre</label>
                <input id="ejNombre" type="text" class="form-control" placeholder="p. ej. Corrida 12 min">
              </div>
              <div class="col-md-2">
                <label class="form-label">Unidad</label>
                <select id="ejUnidad" class="form-select">
                  <option value="metros">metros</option>
                  <option value="repeticiones">repeticiones</option>
                  <option value="segundos">segundos</option>
                </select>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="ejMejorMayor" checked>
                  <label class="form-check-label" for="ejMejorMayor">Mejor es mayor</label>
                </div>
              </div>
            </div>
            <hr>
            <h6 class="mt-2">Plantilla de rangos por edad (0–10)</h6>
            <div id="contenedorPlantilla"></div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarEjercicio">Guardar</button>
          </div>
        </div></div>
      </div>`;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);
    return document.getElementById('modalEjercicio');
  }

  async function abrirModalEjercicio(ej = null) {
    let modalEl = document.getElementById('modalEjercicio');
    if (!modalEl) modalEl = crearModalEjercicio();
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const titulo = document.getElementById('tituloModalEjercicio');
    const inpClave = document.getElementById('ejClave');
    const inpNombre = document.getElementById('ejNombre');
    const selUnidad = document.getElementById('ejUnidad');
    const chkMejor = document.getElementById('ejMejorMayor');
    const contPlantilla = document.getElementById('contenedorPlantilla');
    const btnGuardar = document.getElementById('btnGuardarEjercicio');

    // Reset
    inpClave.disabled = !!ej; // no permitir cambiar clave en edición
    inpClave.value = ej ? ej.clave : '';
    inpNombre.value = ej ? ej.nombre : '';
    selUnidad.value = ej ? ej.unidad : 'metros';
    chkMejor.checked = ej ? !!ej.mejor_mayor : true;
    titulo.textContent = ej ? 'Editar Ejercicio' : 'Nuevo Ejercicio';

    // Cargar reglas existentes si hay edición
    let reglasExistentes = [];
    if (ej) {
      try {
        const { reglas } = await apiGet('fisicas/reglas/lista', { ejercicio: ej.clave });
        reglasExistentes = Array.isArray(reglas) ? reglas : [];
      } catch (e) { console.warn('No se pudieron cargar reglas:', e); }
    }
    construirTablaPlantilla(contPlantilla, reglasExistentes, selUnidad.value);

    selUnidad.onchange = () => construirTablaPlantilla(contPlantilla, reglasExistentes, selUnidad.value);

    btnGuardar.onclick = async () => {
      const clave = (inpClave.value || '').trim().toLowerCase();
      const nombre = (inpNombre.value || '').trim();
      const unidad = selUnidad.value;
      const mejor_mayor = chkMejor.checked ? 1 : 0;
      if (!clave && !ej) { toast('Escribe la clave'); return; }
      if (!nombre) { toast('Escribe el nombre'); return; }

      try {
        if (!ej) {
          await apiPost('fisicas/ejercicios/crear', { clave, nombre, unidad, mejor_mayor });
        } else {
          await apiPost('fisicas/ejercicios/actualizar', { id: ej.id, nombre, unidad, mejor_mayor });
        }

        // Construir reglas desde la plantilla
        const inputs = Array.from(contPlantilla.querySelectorAll('input[data-campo]'));
        const porCelda = {};
        inputs.forEach(inp => {
          const emin = inp.dataset.emin, emax = inp.dataset.emax, etq = inp.dataset.etq, campo = inp.dataset.campo;
          const key = `${emin}-${emax}-${etq}`;
          porCelda[key] = porCelda[key] || { edad_min: Number(emin), edad_max: Number(emax), etiqueta: etq, puntos: (CATEGORIAS.find(c=>c.etiqueta===etq)||{}).puntos || 0 };
          const val = inp.value === '' ? null : Number(inp.value);
          if (campo === 'min') porCelda[key].valor_min = val;
          else porCelda[key].valor_max = val;
        });
        const reglas = Object.values(porCelda)
          .filter(r => r.valor_min !== null && r.valor_max !== null)
          .map(r => ({...r, valor_min: Number(r.valor_min), valor_max: Number(r.valor_max)}));

        const claveDestino = ej ? ej.clave : clave;
        await apiPost('fisicas/reglas/guardar', { ejercicio: claveDestino, reglas });

        toast('Ejercicio guardado');
        modal.hide();
        cargarEjercicios();
      } catch (e) {
        console.error(e);
        toast(e.message || 'Error guardando ejercicio');
      }
    };

    modal.show();
  }

  function wireEjercicios() {
    elEj.tbody = document.querySelector('#tablaEjercicios tbody');
    elEj.btnNuevo = document.getElementById('btnNuevoEjercicio');
    if (!elEj.tbody) return;
    cargarEjercicios().catch(console.error);
    elEj.btnNuevo.addEventListener('click', () => abrirModalEjercicio());
    elEj.tbody.addEventListener('click', async ev => {
      const btn = ev.target.closest('button'); if (!btn) return;
      if (btn.dataset.edit) {
        const ej = JSON.parse(btn.dataset.edit);
        abrirModalEjercicio(ej);
      } else if (btn.dataset.del) {
        if (!confirm('¿Desactivar este ejercicio?')) return;
        try { await apiPost('fisicas/ejercicios/eliminar', { id: Number(btn.dataset.del) }); toast('Ejercicio desactivado'); cargarEjercicios(); } catch (e) { toast(e.message); }
      }
    });
  }

  // =============================================================
  // USUARIOS
  // =============================================================
  const elUsers = {
    tbody: null,
    buscar: null,
    btnNuevo: null,
    modal: null,
    campoId: null,
    campoUsuario: null,
    campoPass: null,
    campoRol: null,
    campoNoEmp: null,
    btnGuardar: null
  };

  function renderUsuarios(items = []) {
    elUsers.tbody.innerHTML = '';
    items.forEach(u => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${u.usuario}</td>
        <td>${u.nombre_empleado || '-'}</td>
        <td>${u.rol}</td>
        <td>${u.activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
        <td>
          <button class="btn btn-sm btn-primary me-1" data-edit='${JSON.stringify(u)}'><i class="fas fa-pen"></i></button>
          <button class="btn btn-sm btn-warning me-1" data-reset='${u.id}' title="Restablecer contraseña"><i class="fas fa-key"></i></button>
          <button class="btn btn-sm btn-danger" data-del='${u.id}'><i class="fas fa-user-slash"></i></button>
        </td>`;
      elUsers.tbody.appendChild(tr);
    });
  }

  async function cargarUsuarios() {
    const buscar = (elUsers.buscar.value || '').trim();
    const { usuarios } = await apiGet('usuarios/lista', { buscar });
    renderUsuarios(usuarios);
  }

  function abrirModalUsuario(u = null) {
    elUsers.campoId.value = u ? u.id : '';
    elUsers.campoUsuario.value = u ? u.usuario : '';
    elUsers.campoPass.value = '';
    elUsers.campoRol.value = u ? u.rol : 'vigilante';
    elUsers.campoNoEmp.value = u ? (u.empleado_no_emp || '') : '';
    const t = document.getElementById('tituloModalUsuario');
    if (t) t.textContent = u ? 'Editar Usuario' : 'Nuevo Usuario';
    bootstrap.Modal.getOrCreateInstance(elUsers.modal).show();
  }

  function wireUsuarios() {
    elUsers.tbody = document.querySelector('#tablaUsuarios tbody');
    elUsers.buscar = document.getElementById('buscarUsuario');
    elUsers.btnNuevo = document.getElementById('btnNuevoUsuario');
    elUsers.modal = document.getElementById('modalUsuario');
    elUsers.campoId = document.getElementById('usuarioId');
    elUsers.campoUsuario = document.getElementById('usuarioNombre');
    elUsers.campoPass = document.getElementById('usuarioPass');
    elUsers.campoRol = document.getElementById('usuarioRol');
    elUsers.campoNoEmp = document.getElementById('usuarioNoEmp');
    elUsers.btnGuardar = document.getElementById('btnGuardarUsuario');
    if (!elUsers.tbody) return;

    cargarUsuarios().catch(console.error);
    elUsers.buscar.addEventListener('input', debounce(cargarUsuarios, 250));
    elUsers.btnNuevo.addEventListener('click', () => abrirModalUsuario());
    elUsers.btnGuardar.addEventListener('click', async () => {
      const id = elUsers.campoId.value.trim();
      const usuario = elUsers.campoUsuario.value.trim();
      const rol = elUsers.campoRol.value;
      const empleado_no_emp = elUsers.campoNoEmp.value.trim();
      try {
        if (id) {
          // actualizar datos (usuario/rol/empleado)
          await apiPost('usuarios/actualizar', { id: Number(id), usuario, rol, empleado_no_emp });
          // si se escribió contraseña, resetear
          const pass = elUsers.campoPass.value.trim();
          if (pass) await apiPost('usuarios/reset', { id: Number(id), nueva: pass });
        } else {
          const pass = elUsers.campoPass.value.trim();
          if (!usuario || !pass) { toast('Usuario y contraseña son obligatorios'); return; }
          await apiPost('usuarios/crear', { usuario, contrasena: pass, rol, empleado_no_emp });
        }
        bootstrap.Modal.getInstance(elUsers.modal)?.hide();
        toast('Usuario guardado');
        cargarUsuarios();
      } catch (e) { toast(e.message || 'Error guardando usuario'); }
    });

    elUsers.tbody.addEventListener('click', async ev => {
      const btn = ev.target.closest('button'); if (!btn) return;
      if (btn.dataset.edit) {
        const u = JSON.parse(btn.dataset.edit);
        abrirModalUsuario(u);
      } else if (btn.dataset.reset) {
        const nueva = prompt('Nueva contraseña:');
        if (!nueva) return;
        try { await apiPost('usuarios/reset', { id: Number(btn.dataset.reset), nueva }); toast('Contraseña actualizada'); } catch (e) { toast(e.message); }
      } else if (btn.dataset.del) {
        if (!confirm('¿Desactivar este usuario?')) return;
        try { await apiPost('usuarios/eliminar', { id: Number(btn.dataset.del) }); toast('Usuario desactivado'); cargarUsuarios(); } catch (e) { toast(e.message); }
      }
    });
  }

  // =============================================================
  // Arranque
  // =============================================================
  document.addEventListener('DOMContentLoaded', () => {
    try { wirePuestos(); } catch (e) { console.warn('Puestos: ', e); }
    try { wireRegiones(); } catch (e) { console.warn('Regiones: ', e); }
    try { wirePuntos(); } catch (e) { console.warn('Puntos: ', e); }
    try { wireEjercicios(); } catch (e) { console.warn('Ejercicios: ', e); }
    try { wireUsuarios(); } catch (e) { console.warn('Usuarios: ', e); }
  });
})();

