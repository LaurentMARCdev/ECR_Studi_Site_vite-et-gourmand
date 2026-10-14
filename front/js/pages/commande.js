/**
 * Fonction principale Alpine — retourne l'état + les méthodes de la page.
 * Alpine appelle init() au montage.
 */
function commandeApp() {
  return {
    // ─── État général ───────────────────────────────────
    chargementInitial: true,        // Vrai tant que la page n'est pas prête
    erreurInitiale:    null,        // Message d'erreur bloquant (menu introuvable, non connecté...)
    chargement:        false,       // Vrai pendant l'envoi du POST

    utilisateur: null,              // Info utilisateur connecté (via /auth/me)
    menu:        null,              // Détail du menu chargé (via /menus/{id})

    // ─── Formulaire ─────────────────────────────────────
    form: {
      menuId:            0,
      nombrePersonnes:   1,
      datePrestation:    '',
      heureLivraison:    '',
      adresseLivraison:  '',
      villeLivraison:    '',
      pretMateriel:      false,
    },
    erreurs: {},                    // Erreurs de validation champ par champ (+ .global)

    // ─── Toast (feedback UI) ────────────────────────────
    toast: { visible: false, message: '', type: 'succes' },

    // ─── Données calculées (getters Alpine) ─────────────

    /**
     * Date minimum acceptée (J+15) au format YYYY-MM-DD.
     * Utilisée pour l'attribut `min` du champ date.
     */
    get dateMinimum() {
      const d = new Date();
      d.setDate(d.getDate() + 15);
      return d.toISOString().split('T')[0];
    },

    /**
     * Sous-total menu = prix par personne × nombre de personnes.
     */
    get sousTotalMenu() {
      if (!this.menu) return 0;
      return (this.menu.prix_par_personne || 0) * (this.form.nombrePersonnes || 0);
    },

    /**
     * Frais de livraison (estimation front).
     * Le back-end recalcule précisément selon la distance réelle.
     * Formule stub : 5€ de base + estimation forfaitaire.
     * Note : cette valeur est indicative, elle sera confirmée après validation.
     */
    get fraisLivraison() {
      // Stub : 5€ fixes pour l'estimation front
      // Le vrai calcul se fait côté back via DistanceCalculatorInterface
      return 5;
    },

    /**
     * Réduction : 10% appliqués si le sous-total menu > 500€.
     */
    get reductionAppliquee() {
      return this.sousTotalMenu > 500 ? this.sousTotalMenu * 0.10 : 0;
    },

    /**
     * Total final affiché : sous-total menu + livraison - réduction.
     */
    get totalFinal() {
      return this.sousTotalMenu + this.fraisLivraison - this.reductionAppliquee;
    },

    // ─── Init : appelé au montage ───────────────────────
    async init() {
      // 1. Récupérer les paramètres URL (?menu=X&personnes=Y)
      const params = new URLSearchParams(window.location.search);
      const menuId = parseInt(params.get('menu'), 10);
      const personnes = parseInt(params.get('personnes'), 10);

      if (!menuId || menuId < 1) {
        this.erreurInitiale = 'Aucun menu sélectionné. Retournez au catalogue pour choisir un menu.';
        this.chargementInitial = false;
        return;
      }

      this.form.menuId = menuId;
      this.form.nombrePersonnes = personnes && personnes >= 1 ? personnes : 1;

      // 2. Vérifier que l'utilisateur est connecté
      await this.verifierConnexion();
      if (this.erreurInitiale) {
        this.chargementInitial = false;
        return;
      }

      // 3. Charger les détails du menu
      await this.chargerMenu();

      // 4. Fin du chargement initial
      this.chargementInitial = false;
    },

    /**
     * Vérifie que l'utilisateur est connecté.
     * Si non → redirection vers /connexion.html avec l'URL de retour.
     */
    async verifierConnexion() {
      try {
        const res = await fetch(`${API_BASE}/auth/me`, {
          credentials: 'include',
          headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) {
          // Non connecté → redirection avec URL de retour
          const retour = encodeURIComponent(window.location.pathname + window.location.search);
          window.location.href = `/connexion.html?retour=${retour}`;
          return;
        }
        this.utilisateur = await res.json();
      } catch (e) {
        this.erreurInitiale = 'Impossible de vérifier votre connexion. Vérifiez votre réseau.';
      }
    },

    /**
     * Charge les détails du menu via GET /api/menus/{id}.
     */
    async chargerMenu() {
      try {
        const res = await fetch(`${API_BASE}/menus/${this.form.menuId}`, {
          credentials: 'include',
          headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) {
          if (res.status === 404) {
            this.erreurInitiale = 'Ce menu n\'existe pas ou n\'est plus disponible.';
          } else {
            this.erreurInitiale = 'Erreur lors du chargement du menu.';
          }
          return;
        }
        this.menu = await res.json();

        // Ajuster le nombre de personnes au minimum requis si nécessaire
        if (this.menu.nombre_personne_minimum && this.form.nombrePersonnes < this.menu.nombre_personne_minimum) {
          this.form.nombrePersonnes = this.menu.nombre_personne_minimum;
        }
      } catch (e) {
        this.erreurInitiale = 'Erreur réseau lors du chargement du menu.';
      }
    },

    /**
     * Formate un prix en euros (2 décimales, symbole €).
     */
    formatPrix(montant) {
      return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(montant || 0);
    },

    /**
     * Affiche un toast de feedback (disparaît après 3s).
     */
    afficherToast(message, type = 'succes') {
      this.toast = { visible: true, message, type };
      setTimeout(() => { this.toast.visible = false; }, 3000);
    },

    /**
     * Validation front minimale avant envoi au back.
     * Retourne true si tout est OK, false sinon (et remplit this.erreurs).
     */
    validerFront() {
      this.erreurs = {};
      let ok = true;

      if (!this.form.adresseLivraison || this.form.adresseLivraison.trim().length === 0) {
        this.erreurs.adresseLivraison = 'L\'adresse de livraison est obligatoire.';
        ok = false;
      }
      if (!this.form.villeLivraison || this.form.villeLivraison.trim().length === 0) {
        this.erreurs.villeLivraison = 'La ville est obligatoire.';
        ok = false;
      }
      if (!this.form.datePrestation) {
        this.erreurs.datePrestation = 'La date de prestation est obligatoire.';
        ok = false;
      }
      if (!this.form.heureLivraison) {
        this.erreurs.heureLivraison = 'L\'heure de livraison est obligatoire.';
        ok = false;
      }

      return ok;
    },

    /**
     * Soumission du formulaire — envoie POST /api/commandes.
     */
    async soumettre() {
      // 1. Validation front
      if (!this.validerFront()) {
        this.afficherToast('Veuillez corriger les champs invalides.', 'erreur');
        return;
      }

      this.chargement = true;
      try {
        // 2. Construction du body (aligné sur CreerCommandeDTO)
        const body = {
          menuId:            this.form.menuId,
          nombrePersonnes:   this.form.nombrePersonnes,
          datePrestation:    this.form.datePrestation,
          heureLivraison:    this.form.heureLivraison,
          adresseLivraison:  this.form.adresseLivraison.trim(),
          villeLivraison:    this.form.villeLivraison.trim(),
          pretMateriel:      this.form.pretMateriel,
        };

        // 3. Envoi
        const res = await fetch(`${API_BASE}/commandes`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json'
          },
          body: JSON.stringify(body)
        });

        const data = await res.json().catch(() => ({}));

        // 4. Gestion de la réponse
        if (!res.ok) {
          // Erreur de validation : afficher les détails champ par champ
          if (data.details && typeof data.details === 'object') {
            this.erreurs = { ...this.erreurs, ...data.details };
            this.erreurs.global = data.erreur || 'Données invalides. Vérifiez le formulaire.';
          } else {
            this.erreurs.global = data.erreur || 'Une erreur est survenue. Réessayez.';
          }
          this.afficherToast(this.erreurs.global, 'erreur');
          return;
        }

        // 5. Succès !
        this.afficherToast('Commande enregistrée ! Redirection…', 'succes');
        // Petite pause pour laisser voir le toast puis redirection
        setTimeout(() => {
          window.location.href = '/mon-espace.html';
        }, 1500);

      } catch (e) {
        this.erreurs.global = 'Erreur réseau : impossible d\'envoyer votre commande.';
        this.afficherToast(this.erreurs.global, 'erreur');
      } finally {
        this.chargement = false;
      }
    },
  };
}
