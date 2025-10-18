/* ==========================================================
   VADUM · UI helpers (toasts y modales)
   - showToast(mensaje, tipo='info')
   - mostrarConfirmar(opciones) => Promise<boolean>
   - mostrarFormulario(opciones) => Promise<{ok:boolean, datos:any}>
   ========================================================== */

// ==== Toasts =================================================
(function prepararToasts(){
  if (!document.getElementById('toast-contenedor')) {
    const c = document.createElement('div');
    c.id = 'toast-contenedor';
    c.setAttribute('aria-live','polite');
    document.body.appendChild(c);
  }
})();

/**
 * Muestra un toast elegante en la esquina inferior derecha
 * tipo: 'info' | 'ok' | 'error'
 */
function showToast(mensaje, tipo='info'){
  const cont = document.getElementById('toast-contenedor');
  const t = document.createElement('div');
  t.className = `toast toast--${tipo}`;
  t.innerHTML = `<div class="toast__texto">${mensaje}</div>`;
  cont.appendChild(t);
  // animación / auto-cierre
  setTimeout(()=>{ t.classList.add('toast--show'); }, 10);
  setTimeout(()=>{
    t.classList.remove('toast--show');
    setTimeout(()=> t.remove(), 250);
  }, 3500);
}

// ==== Modales (confirmar / formulario) ======================

(function prepararModal(){
  if (!document.getElementById('modal-capa')) {
    const capa = document.createElement('div');
    capa.id = 'modal-capa';
    capa.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
        <div class="modal__cabeza">
          <h3 id="modal-titulo" class="modal__titulo">Título</h3>
          <button class="modal__cerrar" id="modal-cerrar" aria-label="Cerrar">×</button>
        </div>
        <div class="modal__cuerpo" id="modal-cuerpo">Contenido</div>
        <div class="modal__pie" id="modal-pie">
          <button class="btn btn--borde" id="modal-cancelar">Cancelar</button>
          <button class="btn" id="modal-aceptar">Aceptar</button>
        </div>
      </div>`;
    document.body.appendChild(capa);
    capa.addEventListener('click', (e)=>{
      if (e.target.id==='modal-capa') cerrarModal();
    });
    document.getElementById('modal-cerrar').onclick = cerrarModal;
    document.getElementById('modal-cancelar').onclick = cerrarModal;
  }
})();

function abrirModal(){ document.getElementById('modal-capa').classList.add('activo'); }
function cerrarModal(){ document.getElementById('modal-capa').classList.remove('activo'); }

/**
 * Modal de confirmación
 * opciones: {titulo, mensaje, textoAceptar='Aceptar', textoCancelar='Cancelar', enfasisAceptar=true}
 * return Promise<boolean>
 */
function mostrarConfirmar(opciones={}){
  return new Promise(resolve=>{
    const { titulo='Confirmar', mensaje='', textoAceptar='Aceptar', textoCancelar='Cancelar', enfasisAceptar=true } = opciones;
    document.getElementById('modal-titulo').textContent = titulo;
    document.getElementById('modal-cuerpo').innerHTML = `<p>${mensaje}</p>`;
    const btnOk = document.getElementById('modal-aceptar');
    const btnCancel = document.getElementById('modal-cancelar');
    btnOk.textContent = textoAceptar;
    btnCancel.textContent = textoCancelar;
    // estilo énfasis
    if (enfasisAceptar) { btnOk.classList.remove('btn--borde'); btnCancel.classList.add('btn--borde'); }
    else { btnOk.classList.add('btn--borde'); btnCancel.classList.remove('btn--borde'); }
    // listeners
    const onOk = ()=>{ limpiar(); cerrarModal(); resolve(true); };
    const onCancel = ()=>{ limpiar(); cerrarModal(); resolve(false); };
    function limpiar(){ btnOk.onclick=null; btnCancel.onclick=null; }
    btnOk.onclick = onOk; btnCancel.onclick = onCancel;
    abrirModal();
  });
}

/**
 * Modal formulario simple (reemplaza prompt múltiple)
 * opciones: {
 *   titulo, descripcion,
 *   campos: [{id,label,tipo='text',placeholder='',valor='',requerido=false}],
 *   textoAceptar='Guardar', textoCancelar='Cancelar'
 * }
 * return Promise<{ok:boolean, datos:any}>
 */
function mostrarFormulario(opciones={}){
  return new Promise(resolve=>{
    const {
      titulo='Formulario',
      descripcion='',
      campos=[],
      textoAceptar='Guardar',
      textoCancelar='Cancelar'
    } = opciones;

    document.getElementById('modal-titulo').textContent = titulo;
    const cuerpo = document.getElementById('modal-cuerpo');
    const btnOk = document.getElementById('modal-aceptar');
    const btnCancel = document.getElementById('modal-cancelar');
    btnOk.textContent = textoAceptar;
    btnCancel.textContent = textoCancelar;

    // construir formulario
    const formId = 'modal-form';
    cuerpo.innerHTML = `
      ${descripcion ? `<p>${descripcion}</p>`:''}
      <form id="${formId}" class="modal__form">
        ${campos.map(c=>`
          <label class="label" for="${c.id}">${c.label}${c.requerido?' *':''}</label>
          <input class="input" id="${c.id}" name="${c.id}" type="${c.tipo||'text'}"
                 placeholder="${c.placeholder||''}" value="${c.valor||''}" ${c.requerido?'required':''}>
        `).join('')}
      </form>`;

    const formulario = document.getElementById(formId);

    btnOk.onclick = ()=>{
      if (!formulario.checkValidity()){
        showToast('Por favor completa los campos requeridos.', 'error');
        return;
      }
      const datos = {};
      campos.forEach(c=>{ datos[c.id] = (document.getElementById(c.id).value || '').trim(); });
      cerrarModal();
      resolve({ok:true, datos});
      btnOk.onclick=null; btnCancel.onclick=null;
    };
    btnCancel.onclick = ()=>{
      cerrarModal();
      resolve({ok:false, datos:null});
      btnOk.onclick=null; btnCancel.onclick=null;
    };
    abrirModal();
  });
}
