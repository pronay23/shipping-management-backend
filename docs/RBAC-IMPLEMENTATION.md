# Role-Based Access Control (RBAC) Implementation

## Overview

Dynamic role-based access control using `spatie/laravel-permission` for the Shipping Management System. Roles are fully manageable via API — Super Admin and Admin can create, edit, delete custom roles and assign granular module-level permissions.

---

## Packages

| Package | Version | Purpose |
|---------|---------|---------|
| `spatie/laravel-permission` | v6+ | Roles & permissions management |

Install:
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

---

## Database Changes

### Spatie Tables (published migration)
- `roles` — stores roles
- `permissions` — stores permissions
- `role_has_permissions` — pivot table linking roles to permissions

### Employees Table
Add `role` column for quick lookups:
```php
$table->string('role')->default('employee');
```

---

## Default Roles (Seeded)

| Role | Slug | Description | Protected from Delete? |
|------|------|-------------|:----------------------:|
| Super Administrator | `super-admin` | Full system access | Yes |
| Administrator | `admin` | Administrative access, no employee management | Yes |
| Manager | `manager` | Operational access, limited finance | Yes |
| *(custom)* | *(dynamic)* | Created dynamically by admin/super-admin | No |

---

## Permission Structure (72+ total)

Permissions are grouped by module with 4 standard actions:

| Module | Slug Prefix | Permissions |
|--------|-------------|-------------|
| Employee | `employee` | `view`, `create`, `update`, `delete` |
| Voyage | `voyage` | `view`, `create`, `update`, `delete` |
| Bill of Lading | `bill-of-lading` | `view`, `create`, `update`, `delete` |
| Container Manifest | `container-manifest` | `view` |
| Invoice | `invoice` | `view`, `create`, `update`, `delete` |
| Money Receipt | `money-receipt` | `view`, `create`, `update`, `delete` |
| AP Invoice | `ap-invoice` | `view`, `create`, `update`, `delete` |
| AP Payment | `ap-payment` | `view`, `create`, `update`, `delete` |
| Journal Entry | `journal-entry` | `view`, `create`, `approve`, `void` |
| Chart of Account | `chart-of-account` | `view`, `create`, `update`, `delete` |
| Account Type | `account-type` | `view`, `create`, `update`, `delete` |
| Vendor | `vendor` | `view`, `create`, `update`, `delete` |
| Vendor Category | `vendor-category` | `view`, `create`, `update`, `delete` |
| Country | `country` | `view`, `create`, `update`, `delete` |
| Currency | `currency` | `view`, `create`, `update`, `delete` |
| UOM | `uom` | `view`, `create`, `update`, `delete` |
| Item | `item` | `view`, `create`, `update`, `delete` |
| Reports | `reports` | `view` |

---

## Role-Permission Matrix (Default Roles)

| Module | Super Admin | Admin | Manager |
|--------|:-----------:|:-----:|:-------:|
| Employee | C R U D | R | R |
| Voyage | C R U D | C R U D | C R U |
| Bill of Lading | C R U D | C R U D | C R U |
| Container Manifest | R | R | R |
| Invoice | C R U D | C R U D | C R U |
| Money Receipt | C R U D | C R U D | C R U |
| AP Invoice | C R U D | C R U D | C R U |
| AP Payment | C R U D | C R U D | C R U |
| Journal Entry | C R + Approve + Void | C R + Approve + Void | R |
| Chart of Account | C R U D | C R U D | R |
| Account Type | C R U D | C R U D | R |
| Vendor | C R U D | C R U D | C R U |
| Vendor Category | C R U D | C R U D | R |
| Country | C R U D | C R U D | R |
| Currency | C R U D | C R U D | R |
| UOM | C R U D | C R U D | R |
| Item | C R U D | C R U D | C R U |
| Reports | Full | Full | Read-only |

**Legend**: C=Create, R=Read, U=Update, D=Delete

---

## API Endpoints

### Authentication (Existing)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/employee/register` | Public | Register new employee |
| POST | `/api/employee/login` | Public | Login, returns token + role + permissions |
| POST | `/api/employee/logout` | `auth:sanctum` | Logout |
| GET | `/api/employee/me` | `auth:sanctum` | Get current user with role & permissions |

### Role Management (New)

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| GET | `/api/roles` | super-admin, admin | List all roles with permissions |
| POST | `/api/roles` | super-admin, admin | Create new role with permissions |
| GET | `/api/roles/{id}` | super-admin, admin | Get role details |
| PUT | `/api/roles/{id}` | super-admin, admin | Update role + permissions |
| DELETE | `/api/roles/{id}` | super-admin, admin | Delete role (except system roles) |

### Permission Management (New)

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| GET | `/api/permissions` | super-admin, admin | List all permissions grouped by module |

### Employee Role Assignment (New)

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| PUT | `/api/employees/{id}/role` | super-admin, admin | Assign role to employee |

### Existing Module Routes (Protected)

All existing `apiResource` routes will be protected with role/permission middleware.

---

## Route Protection

### Middleware Registration (bootstrap/app.php)

```php
->withMiddleware(function (Middleware $middleware): void
{
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);
})
```

### Route Groups (routes/api.php)

```php
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/employee/register', [EmployeeAuthController::class, 'register']);
Route::post('/employee/login', [EmployeeAuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Employee auth
    Route::post('/employee/logout', [EmployeeAuthController::class, 'logout']);
    Route::get('/employee/me', [EmployeeAuthController::class, 'me']);

    // Role & Permission management (super-admin + admin)
    Route::middleware('role:super-admin|admin')->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::put('/employees/{id}/role', [EmployeeController::class, 'assignRole']);
    });

    // Employee management (super-admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::apiResource('employees', EmployeeController::class);
    });

    // Operational modules (super-admin + admin + manager, permission-based)
    Route::middleware('role:super-admin|admin|manager')->group(function () {

        // Bill of Lading
        Route::apiResource('bill-of-ladings', BillOfLadingController::class);
        Route::get('/container-manifests', [ContainerManifestController::class, 'index']);

        // Voyage
        Route::apiResource('voyages', VoyageController::class);

        // Accounts Receivable
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('money-receipts', MoneyReceiptController::class);

        // Accounts Payable
        Route::get('/ap-invoices/next-number', [ApInvoiceController::class, 'nextNumber']);
        Route::apiResource('ap-invoices', ApInvoiceController::class);
        Route::apiResource('ap-payments', ApPaymentController::class);

        // Journal Entries
        Route::get('/journal-entries', [JournalEntryController::class, 'index']);
        Route::get('/journal-entries/{id}', [JournalEntryController::class, 'show']);
        Route::post('/journal-entries/{id}/approve', [JournalEntryController::class, 'approve']);
        Route::post('/journal-entries/{id}/void', [JournalEntryController::class, 'void']);

        // Reports
        Route::get('/accounts/{id}/ledger', [LedgerReportController::class, 'accountLedger']);
        Route::get('/reports/trial-balance', [LedgerReportController::class, 'trialBalance']);
        Route::get('/reports/ar-aging', [LedgerReportController::class, 'arAging']);
        Route::get('/reports/ap-aging', [LedgerReportController::class, 'apAging']);
        Route::get('/reports/balance-sheet', [LedgerReportController::class, 'balanceSheet']);
        Route::get('/reports/profit-and-loss', [LedgerReportController::class, 'profitAndLoss']);

        // Chart of Accounts & Account Types
        Route::apiResource('chart-of-accounts', ChartOfAccountController::class);
        Route::apiResource('account-types', AccountTypeController::class);

        // Master Data
        Route::apiResource('vendor-categories', VendorCategoryController::class);
        Route::get('/vendors/lookup', [VendorController::class, 'lookup']);
        Route::apiResource('vendors', VendorController::class);
        Route::apiResource('countries', CountryController::class);
        Route::apiResource('currencies', CurrencyController::class);
        Route::apiResource('uoms', UomController::class);
        Route::get('/items/lookup', [ItemController::class, 'lookup']);
        Route::apiResource('items', ItemController::class);

        // XML Manifest
        Route::get('/voyages/{id}/xml/igm', [ManifestXmlController::class, 'generateIgm']);
        Route::get('/voyages/{id}/xml/egm', [ManifestXmlController::class, 'generateEgm']);
    });
});
```

---

## Login Response

### POST `/api/employee/login`

**Request:**
```json
{
  "employee_id": "EMP001",
  "password": "secret"
}
```

**Response:**
```json
{
  "employee": {
    "id": 1,
    "employee_id": "EMP001",
    "name": "John Doe",
    "department": "Operations",
    "designation": "Manager",
    "email": "john@example.com",
    "status": "active"
  },
  "token": "1|abc123...",
  "role": {
    "name": "super-admin",
    "display_name": "Super Administrator",
    "description": "Full system access"
  },
  "permissions": [
    "employee.view",
    "employee.create",
    "employee.update",
    "employee.delete",
    "voyage.view",
    "voyage.create",
    "voyage.update",
    "voyage.delete",
    "invoice.view",
    "invoice.create",
    "invoice.update",
    "invoice.delete"
  ]
}
```

---

## Employee Model Changes

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $primaryKey = 'employee_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'name',
        'department',
        'designation',
        'official_mobile',
        'personal_mobile',
        'email',
        'image',
        'nid',
        'joining_date',
        'bank_account_number',
        'birthday',
        'present_address',
        'permanent_address',
        'emergency_contact_name',
        'emergency_contact_number',
        'password',
        'status',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'birthday' => 'date',
    ];
}
```

---

## New Controllers

### RoleController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'guard_name' => 'api',
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $systemRoles = ['super-admin', 'admin', 'manager'];
        if (in_array($role->name, $systemRoles)) {
            $role->fill($request->only(['display_name', 'description']));
        } else {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
                'display_name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ]);
            $role->fill($validated);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        $role->save();

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions'),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $systemRoles = ['super-admin', 'admin', 'manager'];
        if (in_array($role->name, $systemRoles)) {
            return response()->json([
                'message' => 'System roles cannot be deleted',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}
```

### PermissionController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return response()->json($permissions);
    }
}
```

### EmployeeController Updates

```php
// Add assignRole method
public function assignRole(Request $request, string $id): JsonResponse
{
    $validated = $request->validate([
        'role' => 'required|string|exists:roles,name',
    ]);

    $employee = Employee::findOrFail($id);
    $employee->syncRoles([$validated['role']]);
    $employee->update(['role' => $validated['role']]);

    return response()->json([
        'message' => 'Role assigned successfully',
        'employee' => $employee->load('roles'),
    ]);
}
```

---

## Seeder (RoleSeeder)

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define modules and their permissions
        $modules = [
            'employee'         => ['view', 'create', 'update', 'delete'],
            'voyage'           => ['view', 'create', 'update', 'delete'],
            'bill-of-lading'   => ['view', 'create', 'update', 'delete'],
            'container-manifest' => ['view'],
            'invoice'          => ['view', 'create', 'update', 'delete'],
            'money-receipt'    => ['view', 'create', 'update', 'delete'],
            'ap-invoice'       => ['view', 'create', 'update', 'delete'],
            'ap-payment'       => ['view', 'create', 'update', 'delete'],
            'journal-entry'    => ['view', 'create', 'approve', 'void'],
            'chart-of-account' => ['view', 'create', 'update', 'delete'],
            'account-type'     => ['view', 'create', 'update', 'delete'],
            'vendor'           => ['view', 'create', 'update', 'delete'],
            'vendor-category'  => ['view', 'create', 'update', 'delete'],
            'country'          => ['view', 'create', 'update', 'delete'],
            'currency'         => ['view', 'create', 'update', 'delete'],
            'uom'              => ['view', 'create', 'update', 'delete'],
            'item'             => ['view', 'create', 'update', 'delete'],
            'reports'          => ['view'],
        ];

        // Create all permissions
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'api',
                ]);
            }
        }

        // Super Admin - all permissions
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'api'],
            ['display_name' => 'Super Administrator', 'description' => 'Full system access']
        );
        $superAdmin->syncPermissions(Permission::all());

        // Admin - all except employee delete
        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api'],
            ['display_name' => 'Administrator', 'description' => 'Administrative access, no employee management']
        );
        $adminPermissions = Permission::where('name', '!=', 'employee.create')
            ->where('name', '!=', 'employee.update')
            ->where('name', '!=', 'employee.delete')
            ->get();
        $admin->syncPermissions($adminPermissions);

        // Manager - operational, no delete, limited finance
        $manager = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'api'],
            ['display_name' => 'Manager', 'description' => 'Operational access, limited finance']
        );
        $managerPermissions = Permission::whereIn('name', [
            'employee.view',
            'voyage.view', 'voyage.create', 'voyage.update',
            'bill-of-lading.view', 'bill-of-lading.create', 'bill-of-lading.update',
            'container-manifest.view',
            'invoice.view', 'invoice.create', 'invoice.update',
            'money-receipt.view', 'money-receipt.create', 'money-receipt.update',
            'ap-invoice.view', 'ap-invoice.create', 'ap-invoice.update',
            'ap-payment.view', 'ap-payment.create', 'ap-payment.update',
            'journal-entry.view',
            'chart-of-account.view',
            'account-type.view',
            'vendor.view', 'vendor.create', 'vendor.update',
            'vendor-category.view',
            'country.view',
            'currency.view',
            'uom.view',
            'item.view', 'item.create', 'item.update',
            'reports.view',
        ])->get();
        $manager->syncPermissions($managerPermissions);
    }
}
```

---

## Migration (add_role_to_employees)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('role')->default('employee')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

---

## Files Summary

### New Files
| File | Purpose |
|------|---------|
| `database/migrations/xxxx_add_role_to_employees_table.php` | Add `role` column to employees |
| `database/seeders/RoleSeeder.php` | Seed 3 default roles + 72 permissions |
| `app/Http/Controllers/Api/RoleController.php` | CRUD for roles |
| `app/Http/Controllers/Api/PermissionController.php` | List permissions grouped by module |

### Modified Files
| File | Changes |
|------|---------|
| `composer.json` | Add `spatie/laravel-permission` |
| `app/Models/Employee.php` | Add `HasRoles` trait, `role` fillable |
| `bootstrap/app.php` | Register Spatie middleware aliases |
| `routes/api.php` | Restructure with role/permission middleware |
| `app/Http/Controllers/Api/EmployeeAuthController.php` | Return role + permissions in login/me |
| `app/Http/Controllers/Api/EmployeeController.php` | Add `assignRole` method |
| `database/seeders/DatabaseSeeder.php` | Call RoleSeeder |

---

## Verification

```bash
# Run migrations
php artisan migrate

# Seed roles and permissions
php artisan db:seed

# Run tests
php artisan test --compact

# Format code
vendor/bin/pint --dirty --format agent
```
