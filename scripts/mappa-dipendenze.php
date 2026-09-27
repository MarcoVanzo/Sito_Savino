<?php

/**
 * Mappa delle dipendenze del progetto, e il controllo che ne deriva.
 *
 *   php scripts/mappa-dipendenze.php                  controlla ed esce 1 se trova problemi
 *   php scripts/mappa-dipendenze.php --json=grafo.json  scrive anche il grafo
 *   php scripts/mappa-dipendenze.php --html=mappa.html  e la mappa 3D navigabile
 *
 * Un file e' un nodo; un legame e' una dipendenza che il codice dichiara
 * davvero. Le classi PHP le legge il parser del linguaggio (nomi risolti, i
 * commenti non contano), le rotte vengono dal router gia' avviato, e si
 * aggiungono le convenzioni con cui Laravel e Filament collegano file che non
 * si nominano: scoperta automatica di risorse, pagine, widget, comandi e
 * listener; Model -> Policy e Model -> Factory; `config()`; viste; comandi
 * lanciati per nome; i modelli di pagina di `PageTemplate`.
 *
 * Due controlli, entrambi bloccanti in CI:
 *  1. Codice che nessuno raggiunge. Si parte da rotte, config, bootstrap,
 *     migrazioni e seeder e si seguono i legami, senza passare dai test: un
 *     file raggiunto solo da un test non serve al sito.
 *  2. Riferimenti rotti: rotte, viste, pagine Vue, import JS e classi `App\`
 *     citati ma inesistenti.
 *
 * Restano fuori traduzioni, CSS, `vendor` e i legami che vivono solo nei dati
 * del database. Un falso positivo si mette in ECCEZIONI con il motivo.
 */

use App\Enums\PageTemplate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

$radice = dirname(__DIR__);
chdir($radice);
require $radice.'/vendor/autoload.php';
$app = require $radice.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** Percorsi che il controllo non deve segnalare, con il motivo. */
const ECCEZIONI = [
    // 'app/Esempio.php' => 'lo carica un servizio esterno per nome',
];

$opzioni = getopt('', ['json:', 'html:']);

// ---------------------------------------------------------------- nodi

$nodi = [];
$legami = [];

function strato(string $p): string
{
    if (str_starts_with($p, 'tests/') || str_contains($p, '.test.') || str_starts_with($p, 'resources/js/testing/')) {
        return 'Test';
    }
    foreach ([
        'routes/' => 'Rotte', 'config/' => 'Config e bootstrap', 'bootstrap/' => 'Config e bootstrap',
        'database/migrations' => 'Migrazioni', 'database/data' => 'Dati', 'database/' => 'Seeder e factory',
        'resources/views' => 'Viste Blade',
    ] as $prefisso => $nome) {
        if (str_starts_with($p, $prefisso)) {
            return $nome;
        }
    }
    if (str_starts_with($p, 'resources/js/')) {
        $parti = explode('/', substr($p, 13));
        if (count($parti) === 1) {
            return 'Bootstrap JS';
        }

        return ['Pages' => 'Pagine Vue', 'Components' => 'Componenti Vue', 'Layouts' => 'Layout Vue', 'Composables' => 'Composables'][$parti[0]] ?? 'Supporto JS';
    }
    $parti = explode('/', $p);
    $cartella = count($parti) > 2 ? $parti[1] : 'Infrastruttura';

    return [
        'Mail' => 'Code e Mail', 'Jobs' => 'Code e Mail', 'Events' => 'Infrastruttura', 'Listeners' => 'Infrastruttura',
        'Notifications' => 'Infrastruttura', 'Rules' => 'Infrastruttura', 'Exceptions' => 'Infrastruttura', 'Providers' => 'Infrastruttura',
    ][$cartella] ?? $cartella;
}

function file_sotto(string $dove, callable $filtro): array
{
    if (is_file($dove)) {
        return [$dove];
    }
    $trovati = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dove, FilesystemIterator::SKIP_DOTS)) as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        if ($filtro($p)) {
            $trovati[] = $p;
        }
    }
    sort($trovati);

    return $trovati;
}

function aggiungi(array &$nodi, string $p, ?string $nome = null): void
{
    $nodi[$p] = [
        'id' => $p,
        'name' => $nome ?? explode('.', basename($p))[0],
        'layer' => strato($p),
        'loc' => substr_count((string) file_get_contents($p), "\n") + 1,
    ];
}

// ---------------------------------------------------------------- PHP

final class Raccoglitore extends NodeVisitorAbstract
{
    public array $classi = [];

    public array $astratte = [];

    public array $nomi = [];

    public array $tratti = [];

    /** @var list<array{string, string}> valore e contesto: "f:view", "m:route", "s:render", "p:$view"... */
    public array $stringhe = [];

    public ?string $firma = null;

    private function contesto(Node $n): string
    {
        $p = $n->getAttribute('parent');
        if ($p instanceof Node\Arg) {
            $p = $p->getAttribute('parent');
        }
        if ($p instanceof Node\Expr\FuncCall && $p->name instanceof Node\Name) {
            return 'f:'.strtolower($p->name->getLast());
        }
        if ($p instanceof Node\Expr\StaticCall && $p->name instanceof Node\Identifier) {
            return 's:'.strtolower($p->name->toString());
        }
        if (($p instanceof Node\Expr\MethodCall || $p instanceof Node\Expr\NullsafeMethodCall) && $p->name instanceof Node\Identifier) {
            return 'm:'.strtolower($p->name->toString());
        }
        if ($p instanceof Node\PropertyItem) {
            return 'p:$'.strtolower($p->name->toString());
        }

        return '';
    }

    public function enterNode(Node $n)
    {
        if ($n instanceof Node\Stmt\ClassLike && $n->namespacedName !== null) {
            $this->classi[] = $n->namespacedName->toString();
            if ($n instanceof Node\Stmt\Class_ && $n->isAbstract()) {
                $this->astratte[] = $n->namespacedName->toString();
            }
        }
        if ($n instanceof Node\Stmt\TraitUse) {
            foreach ($n->traits as $t) {
                $this->tratti[] = $t->toString();
            }
        }
        if ($n instanceof Node\Name) {
            $p = $n->getAttribute('parent');
            $dichiarazione = $p instanceof Node\Stmt\Namespace_ || $p instanceof Node\UseItem || $p instanceof Node\Stmt\GroupUse;
            $funzione = ($p instanceof Node\Expr\FuncCall && $p->name === $n) || $p instanceof Node\Expr\ConstFetch;
            if (! $dichiarazione && ! $funzione && ! $n->isSpecialClassName()) {
                $this->nomi[$n->toString()] = true;
            }
        }
        if ($n instanceof Node\Scalar\String_) {
            $c = $this->contesto($n);
            $this->stringhe[] = [$n->value, $c];
            if ($c === 'p:$signature') {
                $this->firma = preg_split('/\s+/', trim($n->value))[0];
            }
        }
        if ($n instanceof Node\Attribute && $n->name->getLast() === 'AsCommand') {
            foreach ($n->args as $a) {
                if ($a->value instanceof Node\Scalar\String_ && ($a->name === null || $a->name->toString() === 'name')) {
                    $this->firma = $a->value->value;
                    break;
                }
            }
        }

        return null;
    }
}

$parser = (new ParserFactory)->createForHostVersion();
$php = [];
$fqcn = [];
foreach (['app', 'routes', 'config', 'database', 'tests', 'bootstrap/app.php', 'bootstrap/providers.php'] as $dove) {
    foreach (file_sotto($dove, fn ($p) => str_ends_with($p, '.php') && ! str_ends_with($p, '.blade.php') && ! str_contains($p, '/Fixtures/')) as $p) {
        $t = new NodeTraverser(new ParentConnectingVisitor, new NameResolver(null, ['replaceNodes' => true]));
        $ast = $t->traverse($parser->parse((string) file_get_contents($p)) ?? []);
        $r = new Raccoglitore;
        (new NodeTraverser(new ParentConnectingVisitor, $r))->traverse($ast);
        $php[$p] = $r;
        aggiungi($nodi, $p, $r->classi ? basename(str_replace('\\', '/', $r->classi[0])) : null);
        foreach ($r->classi as $c) {
            $fqcn[$c] = $p;
        }
    }
}

$js = file_sotto('resources/js', fn ($p) => preg_match('/\.(js|vue)$/', $p) === 1);
$blade = file_sotto('resources/views', fn ($p) => str_ends_with($p, '.blade.php'));
foreach ($js as $p) {
    aggiungi($nodi, $p);
}
foreach ($blade as $p) {
    aggiungi($nodi, $p, basename($p, '.blade.php'));
}
$tutti = array_keys($nodi);

$pagine = [];
foreach ($js as $p) {
    if (preg_match('#^resources/js/Pages/(.+)\.vue$#', $p, $m)) {
        $pagine[$m[1]] = $p;
    }
}
$viste = [];
foreach ($blade as $p) {
    $viste[str_replace('/', '.', substr($p, 16, -10))] = $p;
}
$comandi = [];
foreach ($php as $p => $r) {
    if ($r->firma !== null) {
        $comandi[$r->firma] = $p;
    }
}
$rotte = [];
$indirizzi = [];
foreach (Route::getRoutes() as $rotta) {
    $classe = explode('@', $rotta->getActionName())[0];
    if (! isset($fqcn[$classe])) {
        continue;
    }
    if ($rotta->getName()) {
        $rotte[$rotta->getName()] = $fqcn[$classe];
    }
    if (! str_contains($rotta->uri(), '{')) {
        $indirizzi['/'.ltrim($rotta->uri(), '/')] = $fqcn[$classe];
    }
}
$nomiRotte = array_flip(array_filter(array_map(fn ($r) => $r->getName(), iterator_to_array(Route::getRoutes()))));

// ---------------------------------------------------------------- legami

$problemi = [];
$lega = function (string $da, string $a, string $tipo) use (&$legami, $nodi) {
    if ($da !== $a && isset($nodi[$a])) {
        $legami[$da][$a][$tipo] = true;
    }
};
$rotto = function (string $da, string $cosa, string $valore) use (&$problemi) {
    $problemi[] = "{$da}: {$cosa} «{$valore}» non esiste";
};
$percorso = function (string $s) use ($tutti): ?string {
    $s = ltrim(ltrim($s, '/'), './');
    if (strlen($s) < 5 || ! preg_match('/\.(php|json|js|vue|css)$/', $s)) {
        return null;
    }
    $c = array_values(array_filter($tutti, fn ($f) => $f === $s || str_ends_with($f, '/'.$s)));

    return count($c) === 1 ? $c[0] : null;
};

foreach ($php as $p => $r) {
    $prova = strato($p) === 'Test';
    foreach (array_keys($r->nomi) as $n) {
        if (isset($fqcn[$n])) {
            $lega($p, $fqcn[$n], 'uso');
        } elseif (str_starts_with($n, 'App\\') && ! class_exists($n) && ! interface_exists($n) && ! trait_exists($n) && ! enum_exists($n)) {
            $rotto($p, 'la classe', $n);
        }
    }
    foreach ($r->stringhe as [$s, $c]) {
        if (isset($fqcn[ltrim($s, '\\')])) {
            $lega($p, $fqcn[ltrim($s, '\\')], 'uso');
        }
        if (isset($viste[$s]) && (str_contains($s, '.') || in_array($c, ['f:view', 'p:$view', 'p:$rootview'], true))) {
            $lega($p, $viste[$s], 'vista');
        } elseif (! $prova && in_array($c, ['f:view', 'p:$view'], true) && preg_match('/^[\w-]+(\.[\w-]+)+$/', $s)) {
            $rotto($p, 'la vista', $s);
        }
        $primo = explode(' ', $s)[0];
        if (str_contains($primo, ':') && isset($comandi[$primo])) {
            $lega($p, $comandi[$primo], 'comando');
        }
        if (in_array($c, ['f:route', 'f:to_route', 'm:route', 's:route', 'm:signedroute', 'm:temporarysignedroute', 'm:routeis'], true) && isset($rotte[$s])) {
            $lega($p, $rotte[$s], 'rotta');
        }
        if (! $prova && in_array($c, ['f:route', 'f:to_route'], true) && preg_match('/^[\w.-]+$/', $s) && ! isset($nomiRotte[$s])) {
            $rotto($p, 'la rotta', $s);
        }
        if ($c === 'f:config' && preg_match('/^[\w-]+(\.|$)/', $s) && isset($nodi['config/'.explode('.', $s)[0].'.php'])) {
            $lega($p, 'config/'.explode('.', $s)[0].'.php', 'config');
        }
        if (str_starts_with($p, 'app/') || str_starts_with($p, 'routes/') || str_starts_with($p, 'bootstrap/')) {
            if (isset($pagine[$s])) {
                $lega($p, $pagine[$s], 'pagina');
            } elseif ($c === 's:render' && preg_match('#^[A-Z]\w*(/\w+)+$#', $s)) {
                $rotto($p, 'la pagina Vue', $s);
            }
        }
        if (($f = $percorso($s)) !== null) {
            $lega($p, $f, 'file');
        }
    }
}

// I modelli di pagina: PageController mostra il componente che l'enum indica.
foreach (PageTemplate::cases() as $modello) {
    if (isset($pagine[$modello->componente()])) {
        $lega('app/Enums/PageTemplate.php', $pagine[$modello->componente()], 'pagina');
    } else {
        $rotto('app/Enums/PageTemplate.php', 'la pagina Vue', $modello->componente());
    }
}

$risolvi = function (string $base) use ($nodi): ?string {
    foreach (['', '.js', '.vue', '/index.js'] as $e) {
        if (isset($nodi[$base.$e])) {
            return $base.$e;
        }
    }

    return null;
};
$normalizza = function (string $p): string {
    $out = [];
    foreach (explode('/', $p) as $pezzo) {
        if ($pezzo === '..') {
            array_pop($out);
        } elseif ($pezzo !== '.' && $pezzo !== '') {
            $out[] = $pezzo;
        }
    }

    return implode('/', $out);
};
foreach ($js as $p) {
    $src = (string) file_get_contents($p);
    $codice = preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $src);
    $script = str_ends_with($p, '.vue') && preg_match_all('#<script[^>]*>(.*?)</script>#s', $codice, $m) ? implode("\n", $m[1]) : $codice;
    preg_match_all('/\b(?:import|export)\s[^\'";]*?\bfrom\s*[\'"]([^\'"]+)[\'"]|\bimport\s*\(?\s*[\'"]([^\'"]+)[\'"]|\bvi\.(?:mock|importActual)\(\s*[\'"]([^\'"]+)[\'"]/', $script, $m, PREG_SET_ORDER);
    foreach ($m as $x) {
        $s = $x[1] ?: ($x[2] ?? '') ?: ($x[3] ?? '');
        if (str_starts_with($s, '@/')) {
            $base = 'resources/js/'.substr($s, 2);
        } elseif (str_starts_with($s, '.')) {
            $base = $normalizza(dirname($p).'/'.$s);
        } else {
            continue;
        }
        if (($t = $risolvi($base)) !== null) {
            $lega($p, $t, 'import');
        } elseif (! file_exists($base) && ! file_exists($base.'.js') && ! file_exists($base.'.vue')) {
            // CSS, JSON e pacchetti in vendor esistono ma non sono nodi della mappa.
            $rotto($p, "l'import", $s);
        }
    }
    preg_match_all('/import\.meta\.glob\(\s*[\'"]([^\'"]+)[\'"]/', $script, $m);
    foreach ($m[1] as $g) {
        $modello = $normalizza(dirname($p).'/'.$g);
        foreach ($js as $f) {
            if (fnmatch($modello, $f)) {
                $lega($p, $f, 'glob');
            }
        }
    }
    preg_match_all('/\broute\(\s*[\'"]([\w.-]+)[\'"]/', $codice, $m);
    foreach ($m[1] as $s) {
        isset($rotte[$s]) ? $lega($p, $rotte[$s], 'rotta') : (isset($nomiRotte[$s]) || strato($p) === 'Test' ? null : $rotto($p, 'la rotta', $s));
    }
    preg_match_all('#[\'"`](/[a-z0-9/_-]+)[\'"`]#', $codice, $m);
    foreach ($m[1] as $s) {
        if (isset($indirizzi[$s])) {
            $lega($p, $indirizzi[$s], 'rotta');
        }
    }
}
foreach ($blade as $p) {
    $src = preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($p));
    preg_match_all('/@(?:include|includeIf|includeWhen|includeUnless|includeFirst|extends|component|each)\s*\(\s*(?:[^,\'"()]+,\s*)?[\'"]([^\'"]+)[\'"]/', $src, $m);
    foreach ($m[1] as $s) {
        isset($viste[$s]) ? $lega($p, $viste[$s], 'vista') : (str_contains($s, '::') ? null : $rotto($p, 'la vista', $s));
    }
    preg_match_all('/<x-([\w.-]+)/', $src, $m);
    foreach ($m[1] as $s) {
        if (isset($viste['components.'.$s])) {
            $lega($p, $viste['components.'.$s], 'vista');
        }
    }
    preg_match_all('/[\'"]([^\'"]+\.(?:js|css|vue|php))[\'"]/', $src, $m);
    foreach ($m[1] as $s) {
        if (($f = $percorso($s)) !== null) {
            $lega($p, $f, 'file');
        }
    }
    preg_match_all('/\\\\?(App\\\\[A-Za-z0-9_\\\\]+)/', $src, $m);
    foreach ($m[1] as $s) {
        if (isset($fqcn[$s])) {
            $lega($p, $fqcn[$s], 'uso');
        }
    }
    preg_match_all('/\broute\(\s*[\'"]([\w.-]+)[\'"]/', $src, $m);
    foreach ($m[1] as $s) {
        isset($rotte[$s]) ? $lega($p, $rotte[$s], 'rotta') : (isset($nomiRotte[$s]) ? null : $rotto($p, 'la rotta', $s));
    }
}

// Convenzioni del framework: file che nessuno nomina ma che Laravel e Filament caricano.
$pannello = 'app/Providers/Filament/AdminPanelProvider.php';
foreach ($php as $p => $r) {
    $concrete = array_values(array_diff($r->classi, $r->astratte));
    if (! $concrete) {
        continue;
    }
    if (str_starts_with($p, 'app/Filament/Resources/') && str_ends_with($concrete[0], 'Resource')) {
        $lega($pannello, $p, 'scoperta');
    }
    foreach (['app/Filament/Pages/', 'app/Filament/Widgets/', 'app/Filament/Clusters/'] as $dir) {
        if (str_starts_with($p, $dir)) {
            $lega($pannello, $p, 'scoperta');
        }
    }
    if (str_starts_with($p, 'app/Console/Commands/') || str_starts_with($p, 'app/Listeners/')) {
        $lega('bootstrap/app.php', $p, 'scoperta');
    }
    if (preg_match('#^app/Models/(\w+)\.php$#', $p, $m)) {
        $lega($p, "app/Policies/{$m[1]}Policy.php", 'convenzione');
        if (in_array('Illuminate\\Database\\Eloquent\\Factories\\HasFactory', $r->tratti, true)) {
            $lega($p, "database/factories/{$m[1]}Factory.php", 'convenzione');
        }
    }
}
foreach ($tutti as $p) {
    if (str_starts_with($p, 'config/')) {
        $lega('bootstrap/app.php', $p, 'scoperta');
    }
}
$inertia = 'app/Http/Middleware/HandleInertiaRequests.php';
if (isset($php[$inertia]) && ! in_array('p:$rootview', array_column($php[$inertia]->stringhe, 1), true)) {
    $lega($inertia, 'resources/views/app.blade.php', 'vista');
}

// ---------------------------------------------------------------- raggiungibilita'

$uscite = [];
foreach ($legami as $da => $verso) {
    if ($nodi[$da]['layer'] !== 'Test') {
        $uscite[$da] = array_keys($verso);
    }
}
$coda = array_values(array_filter($tutti, fn ($p) => in_array($nodi[$p]['layer'], ['Rotte', 'Config e bootstrap', 'Migrazioni'], true) || str_starts_with($p, 'database/seeders/')));
$visti = array_fill_keys($coda, true);
while ($coda) {
    foreach ($uscite[array_shift($coda)] ?? [] as $b) {
        if (! isset($visti[$b])) {
            $visti[$b] = true;
            $coda[] = $b;
        }
    }
}
foreach ($tutti as $p) {
    if (! isset($visti[$p]) && $nodi[$p]['layer'] !== 'Test' && ! isset(ECCEZIONI[$p])) {
        $problemi[] = "{$p}: nessun percorso lo raggiunge da rotte, config, bootstrap, migrazioni o seeder";
    }
}

// ---------------------------------------------------------------- uscita

$grado = [];
$elenco = [];
foreach ($legami as $da => $verso) {
    foreach ($verso as $a => $tipi) {
        $tipi = array_keys($tipi);
        sort($tipi);
        $elenco[] = ['source' => $da, 'target' => $a, 'k' => $tipi];
        if ($tipi !== ['glob']) {
            $grado[$da] = ($grado[$da] ?? 0) + 1;
            $grado[$a] = ($grado[$a] ?? 0) + 1;
        }
    }
}
$grafo = ['nodes' => [], 'links' => $elenco];
foreach ($nodi as $p => $n) {
    $grafo['nodes'][] = $n + ['deg' => $grado[$p] ?? 0, 'reach' => isset($visti[$p]) || $n['layer'] === 'Test' || isset(ECCEZIONI[$p])];
}
$json = json_encode($grafo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
if (isset($opzioni['json'])) {
    file_put_contents($opzioni['json'], $json);
}
if (isset($opzioni['html'])) {
    $modello = (string) file_get_contents(__DIR__.'/mappa-dipendenze.html');
    file_put_contents($opzioni['html'], str_replace('__DATA__', $json, $modello));
}

$problemi = array_values(array_unique($problemi));
fwrite(STDOUT, sprintf("%d file, %d legami, %d problemi\n", count($nodi), count($elenco), count($problemi)));
foreach ($problemi as $riga) {
    fwrite(STDOUT, "  - {$riga}\n");
}
exit($problemi ? 1 : 0);
