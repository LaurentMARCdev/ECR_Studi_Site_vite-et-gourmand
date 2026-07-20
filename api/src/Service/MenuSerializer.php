<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Plat;

/**
 * Sérialise les entités Menu et Plat vers le format JSON attendu par le front.
 *
 * On préfère un service dédié plutôt que d'annoter les entités avec des
 * groupes de sérialisation Symfony : c'est plus explicite, plus flexible,
 * et cela permet d'avoir plusieurs vues (liste vs détail) sans magie.
 */
class MenuSerializer
{
    /**
     * Format compact pour la liste des menus (page /menus).
     *
     * @return array<string, mixed>
     */
    public function toArrayListe(Menu $menu): array
    {
        // On prend le premier thème/régime pour l'affichage compact
        // (le front peut faire du filtre par un seul thème dominant)
        $themePrincipal  = $menu->getThemes()->first();
        $regimePrincipal = $menu->getRegimes()->first();
        $imagePrincipale = $menu->getImagePrincipale();

        return [
            'menu_id'                 => $menu->getMenuId(),
            'titre'                   => $menu->getTitre(),
            'description'             => $menu->getDescription(),
            'theme'                   => $themePrincipal  ? $themePrincipal->getLibelle()  : null,
            'themes'                  => array_map(fn($t) => $t->getLibelle(), $menu->getThemes()->toArray()),
            'regime'                  => $regimePrincipal ? $regimePrincipal->getLibelle() : null,
            'regimes'                 => array_map(fn($r) => $r->getLibelle(), $menu->getRegimes()->toArray()),
            'nombre_personne_minimum' => $menu->getNombrePersonneMinimum(),
            'prix_par_personne'       => $menu->getPrixParPersonneAsFloat(),
            'quantite_restante'       => $menu->getQuantiteRestante(),
            'image_url'               => $imagePrincipale?->getUrl(),
            // Liste dédupliquée des libellés d'allergènes (pour l'affichage rapide)
            'allergenes'              => $this->collecterAllergenesUniques($menu),
        ];
    }

    /**
     * Format détaillé pour la page /menus/:id.
     *
     * @return array<string, mixed>
     */
    public function toArrayDetail(Menu $menu): array
    {
        $platsGroupes = $menu->getPlatsGroupes();

        return [
            'menu_id'                 => $menu->getMenuId(),
            'titre'                   => $menu->getTitre(),
            'description'             => $menu->getDescription(),
            'theme'                   => $menu->getThemes()->first()?->getLibelle(),
            'themes'                  => array_map(fn($t) => $t->getLibelle(), $menu->getThemes()->toArray()),
            'regime'                  => $menu->getRegimes()->first()?->getLibelle(),
            'regimes'                 => array_map(fn($r) => $r->getLibelle(), $menu->getRegimes()->toArray()),
            'nombre_personne_minimum' => $menu->getNombrePersonneMinimum(),
            'prix_par_personne'       => $menu->getPrixParPersonneAsFloat(),
            'prix_minimum'            => $menu->getPrixMinimum(),
            'quantite_restante'       => $menu->getQuantiteRestante(),
            'conditions'              => $menu->getConditions(),
            'actif'                   => $menu->isActif(),
            'images'                  => array_map(
                fn($img) => $img->getUrl(),
                $menu->getImages()->toArray()
            ),
            'allergenes_globaux'      => $this->collecterAllergenesUniques($menu),
            'plats'                   => [
                'entrees'          => array_map([$this, 'platToArray'], $platsGroupes['entrees']),
                'plats_principaux' => array_map([$this, 'platToArray'], $platsGroupes['plats_principaux']),
                'desserts'         => array_map([$this, 'platToArray'], $platsGroupes['desserts']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function platToArray(Plat $plat): array
    {
        return [
            'plat_id'     => $plat->getPlatId(),
            'nom'         => $plat->getTitre(),
            'description' => $plat->getDescription(),
            'categorie'   => $plat->getCategorie(),
            'image'       => $plat->getImageUrl(),
            'allergenes'  => array_map(fn($a) => $a->getLibelle(), $plat->getAllergenes()->toArray()),
        ];
    }

    /**
     * Collecte la liste dédupliquée des allergènes présents dans tous les plats du menu.
     *
     * @return string[]
     */
    private function collecterAllergenesUniques(Menu $menu): array
    {
        $allergenes = [];
        foreach ($menu->getPlats() as $plat) {
            foreach ($plat->getAllergenes() as $allergene) {
                $allergenes[$allergene->getLibelle()] = true;
            }
        }
        return array_keys($allergenes);
    }
}
