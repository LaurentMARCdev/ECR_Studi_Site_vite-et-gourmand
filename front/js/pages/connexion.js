function authApp() {
  return {
    menuOpen: false,
    onglet:   'connexion',   // 'connexion' | 'inscription'
    modaleReinitialisationOuverte: false,

    // ── État connexion ────────────────────────────────────────────────────
    connexion: {
      email: '', motDePasse: '',
      voirMdp: false, chargement: false,
      erreur: null, succes: null,
      erreurs: { email: null, motDePasse: null },
    },

    // ── État inscription ──────────────────────────────────────────────────
    inscription: {
      prenom: '', nom: '', email: '', gsm: '',
      adresse: '', motDePasse: '', confirmation: '',
      rgpd: false, voirMdp: false, chargement: false,
      erreur: null, succes: false,
      erreurs: {},
      forcePct: 0, forceCouleur: '#ccc', forceLabel: '',
    },

    // Règles mot de passe affichées en temps réel
    reglesMdp: [
      { id: 'len',     label: '10 caractères minimum',          valide: false },
      { id: 'upper',   label: 'Au moins une majuscule',         valide: false },
      { id: 'lower',   label: 'Au moins une minuscule',         valide: false },
      { id: 'digit',   label: 'Au moins un chiffre',            valide: false },
      { id: 'special', label: 'Au moins un caractère spécial',  valide: false },
    ],

    // ── État réinitialisation ─────────────────────────────────────────────
    reinitialisation: {
      email: '', chargement: false,
      erreur: null, succes: false,
      erreurs: { email: null },
    },

    // ── Init ──────────────────────────────────────────────────────────────
    init() {
      // Si ?tab=inscription dans l'URL (ex: depuis le bouton "commander" sans compte)
      const params = new URLSearchParams(window.location.search);
      if (params.get('tab') === 'inscription') this.onglet = 'inscription';
    },

    // ── Reset messages entre les onglets ──────────────────────────────────
    resetMessages() {
      this.connexion.erreur  = null;
      this.connexion.succes  = null;
      this.inscription.erreur = null;
    },

    // ════════════════════════════════════════
    // CONNEXION
    // ════════════════════════════════════════
    validerConnexion() {
      this.connexion.erreurs = { email: null, motDePasse: null };
      let ok = true;

      if (!this.connexion.email) {
        this.connexion.erreurs.email = 'L\'adresse e-mail est obligatoire.'; ok = false;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.connexion.email)) {
        this.connexion.erreurs.email = 'Format d\'e-mail invalide.'; ok = false;
      }

      if (!this.connexion.motDePasse) {
        this.connexion.erreurs.motDePasse = 'Le mot de passe est obligatoire.'; ok = false;
      }

      return ok;
    },

    async soumettreConnexion() {
      if (!this.validerConnexion()) return;

      this.connexion.chargement = true;
      this.connexion.erreur     = null;
      this.connexion.succes     = null;

      try {
        /*
         * POST /api/auth/login
         * Body JSON : { email, motDePasse }
         * Réponse attendue :
         *   200 → { message, role, redirect }
         *   401 → { erreur: "Identifiants incorrects" }
         *   403 → { erreur: "Compte désactivé" }
         */
        const response = await fetch(`${API_BASE}/auth/login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            email:      this.connexion.email,
            motDePasse: this.connexion.motDePasse,
          }),
        });

        const data = await response.json();

        if (!response.ok) {
          this.connexion.erreur = data.erreur || 'Identifiants incorrects.';
          return;
        }

        // Connexion réussie → redirection selon le rôle
        this.connexion.succes = 'Connexion réussie ! Redirection…';
        setTimeout(() => {
          const redirections = {
            administrateur: '/admin',
            employe:        '/employe',
            utilisateur:    '/mon-espace',
          };
          window.location.href = data.redirect || redirections[data.role] || '/';
        }, 800);

      } catch (err) {
        this.connexion.erreur = 'Impossible de contacter le serveur. Vérifiez votre connexion.';
      } finally {
        this.connexion.chargement = false;
      }
    },

    // ════════════════════════════════════════
    // INSCRIPTION
    // ════════════════════════════════════════
    evaluerForceMdp() {
      const mdp = this.inscription.motDePasse;

      this.reglesMdp[0].valide = mdp.length >= 10;
      this.reglesMdp[1].valide = /[A-Z]/.test(mdp);
      this.reglesMdp[2].valide = /[a-z]/.test(mdp);
      this.reglesMdp[3].valide = /[0-9]/.test(mdp);
      this.reglesMdp[4].valide = /[^A-Za-z0-9]/.test(mdp);

      const score = this.reglesMdp.filter(r => r.valide).length;

      const niveaux = [
        { pct: 0,   couleur: '#ccc',     label: '' },
        { pct: 20,  couleur: '#DC2626',  label: 'Très faible' },
        { pct: 40,  couleur: '#F97316',  label: 'Faible' },
        { pct: 60,  couleur: '#EAB308',  label: 'Moyen' },
        { pct: 80,  couleur: '#84CC16',  label: 'Fort' },
        { pct: 100, couleur: '#22C55E',  label: 'Très fort' },
      ];
      const n = niveaux[score];
      this.inscription.forcePct    = n.pct;
      this.inscription.forceCouleur = n.couleur;
      this.inscription.forceLabel  = n.label;
    },

    validerInscription() {
      this.inscription.erreurs = {};
      let ok = true;

      if (!this.inscription.prenom.trim()) {
        this.inscription.erreurs.prenom = 'Le prénom est obligatoire.'; ok = false;
      }
      if (!this.inscription.nom.trim()) {
        this.inscription.erreurs.nom = 'Le nom est obligatoire.'; ok = false;
      }
      if (!this.inscription.email) {
        this.inscription.erreurs.email = 'L\'adresse e-mail est obligatoire.'; ok = false;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.inscription.email)) {
        this.inscription.erreurs.email = 'Format d\'e-mail invalide.'; ok = false;
      }
      if (!this.inscription.gsm.trim()) {
        this.inscription.erreurs.gsm = 'Le numéro de téléphone est obligatoire.'; ok = false;
      } else if (!/^(\+?\d[\d\s\-().]{6,18}\d)$/.test(this.inscription.gsm.trim())) {
        this.inscription.erreurs.gsm = 'Format de numéro invalide.'; ok = false;
      }
      if (!this.inscription.adresse.trim()) {
        this.inscription.erreurs.adresse = 'L\'adresse postale est obligatoire.'; ok = false;
      }

      const mdpOk = this.reglesMdp.every(r => r.valide);
      if (!this.inscription.motDePasse) {
        this.inscription.erreurs.motDePasse = 'Le mot de passe est obligatoire.'; ok = false;
      } else if (!mdpOk) {
        this.inscription.erreurs.motDePasse = 'Le mot de passe ne respecte pas tous les critères.'; ok = false;
      }

      if (this.inscription.motDePasse !== this.inscription.confirmation) {
        this.inscription.erreurs.confirmation = 'Les mots de passe ne correspondent pas.'; ok = false;
      }
      if (!this.inscription.rgpd) {
        this.inscription.erreurs.rgpd = 'Vous devez accepter la politique de confidentialité.'; ok = false;
      }

      return ok;
    },

    async soumettreInscription() {
      if (!this.validerInscription()) return;

      this.inscription.chargement = true;
      this.inscription.erreur     = null;

      try {
        /*
         * POST /api/auth/inscription
         * Body JSON : { prenom, nom, email, gsm, adresse, motDePasse }
         * Réponse attendue :
         *   201 → { message: "Compte créé" }
         *   409 → { erreur: "E-mail déjà utilisé" }
         *   422 → { erreur: "..." }
         */
        const response = await fetch(`${API_BASE}/auth/inscription`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            prenom:     this.inscription.prenom,
            nom:        this.inscription.nom,
            email:      this.inscription.email,
            gsm:        this.inscription.gsm,
            adresse:    this.inscription.adresse,
            motDePasse: this.inscription.motDePasse,
          }),
        });

        const data = await response.json();

        if (!response.ok) {
          // Email déjà utilisé
          if (response.status === 409) {
            this.inscription.erreurs.email = 'Cette adresse e-mail est déjà associée à un compte.';
          } else {
            this.inscription.erreur = data.erreur || 'Une erreur est survenue lors de la création du compte.';
          }
          return;
        }

        // Succès → affiche le message de confirmation
        this.inscription.succes = true;

      } catch (err) {
        this.inscription.erreur = 'Impossible de contacter le serveur. Vérifiez votre connexion.';
      } finally {
        this.inscription.chargement = false;
      }
    },

    // ════════════════════════════════════════
    // RÉINITIALISATION MOT DE PASSE
    // ════════════════════════════════════════
    ouvrirReinitialisationMdp() {
      this.reinitialisation = {
        email: this.connexion.email,  // pré-remplit si déjà saisi
        chargement: false, erreur: null, succes: false,
        erreurs: { email: null },
      };
      this.modaleReinitialisationOuverte = true;
    },

    async soumettreReinitialisation() {
      this.reinitialisation.erreurs = { email: null };

      if (!this.reinitialisation.email) {
        this.reinitialisation.erreurs.email = 'L\'adresse e-mail est obligatoire.'; return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.reinitialisation.email)) {
        this.reinitialisation.erreurs.email = 'Format d\'e-mail invalide.'; return;
      }

      this.reinitialisation.chargement = true;
      this.reinitialisation.erreur     = null;

      try {
        /*
         * POST /api/auth/mot-de-passe-oublie
         * Body JSON : { email }
         * Réponse attendue :
         *   200 → { message: "Lien envoyé" }   (même si l'email n'existe pas, pour ne pas divulguer)
         */
        const response = await fetch(`${API_BASE}/auth/mot-de-passe-oublie`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ email: this.reinitialisation.email }),
        });

        // On affiche toujours le succès (sécurité : ne pas révéler si l'email existe)
        this.reinitialisation.succes = true;

      } catch (err) {
        this.reinitialisation.erreur = 'Impossible d\'envoyer l\'e-mail. Réessayez plus tard.';
      } finally {
        this.reinitialisation.chargement = false;
      }
    },
  };
}
