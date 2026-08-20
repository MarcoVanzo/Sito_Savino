<?php

namespace App\Filament\Actions;

use App\Models\Player;
use App\Services\Wikipedia\PalmaresImporter;
use App\Services\Wikipedia\PalmaresParser;
use App\Services\Wikipedia\WikipediaClient;
use App\Services\Wikipedia\WikipediaPageResolver;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Pulsante "Crea palmarès": legge la voce di Wikipedia dell'atleta e ne
 * importa trofei, medaglie e premi.
 *
 * Non importa alla cieca. La modale mostra **quale voce** è stata agganciata e
 * **cosa** sta per entrare in archivio, e il titolo è un campo modificabile:
 * la ricerca per nome è la parte fragile del meccanismo (gli omonimi esistono,
 * gli accenti pure) e l'ultima parola deve restare alla redazione.
 */
class ImportPalmaresAction
{
    /**
     * L'anteprima si ricarica a ogni uscita dal campo del titolo: senza cache
     * la stessa voce verrebbe riscaricata a ogni click nella modale.
     */
    private const PREVIEW_TTL_SECONDS = 600;

    public static function make(Player $player, string $name = 'importPalmares'): Action
    {
        // L'atleta si passa esplicitamente: è un'azione di testata, non di riga,
        // e Filament non ha nessun record da iniettare.
        return Action::make($name)
            ->label('Crea palmarès')
            ->icon('heroicon-o-trophy')
            ->color('warning')
            ->modalHeading('Importa il palmarès da Wikipedia')
            ->modalDescription('Le righe già importate vengono sostituite. Quelle inserite o corrette a mano restano dove sono.')
            ->modalSubmitActionLabel('Importa')
            ->modalWidth('3xl')
            ->fillForm(fn (): array => self::initialState($player))
            ->form([
                Forms\Components\TextInput::make('wikipedia_title')
                    ->label('Voce di Wikipedia')
                    ->helperText('Titolo esatto della voce su it.wikipedia.org. Correggilo se l\'atleta agganciata non è quella giusta.')
                    ->live(onBlur: true)
                    ->required(),
                Forms\Components\Placeholder::make('anteprima')
                    ->label('Anteprima')
                    ->content(fn (Get $get): HtmlString => self::preview((string) $get('wikipedia_title'))),
            ])
            ->action(function (array $data) use ($player): void {
                self::import($player, (string) $data['wikipedia_title']);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private static function initialState(Player $player): array
    {
        if ($player->wikipedia_title !== null) {
            return ['wikipedia_title' => $player->wikipedia_title];
        }

        try {
            $page = app(WikipediaPageResolver::class)->resolve($player);
        } catch (Throwable) {
            return ['wikipedia_title' => trim("{$player->first_name} {$player->last_name}")];
        }

        return [
            'wikipedia_title' => $page['title'] ?? trim("{$player->first_name} {$player->last_name}"),
        ];
    }

    private static function import(Player $player, string $title): void
    {
        $client = app(WikipediaClient::class);

        try {
            $page = $client->page($title);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Wikipedia non raggiungibile')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        if ($page === null) {
            Notification::make()
                ->title('Voce inesistente')
                ->body("Su it.wikipedia.org non c'è nessuna voce intitolata «{$title}».")
                ->warning()
                ->send();

            return;
        }

        $stats = app(PalmaresImporter::class)->import(
            $player,
            $page['wikitext'],
            $page['title'],
            $page['revid'],
            $client->lang(),
        );

        self::forgetPreview($title);

        if ($stats['imported'] === 0 && $stats['kept'] === 0) {
            Notification::make()
                ->title('Nessun palmarès nella voce')
                ->body("«{$page['title']}» non ha una sezione Palmarès leggibile. Le righe si possono inserire a mano.")
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Palmarès importato')
            ->body(self::summary($stats))
            ->success()
            ->send();
    }

    /**
     * @param  array{imported: int, kept: int, skipped: int}  $stats
     */
    private static function summary(array $stats): string
    {
        $parts = [$stats['imported'].' '.($stats['imported'] === 1 ? 'riga importata' : 'righe importate')];

        if ($stats['kept'] > 0) {
            $parts[] = $stats['kept'].' manuali conservate';
        }

        if ($stats['skipped'] > 0) {
            $parts[] = $stats['skipped'].' già presenti a mano';
        }

        return implode(', ', $parts).'.';
    }

    private static function preview(string $title): HtmlString
    {
        $title = trim($title);

        if ($title === '') {
            return new HtmlString('<p class="text-sm text-gray-500">Scrivi il titolo della voce per vedere l\'anteprima.</p>');
        }

        try {
            $honours = Cache::remember(
                self::previewKey($title),
                self::PREVIEW_TTL_SECONDS,
                function () use ($title): ?array {
                    $page = app(WikipediaClient::class)->page($title);

                    return $page === null ? null : app(PalmaresParser::class)->parse($page['wikitext']);
                }
            );
        } catch (Throwable $e) {
            return new HtmlString('<p class="text-sm text-danger-600">Wikipedia non raggiungibile: '.e($e->getMessage()).'</p>');
        }

        return new HtmlString(
            view('filament.actions.palmares-preview', [
                'title' => $title,
                'honours' => $honours,
            ])->render()
        );
    }

    private static function forgetPreview(string $title): void
    {
        Cache::forget(self::previewKey($title));
    }

    private static function previewKey(string $title): string
    {
        return 'wikipedia:palmares-preview:'.md5($title);
    }
}
