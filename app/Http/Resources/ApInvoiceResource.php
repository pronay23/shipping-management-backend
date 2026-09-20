<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApInvoiceResource extends JsonResource
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
            'vendor_id' => $this->vendor_id,
            'ap_invoice_number' => $this->ap_invoice_number,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'total_bdt' => $this->total_bdt,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'net_amount' => $this->net_amount,
            'paid_amount' => $this->paid_amount,
            'due_amount' => $this->due_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'vendor' => $this->whenLoaded('vendor', [
                'id' => $this->vendor->id,
                'vendor_code' => $this->vendor->vendor_code,
                'vendor_name' => $this->vendor->vendor_name,
            ]),
            'items' => $this->whenLoaded('items', $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'account_id' => $item->account_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_bdt' => $item->total_bdt,
                    'notes' => $item->notes,
                ];
            })->values()),
            'payment_links' => $this->whenLoaded('paymentLinks', $this->paymentLinks->map(function ($link) {
                return [
                    'id' => $link->id,
                    'ap_payment_id' => $link->ap_payment_id,
                    'paid_amount' => $link->paid_amount,
                    'payment' => $link->apPayment? [
                        'id' => $link->apPayment->id,
                        'ap_payment_number' => $link->apPayment->ap_payment_number,
                        'payment_date' => $link->apPayment->payment_date?->toDateString(),
                        'payment_method' => $link->apPayment->payment_method,
                    ] : null,
                ];
            })->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
