function app() {
  return {
    menuOpen: false,

    // Horaires du pied de page (GET /api/horaires, modifiables par les employés)
    horaires: [],

    init() {
      this.initReveal();
      this.chargerHoraires();
    },

    async chargerHoraires() {
      try {
        const liste = await apiFetch('/horaires');
        this.horaires = liste.map(h => ({
          jour:  h.jour,
          heure: h.ferme ? 'Fermé' : `${h.heure_ouverture.replace(':', 'h')} – ${h.heure_fermeture.replace(':', 'h')}`,
        }));
      } catch {
        this.horaires = [];   // le pied de page reste lisible sans les horaires
      }
    },

    // Révélation au scroll via IntersectionObserver
    initReveal() {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(el => {
          if (el.isIntersecting) {
            el.target.classList.add('visible');
          }
        });
      }, { threshold: 0.15 });

      document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    }
  }
}

// Avis clients publiés (validés par un employé) : GET /api/avis
// Format RGPD renvoyé par l'API : prénom + initiale du nom uniquement.
function avisCarousel() {
  return {
    avisVisibles: [],

    async init() {
      try {
        this.avisVisibles = await apiFetch('/avis?limite=6');
      } catch {
        this.avisVisibles = [];
      }
    },
  };
}
