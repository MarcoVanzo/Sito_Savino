<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case CommunicationManager = 'communication_manager';
    case ShopManager = 'shop_manager';
    case SportCoordinator = 'sport_coordinator';
    case User = 'user';
    case Customer = 'customer';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::CommunicationManager => 'Resp. Comunicazione',
            self::ShopManager => 'Resp. Shop',
            self::SportCoordinator => 'Coord. Sportivo',
            self::User => 'Utente',
            self::Customer => 'Cliente',
        };
    }

    /**
     * Colore badge per l'interfaccia Filament.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::CommunicationManager => 'warning',
            self::ShopManager => 'success',
            self::SportCoordinator => 'info',
            self::User => 'gray',
            self::Customer => 'primary',
        };
    }

    /**
     * Icona per l'interfaccia Filament.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'heroicon-o-shield-check',
            self::CommunicationManager => 'heroicon-o-megaphone',
            self::ShopManager => 'heroicon-o-shopping-bag',
            self::SportCoordinator => 'heroicon-o-trophy',
            self::User => 'heroicon-o-user',
            self::Customer => 'heroicon-o-shopping-cart',
        };
    }

    // ─── Accesso al pannello CMS ───────────────────────────────

    /**
     * Può accedere al pannello di amministrazione Filament?
     */
    public function canAccessPanel(): bool
    {
        return ! in_array($this, [self::User, self::Customer]);
    }

    // ─── Aree funzionali ───────────────────────────────────────

    /**
     * Ha accesso completo a tutto (Super Admin).
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Può gestire contenuti editoriali: Post, Pagine, Categorie, Tag,
     * Hero Slider, Menu, Messaggi Contatto.
     */
    public function canManageEditorial(): bool
    {
        return in_array($this, [self::SuperAdmin, self::CommunicationManager]);
    }

    /**
     * Può gestire media: Galleria eventi, Galleria immagini, Foto ufficiale.
     */
    public function canManageMedia(): bool
    {
        return in_array($this, [self::SuperAdmin, self::CommunicationManager]);
    }

    /**
     * Può gestire l'area sportiva: Stagioni, Roster, Giocatrici,
     * Staff, Statistiche, Partite, Management, Youth.
     */
    public function canManageSport(): bool
    {
        return in_array($this, [self::SuperAdmin, self::SportCoordinator]);
    }

    /**
     * Può gestire lo shop: Prodotti, Categorie Prodotto, Ordini,
     * Movimenti Magazzino, Aste.
     */
    public function canManageShop(): bool
    {
        return in_array($this, [self::SuperAdmin, self::ShopManager]);
    }

    /**
     * Può gestire sponsor (area comunicazione/commerciale).
     */
    public function canManageSponsors(): bool
    {
        return in_array($this, [self::SuperAdmin, self::CommunicationManager]);
    }

    /**
     * Può gestire il sistema: Utenti, Opzioni, Log, Impostazioni Sito.
     */
    public function canManageSystem(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Può visualizzare (sola lettura) le risorse di un'area sportiva
     * anche se non la gestisce (es. Resp. Comunicazione vede le giocatrici
     * per scrivere le news).
     */
    public function canViewSport(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::SportCoordinator,
            self::CommunicationManager,
        ]);
    }

    /**
     * Può visualizzare (sola lettura) le risorse media (es. Coord. Sportivo).
     */
    public function canViewMedia(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::CommunicationManager,
            self::SportCoordinator,
        ]);
    }
}
