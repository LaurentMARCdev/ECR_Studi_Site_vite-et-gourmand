// ─── Application principale ──────────────────────────────────────────────
function menusApp() {
  return {
    // État
    menuOpen:     false,
    loading:      true,
    erreur:       null,
    menus:        [],        // tous les menus reçus de l'API
    menusFiltres: [],        // menus après application des filtres

    // Options de filtres
    themes: [
      { value: null,          label: 'Tous',       emoji: '🍽️' },
      { value: 'Noël',        label: 'Noël',       emoji: '🎄' },
      { value: 'Pâques',      label: 'Pâques',     emoji: '🐣' },
      { value: 'classique',   label: 'Classique',  emoji: '🥗' },
      { value: 'évènement',   label: 'Événement',  emoji: '🎉' },
    ],
    regimes: [
      { value: null,          label: 'Tous',       emoji: '🍴' },
      { value: 'classique',   label: 'Classique',  emoji: '🥩' },
      { value: 'végétarien',  label: 'Végétarien', emoji: '🥕' },
      { value: 'vegan',       label: 'Vegan',      emoji: '🌱' },
    ],

    // Valeurs des filtres
    filtres: {
      theme:       null,
      regime:      null,
      prixMax:     0,       // 0 = pas de limite (slider)
      prixMin:     null,
      prixMaxInput: null,
      personnesMin: null,
    },

    // Horaires footer
    horaires: [
      { jour: 'Lundi',    heure: '08h00 – 19h00' },
      { jour: 'Mardi',    heure: '08h00 – 19h00' },
      { jour: 'Mercredi', heure: '08h00 – 19h00' },
      { jour: 'Jeudi',    heure: '08h00 – 19h00' },
      { jour: 'Vendredi', heure: '08h00 – 20h00' },
      { jour: 'Samedi',   heure: '09h00 – 18h00' },
      { jour: 'Dimanche', heure: 'Fermé' },
    ],

    // ── Init ──────────────────────────────────────────────────────────────
    init() {
      this.chargerMenus();
    },

    // ── Chargement via fetch() → API PHP ─────────────────────────────────
    async chargerMenus() {
      this.loading = true;
      this.erreur  = null;

      try {
        /*
         * GET /api/menus
         * Réponse attendue (PHP JSON) :
         * [
         *   {
         *     "menu_id": 1,
         *     "titre": "Menu de Noël",
         *     "description": "...",
         *     "theme": "Noël",
         *     "regime": "classique",
         *     "nombre_personne_minimum": 8,
         *     "prix_par_personne": 35.00,
         *     "quantite_restante": 5,
         *     "image_url": "/uploads/menus/noel.jpg",
         *     "allergenes": ["Gluten", "Lactose"]
         *   },
         *   ...
         * ]
         */
        const response = await fetch(`${API_BASE}/menus`, {
          method: 'GET',
          headers: {
            'Accept':       'application/json',
            'Content-Type': 'application/json',
          },
          credentials: 'include',   // envoie le cookie de session PHP
        });

        if (!response.ok) {
          // Gestion des codes HTTP d'erreur
          if (response.status === 401) throw new Error('Non autorisé. Veuillez vous connecter.');
          if (response.status === 500) throw new Error('Erreur serveur. Réessayez dans quelques instants.');
          throw new Error(`Erreur HTTP ${response.status}`);
        }

        const data = await response.json();

        // Validation basique de la réponse
        if (!Array.isArray(data)) throw new Error('Format de réponse inattendu.');

        this.menus = data;
        this.appliquerFiltres();

      } catch (err) {
        // Réseau hors ligne ou erreur de parsing
        if (err.name === 'TypeError') {
          this.erreur = 'Impossible de contacter le serveur. Vérifiez votre connexion.';
        } else {
          this.erreur = err.message || 'Une erreur inconnue est survenue.';
        }

      } finally {
        this.loading = false;
      }
    },

    // ── Application des filtres (sans rechargement de page) ───────────────
    appliquerFiltres() {
      this.menusFiltres = this.menus.filter(menu => {

        // Filtre thème
        if (this.filtres.theme && menu.theme !== this.filtres.theme) return false;

        // Filtre régime
        if (this.filtres.regime && menu.regime !== this.filtres.regime) return false;

        // Filtre prix max (slider)
        if (this.filtres.prixMax > 0) {
          const prixTotal = menu.prix_par_personne * menu.nombre_personne_minimum;
          if (prixTotal > this.filtres.prixMax) return false;
        }

        // Filtre fourchette de prix
        const prixTotal = menu.prix_par_personne * menu.nombre_personne_minimum;
        if (this.filtres.prixMin && prixTotal < this.filtres.prixMin) return false;
        if (this.filtres.prixMaxInput && prixTotal > this.filtres.prixMaxInput) return false;

        // Filtre personnes minimum
        if (this.filtres.personnesMin && menu.nombre_personne_minimum < this.filtres.personnesMin) return false;

        return true;
      });
    },

    // ── Toggle filtre bouton (thème / régime) ─────────────────────────────
    toggleFiltre(type, valeur) {
      // Si déjà actif → désactiver
      this.filtres[type] = this.filtres[type] === valeur ? null : valeur;
      this.appliquerFiltres();
    },

    // ── Reset tous les filtres ────────────────────────────────────────────
    resetFiltres() {
      this.filtres = { theme: null, regime: null, prixMax: 0, prixMin: null, prixMaxInput: null, personnesMin: null };
      this.appliquerFiltres();
    },

    // ── Filtres actifs ? (pour afficher le bouton Reset) ─────────────────
    get filtresActifs() {
      return this.filtres.theme || this.filtres.regime
          || this.filtres.prixMax > 0 || this.filtres.prixMin
          || this.filtres.prixMaxInput || this.filtres.personnesMin;
    },

    // ── Helpers d'affichage ───────────────────────────────────────────────
    formatPrix(valeur) {
      return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(valeur);
    },

    badgeTheme(theme) {
      const map = {
        'Noël':       'badge-noel',
        'Pâques':     'badge-paques',
        'classique':  'badge-classique',
        'évènement':  'badge-evenement',
      };
      return map[theme] || 'badge-classique';
    },

    emojiRegime(regime) {
      const map = { 'classique': '🥩', 'végétarien': '🥕', 'vegan': '🌱' };
      return map[regime] || '🍴';
    },
  };
}
