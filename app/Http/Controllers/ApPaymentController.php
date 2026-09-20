<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApPaymentRequest;
use App\Http\Resources\ApPaymentResource;
use App\Models\ApInvoice;
use App\Models\ApPayment;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApPaymentController extends Controller
{
    public function __construct(public JournalService $journal) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(
            ApPaymentResource::collection(ApPayment::with('vendor', 'invoiceLinks.apInvoice')->latest()->get())
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreApPaymentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $invoices = $validated['invoices'] ?? [];
            unset($validated['invoices']);

            $apPayment = ApPayment::create($validated);

            foreach ($invoices as $link) {
                $apPayment->invoiceLinks()->create($link);
            }

            $this->syncInvoiceStatuses($invoices);

            $this->journal->createApPaymentEntry($apPayment);

            return response()->json(
                new ApPaymentResource($apPayment->load('vendor', 'invoiceLinks.apInvoice')),
                201
            );
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(ApPayment $apPayment)
    {
        return response()->json(
            new ApPaymentResource($apPayment->load('vendor', 'invoiceLinks.apInvoice'))
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreApPaymentRequest $request, ApPayment $apPayment)
    {
        return DB::transaction(function () use ($request, $apPayment) {
            $validated = $request->validated();

            $invoices = $validated['invoices'] ?? null;
            unset($validated['invoices']);

            $apPayment->update($validated);

            if ($invoices !== null) {
                $apPayment->invoiceLinks()->delete();

                foreach ($invoices as $link) {
                    $apPayment->invoiceLinks()->create($link);
                }

                $this->syncInvoiceStatuses($invoices);
            }

            $this->journal->createApPaymentEntry($apPayment);

            return response()->json(
                new ApPaymentResource($apPayment->fresh('vendor', 'invoiceLinks.apInvoice'))
            );
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ApPayment $apPayment)
    {
        $this->assertEntryVoidable($apPayment);

        $invoiceIds = $apPayment->invoiceLinks()->pluck('ap_invoice_id')->filter()->unique();

        $apPayment->delete();

        JournalEntry::where('source_type', 'ap_payment')
            ->where('source_id', $apPayment->id)
            ->delete();

        $this->syncInvoices($invoiceIds);

        return response()->json(null, 204);
    }

    /**
     * Block deleting a payment whose journal entry is already approved.
     */
    private function assertEntryVoidable(ApPayment $apPayment): void
    {
        $approved = JournalEntry::where('source_type', 'ap_payment')
            ->where('source_id', $apPayment->id)
            ->where('status', JournalEntry::STATUS_APPROVED)
            ->exists();

        if ($approved) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Cannot delete AP payment; its journal entry is approved. Void the entry first.',
            ]);
        }
    }

    /**
     * Recompute the payment status, paid_amount, and due_amount for each linked
     * AP invoice based on the total amount paid across all of its payments.
     *
     * @param  array<int, array{ap_invoice_id?: int|null, paid_amount?: float|string|null}>  $links
     */
    private function syncInvoiceStatuses(array $links): void
    {
        $invoiceIds = collect($links)
            ->pluck('ap_invoice_id')
            ->filter()
            ->unique();

        $this->syncInvoices($invoiceIds);
    }

    /**
     * Recompute the payment status, paid_amount, and due_amount of an AP invoice
     * from the total amount paid across all of its payments.
     *
     * @param  \Illuminate\Support\Collection<int, int|string>  $invoiceIds
     */
    private function syncInvoices($invoiceIds): void
    {
        foreach ($invoiceIds as $invoiceId) {
            $invoice = ApInvoice::withSum(
                ['paymentLinks as total_paid' => function ($query) {
                    $query->whereNotNull('ap_invoice_id');
                }],
                'paid_amount'
            )->find($invoiceId);

            if ($invoice === null) {
                continue;
            }

            $totalPaid = (float) $invoice->total_paid;
            $netAmount = (float) $invoice->net_amount;

            $invoice->paid_amount = $totalPaid;
            $invoice->due_amount = max(0, $netAmount - $totalPaid);
            $invoice->status = $totalPaid >= $netAmount
                ? ApInvoice::STATUS_PAID
                : ($totalPaid > 0 ? ApInvoice::STATUS_PARTIAL : ApInvoice::STATUS_UNPAID);

            $invoice->save();
        }
    }
}
