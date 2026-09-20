<?php

namespace App\Http\Requests;

use App\Models\ApPayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreApPaymentRequest extends FormRequest
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
        $apPaymentId = $this->route('ap_payment');

        if ($apPaymentId instanceof ApPayment) {
            $apPaymentId = $apPaymentId->getKey();
        }

        return [
            'vendor_id' => 'required|exists:vendors,id',
            'ap_payment_number' => 'required|string|max:50|unique:ap_payments,ap_payment_number' . ($apPaymentId ? ',' . $apPaymentId : ''),
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'bank_account_id' => 'nullable|string|max:20',
            'reference_number' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'invoices' => 'nullable|array',
            'invoices.*.ap_invoice_id' => 'required|exists:ap_invoices,id',
            'invoices.*.paid_amount' => 'required|numeric|min:0',
        ];
    }
}
