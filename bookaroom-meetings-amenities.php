<?PHP
class bookaroom_settings_amenities
{
	############################################
	#
	# Amenity managment
	#
	############################################
	public static function bookaroom_admin_amenities()
	{
		
		#$amenityList = self::getAmenityList();
		$amenityList = self::getAmenityList( false, true );

		# figure out what to do
		# first, is there an action?
		$externals = self::getExternalsAmenities();
		
		switch( $externals['action'] ):
			case 'deleteCheck':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['amenityID'], $amenityList['id'] ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'amenities/IDerror' );
				else:
					# show delete screen
					$roomList		= bookaroom_settings_rooms::getRoomList();
					self::deleteAmenity( $amenityList['id'][$externals['amenityID']], $roomList );
					bookaroom_settings::showMessage( 'amenities/delete_success' );
				endif;
				
				break;
			
			case 'delete':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['amenityID'], $amenityList['id'] ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'amenities/IDerror' );
				else:
					# show delete screen
					$amenityInfo = self::getAmenityInfo( $externals['amenityID'] );
					self::showAmenityDelete(  $amenityList['id'][$externals['amenityID']] );
				endif;
				
				break;				
				
			case 'editCheck':
				# check entries
				if( ( $errors = self::checkEditAmenities( $externals, $amenityList ) ) == NULL ):
					self::editAmenity( $externals );
					bookaroom_settings::showMessage( 'amenities/editSuccess' );

					break;	
				endif;
				
				$externals['errors'] = $errors;
				
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['amenityID'], $amenityList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'amenities/IDerror' );
				else:
					# show edit screen
					self::showAmenityEdit( $externals, 'editCheck', 'Edit' );
				endif;
				
				break;
			
			case 'edit':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['amenityID'], $amenityList['id'] ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'amenities/IDerror' );
				else:
					# show edit screen
					$amenityInfo = self::getAmenityInfo( $externals['amenityID'] );
					self::showAmenityEdit( $amenityList['id'][$externals['amenityID']], 'editCheck', 'Edit' );
				endif;
				
				break;
							
			case 'addCheck':
				# check entries
				if( ( $errors = self::checkEditAmenities( $externals, $amenityList ) ) == NULL ):
					self::addAmenity( $externals );
					bookaroom_settings::showMessage( 'amenities/add_success' );

					break;	
				endif;
				
				$externals['errors'] = $errors;
				# show edit screen
				self::showAmenityEdit( $externals, 'addCheck', 'Add' );
				break;
				
			case 'add':
				self::showAmenityEdit( $externals, 'addCheck', 'Add' );
				break;
			
			default:
				self::showAmenityList( $amenityList );
				break;
				
		endswitch;
	}
	
	# sub functions:
	
	public static function getAmenityInfo( $amenityID )
	# get information about branch from daabase based on the ID
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_amenities";
		
		$final = $wpdb->get_row( $wpdb->prepare( "SELECT amenityDesc FROM `$table_name` WHERE `amenityID` = %d", $amenityID ) );

		$amenityInfo = array( 'amenityDesc' => $final->amenityDesc, 'amenityID' => $amenityID );
	
		return $amenityInfo;
	}
	
	public static function deleteAmenity( $amenityInfo, $roomList )
	# add a new branch
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_amenities";
		
		$sql = "DELETE FROM `{$table_name}` WHERE `amenityID` = '{$amenityInfo['amenityID']}' LIMIT 1";

		$wpdb->query( $sql );
		
		# Search rooms for that room, remove
		$table_name = $wpdb->prefix . "bookaroom_rooms";
		
		$amenityID = $amenityInfo['amenityID'];
		
		# if no rooms, make array so no error
		if( empty( $roomList['id'] ) ):
			$roomList['id'] = array();
		endif;
		
		foreach( $roomList['id'] as $key => $val):
			if( !empty( $val['amenity'] ) and is_array( $val['amenity'] ) and in_array( $amenityID, $val['amenity'] ) ):
				$finalAmenities = serialize( array_diff( $val['amenity'], array( $amenityID ) ) );
				$wpdb->update( $table_name, 
					array( 'room_amenityArr'		=> $finalAmenities),  
					array( 'roomID'			=>  $key ) );
			endif;
		endforeach;

	}
	
	public static function showAmenityDelete( $amenityInfo )
	# show delete page and fill with values
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/amenities/delete.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#amenityDesc#', $amenityInfo['amenityDesc'], $contents );
		$contents = str_replace( '#amenityID#', $amenityInfo['amenityID'], $contents );
		$isReservable = ( $amenityInfo['amenity_isReservable'] ) ? 'Yes' : 'No';
		$contents = str_replace( '#isReservable#', $isReservable, $contents );
		
		echo $contents;
	}
	
	public static function showAmenityList( $amenityList )
	# show a list of all amenities (if any) with edit and delete options. Also
	# show add new option.
	{
		$filename = BOOKAROOM_PATH . 'templates/amenities/mainAdmin.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# create some and none lines and check for current amenities
		$some_line = repCon( 'some', $contents );
		$none_line = repCon( 'none', $contents, TRUE );
		$line_line = repCon( 'line', $some_line );
		
		if( count( $amenityList ) == 0 ):
		# if none, replace with none line
			$contents = str_replace( '#some_line#', $none_line, $contents );
		else:
		# if some, replace with some line and loop to display each one
			$contents = str_replace( '#some_line#', $some_line, $contents );

			$temp = NULL;
			$final = array();
			$count = 0;

			foreach( $amenityList['id'] as $key => $val ):
				$temp = $line_line;
				
				$bg_class = ($count++%2) ? 'odd_row' : 'even_row';
				$temp = str_replace( '#bg_class#', $bg_class, $temp );
				
				$temp = str_replace( '#amenityID#', $key, $temp );
				$temp = str_replace( '#amenityDesc#', $val['amenityDesc'], $temp );
				$isReservable = ( $val['amenity_isReservable'] ) ? 'Yes' : 'No';
				$temp = str_replace( '#isReservable#', $isReservable, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#line_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		echo $contents;
	}
	
	public static function editAmenity( $externals )
	# change the branch settings
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_amenities";
		
		$isReservable = ( $externals['amenity_isReservable'] ) ? 1 : 0;
		
		$final = $wpdb->update( $table_name, 
			array( 	'amenityDesc' => $externals['amenityDesc'], 
					'amenity_isReservable' => $isReservable, 
			 ), 			
			array( 'amenityID' =>  $externals['amenityID'] ) );

	}
	
	public static function showAmenityEdit( $amenityInfo, $action, $actionName )
	# show edit page and fill with values
	{
		$filename = BOOKAROOM_PATH . 'templates/amenities/edit.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# show errors if any
		$error_line = repCon( 'error', $contents );
		
		if( empty( $amenityInfo['errors'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMsg#', $amenityInfo['errors'], $contents);
		endif;
		
		# fill in values
		$contents = str_replace( '#action#', $action, $contents );
		$contents = str_replace( '#actionName#', $actionName, $contents);
		$contents = str_replace( '#amenityDesc#', $amenityInfo['amenityDesc'], $contents );
		$contents = str_replace( '#amenityID#', $amenityInfo['amenityID'], $contents );
		
		$isReservable = ( $amenityInfo['amenity_isReservable'] ) ? ' checked="checked"' : NULL;
		$contents = str_replace( '#amenity_isReservable_checked#', $isReservable, $contents );
		
		echo $contents;	
	}
	
	public static function checkEditAmenities( &$externals, $amenityList )
	# check the name for duplicates or empty
	{
		$final = NULL;
		$error = array();
		
		# check for empty amenity name
		if( empty( $externals['amenityDesc'] ) ):
			$error[] = 'You must enter an amenity name.';	
		endif;
		
		# check dupe name
		if( bookaroom_settings::dupeCheck( $amenityList['desc'], $externals['amenityDesc'], $externals['amenityID'] ) == 1 ):
			$error[] = 'That amenity name is already in use. Please choose another.';	
		endif;
		
		# if errors, implode and return error messages

		if( count( $error ) !== 0 ):
			$final = implode( "<br />", $error );
		endif;
		
		return $final;

	}
	
	public static function getAmenityList( $onlyReservable = false, $returnReservableBit = false )
	# get a list of all of the available amenities. 
	# Return NULL on no amenities
	# otherwise, return an array with the unique ID of each amenity
	# as the key and the description as the val
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "bookaroom_amenities";
		
		if( $onlyReservable ):
			$sql = "SELECT `amenityID`, `amenityDesc`, `amenity_isReservable` FROM `$table_name` WHERE `amenity_isReservable` IS TRUE ORDER BY `amenityDesc`";
		else:
			$sql = "SELECT `amenityID`, `amenityDesc`,`amenity_isReservable` FROM `$table_name` ORDER BY `amenityDesc`";
		endif;
		
		$count = 0;
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		if( count( $cooked ) == 0 ):
			return array();
		endif;
		
		foreach( $cooked as $key => $val ):
			if( $returnReservableBit ):
				$final['id'][$val['amenityID']] = array( 'amenityID' => $val['amenityID'], 'amenityDesc' => $val['amenityDesc'], 'amenity_isReservable' => $val['amenity_isReservable'] );
				$final['desc'][$val['amenityID']] = $val['amenityDesc'];
			else:
				$final[$val['amenityID']] = $val['amenityDesc'];
			endif;
		endforeach;
		
		return $final;
 	}
	
	public static function getExternalsAmenities()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'amenityID'				=> FILTER_SANITIZE_STRING,
							'action'				=> FILTER_SANITIZE_STRING);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final += $getTemp;
		endif;

		# setup POST variables
		$postArr = array(	'action'				=> FILTER_SANITIZE_STRING,
							'amenityID'				=> FILTER_SANITIZE_STRING, 
							'amenityDesc'			=> FILTER_SANITIZE_STRING,
							'amenity_isReservable'	=> FILTER_SANITIZE_STRING );
	
	
	
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) ):
			$final += $postTemp;
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
	
	public static function addAmenity( $externals )
	# add a new branch
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_amenities";
		
		$isReservable = ( $externals['amenity_isReservable'] ) ? 1 : 0;
		
		$final = $wpdb->insert( $table_name, 
			array( 	'amenityDesc' => $externals['amenityDesc'], 
					'amenity_isReservable' => $isReservable ) );


	}
}
?>