<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentGateway;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Le regole comuni ai due checkout (shop e aste): indirizzi, dati fiscali,
 * privacy e metodo di pagamento, scritte una volta sola perché i due moduli
 * chiedono gli stessi dati e un controllo in più su uno solo è un ordine
 * accettato dall'altro.
 */
trait DatiDiSpedizioneEFatturazione
{
    /**
     * Note senza markup e codice fiscale in maiuscolo, prima della validazione.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && $this->notes !== null) {
            $this->merge(['notes' => strip_tags($this->notes)]);
        }

        if ($this->has('codice_fiscale') && $this->codice_fiscale !== null) {
            $this->merge(['codice_fiscale' => strtoupper(trim($this->codice_fiscale))]);
        }
    }

    /**
     * Spedizione, paese, codice fiscale, privacy e note; la fatturazione solo
     * quando è diversa dalla spedizione.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function regoleDegliIndirizzi(): array
    {
        $regole = [
            'shipping_first_name' => ['required', 'string', 'max:100'],
            'shipping_last_name' => ['required', 'string', 'max:100'],
            'shipping_street' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_zip_code' => ['required', 'string', 'max:20'],
            'shipping_province' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'codice_fiscale' => ['nullable', 'string', 'size:16', 'regex:/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i'],
            'billing_same_as_shipping' => ['required', 'boolean'],
            'privacy_accepted' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $this->boolean('billing_same_as_shipping')) {
            $regole['billing_first_name'] = ['required', 'string', 'max:100'];
            $regole['billing_last_name'] = ['required', 'string', 'max:100'];
            $regole['billing_street'] = ['required', 'string', 'max:255'];
            $regole['billing_city'] = ['required', 'string', 'max:100'];
            $regole['billing_zip_code'] = ['required', 'string', 'max:20'];
            $regole['billing_province'] = ['required', 'string', 'max:100'];
        }

        return $regole;
    }

    /**
     * Il metodo di pagamento, fra quelli offerti ora.
     *
     * @param  array<int, PaymentGateway>  $offerti
     * @return array<int, mixed>
     */
    protected function regolaDelMetodoDiPagamento(array $offerti): array
    {
        return [
            'required',
            'string',
            Rule::in(array_map(fn (PaymentGateway $gateway): string => $gateway->value, $offerti)),
        ];
    }

    /**
     * Per l'Italia il CAP è di cinque cifre e il codice fiscale è
     * obbligatorio: serve per la fattura.
     */
    protected function controllaIDatiItaliani(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->country === 'IT' && $this->shipping_zip_code
                && ! preg_match('/^\d{5}$/', $this->shipping_zip_code)) {
                $validator->errors()->add('shipping_zip_code', __('validation.zip_code_it'));
            }

            if ($this->country === 'IT' && empty($this->codice_fiscale)) {
                $validator->errors()->add('codice_fiscale', __('messages.auction.codice_fiscale_required'));
            }

            if (! $this->boolean('billing_same_as_shipping') && $this->billing_country === 'IT'
                && $this->billing_zip_code && ! preg_match('/^\d{5}$/', $this->billing_zip_code)) {
                $validator->errors()->add('billing_zip_code', 'Il CAP di fatturazione deve essere di 5 cifre per indirizzi italiani.');
            }
        });
    }
}
