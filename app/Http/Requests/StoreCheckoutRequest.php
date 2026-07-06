<?php

namespace App\Http\Requests;

use App\Models\SiteSetting;
use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitizza i dati in input prima della validazione.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && $this->notes !== null) {
            $this->merge([
                'notes' => strip_tags($this->notes),
            ]);
        }
        // Normalizza il codice fiscale a maiuscole
        if ($this->has('codice_fiscale') && $this->codice_fiscale !== null) {
            $this->merge([
                'codice_fiscale' => strtoupper(trim($this->codice_fiscale)),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'shipping_first_name' => ['required', 'string', 'max:100'],
            'shipping_last_name' => ['required', 'string', 'max:100'],
            'shipping_street' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_zip_code' => ['required', 'string', 'max:20'],
            'shipping_province' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'billing_same_as_shipping' => ['required', 'boolean'],
            'payment_gateway' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $activeGateways = SiteSetting::get('shop.active_payment_gateways', 'stripe,paypal,bank_transfer');
                    $allowed = array_map('trim', explode(',', $activeGateways));
                    if (! in_array($value, $allowed)) {
                        $fail(__('messages.checkout.gateway_not_available'));
                    }
                },
            ],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'privacy_accepted' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'codice_fiscale' => ['nullable', 'string', 'size:16', 'regex:/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        // Billing address fields required if not same as shipping
        if (! $this->boolean('billing_same_as_shipping')) {
            $rules['billing_first_name'] = ['required', 'string', 'max:100'];
            $rules['billing_last_name'] = ['required', 'string', 'max:100'];
            $rules['billing_street'] = ['required', 'string', 'max:255'];
            $rules['billing_city'] = ['required', 'string', 'max:100'];
            $rules['billing_zip_code'] = ['required', 'string', 'max:20'];
            $rules['billing_province'] = ['required', 'string', 'max:100'];
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
     * Aggiunge validazione condizionale per il CAP italiano.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->country === 'IT' && $this->shipping_zip_code) {
                if (! preg_match('/^\d{5}$/', $this->shipping_zip_code)) {
                    $validator->errors()->add(
                        'shipping_zip_code',
                        __('validation.zip_code_it')
                    );
                }
            }
            // Codice Fiscale obbligatorio per ordini in Italia
            if ($this->country === 'IT' && empty($this->codice_fiscale)) {
                $validator->errors()->add(
                    'codice_fiscale',
                    'Il Codice Fiscale è obbligatorio per ordini in Italia.'
                );
            }
        });
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
