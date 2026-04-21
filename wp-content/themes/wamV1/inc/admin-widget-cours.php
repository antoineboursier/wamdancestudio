<?php
/**
 * Dashboard Widgets — Suivi du remplissage des CPT cours & stages.
 *
 * Module unifié : une seule logique, deux widgets (cours + stages) via
 * configuration par CPT (wamv1_widget_config). Chaque champ ACF audité
 * est défini par groupe ; la catégorie "wc" est mise en avant et l'alerte
 * des cours sans lien WooCommerce s'affiche sous sa barre. La catégorie
 * "essentiels" expose un détail champ par champ.
 *
 * Performance :
 *   - 1 requête WP_Query (fields=ids) par CPT
 *   - 1 requête SQL groupée sur postmeta (pas de N+1)
 *   - Transient 5 min invalidé aux save_post / deleted_post
 *
 * Accès : administrator, directrice, admin_technique.
 *
 * @package wamv1
 */

if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------
// Config des champs audités par CPT.
// Chaque champ : [ 'key' => meta_key, 'label' => libellé ].
// Les groupes ACF sont exposés via leurs sous-clés réelles en base
// (ACF préfixe : parentkey_childkey).
// ---------------------------------------------------------------
function wamv1_widget_config(string $post_type): ?array
{
    $configs = [
        'cours' => [
            'widget_id' => 'wamv1_cours_widget',
            'title' => 'Suivi des cours',
            'edit_url' => admin_url('edit.php?post_type=cours'),
            'cta' => 'Gérer les cours',
            'noun' => ['un cours', 'cours'],
            'groups' => [
                'wc' => [
                    'label' => 'WooCommerce',
                    'fields' => [
                        ['key' => 'wc_product_id', 'label' => 'Produit WC lié'],
                        ['key' => 'places_totales', 'label' => 'Places totales'],
                    ],
                ],
                'essentiels' => [
                    'label' => 'Essentiels',
                    'detail' => true,
                    'fields' => [
                        ['key' => '_thumbnail_id', 'label' => 'Image à la une'],
                        ['key' => 'photo_cours', 'label' => 'Photo pédagogie'],
                        ['key' => 'sous_titre', 'label' => 'Sous-titre'],
                        ['key' => 'description_cours', 'label' => 'Description'],
                        ['key' => 'jour_de_cours', 'label' => 'Jour'],
                        ['key' => 'heure_debut', 'label' => 'Heure début'],
                        ['key' => 'heure_de_fin', 'label' => 'Heure fin'],
                        ['key' => 'prof_cours', 'label' => 'Professeur·e'],
                    ],
                ],
                'seo' => [
                    'label' => 'SEO (Yoast)',
                    'fields' => [
                        ['key' => '_yoast_wpseo_title', 'label' => 'Titre SEO'],
                        ['key' => '_yoast_wpseo_metadesc', 'label' => 'Méta-description'],
                        ['key' => '_yoast_wpseo_focuskw', 'label' => 'Mot-clé principal'],
                    ],
                ],
                'pedagogie' => [
                    'label' => 'Pédagogie',
                    'fields' => [
                        ['key' => 'pedagogie', 'label' => 'Pédagogie'],
                        ['key' => 'styles_musiques', 'label' => 'Styles musicaux'],
                    ],
                ],
                'deroule' => [
                    'label' => 'Déroulé',
                    'fields' => [
                        ['key' => 'echauffement_time', 'label' => 'Échauffement (durée)'],
                        ['key' => 'echauffement_description', 'label' => 'Échauffement (desc.)'],
                        ['key' => 'exercice_time', 'label' => 'Exercices (durée)'],
                        ['key' => 'exercice_description', 'label' => 'Exercices (desc.)'],
                        ['key' => 'choregraphie_time', 'label' => 'Choré (durée)'],
                        ['key' => 'choregraphie_description', 'label' => 'Choré (desc.)'],
                    ],
                ],
            ],
        ],

        'stages' => [
            'widget_id' => 'wamv1_stages_widget',
            'title' => 'Suivi des stages',
            'edit_url' => admin_url('edit.php?post_type=stages'),
            'cta' => 'Gérer les stages',
            'noun' => ['un stage', 'stages'],
            'groups' => [
                'wc' => [
                    'label' => 'WooCommerce',
                    'fields' => [
                        ['key' => 'wc_product_id', 'label' => 'Produit WC lié'],
                    ],
                ],
                'essentiels' => [
                    'label' => 'Essentiels',
                    'detail' => true,
                    'fields' => [
                        ['key' => '_thumbnail_id', 'label' => 'Image à la une'],
                        ['key' => 'sous_titre', 'label' => 'Sous-titre'],
                        ['key' => 'description', 'label' => 'Description'],
                        ['key' => 'date_stage', 'label' => 'Date'],
                        ['key' => 'heure_debut', 'label' => 'Heure début'],
                        ['key' => 'heure_de_fin', 'label' => 'Heure fin'],
                        ['key' => 'type_format', 'label' => 'Type (stage/atelier/workshop)'],
                        // ACF group « intervenant·e » — sous-champs préfixés en base
                        ['key' => 'intervenant·e_stage_intervenant_inout', 'label' => 'Intervenant·e (interne/externe)'],
                    ],
                ],
                'seo' => [
                    'label' => 'SEO (Yoast)',
                    'fields' => [
                        ['key' => '_yoast_wpseo_title', 'label' => 'Titre SEO'],
                        ['key' => '_yoast_wpseo_metadesc', 'label' => 'Méta-description'],
                        ['key' => '_yoast_wpseo_focuskw', 'label' => 'Mot-clé principal'],
                    ],
                ],
                'tarifs' => [
                    'label' => 'Tarifs',
                    // ACF group « tarifs » — sous-champs tarif_1/2/3 préfixés
                    'fields' => [
                        ['key' => 'tarifs_tarif_1', 'label' => 'Tarif 1'],
                        ['key' => 'tarifs_tarif_2', 'label' => 'Tarif 2'],
                        ['key' => 'tarifs_tarif_3', 'label' => 'Tarif 3'],
                    ],
                ],
                'infos' => [
                    'label' => 'Infos complémentaires',
                    'fields' => [
                        ['key' => 'info_complementaire', 'label' => 'Info complémentaire'],
                        ['key' => 'stage_groupe', 'label' => 'Groupe de sessions'],
                    ],
                ],
            ],
        ],
    ];

    return $configs[$post_type] ?? null;
}

// ---------------------------------------------------------------
// Un champ est rempli si non vide et non 0. ACF sérialise les champs
// image/user/relationship → "a:0:{}" en base = tableau vide.
// ---------------------------------------------------------------
function wamv1_widget_field_is_filled($value): bool
{
    if ($value === null || $value === '' || $value === '0') {
        return false;
    }
    if (is_string($value) && str_starts_with($value, 'a:0:')) {
        return false;
    }
    return true;
}

// ---------------------------------------------------------------
// Agrégation des stats pour un CPT donné. Transient 5 min.
// ---------------------------------------------------------------
function wamv1_widget_data(string $post_type): array
{
    $cache_key = 'wamv1_widget_data_' . $post_type;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $cfg = wamv1_widget_config($post_type);
    if (!$cfg) {
        return [];
    }

    $statuses = ['publish', 'draft', 'pending', 'future', 'private'];
    $counts = array_fill_keys($statuses, 0);

    $ids = get_posts([
        'post_type' => $post_type,
        'post_status' => $statuses,
        'numberposts' => -1,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    foreach ($ids as $id) {
        $st = get_post_status($id);
        if (isset($counts[$st])) {
            $counts[$st]++;
        }
    }

    // Liste plate de toutes les meta_keys auditées
    $all_fields = [];
    foreach ($cfg['groups'] as $g) {
        foreach ($g['fields'] as $f) {
            $all_fields[] = $f['key'];
        }
    }
    $all_fields = array_values(array_unique($all_fields));

    $published_ids = array_values(array_filter($ids, fn($id) => get_post_status($id) === 'publish'));
    $pub_count = count($published_ids);

    // Matrice des valeurs : [post_id][meta_key] = meta_value
    $matrix = [];
    if ($pub_count > 0 && !empty($all_fields)) {
        global $wpdb;
        $ph_ids = implode(',', array_fill(0, $pub_count, '%d'));
        $ph_keys = implode(',', array_fill(0, count($all_fields), '%s'));
        $sql = $wpdb->prepare(
            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
             WHERE post_id IN ($ph_ids) AND meta_key IN ($ph_keys)",
            array_merge($published_ids, $all_fields)
        );
        foreach ($wpdb->get_results($sql) as $r) {
            $matrix[$r->post_id][$r->meta_key] = $r->meta_value;
        }
    }

    // Compteurs par champ + par groupe + détection cours sans WC
    $per_field = array_fill_keys($all_fields, 0);
    $missing_wc = [];
    foreach ($published_ids as $pid) {
        foreach ($all_fields as $key) {
            $v = $matrix[$pid][$key] ?? '';
            if (wamv1_widget_field_is_filled($v)) {
                $per_field[$key]++;
            } elseif ($key === 'wc_product_id') {
                $missing_wc[] = $pid;
            }
        }
    }

    $groups = [];
    foreach ($cfg['groups'] as $gkey => $g) {
        $fields_out = [];
        $g_filled = 0;
        $g_total = count($g['fields']) * $pub_count;
        foreach ($g['fields'] as $f) {
            $n = $per_field[$f['key']] ?? 0;
            $g_filled += $n;
            $fields_out[] = [
                'key' => $f['key'],
                'label' => $f['label'],
                'filled' => $n,
                'rate' => $pub_count > 0 ? round(($n / $pub_count) * 100) : 0,
            ];
        }
        $groups[$gkey] = [
            'label' => $g['label'],
            'detail' => !empty($g['detail']),
            'filled' => $g_filled,
            'total' => $g_total,
            'rate' => $g_total > 0 ? round(($g_filled / $g_total) * 100) : 0,
            'fields' => $fields_out,
        ];
    }

    $global_filled = array_sum(array_column($groups, 'filled'));
    $global_total = array_sum(array_column($groups, 'total'));

    $data = [
        'counts' => $counts,
        'total' => count($ids),
        'published' => $pub_count,
        'groups' => $groups,
        'global_rate' => $global_total > 0 ? round(($global_filled / $global_total) * 100) : 0,
        'missing_wc' => array_slice($missing_wc, 0, 5),
        'missing_wc_count' => count($missing_wc),
        'generated_at' => time(),
    ];

    set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
    return $data;
}

// Invalidation cache
foreach (['cours', 'stages'] as $pt) {
    add_action('save_post_' . $pt, function () use ($pt) {
        delete_transient('wamv1_widget_data_' . $pt);
    });
}
add_action('deleted_post', function ($post_id) {
    $pt = get_post_type($post_id);
    if (in_array($pt, ['cours', 'stages'], true)) {
        delete_transient('wamv1_widget_data_' . $pt);
    }
});

// ---------------------------------------------------------------
// Accès
// ---------------------------------------------------------------
function wamv1_can_view_cpt_widget(): bool
{
    $u = wp_get_current_user();
    if (!$u || !$u->ID) {
        return false;
    }
    if (user_can($u, 'manage_options')) {
        return true;
    }
    $roles = (array) $u->roles;
    return in_array('directrice', $roles, true) || in_array('admin_technique', $roles, true);
}

// ---------------------------------------------------------------
// Enregistrement des widgets + CSS
// ---------------------------------------------------------------
add_action('wp_dashboard_setup', function () {
    if (!wamv1_can_view_cpt_widget()) {
        return;
    }

    // Nettoyage : retire les widgets natifs peu pertinents pour WAM
    // (Actualités WP, D'un coup d'œil, Activité, Brouillon rapide, vue Yoast).
    $to_remove = [
        'dashboard_primary' => 'side',    // Événements et nouvelles WordPress
        'dashboard_right_now' => 'normal',  // D'un coup d'œil
        'dashboard_activity' => 'normal',  // Activité
        'dashboard_quick_press' => 'side',    // Brouillon rapide
        'wpseo-dashboard-overview' => 'normal',  // Yoast : Vue d'ensemble SEO
    ];
    foreach ($to_remove as $id => $ctx) {
        remove_meta_box($id, 'dashboard', $ctx);
    }

    foreach (['cours', 'stages'] as $pt) {
        $cfg = wamv1_widget_config($pt);
        if (!$cfg) {
            continue;
        }
        wp_add_dashboard_widget(
            $cfg['widget_id'],
            $cfg['title'],
            function () use ($pt) {
                wamv1_render_cpt_widget($pt); }
        );
    }

    // Force nos widgets tout en haut de la colonne principale.
    // wp_add_dashboard_widget() les ajoute en fin de 'normal/core' : on
    // réordonne $wp_meta_boxes pour les remonter en tête.
    global $wp_meta_boxes;
    $normal_core = &$wp_meta_boxes['dashboard']['normal']['core'];
    if (is_array($normal_core)) {
        $ours = [];
        foreach (['wamv1_cours_widget', 'wamv1_stages_widget'] as $id) {
            if (isset($normal_core[$id])) {
                $ours[$id] = $normal_core[$id];
                unset($normal_core[$id]);
            }
        }
        if ($ours) {
            $normal_core = $ours + $normal_core;
        }
    }
}, 999);

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'index.php' || !wamv1_can_view_cpt_widget()) {
        return;
    }
    $ver = wp_get_theme()->get('Version');
    wp_enqueue_style(
        'wamv1-widget-cpt',
        get_template_directory_uri() . '/assets/css/admin-widget-cours.css',
        [],
        $ver
    );
    wp_enqueue_style(
        'wamv1-widget-cpt-font',
        'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500&display=swap',
        [],
        null
    );
});

// ---------------------------------------------------------------
// Rendu
// ---------------------------------------------------------------
function wamv1_render_cpt_widget(string $post_type): void
{
    $cfg = wamv1_widget_config($post_type);
    if (!$cfg) {
        return;
    }
    $d = wamv1_widget_data($post_type);

    $r = 52;
    $c = 2 * M_PI * $r;
    $offset = $c * (1 - $d['global_rate'] / 100);

    $status_labels = [
        'publish' => 'Publiés',
        'draft' => 'Brouillons',
        'pending' => 'En attente',
        'future' => 'Planifiés',
        'private' => 'Privés',
    ];

    $grad_id = 'wam-donut-grad-' . $post_type;
    ?>
    <div id="wam-widget-<?php echo esc_attr($post_type); ?>" class="wam-widget">

        <!-- HEAD : donut + totaux -->
        <div class="wam-widget__head">
            <div class="wam-widget__donut" role="img"
                aria-label="Taux de remplissage global : <?php echo (int) $d['global_rate']; ?>%">
                <svg viewBox="0 0 120 120" width="120" height="120" aria-hidden="true">
                    <defs>
                        <linearGradient id="<?php echo esc_attr($grad_id); ?>" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#00D6B2" />
                            <stop offset="50%" stop-color="#F1CE00" />
                            <stop offset="100%" stop-color="#FF6207" />
                        </linearGradient>
                    </defs>
                    <circle cx="60" cy="60" r="<?php echo $r; ?>" class="wam-widget__donut-track" />
                    <circle cx="60" cy="60" r="<?php echo $r; ?>" class="wam-widget__donut-fill"
                        stroke="url(#<?php echo esc_attr($grad_id); ?>)" stroke-dasharray="<?php echo esc_attr($c); ?>"
                        stroke-dashoffset="<?php echo esc_attr($offset); ?>" transform="rotate(-90 60 60)" />
                </svg>
                <div class="wam-widget__donut-label">
                    <span class="wam-widget__donut-num"><?php echo (int) $d['global_rate']; ?><em>%</em></span>
                    <span class="wam-widget__donut-cap">complété</span>
                </div>
            </div>

            <div class="wam-widget__totals">
                <p class="wam-widget__total-num"><?php echo (int) $d['total']; ?></p>
                <p class="wam-widget__total-cap">
                    <?php echo $d['total'] > 1 ? esc_html($cfg['noun'][1]) . ' au total' : esc_html($cfg['noun'][0]) . ' au total'; ?>
                </p>

                <ul class="wam-widget__statuses">
                    <?php foreach ($status_labels as $slug => $label):
                        $n = (int) ($d['counts'][$slug] ?? 0);
                        if ($n === 0 && !in_array($slug, ['publish', 'draft'], true))
                            continue; ?>
                        <li class="wam-widget__status wam-widget__status--<?php echo esc_attr($slug); ?>">
                            <span class="wam-widget__status-dot" aria-hidden="true"></span>
                            <span class="wam-widget__status-num"><?php echo $n; ?></span>
                            <span class="wam-widget__status-label"><?php echo esc_html($label); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- GROUPES -->
        <div class="wam-widget__groups">
            <p class="wam-widget__section-title">
                <span>Remplissage par catégorie</span>
                <span class="wam-widget__scope">sur <?php echo (int) $d['published']; ?>
                    <?php echo esc_html($cfg['noun'][1]); ?> publiés</span>
            </p>

            <ul class="wam-widget__bars">
                <?php foreach ($d['groups'] as $gkey => $g):
                    $is_wc = ($gkey === 'wc');
                    ?>
                    <li class="wam-widget__bar<?php echo $is_wc ? ' is-critical' : ''; ?>">
                        <div class="wam-widget__bar-head">
                            <span class="wam-widget__bar-label">
                                <?php if ($is_wc): ?>
                                    <span class="wam-widget__pulse" aria-hidden="true"></span>
                                <?php endif; ?>
                                <?php echo esc_html($g['label']); ?>
                            </span>
                            <span class="wam-widget__bar-rate"><?php echo (int) $g['rate']; ?>%</span>
                        </div>
                        <div class="wam-widget__bar-track" aria-hidden="true">
                            <div class="wam-widget__bar-fill" style="width: <?php echo (int) $g['rate']; ?>%"></div>
                        </div>
                        <p class="wam-widget__bar-meta">
                            <?php printf('%d / %d champs renseignés', (int) $g['filled'], (int) $g['total']); ?>
                        </p>

                        <!-- Détail champ par champ (Essentiels) -->
                        <?php if ($g['detail'] && $d['published'] > 0): ?>
                            <ul class="wam-widget__fields">
                                <?php foreach ($g['fields'] as $f):
                                    $rate_class = $f['rate'] >= 90 ? 'is-full'
                                        : ($f['rate'] >= 50 ? 'is-mid' : 'is-low');
                                    ?>
                                    <li class="wam-widget__field <?php echo esc_attr($rate_class); ?>">
                                        <span class="wam-widget__field-dot" aria-hidden="true"></span>
                                        <span class="wam-widget__field-label"><?php echo esc_html($f['label']); ?></span>
                                        <span class="wam-widget__field-rate"><?php echo (int) $f['rate']; ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <!-- Alerte WC rattachée directement à la catégorie -->
                        <?php if ($is_wc && $d['missing_wc_count'] > 0): ?>
                            <div class="wam-widget__alert" role="note">
                                <div class="wam-widget__alert-head">
                                    <span class="wam-widget__alert-icon" aria-hidden="true">!</span>
                                    <div>
                                        <p class="wam-widget__alert-title">
                                            <?php printf(
                                                _n(
                                                    '%d %s publié sans lien WooCommerce',
                                                    '%d %s publiés sans lien WooCommerce',
                                                    $d['missing_wc_count'],
                                                    'wamv1'
                                                ),
                                                $d['missing_wc_count'],
                                                $d['missing_wc_count'] > 1 ? $cfg['noun'][1] : trim(preg_replace('/^un /', '', $cfg['noun'][0]))
                                            ); ?>
                                        </p>
                                        <p class="wam-widget__alert-text">
                                            Les inscriptions ne peuvent pas aboutir tant qu'un produit n'est pas relié.
                                        </p>
                                    </div>
                                </div>
                                <?php if (!empty($d['missing_wc'])): ?>
                                    <ul class="wam-widget__alert-list">
                                        <?php foreach ($d['missing_wc'] as $pid): ?>
                                            <li>
                                                <a href="<?php echo esc_url(get_edit_post_link($pid)); ?>">
                                                    <?php echo esc_html(get_the_title($pid) ?: '(sans titre)'); ?>
                                                    <span aria-hidden="true">↗</span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- FOOT -->
        <div class="wam-widget__foot">
            <a href="<?php echo esc_url($cfg['edit_url']); ?>" class="wam-widget__cta">
                <?php echo esc_html($cfg['cta']); ?>
                <span aria-hidden="true">→</span>
            </a>
            <span class="wam-widget__ts">
                Mis à jour à <?php echo esc_html(wp_date('H:i', $d['generated_at'])); ?>
            </span>
        </div>
    </div>
    <?php
}
