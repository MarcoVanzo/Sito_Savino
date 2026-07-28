<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fotografia completa di chi può fare cosa, ruolo per ruolo.
 *
 * Le policy sono decine e in gran parte identiche fra loro: qualunque tentativo
 * di ridurne la duplicazione rischia di spostare in silenzio un permesso. Questo
 * test enumera per riflessione ogni policy e ogni suo metodo, e confronta il
 * risultato con una matrice attesa scritta per esteso: se un refactor cambia
 * anche un solo esito, qui si vede subito quale.
 *
 * L'elenco atteso non è generato dal codice sotto test — sarebbe una tautologia:
 * è il comportamento verificato al momento in cui la matrice è stata fissata.
 */
class PolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Metodi che non sono verifiche di permesso e vanno esclusi.
     *
     * @var list<string>
     */
    private const NOT_ABILITIES = ['__construct', 'before', 'deny', 'allow'];

    /**
     * `role` non è mass-assignable — è una difesa contro l'escalation di
     * privilegi — quindi la factory lo scarterebbe in silenzio.
     */
    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true])->save();

        return $user->refresh();
    }

    /**
     * @return list<class-string>
     */
    private function policies(): array
    {
        $classes = [];

        foreach (glob(app_path('Policies/*.php')) ?: [] as $file) {
            $class = 'App\\Policies\\'.basename($file, '.php');

            if (class_exists($class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function abilities(string $policy): array
    {
        $methods = [];

        foreach ((new ReflectionClass($policy))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! in_array($method->getName(), self::NOT_ABILITIES, true)) {
                $methods[] = $method->getName();
            }
        }

        sort($methods);

        return $methods;
    }

    /**
     * Esegue ogni metodo di ogni policy per ogni ruolo.
     *
     * I metodi che richiedono il modello ricevono un'istanza vuota: le policy di
     * questo progetto decidono in base al ruolo e, dove guardano il record, lo
     * fanno su campi che su un'istanza nuova sono null — comportamento comunque
     * stabile e quindi confrontabile.
     *
     * @return array<string, array<string, bool>>
     */
    private function snapshot(): array
    {
        $matrix = [];

        foreach (UserRole::cases() as $role) {
            $user = $this->userWithRole($role);

            foreach ($this->policies() as $policy) {
                $short = Str::of($policy)->classBasename()->value();
                $instance = app($policy);

                foreach ($this->abilities($policy) as $ability) {
                    $method = new ReflectionMethod($policy, $ability);
                    $arguments = [$user];

                    // Oltre allo User, il secondo parametro è il modello. Il
                    // trait condiviso lo dichiara `mixed $record = null` per
                    // permettere alle policy di sovrascrivere con un tipo
                    // concreto: in quel caso si passa null, altrimenti quei
                    // metodi uscirebbero dalla rete di sicurezza proprio dove
                    // serve, cioè sui permessi che ricevono il record.
                    if ($method->getNumberOfParameters() > 1) {
                        $type = $method->getParameters()[1]->getType();
                        $modelClass = $type instanceof \ReflectionNamedType ? $type->getName() : null;

                        $arguments[] = $modelClass !== null && class_exists($modelClass)
                            ? new $modelClass
                            : null;
                    }

                    $matrix[$role->value][$short.'::'.$ability] = (bool) $method->invokeArgs($instance, $arguments);
                }
            }
        }

        return $matrix;
    }

    #[Test]
    public function il_super_admin_puo_tutto_tramite_gate_before(): void
    {
        // Il bypass vive in AppServiceProvider, non nelle policy: se sparisse,
        // ogni policy dovrebbe reinserire il controllo a mano.
        $admin = $this->userWithRole(UserRole::SuperAdmin);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Order::class));
        $this->assertTrue(Gate::forUser($admin)->allows('deleteAny', Player::class));
        $this->assertTrue(Gate::forUser($admin)->allows('un-permesso-inesistente'));
    }

    #[Test]
    public function nessuna_policy_lascia_scoperti_i_metodi_di_scrittura(): void
    {
        // Filament considera permessa un'azione quando la policy non definisce il
        // metodo corrispondente: una policy che dichiara `delete` ma non
        // `deleteAny` lascia quindi aperta la cancellazione in blocco.
        $mancanti = [];

        foreach ($this->policies() as $policy) {
            $abilities = $this->abilities($policy);

            foreach ([['delete', 'deleteAny'], ['restore', 'restoreAny'], ['forceDelete', 'forceDeleteAny']] as [$single, $bulk]) {
                if (in_array($single, $abilities, true) && ! in_array($bulk, $abilities, true)) {
                    $mancanti[] = Str::of($policy)->classBasename()." definisce {$single} ma non {$bulk}";
                }
            }
        }

        $this->assertSame([], $mancanti, implode("\n", $mancanti));
    }

    #[Test]
    public function la_matrice_dei_permessi_non_cambia(): void
    {
        $atteso = json_decode(
            file_get_contents(base_path('tests/Fixtures/policy-matrix.json')),
            associative: true,
        );

        $attuale = $this->snapshot();

        foreach ($atteso as $role => $abilities) {
            foreach ($abilities as $ability => $allowed) {
                $this->assertArrayHasKey(
                    $ability,
                    $attuale[$role] ?? [],
                    "Il permesso {$ability} non esiste più per il ruolo {$role}: se la rimozione è voluta, aggiorna la fixture."
                );

                $this->assertSame(
                    $allowed,
                    $attuale[$role][$ability],
                    "Il permesso {$ability} per il ruolo {$role} è cambiato."
                );
            }
        }

        // Un permesso nuovo va aggiunto alla fixture consapevolmente, altrimenti
        // una policy aggiunta per sbaglio passerebbe inosservata.
        foreach ($attuale as $role => $abilities) {
            $nuovi = array_diff_key($abilities, $atteso[$role] ?? []);

            $this->assertSame(
                [],
                array_keys($nuovi),
                "Permessi non previsti per il ruolo {$role}: aggiornare tests/Fixtures/policy-matrix.json."
            );
        }
    }
}
