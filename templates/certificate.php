<?php
/**
 * Printable certificate (full HTML page – use browser "Print → Save as PDF").
 *
 * @var string $name
 * @var string $course
 * @var string $date
 * @var string $code
 * @var string $title
 * @var string $signer
 * @var string $accent
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title . ' – ' . $name ); ?></title>
<style>
	@page { size: A4 landscape; margin: 0; }
	* { box-sizing: border-box; }
	body { margin: 0; background: #e5e7eb; font-family: Georgia, 'Times New Roman', serif; color: #111827; }
	.sheet { width: 297mm; height: 210mm; margin: 20px auto; background: #fff; padding: 14mm; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,.15); }
	.frame { border: 3px solid <?php echo esc_attr( $accent ); ?>; outline: 1px solid <?php echo esc_attr( $accent ); ?>; outline-offset: -10px; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 16mm; }
	.site { letter-spacing: .3em; text-transform: uppercase; font-size: 13px; color: #6b7280; font-family: Arial, sans-serif; }
	h1 { font-size: 46px; margin: 14px 0 6px; color: <?php echo esc_attr( $accent ); ?>; font-weight: normal; }
	.lead { font-size: 18px; color: #4b5563; margin: 18px 0 6px; }
	.name { font-size: 42px; font-style: italic; border-bottom: 1px solid #d1d5db; padding: 0 30px 6px; margin: 4px 0 10px; }
	.course { font-size: 26px; font-weight: bold; margin: 8px 0 24px; }
	.footer { display: flex; justify-content: space-between; width: 100%; margin-top: auto; font-family: Arial, sans-serif; font-size: 13px; color: #4b5563; }
	.footer div { min-width: 200px; border-top: 1px solid #9ca3af; padding-top: 6px; }
	.print { position: fixed; top: 16px; right: 16px; background: <?php echo esc_attr( $accent ); ?>; color: #fff; border: 0; padding: 10px 18px; border-radius: 6px; font: 600 14px Arial, sans-serif; cursor: pointer; }
	@media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; } .print { display: none; } }
	@media (max-width: 1100px) { .sheet { transform: scale(.5); transform-origin: top left; } }
</style>
</head>
<body>
<button class="print" onclick="window.print()"><?php esc_html_e( 'Print / Save PDF', 'mg-membership' ); ?></button>
<div class="sheet"><div class="frame">
	<div class="site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
	<h1><?php echo esc_html( $title ); ?></h1>
	<div class="lead"><?php esc_html_e( 'This certifies that', 'mg-membership' ); ?></div>
	<div class="name"><?php echo esc_html( $name ); ?></div>
	<div class="lead"><?php esc_html_e( 'has successfully completed the course', 'mg-membership' ); ?></div>
	<div class="course"><?php echo esc_html( $course ); ?></div>
	<div class="footer">
		<div><?php echo esc_html( $date ); ?><br><small><?php esc_html_e( 'Date', 'mg-membership' ); ?></small></div>
		<div><?php echo esc_html( $code ); ?><br><small><?php esc_html_e( 'Verification code', 'mg-membership' ); ?></small></div>
		<div><?php echo esc_html( $signer ? $signer : get_bloginfo( 'name' ) ); ?><br><small><?php esc_html_e( 'Signature', 'mg-membership' ); ?></small></div>
	</div>
</div></div>
</body>
</html>
