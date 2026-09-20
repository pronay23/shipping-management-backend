<?php

namespace App\Http\Requests;

use App\Models\ApInvoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreApInvoiceRequest extends FormRequest
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
        $apInvoiceId = $this->route('ap_invoice');

        if ($apInvoiceId instanceof ApInvoice) {
            $apInvoiceId = $apInvoiceId->getKey();
        }

        return [
            'vendor_id' => 'required|exists:vendors,id',
            'ap_invoice_number' => 'required|string|max:50|unique:ap_invoices,ap_invoice_number' . ($apInvoiceId ? ',' . $apInvoiceId : ''),
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_bdt' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
