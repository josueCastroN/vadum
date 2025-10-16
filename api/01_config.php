<?php
// =====================================
// Configuración general (BD y rutas)
// =====================================

// Credenciales de MariaDB (XAMPP)
const BD_HOST     = '127.0.0.1';
const BD_PUERTO   = 3306;
const BD_NOMBRE   = 'vadum_db';
const BD_USUARIO  = 'root';
const BD_PASSWORD = '';

// Ruta del almacenamiento de firmas (PNG)
define('RUTA_FIRMAS', __DIR__ . '/../almacenamiento/firmas');

// URL base pública (ajústala si tu carpeta tiene otro nombre)
define('URL_BASE', 'http://localhost/vadum');
