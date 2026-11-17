<?php
/**
 * Email wrapper.
 *
 * @var string $subject
 * @var string $body HTML.
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title><?php echo esc_html( $subject ); ?></title></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 12px;">
	<tr><td align="center">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
			<tr><td style="background:#4f46e5;color:#ffffff;padding:20px 28px;font-size:18px;font-weight:600;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></td></tr>
			<tr><td style="padding:28px;font-size:15px;line-height:1.6;"><?php echo wp_kses_post( $body ); ?></td></tr>
			<tr><td style="padding:16px 28px;background:#f9fafb;color:#6b7280;font-size:12px;"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#6b7280;"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a></td></tr>
		</table>
	</td></tr>
</table>
</body></html>
