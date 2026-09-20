<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_number' => $this->voucher_number,
            'entry_date' => $this->entry_date?->toDateString(),
            'memo' => $this->memo,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reverses_journal_entry_id' => $this->reverses_journal_entry_id,
            'total_debit' => $this->total_debit,
            'total_credit' => $this->total_credit,
            'status' => $this->status,
            'voided_at' => $this->voided_at,
            'voided_by' => $this->voided_by,
            'line_count' => $this->lines_count ?? $this->lines->count(),
            'lines' => $this->whenLoaded('lines', $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'account_type' => $line->account?->type,
                'invoice_id' => $line->invoice_id,
                'invoice_number' => $line->invoice?->invoice_number,
                'reference' => $line->reference,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'note' => $line->note,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}