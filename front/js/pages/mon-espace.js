function espaceApp() {
  return {
    menuOpen:    false,
    loading:     true,
    onglet:      'commandes',
    utilisateur: null,
    commandes:   [],

    // Modales
    modales: { modification: false, annulation: false, avis: false },
    cmdEnCours: null,

    // États formulaires
    profil:      { prenom:'', nom:'', email:'', gsm:'', adresse:'', mdpActuel:'', mdpNouveau:'', mdpConfirm:'', chargement:false, succes:false, erreur:null, erreurs:{} },
    modification:{ chargement:false, erreur:null },
    erreurCommandes: null,
    annulation:  { chargement:false, erreur:null },
    avis:        { note:0, commentaire:'', chargement:false, succes:false, erreur:null, erreurs:{} },

    toast: { visible:false, message:'' },

    // ── Init ──────────────────────────────────────────────
    async init() {
      await Promise.all([ this.chargerProfil(), this.chargerCommandes() ]);
      this.loading = false;
    },

    // ── Chargement profil ─────────────────────────────────
    async chargerProfil() {
      try {
        this.utilisateur = await apiFetch('/auth/me');
      } catch {
        window.location.href = '/connexion';
        return;
      }
      // Pré-remplir le formulaire profil
      Object.assign(this.profil, {
        prenom:  this.utilisateur.prenom,
        nom:     this.utilisateur.nom,
        email:   this.utilisateur.email,
        gsm:     this.utilisateur.gsm,
        adresse: this.utilisateur.adresse,
      });
    },

    // ── Chargement commandes ──────────────────────────────
    async chargerCommandes() {
      this.erreurCommandes = null;
      try {
        const [commandes, mesAvis] = await Promise.all([
          apiFetch('/commandes/mes-commandes'),
          apiFetch('/avis/mes-avis'),
        ]);
        // Une commande ne peut recevoir qu'un avis : on masque le bouton si déjà donné
        const commandesNotees = new Set(mesAvis.map(a => a.numero_commande));
        this.commandes = commandes.map(c => ({ ...c, avis_donne: commandesNotees.has(c.numero_commande) }));
      } catch (e) {
        this.commandes = [];
        this.erreurCommandes = 'Impossible de charger vos commandes : ' + e.message;
      }
    },

    // ── Sauvegarder profil ────────────────────────────────
    validerProfil() {
      this.profil.erreurs = {};
      let ok = true;
      if (!this.profil.prenom.trim()) { this.profil.erreurs.prenom = 'Le prénom est obligatoire.'; ok=false; }
      if (!this.profil.nom.trim())    { this.profil.erreurs.nom    = 'Le nom est obligatoire.';    ok=false; }
      if (!this.profil.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.profil.email)) { this.profil.erreurs.email = 'E-mail invalide.'; ok=false; }
      if (!this.profil.gsm.trim())    { this.profil.erreurs.gsm    = 'Le téléphone est obligatoire.'; ok=false; }
      if (!this.profil.adresse.trim()){ this.profil.erreurs.adresse= 'L\'adresse est obligatoire.'; ok=false; }
      if (this.profil.mdpNouveau && !this.profil.mdpActuel) { this.profil.erreurs.mdpActuel = 'Saisissez votre mot de passe actuel.'; ok=false; }
      if (this.profil.mdpNouveau && this.profil.mdpNouveau !== this.profil.mdpConfirm) { this.profil.erreurs.mdpConfirm = 'Les mots de passe ne correspondent pas.'; ok=false; }
      return ok;
    },

    async sauvegarderProfil() {
      if (!this.validerProfil()) return;
      this.profil.chargement = true;
      this.profil.succes = false;
      this.profil.erreur = null;
      try {
        /*
         * PUT /api/utilisateurs/profil
         * Body : { prenom, nom, email, gsm, adresse, mdpActuel?, mdpNouveau? }
         * Réponse 200 : { message: "Profil mis à jour" }
         */
        const body = { prenom:this.profil.prenom, nom:this.profil.nom, email:this.profil.email, gsm:this.profil.gsm, adresse:this.profil.adresse };
        if (this.profil.mdpNouveau) { body.mdpActuel = this.profil.mdpActuel; body.mdpNouveau = this.profil.mdpNouveau; }
        await apiFetch('/utilisateurs/profil', { method: 'PUT', body });
        this.profil.succes = true;
        this.profil.mdpActuel = ''; this.profil.mdpNouveau = ''; this.profil.mdpConfirm = '';
        this.utilisateur.prenom = this.profil.prenom;
        this.afficherToast('Profil mis à jour avec succès !');
      } catch (err) {
        this.profil.erreur = err.message;
      } finally {
        this.profil.chargement = false;
      }
    },

    // ── Modification commande ─────────────────────────────
    ouvrirModaleModification(cmd) {
      this.cmdEnCours = { ...cmd };
      this.modales.modification = true;
    },

    async soumettreModification() {
      this.modification.chargement = true;
      this.modification.erreur = null;
      const c = this.cmdEnCours;
      try {
        // Corps attendu par ModifierCommandeDTO (le menu ne peut pas être changé)
        await apiFetch(`/commandes/${c.numero_commande}/modifier`, {
          method: 'PUT',
          body: {
            adresseLivraison: c.adresse_livraison,
            villeLivraison:   c.ville_livraison,
            datePrestation:   c.date_prestation,
            heureLivraison:   c.heure_livraison,
            nombrePersonnes:  Number(c.nombre_personnes),
          },
        });
        await this.chargerCommandes();   // prix recalculés côté serveur
        this.modales.modification = false;
        this.afficherToast('Commande modifiée avec succès.');
      } catch (e) {
        this.modification.erreur = e.message;
      } finally {
        this.modification.chargement = false;
      }
    },

    // ── Annulation commande ───────────────────────────────
    ouvrirModaleAnnulation(cmd) {
      this.cmdEnCours = cmd;
      this.annulation.erreur = null;
      this.modales.annulation = true;
    },

    async soumettreAnnulation() {
      this.annulation.chargement = true;
      this.annulation.erreur = null;
      try {
        await apiFetch(`/commandes/${this.cmdEnCours.numero_commande}`, { method: 'DELETE' });
        this.cmdEnCours.statut = 'annulee';
        await this.chargerCommandes();
        this.modales.annulation = false;
        this.afficherToast('Commande annulée.');
      } catch (e) {
        this.annulation.erreur = e.message;
      } finally {
        this.annulation.chargement = false;
      }
    },

    // ── Avis ─────────────────────────────────────────────
    ouvrirModaleAvis(cmd) {
      this.cmdEnCours = cmd;
      this.avis = { note:0, commentaire:'', chargement:false, succes:false, erreur:null, erreurs:{} };
      this.modales.avis = true;
    },

    async soumettreAvis() {
      this.avis.erreurs = {};
      if (!this.avis.note)         { this.avis.erreurs.note        = 'Choisissez une note.'; return; }
      if (!this.avis.commentaire.trim()) { this.avis.erreurs.commentaire = 'Le commentaire est obligatoire.'; return; }

      this.avis.chargement = true;
      this.avis.erreur = null;
      try {
        // POST /api/avis : l'avis reste "en attente" jusqu'à sa validation par un employé
        await apiFetch('/avis', {
          method: 'POST',
          body: { numeroCommande: this.cmdEnCours.numero_commande, note: this.avis.note, commentaire: this.avis.commentaire },
        });
        const cmd = this.commandes.find(c => c.numero_commande === this.cmdEnCours.numero_commande);
        if (cmd) cmd.avis_donne = true;
        this.avis.succes = true;
      } catch (e) {
        this.avis.erreur = e.message;
      } finally {
        this.avis.chargement = false;
      }
    },

    // ── Déconnexion ───────────────────────────────────────
    async seDeconnecter() {
      try {
        await fetch(`${API_BASE}/auth/logout`, { method:'POST', credentials:'include' });
      } finally {
        window.location.href = '/connexion';
      }
    },

    // ── Timeline : construit les étapes selon le statut ──
    timelineEtapes(cmd) {
      const sequence = [
        { statut:'accepte',            label:'Commande acceptée',       emoji:'✅' },
        { statut:'en_preparation',     label:'En préparation',           emoji:'👨‍🍳' },
        { statut:'en_cours_livraison', label:'En cours de livraison',    emoji:'🚚' },
        { statut:'livre',              label:'Livré',                    emoji:'📦' },
        { statut:'attente_materiel',   label:'Attente retour matériel',  emoji:'⏳' },
        { statut:'terminee',           label:'Commande terminée',        emoji:'🎉' },
      ];
      const idx = sequence.findIndex(e => e.statut === cmd.statut);
      return sequence.map((e, i) => {
        const histItem = cmd.historique_statuts?.find(h => h.statut === e.statut);
        return {
          ...e,
          etat: i < idx ? 'done' : i === idx ? 'current' : 'pending',
          date: histItem ? this.formatDateHeure(histItem.date) : null,
        };
      });
    },

    // ── Helpers ───────────────────────────────────────────
    labelStatut(s) {
      const map = {
        en_attente:          'En attente',
        accepte:             'Acceptée',
        en_preparation:      'En préparation',
        en_cours_livraison:  'En livraison',
        livre:               'Livrée',
        attente_materiel:    'Retour matériel',
        terminee:            'Terminée',
        annulee:             'Annulée',
      };
      return map[s] || s;
    },
    formatPrix(v) {
      return new Intl.NumberFormat('fr-FR', { style:'currency', currency:'EUR' }).format(v||0);
    },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString('fr-FR', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
    },
    formatDateHeure(d) {
      if (!d) return '';
      return new Date(d).toLocaleString('fr-FR', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' });
    },

    afficherToast(msg) {
      this.toast = { visible:true, message:msg };
      setTimeout(() => { this.toast.visible = false; }, 3000);
    },
  };
}
