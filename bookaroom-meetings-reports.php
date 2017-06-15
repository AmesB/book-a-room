<?PHP
class bookaroom_reports
{
	public static function bookaroom_reportsAdmin()
	{
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-closings.php' );
		#require_once( BOOKAROOM_PATH . 'bookaroom-events-settings.php' );
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		$changeRoom = false;
		# first, is there an action? 
		$externals = self::getExternals();
		
		switch( $externals['action'] ):	
			default:
				self::showMainIndex();
				break;
				
		endswitch;
	}
	
	public static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();

		# setup GET variables
		$getArr = array(	'action'					=> FILTER_SANITIZE_STRING );

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = array_merge( $final, $getTemp );

		# setup POST variables
		$postArr = array(	'action'					=> FILTER_SANITIZE_STRING,
							'res_id'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ) );
	
	

		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) )
			$final = array_merge( $final, $postTemp );

		$arrayCheck = array_unique( array_merge( array_keys( $getArr ), array_keys( $postArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			elseif( is_array( $final[$key] ) ):
				$final[$key] = array_keys( $final[$key] );
			else:			
				$final[$key] = trim( $final[$key] );
			endif;
		endforeach;

		
		return $final;
	}
	
	protected static function showMainIndex()
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/reports/mainIndex.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		echo $contents;
	}
	
	
	
}
?>