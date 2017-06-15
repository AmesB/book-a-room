<?PHP
class bookaroom_settings_email 
{
	public static function bookaroom_admin_email()
	{
		# first, is there an action?
		$externals = self::getExternalsAdmin();

		switch( $externals['action'] ):	
			case 'testEmail':
				if( ( $errors = self::checkEmailErrors( $externals ) ) !== FALSE ):
					self::showEmailAdmin( $externals, $errors );
					break;
				else:
					self::updateEmail( $externals );
					self::testEmail( $externals['bookaroom_alertEmail'] );
				endif;
				break;
			case 'updateAlertEmail':
				# check for valid email
				if( ( $errors = self::checkEmailErrors( $externals ) ) !== FALSE ):
					self::showEmailAdmin( $externals, $errors );
					break;
				else:
					self::updateEmail( $externals );
					self::showEmailAdminSuccess();
					break;
				endif;			
			default:
				$externals = self::getDefaults();
				self::showEmailAdmin( $externals );
				break;
				
		endswitch;
	}


	protected static function updateEmail( $externals )
	{
		$final = array();
		
		
		$emailAddressArr = explode( ';', $externals['bookaroom_alertEmail'] );
		
		$temp = array();
		foreach( $emailAddressArr as $key => $val ):
			$temp[] = trim( $val );
		endforeach;
		
		$final = implode( ';', $temp);

		update_option( 'bookaroom_alertEmail', 							strtolower( $externals['bookaroom_alertEmail'] ) );
		update_option( 'bookaroom_alertEmailFromName', 					$externals['bookaroom_alertEmailFromName'] );
		update_option( 'bookaroom_alertEmailFromEmail', 				$externals['bookaroom_alertEmailFromEmail'] );
		update_option( 'bookaroom_emailReplyToOnly', 					$externals['bookaroom_emailReplyToOnly'] );
				
		update_option( 'bookaroom_regChange_subject', 					$externals['bookaroom_regChange_subject'] );
		update_option( 'bookaroom_newAlert_subject', 					$externals['bookaroom_newAlert_subject'] );
		update_option( 'bookaroom_requestAcceptedProfit_subject', 		$externals['bookaroom_requestAcceptedProfit_subject'] );
		update_option( 'bookaroom_requestAcceptedNonprofit_subject', 	$externals['bookaroom_requestAcceptedNonprofit_subject'] );		
		update_option( 'bookaroom_requestDenied_subject', 				$externals['bookaroom_requestDenied_subject'] );
		update_option( 'bookaroom_requestReminder_subject', 			$externals['bookaroom_requestReminder_subject'] );
		update_option( 'bookaroom_requestPayment_subject', 				$externals['bookaroom_requestPayment_subject'] );
		update_option( 'bookaroom_newInternal_subject', 				$externals['bookaroom_newInternal_subject'] );
		update_option( 'bookaroom_nonProfit_pending_subject',			$externals['bookaroom_nonProfit_pending_subject'] );
		update_option( 'bookaroom_profit_pending_subject',				$externals['bookaroom_profit_pending_subject'] );
		
		
		update_option( 'bookaroom_nonProfit_pending_body', 				htmlentities( $externals['bookaroom_nonProfit_pending_body'] ) );
		update_option( 'bookaroom_profit_pending_body', 				htmlentities( $externals['bookaroom_profit_pending_body'] ) );
		update_option( 'bookaroom_regChange_body', 						htmlentities( $externals['bookaroom_regChange_body'] ) );
		update_option( 'bookaroom_newAlert_body', 						htmlentities( $externals['bookaroom_newAlert_body'] ) );
		update_option( 'bookaroom_requestAcceptedProfit_body', 			$externals['bookaroom_requestAcceptedProfit_body'] );
		update_option( 'bookaroom_requestAcceptedNonprofit_body', 		$externals['bookaroom_requestAcceptedNonprofit_body'] );
		update_option( 'bookaroom_requestDenied_body', 					$externals['bookaroom_requestDenied_body'] );
		update_option( 'bookaroom_requestReminder_body', 				$externals['bookaroom_requestReminder_body'] );
		update_option( 'bookaroom_requestPayment_body', 				$externals['bookaroom_requestPayment_body'] );
		update_option( 'bookaroom_newInternal_body', 					$externals['bookaroom_newInternal_body'] );
		
		
		return TRUE;
	}
	
	protected static function checkEmailErrors( &$externals )
	{
		$count = 0;
		$errorMSG = array();
		$errorCount = 0;
		
		if( empty( $externals['bookaroom_alertEmail'] ) ):
			$temp[] = "You must add an <strong>Email alerts</strong> address.";
		endif;
		
		$emailAddressArr = explode( ',', $externals['bookaroom_alertEmail'] );
		
		$temp = array();
		
		foreach( $emailAddressArr as $key => $valRaw ):
			$val = trim( $valRaw );
			if( !empty( $val ) && !filter_var( $val, FILTER_VALIDATE_EMAIL ) ):
				$temp[] = "The email address <strong>[{$val}]</strong> is not valid.";
				$errorCount++;
			endif;
		endforeach;

		if( empty( $externals['bookaroom_alertEmailFromName'] ) ):
			$temp[] = "You must add a <strong>from name</strong>.";
		endif;
		
		if( empty( $externals['bookaroom_alertEmailFromEmail'] ) ):
			$temp[] = "You must add a <strong>from email address</strong>.";
		elseif( !filter_var( $externals['bookaroom_alertEmailFromEmail'], FILTER_VALIDATE_EMAIL ) ):
			$temp[] = "You must add a valid <strong>from email address</strong>.";
		endif;		
				
		if( count( $temp ) !== 0 ):
			$errorMSG['bookaroom_alertEmail'] =  implode( '<br /><br />', $temp );
		endif;
		
		$goodArr = self::makeFormList();
		
		
		foreach( $goodArr as $key => $val ):
			$final = array();
			if( empty( $externals[$val['wordpressSettingName'].'_subject'] ) ):
				$final[] = "You must enter an email subject for <em>{$key}</em>.";
				$errorCount++;
			endif;
			if( empty( $externals[$val['wordpressSettingName'].'_body'] ) ):
				$final[] = "You must enter an email body for <em>{$key}</em>.";
				$errorCount++;
			endif;
			if( count( $final ) !== 0 ):
				$errorMSG[$val['wordpressSettingName']] = implode( '<br /><br />', $final );
				
			endif;
		endforeach;
		
		if( $errorCount == 0 ):
			return FALSE;
		else:
			$errorMSG['count'] = $errorCount;
			return $errorMSG;
		endif;
	}
	
	protected static function getDefaults()
	{
		$option = array();
		$option['bookaroom_alertEmail']							= get_option( 'bookaroom_alertEmail' );	
		$option['bookaroom_alertEmailFromName']					= get_option( 'bookaroom_alertEmailFromName' );	
		$option['bookaroom_alertEmailFromEmail']				= get_option( 'bookaroom_alertEmailFromEmail' );			
		$option['bookaroom_emailReplyToOnly']					= get_option( 'bookaroom_emailReplyToOnly' );			
				
		$option['bookaroom_regChange_subject']					= get_option( 'bookaroom_regChange_subject' );
		$option['bookaroom_newAlert_subject']					= get_option( 'bookaroom_newAlert_subject' );
		$option['bookaroom_requestAcceptedProfit_subject']		= get_option( 'bookaroom_requestAcceptedProfit_subject' );
		$option['bookaroom_requestAcceptedNonprofit_subject']	= get_option( 'bookaroom_requestAcceptedNonprofit_subject' );
		$option['bookaroom_requestDenied_subject']				= get_option( 'bookaroom_requestDenied_subject' );
		$option['bookaroom_requestReminder_subject']			= get_option( 'bookaroom_requestReminder_subject' );
		$option['bookaroom_requestPayment_subject']				= get_option( 'bookaroom_requestPayment_subject' );
		$option['bookaroom_newInternal_subject']				= get_option( 'bookaroom_newInternal_subject' );
		$option['bookaroom_nonProfit_pending_subject']			= get_option( 'bookaroom_nonProfit_pending_subject' );
		$option['bookaroom_profit_pending_subject']				= get_option( 'bookaroom_profit_pending_subject' );
		
				
		$option['bookaroom_nonProfit_pending_body']				= html_entity_decode( get_option( 'bookaroom_nonProfit_pending_body' ) );
		$option['bookaroom_profit_pending_body']				= html_entity_decode( get_option( 'bookaroom_profit_pending_body' ) );		
		$option['bookaroom_regChange_body']						= html_entity_decode( get_option( 'bookaroom_regChange_body' ) );
		$option['bookaroom_newAlert_body']						= html_entity_decode( get_option( 'bookaroom_newAlert_body' ) );
		$option['bookaroom_requestAcceptedProfit_body']			= html_entity_decode( get_option( 'bookaroom_requestAcceptedProfit_body' ) );
		$option['bookaroom_requestAcceptedNonprofit_body']		= html_entity_decode( get_option( 'bookaroom_requestAcceptedNonprofit_body' ) );
		$option['bookaroom_requestDenied_body']					= html_entity_decode( get_option( 'bookaroom_requestDenied_body' ) );
		$option['bookaroom_requestReminder_body']				= html_entity_decode( get_option( 'bookaroom_requestReminder_body' ) );
		$option['bookaroom_requestPayment_body']				= html_entity_decode( get_option( 'bookaroom_requestPayment_body' ) );
		$option['bookaroom_newInternal_body']					= html_entity_decode( get_option( 'bookaroom_newInternal_body' ) );

		
		$option['bookaroom_profitDeposit']						= get_option( 'bookaroom_profitDeposit' );
		$option['bookaroom_nonProfitDeposit']					= get_option( 'bookaroom_nonProfitDeposit' );
		$option['bookaroom_profitIncrementPrice']				= get_option( 'bookaroom_profitIncrementPrice' );
		$option['bookaroom_nonProfitIncrementPrice']			= get_option( 'bookaroom_nonProfitIncrementPrice' );
		return $option;
	}
	
	public static function getExternalsAdmin()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'										=> FILTER_SANITIZE_STRING,
																			);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final = array_merge( $final, $getTemp );
		endif;

		# setup POST variables
		$postArr = array(	'action'										=> FILTER_SANITIZE_STRING,
							'bookaroom_alertEmail'							=> FILTER_SANITIZE_STRING,
							'bookaroom_alertEmailFromName'					=> FILTER_SANITIZE_STRING,
							'bookaroom_alertEmailFromEmail'					=> FILTER_SANITIZE_STRING,
							'bookaroom_emailReplyToOnly'					=> FILTER_SANITIZE_STRING,
							'bookaroom_newAlert_subject'					=> FILTER_SANITIZE_STRING,
							'bookaroom_requestAcceptedProfit_subject'		=> FILTER_SANITIZE_STRING, 
							'bookaroom_requestAcceptedNonprofit_subject'	=> FILTER_SANITIZE_STRING, 							
							'bookaroom_requestDenied_subject'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_requestReminder_subject'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_requestPayment_subject'				=> FILTER_SANITIZE_STRING, 
							'bookaroom_newInternal_subject'					=> FILTER_SANITIZE_STRING, 
							'bookaroom_regChange_subject'					=> FILTER_SANITIZE_STRING, 
							'bookaroom_nonProfit_pending_subject'			=> FILTER_SANITIZE_STRING, 
							'bookaroom_profit_pending_subject'				=> FILTER_SANITIZE_STRING, 							
														
							'bookaroom_nonProfit_pending_body'				=> FILTER_UNSAFE_RAW, 
							'bookaroom_profit_pending_body'					=> FILTER_UNSAFE_RAW, 
							'bookaroom_newAlert_body'						=> FILTER_UNSAFE_RAW, 							
							'bookaroom_requestAcceptedProfit_body'			=> FILTER_UNSAFE_RAW, 
							'bookaroom_requestAcceptedNonprofit_body'		=> FILTER_UNSAFE_RAW, 
							'bookaroom_requestDenied_body'					=> FILTER_UNSAFE_RAW, 
							'bookaroom_requestReminder_body'				=> FILTER_UNSAFE_RAW, 
							'bookaroom_requestPayment_body'					=> FILTER_UNSAFE_RAW, 
							'bookaroom_newInternal_body'					=> FILTER_UNSAFE_RAW, 
							'bookaroom_regChange_body'						=> FILTER_UNSAFE_RAW, 
							
							'submit'										=> FILTER_SANITIZE_STRING,
							'testEmail'										=> FILTER_SANITIZE_STRING);


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
		
		# fix action
		if( !empty( $final['testEmail'] ) ):
			$final['action'] = 'testEmail';
		endif;
		
		return $final;
	}
	
	protected static function showEmailAdmin( $externals, $errorMSG = NULL )
	{
		$filename = BOOKAROOM_PATH . 'templates/email/mainAdmin.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		##############################################################
		#
		# Error Counts
		#
		##############################################################
		$errorCount_line = repCon( 'errorCount', $contents );
		
		if( !empty( $errorMSG['count'] ) ):
			$contents  = str_replace('#errorCount_line#', $errorCount_line, $contents );
			$contents = str_replace('#errorCount#', $errorMSG['count'], $contents );
		else:
			$contents = str_replace('#errorCount_line#', NULL, $contents );
		endif;
		##############################################################
		#
		# EMAIL ADDRESSES
		#
		##############################################################
		#
		# errors
		#
		
		$error_settings_line = repCon( 'error_settings', $contents );
		
		if( empty( $errorMSG['bookaroom_alertEmail'] ) ):
			$contents = str_replace( '#error_settings_line#', NULL, $contents );			
		else:
			$contents = str_replace( '#error_settings_line#', $error_settings_line, $contents );
			$contents = str_replace( '#error_settings_MSG#', $errorMSG['bookaroom_alertEmail'], $contents );
		endif;
		
		$contents = str_replace( '#bookaroom_alertEmail#', $externals['bookaroom_alertEmail'], $contents );
		$contents = str_replace( '#bookaroom_alertEmailFromName#', $externals['bookaroom_alertEmailFromName'], $contents );
		$contents = str_replace( '#bookaroom_alertEmailFromEmail#', $externals['bookaroom_alertEmailFromEmail'], $contents );
		$contents = str_replace( '#bookaroom_emailReplyToOnly#', $externals['bookaroom_emailReplyToOnly'], $contents );
		

		##############################################################
		#
		# EMAIL SETTINGS
		#
		##############################################################
		#
		# errors
		#
		
		$goodArr = self::makeFormList();
				
		# get 'template' for form
		$emailAlerts_line = repCon( 'emailAlerts', $contents );

		# setup template
		$temp = NULL;
		$final = array();


		foreach( $goodArr as $key => $val ):
			$temp = $emailAlerts_line;
			
			# get error line
			$curError_line = repCon( 'emailAlerts_error', $temp );
			if( empty( $errorMSG[$val['wordpressSettingName']] ) ):
				$temp = str_replace( '#emailAlerts_error_line#', NULL, $temp );
			else:
				$temp = str_replace( '#emailAlerts_error_line#', $curError_line, $temp );
				$temp = str_replace( '#emailAlerts_MSG#', $errorMSG[$val['wordpressSettingName']], $temp );
			endif;
			
			# content replacement
			#$temp = str_replace( '#helpHeight#', $val['helpHeight'], $temp );
			$temp = str_replace( '#header#', $key, $temp );
			$temp = str_replace( '#emailAlerts_subjectVal#', $externals[$val['wordpressSettingName'].'_subject'], $temp );
			$temp = str_replace( '#emailAlerts_bodyVal#', $externals[$val['wordpressSettingName'].'_body'], $temp );

			$temp = str_replace( '#emailAlerts_subjectName#', $val['wordpressSettingName'].'_subject', $temp );
			$temp = str_replace( '#emailAlerts_bodyName#', $val['wordpressSettingName'].'_body', $temp );


			$temp = str_replace( '#wordpressSettingName#', $val['wordpressSettingName'], $temp );
			
			
			$final[] = $temp;		
		endforeach;
		
		$contents = str_replace( '#emailAlerts_line#', implode( "\r\n", $final ), $contents );
		
		# from check box
		if( !empty( $externals['bookaroom_emailReplyToOnly'] ) ):
			$bookaroom_emailReplyToOnly_checked = ' checked="checked"';
		else:
			$bookaroom_emailReplyToOnly_checked = NULL;
		endif;
		
		$contents = str_replace( "#bookaroom_emailReplyToOnly_checked#", $bookaroom_emailReplyToOnly_checked, $contents );
		
		echo $contents;
	}

	protected static function showEmailAdminSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/email/addSuccess.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
	
	protected static function testEmail( $bookaroom_alertEmail )
	{
		$filename = BOOKAROOM_PATH . 'templates/email/test.html';	
		$handle = fopen( $filename, "r" );
		$mailContents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $mailContents );
		
		$mailContents = str_replace( '#bookaroom_alertEmail#', $bookaroom_alertEmail, $mailContents );
		
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
					'From: Test_email' . "\r\n" .
				    'Reply-To: <>' . "\r\n" .
				    'X-Mailer: PHP/' . phpversion();

		mail( $bookaroom_alertEmail, 'Book a Room Email Test', $mailContents, $headers );
		
		$filename = BOOKAROOM_PATH . 'templates/email/testSuccess.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#bookaroom_alertEmail#', $bookaroom_alertEmail, $contents );
		
		echo $contents;


	}
	
	protected static function showEmailSettingsAdminSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/email/settingsSuccess.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
	
	protected static function makeFormList()
	{
		$goodArr = array();

		$goodArr['New Request Alert Settings'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_newAlert');
			
		$goodArr['New Staff Request Receipt'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_newInternal');
			
		#$goodArr['Payment Received Settings'] = array( 'wordpressSettingName'	=> 'bookaroom_requestPayment');
			
		$goodArr['Registration Change (From Waiting List)'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_regChange');
			
		$goodArr['Request Accepted (Nonprofit) Settings'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_requestAcceptedNonprofit');
			
		$goodArr['Request Accepted (Profit) Settings'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_requestAcceptedProfit');
			
		$goodArr['Request Denied Settings'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_requestDenied');
			
		$goodArr['Waiting on Nonprofit Paperwork'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_nonProfit_pending');
			
		$goodArr['Waiting on Payment'] = 
			array( 'wordpressSettingName'	=> 'bookaroom_profit_pending');
			
		#$goodArr['Request Reminder Settings'] = array( 'wordpressSettingName'	=> 'bookaroom_requestReminder');
		
		return $goodArr;		
	}
}
?>