/**
 * auth.js
 * Lógica de Autenticación, Control de Acceso por Roles (ACL) y Login.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ===============================================
    // 1. SIMULACIÓN DE DATOS DE USUARIO AUTENTICADO
    //    (Usada en index.html)
    // ===============================================
    const usuarioSesion = {
        nombre: 'Juan Pérez',
        usuario: 'jperez',
        rol: 'supervisor', // <-- CAMBIA ESTO PARA PROBAR OTROS ROLES
        id_usuario: 101
    };

    // ===============================================
    // 2. ELEMENTOS DEL DOM
    // ===============================================
    // Elementos Globales (Usados en index.html y login.html)
    const sidebarMenu = document.getElementById('sidebarMenu');
    const offcanvasMenu = document.getElementById('menuOffcanvas');
    const nombreUsuarioSpan = document.getElementById('nombreUsuario');
    const btnCerrarSesionTop = document.getElementById('btnCerrarSesionTop');

    // Elementos del Login (Solo existen en login.html)
    const formLogin = document.getElementById('formLogin');
    const campoUsuario = document.getElementById('campoUsuario');
    const campoContrasena = document.getElementById('campoContrasena');
    const btnTogglePass = document.getElementById('btnTogglePass');
    const btnEntrar = document.getElementById('btnEntrar');
    const alertaMensaje = document.getElementById('alertaMensaje');
    const eyeIcon = document.getElementById('eyeIcon');
    
    // Función para mostrar mensajes de estado (Usada en login)
    const mostrarAlerta = (mensaje, tipo = 'danger') => {
        if (!alertaMensaje) return;
        alertaMensaje.textContent = mensaje;
        alertaMensaje.className = `alert alert-${tipo}`;
        alertaMensaje.classList.remove('d-none');
    };
    
    // ===============================================
    // 3. LÓGICA DE CONTROL DE ACCESO (ACL - Usada en index.html)
    // ===============================================
    
    function aplicarRestriccionesDeRol(rol, nombreUsuario) {
        if (nombreUsuarioSpan) {
            nombreUsuarioSpan.textContent = nombreUsuario;
            localStorage.setItem('userName', nombreUsuario); 
        }

        const menus = [sidebarMenu, offcanvasMenu].filter(el => el != null);

        menus.forEach(menu => {
            const enlacesNav = menu.querySelectorAll('a[data-roles]');

            enlacesNav.forEach(enlace => {
                const rolesPermitidos = enlace.getAttribute('data-roles');
                
                if (!rolesPermitidos) {
                    enlace.style.display = 'none';
                    return;
                }
                const listaRoles = rolesPermitidos.split(',').map(r => r.trim());
                enlace.style.display = listaRoles.includes(rol) ? 'flex' : 'none';
            });
        });
    }

    function verificarSesion() {
        if (!usuarioSesion.rol) {
            // No autenticado: Si no estamos en login, redirigir
            if (location.pathname.split('/').pop() !== 'login.html') {
                 // location.href = './login.html'; 
                 console.log('Usuario no autenticado. Redirigir a login.html (Simulación)');
            }
        } else {
            // Autenticado: Aplicar restricciones
            aplicarRestriccionesDeRol(usuarioSesion.rol, usuarioSesion.nombre);
        }
    }

    // ===============================================
    // 4. LÓGICA DE LOGIN (Solo se ejecuta si existen los elementos)
    // ===============================================

    // 4a. Toggle Mostrar/Ocultar Contraseña
    if (campoContrasena && btnTogglePass && eyeIcon) {
        btnTogglePass.addEventListener('click', function (e) {
            e.preventDefault();
            const currentType = campoContrasena.getAttribute('type');

            if (currentType === 'password') {
                campoContrasena.setAttribute('type', 'text');
                eyeIcon.textContent = 'visibility'; 
            } else {
                campoContrasena.setAttribute('type', 'password');
                eyeIcon.textContent = 'visibility_off';
            }
        });
    }

    // 4b. Manejo del Envío del Formulario
    if (formLogin) {
        formLogin.addEventListener('submit', async function(e) {
            e.preventDefault(); 
            mostrarAlerta("", 'd-none'); // Ocultar alerta previa

            if (!campoUsuario.value.trim() || !campoContrasena.value.trim()) {
                mostrarAlerta("Ingresa tu usuario y contraseña.");
                return;
            }
            
            // Simular carga y validación
            btnEntrar.disabled = true;
            btnEntrar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Validando...';

            const datosLogin = {
                usuario: campoUsuario.value.trim(),
                contrasena: campoContrasena.value.trim(), 
                recordar: document.getElementById('checkRecordar').checked ? 1 : 0
            };

            try {
                // *** IMPLEMENTA TU LLAMADA REAL A LA API AQUÍ ***
                // Simulación de éxito/fracaso
                const exito = (datosLogin.usuario === 'jperez' && datosLogin.contrasena === '1234');

                if (exito) {
                    mostrarAlerta("Acceso exitoso. Redirigiendo...", 'success');
                    setTimeout(() => {
                        window.location.href = './index.html'; 
                    }, 500);
                } else {
                    mostrarAlerta("Credenciales incorrectas.");
                }

            } catch (error) {
                mostrarAlerta("Error de conexión con el servidor.");
                console.error('Error en la solicitud de login:', error);
            } finally {
                btnEntrar.disabled = false;
                btnEntrar.innerHTML = '<i class="material-icons-outlined me-2">login</i> Entrar';
            }
        });
    }

    // ===============================================
    // 5. EVENTOS GLOBALES
    // ===============================================

    // Evento de Cerrar Sesión
    if (btnCerrarSesionTop) {
        btnCerrarSesionTop.addEventListener('click', (e) => {
            e.preventDefault();
            // Lógica real de cerrar sesión
            // location.href = './login.html'; 
            console.log('Sesión cerrada. Redirigir a login.html');
        });
    }

    // 6. EJECUCIÓN INICIAL
    verificarSesion();
});