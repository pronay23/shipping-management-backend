<?php

namespace App\Http\Requests;

use App\Models\MoneyReceipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMoneyReceiptRequest extends FormRequest
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
        $moneyReceiptId = $this->route('money_receipt');

        if ($moneyReceiptId instanceof MoneyReceipt) {
            $moneyReceiptId = $moneyReceiptId->getKey();
        }

        return [
            'title' => 'nullable|string|max:255',
            'money_receipt_number' => 'required|string|max:50|unique:money_receipts,money_receipt_number' . ($moneyReceiptId ? ',' . $moneyReceiptId : ''),
            'money_receipt_date' => 'nullable|date',
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
            'payment_term' => 'required|string|max:100',
            'items' => 'nullable|array',
            'items.*.key' => 'nullable|string|max:50',
            'items.*.label' => 'nullable|string|max:255',
            'items.*.qty_20' => 'nullable|integer|min:0',
            'items.*.qty_40' => 'nullable|integer|min:0',
            'items.*.rate_usd' => 'nullable|numeric',
            'items.*.rate_bdt' => 'nullable|numeric',
            'items.*.total_usd' => 'nullable|numeric',
            'invoices' => 'nullable|array',
            'invoices.*.invoice_id' => 'nullable|exists:invoices,id',
            'invoices.*.bill_of_lading_id' => 'nullable|exists:bill_of_ladings,id',
            'invoices.*.paid_amount' => 'nullable|numeric|min:0',
        ];
    }
}