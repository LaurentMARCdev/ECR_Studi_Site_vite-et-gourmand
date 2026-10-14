/**
 * Accès à l'API REST Symfony, partagé par toutes les pages.
 *
 * Toutes les requêtes passent par apiFetch() :
 *  - envoi du cookie de session (credentials: 'include') ;
 *  - corps JSON sérialisé automatiquement ;
 *  - en cas de réponse HTTP en erreur, une exception ApiErreur est levée avec
 *    le message renvoyé par l'API ({ erreur, details }), pour que l'interface
 *    affiche l'échec au lieu de faire comme si l'action avait réussi.
 */
const API_BASE = '/api';

class ApiErreur extends Error {
  constructor(message, statut, details) {
    super(message);
    this.name = 'ApiErreur';
    this.statut = statut;
    this.details = details || null;
  }
}

/**
 * @param {string} chemin  ex. '/employe/menus'
 * @param {{method?: string, body?: any, headers?: object}} options
 * @returns {Promise<any>} le JSON de la réponse (ou null si vide)
 */
async function apiFetch(chemin, options = {}) {
  const init = {
    method: options.method || 'GET',
    credentials: 'include',
    headers: { 'Accept': 'application/json', ...(options.headers || {}) },
  };
  if (options.body !== undefined) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(options.body);
  }

  let reponse;
  try {
    reponse = await fetch(API_BASE + chemin, init);
  } catch (e) {
    throw new ApiErreur('Serveur injoignable. Vérifiez votre connexion.', 0);
  }

  const texte = await reponse.text();
  let donnees = null;
  if (texte) {
    try { donnees = JSON.parse(texte); } catch { donnees = null; }
  }

  if (!reponse.ok) {
    throw new ApiErreur(messageErreur(donnees, reponse.status), reponse.status, donnees && donnees.details);
  }
  return donnees;
}

/** Construit un message lisible à partir de la réponse d'erreur de l'API. */
function messageErreur(donnees, statut) {
  if (statut === 401) return 'Votre session a expiré. Veuillez vous reconnecter.';
  if (statut === 403) return 'Action non autorisée pour votre compte.';
  let message = (donnees && (donnees.erreur || donnees.message)) || `Erreur ${statut}.`;
  if (donnees && donnees.details && typeof donnees.details === 'object') {
    message += ' ' + Object.values(donnees.details).join(' ');
  }
  return message;
}

/** Formats d'affichage communs */
const formats = {
  prix(v)       { return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0); },
  date(d)       { return d ? new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) : ''; },
  dateCourte(d) { return d ? new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) : ''; },
  dateLongue(d) { return d ? new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : ''; },
  aujourdhui()  { return new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); },
};

/**
 * Assemble un composant Alpine à partir de plusieurs morceaux réutilisables
 * (ex. gestionCatalogue(), gestionCommandes()). Contrairement à l'opérateur
 * "...", les getters (propriétés calculées) restent dynamiques.
 */
function composer(...morceaux) {
  const composant = {};
  for (const morceau of morceaux) {
    Object.defineProperties(composant, Object.getOwnPropertyDescriptors(morceau));
  }
  return composant;
}
