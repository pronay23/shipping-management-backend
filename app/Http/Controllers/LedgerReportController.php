<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Services\LedgerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerReportController extends Controller
{
    public function __construct(public LedgerReportService $reports) {}

    /**
     * Trial balance over approved entries.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $validated = $this->validateRange($request);

        return response()->json(
            $this->reports->trialBalance(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 50),
            )
        );
    }

    /**
     * General ledger (running balance) for a single account.
     */
    public function accountLedger(Request $request, ChartOfAccount $account): JsonResponse
    {
        $validated = $this->validateRange($request);

        return response()->json(
            $this->reports->accountLedger(
                $account,
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 50),
            )
        );
    }

    /**
     * Accounts-receivable aging per invoice.
     */
    public function arAging(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        return response()->json($this->reports->arAging($validated['as_of'] ?? null));
    }

    /**
     * Accounts-payable aging per vendor.
     */
    public function apAging(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        return response()->json($this->reports->apAging($validated['as_of'] ?? null));
    }

    /**
     * Balance sheet as of a given date.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        return response()->json($this->reports->balanceSheet($validated['as_of'] ?? null));
    }

    /**
     * Profit & loss (income statement) over a date range.
     */
    public function profitAndLoss(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json(
            $this->reports->profitAndLoss(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
            )
        );
    }

    /**
     * Shared query-parameter rules for the paginated date-ranged reports.
     *
     * @return array<string, mixed>
     */
    private function validateRange(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
    }
}
