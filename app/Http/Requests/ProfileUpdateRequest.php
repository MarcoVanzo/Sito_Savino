<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Cambiare l'email e poi chiedere il reset della password basta a
            // prendersi l'account: con una sessione lasciata aperta su un
            // computer altrui non deve bastare, serve la password attuale.
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->cambiaLEmail()),
                'nullable',
                'string',
                'current_password',
            ],
        ];
    }

    private function cambiaLEmail(): bool
    {
        return mb_strtolower(trim((string) $this->input('email')))
            !== mb_strtolower((string) $this->user()->email);
    }
}
