# 🕵️‍♂️ Detechtive Agency - Secure Infrastructure & Intranet

![Status](https://img.shields.io/badge/Status-Completed-success?style=for-the-badge&logo=github)
![Certification](https://img.shields.io/badge/Certification-AIS_Niveau_6-blue?style=for-the-badge)
![Stack](https://img.shields.io/badge/Tech-GNS3_%7C_PfSense_%7C_Windows_Server_2022-orange?style=for-the-badge)

> **Projet de fin d'études - Certification AIS (Administrateur d'Infrastructures Sécurisées)**
> *RNCP Niveau 6 - Jedha Bootcamp - 2026*

---

## 📖 À propos

**Detechtive Agency** est un projet de mise en situation réelle simulant le déploiement d'une infrastructure sécurisée pour une agence de renseignement. Le projet couvre la conception de l'architecture réseau, la virtualisation, le durcissement des systèmes (Hardening) et le développement d'un intranet métier interconnecté aux services d'infrastructure critiques.

🎯 **Objectif :** Démontrer la capacité à concevoir une architecture **"Secure by Design"** en segmentant le réseau en zones de sécurité distinctes, en chiffrant les communications de bout en bout et en assurant l'interopérabilité entre une application Web et un domaine Active Directory.

---

## 🏗️ Architecture & Infrastructure

L'infrastructure est entièrement virtualisée et émulée via **GNS3**. Elle repose sur une segmentation stricte en VLANs pour limiter les mouvements latéraux et réduire la surface d'attaque. Le pare-feu **pfSense** assure le routage inter-VLAN et applique des règles de filtrage entre chaque zone.

### 🗺️ Topologie Réseau

Le réseau est cloisonné en **5 zones de sécurité** indépendantes, chacune avec un niveau de confiance et des droits d'accès distincts.

| Zone | VLAN | CIDR | Services Hébergés | Niveau de confiance |
| :--- | :---: | :--- | :--- | :---: |
| **DMZ** | `10` | `192.168.10.0/28` | Serveur Web (Apache/PHP), Webterm | 🔴 Faible |
| **Serveurs Internes** | `20` | `192.168.10.16/28` | File Server, Base de Données (MariaDB) | 🟠 Moyen |
| **Postes Clients** | `30` | `192.168.10.128/25` | Workstations des agents (Windows) | 🟡 Standard |
| **Active Directory** | `40` | `192.168.10.32/29` | Contrôleur de Domaine (SRV-AD-01), DNS | 🔴 Critique |
| **Management & Sécurité** | `50` | `192.168.10.40/29` | SIEM Wazuh, administration réseau | 🔴 Critique |
| **Zone Externe** | `-` | `WAN` | Poste Attaquant (Kali Linux) — Pentest | ⚫ Non fiable |

### 🔒 Principes de segmentation

- Le **Serveur Web (VLAN 10 / DMZ)** est exposé depuis Internet via pfSense. Il ne peut communiquer avec le VLAN 20 que sur les ports applicatifs stricts (MariaDB, SMB) — jamais en accès libre.
- Le **VLAN 40 (AD)** est totalement isolé de la DMZ. Seuls les VLANs 20, 30 et 50 peuvent interroger le contrôleur de domaine sur les ports LDAP/Kerberos autorisés.
- Le **VLAN 50 (Wazuh)** collecte les logs de tous les VLANs en lecture seule. Aucun flux entrant depuis la DMZ n'est autorisé vers ce VLAN.
- Le poste **Kali Linux** est positionné avant pfSense (zone WAN) pour simuler un attaquant externe réaliste sans accès au réseau interne.

### 📸 Vue Logique (GNS3)

![Architecture GNS3](docs/gns3.png)

*Schéma conceptuel et plan d'adressage IP :*

![Plan IP](docs/ip.png)

---

## 🛠️ Stack Technique

### 🖥️ Virtualisation & Réseau

* **Hyperviseur / Émulateur :** GNS3 (gestion de la topologie), VMware Workstation.
* **Sécurité Périmétrique :** pfSense (Firewalling, NAT, Routing inter-VLAN, DHCP).
* **Supervision de Sécurité :** Wazuh (SIEM & XDR) — collecte d'alertes sur l'ensemble des VLANs depuis le VLAN 50 dédié.

### ⚙️ Systèmes & Services (Full Windows)

Toute l'infrastructure serveur repose sur **Windows Server 2022** pour assurer une cohérence d'administration via l'Active Directory.

* **Serveur AD (SRV-AD-01) — VLAN 40 :** Active Directory DS, DNS. Isolé dans un VLAN dédié, inaccessible depuis la DMZ.
* **Serveur de Fichiers (FS) — VLAN 20 :** Stockage des preuves, partages SMB sécurisés, quotas.
* **Serveur Web — VLAN 10 (DMZ) :**
  * OS : Windows Server 2022
  * Serveur HTTP : Apache (XAMPP/WAMP customisé)
  * Langage : PHP 8.x
* **Serveur Base de Données — VLAN 20 :**
  * OS : Windows Server 2022
  * SGBD : MariaDB (MySQL)

### 💻 Application Intranet ("Detechtive Dashboard")

* **Frontend :** HTML5 / CSS3 (Design "Terminal" immersif).
* **Backend :** PHP Natif sécurisé.
* **Outils de gestion de projet :** Trello (Kanban), Excalidraw (Schématisation).

---

## 🔐 Implémentations Sécurité (Focus AIS)

Ce projet met en œuvre une défense en profondeur, du réseau à la couche applicative.

### 1. Segmentation réseau (Defense in Depth)

* **5 VLANs isolés** avec règles pfSense strictes (whitelist par port/service, pas de règles "any to any").
* Le **Serveur Web est en DMZ** (VLAN 10) : une compromission n'expose ni l'AD, ni les données internes.
* **L'AD est isolé** dans VLAN 40 : inaccessible depuis Internet et depuis la DMZ.
* **Wazuh est isolé** dans VLAN 50 : un attaquant qui compromet la DMZ ne peut pas désactiver la supervision.

### 2. Chiffrement des Flux Critiques

* **HTTPS Strict :** L'application web n'est accessible que via TLS (certificat auto-signé ou autorité privée).
* **Database SSL/TLS :** La connexion entre le backend PHP et MariaDB est chiffrée.
  * *Détail technique :* Utilisation de `PDO::MYSQL_ATTR_SSL_CA` pointant vers le certificat CA (`ca-cert.pem`) pour prévenir les attaques Man-in-the-Middle.
* **Vérification Active :** Le dashboard affiche en temps réel le statut du chiffrement SQL (`Ssl_cipher`).

### 3. Gestion des Identités & Interopérabilité

* **Authentification Centralisée :** Les utilisateurs sont gérés via l'Active Directory (VLAN 40).
* **Interopérabilité PHP ↔ SMB :** L'application web ne stocke pas les fichiers localement. Elle s'authentifie dynamiquement sur le File Server via `net use` pour monter les partages sécurisés uniquement le temps de la session.

### 4. Sécurité Applicative (DevSecOps)

* **Upload Sécurisé :**
  * Whitelist d'extensions stricte (jpg, png, pdf, docx...).
  * Renommage forcé des fichiers pour éviter les injections de commandes.
  * Vérification de la taille (Max 5 Mo).
* **Protection SQL :** Requêtes préparées (`PDO`) systématiques contre les injections SQL.
* **Gestion d'erreurs :** Mode silencieux en production avec système de fallback si SSL échoue, et alerte administrateur.

---

## 🚀 Installation / Déploiement (Simulation)

> ⚠️ **Note :** Le code source complet est privé. Ce dépôt sert de vitrine technique.

Pour reproduire cet environnement sous GNS3 :

1. Importer les appliances (pfSense, Windows Server 2022, Kali Linux, Webterm).
2. Configurer les interfaces VLAN sur le switch virtuel (IOU/vSwitch) — 5 VLANs (10, 20, 30, 40, 50).
3. Déployer les règles de pare-feu pfSense :
   - VLAN 10 (DMZ) → VLAN 20 : ports 3306 (MariaDB) et 445 (SMB) uniquement.
   - VLAN 10 (DMZ) → VLAN 40 : ports 389/636 (LDAP/LDAPS) uniquement.
   - VLAN 10 (DMZ) → VLAN 50 : interdit.
   - VLAN 50 (Management) → tous VLANs : lecture seule (Wazuh agents).
4. Initialiser l'Active Directory et joindre les serveurs Web, BDD et FS au domaine `detechtive.local`.
5. Configurer Wazuh agents sur chaque serveur pour remonter les alertes vers VLAN 50.

---

*Projet réalisé dans le cadre de la certification RNCP Niveau 6 "Administrateur d'Infrastructures Sécurisées".*
