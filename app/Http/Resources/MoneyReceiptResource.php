<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MoneyReceiptResource extends JsonResource
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
            'title' => $this->title,
            'money_receipt_number' => $this->money_receipt_number,
            'money_receipt_date' => $this->money_receipt_date?->toDateString(),
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
            'payment_term' => $this->payment_term,
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
            'invoices' => $this->whenLoaded('invoiceLinks', $this->invoiceLinks->map(function ($link) {
                return [
                    'invoice_id' => $link->invoice_id,
                    'bill_of_lading_id' => $link->bill_of_lading_id,
                    'paid_amount' => $link->paid_amount,
                ];
            })->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}