<?php

namespace App\Console\Commands;

use App\Filament\Traits\HasFaceSyncMethods;
use App\Models\Player;
use App\Models\Roster;
use App\Models\StaffMember;
use App\Services\FacialRecognitionService;
use Illuminate\Console\Command;

class RetrainFacesCommand extends Command
{
    use HasFaceSyncMethods;

    protected $signature = 'faces:retrain';

    protected $description = 'Resetta e riaddestra il modello facciale solo con i ritratti ufficiali';

    public function handle(FacialRecognitionService $service)
    {
        $this->info('Inizio procedura di reset e riaddestramento volti AI...');

        // 1. Azzeriamo i player
        $players = Player::all();
        foreach ($players as $player) {
            $this->info("Reset AI Player ID: {$player->id}");
            $service->deleteAllSubjectExamples($player);
            $player->update(['ai_face_examples' => 0]);

            // Trova l'ultimo roster per sincronizzare l'ufficiale
            $roster = Roster::where('player_id', $player->id)->latest()->first();
            if ($roster) {
                $mediaList = $this->getMediaForSync($roster->id);
                foreach ($mediaList as $m) {
                    $this->syncSinglePhoto($roster->id, $m['id']);
                    $this->info("  -> Foto id {$m['id']} inviata");
                }
            } else {
                // Fallback su avatar player
                $media = $player->getFirstMedia('players');
                if ($media) {
                    $service->addFaceExampleFromMedia($player, $media);
                    $player->increment('ai_face_examples');
                    $this->info('  -> Avatar base inviato');
                }
            }
        }

        // 2. Azzeriamo lo staff
        $staffs = StaffMember::all();
        foreach ($staffs as $staff) {
            $this->info("Reset AI Staff ID: {$staff->id}");
            $service->deleteAllSubjectExamples($staff);

            $media = $staff->getFirstMedia('staff');
            if ($media) {
                $service->addFaceExampleFromMedia($staff, $media);
                $this->info('  -> Foto staff inviata');
            }
        }

        $this->info('Reset e riaddestramento completati.');
    }
}
