<?php

// Web Service pour la modification du mot de passe de l'utilisateur (Mon compte / Modification de mot de passe)
$rcmail_config['ws_changepass'] = 'https://davy.ida.melanie2.i2/changepasswordm2_ldapma/service_ida.wsdl';
//$rcmail_config['ws_changepass'] = 'https://wsamande.ac.melanie2.i2/changepassword/wsdl.php';

// Web Service z-push pour accéder aux statistiques Mobile (Mes statistiques / Mobiles)
$rcmail_config['ws_zp'] = 'https://zp-ida01.ida.melanie2.i2/webservice.php';
//$rcmail_config['ws_zp'] = 'https://zlb.csac.melanie2.i2/webservice.php';

// Configuration de l'administrateur LDAP pour l'édition de listes (Mon compte / Gestion des listes)
$rcmail_config['liste_admin'] = "uid=listeadmin,ou=admin,ou=ressources,dc=equipement,dc=gouv,dc=fr";
// Configuration du mot de passe de l'administrateur LDAP pour l'édition de listes
$rcmail_config['liste_pwd'] = "B1M'enFin!";

// Activer le menu Mon compte
$rcmail_config['enable_moncompte']          = true;
// Activer le menu Mon compte / Gestion des CGUs
$rcmail_config['enable_moncompte_cgu']      = true;
// Activer le menu Mon compte / Gestionnaire d'absence
$rcmail_config['enable_moncompte_abs']      = true;
// Activer le menu Mon compte / Gestion des listes
$rcmail_config['enable_moncompte_lists']    = true;
// Activer le menu Mon compte / Informations personnelles
$rcmail_config['enable_moncompte_infos']    = true;
// Activer le menu Mon compte / Modification du mot de passe
$rcmail_config['enable_moncompte_mdp']      = true;
// Activer le menu Mon compte / Afficher la photo
$rcmail_config['enable_moncompte_photo']    = true;

// Activer le menu Mes ressources
$rcmail_config['enable_mesressources']              = true;
// Activer le menu Mes ressources / Boites aux lettres
$rcmail_config['enable_mesressources_mail']         = true;
// Activer la restaurationdes mails
$rcmail_config['enable_mesressources_mail_restore'] = true;
// Activer le menu Mes ressources / Agendas
$rcmail_config['enable_mesressources_cal']          = true;
// Activer la restauration des agendas
$rcmail_config['enable_mesressources_cal_restore']  = true;
// Activer le menu Mes ressources / Contacts
$rcmail_config['enable_mesressources_addr']         = true;
// Activer la restaurationdes contacts
$rcmail_config['enable_mesressources_addr_restore'] = true;
// Activer le menu Mes ressources / Tâches
$rcmail_config['enable_mesressources_task']         = true;

// Activer le menu Mes statistiques
$rcmail_config['enable_messtatistiques']        = true;
// Activer le menu Mes statistiques / Mobiles
$rcmail_config['enable_messtatistiques_mobile'] = true;

// Liste des attributs ldap à récupérer
$rcmail_config['ldap_attributes'] = array(
		"user_cn",
        "user_pwd_change",				// Raison du mdp doit changer
        "user_mel_partages",			// Partages 
        "user_mel_response",			// Message d'absence
        "user_type_entree",				// Type d'entrée
        "user_mel_accesinterneta",		// Droit d'accès depuis internet donné par admin
        "user_mel_accesinternetu",		// Droit d'accès depuis internet accepté par user
        "user_photo_publiader",   		// Flag publier la photo sur Ader
        "user_photo_publiintra",    	// Flag publier la photo sur l'intranet
        "user_employeenumber",
        "user_zone",
        "user_street",
        "user_postalcode",
        "user_locality",
        "user_info",
        "user_description",
        "user_phonenumber",
        "user_faxnumber",
        "user_mobilephone",
        "user_roomnumber",
        "user_title",
        "user_businesscat",
        "user_vpnprofil",         		// Profil de droits VPN 
        "user_sambasid",
        "user_majinfoperso",         	// Droit de modification des infos perso 
        "user_mel_remise",          		
        "user_mel_accessynchroa",		// Droit d'accès synchro mobile donné par admin
        "user_mel_accessynchrou",		// Droit d'accès synchro mobile accepté par user
        "user_mission",
        "user_photo",
        "user_gender",
        "user_liens_import"
        );

