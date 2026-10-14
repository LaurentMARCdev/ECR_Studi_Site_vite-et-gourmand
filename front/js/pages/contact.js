function contactForm() {
  return {
    form: { titre: '', email: '', description: '' },
    envoi: false,
    succes: false,
    erreur: null,
    async envoyer() {
      this.envoi = true;
      this.erreur = null;
      try {
        const res = await fetch(`${API_BASE}/contact`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(this.form),
        });
        if (!res.ok) {
          const data = await res.json().catch(() => ({}));
          let msg = data.erreur || data.message || `Erreur ${res.status}`;
          if (data.details && typeof data.details === 'object') {
            msg = Object.values(data.details).join(' — ');
          }
          throw new Error(msg);
        }
        this.succes = true;
      } catch (e) {
        this.erreur = e.message || 'Impossible d\'envoyer le message. Réessayez plus tard.';
      } finally {
        this.envoi = false;
      }
    },
  };
}

function horaires() {
  const NOMS_JOURS = {
    1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi',
    5: 'Vendredi', 6: 'Samedi', 7: 'Dimanche',
    LUNDI: 'Lundi', MARDI: 'Mardi', MERCREDI: 'Mercredi', JEUDI: 'Jeudi',
    VENDREDI: 'Vendredi', SAMEDI: 'Samedi', DIMANCHE: 'Dimanche',
  };
  return {
    liste: [],
    chargement: true,
    async charger() {
      try {
        const res = await fetch(`${API_BASE}/horaires`, { headers: { 'Accept': 'application/json' } });
        if (res.ok) this.liste = await res.json();
      } catch (e) {}
      this.chargement = false;
    },
    nomJour(j) { return NOMS_JOURS[j] || j; },
    afficherHoraire(h) {
      const ferme = h.ferme || h.estFerme || (h.ouverture === null);
      if (ferme) return 'Fermé';
      const ouv = h.heure_ouverture || h.ouverture || h.heureOuverture;
      const fer = h.heure_fermeture || h.fermeture || h.heureFermeture;
      if (ouv && fer) return `${ouv.slice(0,5)} – ${fer.slice(0,5)}`;
      return '—';
    },
  };
}
