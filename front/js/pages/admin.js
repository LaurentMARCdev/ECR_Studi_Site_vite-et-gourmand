   function adminApp() {
     return {
       menuOpen: false,
       section:  'dashboard',
       admin:    null,

       // Données
       stats:            { totalCommandes:0, chiffreAffaires:0, noteMoyenne:0, totalAvis:0 },
       commandes:        [],
       commandesFiltrees:[],
       menusListe:       [],
       employes:         [],
       avisEnAttente:    [],
       commandesParMenu: [],
       themesListe:      [],
       regimesListe:     [],
       allergenesListe:  [],
       platsListe:       [],
       platForm:         { plat_id:null, titre:'', description:'', categorie:'entree', imageUrl:'', allergeneIds:[], chargement:false },
       platFormErr:      {},
       caParMenu:        [],
       caFiltre:         [],

       // KPIs calculés
       get nbCommandesEnAttente() { return this.commandes.filter(c=>c.statut==='en_attente').length; },
       get nbAvisEnAttente()      { return this.avisEnAttente.length; },
       get maxCommandes()         { return Math.max(...this.commandesParMenu.map(i=>i.count), 1); },
       get maxCA()                { return Math.max(...this.caFiltre.map(i=>i.ca), 1); },

       // Filtres
       filtreCmd:  { client:'', statut:'', menu:'' },
       filtreCA:   { menu:'', debut:'', fin:'' },

       // Modales
       modales: { menu:false, plat:false, employe:false, annulAdmin:false },
       cmdEnCours: null,

       // Formulaires
       menuForm:     { menu_id:null, titre:'', description:'', theme:'', regime:'', nombre_personne_minimum:4, prix_par_personne:0, quantite_restante:null, conditions:'', chargement:false },
       menuFormErr:  {},
       employeForm:  { prenom:'', nom:'', email:'', motDePasse:'', chargement:false, succes:false },
       employeFormErr:{},
       annulAdmin:   { modeContact:'', motif:'', chargement:false, erreurs:{} },

       toast: { visible:false, message:'' },

       statutsCommande: [
         { val:'en_attente',         label:'En attente' },
         { val:'accepte',            label:'Acceptée' },
         { val:'en_preparation',     label:'En préparation' },
         { val:'en_cours_livraison', label:'En cours de livraison' },
         { val:'livre',              label:'Livrée' },
         { val:'attente_materiel',   label:'Attente retour matériel' },
         { val:'terminee',           label:'Terminée' },
         { val:'annulee',            label:'Annulée' },
       ],

       // ── Init ──────────────────────────────────────────────
       async init() {
         await Promise.all([
           this.chargerAdmin(),
           this.chargerCommandes(),
           this.chargerMenus(),
           this.chargerEmployes(),
           this.chargerAvis(),
           this.chargerStatsNoSQL(),
           this.chargerReferentiels(),
           this.chargerPlats(),
         ]);
       },

       // ── Chargement admin ──────────────────────────────────
       async chargerAdmin() {
         try {
           const res = await fetch(`${API_BASE}/auth/me`, { credentials:'include', headers:{'Accept':'application/json'} });
           if (!res.ok) { window.location.href='/connexion'; return; }
           const data = await res.json();
           if (data.role !== 'administrateur') { window.location.href='/'; return; }
           this.admin = data;
         } catch {
           this.admin = { prenom:'José', nom:'Martin', role:'administrateur' };
         }
       },

       // ── Commandes ─────────────────────────────────────────
       async chargerCommandes() {
         try {
           /*
            * GET /api/admin/commandes
            * Réponse : [{ numero_commande, client_prenom, client_nom, client_email,
            *               menu_titre, menu_id, date_prestation, statut,
            *               prix_total }]
            */
           const res = await fetch(`${API_BASE}/admin/commandes`, { credentials:'include', headers:{'Accept':'application/json'} });
           if (!res.ok) throw new Error();
           this.commandes = await res.json();
         } catch {
           this.commandes = demoCommandes();
         }
         this.filtrerCommandes();
         this.stats.totalCommandes = this.commandes.length;
         this.stats.chiffreAffaires = this.commandes.filter(c=>c.statut!=='annulee').reduce((s,c)=>s+c.prix_total,0);
       },

       filtrerCommandes() {
         this.commandesFiltrees = this.commandes.filter(c => {
           const recherche = (this.filtreCmd.client||'').toLowerCase();
           if (recherche && !`${c.client_prenom} ${c.client_nom} ${c.client_email}`.toLowerCase().includes(recherche)) return false;
           if (this.filtreCmd.statut && c.statut !== this.filtreCmd.statut) return false;
           if (this.filtreCmd.menu   && c.menu_id != this.filtreCmd.menu)   return false;
           return true;
         });
       },

       // ── Menus ─────────────────────────────────────────────
       async chargerReferentiels() {
         try {
           const [rTh, rRe, rAl] = await Promise.all([
             fetch(`${API_BASE}/referentiels/themes`, { credentials:'include', headers:{'Accept':'application/json'} }),
             fetch(`${API_BASE}/referentiels/regimes`, { credentials:'include', headers:{'Accept':'application/json'} }),
             fetch(`${API_BASE}/referentiels/allergenes`, { credentials:'include', headers:{'Accept':'application/json'} }),
           ]);
           if (rTh.ok) this.themesListe     = await rTh.json();
           if (rRe.ok) this.regimesListe    = await rRe.json();
           if (rAl.ok) this.allergenesListe = await rAl.json();
         } catch {}
       },
       async chargerPlats() {
         try {
           const res = await fetch(`${API_BASE}/employe/plats`, { credentials:'include', headers:{'Accept':'application/json'} });
           if (res.ok) this.platsListe = await res.json();
         } catch {}
       },

// ── Plats ─────────────────────────────────────────────
       ouvrirNouveauPlat() {
         this.platForm = { plat_id: null, titre: '', description: '', categorie: 'entree', imageUrl: '', allergeneIds: [], chargement: false };
         this.platFormErr = {};
         this.modales.plat = true;
       },
       ouvrirEditionPlat(plat) {
         this.platForm = {
           plat_id: plat.plat_id,
           titre: plat.titre || '',
           description: plat.description || '',
           categorie: plat.categorie || 'entree',
           imageUrl: plat.image_url || plat.imageUrl || '',
           allergeneIds: (plat.allergenes || []).map(function(a) { return a.allergene_id || a; }),
           chargement: false
         };
         this.platFormErr = {};
         this.modales.plat = true;
       },
       async soumettrePlat() {
         this.platFormErr = {};
         if (!this.platForm.titre.trim() || this.platForm.titre.length < 2) {
           this.platFormErr.titre = 'Titre obligatoire (2 caractères min).';
           return;
         }
         this.platForm.chargement = true;
         try {
           const method = this.platForm.plat_id ? 'PUT' : 'POST';
           const url = this.platForm.plat_id
             ? API_BASE + '/employe/plats/' + this.platForm.plat_id
             : API_BASE + '/employe/plats';
           const body = {
             titre: this.platForm.titre,
             description: this.platForm.description || null,
             categorie: this.platForm.categorie,
             imageUrl: this.platForm.imageUrl || null,
             allergeneIds: this.platForm.allergeneIds
           };
           const res = await fetch(url, {
             method: method,
             credentials: 'include',
             headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
             body: JSON.stringify(body)
           });
           const data = await res.json().catch(function() { return {}; });
           if (!res.ok) {
             let msg = data.erreur || 'Erreur lors de l\'enregistrement.';
             if (data.details) {
               msg += ' ' + Object.values(data.details).join(' ');
             }
             this.platFormErr.global = msg;
             return;
           }
           await this.chargerPlats();
           this.modales.plat = false;
           this.afficherToast(this.platForm.plat_id ? 'Plat modifié.' : 'Plat créé.');
         } catch (e) {
           this.platFormErr.global = 'Erreur réseau : ' + e.message;
         } finally {
           this.platForm.chargement = false;
         }
       },
       async supprimerPlat(plat) {
         if (!confirm('Supprimer le plat "' + plat.titre + '" ?')) return;
         try {
           const res = await fetch(API_BASE + '/employe/plats/' + plat.plat_id, {
             method: 'DELETE',
             credentials: 'include'
           });
           if (res.ok) {
             this.platsListe = this.platsListe.filter(function(p) { return p.plat_id !== plat.plat_id; });
             this.afficherToast('Plat supprimé.');
           } else {
             const data = await res.json().catch(function() { return {}; });
             alert(data.erreur || 'Impossible de supprimer.');
           }
         } catch (e) {
           alert('Erreur réseau.');
         }
       },

       async chargerMenus() {
         try {
           const res = await fetch(`${API_BASE}/menus`, { credentials:'include', headers:{'Accept':'application/json'} });
           if (!res.ok) throw new Error();
           this.menusListe = await res.json();
         } catch {
           this.menusListe = [];
         }
         this.recalculerCA();
       },

       async soumettreMenu() {
         this.menuFormErr = {};

         // Validation front minimale
         if (!this.menuForm.titre || this.menuForm.titre.trim().length < 3) {
           this.menuFormErr.titre = 'Titre obligatoire (3 caractères min).';
           return;
         }
         if (!this.menuForm.description || this.menuForm.description.trim().length === 0) {
           this.menuFormErr.global = 'La description est obligatoire.';
           return;
         }
         if (!this.menuForm.themeIds || this.menuForm.themeIds.length === 0) {
           this.menuFormErr.global = 'Sélectionnez au moins un thème.';
           return;
         }
         if (!this.menuForm.regimeIds || this.menuForm.regimeIds.length === 0) {
           this.menuFormErr.global = 'Sélectionnez au moins un régime.';
           return;
         }
         if (!this.menuForm.platIds || this.menuForm.platIds.length === 0) {
           this.menuFormErr.global = 'Sélectionnez au moins un plat.';
           return;
         }

         this.menuForm.chargement = true;
         try {
           const method = this.menuForm.menu_id ? 'PUT' : 'POST';
           const url = this.menuForm.menu_id
             ? API_BASE + '/employe/menus/' + this.menuForm.menu_id
             : API_BASE + '/employe/menus';

           const body = {
             titre: this.menuForm.titre,
             description: this.menuForm.description,
             prixParPersonne: Number(this.menuForm.prixParPersonne) || 0,
             nombrePersonneMinimum: Number(this.menuForm.nombrePersonneMinimum) || 1,
             quantiteRestante: (this.menuForm.quantiteRestante === '' || this.menuForm.quantiteRestante === null) ? null : Number(this.menuForm.quantiteRestante),
             conditions: this.menuForm.conditions || null,
             actif: this.menuForm.actif !== false,
             themeIds: this.menuForm.themeIds.map(function(x) { return Number(x); }),
             regimeIds: this.menuForm.regimeIds.map(function(x) { return Number(x); }),
             platIds: this.menuForm.platIds.map(function(x) { return Number(x); }),
             images: []
           };

           const res = await fetch(url, {
             method: method,
             credentials: 'include',
             headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
             body: JSON.stringify(body)
           });

           const data = await res.json().catch(function() { return {}; });

           if (!res.ok) {
             let msg = data.erreur || 'Erreur lors de l\'enregistrement.';
             if (data.details && typeof data.details === 'object') {
               msg += ' ' + Object.values(data.details).join(' | ');
             }
             this.menuFormErr.global = msg;
             return;
           }

           await this.chargerMenus();
           this.modales.menu = false;
           this.afficherToast(this.menuForm.menu_id ? 'Menu modifié.' : 'Menu créé.');
         } catch (e) {
           this.menuFormErr.global = 'Erreur réseau : ' + e.message;
         } finally {
           this.menuForm.chargement = false;
         }
       },

       async supprimerMenu(menu) {
         if (!confirm('Supprimer le menu "' + menu.titre + '" ?')) return;
         try {
           const res = await fetch(API_BASE + '/employe/menus/' + menu.menu_id, {
             method: 'DELETE',
             credentials: 'include'
           });
           if (res.ok) {
             this.menusListe = this.menusListe.filter(function(m) { return m.menu_id !== menu.menu_id; });
             this.afficherToast('Menu supprimé.');
           } else {
             const data = await res.json().catch(function() { return {}; });
             alert(data.erreur || 'Impossible de supprimer.');
           }
         } catch (e) {
           alert('Erreur réseau.');
         }
       },

       ouvrirNouveauMenu() {
         this.menuForm = {
           menu_id: null,
           titre: '',
           description: '',
           prixParPersonne: 0,
           nombrePersonneMinimum: 4,
           quantiteRestante: null,
           conditions: '',
           actif: true,
           themeIds: [],
           regimeIds: [],
           platIds: [],
           chargement: false
         };
         this.menuFormErr = {};
         this.modales.menu = true;
       },

       ouvrirEditionMenu(menu) {
         const themeIdsFromLibelles = (menu.themes || [])
           .map(function(lib) {
             const t = this.themesListe.find(function(x) { return x.libelle === lib; });
             return t ? t.theme_id : null;
           }.bind(this))
           .filter(function(id) { return id !== null; });
         const regimeIdsFromLibelles = (menu.regimes || [])
           .map(function(lib) {
             const r = this.regimesListe.find(function(x) { return x.libelle === lib; });
             return r ? r.regime_id : null;
           }.bind(this))
           .filter(function(id) { return id !== null; });

         this.menuForm = {
           menu_id: menu.menu_id,
           titre: menu.titre || '',
           description: menu.description || '',
           prixParPersonne: menu.prix_par_personne || 0,
           nombrePersonneMinimum: menu.nombre_personne_minimum || 1,
           quantiteRestante: menu.quantite_restante,
           conditions: menu.conditions || '',
           actif: menu.actif !== false,
           themeIds: themeIdsFromLibelles,
           regimeIds: regimeIdsFromLibelles,
           platIds: [],
           chargement: false
         };
         this.menuFormErr = {};
         this.modales.menu = true;
       },

       // ── Employés ──────────────────────────────────────────
       async chargerEmployes() {
         try {
           /*
            * GET /api/admin/employes
            * Réponse : [{ utilisateur_id, prenom, nom, email, actif, date_creation }]
            */
           const res = await fetch(`${API_BASE}/admin/employes`, { credentials:'include', headers:{'Accept':'application/json'} });
           if (!res.ok) throw new Error();
           this.employes = await res.json();
         } catch {
           this.employes = demoEmployes();
         }
       },

       ouvrirNouvelEmploye() {
         this.employeForm = { prenom:'', nom:'', email:'', motDePasse:'', chargement:false, succes:false };
         this.employeFormErr = {};
         this.modales.employe = true;
       },

       async soumettreEmploye() {
         this.employeFormErr = {};
         if (!this.employeForm.prenom.trim()) { this.employeFormErr.prenom = 'Obligatoire.'; return; }
         if (!this.employeForm.nom.trim())    { this.employeFormErr.nom    = 'Obligatoire.'; return; }
         if (!this.employeForm.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.employeForm.email)) { this.employeFormErr.email = 'E-mail invalide.'; return; }
         if (!this.employeForm.motDePasse || this.employeForm.motDePasse.length < 10) { this.employeFormErr.motDePasse = 'Minimum 10 caractères.'; return; }
         this.employeForm.chargement = true;
         try {
           /*
            * POST /api/admin/employes
            * Body : { prenom, nom, email, motDePasse }
            * → crée le compte, envoie un mail de notification (sans le mdp)
            */
           const res = await fetch(`${API_BASE}/admin/employes`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({ prenom:this.employeForm.prenom, nom:this.employeForm.nom, email:this.employeForm.email, motDePasse:this.employeForm.motDePasse }) });
           if (!res.ok) throw new Error();
         } catch {
           this.employes.push({ utilisateur_id:Date.now(), prenom:this.employeForm.prenom, nom:this.employeForm.nom, email:this.employeForm.email, actif:true, date_creation: new Date().toISOString() });
         } finally {
           this.employeForm.chargement = false;
           this.employeForm.succes = true;
         }
       },

       async toggleEmployeActif(emp) {
         const action = emp.actif ? 'désactiver' : 'réactiver';
         if (!confirm(`${action.charAt(0).toUpperCase()+action.slice(1)} le compte de ${emp.prenom} ${emp.nom} ?`)) return;
         try {
           /* PUT /api/admin/employes/:id/toggle-actif */
           await fetch(`${API_BASE}/admin/employes/${emp.utilisateur_id}/toggle-actif`, { method:'PUT', credentials:'include' });
         } catch {}
         emp.actif = !emp.actif;
         this.afficherToast(`Compte ${emp.actif ? 'réactivé' : 'désactivé'}.`);
       },

       // ── Avis ──────────────────────────────────────────────
       async chargerAvis() {
         try {
           /*
            * GET /api/admin/avis?statut=en_attente
            * Réponse : [{ avis_id, note, commentaire, auteur_prenom, auteur_nom, menu_titre, date }]
            */
           const res = await fetch(`${API_BASE}/admin/avis?statut=en_attente`, { credentials:'include', headers:{'Accept':'application/json'} });
           if (!res.ok) throw new Error();
           this.avisEnAttente = await res.json();
         } catch {
           this.avisEnAttente = demoAvis();
         }
         this.stats.noteMoyenne = this.avisEnAttente.length ? (this.avisEnAttente.reduce((s,a)=>s+a.note,0)/this.avisEnAttente.length).toFixed(1) : '—';
         this.stats.totalAvis = this.avisEnAttente.length;
       },

       async validerAvis(avis) {
         try { await fetch(`${API_BASE}/admin/avis/${avis.avis_id}/valider`, { method:'PUT', credentials:'include' }); } catch {}
         this.avisEnAttente = this.avisEnAttente.filter(a=>a.avis_id!==avis.avis_id);
         this.afficherToast('Avis publié.');
       },
       async refuserAvis(avis) {
         try { await fetch(`${API_BASE}/admin/avis/${avis.avis_id}/refuser`, { method:'DELETE', credentials:'include' }); } catch {}
         this.avisEnAttente = this.avisEnAttente.filter(a=>a.avis_id!==avis.avis_id);
         this.afficherToast('Avis refusé.');
       },

       // ── Stats NoSQL (commandes par menu) ──────────────────
       async chargerStatsNoSQL() {
         try {
           /*
            * GET /api/admin/stats/commandes-par-menu   (données MongoDB)
            * Réponse : [{ menu: "Menu de Noël Prestige", count: 42 }]
            */
           const res = await fetch(`${API_BASE}/admin/stats/commandes-par-menu`, { credentials:'include', headers:{'Accept':'application/json'} });
           if (!res.ok) throw new Error();
           this.commandesParMenu = await res.json();
         } catch {
           this.commandesParMenu = demoStatsNoSQL();
         }
         this.caParMenu = this.commandesParMenu.map(i => ({
           menu: i.menu,
           ca: (demoMenus().find(m=>m.titre===i.menu)?.prix_par_personne || 30) * (demoMenus().find(m=>m.titre===i.menu)?.nombre_personne_minimum || 6) * i.count,
         }));
         this.caFiltre = [...this.caParMenu];
       },

       recalculerCA() {
         /* Filtre local pour la démo ; côté prod → GET /api/admin/stats/ca avec params */
         this.caFiltre = this.caParMenu.filter(i => {
           if (this.filtreCA.menu) {
             const menu = this.menusListe.find(m => m.menu_id == this.filtreCA.menu);
             if (menu && i.menu !== menu.titre) return false;
           }
           return true;
         });
         if (!this.caFiltre.length) this.caFiltre = [...this.caParMenu];
       },

       // ── Changement de statut ──────────────────────────────
       statutsSuivants(actuel) {
         const flux = {
           en_attente:         ['accepte'],
           accepte:            ['en_preparation'],
           en_preparation:     ['en_cours_livraison'],
           en_cours_livraison: ['livre'],
           livre:              ['attente_materiel','terminee'],
           attente_materiel:   ['terminee'],
         };
         return (flux[actuel] || []).map(v => this.statutsCommande.find(s=>s.val===v)).filter(Boolean);
       },

       async changerStatut(cmd, nouveauStatut) {
         try {
           /* PUT /api/admin/commandes/:numero/statut   Body: { statut } */
           await fetch(`${API_BASE}/admin/commandes/${cmd.numero_commande}/statut`, { method:'PUT', credentials:'include', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ statut:nouveauStatut }) });
         } catch {}
         cmd.statut = nouveauStatut;
         this.afficherToast(`Statut mis à jour : ${this.labelStatut(nouveauStatut)}.`);
       },

       // ── Annulation admin ──────────────────────────────────
       ouvrirAnnulationAdmin(cmd) {
         this.cmdEnCours = cmd;
         this.annulAdmin = { modeContact:'', motif:'', chargement:false, erreurs:{} };
         this.modales.annulAdmin = true;
       },
       async soumettreAnnulAdmin() {
         this.annulAdmin.erreurs = {};
         if (!this.annulAdmin.modeContact) { this.annulAdmin.erreurs.modeContact = 'Obligatoire.'; return; }
         if (!this.annulAdmin.motif.trim()) { this.annulAdmin.erreurs.motif = 'Le motif est obligatoire.'; return; }
         this.annulAdmin.chargement = true;
         try {
           /* DELETE /api/admin/commandes/:numero   Body: { modeContact, motif } */
           await fetch(`${API_BASE}/admin/commandes/${this.cmdEnCours.numero_commande}`, { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ modeContact:this.annulAdmin.modeContact, motif:this.annulAdmin.motif }) });
         } catch {}
         this.cmdEnCours.statut = 'annulee';
         this.modales.annulAdmin = false;
         this.afficherToast('Commande annulée.');
         this.annulAdmin.chargement = false;
       },

       // ── Déconnexion ───────────────────────────────────────
       async seDeconnecter() {
         try { await fetch(`${API_BASE}/auth/logout`, { method:'POST', credentials:'include' }); } finally { window.location.href='/connexion'; }
       },

       // ── Helpers ───────────────────────────────────────────
       formatPrix(v) { return new Intl.NumberFormat('fr-FR',{style:'currency',currency:'EUR'}).format(v||0); },
       formatDate(d) { if(!d)return''; return new Date(d).toLocaleDateString('fr-FR',{day:'numeric',month:'short',year:'numeric'}); },
       labelStatut(s) {
         return { en_attente:'En attente', accepte:'Acceptée', en_preparation:'En préparation', en_cours_livraison:'En livraison', livre:'Livrée', attente_materiel:'Retour matériel', terminee:'Terminée', annulee:'Annulée' }[s] || s;
       },
       dateAujourdhui() { return new Date().toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'}); },
       afficherToast(msg) { this.toast={visible:true,message:msg}; setTimeout(()=>{this.toast.visible=false;},3000); },
     };
   }

   // ── Données de démo ──────────────────────────────────────
   function demoCommandes() {
     return [
       { numero_commande:'VG-2025-0042', client_prenom:'Marie',  client_nom:'Dupont', client_email:'marie@exemple.fr',  menu_titre:'Menu de Noël Prestige',           menu_id:1, date_prestation:'2025-12-24', statut:'en_preparation',     prix_total:383 },
       { numero_commande:'VG-2025-0043', client_prenom:'Thomas', client_nom:'Bernard',client_email:'thomas@exemple.fr', menu_titre:'Brunch de Pâques',                menu_id:2, date_prestation:'2025-04-20', statut:'en_attente',          prix_total:178 },
       { numero_commande:'VG-2025-0044', client_prenom:'Sophie', client_nom:'Martin', client_email:'sophie@exemple.fr', menu_titre:'Cocktail Dinatoire Événement',     menu_id:4, date_prestation:'2026-01-15', statut:'en_attente',          prix_total:792 },
       { numero_commande:'VG-2025-0045', client_prenom:'Lucas',  client_nom:'Petit',  client_email:'lucas@exemple.fr',  menu_titre:'Menu Végétarien Printemps',        menu_id:3, date_prestation:'2025-06-10', statut:'terminee',            prix_total:96  },
       { numero_commande:'VG-2025-0046', client_prenom:'Emma',   client_nom:'Leroy',  client_email:'emma@exemple.fr',   menu_titre:'Menu de Noël Prestige',           menu_id:1, date_prestation:'2025-12-31', statut:'accepte',             prix_total:336 },
     ];
   }
   function demoMenus() {
     return [
       { menu_id:1, titre:'Menu de Noël Prestige',         theme:'Noël',      regime:'classique',  nombre_personne_minimum:8,  prix_par_personne:42, quantite_restante:3, description:'Foie gras, chapon, bûche glacée.' },
       { menu_id:2, titre:'Brunch de Pâques',              theme:'Pâques',    regime:'classique',  nombre_personne_minimum:6,  prix_par_personne:28, quantite_restante:8, description:'Viennoiseries, œufs cocotte, saumon.' },
       { menu_id:3, titre:'Menu Végétarien Printemps',     theme:'classique', regime:'végétarien', nombre_personne_minimum:4,  prix_par_personne:24, quantite_restante:12,description:'Velouté d\'asperges, risotto.' },
       { menu_id:4, titre:'Cocktail Dinatoire Événement',  theme:'évènement', regime:'classique',  nombre_personne_minimum:20, prix_par_personne:35, quantite_restante:5, description:'45 pièces / personne.' },
       { menu_id:5, titre:'Menu Vegan Terre & Saveurs',    theme:'classique', regime:'vegan',      nombre_personne_minimum:4,  prix_par_personne:22, quantite_restante:15,description:'Gaspacho, tajine légumes, mousse chocolat.' },
     ];
   }
   function demoEmployes() {
     return [
       { utilisateur_id:10, prenom:'Julie', nom:'Moreau', email:'julie@vitegourmand.fr', actif:true,  date_creation:'2023-03-15' },
       { utilisateur_id:11, prenom:'Marc',  nom:'Sanchez',email:'marc@vitegourmand.fr',  actif:true,  date_creation:'2024-01-10' },
       { utilisateur_id:12, prenom:'Nina',  nom:'Leblanc',email:'nina@vitegourmand.fr',  actif:false, date_creation:'2022-09-01' },
     ];
   }
   function demoAvis() {
     return [
       { avis_id:1, note:5, commentaire:'Prestation parfaite, tout était délicieux et la livraison très ponctuelle.', auteur_prenom:'Marie',  auteur_nom:'Dupont',  menu_titre:'Menu de Noël Prestige', date:'2025-01-03' },
       { avis_id:2, note:4, commentaire:'Très bon repas, légèrement en retard sur la livraison mais la qualité était au rendez-vous.',  auteur_prenom:'Thomas', auteur_nom:'Bernard', menu_titre:'Brunch de Pâques',      date:'2025-04-22' },
       { avis_id:3, note:3, commentaire:'Correct mais manquait de saveur sur le plat principal. Le dessert était excellent en revanche.',auteur_prenom:'Sophie', auteur_nom:'Martin',  menu_titre:'Menu Végétarien',       date:'2025-06-12' },
     ];
   }
   function demoStatsNoSQL() {
     return [
       { menu:'Menu de Noël Prestige',        count:47 },
       { menu:'Cocktail Dinatoire Événement', count:34 },
       { menu:'Brunch de Pâques',             count:28 },
       { menu:'Menu Végétarien Printemps',    count:19 },
       { menu:'Menu Vegan Terre & Saveurs',   count:11 },
     ];
   }
