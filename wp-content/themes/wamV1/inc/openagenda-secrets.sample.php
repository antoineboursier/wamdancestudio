<?php
/**
 * Modèle. Copier sous le nom wam-openagenda-secrets.php, soit à la racine du site (à côté
 * de wp-config.php), soit dans le dossier PARENT de la racine (en prod : /home/<compte>/,
 * hors du web, à préférer), puis renseigner les valeurs.
 * Ce fichier réel est ignoré par git : ne jamais le versionner.
 *
 * - agenda_uid   : identifiant numérique de l'agenda (URL de l'agenda ou ses réglages).
 * - secret_key   : clé secrète d'API (commence par oa_sk_), créée sur
 *                  openagenda.com/settings/apiKey avec les droits d'écriture.
 * - location_uid : facultatif. Laissé à 0, le lieu "WAM Dance Studio" est créé automatiquement
 *                  dans l'agenda au premier envoi, puis mémorisé.
 * - allow_local  : true seulement pour tester en local, avec la clé d'un agenda de TEST.
 */
return [
    'agenda_uid'   => 671961,
    'secret_key'   => '',
    'location_uid' => 0,
    'allow_local'  => false,
];
