<?php

namespace App\Http\Requests;

use App\Enums\PaymentGateway;
use App\Http\Requests\Concerns\DatiDiSpedizioneEFatturazione;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCheckoutRequest extends FormRequest
{
    use DatiDiSpedizioneEFatturazione;

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
        $rules = [
            ...$this->regoleDegliIndirizzi(),
            'payment_gateway' => $this->regolaDelMetodoDiPagamento(PaymentGateway::offertiAlCheckout()),
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if (! $this->boolean('billing_same_as_shipping')) {
            $rules['billing_country'] = ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'];
        }

        // Campi guest obbligatori se non autenticato
        if (! auth()->check()) {
            $rules['guest_name'] = ['required', 'string', 'max:255'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
            $rules['guest_phone'] = ['required', 'string', 'max:30'];
        }

        // Telefono obbligatorio per utenti autenticati
        if (auth()->check()) {
            $rules['phone'] = ['required', 'string', 'max:30'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_gateway.in' => __('messages.checkout.gateway_not_available'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->controllaIDatiItaliani($validator);
    }

    /**
     * Build the structured order data array from validated input.
     *
     * @return array<string, mixed>
     */
    public function toOrderData(): array
    {
        $shippingAddress = [
            'first_name' => $this->shipping_first_name,
            'last_name' => $this->shipping_last_name,
            'street' => $this->shipping_street,
            'city' => $this->shipping_city,
            'zip_code' => $this->shipping_zip_code,
            'province' => $this->shipping_province,
        ];

        $billingAddress = $this->billing_same_as_shipping
            ? $shippingAddress
            : [
                'first_name' => $this->billing_first_name,
                'last_name' => $this->billing_last_name,
                'street' => $this->billing_street,
                'city' => $this->billing_city,
                'zip_code' => $this->billing_zip_code,
                'province' => $this->billing_province,
            ];

        return [
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'country' => $this->country,
            'billing_country' => $this->billing_same_as_shipping
                ? $this->country
                : $this->billing_country,
            'payment_gateway' => $this->payment_gateway,
            'coupon_code' => $this->coupon_code,
            'notes' => $this->notes,
            'privacy_accepted_at' => now(),
            'guest_name' => $this->guest_name ?? null,
            'guest_email' => $this->guest_email ?? (auth()->user()?->email),
            'guest_phone' => $this->guest_phone ?? null,
            'codice_fiscale' => $this->codice_fiscale ?? null,
            'phone' => $this->phone ?? null,
            'user_id' => auth()->id(),
        ];
    }
}
