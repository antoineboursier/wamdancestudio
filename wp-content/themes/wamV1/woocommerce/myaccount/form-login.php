<?php
/**
 * Login Form
 *
 * @package WooCommerce/Templates
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$is_tunnel = ! empty( $_GET['redirect'] ) && strpos( $_GET['redirect'], 'commande' ) !== false;
$icon_dir = get_template_directory_uri() . '/assets/images/';

do_action( 'woocommerce_before_customer_login_form' ); ?>

<div class="wam-account-login <?php echo $is_tunnel ? 'wam-account-login--tunnel' : ''; ?>" id="customer_login">

    <?php if ($is_tunnel) : ?>
        <!-- Bandeau tunnel -->
        <div class="wam-tunnel-banner">
            <h1 class="title-sign-md has-accent-yellow-color mb-xs">Étape 1 : Connexion ou création de compte</h1>
            <p class="text-md color-subtext">Pour finaliser votre réservation, veuillez vous identifier ou créer votre compte WAM.</p>
        </div>
    <?php endif; ?>

    <div class="wam-account-login__main">
        <div class="wam-account-login__columns">
            
            <!-- LOGIN -->
            <div class="wam-account-login__col wam-login-col">
                <h2 class="title-norm-sm mb-lg"><?php esc_html_e( 'Me connecter', 'wamv1' ); ?></h2>

                <form class="woocommerce-form woocommerce-form-login login" method="post">

                    <?php do_action( 'woocommerce_login_form_start' ); ?>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="username"><?php esc_html_e( 'Email', 'wamv1' ); ?>&nbsp;<span class="required">*</span></label>
                        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" placeholder="votre@email.com" required />
                    </p>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="password"><?php esc_html_e( 'Mot de passe', 'wamv1' ); ?>&nbsp;<span class="required">*</span></label>
                        <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required />
                    </p>

                    <?php do_action( 'woocommerce_login_form' ); ?>

                    <p class="form-row">
                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                            <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Se souvenir de moi', 'wamv1' ); ?></span>
                        </label>
                        <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                        <button type="submit" class="btn-primary woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>">
                            <?php esc_html_e( 'Connexion', 'wamv1' ); ?>
                            <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>chevron-right.svg');"></span>
                        </button>
                    </p>
                    <p class="woocommerce-LostPassword lost_password">
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="text-sm color-subtext"><?php esc_html_e( 'Mot de passe oublié ?', 'wamv1' ); ?></a>
                    </p>

                    <?php do_action( 'woocommerce_login_form_end' ); ?>

                </form>
            </div>

            <!-- REGISTER -->
            <?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
                <div class="wam-account-login__col wam-register-col">
                    <h2 class="title-norm-sm color-green mb-lg"><?php esc_html_e( 'Créer mon compte', 'wamv1' ); ?></h2>

                    <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

                        <?php do_action( 'woocommerce_register_form_start' ); ?>

                        <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                                <label for="reg_username"><?php esc_html_e( 'Identifiant', 'wamv1' ); ?>&nbsp;<span class="required">*</span></label>
                                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required />
                            </p>
                        <?php endif; ?>

                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="reg_email"><?php esc_html_e( 'Email', 'wamv1' ); ?>&nbsp;<span class="required">*</span></label>
                            <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" placeholder="votre@email.com" required />
                        </p>

                        <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                                <label for="reg_password"><?php esc_html_e( 'Mot de passe', 'wamv1' ); ?>&nbsp;<span class="required">*</span></label>
                                <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required />
                                <p class="text-xs color-subtext mt-xs">Le mot de passe doit comporter au moins 12 caractères.</p>
                            </p>
                        <?php endif; ?>

                        <?php do_action( 'woocommerce_register_form' ); ?>

                        <p class="woocommerce-form-row form-row">
                            <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                            <button type="submit" class="btn-primary woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">
                                <?php esc_html_e( 'Créer mon compte', 'wamv1' ); ?>
                                <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>chevron-right.svg');"></span>
                            </button>
                        </p>

                        <?php do_action( 'woocommerce_register_form_end' ); ?>

                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if ($is_tunnel) : ?>
        <!-- Récapitulatif panier tunnel -->
        <aside class="wam-tunnel-cart">
            <h3 class="title-norm-sm mb-md"><?php _e('Votre panier', 'wamv1'); ?></h3>
            <ul class="wam-tunnel-cart__list">
                <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) : 
                    $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                    if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) :
                        ?>
                        <li class="wam-tunnel-cart__item">
                            <div class="wam-tunnel-cart__item-info">
                                <span class="text-sm fw-bold d-block"><?php echo apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ); ?></span>
                                <span class="text-xs color-subtext"><?php echo WC()->cart->get_product_price( $_product ); ?> x <?php echo $cart_item['quantity']; ?></span>
                            </div>
                            <span class="text-sm fw-bold"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?></span>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="wam-tunnel-cart__total mt-md pt-md border-top">
                <span class="text-md fw-bold"><?php _e('Total', 'wamv1'); ?></span>
                <span class="text-md fw-bold has-accent-yellow-color"><?php wc_cart_totals_order_total_html(); ?></span>
            </div>
        </aside>
    <?php endif; ?>

</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
