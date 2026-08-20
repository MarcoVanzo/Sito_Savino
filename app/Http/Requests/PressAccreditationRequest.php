<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PressAccreditationRequest extends FormRequest
{
    /**
     * Ruoli accreditabili. Il valore viaggia nel modulo, l'etichetta mostrata
     * al pubblico sta nelle traduzioni: qui serve solo sapere che è uno dei tre.
     */
    public const ROLES = ['giornalista', 'fotografo', 'operatore'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'outlet' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ROLES)],
            'match' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'honeypot' => ['present', 'max:0'], // Anti-spam honeypot
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => __('messages.validation.name_required'),
            'last_name.required' => __('messages.validation.name_required'),
            'email.required' => __('messages.validation.email_required'),
            'email.email' => __('messages.validation.email_invalid'),
            'phone.required' => __('messages.validation.phone_required'),
            'outlet.required' => __('messages.validation.outlet_required'),
            'role.required' => __('messages.validation.role_required'),
            'match.required' => __('messages.validation.match_required'),
        ];
    }
}
