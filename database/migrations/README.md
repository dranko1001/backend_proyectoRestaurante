# Migraciones — estructura

Este directorio **no** debe contener archivos `*_*.php` en la raíz.

| Subcarpeta | Comando | Base de datos |
|------------|---------|---------------|
| `master/` | `php artisan master:migrate` | `restaurante_master` |
| `tenant_patches/` | `php artisan migrate --path=database/migrations/tenant_patches` (plantilla) o `php artisan tenants:migrate-patches` (tenants activos) | Plantilla tenant / `rest_*` |

## Plantilla tenant (`TENANT_TEMPLATE_DATABASE`)

El esquema base del restaurante **no** vive en migraciones Laravel. Se importa desde `restaurante.sql` en la raíz del repo (o se mantiene una BD plantilla ya existente). Después se aplican los parches de `tenant_patches/`.

## Qué no hacer

- **No** ejecutar `php artisan migrate` sin `--path` (ya no hay migraciones en la raíz).
- **No** duplicar parches aquí y en `tenant_patches/` — la fuente única de parches tenant es `tenant_patches/`.

Ver `docs/MIGRATIONS.md`.
