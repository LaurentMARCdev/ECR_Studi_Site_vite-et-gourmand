/**
 * Espace administrateur : tableau de bord (statistiques MongoDB),
 * commandes, menus et plats, comptes employés, modération des avis.
 *
 * Commandes, avis, menus et plats reposent sur les mêmes modules que
 * l'espace employé (js/commun/gestion-*.js).
 *
 * Dépend de : js/commun/api.js, js/commun/gestion-catalogue.js,
 *             js/commun/gestion-commandes.js
 */
function adminApp() {
  return composer(gestionCommandes(), gestionAvis(), gestionCatalogue(), {
    menuOpen: false,
    section:  'dashboard',
    admin:    null,

    modales: { detail: false, menu: false, plat: false, employe: false, annulation: false },

    // ── Tableau de bord ───────────────────────────────────────
    commandesParMenu: [],   // GET /api/admin/stats/commandes-par-menu (MongoDB)
    caParMenu:        [],   // GET /api/admin/stats/chiffre-affaires   (MongoDB)
    filtreCA:         { menu: '', debut: '', fin: '' },
    noteMoyenne:      null,
    nbAvisPublies:    0,
    erreurStats:      null,

    get caFiltre() {
      if (!this.filtreCA.menu) return this.caParMenu;
      const menu = this.menusListe.find(m => String(m.menu_id) === String(this.filtreCA.menu));
      return menu ? this.caParMenu.filter(i => i.menu === menu.titre) : this.caParMenu;
    },
    get totalCA()       { return this.caFiltre.reduce((total, i) => total + i.ca, 0); },
    get maxCommandes()  { return Math.max(...this.commandesParMenu.map(i => i.count), 1); },
    get maxCA()         { return Math.max(...this.caFiltre.map(i => i.ca), 1); },
    get nbCommandesActives() { return this.commandes.filter(c => c.statut !== 'annulee').length; },

    // ── Employés ──────────────────────────────────────────────
    employes:       [],
    erreurEmployes: null,
    employeForm:    { prenom: '', nom: '', email: '', motDePasse: '', chargement: false, succes: false },
    employeFormErr: {},

    toast: { visible: false, message: '' },

    // Appelée automatiquement par Alpine au montage du composant
    async init() {
      const autorise = await this.chargerAdmin();
      if (!autorise) return;
      await Promise.all([
        this.chargerCommandes(),
        this.chargerCatalogue(),
        this.chargerEmployes(),
        this.chargerAvis(),
        this.chargerStatistiques(),
        this.chargerNoteMoyenne(),
      ]);
    },

    // ── Authentification ──────────────────────────────────────
    async chargerAdmin() {
      try {
        const utilisateur = await apiFetch('/auth/me');
        if (utilisateur.role !== 'administrateur') {
          window.location.href = '/';
          return false;
        }
        this.admin = utilisateur;
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

    // ── Statistiques (MongoDB, avec repli SQL côté serveur) ───
    async chargerStatistiques() {
      this.erreurStats = null;
      try {
        this.commandesParMenu = await apiFetch('/admin/stats/commandes-par-menu');
        await this.chargerChiffreAffaires();
      } catch (e) {
        this.erreurStats = 'Impossible de charger les statistiques : ' + e.message;
      }
    },

    async chargerChiffreAffaires() {
      const params = new URLSearchParams();
      if (this.filtreCA.debut) params.set('debut', this.filtreCA.debut);
      if (this.filtreCA.fin)   params.set('fin', this.filtreCA.fin);
      try {
        this.caParMenu = await apiFetch('/admin/stats/chiffre-affaires' + (params.toString() ? '?' + params : ''));
      } catch (e) {
        this.erreurStats = 'Impossible de charger le chiffre d\'affaires : ' + e.message;
      }
    },

    async chargerNoteMoyenne() {
      try {
        const avis = await apiFetch('/avis?limite=20');
        this.nbAvisPublies = avis.length;
        this.noteMoyenne = avis.length ? (avis.reduce((s, a) => s + a.note, 0) / avis.length).toFixed(1) : null;
      } catch {
        this.noteMoyenne = null;
      }
    },

    // ── Employés ──────────────────────────────────────────────
    async chargerEmployes() {
      this.erreurEmployes = null;
      try {
        this.employes = await apiFetch('/admin/employes');
      } catch (e) {
        this.erreurEmployes = 'Impossible de charger les employés : ' + e.message;
      }
    },

    ouvrirNouvelEmploye() {
      this.employeForm = { prenom: '', nom: '', email: '', motDePasse: '', chargement: false, succes: false };
      this.employeFormErr = {};
      this.modales.employe = true;
    },

    async soumettreEmploye() {
      const f = this.employeForm;
      this.employeFormErr = {};
      if (!f.prenom.trim()) { this.employeFormErr.prenom = 'Obligatoire.'; return; }
      if (!f.nom.trim())    { this.employeFormErr.nom = 'Obligatoire.'; return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) { this.employeFormErr.email = 'E-mail invalide.'; return; }
      if (!f.motDePasse || f.motDePasse.length < 10) { this.employeFormErr.motDePasse = 'Minimum 10 caractères.'; return; }

      f.chargement = true;
      try {
        // Le mot de passe n'est jamais envoyé par e-mail (règle du sujet)
        await apiFetch('/admin/employes', {
          method: 'POST',
          body: { prenom: f.prenom, nom: f.nom, email: f.email, motDePasse: f.motDePasse },
        });
        f.succes = true;
        await this.chargerEmployes();
      } catch (e) {
        this.employeFormErr.global = e.message;
      } finally {
        f.chargement = false;
      }
    },

    async toggleEmployeActif(emp) {
      const action = emp.actif ? 'Désactiver' : 'Réactiver';
      if (!confirm(`${action} le compte de ${emp.prenom} ${emp.nom} ?`)) return;
      try {
        await apiFetch(`/admin/employes/${emp.utilisateur_id}/toggle-actif`, { method: 'PUT' });
        emp.actif = !emp.actif;
        this.afficherToast(`Compte ${emp.actif ? 'réactivé' : 'désactivé'}.`);
      } catch (e) {
        this.afficherToast('Échec : ' + e.message);
      }
    },

    // ── Affichage ─────────────────────────────────────────────
    /** Message d'erreur de la section affichée (bandeau en haut de page) */
    erreurSection() {
      return {
        dashboard: this.erreurStats,
        commandes: this.erreurCommandes,
        menus:     this.erreurCatalogue,
        employes:  this.erreurEmployes,
        avis:      this.erreurAvis,
      }[this.section] || '';
    },

    formatPrix:     formats.prix,
    formatDate:     formats.date,
    dateAujourdhui: formats.aujourdhui,

    afficherToast(message) {
      this.toast = { visible: true, message };
      setTimeout(() => { this.toast.visible = false; }, 3000);
    },
  });
}
