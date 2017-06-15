<?php
header("Content-type: text/css");
global $wpdb;
$selectedColor	= get_option( 'bookaroom_setupColor' );
$selectedFont	= get_option( 'bookaroom_setupFont' );
$reservedColor	= get_option( 'bookaroom_reservedColor' );
$reservedFont	= get_option( 'bookaroom_reservedFont' );

?>
#hoursTable .calHoursSetup td {
	background: <?php echo $selectedColor; ?>;	
    color: <?php echo $selectedFont; ?>;	
}

#hoursTable .calHoursReserved td {
	background: <?php echo $reservedColor; ?>;	
    color: <?php echo $reservedFont; ?>;	
}

