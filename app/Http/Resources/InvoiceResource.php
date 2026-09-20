<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'title' => $this->title,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'bl_number' => $this->bl_number,
            'customer_name' => $this->customer_name,
            'vessel' => $this->vessel,
            'voyage' => $this->voyage,
            'registration_no' => $this->registration_no,
            'containers' => $this->containers,
            'exchange_rate' => $this->exchange_rate,
            'amount_in_words' => $this->amount_in_words,
            'total_usd' => $this->total_usd,
            'total_bdt' => $this->total_bdt,
            'remarks' => $this->remarks,
            'received' => $this->received,
            'due' => $this->due,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'bank_details' => $this->whenLoaded('bankDetails', [
                'account_name' => $this->bankDetails?->account_name,
                'rd_account_no' => $this->bankDetails?->rd_account_no,
                'bank_name' => $this->bankDetails?->bank_name,
                'branch_name' => $this->bankDetails?->branch_name,
                'swift_code' => $this->bankDetails?->swift_code,
                'routing_no' => $this->bankDetails?->routing_no,
                'address' => $this->bankDetails?->address,
            ]),
            'items' => $this->whenLoaded('items', $this->items->map(function ($item) {
                return [
                    'key' => $item->key,
                    'label' => $item->label,
                    'qty_20' => $item->qty_20,
                    'qty_40' => $item->qty_40,
                    'rate_usd' => $item->rate_usd,
                    'rate_bdt' => $item->rate_bdt,
                    'total_usd' => $item->total_usd,
                ];
            })->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}