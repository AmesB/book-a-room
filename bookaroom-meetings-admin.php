<?PHP
class bookaroom_settings_admin
{
	############################################
	#
	# Amenity managment
	#
	############################################
	public static function bookaroom_admin_admin()
	{
		
		# first, is there an action?
		$externals = self::getExternalsAdmin();
		switch( $externals['action'] ):	
			case 'updateSettings':
				if( ( $error = self::checkSettings( $externals ) ) == TRUE ):
					self::showMeetingRoomAdmin( $externals, $error );
					break;
				else:
					self::updateSettings( $externals );
					self::showMeetingRoomUpdateSuccess();
					break;
				endif;

			default:
				$externals = self::getDefaults();
				self::showMeetingRoomAdmin( $externals );
				break;
				
		endswitch;
	}
	
	# sub functions:
	
	public static function updateSettings( $externals )
	{
		update_option( 'bookaroom_baseIncrement', 					$externals['bookaroom_baseIncrement'] );
		update_option( 'bookaroom_setupIncrement', 					$externals['bookaroom_setupIncrement'] );
		update_option( 'bookaroom_cleanupIncrement', 				$externals['bookaroom_cleanupIncrement'] );
		update_option( 'bookaroom_reservation_URL', 				$externals['bookaroom_reservation_URL'] );
		update_option( 'bookaroom_setupColor', 			strtoupper(	$externals['bookaroom_setupColor'] ) );
		update_option( 'bookaroom_setupFont',			strtoupper(	$externals['bookaroom_setupFont'] ) );
		update_option( 'bookaroom_reservedColor',		strtoupper(	$externals['bookaroom_reservedColor'] ) );
		update_option( 'bookaroom_reservedFont',		strtoupper(	$externals['bookaroom_reservedFont'] ) );
		update_option( 'bookaroom_reserveBuffer',		strtoupper(	$externals['bookaroom_reserveBuffer'] ) );
		update_option( 'bookaroom_reserveAllowed',		strtoupper(	$externals['bookaroom_reserveAllowed'] ) );
		update_option( 'bookaroom_defaultEmailDaily',	strtolower(	$externals['bookaroom_defaultEmailDaily'] ) );
		update_option( 'bookaroom_daysBeforeRemind', 	strtolower(	$externals['bookaroom_daysBeforeRemind'] ) );
		
		update_option( 'bookaroom_waitingListDefault', 				$externals['bookaroom_waitingListDefault'] );
		update_option( 'bookaroom_profitDeposit', 					$externals['bookaroom_profitDeposit'] );
		update_option( 'bookaroom_nonProfitDeposit', 				$externals['bookaroom_nonProfitDeposit'] );
		update_option( 'bookaroom_profitIncrementPrice', 			$externals['bookaroom_profitIncrementPrice'] );
		
		update_option( 'bookaroom_nonProfitIncrementPrice',			$externals['bookaroom_nonProfitIncrementPrice'] );
		update_option( 'bookaroom_eventLink',						$externals['bookaroom_eventLink'] );
		update_option( 'bookaroom_paymentLink', 					$externals['bookaroom_paymentLink'] );
		update_option( 'bookaroom_libcardRegex', 					$externals['bookaroom_libcardRegex'] );
		update_option( 'bookaroom_obfuscatePublicNames', 			$externals['bookaroom_obfuscatePublicNames'] );
		
		update_option( 'bookaroom_hide_contract', 					$externals['bookaroom_hide_contract'] );
		
		
		if( !empty( $externals['bookaroom_screenWidth'] ) ):
			update_option( 'bookaroom_screenWidth', 1 );
		else:
			update_option( 'bookaroom_screenWidth', 0 );
		endif;

		
	}
	
	public static function getDefaults()
	{
		$option = array();
		$option['bookaroom_baseIncrement']				= get_option( 'bookaroom_baseIncrement' );
		$option['bookaroom_setupIncrement']				= get_option( 'bookaroom_setupIncrement' );
		$option['bookaroom_cleanupIncrement']			= get_option( 'bookaroom_cleanupIncrement' );
		$option['bookaroom_reservation_URL']			= get_option( 'bookaroom_reservation_URL' );
		$option['bookaroom_daysBeforeRemind']			= get_option( 'bookaroom_daysBeforeRemind' );
		$option['bookaroom_setupColor']					= get_option( 'bookaroom_setupColor' );
		$option['bookaroom_setupFont']					= get_option( 'bookaroom_setupFont' );
		$option['bookaroom_reservedColor']				= get_option( 'bookaroom_reservedColor' );
		$option['bookaroom_reservedFont']				= get_option( 'bookaroom_reservedFont' );
		$option['bookaroom_reserveBuffer']				= get_option( 'bookaroom_reserveBuffer' );
		$option['bookaroom_reserveAllowed']				= get_option( 'bookaroom_reserveAllowed' );
		$option['bookaroom_alertEmail']					= get_option( 'bookaroom_alertEmail' );
		$option['bookaroom_defaultEmailDaily']			= get_option( 'bookaroom_defaultEmailDaily' );
		$option['bookaroom_waitingListDefault']			= get_option( 'bookaroom_waitingListDefault' );

		$option['bookaroom_profitDeposit']				= get_option( 'bookaroom_profitDeposit' );
		$option['bookaroom_nonProfitDeposit']			= get_option( 'bookaroom_nonProfitDeposit' );
		$option['bookaroom_profitIncrementPrice']		= get_option( 'bookaroom_profitIncrementPrice' );
		$option['bookaroom_nonProfitIncrementPrice']	= get_option( 'bookaroom_nonProfitIncrementPrice' );
		$option['bookaroom_eventLink']					= get_option( 'bookaroom_eventLink' );
		
		$option['bookaroom_screenWidth']				= get_option( 'bookaroom_screenWidth' );
		
		$option['bookaroom_paymentLink']				= get_option( 'bookaroom_paymentLink' );
		$option['bookaroom_libcardRegex']				= get_option( 'bookaroom_libcardRegex' );
		$option['bookaroom_obfuscatePublicNames']		= get_option( 'bookaroom_obfuscatePublicNames' );		
		$option['bookaroom_addressType']				= get_option( 'bookaroom_addressType' );
		
		$option['bookaroom_hide_contract']				= get_option( 'bookaroom_hide_contract' );
				
		return $option;
	}
	
	public static function checkSettings( $externals )
	{
		# check for each item - if NULL
		$error = array();
		$final = NULL;
		
		$checkArray = array( 'bookaroom_reservation_URL' => 'Reservation URL', 'bookaroom_setupColor' => 'Setup Background Color ', 'bookaroom_setupFont' => 'Setup Font Color', 'bookaroom_reservedColor' => 'Reserved Background Color', 'bookaroom_reservedFont' => 'Reserved Font Color', 'bookaroom_daysBeforeRemind' => 'Days before meeting to send reminders' );
		foreach( $checkArray as $key => $val ):
			if( is_null( $externals[$key] ) ):
				$error[] = "Please enter a value for the <strong>{$val}</strong>";
			endif;
		endforeach;
		
		# check colors
		$checkArray = array( 'bookaroom_setupColor' => 'Setup Background Color ', 'bookaroom_setupFont' => 'Setup Font Color', 'bookaroom_reservedColor' => 'Reserved Background Color', 'bookaroom_reservedFont' => 'Reserved Font Color');
		foreach( $checkArray as $key => $val ):
			if( !is_null( $externals[$key] ) and !preg_match('/^#[a-f0-9]{6}$/i', $externals[$key] ) and !preg_match('/^#[a-f0-9]{3}$/i', $externals[$key] ) ):
				$error[] = "Please enter a valid HEX value for the <strong>{$val}</strong>";
			endif;
		endforeach;
		
		# numbers
		$checkArray = array( 'bookaroom_baseIncrement' => 'Base Increment', 'bookaroom_setupIncrement' => 'Setup Increment', 'bookaroom_cleanupIncrement' => 'Cleanup Increment', 'bookaroom_reserveBuffer' => 'Days Buffer for Reserve', 'bookaroom_reserveAllowed' => 'Days Allowed for Reserve', 
'bookaroom_profitDeposit' => 'For Profit Room Deposit', 'bookaroom_nonProfitDeposit' => 'Non-profit Room Deposit', 'bookaroom_profitIncrementPrice' => 'For Profit price per increment', 'bookaroom_nonProfitIncrementPrice' => 'Non-profit price per increment', 'bookaroom_waitingListDefault' => 'Waiting list default', 'bookaroom_screenWidth' => 'Screen width', '	Credit card payment link' => 'bookaroom_paymentLink' );
		foreach( $checkArray as $key => $val ):
			if( !empty( $externals[$key] ) and !is_numeric( $externals[$key] ) ):
				$error[] = "Please enter a valid number for the <strong>{$val}</strong>";
			endif;
		endforeach;
		
		if( !empty( $externals['bookaroom_defaultEmailDaily'] ) && !filter_var( $externals['bookaroom_defaultEmailDaily'], FILTER_VALIDATE_EMAIL ) ):
			$error[] = "Please enter a valid email address for the <strong>Default Email for Daily Reservations</strong> field.";
		endif;
		
		if( count( $error ) > 0 ):
			$final = implode( '<br /><br />', $error );
		endif;
		
		return $final;	
	}
	
	public static function showMeetingRoomUpdateSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/mainSettings/updateSuccess.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		echo $contents;
	}
	
		
	public static function showMeetingRoomAdmin( $options, $error=NULL )
	{
		$filename = BOOKAROOM_PATH . 'templates/mainSettings/adminMain.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# errors
		$error_line = repCon( 'error', $contents );
		if( empty( $error ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMsg#', $error, $contents );
		
		endif;
		$contents = str_replace( '#bookaroom_baseIncrement#', $options['bookaroom_baseIncrement'], $contents );
		$contents = str_replace( '#bookaroom_setupIncrement#', $options['bookaroom_setupIncrement'], $contents );
		$contents = str_replace( '#bookaroom_cleanupIncrement#', $options['bookaroom_cleanupIncrement'], $contents );
		$contents = str_replace( '#bookaroom_daysBeforeRemind#', $options['bookaroom_daysBeforeRemind'], $contents );
		$contents = str_replace( '#bookaroom_reservation_URL#', $options['bookaroom_reservation_URL'], $contents );
		$contents = str_replace( '#bookaroom_setupColor#', $options['bookaroom_setupColor'], $contents );
		$contents = str_replace( '#bookaroom_setupFont#', $options['bookaroom_setupFont'], $contents );
		$contents = str_replace( '#bookaroom_reservedColor#', $options['bookaroom_reservedColor'], $contents );
		$contents = str_replace( '#bookaroom_reservedFont#', $options['bookaroom_reservedFont'], $contents );
		$contents = str_replace( '#bookaroom_reserveBuffer#', $options['bookaroom_reserveBuffer'], $contents );
		$contents = str_replace( '#bookaroom_reserveAllowed#', $options['bookaroom_reserveAllowed'], $contents );
		$contents = str_replace( '#bookaroom_defaultEmailDaily#', $options['bookaroom_defaultEmailDaily'], $contents );
		$contents = str_replace( '#bookaroom_waitingListDefault#', $options['bookaroom_waitingListDefault'], $contents );
		
		$contents = str_replace( '#bookaroom_profitDeposit#', $options['bookaroom_profitDeposit'], $contents );
		$contents = str_replace( '#bookaroom_nonProfitDeposit#', $options['bookaroom_nonProfitDeposit'], $contents );
		$contents = str_replace( '#bookaroom_profitIncrementPrice#', $options['bookaroom_profitIncrementPrice'], $contents );
		$contents = str_replace( '#bookaroom_nonProfitIncrementPrice#', $options['bookaroom_nonProfitIncrementPrice'], $contents );
		$contents = str_replace( '#bookaroom_eventLink#', $options['bookaroom_eventLink'], $contents );
		
		$contents = str_replace( '#bookaroom_paymentLink#', $options['bookaroom_paymentLink'], $contents );
		$contents = str_replace( '#bookaroom_libcardRegex#', $options['bookaroom_libcardRegex'], $contents );

		$obfuscateChecked = empty( $options['bookaroom_obfuscatePublicNames'] ) ? NULL : ' checked="checked"';
		$contents = str_replace( '#bookaroom_obfuscatePublicNamesChecked#', $obfuscateChecked, $contents );
		
		$hideContractChecked = empty( $options['bookaroom_hide_contract'] ) ? NULL : ' checked="checked"';
		$contents = str_replace( '#bookaroom_hideContractChecked#', $hideContractChecked, $contents );
		
		
		# screen width drop down
		$screenWidth_line = repCon( 'screenWidth', $contents );
		
		$screenWidthOptions = array( 0 => 'Responsive/Wide', 1 => 'Narrow' );
		$temp = NULL;
		$final = array();
		
		foreach( $screenWidthOptions as $key => $val ):
			$selected = NULL;
			
			
			if( $options['bookaroom_screenWidth'] == $key ):
				$selected = ' selected="selected"';
			endif;
							
			$temp = $screenWidth_line;
			
			$temp = str_replace( '#screenWidth_disp#', $val, $temp );
			$temp = str_replace( '#screenWidth_val#', $key, $temp );
			$temp = str_replace( '#screenWidth_selected#', $selected, $temp );
			
			$final[] = $temp;		
		endforeach;
		
		$contents = str_replace( '#screenWidth_line#', implode( "\r\n", $final ), $contents );
		

		echo $contents;		
	}
	
	public static function getExternalsAdmin()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'				=> FILTER_SANITIZE_STRING);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final = array_merge( $final, $getTemp );
		endif;

		# setup POST variables
		$postArr = array(	'action'								=> FILTER_SANITIZE_STRING, 
							'bookaroom_baseIncrement'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_cleanupIncrement'			=> FILTER_SANITIZE_STRING, 
							'bookaroom_paymentLink'					=> FILTER_SANITIZE_STRING, 
							'bookaroom_libcardRegex'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_obfuscatePublicNames'		=> FILTER_SANITIZE_STRING, 
							'bookaroom_daysBeforeRemind'			=> FILTER_SANITIZE_STRING, 
							'bookaroom_defaultEmailDaily'			=> FILTER_SANITIZE_STRING, 
							'bookaroom_nonProfitDeposit'			=> FILTER_SANITIZE_STRING, 
							'bookaroom_nonProfitIncrementPrice'		=> FILTER_SANITIZE_STRING, 
							'bookaroom_profitDeposit'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_profitIncrementPrice'		=> FILTER_SANITIZE_STRING, 
							'bookaroom_reservation_URL'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_reserveAllowed'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_reserveBuffer'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_reservedColor'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_reservedFont'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_screenWidth'					=> FILTER_SANITIZE_STRING, 
							'bookaroom_setupColor'					=> FILTER_SANITIZE_STRING, 
							'bookaroom_setupFont'					=> FILTER_SANITIZE_STRING, 
							'bookaroom_setupIncrement'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_waitingListDefault'			=> FILTER_SANITIZE_STRING,
							'bookaroom_eventLink'					=> FILTER_SANITIZE_STRING,
						 	'bookaroom_hide_contract'				=> FILTER_SANITIZE_STRING,
						 
							);

			
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) ):
			$final = array_merge( $final, $postTemp );
		endif; 

		$arrayCheck = array_unique( array_merge( array_keys( $getArr ), array_keys( $postArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			else:
				$final[$key] = trim( $final[$key] );
			endif;
		endforeach;
		
		return $final;
	}
}
?>