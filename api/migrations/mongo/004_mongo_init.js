// ═══════════════════════════════════════════════════════════════
// Vite & Gourmand — Script d'initialisation MongoDB
// ═══════════════════════════════════════════════════════════════
// Exigence du cahier des charges : "Base de données NoSQL utilisée
// pour les statistiques".
//
// La BDD MongoDB stocke une collection dénormalisée des commandes,
// optimisée pour les agrégations rapides (dashboard admin).
//
// Usage (à exécuter dans mongosh) :
//   mongosh mongodb://127.0.0.1:27017/vitegourmand_stats < 004_mongo_init.js
// ═══════════════════════════════════════════════════════════════

// Utilise la base configurée dans MONGODB_DB (.env)
db = db.getSiblingDB('vitegourmand_stats');

// ── Collection : statistiques_commandes ───────────────────────
// Structure d'un document :
// {
//   _id            : ObjectId,
//   commande_id    : 42,               // clé de rapprochement avec PG
//   numero_commande: "VG-2025-0042",
//   menu_id        : 1,
//   menu_titre     : "Menu de Noël Prestige",
//   utilisateur_id : 123,
//   statut         : "terminee",
//   prix_menu      : 336.00,
//   reduction      : 33.60,
//   prix_livraison : 5.00,
//   prix_total     : 307.40,
//   nombre_personnes: 8,
//   ville_livraison : "Bordeaux",
//   date_commande   : ISODate("2025-11-20T14:30:00Z"),
//   date_prestation : ISODate("2025-12-24T12:00:00Z")
// }
// ──────────────────────────────────────────────────────────────

// Créer la collection si absente (sinon on garde les documents existants)
if (!db.getCollectionNames().includes('statistiques_commandes')) {
    db.createCollection('statistiques_commandes');
    print('✓ Collection statistiques_commandes créée');
} else {
    print('✓ Collection statistiques_commandes déjà présente');
}

// Index sur commande_id : rapprochement avec la clé PostgreSQL
db.statistiques_commandes.createIndex(
    { commande_id: 1 },
    { unique: true, name: 'idx_commande_id_unique' }
);

// Index sur menu_titre : agrégations "commandes par menu"
db.statistiques_commandes.createIndex(
    { menu_titre: 1 },
    { name: 'idx_menu_titre' }
);

// Index sur statut : filtrage rapide (excluent annulées, en_attente…)
db.statistiques_commandes.createIndex(
    { statut: 1 },
    { name: 'idx_statut' }
);

// Index composite sur date_commande + statut : agrégations CA temporelles
db.statistiques_commandes.createIndex(
    { date_commande: -1, statut: 1 },
    { name: 'idx_date_statut' }
);

print('✓ Index créés');
print('');
print('Initialisation MongoDB terminée.');
print('Pour peupler la collection depuis les commandes PostgreSQL existantes,');
print('exécuter : php bin/console app:sync-mongo');
