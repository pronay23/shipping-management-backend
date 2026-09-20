<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('employee/register', [App\Http\Controllers\EmployeeAuthController::class, 'register']);
Route::post('employee/login', [App\Http\Controllers\EmployeeAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('employee/logout', [App\Http\Controllers\EmployeeAuthController::class, 'logout']);
    Route::get('employee/me', [App\Http\Controllers\EmployeeAuthController::class, 'me']);

    Route::middleware('role:super-admin|admin')->group(function () {
        Route::apiResource('roles', App\Http\Controllers\RoleController::class);
        Route::get('permissions', [App\Http\Controllers\PermissionController::class, 'index']);
        Route::put('employees/{id}/role', [App\Http\Controllers\EmployeeController::class, 'assignRole']);
    });

    Route::middleware('role:super-admin')->group(function () {
        Route::apiResource('employees', App\Http\Controllers\EmployeeController::class)->except(['index', 'show']);
    });

    Route::middleware('role:super-admin|admin|manager')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        
        Route::get('employees', [App\Http\Controllers\EmployeeController::class, 'index']);
        Route::get('employees/{employee}', [App\Http\Controllers\EmployeeController::class, 'show']);

        Route::apiResource('bill-of-ladings', App\Http\Controllers\BillOfLadingController::class);
        Route::get('container-manifests', [App\Http\Controllers\ContainerManifestController::class, 'index']);

        Route::apiResource('voyages', App\Http\Controllers\VoyageController::class);
        Route::get('voyages/{voyage}/xml/igm', [App\Http\Controllers\Api\ManifestXmlController::class, 'generateIgm']);
        Route::get('voyages/{voyage}/xml/egm', [App\Http\Controllers\Api\ManifestXmlController::class, 'generateEgm']);

        Route::apiResource('invoices', App\Http\Controllers\InvoiceController::class);
        Route::apiResource('money-receipts', App\Http\Controllers\MoneyReceiptController::class);

        Route::get('ap-invoices/next-number', [App\Http\Controllers\ApInvoiceController::class, 'nextNumber']);
        Route::apiResource('ap-invoices', App\Http\Controllers\ApInvoiceController::class);
        Route::apiResource('ap-payments', App\Http\Controllers\ApPaymentController::class);

        Route::get('journal-entries', [App\Http\Controllers\JournalEntryController::class, 'index']);
        Route::get('journal-entries/{journal_entry}', [App\Http\Controllers\JournalEntryController::class, 'show']);
        Route::post('journal-entries/{journal_entry}/approve', [App\Http\Controllers\JournalEntryController::class, 'approve']);
        Route::post('journal-entries/{journal_entry}/void', [App\Http\Controllers\JournalEntryController::class, 'void']);

        Route::get('accounts/{account}/ledger', [App\Http\Controllers\LedgerReportController::class, 'accountLedger']);
        Route::get('reports/trial-balance', [App\Http\Controllers\LedgerReportController::class, 'trialBalance']);
        Route::get('reports/ar-aging', [App\Http\Controllers\LedgerReportController::class, 'arAging']);
        Route::get('reports/ap-aging', [App\Http\Controllers\LedgerReportController::class, 'apAging']);
        Route::get('reports/balance-sheet', [App\Http\Controllers\LedgerReportController::class, 'balanceSheet']);
        Route::get('reports/profit-and-loss', [App\Http\Controllers\LedgerReportController::class, 'profitAndLoss']);

        Route::apiResource('chart-of-accounts', App\Http\Controllers\ChartOfAccountController::class);
        Route::apiResource('account-types', App\Http\Controllers\AccountTypeController::class);

        Route::apiResource('vendor-categories', App\Http\Controllers\VendorCategoryController::class);
        Route::get('vendors/lookup', [App\Http\Controllers\VendorController::class, 'lookup']);
        Route::apiResource('vendors', App\Http\Controllers\VendorController::class);

        Route::apiResource('countries', App\Http\Controllers\CountryController::class);
        Route::apiResource('currencies', App\Http\Controllers\CurrencyController::class);

        Route::apiResource('uoms', App\Http\Controllers\UomController::class);
        Route::get('items/lookup', [App\Http\Controllers\ItemController::class, 'lookup']);
        Route::apiResource('items', App\Http\Controllers\ItemController::class);
    });
});
