/**
 * Pages d'information (CGV, mentions légales, déclaration d'accessibilité).
 * Seul comportement dynamique : le menu mobile et l'affichage du lien
 * vers l'espace personnel quand l'utilisateur est connecté.
 */
function pageInformation() {
  return {
    menuOpen: false,
    utilisateur: null,

    async init() {
      try {
        this.utilisateur = await apiFetch('/auth/me');
      } catch {
        this.utilisateur = null;   // visiteur non connecté
      }
    },
  };
}
