<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApPaymentResource extends JsonResource
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
            'ap_payment_number' => $this->ap_payment_number,
            'payment_date' => $this->payment_date?->toDateString(),
            'payment_method' => $this->payment_method,
            'bank_account_id' => $this->bank_account_id,
            'reference_number' => $this->reference_number,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'vendor' => $this->whenLoaded('vendor', [
                'id' => $this->vendor->id,
                'vendor_code' => $this->vendor->vendor_code,
                'vendor_name' => $this->vendor->vendor_name,
            ]),
            'invoice_links' => $this->whenLoaded('invoiceLinks', $this->invoiceLinks->map(function ($link) {
                return [
                    'id' => $link->id,
                    'ap_invoice_id' => $link->ap_invoice_id,
                    'paid_amount' => $link->paid_amount,
                    'invoice' => $link->apInvoice? [
                        'id' => $link->apInvoice->id,
                        'ap_invoice_number' => $link->apInvoice->ap_invoice_number,
                        'invoice_date' => $link->apInvoice->invoice_date?->toDateString(),
                        'vendor_name' => $link->apInvoice->vendor?->vendor_name,
                    ] : null,
                ];
            })->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
