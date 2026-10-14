/**
 * Espace employé : commandes (kanban / liste), menus et plats,
 * horaires d'ouverture, modération des avis.
 *
 * Dépend de : js/commun/api.js, js/commun/gestion-catalogue.js,
 *             js/commun/gestion-commandes.js
 */
function employeApp() {
  return composer(gestionCommandes(), gestionAvis(), gestionCatalogue(), {
    section:   'commandes',
    employe:   null,
    vueKanban: true,

    modales: { detail: false, menu: false, plat: false, annulation: false },

    horaires: { liste: [], chargement: false, succes: false, erreur: null },

    toast: { visible: false, message: '' },

    // Colonnes du kanban (les commandes annulées n'y figurent pas)
    colonnesKanban: [
      { statut: 'en_attente',         label: 'En attente',      bg: '#FEF9EC', color: '#92400E' },
      { statut: 'accepte',            label: 'Acceptée',        bg: '#EFF6FF', color: '#1E40AF' },
      { statut: 'en_preparation',     label: 'En préparation',  bg: '#FDF2F8', color: '#9D174D' },
      { statut: 'en_cours_livraison', label: 'En livraison',    bg: '#F5F3FF', color: '#5B21B6' },
      { statut: 'livre',              label: 'Livrée',          bg: '#F0FDF4', color: '#166534' },
      { statut: 'attente_materiel',   label: 'Retour matériel', bg: '#FFF7ED', color: '#C2410C' },
      { statut: 'terminee',           label: 'Terminée',        bg: '#F0FDF4', color: '#15803D' },
    ],

    // Appelée automatiquement par Alpine au montage du composant
    async init() {
      const autorise = await this.chargerEmploye();
      if (!autorise) return;
      await Promise.all([
        this.chargerCommandes(),
        this.chargerCatalogue(),
        this.chargerAvis(),
        this.chargerHoraires(),
      ]);
    },

    // ── Authentification ──────────────────────────────────────
    async chargerEmploye() {
      try {
        const utilisateur = await apiFetch('/auth/me');
        if (!['employe', 'administrateur'].includes(utilisateur.role)) {
          window.location.href = '/';
          return false;
        }
        this.employe = utilisateur;
        return true;
      } catch {
        window.location.href = '/connexion';
        return false;
      }
    },

    async seDeconnecter() {
      try { await apiFetch('/auth/logout', { method: 'POST' }); } catch { /* session déjà fermée */ }
      window.location.href = '/connexion';
    },

    // ── Horaires ──────────────────────────────────────────────
    async chargerHoraires() {
      this.horaires.erreur = null;
      try {
        this.horaires.liste = await apiFetch('/horaires');
      } catch (e) {
        this.horaires.erreur = 'Impossible de charger les horaires : ' + e.message;
      }
    },

    async sauvegarderHoraires() {
      this.horaires.chargement = true;
      this.horaires.succes = false;
      this.horaires.erreur = null;
      try {
        // Le DTO HoraireDTO attend des noms en camelCase ; un jour fermé n'a pas d'heures
        const corps = this.horaires.liste.map(h => ({
          horaireId:      h.horaire_id,
          ferme:          !!h.ferme,
          heureOuverture: h.ferme ? null : (h.heure_ouverture || null),
          heureFermeture: h.ferme ? null : (h.heure_fermeture || null),
        }));
        const reponse = await apiFetch('/employe/horaires', { method: 'PUT', body: corps });
        this.horaires.liste = reponse.horaires;
        this.horaires.succes = true;
        this.afficherToast('Horaires enregistrés.');
        setTimeout(() => { this.horaires.succes = false; }, 4000);
      } catch (e) {
        this.horaires.erreur = e.message;
      } finally {
        this.horaires.chargement = false;
      }
    },

    // ── Affichage ─────────────────────────────────────────────
    /** Message d'erreur de la section affichée (bandeau en haut de page) */
    erreurSection() {
      return {
        commandes: this.erreurCommandes,
        menus:     this.erreurCatalogue,
        horaires:  this.horaires.erreur,
        avis:      this.erreurAvis,
      }[this.section] || '';
    },

    formatPrix:      formats.prix,
    formatDateCourt: formats.dateCourte,
    formatDate:      formats.dateLongue,
    dateAujourdhui:  formats.aujourdhui,

    afficherToast(message) {
      this.toast = { visible: true, message };
      setTimeout(() => { this.toast.visible = false; }, 3000);
    },
  });
}
