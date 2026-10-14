function app() {
  return {
    menuOpen: false,

    // Horaires pied de page
    horaires: [
      { jour: 'Lundi',    heure: '08h00 – 19h00' },
      { jour: 'Mardi',    heure: '08h00 – 19h00' },
      { jour: 'Mercredi', heure: '08h00 – 19h00' },
      { jour: 'Jeudi',    heure: '08h00 – 19h00' },
      { jour: 'Vendredi', heure: '08h00 – 20h00' },
      { jour: 'Samedi',   heure: '09h00 – 18h00' },
      { jour: 'Dimanche', heure: 'Fermé' },
    ],

    init() {
      this.initReveal();
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

// Données avis clients (simulées — viendront de la BDD côté back-end)
function avisCarousel() {
  return {
    // Seuls les avis avec statut "validé" sont affichés
    avisVisibles: [
      {
        note: 5,
        commentaire: "Prestation exceptionnelle pour notre réveillon de Noël. Les plats étaient savoureux et la livraison parfaitement ponctuelle. Nous recommandons sans hésiter.",
        prenom: "Marie",
        nom: "Dupont",
        evenement: "Réveillon de Noël"
      },
      {
        note: 5,
        commentaire: "Menu de Pâques magnifique, généreux et plein de saveurs. Julie et José ont su s'adapter à nos contraintes alimentaires avec beaucoup de professionnalisme.",
        prenom: "Thomas",
        nom: "Bernard",
        evenement: "Repas de Pâques"
      },
      {
        note: 4,
        commentaire: "Très bon rapport qualité-prix pour notre séminaire d'entreprise. Le menu végétarien était particulièrement réussi. Nous ferons à nouveau appel à eux.",
        prenom: "Sophie",
        nom: "Martin",
        evenement: "Séminaire d'entreprise"
      }
    ]
  }
}
