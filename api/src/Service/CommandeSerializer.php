<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;

/**
 * Sérialise une commande vers le format attendu par le front.
 *
 * Deux formats :
 *  - toArrayEspaceClient : utilisé par /api/commandes/mes-commandes
 *  - toArrayEspaceEmploye : ajoute les infos client (nom, mail, gsm)
 */
class CommandeSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArrayEspaceClient(Commande $c): array
    {
        return [
            'numero_commande'          => $c->getNumeroCommande(),
            'menu_id'                  => $c->getMenu()->getMenuId(),
            'menu_titre'               => $c->getMenu()->getTitre(),
            'statut'                   => $c->getStatut()->value,
            'statut_label'             => $c->getStatut()->label(),
            'date_prestation'          => $c->getDatePrestation()->format('Y-m-d'),
            'heure_livraison'          => $c->getHeureLivraison()->format('H:i'),
            'adresse_livraison'        => $c->getAdresseLivraison(),
            'ville_livraison'          => $c->getVilleLivraison(),
            'nombre_personnes'         => $c->getNombrePersonnes(),
            'nombre_personnes_minimum' => $c->getMenu()->getNombrePersonneMinimum(),
            'prix_menu'                => $c->getPrixMenu(),
            'reduction'                => $c->getReduction(),
            'prix_livraison'           => $c->getPrixLivraison(),
            'prix_total'               => $c->getPrixTotal(),
            'pret_materiel'            => $c->isPretMateriel(),
            'restitution_materiel'     => $c->isRestitutionMateriel(),
            'motif_annulation'         => $c->getMotifAnnulation(),
            'date_commande'            => $c->getDateCommande()->format(\DateTimeInterface::ATOM),
            'historique_statuts'       => $this->historiqueToArray($c),
            'transitions_autorisees'   => array_map(
                fn($s) => ['statut' => $s->value, 'label' => $s->label()],
                $c->getStatut()->transitionsAutorisees()
            ),
            'autorise_modification'    => $c->getStatut()->autoriseModificationUtilisateur(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArrayEspaceEmploye(Commande $c): array
    {
        $u = $c->getUtilisateur();
        return array_merge($this->toArrayEspaceClient($c), [
            'client_prenom'          => $u->getPrenom(),
            'client_nom'             => $u->getNom(),
            'client_email'           => $u->getEmail(),
            'client_gsm'             => $u->getTelephone(),
            'mode_contact_annulation' => $c->getModeContactAnnulation(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historiqueToArray(Commande $c): array
    {
        $historique = [];
        foreach ($c->getHistoriqueStatuts() as $h) {
            $historique[] = [
                'statut'          => $h->getStatut()->value,
                'label'           => $h->getStatut()->label(),
                'date_changement' => $h->getDateChangement()->format(\DateTimeInterface::ATOM),
            ];
        }
        return $historique;
    }
}
