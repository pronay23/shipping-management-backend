<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $invoiceId = $this->route('invoice');

        if ($invoiceId instanceof Invoice) {
            $invoiceId = $invoiceId->getKey();
        }

        return [
            'title' => 'nullable|string|max:255',
            'invoice_number' => 'required|string|max:50|unique:invoices,invoice_number' . ($invoiceId ? ',' . $invoiceId : ''),
            'invoice_date' => 'nullable|date',
            'bl_number' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string|max:255',
            'vessel' => 'nullable|string|max:100',
            'voyage' => 'nullable|string|max:50',
            'registration_no' => 'nullable|string|max:100',
            'containers' => 'nullable|string|max:255',
            'exchange_rate' => 'nullable|numeric',
            'amount_in_words' => 'nullable|string',
            'total_usd' => 'nullable|numeric',
            'total_bdt' => 'nullable|numeric',
            'remarks' => 'nullable|string',
            'received' => 'nullable|numeric|min:0',
            'due' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:unpaid,partial,paid',
            'bank_details' => 'nullable|array',
            'bank_details.account_name' => 'nullable|string|max:255',
            'bank_details.rd_account_no' => 'nullable|string|max:100',
            'bank_details.bank_name' => 'nullable|string|max:255',
            'bank_details.branch_name' => 'nullable|string|max:255',
            'bank_details.swift_code' => 'nullable|string|max:20',
            'bank_details.routing_no' => 'nullable|string|max:50',
            'bank_details.address' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.key' => 'nullable|string|max:50',
            'items.*.label' => 'nullable|string|max:255',
            'items.*.qty_20' => 'nullable|integer|min:0',
            'items.*.qty_40' => 'nullable|integer|min:0',
            'items.*.rate_usd' => 'nullable|numeric',
            'items.*.rate_bdt' => 'nullable|numeric',
            'items.*.total_usd' => 'nullable|numeric',
        ];
    }
}