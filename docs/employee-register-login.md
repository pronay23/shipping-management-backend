# Employee Register & Login — Laravel API Backend Spec

This document defines the database schema, Eloquent model, auth flow, controller wiring, and API
endpoints for employee registration and login in a Laravel API application using **Sanctum** tokens.

## 1. Design Decision

Employees live in their **own `employees` table**, separate from the default `users` auth table.

- Login credentials (`employee_id` + `password`) are columns **in the same `employees` table** —
  no separate credential table.
- `users` remains untouched for admin / other application roles.
- Auth interface: **API + Sanctum bearer token**.
- Login identifier: **`employee_id`** (not email).

```
employees
├── employee_id      ← login username (unique, indexed)
├── password         ← hashed (bcrypt), never plain text
└── ...profile columns
```

## 2. Table: `employees`

One row per employee.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint unsigned | — | auto | primary key |
| `employee_id` | string(50) | no | — | **unique**, indexed — login key |
| `name` | string(255) | no | — | |
| `department` | string(100) | yes | — | indexed |
| `designation` | string(100) | yes | — | indexed |
| `official_mobile` | string(20) | yes | — | |
| `personal_mobile` | string(20) | yes | — | second mobile |
| `email` | string(255) | yes | — | nullable **unique**; not required for login |
| `image` | string(255) | yes | — | image URL / path |
| `nid` | string(50) | yes | — | nullable **unique** |
| `joining_date` | date | yes | — | |
| `bank_account_number` | string(50) | yes | — | |
| `birthday` | date | yes | — | |
| `present_address` | text | yes | — | |
| `personal_address` | text | yes | — | |
| `emergency_person_mobile` | string(20) | yes | — | |
| `relationship_with_emergency_person` | string(100) | yes | — | |
| `password` | string(255) | no | — | bcrypt hashed via model cast |
| `status` | string(20) | no | `active` | `active` / `inactive` |
| `created_at` / `updated_at` | timestamp | yes | — | |

### Column naming

Use snake_case (`official_mobile`, `joining_date`, `bank_account_number`, ...). API JSON keys mirror
column names exactly.

## 3. Eloquent Model: `Employee`

```php
class Employee extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'joining_date' => 'date',
            'birthday' => 'date',
        ];
    }
}
```

- Extends `Authenticatable` so Laravel's auth machinery (`Auth::guard()`, `attempt()`) works.
- `password` cast as `hashed` → automatically bcrypts on save.
- Hide `password` (and `remember_token`) from JSON responses.
- Override `getAuthIdentifierName()` to return `employee_id`.

### Fillable attributes

`employee_id`, `name`, `department`, `designation`, `official_mobile`, `personal_mobile`, `email`,
`image`, `nid`, `joining_date`, `bank_account_number`, `birthday`, `present_address`,
`personal_address`, `emergency_person_mobile`, `relationship_with_emergency_person`, `password`,
`status`.

## 4. Auth Wiring

### `config/auth.php`

Register a dedicated guard + provider so employee auth is isolated from `users`:

```php
'guards' => [
    'employees' => [
        'driver' => 'sanctum',
        'provider' => 'employees',
    ],
    // ...
],

'providers' => [
    'employees' => [
        'driver' => 'eloquent',
        'model' => App\Models\Employee::class,
    ],
    // ...
],
```

### `EmployeeAuthController`

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `register` | `POST /api/employee/register` | public | Create employee + profile |
| `login` | `POST /api/employee/login` | public | `employee_id` + `password` → Sanctum token |
| `logout` | `POST /api/employee/logout` | sanctum | Revoke current token |
| `me` | `GET /api/employee/me` | sanctum | Current employee profile |

## 5. Register Flow

1. **Validate** the request (see §7 rules for exact validation rules per field).
2. `Employee::create($validated)` — password is hashed automatically by the model cast.
3. Return `201` with the created employee (password hidden).

## 6. Login Flow

1. Validate `employee_id` + `password` (required, string).
2. `Auth::guard('employees')->attempt(['employee_id' => ..., 'password' => ...])` — note Laravel
   attempts against the `Employee` model's `getAuthIdentifierName()`.
3. If credentials fail → `401 Unauthorized`.
4. If `status === 'inactive'` → `403` "account is inactive".
5. On success:
   `$token = $employee->createToken('employee-token')->plainTextToken;`
6. Return:
   ```json
   {
     "token": "...",
     "employee": { ... }   // password excluded
   }
   ```

## 7. Validation Rules

| Field | Rules |
|---|---|
| `employee_id` | `required`, `string`, `max:50`, `unique:employees,employee_id` |
| `name` | `required`, `string`, `max:255` |
| `email` | `nullable`, `email`, `unique:employees,email` |
| `nid` | `nullable`, `string`, `unique:employees,nid` |
| `joining_date` / `birthday` | `nullable`, `date` |
| `password` | `required`, `string`, `min:8` |
| `status` | `nullable`, `in:active,inactive` |
| all other fields | `nullable`, `string` |

## 8. CRUD Endpoint (`EmployeeController`)

`Route::apiResource('employees', EmployeeController::class)` following existing controller style
(e.g. `BillOfLadingController`): JSON responses, `$request->validate()` in `store`, `204` on delete.

| Endpoint | Purpose |
|---|---|
| `GET /api/employees` | List all employees |
| `POST /api/employees` | Create employee |
| `GET /api/employees/{employee}` | Show one |
| `PUT/PATCH /api/employees/{employee}` | Update |
| `DELETE /api/employees/{employee}` | Delete |

## 9. Security Notes

- **Never store plain-text passwords** — the `hashed` cast handles bcrypt automatically.
- Keep `password` out of all JSON responses (`$hidden` / `#[Hidden]`).
- Do **not** combine `employees` with `users` — keep auth role separation.
- Block inactive accounts at login time — don't rely only on UI.
- Consider a default password convention + forced change on first login for admin-created accounts.
- Rate-limit `login` (`throttle`) to deter brute-force attacks.

## 10. TODO After This Doc

- [ ] Migration `create_employees_table`
- [ ] Model `Employee` + factory
- [ ] `config/auth.php` guard/provider
- [ ] `EmployeeAuthController` + `EmployeeController`
- [ ] Routes in `routes/api.php`
- [ ] Pest tests for register + login
- [ ] `php artisan migrate`, `vendor/bin/pint --dirty`, `php artisan test`