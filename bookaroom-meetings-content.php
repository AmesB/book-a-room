<?PHP
class bookaroom_settings_content {
	public static function bookaroom_admin_content()
	{
		# first, is there an action?
		$externals = self::getExternalsAdmin();

		switch( $externals['action'] ):	
			case 'updateContent':
				# check for valid content
				if( ( $errors = self::checkContentContents( $externals ) ) !== FALSE ):
					self::showContentAdmin( $externals, $errors );
					break;
				else:
					self::updateContent( $externals );
					self::showContentAdminSuccess();
					break;
				endif;			
			default:
				$externals = self::getDefaults();
				self::showContentAdmin( $externals );
				break;
				
		endswitch;
	}


	protected static function checkContentContents( &$externals )
	{
		$count = 0;
		$errorMSG = array();
		$errorCount = 0;
		
		$temp = array();

		$goodArr = self::makeFormList();				
		
		foreach( $goodArr as $key => $val ):
			$final = array();

			if( empty( $externals[$val['wordpressSettingName']] ) ):
				$final[] = "You must enter a value for <em>{$key}</em>.";
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
		$option['bookaroom_content_contract']			= get_option( 'bookaroom_content_contract' );

		return $option;
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
		$postArr = array(	'action'												=> FILTER_SANITIZE_STRING,
							'bookaroom_content_contract'					=> FILTER_UNSAFE_RAW,
							'submit'												=> FILTER_SANITIZE_STRING);


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
	
	protected static function showContentAdmin( $externals, $errorMSG = NULL )
	{
		$filename = BOOKAROOM_PATH . 'templates/content/mainAdmin.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		##############################################################
		#
		# Content Counts
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
		# CONTENT ADDRESSES
		#
		##############################################################
		#
		# errors
		#
		
		$error_settings_line = repCon( 'error_settings', $contents );
		
		if( empty( $errorMSG['bookaroom_alertContent'] ) ):
			$contents = str_replace( '#error_settings_line#', NULL, $contents );			
		else:
			$contents = str_replace( '#error_settings_line#', $error_settings_line, $contents );
			$contents = str_replace( '#error_settings_MSG#', $errorMSG['bookaroom_alertContent'], $contents );
		endif;
		
		##############################################################
		#
		# CONTENT SETTINGS
		#
		##############################################################
		#
		# errors
		#
		
		$goodArr = self::makeFormList();
				
		# get 'template' for form
		$contentAlerts_line = repCon( 'contentAlerts', $contents );

		# setup template
		$temp = NULL;
		$final = array();

		
		foreach( $goodArr as $key => $val ):
			$temp = $contentAlerts_line;
			
			# get error line
			$curContent_line = repCon( 'contentAlerts_error', $temp );
			if( empty( $errorMSG[$val['wordpressSettingName']] ) ):
				$temp = str_replace( '#contentAlerts_error_line#', NULL, $temp );
			else:
				$temp = str_replace( '#contentAlerts_error_line#', $curContent_line, $temp );
				$temp = str_replace( '#contentAlerts_MSG#', $errorMSG[$val['wordpressSettingName']], $temp );
			endif;
			
			# content replacement
			$temp = str_replace( '#header#', $key, $temp );
			$temp = str_replace( '#contentAlert_val#', $externals[$val['wordpressSettingName']], $temp );

			$temp = str_replace( '#wordpressSettingName#', $val['wordpressSettingName'], $temp );
			
			$final[] = $temp;		
		endforeach;
		
		$contents = str_replace( '#contentAlerts_line#', implode( "\r\n", $final ), $contents );
		
		echo $contents;
	}

	protected static function showContentAdminSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/content/addSuccess.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
	
	protected static function showContentSettingsAdminSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/content/settingsSuccess.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
	
	protected static function makeFormList()
	{
		$goodArr = array();

		$goodArr['Meeting Room Contract'] = array(	
					'wordpressSettingName'	=> 'bookaroom_content_contract');
					
		return $goodArr;		
	}
	
	protected static function updateContent( $externals )
	{
		$final = array();
		
		update_option( 'bookaroom_content_contract', 			$externals['bookaroom_content_contract'] );
		
		return TRUE;
	}
}
?>