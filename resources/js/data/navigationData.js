/**
 * Dati di navigazione condivisi — usati da PublicLayout, MegaMenu e MobileDrawer.
 *
 * Estrarre la configurazione in un modulo separato elimina la duplicazione
 * segnalata da SonarCloud nel layout (44.7%, 2 blocchi).
 */
export const navigation = [
    {
        name: 'Stagione', route: 'stagione', path: '/stagione',
        mottoTitle: 'Nel Cuore<br/>della Sfida.',
        mottoSubtitle: 'Scopri il roster, il calendario e i risultati della squadra in Serie A1.',
        items: [
            { name: 'Roster A1', href: '/stagione' },
            { name: 'Staff Tecnico', href: '/staff' },
            { name: 'Risultati e Classifica', href: '/risultati' },
            { name: 'Gallery', href: '/gallery' },
        ]
    },
    {
        name: 'Società', route: 'societa', path: '/societa',
        mottoTitle: 'La Nostra<br/>Storia.',
        mottoSubtitle: 'Le radici, l\'organizzazione e la casa della Savino Del Bene Volley.',
        items: [
            { name: 'Storia', href: '/storia' },
            { name: 'Organigramma', href: '/organigramma' },
            { name: 'Palazzetto', href: '/palazzetto' },
            { name: 'Contatti', href: '/contatti' },
        ]
    },
    {
        name: 'Ticketing', route: 'ticketing', path: '/ticketing',
        mottoTitle: 'Vivi<br/>l\'Emozione.',
        mottoSubtitle: 'Acquista i tuoi biglietti e unisciti ai tifosi al palazzetto.',
        items: [
            { name: 'Biglietteria', href: '/biglietteria' },
            { name: 'Abbonamenti', href: '/abbonamenti' },
            { name: 'Convenzioni', href: '/convenzioni' },
            { name: 'Accessibilità', href: '/accessibilita' },
        ]
    },
    {
        name: 'Sponsor', route: 'sponsor', path: '/sponsor',
        mottoTitle: 'I Nostri<br/>Partner.',
        mottoSubtitle: 'Eccellenza e business: le aziende che credono nel nostro progetto.',
        items: [
            { name: 'I Nostri Sponsor', href: '/sponsor' },
            { name: 'Title Sponsor', href: '/title-sponsor' },
            { name: 'Diventa Sponsor', href: '/diventa-sponsor' },
            { name: 'Hospitality', href: '/hospitality' },
            { name: 'Affiliazioni', href: '/affiliazioni' },
        ]
    },
    {
        name: 'SDB Youth', route: 'youth', path: '/youth',
        mottoTitle: 'Il Futuro<br/>in Campo.',
        mottoSubtitle: 'Le giovani promesse e le squadre del nostro settore giovanile.',
        items: [
            { name: 'Roster B1', href: '/stagione/b1' },
            { name: 'Settore Giovanile', href: '/settore-giovanile' },
            { name: 'Talent Day', href: '/talent-day' },
            { name: 'Progetto Scuola', href: '/progetto-scuola' },
        ]
    },
    {
        name: 'Camp', route: 'summer-camp', path: '/summer-camp',
        mottoTitle: 'Estate<br/>di Sport.',
        mottoSubtitle: 'Divertimento, crescita e pallavolo nei nostri Summer Camp.',
        items: [
            { name: 'Summer Camp', href: '/summer-camp' },
            { name: 'Iscrizioni', href: '/summer-camp' },
        ]
    },
    {
        name: 'Sociale', route: 'sociale', path: '/sociale',
        mottoTitle: 'Volley<br/>For All.',
        mottoSubtitle: 'L\'impegno della società per il territorio e l\'inclusione.',
        items: [
            { name: 'Volley4All', href: '/volley-4-all' },
            { name: 'Progetti Sociali', href: '/progetti-sociali' },
            { name: 'Sostenibilità', href: '/sostenibilita' },
        ]
    },
    {
        name: 'Media', route: 'comunicazione', path: '/comunicazione',
        mottoTitle: 'La Nostra<br/>Voce.',
        mottoSubtitle: 'Ultime news, gallery fotografiche e area stampa ufficiale.',
        items: [
            { name: 'News', href: '/news' },
            { name: 'Accrediti Stampa', href: '/accrediti-stampa' },
            { name: 'Cartelle Stampa', href: '/cartelle-stampa' },
            { name: 'Double Face', href: '/double-face' },
            { name: 'Foto Gallery', href: '/gallery' },
        ]
    },
    {
        name: 'Shop', route: 'shop', path: '/shop', isHighlight: true,
        mottoTitle: 'I Colori<br/>Addosso.',
        mottoSubtitle: 'Vesti la tua passione con il merchandising ufficiale del club.',
        items: [
            { name: 'Catalogo', href: '/shop' },
            { name: 'Carrello', href: '/shop/checkout' },
        ]
    },
];
