Dichiarazione di recesso ricevuta dalla funzione online del sito.

Ordine indicato: {{ $richiesta->numero_ordine }}{{ $richiesta->order_id ? '' : ' (non trovato fra gli ordini: verificare)' }}
Nome: {{ $richiesta->nome }}
Email: {{ $richiesta->email }}
Articoli: {{ $richiesta->articoli ?: 'tutti quelli dell\'ordine' }}
Inviata il: {{ $richiesta->inviata_il->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}

Il cliente ha 14 giorni per rispedire la merce. Il rimborso (prezzo e spese di consegna standard) va eseguito entro 14 giorni da oggi, con lo stesso metodo di pagamento; si puo' attendere di ricevere la merce o la prova della spedizione.

La richiesta si segna come gestita nel pannello: Shop > Richieste di recesso.
