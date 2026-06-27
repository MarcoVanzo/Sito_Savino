/**
 * Dati di navigazione condivisi — usati da PublicLayout, MegaMenu e MobileDrawer.
 *
 * Estrarre la configurazione in un modulo separato elimina la duplicazione
 * segnalata da SonarCloud nel layout.
 */

/** Helper: costruisce un item di navigazione dal formato compatto */
function nav(name, route, mottoTitle, mottoSubtitle, menuImage, items, extra = {}) {
    return {
        name, route, path: `/${route}`,
        mottoTitle, mottoSubtitle, menuImage,
        items: items.map(([label, href]) => ({ name: label, href })),
        ...extra,
    };
}

export const navigation = [
    nav('Stagione', 'stagione',
        'Nel Cuore<br/>della Sfida.',
        'Scopri il roster, il calendario e i risultati della squadra in Serie A1.',
        '/images/menu_stagione.jpg',
        [['Roster A1', '/stagione'], ['Staff Tecnico', '/staff'], ['Risultati e Classifica', '/risultati'], ['Gallery', '/gallery']]),

    nav('Società', 'societa',
        'La Nostra<br/>Storia.',
        'Le radici, l\'organizzazione e la casa della Savino Del Bene Volley.',
        '/images/menu_societa.jpg',
        [['Storia', '/storia'], ['Organigramma', '/organigramma'], ['Palazzetto', '/palazzetto'], ['Contatti', '/contatti']]),

    nav('Ticketing', 'ticketing',
        'Vivi<br/>l\'Emozione.',
        'Acquista i tuoi biglietti e unisciti ai tifosi al palazzetto.',
        '/images/menu_ticketing.jpg',
        [['Biglietteria', '/biglietteria'], ['Abbonamenti', '/abbonamenti'], ['Convenzioni', '/convenzioni'], ['Accessibilità', '/accessibilita']]),

    nav('Sponsor', 'sponsor',
        'I Nostri<br/>Partner.',
        'Eccellenza e business: le aziende che credono nel nostro progetto.',
        '/images/menu_sponsor.jpg',
        [['I Nostri Sponsor', '/sponsor'], ['Title Sponsor', '/title-sponsor'], ['Diventa Sponsor', '/diventa-sponsor'], ['Hospitality', '/hospitality'], ['Affiliazioni', '/affiliazioni']]),

    nav('SDB Youth', 'youth',
        'Il Futuro<br/>in Campo.',
        'Le giovani promesse e le squadre del nostro settore giovanile.',
        '/images/menu_youth.jpg',
        [['Roster B1', '/stagione/b1'], ['Settore Giovanile', '/settore-giovanile'], ['Talent Day', '/talent-day'], ['Progetto Scuola', '/progetto-scuola']]),

    nav('Camp', 'summer-camp',
        'Estate<br/>di Sport.',
        'Divertimento, crescita e pallavolo nei nostri Summer Camp.',
        '/images/menu_camp.jpg',
        [['Summer Camp', '/summer-camp'], ['Iscrizioni', '/summer-camp']]),

    nav('Sociale', 'sociale',
        'Volley<br/>For All.',
        'L\'impegno della società per il territorio e l\'inclusione.',
        '/images/menu_sociale.jpg',
        [['Volley4All', '/volley-4-all'], ['Progetti Sociali', '/progetti-sociali'], ['Sostenibilità', '/sostenibilita']]),

    nav('Media', 'comunicazione',
        'La Nostra<br/>Voce.',
        'Ultime news, gallery fotografiche e area stampa ufficiale.',
        '/images/menu_media.jpg',
        [['News', '/news'], ['Accrediti Stampa', '/accrediti-stampa'], ['Cartelle Stampa', '/cartelle-stampa'], ['Double Face', '/double-face'], ['Foto Gallery', '/gallery']]),

    nav('Shop', 'shop',
        'I Colori<br/>Addosso.',
        'Vesti la tua passione con il merchandising ufficiale del club.',
        '/images/menu_shop.jpg',
        [['Catalogo', '/shop'], ['Carrello', '/shop/checkout']],
        { isHighlight: true }),
];
