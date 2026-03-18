<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'gateway_id' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'max:5'],
            'customer_email' => ['nullable', 'email'],
            'escrow_duration' => ['nullable', 'integer', 'min:1'],
            'payout_destination' => ['nullable', 'string'],
            'transaction_type' => ['required', 'string', 'in:collect,payout'],
        ];

        if ($this->is('api/payments/redirect')) {
            $rules['callback_url'] = ['required', 'url'];
        } else {
            $rules['callback_url'] = ['nullable', 'url'];
        }

        if ($this->is('api/payments/direct')) {
            $rules['phone'] = ['nullable', 'string'];
        } else {
            $rules['phone'] = ['nullable', 'string'];
        }

        return $rules;
    }
}
