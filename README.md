# PLANNING INTERVENTION POUR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

Module développé par **[ForLead](https://forlead.fr)** — support@forlead.fr

## Présentation

**Planning Intervention** est un module de planification visuelle des **Interventions** pour Dolibarr ERP & CRM.

Il offre une vue calendrier interactive, permettant de visualiser, filtrer et gérer les interventions en un coup d'œil, directement depuis Dolibarr.

<!--
![Screenshot planningintervention](img/screenshot_planningintervention.png?raw=true "PlanningIntervention"){imgmd}
-->

## Fonctionnalités

- 📅 **Vue calendrier interactive** (mois, liste) des interventions
- 🖱️ **Modification** des dates d'intervention directement sur le calendrier
- 🔍 **Filtres dynamiques** : filtrer par statut, client, référence d'intervention, Interventions effectuées
- 👤 **Contacts** : Affichage des contacts liés à l'intervention dans l'infobulle
- 📆 **Jours fériés** affichés automatiquement selon le pays configuré (données natives Dolibarr)
- 📆 **Affichage des weekends** optionnel (paramétrable)

## Paramétrage

Depuis le menu **Configuration > Modules > PlanningIntervention > Paramètres** :

- Couleurs des événements (intervention par statut)
- Pays pour les jours fériés
- Masquer les weekends
- Griser les weekends

## Traductions

Les traductions sont gérables manuellement en éditant les fichiers dans le répertoire `langs/` du module.

Langues actuellement disponibles :
- 🇫🇷 Français (`fr_FR`)
- 🇺🇸 Anglais (`en_US`)

Les contributions de traduction sont les bienvenues.

## Installation

**Prérequis :** Dolibarr ERP & CRM doit être installé sur votre serveur.  
Téléchargeable sur [dolibarr.org](https://www.dolibarr.org) ou disponible en mode SaaS sur [saas.dolibarr.org](https://saas.dolibarr.org).

### Depuis le fichier ZIP (interface graphique)

1. Téléchargez le fichier `module_planningintervention-x.x.x.zip` depuis le [Dolistore](https://www.dolistore.com)
2. Dans Dolibarr, allez dans **Accueil > Configuration > Modules > Déployer un module externe**
3. Uploadez le fichier ZIP
4. Activez le module depuis la liste des modules

### Étapes finales

Depuis votre navigateur :

1. Connectez-vous à Dolibarr en tant que administrateur
2. Allez dans **Configuration > Modules**
3. Recherchez **PlanningIntervention** et activez-le
4. Rendez-vous dans les **Paramètres** du module pour personnaliser les couleurs et les options d'affichage

## Support

Pour toute question ou demande de support :

- 🌐 Site : [forlead.fr](https://forlead.fr)
- 📧 Email : [support@forlead.fr](mailto:support@forlead.fr)


## Autres modules

D'autres modules ForLead sont disponibles sur [Dolistore.com](https://www.dolistore.com).

## Licences

### Documentation

Tous les textes et fichiers README sont sous licence [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).

---

*Module PlanningIntervention — © [ForLead](https://forlead.fr) — [support@forlead.fr](mailto:support@forlead.fr)*