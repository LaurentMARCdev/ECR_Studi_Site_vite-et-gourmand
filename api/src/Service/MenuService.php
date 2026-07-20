<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MenuDTO;
use App\DTO\PlatDTO;
use App\Entity\ImageMenu;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Repository\AllergeneRepository;
use App\Repository\MenuRepository;
use App\Repository\PlatRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier pour la gestion des menus et plats.
 *
 * Séparé du contrôleur pour :
 *  - Testabilité (mock des repositories)
 *  - Réutilisation (CLI, tests, seed…)
 *  - Cohérence transactionnelle (une seule flush par opération)
 */
class MenuService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MenuRepository         $menus,
        private readonly PlatRepository         $plats,
        private readonly ThemeRepository        $themes,
        private readonly RegimeRepository       $regimes,
        private readonly AllergeneRepository    $allergenes,
    ) {
    }

    // ═══════════════════════════════════════════════════════════
    // MENUS
    // ═══════════════════════════════════════════════════════════

    /**
     * Crée un nouveau menu depuis un DTO validé.
     *
     * @throws \InvalidArgumentException si un ID de relation n'existe pas
     */
    public function creerMenu(MenuDTO $dto): Menu
    {
        $menu = new Menu();
        $this->hydraterMenu($menu, $dto);

        $this->em->persist($menu);
        $this->em->flush();

        return $menu;
    }

    /**
     * Modifie un menu existant depuis un DTO validé.
     */
    public function modifierMenu(Menu $menu, MenuDTO $dto): Menu
    {
        $this->hydraterMenu($menu, $dto);
        $this->em->flush();

        return $menu;
    }

    /**
     * "Supprime" un menu (soft delete via le champ actif).
     *
     * On préserve les données pour l'intégrité des commandes historiques.
     * L'admin peut toujours forcer la suppression physique via une commande dédiée
     * si aucune commande n'y fait référence.
     */
    public function supprimerMenu(Menu $menu): void
    {
        $menu->setActif(false);
        $this->em->flush();
    }

    /**
     * Hydrate une entité Menu depuis un DTO.
     * Utilisé pour la création ET la modification (DRY).
     */
    private function hydraterMenu(Menu $menu, MenuDTO $dto): void
    {
        $menu
            ->setTitre($dto->titre)
            ->setDescription($dto->description)
            ->setPrixParPersonne($dto->prixParPersonne)
            ->setNombrePersonneMinimum($dto->nombrePersonneMinimum)
            ->setQuantiteRestante($dto->quantiteRestante)
            ->setConditions($dto->conditions)
            ->setActif($dto->actif);

        // ── Thèmes (relation *propose*) ─────────────────────────
        $themes = $this->themes->findByIds($dto->themeIds);
        $this->assertTousTrouves($themes, $dto->themeIds, 'thème');
        $menu->clearThemes();
        foreach ($themes as $theme) {
            $menu->addTheme($theme);
        }

        // ── Régimes (relation *adopte*) ─────────────────────────
        $regimes = $this->regimes->findByIds($dto->regimeIds);
        $this->assertTousTrouves($regimes, $dto->regimeIds, 'régime');
        $menu->clearRegimes();
        foreach ($regimes as $regime) {
            $menu->addRegime($regime);
        }

        // ── Plats (relation *compose*) ──────────────────────────
        $plats = $this->plats->findByIds($dto->platIds);
        $this->assertTousTrouves($plats, $dto->platIds, 'plat');
        $menu->clearPlats();
        foreach ($plats as $plat) {
            $menu->addPlat($plat);
        }

        // ── Images (remplacement complet) ───────────────────────
        // Simple : on supprime toutes les images existantes (orphanRemoval)
        // et on recrée la nouvelle liste. Suffisant pour l'ampleur du projet ;
        // pour un très grand volume d'images on ferait plutôt du diff.
        foreach ($menu->getImages()->toArray() as $imgExistante) {
            $menu->removeImage($imgExistante);
        }
        foreach ($dto->images as $ordre => $imgData) {
            if (empty($imgData['url'])) {
                continue;
            }
            $image = new ImageMenu();
            $image
                ->setUrl($imgData['url'])
                ->setAltText($imgData['altText'] ?? $menu->getTitre())
                ->setOrdreAffichage($imgData['ordreAffichage'] ?? $ordre)
                ->setEstPrincipale((bool)($imgData['estPrincipale'] ?? ($ordre === 0)));
            $menu->addImage($image);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PLATS
    // ═══════════════════════════════════════════════════════════

    public function creerPlat(PlatDTO $dto): Plat
    {
        $plat = new Plat();
        $this->hydraterPlat($plat, $dto);

        $this->em->persist($plat);
        $this->em->flush();

        return $plat;
    }

    public function modifierPlat(Plat $plat, PlatDTO $dto): Plat
    {
        $this->hydraterPlat($plat, $dto);
        $this->em->flush();

        return $plat;
    }

    public function supprimerPlat(Plat $plat): void
    {
        // Suppression physique acceptable : si un plat est encore rattaché
        // à un menu, Doctrine casse le lien via la table pivot (CASCADE).
        // Néanmoins on vérifie si le plat est encore utilisé pour informer l'utilisateur.
        if ($plat->getMenus()->count() > 0) {
            throw new \DomainException(
                sprintf(
                    'Ce plat est encore utilisé dans %d menu(s). Retirez-le d\'abord de ces menus.',
                    $plat->getMenus()->count()
                )
            );
        }

        $this->em->remove($plat);
        $this->em->flush();
    }

    private function hydraterPlat(Plat $plat, PlatDTO $dto): void
    {
        $plat
            ->setTitre($dto->titre)
            ->setDescription($dto->description)
            ->setCategorie($dto->categorie)
            ->setImageUrl($dto->imageUrl);

        $allergenes = $this->allergenes->findByIds($dto->allergeneIds);
        $this->assertTousTrouves($allergenes, $dto->allergeneIds, 'allergène');
        $plat->clearAllergenes();
        foreach ($allergenes as $allergene) {
            $plat->addAllergene($allergene);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Utilitaire
    // ═══════════════════════════════════════════════════════════

    /**
     * Vérifie que le nombre d'entités trouvées correspond au nombre d'IDs demandés.
     *
     * @param object[] $entities
     * @param int[]    $ids
     */
    private function assertTousTrouves(array $entities, array $ids, string $type): void
    {
        if (count($entities) !== count(array_unique($ids))) {
            throw new \InvalidArgumentException(
                sprintf('Un ou plusieurs %s(s) demandé(s) n\'existent pas.', $type)
            );
        }
    }
}
