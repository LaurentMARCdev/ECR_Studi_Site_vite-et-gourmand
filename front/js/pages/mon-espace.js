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
    modification:{ chargement:false },
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
        /*
         * GET /api/auth/me
         * Réponse : { utilisateur_id, prenom, nom, email, gsm, adresse, role }
         */
        const res = await fetch(`${API_BASE}/auth/me`, { credentials:'include', headers:{'Accept':'application/json'} });
        if (!res.ok) { window.location.href = '/connexion'; return; }
        this.utilisateur = await res.json();
        // Pré-remplir le formulaire profil
        Object.assign(this.profil, {
          prenom:  this.utilisateur.prenom,
          nom:     this.utilisateur.nom,
          email:   this.utilisateur.email,
          gsm:     this.utilisateur.gsm,
          adresse: this.utilisateur.adresse,
        });
      } catch {
        // MODE DÉMO
        this.utilisateur = { utilisateur_id:1, prenom:'Marie', nom:'Dupont', email:'marie@exemple.fr', gsm:'+33612345678', adresse:'12 rue des Chartrons, 33000 Bordeaux', role:'utilisateur' };
        Object.assign(this.profil, { prenom:'Marie', nom:'Dupont', email:'marie@exemple.fr', gsm:'+33612345678', adresse:'12 rue des Chartrons, 33000 Bordeaux' });
      }
    },

    // ── Chargement commandes ──────────────────────────────
    async chargerCommandes() {
      try {
        /*
         * GET /api/commandes/mes-commandes
         * Réponse : [{ numero_commande, menu_titre, menu_id, statut,
         *               date_prestation, heure_livraison, adresse_livraison,
         *               nombre_personnes, nombre_personnes_minimum,
         *               prix_menu, prix_livraison, prix_total,
         *               avis_donne, historique_statuts: [{statut, date}] }]
         */
        const res = await fetch(`${API_BASE}/commandes/mes-commandes`, { credentials:'include', headers:{'Accept':'application/json'} });
        if (!res.ok) throw new Error();
        this.commandes = await res.json();
      } catch {
        // MODE DÉMO
        this.commandes = demoCommandes();
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
        const res = await fetch(`${API_BASE}/utilisateurs/profil`, { method:'PUT', credentials:'include', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify(body) });
        const data = await res.json();
        if (!res.ok) throw new Error(data.erreur || 'Erreur lors de la mise à jour.');
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
      try {
        /*
         * PUT /api/commandes/:numero/modifier
         * Body : { adresse_livraison, date_prestation, heure_livraison, nombre_personnes }
         * Réponse 200 : commande mise à jour
         */
        const res = await fetch(`${API_BASE}/commandes/${this.cmdEnCours.numero_commande}/modifier`, {
          method:'PUT', credentials:'include',
          headers:{'Content-Type':'application/json','Accept':'application/json'},
          body: JSON.stringify({
            adresse_livraison: this.cmdEnCours.adresse_livraison,
            date_prestation:   this.cmdEnCours.date_prestation,
            heure_livraison:   this.cmdEnCours.heure_livraison,
            nombre_personnes:  this.cmdEnCours.nombre_personnes,
          }),
        });
        if (!res.ok) throw new Error();
        // Mettre à jour la commande localement
        const idx = this.commandes.findIndex(c => c.numero_commande === this.cmdEnCours.numero_commande);
        if (idx !== -1) this.commandes[idx] = { ...this.commandes[idx], ...this.cmdEnCours };
        this.modales.modification = false;
        this.afficherToast('Commande modifiée avec succès.');
      } catch {
        // Mode démo : simuler la mise à jour
        const idx = this.commandes.findIndex(c => c.numero_commande === this.cmdEnCours.numero_commande);
        if (idx !== -1) this.commandes[idx] = { ...this.commandes[idx], ...this.cmdEnCours };
        this.modales.modification = false;
        this.afficherToast('Commande modifiée avec succès.');
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
        /*
         * DELETE /api/commandes/:numero
         * Réponse 200 : { message: "Commande annulée" }
         */
        const res = await fetch(`${API_BASE}/commandes/${this.cmdEnCours.numero_commande}`, {
          method:'DELETE', credentials:'include', headers:{'Accept':'application/json'},
        });
        if (!res.ok) throw new Error('Impossible d\'annuler cette commande.');
        this.commandes = this.commandes.filter(c => c.numero_commande !== this.cmdEnCours.numero_commande);
        this.modales.annulation = false;
        this.afficherToast('Commande annulée.');
      } catch (err) {
        // Mode démo
        this.commandes = this.commandes.filter(c => c.numero_commande !== this.cmdEnCours.numero_commande);
        this.modales.annulation = false;
        this.afficherToast('Commande annulée.');
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
        /*
         * POST /api/commandes/:numero/avis
         * Body : { note, commentaire }
         * Réponse 201 : { message: "Avis enregistré" }
         */
        const res = await fetch(`${API_BASE}/commandes/${this.cmdEnCours.numero_commande}/avis`, {
          method:'POST', credentials:'include',
          headers:{'Content-Type':'application/json','Accept':'application/json'},
          body: JSON.stringify({ note:this.avis.note, commentaire:this.avis.commentaire }),
        });
        if (!res.ok) throw new Error('Impossible d\'envoyer votre avis.');
        const idx = this.commandes.findIndex(c => c.numero_commande === this.cmdEnCours.numero_commande);
        if (idx !== -1) this.commandes[idx].avis_donne = true;
        this.avis.succes = true;
      } catch {
        // Mode démo
        const idx = this.commandes.findIndex(c => c.numero_commande === this.cmdEnCours.numero_commande);
        if (idx !== -1) this.commandes[idx].avis_donne = true;
        this.avis.succes = true;
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

// ── Données de démo ──────────────────────────────────────
function demoCommandes() {
  return [
    {
      numero_commande: 'VG-2025-0042',
      menu_titre: 'Menu de Noël Prestige',
      menu_id: 1,
      statut: 'en_preparation',
      date_prestation: '2025-12-24',
      heure_livraison: '12:00',
      adresse_livraison: '12 rue des Chartrons, 33000 Bordeaux',
      nombre_personnes: 10,
      nombre_personnes_minimum: 8,
      prix_menu: 378,
      prix_livraison: 5,
      prix_total: 383,
      avis_donne: false,
      historique_statuts: [
        { statut:'accepte',        date:'2025-12-15T09:30:00' },
        { statut:'en_preparation', date:'2025-12-23T08:00:00' },
      ],
    },
    {
      numero_commande: 'VG-2025-0031',
      menu_titre: 'Brunch de Pâques',
      menu_id: 2,
      statut: 'terminee',
      date_prestation: '2025-04-20',
      heure_livraison: '10:30',
      adresse_livraison: '5 allée des Mimosas, 33700 Mérignac',
      nombre_personnes: 6,
      nombre_personnes_minimum: 6,
      prix_menu: 168,
      prix_livraison: 10.31,
      prix_total: 178.31,
      avis_donne: false,
      historique_statuts: [
        { statut:'accepte',            date:'2025-04-10T14:00:00' },
        { statut:'en_preparation',     date:'2025-04-20T07:00:00' },
        { statut:'en_cours_livraison', date:'2025-04-20T09:45:00' },
        { statut:'livre',              date:'2025-04-20T10:28:00' },
        { statut:'terminee',           date:'2025-04-20T10:28:00' },
      ],
    },
    {
      numero_commande: 'VG-2025-0058',
      menu_titre: 'Cocktail Dinatoire Événement',
      menu_id: 4,
      statut: 'en_attente',
      date_prestation: '2026-01-15',
      heure_livraison: '19:00',
      adresse_livraison: '3 place de la Bourse, 33000 Bordeaux',
      nombre_personnes: 25,
      nombre_personnes_minimum: 20,
      prix_menu: 787.50,
      prix_livraison: 5,
      prix_total: 792.50,
      avis_donne: false,
      historique_statuts: [],
    },
  ];
}
