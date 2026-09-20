<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApInvoiceRequest;
use App\Http\Resources\ApInvoiceResource;
use App\Models\ApInvoice;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApInvoiceController extends Controller
{
    public function __construct(public JournalService $journal) {}

    /**
     * Return the next sequential AP invoice number.
     */
    public function nextNumber(): \Illuminate\Http\JsonResponse
    {
        $year = (int) now()->format('Y');
        $prefix = "API-{$year}-";

        $last = ApInvoice::where('ap_invoice_number', 'like', $prefix.'%')
            ->orderByDesc('ap_invoice_number')
            ->value('ap_invoice_number');

        if ($last !== null) {
            $seq = (int) substr($last, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }

        return response()->json([
            'data' => $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(
            ApInvoiceResource::collection(ApInvoice::with('vendor', 'items')->latest()->get())
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreApInvoiceRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $items = $validated['items'] ?? [];
            unset($validated['items']);

            $totalBdt = array_reduce(
                $items,
                fn (string $carry, array $item): string => bcadd($carry, (string) ($item['total_bdt'] ?? 0), 2),
                '0.00'
            );

            $validated['total_bdt'] = $totalBdt;
            $validated['net_amount'] = bcsub($totalBdt, (string) ($validated['discount_amount'] ?? 0), 2);
            $validated['due_amount'] = $validated['net_amount'];

            $apInvoice = ApInvoice::create($validated);

            foreach ($items as $item) {
                $apInvoice->items()->create($item);
            }

            $this->journal->createApInvoiceEntry($apInvoice);

            return response()->json(
                new ApInvoiceResource($apInvoice->load('vendor', 'items')),
                201
            );
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(ApInvoice $apInvoice)
    {
        return response()->json(
            new ApInvoiceResource($apInvoice->load('vendor', 'items', 'paymentLinks.apPayment'))
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreApInvoiceRequest $request, ApInvoice $apInvoice)
    {
        return DB::transaction(function () use ($request, $apInvoice) {
            $validated = $request->validated();

            $items = $validated['items'] ?? null;
            unset($validated['items']);

            if ($items !== null) {
                $totalBdt = array_reduce(
                    $items,
                    fn (string $carry, array $item): string => bcadd($carry, (string) ($item['total_bdt'] ?? 0), 2),
                    '0.00'
                );

                $validated['total_bdt'] = $totalBdt;
                $validated['net_amount'] = bcsub($totalBdt, (string) ($validated['discount_amount'] ?? 0), 2);
                $validated['due_amount'] = bcsub($validated['net_amount'], (string) ($apInvoice->paid_amount ?? 0), 2);
            }

            $apInvoice->update($validated);

            if ($items !== null) {
                $apInvoice->items()->delete();

                foreach ($items as $item) {
                    $apInvoice->items()->create($item);
                }
            }

            $this->journal->createApInvoiceEntry($apInvoice);

            return response()->json(
                new ApInvoiceResource($apInvoice->fresh('vendor', 'items'))
            );
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ApInvoice $apInvoice)
    {
        $this->assertEntryVoidable($apInvoice);

        $apInvoice->delete();

        JournalEntry::where('source_type', 'ap_invoice')
            ->where('source_id', $apInvoice->id)
            ->delete();

        return response()->json(null, 204);
    }

    /**
     * Block deleting an AP invoice whose journal entry is already approved.
     */
    private function assertEntryVoidable(ApInvoice $apInvoice): void
    {
        $approved = JournalEntry::where('source_type', 'ap_invoice')
            ->where('source_id', $apInvoice->id)
            ->where('status', JournalEntry::STATUS_APPROVED)
            ->exists();

        if ($approved) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Cannot delete AP invoice; its journal entry is approved. Void the entry first.',
            ]);
        }
    }
}
