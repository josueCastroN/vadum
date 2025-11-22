(()=>{
  const toast = (m)=> (window.VADUM_UI?.mostrarToast? VADUM_UI.mostrarToast(m): alert(m));
  const clean = (t)=> (t||'').replace(/^\uFEFF|^\u200B|^\ufeff/, '');
  async function apiGet(ruta, params={}){ const qs=new URLSearchParams(params).toString(); const r=await fetch(`../api/index.php?ruta=${encodeURIComponent(ruta)}${qs?`&${qs}`:''}`,{credentials:'include'}); const t=await r.text(); const j=t?JSON.parse(clean(t)):{ }; if(!r.ok||j.ok===false) throw new Error(j.error||`Error ${r.status}`); return j; }
  async function apiPost(ruta, body={}){ const r=await fetch(`../api/index.php?ruta=${encodeURIComponent(ruta)}`,{method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}); const t=await r.text(); const j=t?JSON.parse(clean(t)):{ }; if(!r.ok||j.ok===false) throw new Error(j.error||`Error ${r.status}`); return j; }

  const btnCap = document.getElementById('tabBtnCaptura');
  const btnHist = document.getElementById('tabBtnHist');
  const tabCap = document.getElementById('tabCaptura');
  const tabHist = document.getElementById('tabHistorial');
  if (btnCap&&btnHist){
    btnCap.addEventListener('click', ()=>{ btnCap.classList.add('active'); btnHist.classList.remove('active'); tabCap.style.display='block'; tabHist.style.display='none'; });
    btnHist.addEventListener('click', ()=>{ btnHist.classList.add('active'); btnCap.classList.remove('active'); tabCap.style.display='none'; tabHist.style.display='block'; });
  }

  const selPunto = document.getElementById('selPunto');
  const selGuardia = document.getElementById('selGuardia');
  const fotoDiv = document.getElementById('foto');
  const selPuntoHist = document.getElementById('selPuntoHist');
  const selGuardiaHist = document.getElementById('selGuardiaHist');
  const tbodyHist = document.querySelector('#tablaHist tbody');

  (async ()=>{
    const me = await apiGet('auth/me').catch(()=>({}));
    async function cargarPuntos(){
      if (me?.logeado && me.usuario?.rol==='cliente' && me.usuario?.punto_id){
        selPunto.innerHTML = `<option value="${me.usuario.punto_id}">Punto #${me.usuario.punto_id}</option>`; selPunto.disabled=true;
        selPuntoHist.innerHTML = `<option value="${me.usuario.punto_id}">Punto #${me.usuario.punto_id}</option>`;
        await cargarGuardias(me.usuario.punto_id); await cargarGuardiasHist(me.usuario.punto_id);
      } else {
        try{ const { puntos } = await apiGet('puntos/lista');
          selPunto.innerHTML = '<option value="">Seleccione punto…</option>' + (puntos||[]).map(p=>`<option value="${p.id}">${p.nombre} (${p.region})</option>`).join('');
          selPuntoHist.innerHTML = '<option value="">Todos los puntos…</option>' + (puntos||[]).map(p=>`<option value="${p.id}">${p.nombre} (${p.region})</option>`).join('');
        }catch(e){ console.error(e); toast('No se pudieron cargar los puntos'); }
      }
    }
    selPunto?.addEventListener('change', ()=>{ const pid=selPunto.value; if(!pid){ selGuardia.innerHTML=''; fotoDiv.innerHTML=''; return;} cargarGuardias(pid); });
    selPuntoHist?.addEventListener('change', ()=>{ const pid=selPuntoHist.value; cargarGuardiasHist(pid||''); });

    async function cargarGuardias(puntoId){ try{ const { resultados } = await apiGet('empleados/por_punto',{ punto_id:puntoId }); const solo=(resultados||[]).filter(e=> (e.puesto||'').toLowerCase().includes('vigil')); selGuardia.innerHTML = solo.map(emp=>{ const text=`${emp.no_emp} - ${emp.nombre}`; const foto=emp.foto_url||''; return `<option value="${emp.no_emp}" data-foto="${foto}">${text}</option>`; }).join(''); actualizarFoto(); }catch(e){ console.error(e); selGuardia.innerHTML=''; fotoDiv.innerHTML=''; } }
    async function cargarGuardiasHist(puntoId){ try{ if(!puntoId){ selGuardiaHist.innerHTML='<option value="">Todos…</option>'; return;} const { resultados } = await apiGet('empleados/por_punto',{ punto_id:puntoId }); const solo=(resultados||[]).filter(e=> (e.puesto||'').toLowerCase().includes('vigil')); selGuardiaHist.innerHTML = '<option value="">Todos…</option>' + solo.map(emp=>`<option value="${emp.no_emp}">${emp.no_emp} - ${emp.nombre}</option>`).join(''); }catch(e){ console.error(e); selGuardiaHist.innerHTML='<option value="">Todos…</option>'; } }
    function actualizarFoto(){ const foto = selGuardia?.selectedOptions[0]?.dataset.foto || ''; fotoDiv.innerHTML = foto? `<img src="${foto}" style="max-width:160px;border:1px solid #eaeaea;border-radius:12px;">` : '<div class="chip">Sin foto</div>'; }
    selGuardia?.addEventListener('change', actualizarFoto);

    document.getElementById('guardar')?.addEventListener('click', async ()=>{
      const edificio_id = Number(selPunto.value||0); const no_emp=selGuardia.value||'';
      const servicio=Number(document.getElementById('s').value||0);
      const actitud=Number(document.getElementById('a').value||0);
      const respuesta=Number(document.getElementById('r').value||0);
      const confiabilidad=Number(document.getElementById('c').value||0);
      const comentarios=(document.getElementById('com').value||'').trim();
      if (!edificio_id) return toast('Selecciona un punto'); if (!no_emp) return toast('Selecciona un guardia');
      try{ const res = await apiPost('encuesta/guardar',{ edificio_id, no_emp, fecha:new Date().toISOString().slice(0,10), servicio, actitud, respuesta, confiabilidad, comentarios }); const cal = res.cal_0_100 ?? Math.round(((servicio+actitud+respuesta+confiabilidad)/20)*100); toast(`¡Gracias! Calificación ${cal}%`); document.getElementById('com').value=''; }catch(err){ console.error(err); toast(err.message||'Error al enviar encuesta'); }
    });

    function setMeses(){ const hoy=new Date(); const y=hoy.getFullYear(); const m=("0"+(hoy.getMonth()+1)).slice(-2); document.getElementById('hasta').value=`${y}-${m}`; const hace3=new Date(hoy.getFullYear(),hoy.getMonth()-2,1); document.getElementById('desde').value=`${hace3.getFullYear()}-${("0"+(hace3.getMonth()+1)).slice(-2)}`; }
    setMeses();
    async function buscarHistorial(){ const punto_id=selPuntoHist.value||''; const no_emp=selGuardiaHist.value||''; const desde=document.getElementById('desde').value||''; const hasta=document.getElementById('hasta').value||''; try{ const res=await apiGet('encuesta/historial',{ punto_id, no_emp, desde, hasta }); const filas = res.encuestas||res.resultados||res.items||res.rows||[]; tbodyHist.innerHTML = filas.map(f=>{ const cal=f.cal_0_100 ?? Math.round(((Number(f.servicio)||0 + Number(f.actitud)||0 + Number(f.respuesta)||0 + Number(f.confiabilidad)||0)/20)*100); const fecha=(f.fecha||'').slice(0,10); const pto=f.punto_nombre||f.punto||(punto_id?`#${punto_id}`:''); const com=(f.comentarios||''); return `<tr><td>${fecha}</td><td>${pto}</td><td>${f.no_emp||''}</td><td>${f.nombre||''}</td><td>${f.servicio||''}</td><td>${f.actitud||''}</td><td>${f.respuesta||''}</td><td>${f.confiabilidad||''}</td><td>${cal}</td><td>${com}</td></tr>`; }).join(''); if(!filas.length) tbodyHist.innerHTML='<tr><td colspan="10" class="text-center">Sin resultados</td></tr>'; }catch(err){ console.error(err); toast(err.message||'No se pudo cargar el historial'); } }
    document.getElementById('btnBuscarHist')?.addEventListener('click', buscarHistorial);
    await cargarPuntos();
  })();
})();

