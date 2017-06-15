<?PHP
class bookaroom_settings_rooms
{
	############################################
	#
	# Room managment
	#
	############################################
	public static function bookaroom_admin_rooms()
	{
		$roomList = self::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList();
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		# figure out what to do
		# first, is there an action?
		$externals = self::getExternalsRoom();
		$error = NULL;
		
		switch( $externals['action'] ):
			case 'deleteCheck':
				if( bookaroom_settings::checkID( $externals['roomID'], $roomList['room'], TRUE ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'rooms/IDerror' );
				else:
					# delete room
					$roomContList = bookaroom_settings_roomConts::getRoomContList();
					
					self::deleteRoom( $externals['roomID'], $roomContList );
					bookaroom_settings::showMessage( 'rooms/deleteSuccess' );
				endif;
				
				break;
			
			case 'delete':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['roomID'], $roomList['room'], TRUE ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'rooms/IDerror' );
				else:
					# show delete screen
					self::showRoomDelete( $externals['roomID'], $roomList, $branchList, $amenityList );
				endif;
				
				break;
				
			case 'editCheck':
				# check entries
				if( ( $errors = self::checkEditRoom( $externals, $branchList, $amenityList, $roomList, $externals['roomID'] ) ) == NULL ):
					self::editRoom( $externals, $amenityList );
					bookaroom_settings::showMessage( 'rooms/editSuccess' );
					break;	
				endif;
				
				$externals['errors'] = $errors;
				
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['roomID'], $roomList['room'], TRUE ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'rooms/IDerror' );
				else:
					# show edit screen
					self::showRoomEdit( $externals, $branchList, $amenityList, 'editCheck', 'Edit' );
				endif;
				
				break;
				
			case 'edit':
				# check that there is an ID and it is valid
				
				if( bookaroom_settings::checkID( $externals['roomID'], $roomList['room'], TRUE ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'rooms/IDerror' );
				else:
					# show edit screen
					$roomInfo = self::getRoomInfo( $externals['roomID'] );
					self::showRoomEdit( $roomInfo, $branchList, $amenityList, 'editCheck', 'Edit' );
				endif;
				
				break;
				
			case 'addCheck':
				if( ( $error = self::checkEditRoom( $externals, $branchList, $amenityList, $roomList, NULL ) ) == TRUE):
				
					$externals['errors'] = $error;
					self::showRoomEdit( $externals, $branchList, $amenityList, 'addCheck', 'Add' );
				else:
					self::addRoom( $externals, $amenityList );
					bookaroom_settings::showMessage( 'rooms/addSuccess' );
				endif;
				
				break;
				
			case 'add':
				self::showRoomEdit( NULL, $branchList, $amenityList, 'addCheck', 'Add' );
				break;
			
			default:
				self::showRoomList( $roomList, $branchList, $amenityList );
				break;
				
		endswitch;
	}
	
	# sub functions:
	
	public static function addRoom( $externals, $amenityList )
	# add a new branch
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_rooms";
		
		# make amenity list
		# if no amenities, then null
		if( empty( $externals['amenity'] ) ):
			$amenityArr = NULL;
		else:
		# If there are some, only use valid amenity ids and serialize
			$goodArr = array_keys( $amenityList );
			$amenityArr = serialize( array_intersect( $goodArr, $externals['amenity'] ) );
		endif;
	
		$final = $wpdb->insert( $table_name, 
			array( 	'room_desc'=> 			$externals['roomDesc'], 
					'room_amenityArr'=>		$amenityArr, 
					'room_branchID'=>		$externals['branch'] ) );
	}
	
	public static function checkEditRoom( $externals, $branchList, $amenityList, $roomList, $roomID )
	# check the room to make sure everything is filled out
	# there are no dulicate names in the same branch
	# and the amenities are valid
	{
		$error = array();
		$final = NULL;
		# check name is filled and isn't duped in the same branch
		# check for empty room name
		if( empty( $externals['roomDesc'] ) ):
			$error[] = 'You must enter a room name.';	
		endif;
		
		# fix array if none
		
		if( empty( $externals['branch'] ) ):
			$error[] = 'You must choose a branch.';	
		else:
			# check dupe name FOR THAT BRANCH - first, are there any rooms?
			if( !empty( $roomList['room'] ) and array_key_exists( $externals['branch'], $roomList['room'] ) ):
				if( bookaroom_settings::dupeCheck( $roomList['room'][$externals['branch']], $externals['roomDesc'], $externals['roomID'] ) == 1 ):
				$error[] = 'That room name is already in use at that branch. Please choose another.';	
				endif;
			endif;
		endif;
		# if errors, implode and return error messages
		if( count( $error ) !== 0 ):
			$final = implode( "<br />", $error );
		endif;
		
		return $final;
	}
	
	public static function deleteRoom( $roomID, $roomContList )
	# delete room
	{
		global $wpdb;
		# Delete actual room
		# ***
		$table_name = $wpdb->prefix . "bookaroom_rooms";
		
		$sql = "DELETE FROM `{$table_name}` WHERE `roomID` = '{$roomID}' LIMIT 1";
		$wpdb->query( $sql );
		
		# Search containers for that room, remove
		$table_name = $wpdb->prefix . "bookaroom_roomConts";
		
		$sql = "DELETE FROM `{$table_name}` WHERE `rcm_roomID` = '{$roomID}'";
		$wpdb->query( $sql );
	}
	
	public static function editRoom( $externals, $amenityList )
	# change the room settings
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_rooms";
		
		# make amenity list
		# if no amenities, then null
		if( empty( $externals['amenity'] ) ):
			$amenityArr = NULL;
		else:
		# If there are some, only use valid amenity ids and serialize
			$goodArr = array_keys( $amenityList );
			$amenityArr = serialize( array_intersect( $goodArr, $externals['amenity'] ) );
		endif;
	
		$final = $wpdb->update( $table_name, 
			array( 	'room_desc'=> 			$externals['roomDesc'], 
					'room_amenityArr'=>		$amenityArr, 
					'room_branchID'=>		$externals['branch'] ),  
			array( 'roomID' =>  $externals['roomID'] ) );
	}
	
	public static function getExternalsRoom()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'roomID'				=> FILTER_SANITIZE_STRING,
							'action'				=> FILTER_SANITIZE_STRING);
		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final += $getTemp;
		endif;
		# setup POST variables
		$postArr = array(	'action'				=> FILTER_SANITIZE_STRING,
							'roomID'				=> FILTER_SANITIZE_STRING, 
							'branch'				=> FILTER_SANITIZE_STRING, 
							'roomDesc'				=> FILTER_SANITIZE_STRING, 
							'amenity'				=> array(	'filter'    => FILTER_SANITIZE_STRING,
                           										'flags'     => FILTER_REQUIRE_ARRAY ) );
	
	
	
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) ):
			$final += $postTemp;
		endif;
		$arrayCheck = array_unique( array_merge( array_keys( $getArr ), array_keys( $postArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			endif;
		endforeach;
		
		if( !is_null( $final['amenity'] ) ):
			$final['amenity'] = array_keys( $final['amenity'] );
		endif;
		return $final;
	}
	
	public static function getRoomInfo( $roomID )
	# get information about branch from daabase based on the ID
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_rooms";
		$final = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `roomID` = %d", $roomID ) );
		$roomInfo = array( 'roomID' => $roomID, 'roomDesc' => $final->room_desc, 'amenity' => unserialize( $final->room_amenityArr ), 'branch' => $final->room_branchID );
		
		return $roomInfo;
	}
	
	public static function getRoomList()
	{
		global $wpdb;
		$roomList = array();
		
		$table_name = $wpdb->prefix . "bookaroom_rooms";
		$sql = "SELECT `roomID`, `room_desc`, `room_amenityArr`, `room_branchID` FROM `$table_name` ORDER BY `room_branchID`, `room_desc`";
		
		$count = 0;
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		if( count( $cooked ) == 0):
			return array();
		endif;
		foreach( $cooked as $key => $val):
			# check for amenities
			$amenityGood = ( empty( $val['room_amenityArr'] ) ) ? NULL : unserialize( $val['room_amenityArr'] );
			$roomList['room'][$val['room_branchID']][$val['roomID']] = $val['room_desc'];
			#$roomList['amenity'][$val['roomID']] = $amenityGood;
			$roomList['id'][$val['roomID']] = array( 'branch' => $val['room_branchID'], 'amenity' => $amenityGood, 'desc' => $val['room_desc'] );
		endforeach;
		return $roomList;	
	}
	
	public static function showRoomList( $roomList, $branchList, $amenityList )
	# show a list of rooms with edit and delete links, or, if none 
	# a message stating there are no branches
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/rooms/mainAdmin.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# setup placeholders
		$someBranch_line	= repCon( 'someBranch', $contents );
		$noneBranch_line	= repCon( 'noneBranch', $contents, TRUE );
		
		$none_line			= repCon( 'none', $contents, TRUE );
		$some_line			= repCon( 'some', $contents );
		$branch_line		= repCon( 'branch', $some_line );
		$line_line			= repCon( 'line', $branch_line );
		$no_line_line		= repCon( 'no_line', $branch_line, TRUE );
		
		# first, replace the 'Create a new room' if there are no branches
		if( is_null( $branchList ) or !is_array( $branchList ) or count( $branchList ) == 0 ):
			# if null, then replace with the 'no branches' line
			$contents = str_replace( '#someBranch_line#', $noneBranch_line, $contents );
		else:
			# if there are branches, put back placeholder
			$contents = str_replace( '#someBranch_line#', $someBranch_line, $contents );
		endif;
		
		######################################
		
		# show edit/delete list if there are rooms, else show no room message		
		if( is_null( $roomList ) or !is_array( $roomList ) or count( $roomList ) == 0 ):
			# if null, then replace with the 'no rooms' line
			$contents = str_replace( '#some_line#', $none_line, $contents );
		else:
			# if there are branches, put back placeholder
			$contents = str_replace( '#some_line#', $some_line, $contents );
			
			# parse through the branches and create the list
			$b_final = array();
			$b_temp = NULL;
			foreach( $branchList as $b_key => $b_val):
				$b_temp = $branch_line;
				$b_temp = str_replace('#branchName#', $b_val, $b_temp);
				
				# show rooms
				$r_final = array();
				$r_temp = NULL;
				# get branch and line
				if( empty( $roomList['room'][$b_key] ) ):
					$r_final[] = $no_line_line;
				else:
					$count = 1;
					foreach( $roomList['room'][$b_key] as $r_key => $r_val):
						$r_temp = $line_line;
						$bg_class = ($count++%2) ? 'odd_row' : 'even_row';
						$r_temp = str_replace( '#bg_class#', $bg_class, $r_temp );
							
						$r_temp = str_replace( '#roomDesc#', $r_val, $r_temp );
						$r_temp = str_replace( '#branchID#', $b_key, $r_temp );
						$r_temp = str_replace( '#roomID#', $r_key, $r_temp );
						# amenity list
						# none
						if( count( $roomList['id'][$r_key]['amenity'] ) == 0 ):
							$r_temp = str_replace( '#amenityList#', 'None', $r_temp );
						else:
							$a_list = array();
							foreach( $roomList['id'][$r_key]['amenity'] as $a_key => $a_val ):
								$a_list[] = $amenityList[$a_val];
							endforeach;
							$r_temp = str_replace( '#amenityList#', implode( ', ', $a_list ), $r_temp );
						endif;
						
						$r_final[] = $r_temp;
					endforeach;
					
				endif;
				
				$b_temp = str_replace( '#line_line#', implode( "\r\n", $r_final ), $b_temp );
				$b_final[] = $b_temp;
				
			endforeach;
			
			$contents = str_replace( '#branch_line#', implode( "\r\n", $b_final ), $contents );
			
		endif;
		
		echo $contents;
	}
	
	public static function showRoomDelete( $roomID, $roomList, $branchList, $amenityList )
	# show delete page
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/rooms/delete.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# replace amenity lines
		$amenity_more_line = repCon( 'amenity_more', $contents );
		$amenity_some_line = repCon( 'amenity_some', $contents );
		$amenity_one_line = repCon( 'amenity_one', $contents, TRUE );
		$amenity_none_line = repCon( 'amenity_none', $contents, TRUE );
		
		# replace base information
		$contents = str_replace( '#branch#', $branchList[$roomList['id'][$roomID]['branch']], $contents );
		$contents = str_replace( '#roomDesc#', $roomList['id'][$roomID]['desc'], $contents );
		$contents = str_replace( '#roomID#', $roomID, $contents );
		
		# amenities
		switch( count( $roomList['id'][$roomID]['amenity'] ) ):
			case 0:
				$contents = str_replace( '#amenity_some_line#', $amenity_none_line, $contents );
				break;
				
			case 1:
				$contents = str_replace( '#amenity_some_line#', $amenity_one_line, $contents );
				# replace amenity
				$contents = str_replace( '#amenity#', $amenityList[current( $roomList['id'][$roomID]['amenity'] )], $contents );
				break;
				
			default:
				# replace amenities
				$temp = NULL;
				$final = array();
				$first = TRUE;
				
				# loop through amenities
				foreach( $roomList['id'][$roomID]['amenity'] as $key => $val ):
					# first amenity
					if( $first ):
						$first = FALSE;
						$amenity_some_line = str_replace( '#amenity1#', $amenityList[$val], $amenity_some_line );
						continue;						
					endif;
					
					$temp = $amenity_more_line;
					$temp = str_replace( '#amenity_more#', $amenityList[$val], $temp );
					$final[] = $temp;
					
				endforeach;
				$amenity_some_line = str_replace( '#amenity_more_line#', implode( "\r\n", $final ), $amenity_some_line );
				$contents = str_replace( '#amenity_some_line#', $amenity_some_line, $contents );
				$contents = str_replace( '#rowSpan#', count( $final )+1, $contents );
				break;
				
		endswitch;
		
		echo $contents;
	}
	
	
	public static function showRoomEdit( $roomInfo, $branchList, $amenityList, $action, $actionName )
	# show edit page and fill with values
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/rooms/edit.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# handle error line
		$error_line = repCon( 'error', $contents );
		if( empty( $roomInfo['errors'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );	
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMsg#', $roomInfo['errors'], $contents );
		endif;
		
		# replace value for room name, display and action
		$contents = str_replace( '#actionName#', $actionName, $contents );
		$contents = str_replace( '#action#', $action, $contents );
		$contents = str_replace( '#roomDesc#', $roomInfo['roomDesc'], $contents );
		$contents = str_replace( '#roomID#', $roomInfo['roomID'], $contents );
		
		# create drop down for branches
		$branch_line = repCon( 'branchList', $contents );
		
		$final = array();
		$temp = NULL;
		
		# setup initial/no selection
		$temp = $branch_line;
		$checked = ( empty( $roomInfo['branch'] ) ) ? ' checked="checked"' : NULL;		
		$temp = str_replace( '#branch_checked#', $checked, $temp );
		$temp = str_replace( '#branch_disp#', 'Choose a branch', $temp );
		$temp = str_replace( '#branch_val#', NULL, $temp);
		$final[] = $temp;
		foreach( $branchList as $key => $val ):
			$temp = $branch_line;
			$checked = ( !empty( $roomInfo['branch'] ) and $roomInfo['branch'] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#branch_selected#', $checked, $temp );
			$temp = str_replace( '#branch_disp#', $val, $temp );
			$temp = str_replace( '#branch_val#', $key, $temp);
			$final[] = $temp;				
		endforeach;
		$contents = str_replace( '#branchList_line#', implode( "\r\n", $final ), $contents );
		
		# amenities
		$amenitySome_line = repCon( 'amenitySome', $contents );
		$amenityNone_line = repCon( 'amenityNone', $contents, TRUE );
		
		# if none, show none line
		if( empty( $amenityList ) or !is_array( $amenityList ) or count( $amenityList ) == 0 ):
			$contents = str_replace( '#amenitySome_line#', $amenityNone_line, $contents );
		else:
		# if there are amenities, list them and check any that are in the room settings
			$a_final = array();
			$a_temp = NULL;
			
			foreach( $amenityList as $a_key => $a_val ):
				$a_temp = $amenitySome_line;
				
				$checked = ( !empty( $roomInfo['amenity'] ) 
						and is_array( $roomInfo['amenity'] ) 
						and in_array( $a_key, $roomInfo['amenity'] ) ) 
							? ' checked="checked"' 
							: NULL;
				$a_temp = str_replace( '#amenity_checked#', $checked, $a_temp );
				$a_temp = str_replace( '#amenity#', $a_val, $a_temp );
				$a_temp = str_replace( '#amenityID#', $a_key, $a_temp );
				$a_final[] = $a_temp;
			endforeach;
			
			$contents = str_replace( '#amenitySome_line#', implode( "\r\n", $a_final ), $contents );
			
		endif;
		
		echo $contents;
	}
}
?>