<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Il registro delle attività serve a rivedere il lavoro della redazione nel
 * pannello, ma `LogsActivity` stava anche su carrelli, righe d'ordine,
 * offerte, coupon usati, ordini e account, che scrivono i visitatori dal sito
 * pubblico: a ogni checkout e registrazione copiava recapiti, indirizzi,
 * codice fiscale, IP e browser del cliente. Il trait ora non c'è più sui
 * primi, registra solo le azioni dello staff su ordini, account e movimenti
 * di magazzino, e su tutti scrive autore, IP e browser solo per lo staff; qui
 * si toglie ciò che aveva già copiato.
 *
 * Le liste stanno scritte qui e non lette dai modelli: la migrazione deve
 * fare domani la stessa cosa che fa oggi, anche se i modelli cambiano.
 * Rilanciarla non fa danni: cancella e ripulisce solo ciò che è ancora da
 * cancellare o ripulire.
 */
return new class extends Migration
{
    /** Modelli che non passano più dal registro: le loro righe se ne vanno. */
    private const MODELLI_TOLTI = [
        'App\\Models\\Cart',
        'App\\Models\\CartItem',
        'App\\Models\\OrderItem',
        'App\\Models\\Bid',
        'App\\Models\\CouponUsage',
    ];

    /** Modelli che restano, coi campi personali da togliere dai `changes`. */
    private const CAMPI_ESCLUSI = [
        'App\\Models\\Order' => [
            'guest_email', 'guest_name', 'guest_phone', 'phone',
            'shipping_address', 'billing_address', 'country', 'billing_country',
            'codice_fiscale', 'notes', 'order_token',
        ],
        'App\\Models\\User' => [
            'name', 'email', 'phone', 'address',
            'password', 'remember_token', 'stripe_customer_id',
        ],
    ];

    /** Modelli registrati solo quando agisce lo staff (`$logSoloDalPannello`). */
    private const SOLO_DAL_PANNELLO = [
        'App\\Models\\Order',
        'App\\Models\\User',
        'App\\Models\\StockMovement',
    ];

    /** Ruoli che entrano nel pannello (`UserRole::canAccessPanel`). */
    private const RUOLI_DEL_PANNELLO = [
        'super_admin', 'communication_manager', 'shop_manager', 'sport_coordinator',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        DB::table('activity_logs')->whereIn('model_type', self::MODELLI_TOLTI)->delete();

        $staff = DB::table('users')->select('id')->whereIn('role', self::RUOLI_DEL_PANNELLO);

        // Ordini, account e movimenti di magazzino toccati da checkout,
        // webhook, job o dal cliente stesso: senza un autore del pannello la
        // riga non serve a rivedere il lavoro di nessuno.
        DB::table('activity_logs')
            ->whereIn('model_type', self::SOLO_DAL_PANNELLO)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $staff))
            ->delete();

        // Sugli altri modelli (offerte che aggiornano l'asta, coupon usati…)
        // la riga resta come azione di sistema: senza il cliente che l'ha
        // fatta né il suo IP e browser.
        DB::table('activity_logs')
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $staff))
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('ip_address')->orWhereNotNull('user_agent'))
            ->update(['user_id' => null, 'ip_address' => null, 'user_agent' => null]);

        $this->ripulisciLeRigheDelloStaff();
    }

    /**
     * Sulle righe che restano (azioni dello staff) si tolgono i campi
     * personali dal diff, e dalle etichette degli account dei clienti il
     * nome, come fa `DatiDelCliente::cancella` alla cancellazione.
     */
    private function ripulisciLeRigheDelloStaff(): void
    {
        $idDelloStaff = DB::table('users')->whereIn('role', self::RUOLI_DEL_PANNELLO)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::table('activity_logs')
            ->whereIn('model_type', array_keys(self::CAMPI_ESCLUSI))
            ->chunkById(500, function ($righe) use ($idDelloStaff) {
                foreach ($righe as $riga) {
                    $modifiche = [];

                    $changes = is_string($riga->changes) ? json_decode($riga->changes, true) : null;
                    if (is_array($changes)) {
                        $ripuliti = $this->senzaICampi($changes, self::CAMPI_ESCLUSI[$riga->model_type]);
                        if ($ripuliti !== $changes) {
                            $modifiche['changes'] = $ripuliti === [] ? null : json_encode($ripuliti);
                        }
                    }

                    if ($riga->model_type === 'App\\Models\\User'
                        && ! in_array((int) $riga->model_id, $idDelloStaff, true)) {
                        $etichetta = 'Cliente #'.$riga->model_id;
                        if ($riga->model_label !== $etichetta) {
                            $modifiche['model_label'] = $etichetta;
                        }
                    }

                    if ($modifiche !== []) {
                        DB::table('activity_logs')->where('id', $riga->id)->update($modifiche);
                    }
                }
            });
    }

    /**
     * @param  array<string, mixed>  $changes  `{old: {...}, new: {...}}`
     * @param  list<string>  $campi
     * @return array<string, mixed>
     */
    private function senzaICampi(array $changes, array $campi): array
    {
        foreach (['old', 'new'] as $lato) {
            if (! isset($changes[$lato]) || ! is_array($changes[$lato])) {
                continue;
            }

            $changes[$lato] = array_diff_key($changes[$lato], array_flip($campi));

            if ($changes[$lato] === []) {
                unset($changes[$lato]);
            }
        }

        return $changes;
    }

    /**
     * Niente da rimettere: i dati tolti erano copie che non dovevano
     * esistere, e ricostruirli non si può.
     */
    public function down(): void
    {
        //
    }
};
