<?php
/**
 * Fonctions pour récupérer dynamiquement les avis et notes Google Business
 * @package wamv1
 */

/**
 * Récupère la note et le nombre d'avis Google pour le studio.
 * Utilise un transient pour mettre en cache le résultat pendant 24h.
 */
function wamv1_get_google_business_stats() {
    $cache_key = 'wam_google_stats';
    $stats = get_transient($cache_key);

    if (false === $stats) {
        // Valeurs par défaut (dernier relevé manuel)
        $stats = [
            'rating' => '4.7',
            'count'  => '23',
            'last_update' => current_time('mysql')
        ];

        // Ici, on pourrait ajouter un appel à l'API Google Places
        // Nécessite une clé API Google Maps Platform
        $api_key = defined('GOOGLE_PLACES_API_KEY') ? GOOGLE_PLACES_API_KEY : '';
        $place_id = 'ChIJd0KXL_Mow0cRtBvgQAFW16Q';

        if (!empty($api_key)) {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$place_id}&fields=rating,user_ratings_total&key={$api_key}&language=fr";
            
            $response = wp_remote_get($url);
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['result'])) {
                    $stats['rating'] = (string)$data['result']['rating'];
                    $stats['count']  = (string)$data['result']['user_ratings_total'];
                    
                    // Mise en cache pour 24 heures
                    set_transient($cache_key, $stats, DAY_IN_SECONDS);
                }
            }
        }
    }

    return $stats;
}
