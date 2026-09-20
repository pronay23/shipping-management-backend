<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreMoneyReceiptRequest;
use App\Http\Resources\MoneyReceiptResource;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\MoneyReceipt;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoneyReceiptController extends Controller
{
    public function __construct(public JournalService $journal) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(
            MoneyReceiptResource::collection(MoneyReceipt::with('items', 'invoiceLinks')->latest()->get())
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMoneyReceiptRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $receipt = MoneyReceipt::create($request->only([
                'title',
                'money_receipt_number',
                'money_receipt_date',
                'bl_number',
                'customer_name',
                'vessel',
                'voyage',
                'registration_no',
                'containers',
                'exchange_rate',
                'amount_in_words',
                'total_usd',
                'total_bdt',
                'payment_term',
            ]));

            if ($request->has('items')) {
                foreach ($request->input('items', []) as $item) {
                    $receipt->items()->create($item);
                }
            }

            if ($request->has('invoices')) {
                foreach ($request->input('invoices', []) as $link) {
                    $receipt->invoiceLinks()->create($link);
                }

                $this->syncInvoiceStatuses($request->input('invoices', []));
            }

            $this->journal->createMoneyReceiptEntry($receipt);

            return response()->json(
                new MoneyReceiptResource($receipt->load('items', 'invoiceLinks')),
                201
            );
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(MoneyReceipt $moneyReceipt)
    {
        return response()->json(
            new MoneyReceiptResource($moneyReceipt->load('items', 'invoiceLinks'))
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreMoneyReceiptRequest $request, MoneyReceipt $moneyReceipt)
    {
        return DB::transaction(function () use ($request, $moneyReceipt) {
            $moneyReceipt->update($request->only([
                'title',
                'money_receipt_number',
                'money_receipt_date',
                'bl_number',
                'customer_name',
                'vessel',
                'voyage',
                'registration_no',
                'containers',
                'exchange_rate',
                'amount_in_words',
                'total_usd',
                'total_bdt',
                'payment_term',
            ]));

            if ($request->has('items')) {
                $moneyReceipt->items()->delete();

                foreach ($request->input('items', []) as $item) {
                    $moneyReceipt->items()->create($item);
                }
            }

            if ($request->has('invoices')) {
                $moneyReceipt->invoiceLinks()->delete();

                foreach ($request->input('invoices', []) as $link) {
                    $moneyReceipt->invoiceLinks()->create($link);
                }

                $this->syncInvoiceStatuses($request->input('invoices', []));
            }

            $this->journal->createMoneyReceiptEntry($moneyReceipt);

            return response()->json(
                new MoneyReceiptResource($moneyReceipt->load('items', 'invoiceLinks'))
            );
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MoneyReceipt $moneyReceipt)
    {
        $this->assertEntryVoidable($moneyReceipt);

        $invoiceIds = $moneyReceipt->invoiceLinks()->pluck('invoice_id')->filter()->unique();

        $moneyReceipt->delete();

        JournalEntry::where('source_type', 'money_receipt')
            ->where('source_id', $moneyReceipt->id)
            ->delete();

        $this->syncInvoices($invoiceIds);

        return response()->json(null, 204);
    }

    /**
     * Block deleting a money receipt whose journal entry is already approved.
     */
    private function assertEntryVoidable(MoneyReceipt $moneyReceipt): void
    {
        $approved = JournalEntry::where('source_type', 'money_receipt')
            ->where('source_id', $moneyReceipt->id)
            ->where('status', JournalEntry::STATUS_APPROVED)
            ->exists();

        if ($approved) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Cannot delete money receipt; its journal entry is approved. Void the entry first.',
            ]);
        }
    }

    /**
     * Recompute the payment status, received, and due for each linked invoice
     * based on the total amount paid across all of its money receipts.
     *
     * @param  array<int, array{invoice_id?: int|null, paid_amount?: float|string|null}>  $links
     */
    private function syncInvoiceStatuses(array $links): void
    {
        $invoiceIds = collect($links)
            ->pluck('invoice_id')
            ->filter()
            ->unique();

        $this->syncInvoices($invoiceIds);
    }

    /**
     * Recompute the payment status, received, and due columns of an invoice from
     * the total amount paid across all of its money receipts.
     *
     * @param  \Illuminate\Support\Collection<int, int|string>  $invoiceIds
     */
    private function syncInvoices($invoiceIds): void
    {
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::withSum(
                ['invoiceLinks as total_paid' => function ($query) {
                    $query->whereNotNull('invoice_id');
                }],
                'paid_amount'
            )->find($invoiceId);

            if ($invoice === null) {
                continue;
            }

            $totalPaid = (float) $invoice->total_paid;
            $totalBdt = (float) $invoice->total_bdt;

            $invoice->received = $totalPaid;
            $invoice->due = max(0, $totalBdt - $totalPaid);
            $invoice->status = $totalPaid >= $totalBdt
                ? InvoiceStatus::Paid
                : ($totalPaid > 0 ? InvoiceStatus::Partial : InvoiceStatus::Unpaid);

            $invoice->save();
        }
    }
}