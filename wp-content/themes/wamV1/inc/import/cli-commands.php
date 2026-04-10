<?php
/**
 * Enregistrement des commandes WP-CLI pour le thème WAM
 *
 * @package wamv1
 */

if (defined('WP_CLI') && WP_CLI) {

    // Inclusion des fichiers de logique d'import
    require_once __DIR__ . '/import-profs.php';
    require_once __DIR__ . '/import-cours.php';

    /**
     * Espace de nom 'wam' pour les commandes personnalisées
     */
    WP_CLI::add_command('wam import-profs', 'wamv1_cli_import_profs', array(
        'shortdesc' => 'Importe les professeurs à partir d\'un fichier CSV (data/profs_wam_import.csv)',
        'synopsis' => array(
            array(
                'type'        => 'assoc',
                'name'        => 'dry-run',
                'description' => 'Simule l\'importation sans écrire en base de données.',
                'optional'    => true,
            ),
        ),
    ));

    WP_CLI::add_command('wam import-cours', 'wamv1_cli_import_cours', array(
        'shortdesc' => 'Importe les cours à partir d\'un fichier CSV (data/cours_wam_import.csv)',
        'synopsis' => array(
            array(
                'type'        => 'assoc',
                'name'        => 'dry-run',
                'description' => 'Simule l\'importation sans écrire en base de données.',
                'optional'    => true,
            ),
        ),
    ));
}
