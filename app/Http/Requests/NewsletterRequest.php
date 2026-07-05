<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizza l'email a lowercase prima della validazione.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            // L'honeypot in frontend è invisibile.
            // Se lasciato vuoto da un utente reale, Laravel (ConvertEmptyStringsToNull) lo converte in `null`.
            // La regola `max:0` ignora il campo se è null (poiché manca la regola `required`).
            // Se un bot lo compila (es. con "test"), il valore ha lunghezza > 0 e la validazione fallisce.
            'honeypot' => ['present', 'max:0'],
            'privacy_accepted' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('messages.validation.email_required'),
            'email.email' => __('messages.validation.email_invalid'),
            'email.max' => __('messages.validation.email_max'),
            'privacy_accepted.accepted' => __('messages.validation.privacy_accepted'),
        ];
    }
}
