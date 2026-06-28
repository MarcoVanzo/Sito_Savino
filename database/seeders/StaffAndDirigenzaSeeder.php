<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StaffMember;
use App\Enums\StaffType;

class StaffAndDirigenzaSeeder extends Seeder
{
    public function run(): void
    {
        $dirigenza = [
            ['Sergio', 'Bazzurro', 'Presidente', StaffType::Dirigenza],
            ['Luciano', 'Ciofi', 'Vice Presidente', StaffType::Dirigenza],
            ['Sandra', 'Leoncini', 'Consigliere', StaffType::Dirigenza],
            ['Francesco', 'Paoletti', 'Direttore Generale', StaffType::Dirigenza],
            ['Gabriele', 'Marchi', 'Responsabile Segreteria Organizzativa', StaffType::Dirigenza],
            ['Roberto', 'Zaffina', 'Safeguarding Officer', StaffType::Dirigenza],
            ['Giuseppe', 'Tonini', 'Responsabile Marketing', StaffType::Dirigenza],
            ['Veronica', 'Angeloni', 'Resp. Relazioni Esterne & Brand Ambassador', StaffType::Dirigenza],
            ['Fabio', 'Ferri', 'Press Manager', StaffType::Dirigenza],
            ['Gherardo', 'Dardanelli', 'Social Media Manager', StaffType::Dirigenza],
            ['Maurizio', 'Anatrini', 'Fotografo', StaffType::Dirigenza],
        ];

        $staffTecnico = [
            ['Marco', 'Gaspari', 'Primo Allenatore', StaffType::Tecnico],
            ['Sándor', 'Kántor', 'Vice Allenatore', StaffType::Tecnico],
            ['Mattia', 'Cozzi', 'Terzo Allenatore', StaffType::Tecnico],
            ['Simone', 'Maurilli', 'Scoutman', StaffType::Tecnico],
            ['Marco', 'Sesia', 'Preparatore Atletico', StaffType::Tecnico],
            ['Andrea', 'Panzeri', 'Sparring Partner', StaffType::Tecnico],
        ];

        $staffMedico = [
            ['Eligio', 'Cavalli', 'Medico', StaffType::Medico],
            ['Monica', 'Fabbri', 'Medico', StaffType::Medico],
            ['Sebastiano', 'Cencini', 'Responsabile Fisioterapia', StaffType::Medico],
            ['Gioele', 'Corti', 'Fisioterapista', StaffType::Medico],
            ['Matteo', 'Gori', 'Osteopata', StaffType::Medico],
            ['Christian', 'Petri', 'Nutrizionista', StaffType::Medico],
            ['Alessandra', 'Simone', 'Assistente Nutrizionista', StaffType::Medico],
        ];

        $allStaff = array_merge($dirigenza, $staffTecnico, $staffMedico);

        $sortOrder = 1;
        foreach ($allStaff as $person) {
            StaffMember::updateOrCreate(
                ['first_name' => $person[0], 'last_name' => $person[1]],
                [
                    'role' => $person[2],
                    'type' => $person[3],
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }
}
