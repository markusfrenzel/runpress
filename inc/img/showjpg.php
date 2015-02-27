<?php
$var_image = $_GET[ 'image' ];
$var_text = $_GET[ 'text' ];
$image = ImageCreateFromJPEG( $var_image );
$text = $var_text;
$fw = imagefontwidth( 4 );
$fh = imagefontheight( 4 );
$l = strlen( $text );
$tw = $l * $fw;
$th = $fh;
$iw = imagesx( $image );
$ih = imagesy( $image );
$xpos = ( $iw - $tw ) / 2;
$ypos = ( $ih - $th ) / 2;
/* Define the color of the string to display */
$color = imagecolorallocate( $image, 0, 0, 0 );
/* Write the string to the center of the image */
imagestring( $image, 4, $xpos, $ypos, $text, $color );
/* Show the image */
header('Content-type: image/jpeg');
imagejpeg( $image );
imagedestroy( $image );
?>
