<?php

namespace Tests\Unit\Support;

use App\Support\Diretta\CicloDellaCornice;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CicloDellaCorniceTest extends TestCase
{
    private function ora(string $hhmmss): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-18 '.$hhmmss, 'Europe/Rome');
    }

    #[Test]
    public function i_primi_cicli_partono_dall_inizio_ogni_x_minuti(): void
    {
        $istanti = CicloDellaCornice::primiIstanti($this->ora('17:05:00'), $this->ora('20:00:00'), 10);

        $this->assertSame(['17:05', '17:15', '17:25'], array_map(fn ($i) => $i->format('H:i'), $istanti));
    }

    #[Test]
    public function nessun_ciclo_comincia_dopo_la_fine(): void
    {
        $istanti = CicloDellaCornice::primiIstanti($this->ora('19:45:00'), $this->ora('20:00:00'), 10, 5);

        $this->assertSame(['19:45', '19:55'], array_map(fn ($i) => $i->format('H:i'), $istanti));
    }

    #[Test]
    public function il_prossimo_ciclo_e_quello_in_corso_o_il_successivo(): void
    {
        $inizio = $this->ora('17:05:00');
        $fine = $this->ora('20:00:00');

        $this->assertSame('17:05:00', CicloDellaCornice::prossimo($inizio, $fine, 10, 15, $this->ora('16:00:00'))['inizio']->format('H:i:s'));
        $this->assertSame(0, CicloDellaCornice::prossimo($inizio, $fine, 10, 15, $this->ora('17:05:10'))['numero']);
        $this->assertSame('17:15:00', CicloDellaCornice::prossimo($inizio, $fine, 10, 15, $this->ora('17:05:15'))['inizio']->format('H:i:s'));
        $this->assertNull(CicloDellaCornice::prossimo($inizio, $fine, 10, 15, $this->ora('19:55:20')));
    }

    /** Come nel browser: il ciclo cominciato prima della fine arriva in fondo. */
    #[Test]
    public function il_ciclo_cominciato_prima_della_fine_resta_in_corso_anche_dopo(): void
    {
        $prossimo = CicloDellaCornice::prossimo($this->ora('17:05:00'), $this->ora('19:55:10'), 10, 15, $this->ora('19:55:12'));

        $this->assertSame('19:55:00', $prossimo['inizio']->format('H:i:s'));
    }
}
