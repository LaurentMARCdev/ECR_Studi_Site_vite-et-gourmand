/**
 * Suivi des commandes et modération des avis, partagés par l'espace
 * employé et l'espace administrateur (routes /api/employe/*, accessibles
 * à l'administrateur par héritage de rôle).
 *
 * Utilisation : return composer(gestionCommandes(), gestionAvis(), { ... });
 * Le composant doit fournir afficherToast(message) et un objet modales
 * contenant la clé "annulation".
 */
const STATUTS_COMMANDE = [
  { val: 'en_attente',         label: 'En attente' },
  { val: 'accepte',            label: 'Acceptée' },
  { val: 'en_preparation',     label: 'En préparation' },
  { val: 'en_cours_livraison', label: 'En livraison' },
  { val: 'livre',              label: 'Livrée' },
  { val: 'attente_materiel',   label: 'Retour matériel' },
  { val: 'terminee',           label: 'Terminée' },
  { val: 'annulee',            label: 'Annulée' },
];

// Mêmes transitions que l'enum StatutCommande côté serveur
const FLUX_STATUTS = {
  en_attente:         ['accepte'],
  accepte:            ['en_preparation'],
  en_preparation:     ['en_cours_livraison'],
  en_cours_livraison: ['livre'],
  livre:              ['attente_materiel', 'terminee'],
  attente_materiel:   ['terminee'],
};

function gestionCommandes() {
  return {
    statutsDisponibles: STATUTS_COMMANDE,
    commandes:          [],
    commandesFiltrees:  [],
    erreurCommandes:    null,
    filtreCmd:          { statut: '', client: '', date: '', menu: '' },
    cmdEnCours:         null,
    annulation:         { modeContact: '', motif: '', chargement: false, erreurs: {} },

    get nbEnAttente() { return this.commandes.filter(c => c.statut === 'en_attente').length; },

    async chargerCommandes() {
      this.erreurCommandes = null;
      try {
        this.commandes = await apiFetch('/employe/commandes');
      } catch (e) {
        this.commandes = [];
        this.erreurCommandes = 'Impossible de charger les commandes : ' + e.message;
      }
      this.filtrerCommandes();
    },

    filtrerCommandes() {
      const q = (this.filtreCmd.client || '').toLowerCase();
      this.commandesFiltrees = this.commandes.filter(c => {
        if (q && !`${c.client_prenom} ${c.client_nom} ${c.client_email}`.toLowerCase().includes(q)) return false;
        if (this.filtreCmd.statut && c.statut !== this.filtreCmd.statut) return false;
        if (this.filtreCmd.date && c.date_prestation !== this.filtreCmd.date) return false;
        if (this.filtreCmd.menu && String(c.menu_id) !== String(this.filtreCmd.menu)) return false;
        return true;
      });
    },

    commandesParStatut(statut) {
      return this.commandesFiltrees.filter(c => c.statut === statut);
    },

    statutsSuivants(actuel) {
      return (FLUX_STATUTS[actuel] || []).map(v => STATUTS_COMMANDE.find(s => s.val === v));
    },

    peutAnnuler(statut) {
      return ['en_attente', 'accepte', 'en_preparation'].includes(statut);
    },

    labelStatut(statut) {
      const s = STATUTS_COMMANDE.find(x => x.val === statut);
      return s ? s.label : statut;
    },

    async changerStatut(cmd, nouveauStatut) {
      try {
        await apiFetch(`/employe/commandes/${cmd.numero_commande}/statut`, { method: 'PUT', body: { statut: nouveauStatut } });
        cmd.statut = nouveauStatut;
        this.filtrerCommandes();
        this.afficherToast(`Statut : ${this.labelStatut(nouveauStatut)}.`);
      } catch (e) {
        this.afficherToast('Échec : ' + e.message);
      }
    },

    ouvrirDetailCommande(cmd) {
      this.cmdEnCours = cmd;
      this.modales.detail = true;
    },

    ouvrirAnnulation(cmd) {
      this.cmdEnCours = cmd;
      this.annulation = { modeContact: '', motif: '', chargement: false, erreurs: {} };
      this.modales.annulation = true;
    },

    async soumettreAnnulation() {
      this.annulation.erreurs = {};
      if (!this.annulation.modeContact) { this.annulation.erreurs.modeContact = 'Obligatoire.'; return; }
      if (!this.annulation.motif.trim()) { this.annulation.erreurs.motif = 'Le motif est obligatoire.'; return; }

      this.annulation.chargement = true;
      try {
        await apiFetch(`/employe/commandes/${this.cmdEnCours.numero_commande}`, {
          method: 'DELETE',
          body: { modeContact: this.annulation.modeContact, motif: this.annulation.motif },
        });
        this.cmdEnCours.statut = 'annulee';
        this.filtrerCommandes();
        this.modales.annulation = false;
        this.afficherToast('Commande annulée.');
      } catch (e) {
        this.annulation.erreurs.global = e.message;
      } finally {
        this.annulation.chargement = false;
      }
    },
  };
}

function gestionAvis() {
  return {
    avisEnAttente: [],
    erreurAvis:    null,

    async chargerAvis() {
      this.erreurAvis = null;
      try {
        this.avisEnAttente = await apiFetch('/employe/avis?statut=en_attente');
      } catch (e) {
        this.avisEnAttente = [];
        this.erreurAvis = 'Impossible de charger les avis : ' + e.message;
      }
    },

    async validerAvis(avis) {
      try {
        await apiFetch(`/employe/avis/${avis.avis_id}/valider`, { method: 'PUT' });
        this.avisEnAttente = this.avisEnAttente.filter(a => a.avis_id !== avis.avis_id);
        this.afficherToast('Avis publié sur la page d\'accueil.');
      } catch (e) {
        this.afficherToast('Échec : ' + e.message);
      }
    },

    async refuserAvis(avis) {
      try {
        await apiFetch(`/employe/avis/${avis.avis_id}/refuser`, { method: 'DELETE' });
        this.avisEnAttente = this.avisEnAttente.filter(a => a.avis_id !== avis.avis_id);
        this.afficherToast('Avis refusé.');
      } catch (e) {
        this.afficherToast('Échec : ' + e.message);
      }
    },
  };
}
