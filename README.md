# BeardShop

Panel web responsive para la gestión de la barbería, inspirado en el prototipo Figma: resumen, agenda, clientes, servicios, equipo, reportes y ayuda.

## Requisitos

- PHP 8.0 o posterior con las extensiones PDO y PDO MySQL.
- MySQL 8.0 o MariaDB compatible.

## Puesta en marcha

1. Crea la base y las tablas ejecutando `database/schema.sql` en MySQL. Si ya tenías una base con el esquema anterior, respáldala y ejecuta `database/migracion.sql`.
2. Configura la conexión antes de iniciar PHP. Puedes definir `BEARDSHOP_DB_HOST`, `BEARDSHOP_DB_PORT`, `BEARDSHOP_DB_NAME`, `BEARDSHOP_DB_USER` y `BEARDSHOP_DB_PASSWORD`. Por defecto se usa `127.0.0.1:3306`, base `db_barberia`, usuario `root` y contraseña vacía (configuración local de XAMPP).
3. Ejecuta `database/datos_prueba.sql` una sola vez para crear la cuenta de demostración y cargar ejemplos.
4. Desde la carpeta del proyecto inicia el servidor local con `php -S localhost:8000` y abre `http://localhost:8000`.

La conexión PDO está en `CONEXION.php`. Las credenciales no se deben guardar en el repositorio; utiliza variables de entorno para instalaciones compartidas. Si la base de datos no está disponible, el panel muestra una vista previa claramente identificada y no guarda los formularios.

El acceso de administración acepta cuentas con rol `Administrador` o `Barbero`; las contraseñas deben guardarse con `password_hash()`. La cuenta del archivo de datos de prueba puede iniciar sesión con `admin@barbershop.com` y la contraseña `password`.

`database/schema.sql` es seguro para una instalación nueva y no elimina bases existentes. La migración agrega únicamente los campos necesarios a una instalación previa.