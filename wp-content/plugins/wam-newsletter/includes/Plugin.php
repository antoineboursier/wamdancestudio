<?php
namespace WamNewsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Point de câblage unique : qui s'accroche à quoi.
 * Aucune logique métier ici.
 */
class Plugin {

	public static function boot(): void {
		add_action( 'admin_init', array( Install::class, 'maybe_upgrade' ) );

		Admin\Menu::register_hooks();
		Editor\NewsletterPostType::register_hooks();
		Subscribers\Screen::register_hooks();
		Lists\Screen::register_hooks();
		Settings\SettingsPage::register_hooks();
		Sending\Mailer::register_hooks();

		CLI\Commands::register();
	}
}
