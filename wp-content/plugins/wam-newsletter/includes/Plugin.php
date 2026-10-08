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

		// Contenu et données
		Editor\NewsletterPostType::register_hooks();
		Render\Images::register_hooks();
		Blocks\Blocks::register_hooks();
		Blocks\Patterns::register_hooks();

		// Administration
		Admin\Menu::register_hooks();
		Editor\NewsletterList::register_hooks();
		Editor\EditorSetup::register_hooks();
		Subscribers\Screen::register_hooks();
		Lists\Screen::register_hooks();
		Stats\Screen::register_hooks();
		Settings\SettingsPage::register_hooks();
		Settings\ContentScreen::register_hooks();

		// Public
		Form\Form::register_hooks();
		Tracking\Endpoints::register_hooks();

		// Envoi
		Sending\Mailer::register_hooks();
		Sending\Scheduler::register_hooks();

		// API et ligne de commande
		Editor\RestApi::register_hooks();
		CLI\Commands::register();
	}
}
