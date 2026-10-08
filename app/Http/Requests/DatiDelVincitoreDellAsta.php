<?php

namespace App\Http\Requests;

use App\Enums\PaymentGateway;
use App\Http\Requests\Concerns\DatiDiSpedizioneEFatturazione;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Il modulo con cui il vincitore conclude l'asta: gli stessi dati del
 * checkout dello shop, con il telefono sempre obbligatorio (il vincitore ha
 * un account) e i soli metodi di pagamento offerti alle aste.
 */
class DatiDelVincitoreDellAsta extends FormRequest
{
    use DatiDiSpedizioneEFatturazione;

    /**
     * Che il cliente sia il vincitore lo controlla il controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->regoleDegliIndirizzi(),
            'phone' => ['required', 'string', 'max:30'],
            'payment_gateway' => $this->regolaDelMetodoDiPagamento(PaymentGateway::offertiAlleAste()),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->controllaIDatiItaliani($validator);
    }
}
