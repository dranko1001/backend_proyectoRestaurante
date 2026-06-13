# Archivo — migraciones Laravel por defecto

Estos archivos eran plantillas de Laravel (`users`, `cache`, `jobs`) y **no se usan** en este proyecto:

- La autenticación usa `usuario` (tenant) y `master_users` (master).
- El esquema tenant se importa desde `restaurante.sql` + `tenant_patches/`.

Se conservan solo como referencia. **No** los copies de vuelta a `database/migrations/`.
