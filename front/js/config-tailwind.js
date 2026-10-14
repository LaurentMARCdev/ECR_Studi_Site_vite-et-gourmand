/**
 * Configuration Tailwind (CDN) commune à toutes les pages.
 * Chargée juste après le script Tailwind, dans le <head>.
 * Palette issue de la charte graphique Vite & Gourmand.
 */
tailwind.config = {
  theme: {
    extend: {
      colors: {
        nuit:       '#1A1208',   // Fond sombre, texte principal
        creme:      '#F5F0E8',   // Fond clair
        rouille:    '#D4521A',   // Accents principaux (boutons, liens)
        herbe:      '#4A7C59',   // Succès, validation
        safran:     '#F0C060',   // Attention, notifications
        foret:      '#1C2B1A',   // Barre latérale de l'espace employé
        foretHover: '#253524',
        admin:      '#111827',   // Barre latérale de l'espace administrateur
        adminhover: '#1F2937',
      },
      fontFamily: {
        display: ['"Playfair Display"', 'serif'],
        body:    ['Inter', 'sans-serif'],
        sans:    ['Inter', 'sans-serif'],
      },
    },
  },
};
