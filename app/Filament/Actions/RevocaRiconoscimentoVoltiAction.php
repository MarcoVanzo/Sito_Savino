<?php

namespace App\Filament\Actions;

use App\Models\Player;
use App\Models\StaffMember;
use App\Services\RevocaDelRiconoscimentoDeiVolti;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as AzioneDiTabella;

/**
 * «Revoca consenso al riconoscimento», sulla scheda e nell'elenco di atlete
 * e staff.
 *
 * Diversa da «Resetta memoria volto», che svuota solo gli esempi per
 * riaddestrare: questa toglie tutto quello che il riconoscimento ha prodotto
 * (RevocaDelRiconoscimentoDeiVolti) e lascia traccia nel registro. Il
 * consenso si raccoglie fuori dal sito, quindi lo si revoca da qui quando la
 * persona lo chiede.
 */
class RevocaRiconoscimentoVoltiAction
{
    public const NOME = 'revocaRiconoscimentoVolti';

    private const DESCRIZIONE = 'Si cancellano il volto su CompreFace, i tag automatici nelle foto della gallery e il nome nei testi generati delle foto. '
        .'I tag messi a mano dalla redazione restano. L\'operazione non si annulla: per riattivare il riconoscimento serve un nuovo consenso e un nuovo addestramento.';

    public static function perLaScheda(): Action
    {
        return Action::make(self::NOME)
            ->label('Revoca consenso al riconoscimento')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Revocare il consenso al riconoscimento dei volti?')
            ->modalDescription(self::DESCRIZIONE)
            ->modalSubmitActionLabel('Revoca e cancella')
            ->action(fn (Player|StaffMember $record) => self::esegui($record));
    }

    public static function perLaTabella(): AzioneDiTabella
    {
        return AzioneDiTabella::make(self::NOME)
            ->label('Revoca consenso al riconoscimento')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Revocare il consenso al riconoscimento dei volti?')
            ->modalDescription(self::DESCRIZIONE)
            ->modalSubmitActionLabel('Revoca e cancella')
            ->action(fn (Player|StaffMember $record) => self::esegui($record));
    }

    public static function esegui(Player|StaffMember $persona): void
    {
        $esito = app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($persona);

        $riepilogo = "Tag automatici tolti: {$esito['tag_tolti']}. Foto ripulite: {$esito['foto_ripulite']}.";

        if ($esito['titoli_da_rivedere'] !== []) {
            $riepilogo .= ' Titoli scritti a mano che la nominano ancora (foto n. '.implode(', ', $esito['titoli_da_rivedere']).'): vanno corretti dalla gallery.';
        }

        // La parte su CompreFace e' quella che conta per la revoca: se non e'
        // riuscita non si dice "fatto", si chiede di ripetere.
        if ($esito['compreface'] !== RevocaDelRiconoscimentoDeiVolti::COMPREFACE_CANCELLATO) {
            Notification::make()
                ->title('Revoca incompleta: CompreFace non ha confermato')
                ->body('Il volto potrebbe essere ancora su CompreFace: ripeti la revoca tra qualche minuto. '.$riepilogo)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Consenso revocato')
            ->body($riepilogo)
            ->success()
            ->send();
    }
}
