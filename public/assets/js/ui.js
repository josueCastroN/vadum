/* ======================================================
   VADUM · utilidades UI básicas (toasts y helpers)
   Código y variables en español
   ====================================================== */

// Muestra un toast flotante (mensaje corto)
function mostrarToast(mensaje = 'Acción realizada', ms = 2200) {
  const n = document.createElement('div');
  n.className = 'toast';
  n.textContent = mensaje;
  document.body.appendChild(n);
  setTimeout(() => { n.remove(); }, ms);
}

// Deshabilita/rehabilita un botón mientras haces fetch
async function conCarga(boton, promesa) {
  const txt = boton.textContent;
  boton.disabled = true;
  boton.style.opacity = .6;
  try { return await promesa; }
  finally {
    boton.disabled = false;
    boton.style.opacity = 1;
    boton.textContent = txt;
  }
}
