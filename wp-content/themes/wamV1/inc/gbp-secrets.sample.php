<?php
/**
 * Modèle. Copier sous le nom wam-gbp-secrets.php, soit à la racine du site (à côté de
 * wp-config.php), soit dans le dossier PARENT de la racine (en prod : /home/<compte>/,
 * hors du web, à préférer), puis renseigner les valeurs.
 * Ce fichier réel est ignoré par git : ne jamais le versionner.
 *
 * - client_id     : ID client OAuth (projet Cloud wam-dance-studio, client "WAM site").
 * - client_secret : secret du client OAuth.
 * - refresh_token : jeton de rafraîchissement (commence par 1//), obtenu une seule fois
 *                   via l'OAuth Playground avec le compte propriétaire de la fiche.
 * - account_id    : facultatif. Identifiant du compte Business Profile (accounts/123...).
 *                   Laissé vide, il sera lu par l'API puis mémorisé.
 * - location_id   : facultatif. Identifiant de la fiche (locations/123...). Idem.
 * - allow_local   : true seulement pour tester en local. Attention : publie sur la VRAIE fiche.
 */
return [
    'client_id'     => '',
    'client_secret' => '',
    'refresh_token' => '',
    'account_id'    => '',
    'location_id'   => '',
    'allow_local'   => false,
];
