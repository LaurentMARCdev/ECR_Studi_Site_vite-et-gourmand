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
        // MODE DÉMO — retirer quand le back PHP est prêt
        this.menu = demoMenu();
        this.nbPersonnes = this.menu.nombre_personne_minimum;
        await this.chargerAutresMenus();
        // FIN MODE DÉMO
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
        this.autresMenus = demoAutresMenus().filter(m => m.menu_id !== this.menu?.menu_id);
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

// Données de démo — à supprimer quand l'API PHP /api/menus/:id est prête
function demoMenu() {
  return {
    menu_id: 1,
    titre: "Menu de Noël Prestige",
    description: "Un voyage gustatif pensé pour les fêtes de fin d'année. Foie gras mi-cuit maison, chapon rôti aux cèpes de Bordeaux et bûche glacée aux marrons. Chaque plat est préparé avec des produits locaux sélectionnés par Julie et José.",
    theme: "Noël", regime: "classique",
    nombre_personne_minimum: 8, prix_par_personne: 42, quantite_restante: 3,
    conditions: "Ce menu doit être commandé au minimum 7 jours avant la date de la prestation. La livraison s'effectue entre 10h et 14h. Le matériel de service (plats, couverts) est fourni en prêt et doit être restitué sous 3 jours ouvrés. Tout retard entraînera des frais de 600 € mentionnés dans les CGV.",
    images: [],
    allergenes_globaux: ["Gluten", "Lactose", "Œufs", "Céleri"],
    plats: {
      entrees: [
        { plat_id: 1, nom: "Foie gras mi-cuit maison", description: "Servi avec un chutney de figues et pain brioché toasté.", image: null, allergenes: ["Gluten", "Alcool"] },
        { plat_id: 2, nom: "Velouté de châtaignes", description: "Crème légère, chips de lard fumé et huile de truffe.", image: null, allergenes: ["Lactose"] },
      ],
      plats_principaux: [
        { plat_id: 3, nom: "Chapon rôti aux cèpes de Bordeaux", description: "Farci à la saucisse et aux herbes, jus de rôti réduit, pommes dauphine.", image: null, allergenes: ["Gluten", "Céleri"] },
      ],
      desserts: [
        { plat_id: 4, nom: "Bûche glacée aux marrons", description: "Insert crème de marron, meringue italienne, éclats de caramel.", image: null, allergenes: ["Lactose", "Œufs"] },
        { plat_id: 5, nom: "Mignardises de Noël", description: "Truffes au chocolat, rochers coco et pâtes de fruits.", image: null, allergenes: ["Lactose"] },
      ],
    },
  };
}

function demoAutresMenus() {
  return [
    { menu_id: 2, titre: "Brunch de Pâques",            nombre_personne_minimum: 6,  prix_par_personne: 28 },
    { menu_id: 3, titre: "Menu Végétarien Printemps",    nombre_personne_minimum: 4,  prix_par_personne: 24 },
    { menu_id: 4, titre: "Cocktail Dinatoire Événement", nombre_personne_minimum: 20, prix_par_personne: 35 },
  ];
}
