/**
 * Gestion du catalogue (menus + plats), partagée par l'espace employé et
 * l'espace administrateur : les deux rôles utilisent les mêmes routes
 * /api/employe/menus et /api/employe/plats, donc le même code.
 *
 * Utilisation dans un composant Alpine :
 *   return composer(gestionCatalogue(), { ...donnees et méthodes de la page });
 * Le composant doit fournir afficherToast(message).
 */
const CATEGORIES_PLAT = [
  { val: 'entree',         label: 'Entrées' },
  { val: 'plat_principal', label: 'Plats principaux' },
  { val: 'dessert',        label: 'Desserts' },
];

function menuFormVide() {
  return {
    menu_id: null, titre: '', description: '',
    prixParPersonne: 0, nombrePersonneMinimum: 4, quantiteRestante: null,
    conditions: '', actif: true,
    themeIds: [], regimeIds: [], platIds: [], images: [],
    chargement: false,
  };
}

function platFormVide() {
  return { plat_id: null, titre: '', description: '', categorie: 'entree', imageUrl: '', allergeneIds: [], chargement: false };
}

function gestionCatalogue() {
  return {
    categoriesPlat:  CATEGORIES_PLAT,
    menusListe:      [],
    platsListe:      [],
    themesListe:     [],
    regimesListe:    [],
    allergenesListe: [],
    erreurCatalogue: null,

    menuForm:    menuFormVide(),
    menuFormErr: {},
    platForm:    platFormVide(),
    platFormErr: {},

    // ── Chargements ───────────────────────────────────────────
    async chargerCatalogue() {
      this.erreurCatalogue = null;
      try {
        const [menus, plats, themes, regimes, allergenes] = await Promise.all([
          apiFetch('/menus'),
          apiFetch('/employe/plats'),
          apiFetch('/referentiels/themes'),
          apiFetch('/referentiels/regimes'),
          apiFetch('/referentiels/allergenes'),
        ]);
        this.menusListe      = menus;
        this.platsListe      = plats;
        this.themesListe     = themes;
        this.regimesListe    = regimes;
        this.allergenesListe = allergenes;
      } catch (e) {
        this.erreurCatalogue = 'Impossible de charger le catalogue : ' + e.message;
      }
    },

    async chargerMenus() {
      try { this.menusListe = await apiFetch('/menus'); }
      catch (e) { this.erreurCatalogue = 'Impossible de charger les menus : ' + e.message; }
    },

    async chargerPlats() {
      try { this.platsListe = await apiFetch('/employe/plats'); }
      catch (e) { this.erreurCatalogue = 'Impossible de charger les plats : ' + e.message; }
    },

    platsDeCategorie(categorie) {
      return this.platsListe.filter(p => p.categorie === categorie);
    },

    // ── Menus ─────────────────────────────────────────────────
    ouvrirNouveauMenu() {
      this.menuForm = menuFormVide();
      this.menuFormErr = {};
      this.modales.menu = true;
    },

    /**
     * Ouvre le formulaire pré-rempli. Le détail du menu est rechargé pour
     * récupérer les identifiants des thèmes, régimes et plats déjà associés
     * (la liste ne contient que les libellés) ainsi que ses images.
     */
    async ouvrirEditionMenu(menu) {
      this.menuFormErr = {};
      this.menuForm = { ...menuFormVide(), menu_id: menu.menu_id, titre: menu.titre, chargement: true };
      this.modales.menu = true;
      try {
        const detail = await apiFetch('/menus/' + menu.menu_id);
        this.menuForm = {
          menu_id:               detail.menu_id,
          titre:                 detail.titre || '',
          description:           detail.description || '',
          prixParPersonne:       detail.prix_par_personne || 0,
          nombrePersonneMinimum: detail.nombre_personne_minimum || 1,
          quantiteRestante:      detail.quantite_restante,
          conditions:            detail.conditions || '',
          actif:                 detail.actif !== false,
          themeIds:              detail.theme_ids || [],
          regimeIds:             detail.regime_ids || [],
          platIds:               detail.plat_ids || [],
          images:                detail.images || [],
          chargement:            false,
        };
      } catch (e) {
        this.menuForm.chargement = false;
        this.menuFormErr.global = 'Impossible de charger le menu : ' + e.message;
      }
    },

    validerMenuForm() {
      const f = this.menuForm;
      if (!f.titre || f.titre.trim().length < 3) return { titre: 'Titre obligatoire (3 caractères min.).' };
      if (!f.description || !f.description.trim()) return { global: 'La description est obligatoire.' };
      if (!(Number(f.prixParPersonne) > 0)) return { global: 'Le prix par personne doit être positif.' };
      if (!(Number(f.nombrePersonneMinimum) >= 1)) return { global: 'Le nombre minimum de personnes doit être au moins 1.' };
      if (!f.themeIds.length) return { global: 'Sélectionnez au moins un thème.' };
      if (!f.regimeIds.length) return { global: 'Sélectionnez au moins un régime.' };
      if (!f.platIds.length) return { global: 'Sélectionnez au moins un plat.' };
      return null;
    },

    async soumettreMenu() {
      this.menuFormErr = this.validerMenuForm() || {};
      if (Object.keys(this.menuFormErr).length) return;

      const f = this.menuForm;
      const corps = {
        titre:                 f.titre.trim(),
        description:           f.description.trim(),
        prixParPersonne:       Number(f.prixParPersonne),
        nombrePersonneMinimum: Number(f.nombrePersonneMinimum),
        quantiteRestante:      (f.quantiteRestante === '' || f.quantiteRestante === null) ? null : Number(f.quantiteRestante),
        conditions:            f.conditions || null,
        // le <select> renvoie la chaîne "false" : on la convertit
        actif:                 f.actif !== false && f.actif !== 'false',
        themeIds:              f.themeIds.map(Number),
        regimeIds:             f.regimeIds.map(Number),
        platIds:               f.platIds.map(Number),
        // l'API remplace toutes les images : on renvoie celles du menu pour les conserver
        images:                f.images.map((url, i) => ({ url, ordreAffichage: i, estPrincipale: i === 0 })),
      };

      f.chargement = true;
      try {
        if (f.menu_id) {
          await apiFetch('/employe/menus/' + f.menu_id, { method: 'PUT', body: corps });
        } else {
          await apiFetch('/employe/menus', { method: 'POST', body: corps });
        }
        await this.chargerMenus();
        this.modales.menu = false;
        this.afficherToast(f.menu_id ? 'Menu modifié.' : 'Menu créé.');
      } catch (e) {
        this.menuFormErr = { global: e.message };
      } finally {
        f.chargement = false;
      }
    },

    async supprimerMenu(menu) {
      if (!confirm(`Retirer le menu "${menu.titre}" de la carte ?`)) return;
      try {
        await apiFetch('/employe/menus/' + menu.menu_id, { method: 'DELETE' });
        this.menusListe = this.menusListe.filter(m => m.menu_id !== menu.menu_id);
        this.afficherToast('Menu retiré de la carte.');
      } catch (e) {
        this.afficherToast('Échec : ' + e.message);
      }
    },

    // ── Plats ─────────────────────────────────────────────────
    ouvrirNouveauPlat() {
      this.platForm = platFormVide();
      this.platFormErr = {};
      this.modales.plat = true;
    },

    ouvrirEditionPlat(plat) {
      this.platForm = {
        plat_id:      plat.plat_id,
        titre:        plat.titre || '',
        description:  plat.description || '',
        categorie:    plat.categorie || 'entree',
        imageUrl:     plat.image || '',
        allergeneIds: plat.allergene_ids || [],
        chargement:   false,
      };
      this.platFormErr = {};
      this.modales.plat = true;
    },

    async soumettrePlat() {
      this.platFormErr = {};
      const f = this.platForm;
      if (!f.titre || f.titre.trim().length < 2) {
        this.platFormErr.titre = 'Titre obligatoire (2 caractères min.).';
        return;
      }
      const corps = {
        titre:        f.titre.trim(),
        description:  f.description || null,
        categorie:    f.categorie,
        imageUrl:     f.imageUrl || null,
        allergeneIds: f.allergeneIds.map(Number),
      };

      f.chargement = true;
      try {
        if (f.plat_id) {
          await apiFetch('/employe/plats/' + f.plat_id, { method: 'PUT', body: corps });
        } else {
          await apiFetch('/employe/plats', { method: 'POST', body: corps });
        }
        await this.chargerPlats();
        this.modales.plat = false;
        this.afficherToast(f.plat_id ? 'Plat modifié.' : 'Plat créé.');
      } catch (e) {
        this.platFormErr.global = e.message;
      } finally {
        f.chargement = false;
      }
    },

    async supprimerPlat(plat) {
      if (!confirm(`Supprimer le plat "${plat.titre}" ?`)) return;
      try {
        await apiFetch('/employe/plats/' + plat.plat_id, { method: 'DELETE' });
        this.platsListe = this.platsListe.filter(p => p.plat_id !== plat.plat_id);
        this.afficherToast('Plat supprimé.');
      } catch (e) {
        // 409 : plat utilisé dans un menu
        this.afficherToast('Échec : ' + e.message);
      }
    },
  };
}
