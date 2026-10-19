# Sprint 3 - Archivos nuevos o modificados

76 archivos preparados para la publicacion autorizada; aplicacion principal pendiente.

## Codigo, vistas, rutas y migraciones

- `app/Actions/Catalog/SaveIndividualUnit.php`
- `app/Actions/Catalog/SaveProduct.php`
- `app/Actions/Catalog/SaveProductLocation.php`
- `app/Actions/Inventory/CancelInventoryDocument.php`
- `app/Actions/Inventory/ConfirmInventoryDocument.php`
- `app/Actions/Inventory/SaveInventoryDocument.php`
- `app/Http/Controllers/Inventory/InitialStockImportController.php`
- `app/Http/Controllers/Inventory/InventoryDocumentController.php`
- `app/Http/Controllers/Inventory/InventoryQueryController.php`
- `app/Http/Requests/Inventory/InventoryDocumentRequest.php`
- `app/Models/IndividualUnit.php`
- `app/Models/IndividualUnitPosition.php`
- `app/Models/InventoryDocument.php`
- `app/Models/InventoryDocumentLine.php`
- `app/Models/InventoryMovement.php`
- `app/Models/StockPosition.php`
- `app/Policies/InventoryDocumentPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/InitialStockCsv.php`
- `app/Services/InventoryDraftValidator.php`
- `app/Services/InventoryReferences.php`
- `app/Services/LocationReferences.php`
- `app/Services/ProductReferences.php`
- `app/Support/InventoryPermissions.php`
- `app/Support/InventoryValidation.php`
- `app/Support/PermissionCatalog.php`
- `database/migrations/2026_10_19_000001_create_inventory_tables.php`
- `database/migrations/2026_10_19_000002_add_inventory_permissions.php`
- `resources/views/inventory/documents/form.blade.php`
- `resources/views/inventory/documents/index.blade.php`
- `resources/views/inventory/documents/line.blade.php`
- `resources/views/inventory/documents/show.blade.php`
- `resources/views/inventory/import-preview.blade.php`
- `resources/views/inventory/import.blade.php`
- `resources/views/inventory/movements.blade.php`
- `resources/views/inventory/query-filters.blade.php`
- `resources/views/inventory/stock.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/roles/form.blade.php`
- `routes/inventory.php`
- `routes/web.php`

## Pruebas

- `tests/Feature/Catalog/AttributePermissionInitializationTest.php`
- `tests/Feature/Catalog/CatalogPermissionInitializationTest.php`
- `tests/Feature/Catalog/IndividualUnitPermissionInitializationTest.php`
- `tests/Feature/Catalog/LocationPermissionInitializationTest.php`
- `tests/Feature/Catalog/ProductPermissionInitializationTest.php`
- `tests/Feature/Inventory/InitialStockImportTest.php`
- `tests/Feature/Inventory/InventoryConcurrencyTest.php`
- `tests/Feature/Inventory/InventoryIntegrityTest.php`
- `tests/Feature/Inventory/InventoryManagementTest.php`
- `tests/Feature/RbacSchemaTest.php`
- `tests/Support/CreatesInventory.php`
- `tests/Support/concurrent-inventory.php`
- `tests/Support/inspect-inventory.php`

## Documentacion y evidencias

- `README.md`
- `docs/data-dictionary-sprint-3.md`
- `docs/evidence/sprint-2-user-conformity.json`
- `docs/evidence/sprint-3-implementation.json`
- `docs/evidence/sprint-3-implementation/frozen-source.json`
- `docs/evidence/sprint-3-implementation/inventory-concurrency.xml`
- `docs/evidence/sprint-3-implementation/inventory-final-targeted.xml`
- `docs/evidence/sprint-3-implementation/legacy-concurrency-recheck.xml`
- `docs/evidence/sprint-3-implementation/main-conservation.json`
- `docs/evidence/sprint-3-implementation/product-concurrency-final.xml`
- `docs/evidence/sprint-3-implementation/regression-junit.xml`
- `docs/evidence/sprint-3-implementation/testing-state.json`
- `docs/global-plan.md`
- `docs/sprint-2-acceptance.md`
- `docs/sprint-2-human-acceptance.md`
- `docs/sprint-2-test-matrix.md`
- `docs/sprint-3-acceptance.md`
- `docs/sprint-3-changed-files.md`
- `docs/sprint-3-implementation.md`
- `docs/sprint-3-publication.md`
- `docs/sprint-3-test-matrix.md`
- `docs/sprint-3.md`
