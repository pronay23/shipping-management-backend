<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(public JournalService $journal) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(
            InvoiceResource::collection(Invoice::with('bankDetails', 'items')->latest()->get())
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $invoice = Invoice::create($request->only([
                'title',
                'invoice_number',
                'invoice_date',
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
                'remarks',
                'received',
                'due',
                'status',
            ]));

            if ($request->filled('bank_details')) {
                $invoice->bankDetails()->create($request->input('bank_details'));
            }

            if ($request->has('items')) {
                foreach ($request->input('items', []) as $item) {
                    $invoice->items()->create($item);
                }
            }

            $this->journal->createInvoiceEntry($invoice);

            return response()->json(
                new InvoiceResource($invoice->load('bankDetails', 'items')),
                201
            );
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        return response()->json(
            new InvoiceResource($invoice->load('bankDetails', 'items'))
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreInvoiceRequest $request, Invoice $invoice)
    {
        return DB::transaction(function () use ($request, $invoice) {
            $invoice->update($request->only([
                'title',
                'invoice_number',
                'invoice_date',
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
                'remarks',
                'received',
                'due',
                'status',
            ]));

            if ($request->has('bank_details')) {
                $invoice->bankDetails()->updateOrCreate(
                    ['invoice_id' => $invoice->id],
                    $request->input('bank_details', [])
                );
            }

            if ($request->has('items')) {
                $invoice->items()->delete();

                foreach ($request->input('items', []) as $item) {
                    $invoice->items()->create($item);
                }
            }

            $this->journal->createInvoiceEntry($invoice);

            return response()->json(
                new InvoiceResource($invoice->load('bankDetails', 'items'))
            );
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        $this->assertEntryVoidable($invoice);

        $invoice->delete();

        JournalEntry::where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->delete();

        return response()->json(null, 204);
    }

    /**
     * Block deleting an invoice whose journal entry is already approved.
     */
    private function assertEntryVoidable(Invoice $invoice): void
    {
        $approved = JournalEntry::where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->where('status', JournalEntry::STATUS_APPROVED)
            ->exists();

        if ($approved) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Cannot delete invoice; its journal entry is approved. Void the entry first.',
            ]);
        }
    }
}