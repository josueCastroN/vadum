

(function () {
  'use strict';

  // --------------------------------------------------------------------------
  // Función: marcarActivoSegunURL
  // Marca como "activo" el enlace del menú lateral que coincida con la URL.
  // --------------------------------------------------------------------------
  function marcarActivoSegunURL() {
    try {
      const enlaces = document.querySelectorAll('#menuLateral .menu-link');
      const paginaActual = location.pathname.split('/').pop() || 'index.php';

      enlaces.forEach((a) => {
        a.classList.remove('activo');
        const href = a.getAttribute('href') || '';
        if (href === paginaActual) a.classList.add('activo');
      });
    } catch (e) {
      console.warn('UI: no se pudo marcar el menú activo:', e);
    }
  }

  // --------------------------------------------------------------------------
  // Función: inicializarPaginadoresAuto
  // Busca tablas que usen data-atributos: data-paginador, data-buscador, data-registros.
  // Llama al paginador base (paginador_tablas.js) para cada una.
  // --------------------------------------------------------------------------
  function inicializarPaginadoresAuto() {
    if (typeof window.inicializarPaginadorTabla !== 'function') {
      console.warn('UI: paginador_tablas.js no está cargado.');
      return;
    }

    const tablas = document.querySelectorAll('table.tabla-paginada[data-paginador]');
    tablas.forEach((tabla) => {
      const idTabla = tabla.getAttribute('id');
      const idPaginador = tabla.getAttribute('data-paginador');
      const idBuscador = tabla.getAttribute('data-buscador') || null;
      const registros = Number(tabla.getAttribute('data-registros') || 20);

      if (!idTabla) {
        console.warn('UI: una tabla paginada no tiene atributo id.');
        return;
      }

      inicializarPaginadorTabla({
        idTabla,
        idPaginador,
        idBuscador,
        registrosPorPagina: registros
      });
    });
  }

  // --------------------------------------------------------------------------
  // Helpers opcionales: toast y overlay de carga
  // --------------------------------------------------------------------------
  function mostrarToast(mensaje = 'Operación realizada', tiempoMs = 2500) {
    try {
      // Creamos contenedor si no existe
      let contenedor = document.getElementById('contenedorToasts');
      if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'contenedorToasts';
        contenedor.className = 'position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(contenedor);
      }

      // Toast básico Bootstrap
      const wrapper = document.createElement('div');
      wrapper.className = 'toast align-items-center text-bg-dark border-0';
      wrapper.role = 'alert';
      wrapper.ariaLive = 'assertive';
      wrapper.ariaAtomic = 'true';

      wrapper.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            ${mensaje}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      `;

      contenedor.appendChild(wrapper);
      const toast = new bootstrap.Toast(wrapper, { delay: tiempoMs });
      toast.show();

      // Remover al ocultar
      wrapper.addEventListener('hidden.bs.toast', () => wrapper.remove());
    } catch (e) {
      console.warn('UI: no se pudo mostrar el toast:', e);
      alert(mensaje); // fallback simple
    }
  }

  function mostrarOverlayCarga(mostrar = true, texto = 'Cargando...') {
    let overlay = document.getElementById('overlayCarga');

    if (mostrar) {
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'overlayCarga';
        overlay.className = 'overlay-carga d-flex align-items-center justify-content-center';
        overlay.innerHTML = `
          <div class="tarjeta-carga text-center">
            <div class="spinner-border" role="status"></div>
            <div class="mt-2">${texto}</div>
          </div>
        `;
        document.body.appendChild(overlay);
      }
    } else if (overlay) {
      overlay.remove();
    }
  }

  // Exponemos helpers por si los quieres usar en otros scripts
  window.VADUM_UI = {
    marcarActivoSegunURL,
    inicializarPaginadoresAuto,
    mostrarToast,
    mostrarOverlayCarga
  };

  // Arranque automático cuando el DOM esté listo
  document.addEventListener('DOMContentLoaded', () => {
    marcarActivoSegunURL();
    inicializarPaginadoresAuto();
    try {
      // Normaliza menús (hrefs y etiquetas) y corrige textos mal codificados
      normalizarMenusYTextos();
    } catch (e) {
      console.warn('UI: normalización de menús/textos falló', e);
    }
    try { aplicarMenuEstandar(); marcarActivoSegunURL(); } catch(e){ console.warn('UI: no se pudo aplicar menú estándar', e); }
    try {
      // Arregla textos del dropdown de usuario si vienen mal codificados
      document.querySelectorAll('.top-navbar .dropdown-menu a.dropdown-item').forEach(a=>{
        const t=(a.textContent||'').trim();
        if (/Configuraci/i.test(t)) a.textContent='Configuración';
        if (/Sesi/i.test(t)) a.textContent='Cerrar Sesión';
        if (/Perfil/i.test(t)) a.textContent='Mi Perfil';
      });
      // Fallback de logout por si auth.js no enganchó
      const doLogout = async (e)=>{
        e?.preventDefault?.();
        try { await fetch('../api/index.php?ruta=auth/logout',{method:'POST',credentials:'include'}); } catch(_){ }
        finally { localStorage.removeItem('userName'); location.href='login.html'; }
      };
      const el1=document.getElementById('btnCerrarSesionDropdown'); if(el1) el1.addEventListener('click', doLogout);
      const el2=document.getElementById('btnSalir'); if(el2) el2.addEventListener('click', doLogout);
    } catch(e){ console.warn('UI: ajuste de dropdown/Logout no aplicado', e); }
  });
})();

// Normalización de menús y textos (acento/UTF-8) sin tocar HTML
(function(){
  function setHrefIf(selector, href){
    const a = document.querySelector(selector);
    if (!a) return;
    const h = a.getAttribute('href') || '';
    if (h === '' || h === '#') a.setAttribute('href', href);
  }
  function setLabelIfClientes(selector){
    const a = document.querySelector(selector);
    if (!a) return;
    const span = a.querySelector('span');
    if (span && /Clientes/i.test(span.textContent||'')) span.textContent = 'Encuesta';
  }
  function fixTextNode(t){
    if (!t || typeof t !== 'string') return t;
    const map = {
      'FÃ­sica':'Física','F�sica':'Física','F��sica':'Física',
      'EvaluaciÃ³n':'Evaluación','Evaluaci�n':'Evaluación','Evaluaci��n':'Evaluación','Evaluacion':'Evaluación',
      'CatÃ¡logos':'Catálogos','Cat�logos':'Catálogos','Catǭlogos':'Catálogos','Catalogos':'Catálogos',
      'GestiÃ³n':'Gestión','Gesti�n':'Gestión',
      'RegiÃ³n':'Región','Regi�n':'Región',
      'MenÃº':'Menú','Men�':'Menú',
      'SesiÃ³n':'Sesión','Sesi�n':'Sesión',
      'CÃ¡lculo':'Cálculo','Cǭlculo':'Cálculo',
      'AutomatizaciÃ³n':'Automatización','Automatizaci��n':'Automatización'
    };
    let out = t;
    for (const k in map) { out = out.replaceAll(k, map[k]); }
    return out;
  }
  function fixTextsIn(selectorList){
    const nodes = document.querySelectorAll(selectorList);
    nodes.forEach(el=>{ el.textContent = fixTextNode(el.textContent||''); });
  }

  window.normalizarMenusYTextos = function normalizarMenusYTextos(){
    // Corregir hrefs faltantes en menú móvil
    setHrefIf('#nav-cursos-movil','./cursos.html');
    setHrefIf('#nav-faltas-movil','./faltas.html');
    setHrefIf('#nav-faltas','./faltas.html');
    setHrefIf('#nav-actas','./actas.html');
    setHrefIf('#nav-actas-movil','./actas.html');
    // Cambiar etiqueta Clientes -> Encuesta si aparece
    setLabelIfClientes('#nav-clientes');
    setLabelIfClientes('#nav-clientes-movil');
    // Ajustar textos visibles con acentos en elementos comunes
    // Solo corrige textos en menús y dropdowns para evitar cambios indeseados
    fixTextsIn('#sidebarMenu .menu-link span, #menuOffcanvas .menu-link span, .top-navbar .dropdown-menu a');
  };
})();

// Menú estándar para todas las páginas (sidebar + offcanvas)
(function(){
  const enlaces = [
    { id:'nav-inicio', href:'./index.html', texto:'Inicio', roles:'admin,supervisor,vigilante,cliente', icon:'home', fa:'fa-home' },
    { id:'nav-empleados', href:'./empleados.html', texto:'Empleados', roles:'admin,supervisor', icon:'badge', fa:'fa-user-tie' },
    { id:'nav-fisica', href:'./captura_fisica.html', texto:'Física', roles:'admin,supervisor', icon:'insights', fa:'fa-chart-line' },
    { id:'nav-mensual', href:'./evaluacion_mensual.html', texto:'Mensual', roles:'admin,supervisor', icon:'rule', fa:'fa-users-cog' },
    { id:'nav-encuesta', href:'./encuesta.html', texto:'Encuesta', roles:'admin,supervisor,cliente', icon:'question_answer', fa:'fa-handshake' },
    { id:'nav-cursos', href:'./cursos.html', texto:'Cursos', roles:'admin,supervisor', icon:'school', fa:'fa-book-open' },
    { id:'nav-faltas', href:'./faltas.html', texto:'Faltas', roles:'admin,supervisor', icon:'event_busy', fa:'fa-calendar-times' },
    { id:'nav-actas', href:'./actas.html', texto:'Actas', roles:'admin,supervisor', icon:'description', fa:'fa-file-alt' },
    { id:'nav-incentivo', href:'./incentivo.html', texto:'Incentivo', roles:'admin,supervisor,vigilante', icon:'payments', fa:'fa-dollar-sign' },
    { id:'nav-catalogos', href:'./catalogos.html', texto:'Catálogos', roles:'admin,supervisor', icon:'settings', fa:'fa-cogs' }
  ];

  function htmlSidebar(){
    return enlaces.map(e=>
      `<a class="menu-link" id="${e.id}" href="${e.href}" data-roles="${e.roles}"><i class="material-icons-outlined">${e.icon}</i> <span>${e.texto}</span></a>`
    ).join('');
  }
  function htmlOffcanvas(){
    return enlaces.map(e=>
      `<a class="menu-link" href="${e.href}" data-roles="${e.roles}"><i class="fas ${e.fa}"></i> <span>${e.texto}</span></a>`
    ).join('');
  }

  window.aplicarMenuEstandar = function aplicarMenuEstandar(){
    // Sidebar
    const contSide = document.querySelector('#sidebarMenu .nav-links-sidebar');
    if (contSide){ contSide.innerHTML = htmlSidebar(); }
    // Offcanvas
    const contOff = document.querySelector('#menuOffcanvas .offcanvas-body nav');
    if (contOff){ contOff.innerHTML = htmlOffcanvas(); }
    // Ajusta título del offcanvas si existe
    const offLabel = document.getElementById('menuOffcanvasLabel');
    if (offLabel) offLabel.innerHTML = 'Menú VADUM';
    // Ajusta dropdown de usuario (texto seguro con entidades)
    const dd = document.querySelector('.top-navbar ul.dropdown-menu');
    if (dd){
      dd.innerHTML = `
        <li><a class="dropdown-item" href="#" id="irPerfil">Mi Perfil</a></li>
        <li><a class="dropdown-item" href="#" id="btnConfig">Configuración</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" id="btnCerrarSesionDropdown">Cerrar Sesión</a></li>
      `;
    }
  };
})();

// Normalizador global de mojibake/acentos en todo el documento
(function(){
  const pairMap = {
    // Palabras comunes ya dañadas
    'FÃ­sica':'Física','F��sica':'Física','F�sica':'Física','F\uFFFDsica':'Física',
    'EvaluaciÃ³n':'Evaluación','Evaluaci��n':'Evaluación','Evaluaci�n':'Evaluación','Evaluacion':'Evaluación',
    'CatÃ¡logos':'Catálogos','Cat�logos':'Catálogos','Catǭlogos':'Catálogos','Catalogos':'Catálogos',
    'GestiÃ³n':'Gestión','Gesti�n':'Gestión','GestiÃ³n':'Gestión',
    'RegiÃ³n':'Región','Regi�n':'Región',
    'MenÃº':'Menú','Men�':'Menú','Menǧ':'Menú',
    'SesiÃ³n':'Sesión','Sesi�n':'Sesión','Sesi��n':'Sesión',
    'CÃ¡lculo':'Cálculo','Cǭlculo':'Cálculo',
    'AutomatizaciÃ³n':'Automatización','Automatizaci��n':'Automatización',
    'MÃ©tricas':'Métricas','rÃ¡pidas':'rápidas','rǭpidas':'rápidas',
    'dÃ­a':'día','d��a':'día'
  };
  const charMap = {
    'Ã¡':'á','Ã©':'é','Ã­':'í','Ã³':'ó','Ãº':'ú','Ã±':'ñ','Ã‘':'Ñ','Ã¼':'ü','Ãœ':'Ü',
    'Â·':'·','Âº':'º','Âª':'ª','Â¿':'¿','Â¡':'¡','Â':'',
  };
  function fixStr(s){
    if (!s) return s;
    let out = s;
    for (const k in pairMap) { if (out.includes(k)) out = out.replaceAll(k, pairMap[k]); }
    for (const k in charMap) { if (out.includes(k)) out = out.replaceAll(k, charMap[k]); }
    return out;
  }
  function walkAndFix(root){
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    const updates = [];
    while (walker.nextNode()){
      const n = walker.currentNode;
      if (!n.parentElement) continue;
      const tag = n.parentElement.tagName;
      if (tag === 'SCRIPT' || tag === 'STYLE') continue;
      const fixed = fixStr(n.nodeValue);
      if (fixed !== n.nodeValue) updates.push([n, fixed]);
    }
    updates.forEach(([n, val])=>{ n.nodeValue = val; });
  }
  window.normalizarAcentosDocumento = function normalizarAcentosDocumento(){ walkAndFix(document.body || document); };
})();
