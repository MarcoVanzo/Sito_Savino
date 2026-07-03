<?php

namespace App\Filament\Columns;

use Filament\Tables;
use Illuminate\Support\HtmlString;

class AiScoreColumn
{
    public static function make(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('player.ai_face_examples')
            ->label('AI Score')
            ->formatStateUsing(function ($state) {
                $examples = (int) $state;

                if ($examples === 0) {
                    return new HtmlString(
                        '<span style="font-size:11px;color:#9ca3af">—</span>'
                    );
                }

                $score = match (true) {
                    $examples >= 6 => 100,
                    $examples === 5 => 85,
                    $examples === 4 => 70,
                    $examples === 3 => 55,
                    $examples === 2 => 35,
                    $examples === 1 => 15,
                    default => 0,
                };

                $color = match (true) {
                    $score >= 85 => '#22c55e',
                    $score >= 55 => '#eab308',
                    $score >= 15 => '#f97316',
                    default => '#ef4444',
                };

                $label = match (true) {
                    $score >= 85 => 'Ottimo',
                    $score >= 55 => 'Buono',
                    $score >= 15 => 'Scarso',
                    default => '—',
                };

                return new HtmlString(
                    '<div style="min-width:80px">' .
                    '<div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px">' .
                    '<span>' . $label . '</span><span style="font-weight:600">' . $score . '/100</span></div>' .
                    '<div style="background:#374151;border-radius:9999px;height:6px;overflow:hidden">' .
                    '<div style="width:' . $score . '%;height:100%;background:' . $color . ';border-radius:9999px;transition:width 0.3s"></div>' .
                    '</div></div>'
                );
            })
            ->html()
            ->tooltip(fn ($record) => match ((int) $record->player->ai_face_examples) {
                0 => 'Nessuna foto analizzata. Clicca "Addestra AI".',
                1 => '1 foto analizzata. Carica più foto per migliorare.',
                default => $record->player->ai_face_examples . ' foto analizzate.',
            })
            ->sortable();
    }
}
