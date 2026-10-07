<?php
namespace WamNewsletter\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Messages d'administration qui survivent à une redirection.
 *
 * Les écrans du plugin écrivent puis redirigent (motif « post / redirect / get »,
 * qui évite de rejouer une action sur un rafraîchissement). Le message doit donc
 * être stocké entre les deux requêtes.
 *
 * Stockage dans un transient propre à l'utilisateur·rice : un message destiné à
 * une personne ne doit pas s'afficher chez une autre, et la péremption évite
 * qu'un message jamais lu reste en base indéfiniment.
 */
class Notices {

	const PREFIX = 'wam_nl_notices_';

	/** 5 minutes : largement assez pour la redirection qui suit. */
	const TTL = 300;

	private static function key(): string {
		return self::PREFIX . get_current_user_id();
	}

	private static function add( string $type, string $message ): void {
		if ( '' === trim( $message ) || ! get_current_user_id() ) {
			return;
		}
		$liste   = (array) get_transient( self::key() );
		$liste[] = array(
			'type'    => $type,
			'message' => $message,
		);
		set_transient( self::key(), $liste, self::TTL );
	}

	public static function success( string $message ): void {
		self::add( 'success', $message );
	}

	public static function error( string $message ): void {
		self::add( 'error', $message );
	}

	public static function warning( string $message ): void {
		self::add( 'warning', $message );
	}

	/** Affiche puis consomme : un message ne doit apparaître qu'une fois. */
	public static function render(): void {
		$liste = (array) get_transient( self::key() );
		if ( ! $liste ) {
			return;
		}
		delete_transient( self::key() );

		foreach ( $liste as $notice ) {
			$type = in_array( $notice['type'] ?? '', array( 'success', 'error', 'warning', 'info' ), true )
				? $notice['type']
				: 'info';
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				esc_html( (string) $notice['message'] )
			);
		}
	}
}
