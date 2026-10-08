<?php
/**
 * Gabarit du document e-mail (§7.6.1).
 *
 * Reçoit $wam_nl : couleurs, police, largeur, titre, preheader, contenu,
 * vue_url, pixel, langue.
 *
 * Points non négociables de ce fichier :
 *  - `<meta name="color-scheme" content="dark">` : l'e-mail est déjà sombre, on
 *    demande aux clients de ne pas inverser les couleurs (§7.6.6) ;
 *  - le preheader est la toute première cellule, masquée : c'est ce que les
 *    boîtes affichent en aperçu, après l'objet ;
 *  - le seul `<style>` du document ne contient que des media queries, seul cas
 *    autorisé par le §7.4 (aucun client ne garantit le reste).
 *
 * @var array $wam_nl
 */

defined( 'ABSPATH' ) || exit;

$c         = $wam_nl['couleurs'];
$police    = $wam_nl['police'];
$largeur   = (int) $wam_nl['largeur'];
$preheader = (string) $wam_nl['preheader'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $wam_nl['langue'] ); ?>" style="margin:0;padding:0;">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="dark">
<meta name="supported-color-schemes" content="dark">
<title><?php echo esc_html( $wam_nl['titre'] ); ?></title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style type="text/css">
	/* Media queries uniquement : tout le reste est en styles en ligne (§7.4). */
	@media only screen and (max-width: 620px) {
		.wam-nl-col {
			display: block !important;
			width: 100% !important;
			max-width: 100% !important;
		}
		.wam-nl-gutter {
			display: none !important;
			width: 0 !important;
		}
		.wam-nl-stack-space {
			height: 16px !important;
		}
		/* Bannière : pleine largeur d'écran, puis agrandie à 140 % et centrée.
		   Le logo est zoomé, les côtés sont rognés par overflow. */
		.wam-nl-banner {
			margin: 0 -20px !important;
			overflow: hidden !important;
		}
		.wam-nl-banner__img {
			width: 140% !important;
			max-width: none !important;
			margin-left: -20% !important;
		}
	}
</style>
</head>
<body style="margin:0;padding:0;width:100%;background-color:<?php echo esc_attr( $c['background'] ); ?>;color:<?php echo esc_attr( $c['text'] ); ?>;font-family:<?php echo esc_attr( $police ); ?>;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

<?php if ( '' !== $preheader ) : ?>
	<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:<?php echo esc_attr( $c['background'] ); ?>;">
		<?php echo esc_html( $preheader ); ?>
		<?php
		// Caractères invisibles de remplissage : sans eux, les boîtes complètent
		// l'aperçu avec le début du corps de l'e-mail, et le preheader perd son
		// intérêt.
		echo str_repeat( '&#847;&zwnj;&nbsp;', 60 );
		?>
	</div>
<?php endif; ?>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
	style="border-collapse:collapse;width:100%;background-color:<?php echo esc_attr( $c['background'] ); ?>;">
	<tr>
		<td align="center" style="padding:0;">

			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="<?php echo esc_attr( (string) $largeur ); ?>"
				style="border-collapse:collapse;width:100%;max-width:<?php echo esc_attr( (string) $largeur ); ?>px;background-color:<?php echo esc_attr( $c['background'] ); ?>;">

				<!-- Voir dans le navigateur (§7.6.4) -->
				<tr>
					<td align="center" style="padding:12px 20px 0;">
						<p style="margin:0;font-family:<?php echo esc_attr( $police ); ?>;font-size:12px;line-height:1.4;color:<?php echo esc_attr( $c['muted'] ); ?>;text-align:center;">
							<a href="<?php echo $wam_nl['vue_url']; // phpcs:ignore WordPress.Security.EscapeOutput -- marqueur remplacé à l'envoi. ?>"
								target="_blank" rel="noopener"
								style="color:<?php echo esc_attr( $c['muted'] ); ?>;text-decoration:underline;">
								<?php esc_html_e( 'Voir dans le navigateur', 'wam-newsletter' ); ?>
							</a>
						</p>
					</td>
				</tr>

				<tr>
					<td style="padding:0;">
						<?php echo $wam_nl['contenu']; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML construit et échappé par les renderers. ?>
					</td>
				</tr>

			</table>

		</td>
	</tr>
</table>

<?php echo $wam_nl['pixel']; // phpcs:ignore WordPress.Security.EscapeOutput -- marqueur remplacé à l'envoi. ?>
</body>
</html>
