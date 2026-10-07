# Puesta en marcha

Desde PowerShell, dentro de `C:\Proyectos\INGHERN`:

```powershell
composer install
npm install
php84 artisan migrate
php84 artisan db:seed
php84 artisan inghern:create-admin
npm run build
php84 artisan serve
```

Si ya ejecutaste las migraciones vacías durante esta sesión de desarrollo, reinicia únicamente la base de desarrollo antes de ingresar datos reales:

```powershell
php84 artisan migrate:fresh --seed
php84 artisan inghern:create-admin
```

`migrate:fresh` elimina las tablas y sus datos. No debe usarse cuando la base ya tenga información que quieras conservar.

Para desarrollo con actualización automática de estilos:

```powershell
npm run dev
```

La aplicación quedará disponible en `http://127.0.0.1:8000`. El registro público está desactivado; los usuarios iniciales se crean con el comando de INGHERN.
