<?php

use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Units\Index as UnitsIndex;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userWithInventoryPermissions(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

function inventoryWritePermissions(string $resource): array
{
    return ["{$resource}.view", "{$resource}.create", "{$resource}.update", "{$resource}.delete"];
}

test('role seeder syncs the intended permissions idempotently', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    $admin = Role::findByName('Admin', 'web');
    $storekeeper = Role::findByName('Storekeeper', 'web');
    $viewer = Role::findByName('Viewer', 'web');

    expect($admin->permissions)->toHaveCount(51)
        ->and($storekeeper->permissions)->toHaveCount(20)
        ->and($viewer->permissions)->toHaveCount(9)
        ->and($storekeeper->hasPermissionTo('products.delete'))->toBeFalse()
        ->and($viewer->hasPermissionTo('products.update'))->toBeFalse()
        ->and(Role::findByName('Cashier', 'web')->hasPermissionTo('pos.use'))->toBeTrue()
        ->and(Role::findByName('Cashier', 'web')->hasPermissionTo('invoices.void'))->toBeFalse();
});

test('unit component renders searchable data and creates updates and protects referenced units', function () {
    $user = userWithInventoryPermissions(inventoryWritePermissions('units'));
    $unit = Unit::factory()->create(['name' => 'Each']);

    Livewire::actingAs($user)->test(UnitsIndex::class)
        ->assertSee('Each')
        ->call('sort', 'abbreviation')
        ->call('openForm')
        ->set('name', 'Case')
        ->set('abbreviation', 'cs')
        ->call('store')
        ->assertHasNoErrors();

    $created = Unit::query()->where('name', 'Case')->firstOrFail();

    Livewire::actingAs($user)->test(UnitsIndex::class)
        ->call('openForm', $created->id)
        ->set('abbreviation', 'case')
        ->call('update')
        ->assertHasNoErrors();

    expect($created->fresh()->abbreviation)->toBe('case');

    Livewire::actingAs($user)->test(UnitsIndex::class)
        ->call('openForm')
        ->set('name', 'Each')
        ->set('abbreviation', 'ea')
        ->call('store')
        ->assertHasErrors('name');

    Product::factory()->create(['base_unit_id' => $unit->id]);

    Livewire::actingAs($user)->test(UnitsIndex::class)
        ->call('confirmDelete', $unit->id)
        ->call('delete');

    $this->assertDatabaseHas('units', ['id' => $unit->id]);
});

test('category component creates and updates unique categories and blocks deleting used categories', function () {
    $user = userWithInventoryPermissions(inventoryWritePermissions('categories'));
    $category = Category::factory()->create(['name' => 'Hardware']);

    Livewire::actingAs($user)->test(CategoriesIndex::class)
        ->assertSee('Hardware')
        ->call('openForm')
        ->set('name', 'Tools')
        ->call('store')
        ->assertHasNoErrors();

    $created = Category::query()->where('name', 'Tools')->firstOrFail();
    Livewire::actingAs($user)->test(CategoriesIndex::class)
        ->call('openForm', $created->id)
        ->set('name', 'Tools & Hardware')
        ->call('update')
        ->assertHasNoErrors();

    Livewire::actingAs($user)->test(CategoriesIndex::class)
        ->call('openForm')
        ->set('name', 'Hardware')
        ->call('store')
        ->assertHasErrors('name');

    Product::factory()->create(['category_id' => $category->id]);
    Livewire::actingAs($user)->test(CategoriesIndex::class)
        ->call('confirmDelete', $category->id)
        ->call('delete');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('location component creates updates filters and blocks deletion when stock history exists', function () {
    $user = userWithInventoryPermissions(inventoryWritePermissions('locations'));
    $location = Location::factory()->create(['name' => 'Outlet']);

    Livewire::actingAs($user)->test(LocationsIndex::class)
        ->assertSee('Outlet')
        ->call('openForm')
        ->set('name', 'North Hub')
        ->set('type', 'warehouse')
        ->set('isActive', true)
        ->call('store')
        ->assertHasNoErrors();

    $created = Location::query()->where('name', 'North Hub')->firstOrFail();
    Livewire::actingAs($user)->test(LocationsIndex::class)
        ->call('openForm', $created->id)
        ->set('type', 'branch')
        ->set('isActive', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($created->fresh()->type)->toBe('branch')
        ->and($created->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($user)->test(LocationsIndex::class)
        ->call('openForm')
        ->set('name', 'Outlet')
        ->set('type', 'store')
        ->set('isActive', true)
        ->call('store')
        ->assertHasErrors('name');

    Livewire::actingAs($user)->test(LocationsIndex::class)
        ->call('openForm')
        ->set('name', 'South Depot')
        ->set('type', 'unlisted')
        ->set('isActive', true)
        ->call('store')
        ->assertHasErrors('type');

    Livewire::actingAs($user)->test(LocationsIndex::class)
        ->set('search', 'North')
        ->set('statusFilter', 'inactive')
        ->assertSee('North Hub')
        ->assertDontSee('Outlet');

    $baseUnit = Unit::factory()->create();
    $product = Product::factory()->create(['base_unit_id' => $baseUnit->id]);
    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '1.000',
    ]);

    Livewire::actingAs($user)->test(LocationsIndex::class)
        ->call('confirmDelete', $location->id)
        ->call('delete');

    $this->assertDatabaseHas('locations', ['id' => $location->id]);
});

test('product component normalizes unique skus saves packaging and protects stock identity', function () {
    $user = userWithInventoryPermissions(inventoryWritePermissions('products'));
    $category = Category::factory()->create();
    $baseUnit = Unit::factory()->create(['name' => 'Piece']);
    $carton = Unit::factory()->create(['name' => 'Carton']);

    Livewire::actingAs($user)->test(ProductsIndex::class)
        ->call('openForm')
        ->set('sku', '  kit-001  ')
        ->set('barcode', '1234567890123')
        ->set('name', 'Starter Kit')
        ->set('categoryId', (string) $category->id)
        ->set('baseUnitId', (string) $baseUnit->id)
        ->set('reorderLevel', '2.500')
        ->set('trackBatches', false)
        ->set('isActive', true)
        ->set('description', 'A sample product.')
        ->set('packagingUnits', [['unit_id' => (string) $carton->id, 'factor' => '24.000']])
        ->call('store')
        ->assertHasNoErrors();

    $product = Product::query()->where('sku', 'KIT-001')->firstOrFail();
    expect($product->productUnits)->toHaveCount(1)
        ->and($product->productUnits->first()->factor)->toBe('24.000')
        ->and($product->barcode)->toBe('1234567890123')
        ->and($product->tax_type)->toBe('standard');

    Livewire::actingAs($user)->test(ProductsIndex::class)
        ->call('openForm', $product->id)
        ->set('name', 'Starter Kit Updated')
        ->set('reorderLevel', '3.000')
        ->call('update')
        ->assertHasNoErrors();

    expect($product->fresh()->name)->toBe('Starter Kit Updated');

    Product::factory()->create(['sku' => 'OTHER-001', 'base_unit_id' => $baseUnit->id]);
    Livewire::actingAs($user)->test(ProductsIndex::class)
        ->call('openForm', $product->id)
        ->set('sku', 'other-001')
        ->call('update')
        ->assertHasErrors('sku');

    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => Location::factory()->create()->id,
        'quantity' => '1.000',
    ]);

    $secondUnit = Unit::factory()->create();
    Livewire::actingAs($user)->test(ProductsIndex::class)
        ->set('lowStockOnly', true)
        ->assertSee('Low stock')
        ->assertSee('1.000');

    Livewire::actingAs($user)->test(ProductsIndex::class)
        ->call('openForm', $product->id)
        ->set('baseUnitId', (string) $secondUnit->id)
        ->set('trackBatches', true)
        ->call('update')
        ->assertHasErrors(['baseUnitId', 'trackBatches']);

    Livewire::actingAs($user)->test(ProductsIndex::class)
        ->call('confirmDelete', $product->id)
        ->call('delete');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});

dataset('viewer denied inventory writes', [
    'units' => ['units', Unit::class, UnitsIndex::class],
    'categories' => ['categories', Category::class, CategoriesIndex::class],
    'locations' => ['locations', Location::class, LocationsIndex::class],
    'products' => ['products', Product::class, ProductsIndex::class],
]);

test('viewers are forbidden from creating updating or deleting inventory records', function (string $resource, string $modelClass, string $componentClass) {
    $record = $modelClass::factory()->create();
    $viewer = userWithInventoryPermissions(["{$resource}.view"]);

    Livewire::actingAs($viewer)->test($componentClass)->assertOk();

    Livewire::actingAs($viewer)->test($componentClass)->call('openForm')->assertStatus(403);
    Livewire::actingAs($viewer)->test($componentClass)->call('openForm', $record->id)->assertStatus(403);
    Livewire::actingAs($viewer)->test($componentClass)->call('confirmDelete', $record->id)->assertStatus(403);
})->with('viewer denied inventory writes');

// ─── Search persistence (Bug 2 regression) ───────────────────────────────────

test('list component initialises with an empty search when no query string is present', function (): void {
    $user = userWithInventoryPermissions(['units.view']);

    Livewire::actingAs($user)->test(UnitsIndex::class)
        ->assertSet('search', '');
});

test('clearSearch resets the search term and pagination to page one', function (): void {
    $user = userWithInventoryPermissions(['units.view']);
    Unit::factory()->count(5)->create();

    Livewire::actingAs($user)->test(UnitsIndex::class)
        ->set('search', 'something')
        ->assertSet('search', 'something')
        ->call('clearSearch')
        ->assertSet('search', '');
});
