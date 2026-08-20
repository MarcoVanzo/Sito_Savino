<?php

/*
 * Traduzioni inglesi dei contenuti gestiti dal CMS.
 *
 * Gran parte del sito era pubblicata solo in italiano: dove la scheda inglese
 * era stata compilata, lo era copiando il testo italiano. Il risultato è che
 * /en mostrava contenuti italiani. Questa mappa alimenta il comando
 * `content:translate-missing`, che riempie le sole chiavi `en` lasciando
 * intatto l'italiano e senza mai toccare una traduzione già fatta in redazione.
 *
 * Due modi di individuare la riga da tradurre:
 *
 * - `byText`: si cerca il testo italiano esatto, HTML compreso. Va bene per
 *   valori brevi e ripetuti (nomi prodotto, ruoli, categorie). Se la redazione
 *   cambia l'italiano dal CMS, il comando segnala la voce come mancante.
 * - `byKey`: si cerca la riga per una sua colonna identificativa (lo slug) e si
 *   scrive la traduzione dei campi elencati. Serve per i testi lunghi delle
 *   pagine, dove pretendere il match esatto di centinaia di caratteri di HTML
 *   sarebbe fragile.
 */

return [
    'byText' => [
        'products.name' => [
            ['it' => 'Cappellino', 'en' => 'Cap'],
            ['it' => 'Champions maglia gara Antropova #17', 'en' => 'Champions match jersey Antropova #17'],
            ['it' => 'Champions maglia gara Bechis #3', 'en' => 'Champions match jersey Bechis #3'],
            ['it' => 'Champions maglia gara Bosetti #9', 'en' => 'Champions match jersey Bosetti #9'],
            ['it' => 'Champions maglia gara Castillo #5', 'en' => 'Champions match jersey Castillo #5'],
            ['it' => 'Champions maglia gara Franklin #7', 'en' => 'Champions match jersey Franklin #7'],
            ['it' => 'Champions maglia gara Graziani #13', 'en' => 'Champions match jersey Graziani #13'],
            ['it' => 'Champions maglia gara Maja #10', 'en' => 'Champions match jersey Maja #10'],
            ['it' => 'Champions maglia gara Mancini #11', 'en' => 'Champions match jersey Mancini #11'],
            ['it' => 'Champions maglia gara Nwakalor #14', 'en' => 'Champions match jersey Nwakalor #14'],
            ['it' => 'Champions maglia gara Ribechi #8', 'en' => 'Champions match jersey Ribechi #8'],
            ['it' => 'Champions maglia gara Ruddins #6', 'en' => 'Champions match jersey Ruddins #6'],
            ['it' => 'Champions maglia gara Skinner #4', 'en' => 'Champions match jersey Skinner #4'],
            ['it' => 'Champions maglia gara Traballi #2', 'en' => 'Champions match jersey Traballi #2'],
            ['it' => 'Champions maglia gara Weitzel #21', 'en' => 'Champions match jersey Weitzel #21'],
            ['it' => 'Felpa blu donna con dettaglio azzurro', 'en' => "Women's blue sweatshirt with light blue detail"],
            ['it' => 'Felpa blu donna con trama', 'en' => "Women's blue textured sweatshirt"],
            ['it' => 'Felpa blu uomo con dettaglio azzurro', 'en' => "Men's blue sweatshirt with light blue detail"],
            ['it' => 'Gift Card € 100', 'en' => 'Gift Card € 100'],
            ['it' => 'Gift Card € 200', 'en' => 'Gift Card € 200'],
            ['it' => 'Gift Card € 300', 'en' => 'Gift Card € 300'],
            ['it' => 'Jersey Limited Edition Antropova Pink #17', 'en' => 'Jersey Limited Edition Antropova Pink #17'],
            ['it' => 'Jersey Limited Edition Bechis Pink #3', 'en' => 'Jersey Limited Edition Bechis Pink #3'],
            ['it' => 'Jersey Limited Edition Graziani Pink #13', 'en' => 'Jersey Limited Edition Graziani Pink #13'],
            ['it' => 'Jersey Limited Edition Maja Pink #10', 'en' => 'Jersey Limited Edition Maja Pink #10'],
            ['it' => 'Jersey Limited Edition Nwakalor Pink #14', 'en' => 'Jersey Limited Edition Nwakalor Pink #14'],
            ['it' => 'Maglia gara Antropova #17', 'en' => 'Match jersey Antropova #17'],
            ['it' => 'Maglia gara Bechis #3', 'en' => 'Match jersey Bechis #3'],
            ['it' => 'Maglia gara Bosetti #9', 'en' => 'Match jersey Bosetti #9'],
            ['it' => 'Maglia gara Castillo #5', 'en' => 'Match jersey Castillo #5'],
            ['it' => 'Maglia gara Franklin #7', 'en' => 'Match jersey Franklin #7'],
            ['it' => 'Maglia gara Graziani #13', 'en' => 'Match jersey Graziani #13'],
            ['it' => 'Maglia gara Mancini #11', 'en' => 'Match jersey Mancini #11'],
            ['it' => 'Maglia gara Nwakalor #14', 'en' => 'Match jersey Nwakalor #14'],
            ['it' => 'Maglia gara Ognjenovic #10', 'en' => 'Match jersey Ognjenovic #10'],
            ['it' => 'Maglia gara Ribechi #8', 'en' => 'Match jersey Ribechi #8'],
            ['it' => 'Maglia gara Ruddins #6', 'en' => 'Match jersey Ruddins #6'],
            ['it' => 'Maglia gara Skinner #4', 'en' => 'Match jersey Skinner #4'],
            ['it' => 'Maglia gara Traballi #2', 'en' => 'Match jersey Traballi #2'],
            ['it' => 'Maglia gara Weitzel #21', 'en' => 'Match jersey Weitzel #21'],
            ['it' => 'Notebook', 'en' => 'Notebook'],
            ['it' => 'Original Maglia Gara Antropova #17', 'en' => 'Original Match Jersey Antropova #17'],
            ['it' => 'Original Maglia Gara Bosetti #9', 'en' => 'Original Match Jersey Bosetti #9'],
            ['it' => 'Original Maglia Gara Castillo #5', 'en' => 'Original Match Jersey Castillo #5'],
            ['it' => 'Original Maglia Gara Graziani #13', 'en' => 'Original Match Jersey Graziani #13'],
            ['it' => 'Original Maglia Gara Maja #10', 'en' => 'Original Match Jersey Maja #10'],
            ['it' => 'Original Maglia Gara Nwakalor #14', 'en' => 'Original Match Jersey Nwakalor #14'],
            ['it' => 'Original Maglia Gara Skinner #4', 'en' => 'Original Match Jersey Skinner #4'],
            ['it' => 'Original Maglia Gara Weitzel #21', 'en' => 'Original Match Jersey Weitzel #21'],
            ['it' => 'Pallone Autografato 2024-25', 'en' => 'Signed Ball 2024-25'],
            ['it' => 'Pallone Autografato 2025/2026', 'en' => 'Signed Ball 2025/2026'],
            ['it' => 'Peluche SAVINO', 'en' => 'SAVINO Plush Toy'],
            ['it' => 'Portachiavi', 'en' => 'Keyring'],
            ['it' => 'Sciarpa Tifoso', 'en' => 'Supporter Scarf'],
            ['it' => 'T-Shirt Savino Mascotte', 'en' => 'Savino Mascot T-Shirt'],
            ['it' => 'T-shirt Allenamento', 'en' => 'Training T-shirt'],
            ['it' => 'T-shirt Final Four Champions League – Istanbul 2026', 'en' => 'Champions League Final Four T-shirt – Istanbul 2026'],
            ['it' => 'T-shirt celebrativa Mondiale per Club', 'en' => 'Club World Championship celebration T-shirt'],
            ['it' => 'T-shirt tifoso 2025', 'en' => 'Supporter T-shirt 2025'],
            ['it' => 'Tazza', 'en' => 'Mug'],
            ['it' => 'Zaino', 'en' => 'Backpack'],
        ],

        'products.short_description' => [
            [
                'it' => '<p>Savino Del Bene Volley Maglia gioco ufficiale blu CEV Champions League Volley 2025/2026</p>',
                'en' => '<p>Savino Del Bene Volley official blue match jersey, CEV Champions League Volley 2025/2026</p>',
            ],
            [
                'it' => '<p>Savino Del Bene Volley Maglia gioco ufficiale gialla CEV Champions League Volley 2025/2026</p>',
                'en' => '<p>Savino Del Bene Volley official yellow match jersey, CEV Champions League Volley 2025/2026</p>',
            ],
            [
                'it' => '<p>Tessuto tecnico Oeko-Tex, lavaggio a 30°</p>',
                'en' => '<p>Oeko-Tex technical fabric, machine wash at 30°</p>',
            ],
            [
                'it' => '<p>Savino Del Bene Volley Maglia gioco ufficiale, bianca gara away, indossata dalla giocatrice durante la stagione 2025/2026.</p>',
                'en' => '<p>Savino Del Bene Volley official match jersey, white away kit, worn by the player during the 2025/2026 season.</p>',
            ],
            [
                'it' => '<p>Savino Del Bene Volley Maglia gioco ufficiale, bianca gara away, indossata dalla giocatrice durante la stagione 2025/2026</p>',
                'en' => '<p>Savino Del Bene Volley official match jersey, white away kit, worn by the player during the 2025/2026 season</p>',
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026.</p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season.</p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => '<p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026.</p>',
                'en' => '<p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season.</p>',
            ],
            [
                'it' => '<p>Asta solidale a sostegno di <a href="https://www.artemisiacentroantiviolenza.it/">Artemisia &#8211; Centro Antiviolenza Firenze</a></p>',
                'en' => '<p>Charity auction in support of <a href="https://www.artemisiacentroantiviolenza.it/">Artemisia &#8211; Centro Antiviolenza Firenze</a></p>',
            ],
            [
                'it' => '<p>T-shirt con stampa sul fronte della nostra divertente mascotte Savino.</p>',
                'en' => '<p>T-shirt with our fun mascot Savino printed on the front.</p>',
            ],
        ],

        'products.description' => [
            [
                'it' => <<<'IT'
                    <p><strong>Campionato MiniVolley e Attività Scolastiche.</strong></p>
                    <p>Modello Mikasa VS123W</p>
                    <p>Peso e circonferenza regolamentare (peso 220 grammi)</p>
                    <p>Copertura in EVA.</p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>MiniVolley Championship and school activities.</strong></p>
                    <p>Mikasa VS123W model</p>
                    <p>Regulation weight and circumference (weight 220 grams)</p>
                    <p>EVA cover.</p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Notebook Savino Del Bene Volley !</strong></p>
                    <p>Porta sempre con te la passione per la tua squadra preferita con il notebook<strong> Savino Del Bene Volley</strong>!</p>
                    <p>Un design elegante e minimalista, impreziosito dal logo ufficiale della squadra.</p>
                    <p>Pagine a righe per prendere i propri appunti!</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley Notebook!</strong></p>
                    <p>Take your passion for your favourite team everywhere with the<strong> Savino Del Bene Volley</strong> notebook!</p>
                    <p>An elegant, minimalist design enhanced by the official team logo.</p>
                    <p>Ruled pages for all your notes!</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Portachiavi!</strong></p>
                    <p>Portachiavi sagomato a forma di t shirt da pallavolo, con interno stampato ed esterno in plastica trasparente. Personalizzato con la maglietta della tua giocatrice preferita!</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Keyring!</strong></p>
                    <p>Keyring shaped like a volleyball shirt, with a printed insert and a clear plastic outer shell. Personalised with your favourite player's jersey!</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Savino Del Bene Volley Jersey Limited Edition 2025-2026</strong></p>
                    <p>Maglia Collezione pre-season <strong>indossata dalla giocatrice ed autografata</strong>.</p>
                    <p>L&#8217;intero importo verrà devoluto a <a href="https://www.artemisiacentroantiviolenza.it/">Artemisia &#8211; Centro Antiviolenza Firenze</a></p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley Jersey Limited Edition 2025-2026</strong></p>
                    <p>Pre-season collection jersey <strong>worn and signed by the player</strong>.</p>
                    <p>The entire amount will be donated to <a href="https://www.artemisiacentroantiviolenza.it/">Artemisia &#8211; Centro Antiviolenza Firenze</a></p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Savino Del Bene Volley Maglia gioco ufficiale blu CEV Champions League Volley 2025/2026</strong></p>
                    <p>La maglia presenta un design ricercato e dinamico che si combina al tessuto tecnico per una resa tecnica efficace e duratura.</p>
                    <p>Realizzata per le tifose e le giocatrici, la maglia gara Savino Del Bene Volley <strong>combina comfort, femminilità, qualità e la passione per la tua squadra del cuore nel maggior campionato europeo.</strong></p>
                    <ul>
                    <li>Composizione 18% elastan e 82% poliestere</li>
                    <li>Lavabile in lavatrice a 30°</li>
                    </ul>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley official blue match jersey, CEV Champions League Volley 2025/2026</strong></p>
                    <p>The jersey features a refined, dynamic design combined with technical fabric for effective, long-lasting performance.</p>
                    <p>Made for fans and players alike, the Savino Del Bene Volley match jersey <strong>combines comfort, femininity, quality and your passion for your favourite team in Europe's top competition.</strong></p>
                    <ul>
                    <li>18% elastane and 82% polyester</li>
                    <li>Machine washable at 30°</li>
                    </ul>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Savino Del Bene Volley Maglia gioco ufficiale gialla CEV Champions League Volley 2025/2026</strong></p>
                    <p>La maglia presenta un design ricercato e dinamico che si combina al tessuto tecnico per una resa tecnica efficace e duratura.</p>
                    <p>Realizzata per le tifose e le giocatrici, la maglia gara Savino Del Bene Volley <strong>combina comfort, femminilità, qualità e la passione per la tua squadra del cuore nel maggior campionato europeo.</strong></p>
                    <ul>
                    <li>Composizione 18% elastan e 82% poliestere</li>
                    <li>Lavabile in lavatrice a 30°</li>
                    </ul>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley official yellow match jersey, CEV Champions League Volley 2025/2026</strong></p>
                    <p>The jersey features a refined, dynamic design combined with technical fabric for effective, long-lasting performance.</p>
                    <p>Made for fans and players alike, the Savino Del Bene Volley match jersey <strong>combines comfort, femininity, quality and your passion for your favourite team in Europe's top competition.</strong></p>
                    <ul>
                    <li>18% elastane and 82% polyester</li>
                    <li>Machine washable at 30°</li>
                    </ul>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Savino Del Bene Volley Maglia gioco ufficiale, bianca gara away, indossata dalla giocatrice durante la stagione 2025/2026.</strong></p>
                    <p><strong>Nuova stagione, nuove emozioni, una nuova pelle!</strong></p>
                    <p>La base bianca è impreziosita da una skin texture che si fonde con il simbolo araldico del giglio. Quest’ultimo, emblema condiviso con la città di Firenze, è collocato nella parte inferiore della canotta, a sottolineare il profondo legame tra il club e il suo territorio.</p>
                    <p>I dettagli ciliegia aggiungono un tocco di classe, mentre il grintoso blu, che contorna il collo a V e percorre i fianchi, dona carattere.</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley official match jersey, white away kit, worn by the player during the 2025/2026 season.</strong></p>
                    <p><strong>New season, new emotions, a new skin!</strong></p>
                    <p>The white base is enhanced by a skin texture that blends into the heraldic lily. The lily, an emblem shared with the city of Florence, sits on the lower part of the jersey, underlining the deep bond between the club and its territory.</p>
                    <p>Cherry-red details add a touch of class, while the bold blue outlining the V-neck and running along the sides gives it character.</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Savino Del Bene Volley Maglia gioco ufficiale, bianca gara away, indossata dalla giocatrice durante la stagione 2025/2026.</strong></p>
                    <p><strong>Nuova stagione, nuove emozioni, una nuova pelle!</strong></p>
                    <p>La base bianca è impreziosita da una skin texture che si fonde con il simbolo araldico del giglio. Quest’ultimo, emblema condiviso con la città di Firenze, è collocato nella parte inferiore della canotta, a sottolineare il profondo legame tra il club e il suo territorio.</p>
                    <p>I dettagli ciliegia aggiungono un tocco di classe, mentre il grintoso blu, che contorna il collo a V e percorre i fianchi, dona carattere.</p>
                    <ul>
                    <li>Composizione 18% elastan e 82% poliestere</li>
                    <li>Lavabile in lavatrice a 30°</li>
                    </ul>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley official match jersey, white away kit, worn by the player during the 2025/2026 season.</strong></p>
                    <p><strong>New season, new emotions, a new skin!</strong></p>
                    <p>The white base is enhanced by a skin texture that blends into the heraldic lily. The lily, an emblem shared with the city of Florence, sits on the lower part of the jersey, underlining the deep bond between the club and its territory.</p>
                    <p>Cherry-red details add a touch of class, while the bold blue outlining the V-neck and running along the sides gives it character.</p>
                    <ul>
                    <li>18% elastane and 82% polyester</li>
                    <li>Machine washable at 30°</li>
                    </ul>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Savino Del Bene Volley Maglia gioco ufficiale, rosa gara home, indossata dalla giocatrice durante la stagione 2025/2026.</strong></p>
                    <p><strong>Nuova stagione, nuove emozioni, una nuova pelle!</strong></p>
                    <p>La base rosa è impreziosita da una skin texture che si fonde con il simbolo araldico del giglio. Quest’ultimo, emblema condiviso con la città di Firenze, è collocato nella parte inferiore della canotta, a sottolineare il profondo legame tra il club e il suo territorio.</p>
                    <p>I dettagli in bianco aggiungono un tocco di classe, mentre il grintoso blu, che contorna il collo a V e percorre i fianchi, dona carattere.</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley official match jersey, pink home kit, worn by the player during the 2025/2026 season.</strong></p>
                    <p><strong>New season, new emotions, a new skin!</strong></p>
                    <p>The pink base is enhanced by a skin texture that blends into the heraldic lily. The lily, an emblem shared with the city of Florence, sits on the lower part of the jersey, underlining the deep bond between the club and its territory.</p>
                    <p>White details add a touch of class, while the bold blue outlining the V-neck and running along the sides gives it character.</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Sciarpa del Tifoso!</strong></p>
                    <p>Indossa lo spirito della tua squadra con la sciarpa del Tifoso 2025-2026.</p>
                    <p>Moderna e d’impatto, la sciarpa presenta su un lato la scritta<strong> Savino Del Bene Volley</strong> e dall&#8217;altra il motto<strong> Believe. </strong>L&#8217;unione di queste due scritte rappresenta la volontà di lottare insieme per ogni traguardo.</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Supporter Scarf!</strong></p>
                    <p>Wear your team's spirit with the 2025-2026 Supporter Scarf.</p>
                    <p>Modern and striking, the scarf carries the<strong> Savino Del Bene Volley</strong> name on one side and the motto<strong> Believe </strong>on the other. Together, the two stand for the will to fight side by side for every goal.</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Tazza Savino Del Bene Volley !</strong></p>
                    <p>Tazza personalizzata con logo della squadra e mascotte Savino Del Bene Volley.</p>
                    <p>Esterno di colore bianco, impreziosito dal logo ufficiale della squadra, e interno e manico di colore blu.</p>
                    <p>Porta sempre con te la passione per la tua squadra preferita con la tazza<strong> Savino Del Bene Volley</strong>!</p>
                    <p><strong>PLAY AS ONE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley Mug!</strong></p>
                    <p>Mug personalised with the team logo and the Savino Del Bene Volley mascot.</p>
                    <p>White on the outside, enhanced by the official team logo, with a blue interior and handle.</p>
                    <p>Take your passion for your favourite team everywhere with the<strong> Savino Del Bene Volley</strong> mug!</p>
                    <p><strong>PLAY AS ONE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>WORLD CHAMPIONS T-SHIRT!</strong></p>
                    <p>Maglietta celebrativa del Mondiale per Club 2025 vinto in Brasile.</p>
                    <p>Sul fronte i volti delle nostre amate ragazze guidate da coach Marco Gaspari, sul retro il nome delle atlete che hanno scritto una pagina indelebile della nostra società.</p>
                    <p>Obrigados ragazze!</p>
                    <div dir="auto" data-olk-copy-source="MessageBody">#2 TRABALLI</div>
                    <div dir="auto">#3 BECHIS</div>
                    <div dir="auto">#4 SKINNER</div>
                    <div dir="auto">#5 CASTILLO</div>
                    <div dir="auto">#6 RUDDINS</div>
                    <div dir="auto">#7 FRANKLIN</div>
                    <div dir="auto">#8 RIBECHI</div>
                    <div dir="auto">#9 BOSETTI</div>
                    <div dir="auto">#10 OGNJENOVIC</div>
                    <div dir="auto">#11 MANCINI</div>
                    <div dir="auto">#13 GRAZIANI</div>
                    <div dir="auto">#14 NWAKALOR</div>
                    <div dir="auto">#17 ANTROPOVA</div>
                    <div dir="auto">#21 WEITZEL</div>
                    <div dir="auto">#23 GENNARI</div>
                    <div dir="auto"></div>
                    <div dir="auto">HEAD COACH GASPARI</div>
                    IT,
                'en' => <<<'EN'
                    <p><strong>WORLD CHAMPIONS T-SHIRT!</strong></p>
                    <p>T-shirt celebrating the 2025 Club World Championship won in Brazil.</p>
                    <p>On the front, the faces of our beloved girls led by coach Marco Gaspari; on the back, the names of the athletes who wrote an unforgettable page in our club's history.</p>
                    <p>Obrigados girls!</p>
                    <div dir="auto" data-olk-copy-source="MessageBody">#2 TRABALLI</div>
                    <div dir="auto">#3 BECHIS</div>
                    <div dir="auto">#4 SKINNER</div>
                    <div dir="auto">#5 CASTILLO</div>
                    <div dir="auto">#6 RUDDINS</div>
                    <div dir="auto">#7 FRANKLIN</div>
                    <div dir="auto">#8 RIBECHI</div>
                    <div dir="auto">#9 BOSETTI</div>
                    <div dir="auto">#10 OGNJENOVIC</div>
                    <div dir="auto">#11 MANCINI</div>
                    <div dir="auto">#13 GRAZIANI</div>
                    <div dir="auto">#14 NWAKALOR</div>
                    <div dir="auto">#17 ANTROPOVA</div>
                    <div dir="auto">#21 WEITZEL</div>
                    <div dir="auto">#23 GENNARI</div>
                    <div dir="auto"></div>
                    <div dir="auto">HEAD COACH GASPARI</div>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p><strong>Zaino Savino Del Bene Volley !</strong></p>
                    <p>Porta sempre con te la passione per la tua squadra preferita con lo zaino <strong>Savino Del Bene Volley</strong>!</p>
                    <p>Zaino dal design moderno e minimal, realizzato in tessuto resistente di colore grigio.</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p><strong>Savino Del Bene Volley Backpack!</strong></p>
                    <p>Take your passion for your favourite team everywhere with the <strong>Savino Del Bene Volley</strong> backpack!</p>
                    <p>A backpack with a modern, minimal design, made from hard-wearing grey fabric.</p>
                    <p><strong>BELIEVE Savino Del Bene Volley!</strong></p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Cappellino baseball con fascetta regolabile, personalizzato con patch Savino Del Bene Volley.</p>
                    <p>Composizione: 100% cotone</p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p>Baseball cap with adjustable strap, personalised with a Savino Del Bene Volley patch.</p>
                    <p>Composition: 100% cotton</p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Felpa blu manica lunga con logo stampato sul petto utilizzato dalle giocatrici durante il tempo libero.</p>
                    <ul>
                    <li>Composizione 80% cotone e 20% poliestere</li>
                    <li>Lavabile in lavatrice a 30°</li>
                    </ul>
                    IT,
                'en' => <<<'EN'
                    <p>Long-sleeved blue sweatshirt with printed chest logo, worn by the players in their free time.</p>
                    <ul>
                    <li>80% cotton and 20% polyester</li>
                    <li>Machine washable at 30°</li>
                    </ul>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Felpa blu manica lunga con logo stampato sul petto utilizzato dallo staff nel tempo libero.</p>
                    <ul>
                    <li>Composizione 80% cotone e 20% poliestere</li>
                    <li>Lavabile in lavatrice a 30°</li>
                    </ul>
                    IT,
                'en' => <<<'EN'
                    <p>Long-sleeved blue sweatshirt with printed chest logo, worn by the staff in their free time.</p>
                    <ul>
                    <li>80% cotton and 20% polyester</li>
                    <li>Machine washable at 30°</li>
                    </ul>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Gift Card del valore di € 100,00</p>
                    <p>Con l&#8217;acquisto della Gift Card riceverai tramite mail un codice sconto esclusivo per l&#8217;iscrizione al Summer Camp!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Gift Card worth € 100.00</p>
                    <p>When you buy the Gift Card you will receive an exclusive discount code by email for Summer Camp registration!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Gift Card del valore di € 200,00</p>
                    <p>Con l&#8217;acquisto della Gift Card riceverai tramite mail un codice sconto esclusivo per l&#8217;iscrizione al Summer Camp!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Gift Card worth € 200.00</p>
                    <p>When you buy the Gift Card you will receive an exclusive discount code by email for Summer Camp registration!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Gift Card del valore di € 300,00</p>
                    <p>Con l&#8217;acquisto della Gift Card riceverai tramite mail un codice sconto esclusivo per l&#8217;iscrizione al Summer Camp!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Gift Card worth € 300.00</p>
                    <p>When you buy the Gift Card you will receive an exclusive discount code by email for Summer Camp registration!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Indossa la t-shirt con stampa sul fronte della nostra divertente mascotte Savino.</p>
                    <p><strong>Perfetta come regalo per i più piccolo tifosi.</strong></p>
                    <p>In cotone con collo a giro e maniche corte.</p>
                    <p>Composizione 100% cotone</p>
                    <p>Lavabile in lavatrice</p>
                    IT,
                'en' => <<<'EN'
                    <p>Wear the t-shirt with our fun mascot Savino printed on the front.</p>
                    <p><strong>Perfect as a gift for the youngest fans.</strong></p>
                    <p>Cotton, with a crew neck and short sleeves.</p>
                    <p>100% cotton</p>
                    <p>Machine washable</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Modello Molten <span data-olk-copy-source="MessageBody">V5M1300</span></p>
                    <p>Il pallone con la firma di tutte le giocatrici della Savino Del Bene Volley 2025/2026!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Molten <span data-olk-copy-source="MessageBody">V5M1300</span> model</p>
                    <p>The ball signed by every Savino Del Bene Volley 2025/2026 player!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Morbido peluche della <strong>Mascotte Savino.</strong></p>
                    <p>&nbsp;</p>
                    <p>&nbsp;</p>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p>Soft plush toy of the <strong>Savino Mascot.</strong></p>
                    <p>&nbsp;</p>
                    <p>&nbsp;</p>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, bianca gara away, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia Camilla ha vinto il premio Mvp nella gara casalinga contro Busto Arsizio!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, white away kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, Camilla won the MVP award in the home match against Busto Arsizio!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, bianca gara away, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia la nostra Kate ha macinato una valanga di punti, aggiudicandosi il premio di Mvp in entrambe le occasioni con Perugia, oltre alle trasferte piemontesi contro Monviso, Novara e Chieri, oltre ad aver chiuso i Quarti di Finale Scudetto in Gara 2 a Bergamo!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, white away kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, our Kate piled up points by the bucketload, taking the MVP award in both matches against Perugia and on the Piedmont away trips to Monviso, Novara and Chieri, as well as closing out the Scudetto Quarter-Finals in Game 2 in Bergamo!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, bianca gara away, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia la nostra capitana ha condotto la propria squadra per tutta la stagione, meritandosi il premio di Mvp nella gara di andata con Macerata e nelle gare del girone di ritorno con Il Bisonte e Volley Bergamo</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, white away kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, our captain led her team all season long, earning the MVP award in the first-leg match against Macerata and in the return-leg matches against Il Bisonte and Volley Bergamo</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026.</p>
                    <p>Con questa maglia Caterina ha vinto il premio MVP nella gara di ritorno con Macerata e in Gara 1 dei Quarti di Playoff con Bergamo</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season.</p>
                    <p>Wearing this jersey, Caterina won the MVP award in the return match against Macerata and in Game 1 of the Playoff Quarter-Finals against Bergamo</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia Avery ha vinto il premio di Mvp nella gara di andata con Il Bisonte e con Chieri, ma soprattutto nella Semifinale di Coppa Italia a Torino sempre contro la compagine piemontese!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, Avery won the MVP award in the first-leg matches against Il Bisonte and Chieri, and above all in the Coppa Italia Semi-Final in Turin, again against the Piedmont side!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia Emma ha vinto il premio Mvp nella partita casalinga contro Igor Volley Novara, con un ingresso a dir poco determinante!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, Emma won the MVP award in the home match against Igor Volley Novara, coming off the bench to decisive effect!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia Linda ha vinto il premio Mvp nella gara casalinga contro Megabox Vallefoglia!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, Linda won the MVP award in the home match against Megabox Vallefoglia!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia la nostra Kate ha macinato una valanga di punti, aggiudicandosi il premio di Mvp nelle partite casalinghe con Bergamo, Monviso e Cuneo, oltre che in Gara 3 di Semifinale Playoff contro Vero Volley Milano!</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, our Kate piled up points by the bucketload, taking the MVP award in the home matches against Bergamo, Monviso and Cuneo, as well as in Game 3 of the Playoff Semi-Final against Vero Volley Milano!</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, blu gara home, indossata dalla giocatrice durante la stagione 2025/2026</p>
                    <p>Con questa maglia la nostra capitana ha condotto la propria squadra per tutta la stagione, meritandosi il premio di Mvp nella gara di andata con Vero Volley Milano e nel ritorno con San Giovanni in Marignano</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, blue home kit, worn by the player during the 2025/2026 season</p>
                    <p>Wearing this jersey, our captain led her team all season long, earning the MVP award in the first-leg match against Vero Volley Milano and in the return match against San Giovanni in Marignano</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>Savino Del Bene Volley Maglia gioco ufficiale, rosa gara, indossata dalla giocatrice durante la stagione 2025/2026.</p>
                    <p>Con questa maglia Brenda ha ricevuto in campionato con il 42,94%</p>
                    IT,
                'en' => <<<'EN'
                    <p>Savino Del Bene Volley official match jersey, pink kit, worn by the player during the 2025/2026 season.</p>
                    <p>Wearing this jersey, Brenda posted a 42.94% reception rate in the league</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>T-shirt manica corta in cotone con logo stampato sul petto e lettering flou.</p>
                    <ul>
                    <li>Composizione 100% cotone</li>
                    <li>Maniche corte</li>
                    <li>Lavabile in lavatrice</li>
                    </ul>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p>Short-sleeved cotton t-shirt with printed chest logo and fluo lettering.</p>
                    <ul>
                    <li>100% cotton</li>
                    <li>Short sleeves</li>
                    <li>Machine washable</li>
                    </ul>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => <<<'IT'
                    <p>T-shirt manica corta in cotone con motto<strong> BELIEVE </strong>stampato sul petto e logo <strong>Savino Del Bene Volley</strong> sullo sfondo.</p>
                    <ul>
                    <li>Composizione 100% cotone</li>
                    <li>Maniche corte</li>
                    <li>Lavabile in lavatrice</li>
                    </ul>
                    <p>&nbsp;</p>
                    IT,
                'en' => <<<'EN'
                    <p>Short-sleeved cotton t-shirt with the motto<strong> BELIEVE </strong>printed on the chest and the <strong>Savino Del Bene Volley</strong> logo in the background.</p>
                    <ul>
                    <li>100% cotton</li>
                    <li>Short sleeves</li>
                    <li>Machine washable</li>
                    </ul>
                    <p>&nbsp;</p>
                    EN,
            ],
            [
                'it' => '<p>T-shirt ufficiale Erreà della Final Four di Champions League 2026 a Istanbul</p>',
                'en' => '<p>Official Erreà t-shirt for the 2026 Champions League Final Four in Istanbul</p>',
            ],
        ],

        'product_categories.name' => [
            ['it' => 'Abbigliamento', 'en' => 'Clothing'],
            ['it' => 'Accessori', 'en' => 'Accessories'],
            ['it' => 'Asta', 'en' => 'Auction'],
            ['it' => 'Gift card', 'en' => 'Gift card'],
            ['it' => 'Gift Card', 'en' => 'Gift Card'],
            ['it' => 'Kit Champions', 'en' => 'Champions Kit'],
            ['it' => 'Kit Gara 25-26', 'en' => 'Match Kit 25-26'],
        ],
        'staff_members.role' => [
            ['it' => 'Assistente Nutrizionista', 'en' => 'Assistant Nutritionist'],
            ['it' => 'Consigliere', 'en' => 'Board Member'],
            ['it' => 'Direttore Generale', 'en' => 'General Manager'],
            ['it' => 'Fisioterapista', 'en' => 'Physiotherapist'],
            ['it' => 'Fotografo', 'en' => 'Photographer'],
            ['it' => 'Medico', 'en' => 'Team Doctor'],
            ['it' => 'Nutrizionista', 'en' => 'Nutritionist'],
            ['it' => 'Osteopata', 'en' => 'Osteopath'],
            ['it' => 'Preparatore Atletico', 'en' => 'Strength and Conditioning Coach'],
            ['it' => 'Presidente', 'en' => 'President'],
            ['it' => 'Press Manager', 'en' => 'Press Manager'],
            ['it' => 'Primo Allenatore', 'en' => 'Head Coach'],
            ['it' => 'Resp. Relazioni Esterne & Brand Ambassador', 'en' => 'Head of External Relations & Brand Ambassador'],
            ['it' => 'Responsabile Fisioterapia', 'en' => 'Head of Physiotherapy'],
            ['it' => 'Responsabile Marketing', 'en' => 'Head of Marketing'],
            ['it' => 'Responsabile Segreteria Organizzativa', 'en' => 'Head of Club Administration'],
            ['it' => 'Safeguarding Officer', 'en' => 'Safeguarding Officer'],
            ['it' => 'Scoutman', 'en' => 'Scoutman'],
            ['it' => 'Social Media Manager', 'en' => 'Social Media Manager'],
            ['it' => 'Sparring Partner', 'en' => 'Sparring Partner'],
            ['it' => 'Terzo Allenatore', 'en' => 'Third Coach'],
            ['it' => 'Vice Allenatore', 'en' => 'Assistant Coach'],
            ['it' => 'Vice Presidente', 'en' => 'Vice President'],
        ],

        /*
         * Le voci di menu erano già state tradotte dalla migrazione
         * 2026_07_28_180000_translate_menu_items_to_english: qui restano solo
         * quelle aggiunte dopo, e le poche che in inglese restano identiche
         * perché sono nomi propri o già in inglese.
         */
        'menu_items.label' => [
            ['it' => 'CEV Champions League', 'en' => 'CEV Champions League'],
            // Era "Title Sponsor (SDB)": la redazione ha chiesto il nome per
            // esteso. In inglese resta identica.
            ['it' => 'Title Sponsor', 'en' => 'Title Sponsor'],
            ['it' => 'Codice Tutela Minori', 'en' => 'Child Protection Code'],
            ['it' => 'Corporate Governance', 'en' => 'Corporate Governance'],
            ['it' => 'Double Face', 'en' => 'Double Face'],
            ['it' => 'Gallery', 'en' => 'Gallery'],
            ['it' => 'Hospitality', 'en' => 'Hospitality'],
            ['it' => 'Magazine', 'en' => 'Magazine'],
            ['it' => 'Modello Organizzativo', 'en' => 'Organisational Model'],
            ['it' => 'News', 'en' => 'News'],
            ['it' => 'Protocollo Bullismo', 'en' => 'Anti-Bullying Protocol'],
            ['it' => 'Protocollo Razzismo', 'en' => 'Anti-Racism Protocol'],
            ['it' => 'SDB Youth', 'en' => 'SDB Youth'],
            ['it' => 'Safeguarding', 'en' => 'Safeguarding'],
            ['it' => 'Serie A1', 'en' => 'Serie A1'],
            ['it' => 'Serie B1 / U19', 'en' => 'Serie B1 / U19'],
            ['it' => 'Summer Camp', 'en' => 'Summer Camp'],
            ['it' => 'Talent Day & Recruiting', 'en' => 'Talent Day & Recruiting'],
            ['it' => 'Ticketing', 'en' => 'Ticketing'],
            ['it' => 'Title Sponsor (SDB)', 'en' => 'Title Sponsor (SDB)'],
            ['it' => 'Volley 4 All', 'en' => 'Volley 4 All'],
        ],

        'hero_slides.title' => [
            ['it' => 'SAVINO DEL BENE VOLLEY', 'en' => 'SAVINO DEL BENE VOLLEY'],
        ],

        /*
         * Categorie delle news. Quelle non elencate sono nomi di competizione
         * identici nelle due lingue (Serie A1 2024/2025, Jam Cup, Under 16…) e
         * non hanno bisogno di una traduzione diversa dall'italiano.
         */
        'categories.name' => [
            ['it' => 'CEV Champions League', 'en' => 'CEV Champions League'],
            ['it' => 'CEV Cup 2022/2023', 'en' => 'CEV Cup 2022/2023'],
            ['it' => 'Challenge Cup', 'en' => 'Challenge Cup'],
            ['it' => 'Coppa Italia 2022-2023', 'en' => 'Coppa Italia 2022-2023'],
            ['it' => 'Coppa Italia A1', 'en' => 'Coppa Italia A1'],
            ['it' => 'Giovanile', 'en' => 'Youth'],
            ['it' => 'Jam Cup', 'en' => 'Jam Cup'],
            ['it' => 'Mondiale per Club', 'en' => 'Club World Championship'],
            ['it' => 'News Sponsor', 'en' => 'Sponsor News'],
            ['it' => 'Notizie', 'en' => 'News'],
            ['it' => 'Promozionale', 'en' => 'Promotional'],
            ['it' => 'SDB Youth', 'en' => 'SDB Youth'],
            ['it' => 'Serie A1 2021/2022', 'en' => 'Serie A1 2021/2022'],
            ['it' => 'Serie A1 2022/2023', 'en' => 'Serie A1 2022/2023'],
            ['it' => 'Serie A1 2023/2024', 'en' => 'Serie A1 2023/2024'],
            ['it' => 'Serie A1 2024/2025', 'en' => 'Serie A1 2024/2025'],
            ['it' => 'Serie A1 2025/2026', 'en' => 'Serie A1 2025/2026'],
            ['it' => 'Serie B1', 'en' => 'Serie B1'],
            ['it' => 'Serie C', 'en' => 'Serie C'],
            ['it' => 'Società', 'en' => 'Club'],
            ['it' => 'Società affiliate', 'en' => 'Affiliated Clubs'],
            ['it' => 'Under 14', 'en' => 'Under 14'],
            ['it' => 'Under 16', 'en' => 'Under 16'],
            ['it' => 'Under 18', 'en' => 'Under 18'],
            ['it' => 'Volleyrò', 'en' => 'Volleyrò'],
        ],
    ],
    /*
     * Pagine del CMS, individuate per slug.
     *
     * Il comando scrive solo i campi che in inglese mancano del tutto: dove la
     * redazione ha già tradotto (title-sponsor, hospitality e volley-4-all
     * hanno il contenuto inglese, non il titolo) la voce qui viene ignorata.
     */
    'byKey' => [
        'pages' => [
            'abbonamenti' => [
                'title' => 'Season Tickets',
                'excerpt' => 'Savino Del Bene Volley 2026/2027 season tickets. Discover the packages and prices for the new season.',
                'meta_description' => 'Savino Del Bene Volley 2026/2027 season tickets. Discover the packages and prices for the new season.',
                'content' => '<h2>2026/2027 Season Ticket Campaign</h2><p>Live every moment of Serie A1 and the Champions League with a season ticket. Choose the package that suits you best and secure your seat at Pala BigMat.</p>',
            ],
            'accrediti-stampa' => [
                'title' => 'Press Accreditation',
                'excerpt' => 'Apply for press accreditation for Savino Del Bene Volley matches. Information for journalists and photographers.',
                'meta_description' => 'Apply for press accreditation for Savino Del Bene Volley matches. Information for journalists and photographers.',
                'content' => '<p>To apply for press accreditation, fill in the dedicated form at least 48 hours before the event. The press office will assess the request and send confirmation by email.</p>',
            ],
            'biglietteria' => [
                'title' => 'Tickets',
                'excerpt' => 'Buy tickets for Savino Del Bene Volley matches. Prices, sales points and how to purchase.',
                'meta_description' => 'Buy tickets for Savino Del Bene Volley matches. Prices, sales points and how to purchase.',
                'content' => '<h2>Tickets</h2><p>Tickets for Savino Del Bene Volley home matches can be bought online and at authorised sales points.</p>',
            ],
            'cartelle-stampa' => [
                'title' => 'Press Kits',
                'excerpt' => 'Download the official Savino Del Bene Volley press kits. Logo, official photos and press materials.',
                'meta_description' => 'Download the official Savino Del Bene Volley press kits. Logo, official photos and press materials.',
                'content' => '<h2>Press Materials</h2><p>This section holds the official press materials: the club logo in various formats, official photos of the players, pre-match press kits and official statements.</p>',
            ],
            'cookie-policy' => [
                'title' => 'Cookie Policy',
                'content' => '<h2>Cookie Notice</h2><p>This website only uses technical cookies necessary for the site to work.</p><h3>Technical cookies</h3><p>These cookies are essential for the site to work properly and cannot be disabled. They include session cookies (XSRF-TOKEN, laravel_session) that keep browsing secure.</p><h3>Third-party cookies</h3><p>The site does not use profiling or third-party tracking cookies.</p><h3>How to manage cookies</h3><p>You can manage your cookie preferences through your browser settings.</p>',
            ],
            'hospitality' => [
                'title' => 'Hospitality',
                'excerpt' => 'Hospitality and premium services for Savino Del Bene Volley matches at Pala BigMat.',
                'meta_description' => 'Hospitality and premium services for Savino Del Bene Volley matches at Pala BigMat.',
                'content' => '<h2>The Hospitality Experience</h2><p>Watch Savino Del Bene Volley from an exclusive vantage point. Our Hospitality programme offers premium seating, dedicated catering, meet &amp; greet with the players and networking with the club\'s partners.</p>',
            ],
            'organigramma' => [
                'title' => 'Organisation Chart',
                'excerpt' => 'The official Savino Del Bene Volley organisation chart. Meet the management team and the club staff.',
                'meta_description' => 'The official Savino Del Bene Volley organisation chart. Meet the management team and the club staff.',
                'content' => '<p>The Savino Del Bene Volley organisation chart. This page is managed from the CMS.</p>',
            ],
            'palazzetto' => [
                'title' => 'The Arena',
                'excerpt' => 'Pala BigMat, home of Savino Del Bene Volley in Florence. Capacity, how to get there and facilities.',
                'meta_description' => 'Pala BigMat, home of Savino Del Bene Volley in Florence. Capacity, how to get there and facilities.',
                'content' => '<h2>Pala BigMat</h2><p>Pala BigMat in Florence is the home of Savino Del Bene Volley. With a capacity of more than 3,500 seats, the arena offers a unique experience for fans and volleyball lovers alike.</p><h2>How to Get There</h2><p>Via del Cavallaccio, 18/20/22/24 — 50142 Florence (FI). Easy to reach by public transport, with plenty of parking available.</p><h2>Facilities</h2><p>Bar, hospitality area, accessible entrances, supervised car park.</p>',
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'content' => '<h2>Privacy Notice</h2><p>Under Article 13 of EU Regulation 2016/679 (GDPR), Savino Del Bene Volley states that the personal data collected through this website is processed in accordance with the data protection legislation in force.</p><h3>Data Controller</h3><p>Savino Del Bene Volley S.S.D. a r.l. — Via di Scandicci, 50142 Florence (FI)</p><h3>Data collected</h3><p>The site only collects the technical data required for browsing (technical cookies, session data). No profiling data is collected without the explicit consent of the user.</p><h3>Your rights</h3><p>You may exercise the rights set out in Articles 15-22 of the GDPR by writing to: privacy@savinodelbenevolley.it</p>',
            ],
            'safeguarding' => [
                'title' => 'Safeguarding',
                'excerpt' => 'The Savino Del Bene Volley safeguarding policy. Protecting minors and preventing harassment.',
                'meta_description' => 'The Savino Del Bene Volley safeguarding policy. Protecting minors and preventing harassment.',
                'content' => '<h2>Safeguarding Policy</h2><p>Savino Del Bene Volley is committed to providing a safe and protected environment for everyone registered with the club, and for minors in particular, with measures to prevent every form of abuse, harassment and discrimination.</p>',
            ],
            'settore-giovanile' => [
                'title' => 'Youth Sector',
                'excerpt' => 'The Savino Del Bene Volley youth sector. Under 18, Under 16, Under 14 and Under 13.',
                'meta_description' => 'The Savino Del Bene Volley youth sector. Under 18, Under 16, Under 14 and Under 13.',
                'content' => '<p>The youth sector is the beating heart of our sporting project. Through a structured training programme, our young players grow up living the values of the club.</p>',
            ],
            'storia' => [
                'title' => 'Club History',
                'excerpt' => 'The story of Savino Del Bene Volley: from 1982 to today, a path of growth and success in Italian women\'s volleyball.',
                'meta_description' => 'The story of Savino Del Bene Volley: from 1982 to today, a path of growth and success in Italian women\'s volleyball.',
                'content' => '<h2>The Beginnings</h2><p>Founded in Scandicci in 1982, Savino Del Bene Volley has become one of the leading clubs in Italian women\'s volleyball.</p><h2>Growing Up</h2><p>With the strategic partnership of the Savino Del Bene Group, the club reached historic milestones: the Scudetto Final and a place in the CEV Champions League.</p><h2>Today</h2><p>Savino Del Bene Volley is now a model of sporting management, with an outstanding youth sector and a vision set firmly on the future.</p>',
            ],
            'summer-camp' => [
                'title' => 'Summer Camp & Experience',
                'excerpt' => 'Savino Del Bene Volley Summer Camp. Weeks of sport, fun and training with Serie A1 players.',
                'meta_description' => 'Savino Del Bene Volley Summer Camp. Weeks of sport, fun and training with Serie A1 players.',
                'content' => '<h2>Summer Camp 2026</h2><p>A one-of-a-kind experience for young volleyball players: training sessions with the first team coaching staff, tournaments, recreational activities and the chance to meet the Serie A1 players.</p>',
            ],
            'talent-day' => [
                'title' => 'Talent Day & Recruiting',
                'excerpt' => 'Savino Del Bene Volley Talent Day. Take part in the trials to join the youth sector.',
                'meta_description' => 'Savino Del Bene Volley Talent Day. Take part in the trials to join the youth sector.',
                'content' => '<h2>Talent Day</h2><p>Every year Savino Del Bene Volley holds trial days open to all young players who want to join the youth sector. Keep an eye on this page for the next dates.</p>',
            ],
            'title-sponsor' => [
                'title' => 'Title Sponsor',
                'excerpt' => 'Savino Del Bene S.p.A., title sponsor of Savino Del Bene Volley. Discover the partnership.',
                'meta_description' => 'Savino Del Bene S.p.A., title sponsor of Savino Del Bene Volley. Discover the partnership.',
                'content' => '<h2>Savino Del Bene S.p.A.</h2><p>The Savino Del Bene Group, a world leader in logistics and international freight forwarding, has been the club\'s title sponsor since it was founded. A partnership that brings together business excellence and a passion for sport.</p>',
            ],
            'volley-4-all' => [
                'title' => 'Volley 4 All',
                'excerpt' => 'Volley 4 All: the Savino Del Bene Volley social inclusion project. Sport for everyone, without barriers.',
                'meta_description' => 'Volley 4 All: the Savino Del Bene Volley social inclusion project. Sport for everyone, without barriers.',
                'content' => '<h2>Volley 4 All</h2><p>A project that breaks down barriers and brings volleyball to everyone: people with disabilities, young people in difficult social circumstances and disadvantaged communities. Because sport is a right, not a privilege.</p>',
            ],
            /*
             * Pagine "contenitore" delle sezioni: il corpo lo mettono i
             * componenti Vue dedicati, ma il titolo resta quello della Page e
             * finisce nei meta. Le traduzioni seguono quelle già usate nella
             * navigazione (footer.* nei dizionari JS), per non avere due nomi
             * diversi per la stessa sezione.
             */
            'home' => [
                'title' => 'Home',
            ],
            'shop' => [
                'title' => 'Shop',
            ],
            'societa' => [
                'title' => 'The Club',
            ],
            'sponsor' => [
                'title' => 'Sponsors',
            ],
            'ticketing' => [
                'title' => 'Ticketing',
            ],
            'comunicazione' => [
                'title' => 'Media',
            ],
            'sociale' => [
                'title' => 'Social Projects',
            ],
            'youth' => [
                'title' => 'Youth Academy',
            ],
            'contatti' => [
                'title' => 'Contacts',
                'excerpt' => 'Get in touch with Savino Del Bene Volley. Find our contact details, the address of our offices and the contact form.',
                'meta_description' => 'Get in touch with Savino Del Bene Volley. Find our contact details, the address of our offices and the contact form.',
            ],
        ],
    ],
];
