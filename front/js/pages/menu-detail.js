function detailApp() {
  return {
    menuOpen:     false,
    loading:      true,
    erreur:       null,
    menu:         null,
    autresMenus:  [],
    galerieIndex: 0,
    nbPersonnes:  1,
    estConnecte:  false,
    toast: { visible: false, message: '' },

    horaires: [
      { jour: 'Lundi',    heure: '08h00 – 19h00' },
      { jour: 'Mardi',    heure: '08h00 – 19h00' },
      { jour: 'Mercredi', heure: '08h00 – 19h00' },
      { jour: 'Jeudi',    heure: '08h00 – 19h00' },
      { jour: 'Vendredi', heure: '08h00 – 20h00' },
      { jour: 'Samedi',   heure: '09h00 – 18h00' },
      { jour: 'Dimanche', heure: 'Fermé' },
    ],

    async init() {
      await Promise.all([ this.chargerMenu(), this.verifierConnexion() ]);
      this.initReveal();
    },

    getMenuId() {
      const parts = window.location.pathname.split('/').filter(Boolean);
      return parts[parts.length - 1];
    },

    async chargerMenu() {
      this.loading = true;
      this.erreur  = null;
      try {
        /*
         * GET /api/menus/:id
         * Réponse attendue :
         * {
         *   menu_id, titre, description, theme, regime,
         *   nombre_personne_minimum, prix_par_personne,
         *   quantite_restante, conditions,
         *   images: ["url1", "url2"],
         *   allergenes_globaux: ["Gluten", ...],
         *   plats: {
         *     entrees:          [{ plat_id, nom, description, image, allergenes }],
         *     plats_principaux: [...],
         *     desserts:         [...]
         *   }
         * }
         */
        const res = await fetch(`${API_BASE}/menus/${this.getMenuId()}`, {
          headers: { 'Accept': 'application/json' },
          credentials: 'include',
        });
        if (res.status === 404) throw new Error("Ce menu n'existe pas ou a été supprimé.");
        if (!res.ok) throw new Error(`Erreur serveur (${res.status})`);
        this.menu = await res.json();
        this.nbPersonnes = this.menu.nombre_personne_minimum;
        await this.chargerAutresMenus();
      } catch (err) {
        this.erreur = err.message || 'Impossible de charger ce menu.';
      } finally {
        this.loading = false;
      }
    },

    async chargerAutresMenus() {
      try {
        const res = await fetch(`${API_BASE}/menus`, { headers: { 'Accept': 'application/json' }, credentials: 'include' });
        if (!res.ok) throw new Error();
        const tous = await res.json();
        this.autresMenus = tous.filter(m => m.menu_id !== this.menu?.menu_id).slice(0, 3);
      } catch {
        this.autresMenus = [];   // section secondaire : on la masque simplement
      }
    },

    async verifierConnexion() {
      try {
        /*
         * GET /api/auth/me
         * 200 → { utilisateur_id, role, prenom }
         * 401 → non connecté
         */
        const res = await fetch(`${API_BASE}/auth/me`, { credentials: 'include', headers: { 'Accept': 'application/json' } });
        this.estConnecte = res.ok;
      } catch { this.estConnecte = false; }
    },

    decrementerPersonnes() {
      if (this.nbPersonnes > this.menu.nombre_personne_minimum) this.nbPersonnes--;
    },
    corrigerPersonnes() {
      if (!this.nbPersonnes || this.nbPersonnes < this.menu.nombre_personne_minimum)
        this.nbPersonnes = this.menu.nombre_personne_minimum;
    },

    get prixMenu() {
      return (this.menu?.prix_par_personne || 0) * this.nbPersonnes;
    },
    get reductionAppliquee() {
      if (!this.menu) return false;
      return this.nbPersonnes >= this.menu.nombre_personne_minimum + 5;
    },
    get montantReduction() {
      return this.reductionAppliquee ? this.prixMenu * 0.1 : 0;
    },
    get prixTotal() {
      return this.prixMenu - this.montantReduction;
    },

    stockLabel() {
      if (!this.menu) return '';
      if (this.menu.quantite_restante === 0)    return 'Épuisé';
      if (this.menu.quantite_restante === null)  return 'Disponible';
      if (this.menu.quantite_restante <= 3)     return `Plus que ${this.menu.quantite_restante} disponible(s)`;
      return `${this.menu.quantite_restante} disponible(s)`;
    },
    stockClass() {
      if (!this.menu) return '';
      if (this.menu.quantite_restante === 0)  return 'text-red-600';
      if (this.menu.quantite_restante <= 3)   return 'text-rouille font-bold';
      return 'text-herbe font-semibold';
    },

    async partager() {
      const url = window.location.href;
      try {
        if (navigator.share) { await navigator.share({ title: this.menu?.titre, url }); }
        else { await navigator.clipboard.writeText(url); this.afficherToast('Lien copié dans le presse-papier !'); }
      } catch { /* annulé */ }
    },
    afficherToast(msg) {
      this.toast = { visible: true, message: msg };
      setTimeout(() => { this.toast.visible = false; }, 3000);
    },

    formatPrix(val) {
      return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val || 0);
    },
    emojiRegime(r) {
      return { 'classique': '🥩', 'végétarien': '🥕', 'vegan': '🌱' }[r] || '🍴';
    },

    initReveal() {
      const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
      }, { threshold: 0.12 });
      document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
    },
  };
}
