<?PHP
class bookaroom_settings_roomConts
{
	############################################
	#
	# Room Container managment
	#
	############################################
	public static function bookaroom_admin_roomCont()
	{
		$roomContList = self::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList();
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		# figure out what to do
		# first, is there an action?
		$externals = self::getExternalsRoomCont();
		$error = NULL;
		
		switch( $externals['action'] ):
			case 'deleteCheck':
				if( bookaroom_settings::checkID( $externals['roomContID'], $roomContList['id'] ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'roomConts/IDerror' );
				else:
					# delete room
					self::deleteRoomCont( $externals['roomContID'] );
					bookaroom_settings::showMessage( 'roomConts/deleteSuccess' );
				endif;
				
				break;
			
			case 'delete':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['roomContID'], $roomContList['id'] ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'roomConts/IDerror' );
					break;
				# check for branch and make sure it is valid
				elseif( empty( $roomContList['id'][$externals['roomContID']]['branchID'] ) or !in_array(  $roomContList['id'][$externals['roomContID']]['branchID'], array_keys( $branchList ) ) ) :
					# show error page
					bookaroom_settings::showMessage( 'roomConts/noBranch' );
					break;
				else:
					# show delete screen
					self::showRoomContDelete( $externals['roomContID'], $roomContList, $roomList, $branchList, $amenityList );
				endif;
				
				break;
				
			case 'editCheck':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['roomContID'], $roomContList['id'] ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'roomConts/IDerror' );
					break;
				# check for branch and make sure it is valid
				elseif( empty( $roomContList['id'][$externals['roomContID']]['branchID'] ) or !in_array(  $roomContList['id'][$externals['roomContID']]['branchID'], array_keys( $branchList ) ) ) :
					# show error page
					bookaroom_settings::showMessage( 'roomConts/noBranch' );
					break;
				endif;	
				
				# check entries
				if( ( $errors = self::checkEditRoomConts( $externals, $roomContList, $branchList, $roomList, $externals['roomContID'] ) ) == NULL ):
					self::editRoomCont( $externals, $roomList );
					bookaroom_settings::showMessage( 'roomConts/editSuccess' );
					break;	
				else: 
					$externals['errors'] = $errors;
					$roomContInfo = self::getRoomContInfo( $externals['roomContID'] );
					self::showRoomContEdit( $externals, $roomContInfo['branchID'], $roomContList, $roomList, $branchList, $amenityList, 'editCheck', 'Edit' );
				endif;
				
				break;
				
			case 'edit':
				if( bookaroom_settings::checkID( $externals['roomContID'], $roomContList['id'] ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'roomConts/IDerror' );
					break;
				# check for branch and make sure it's valid
				elseif( empty( $roomContList['id'][$externals['roomContID']]['branchID'] ) or !in_array(  $roomContList['id'][$externals['roomContID']]['branchID'], array_keys( $branchList ) ) ) :
					bookaroom_settings::showMessage( 'roomConts/noBranch' );
					break;
				endif;
				
				$roomContInfo = self::getRoomContInfo( $externals['roomContID'] );
				self::showRoomContEdit( $roomContInfo, $roomContInfo['branchID'], $roomContList, $roomList, $branchList, $amenityList, 'editCheck', 'Edit' );
				break;
				
			case 'addCheck':
				if( empty( $externals['branchID'] ) or !in_array( $externals['branchID'], array_keys( $branchList ) ) ) :
					bookaroom_settings::showMessage( 'roomConts/noBranch' );
					break;
				endif;
				
				if( ( $error = self::checkEditRoomConts( $externals, $roomContList, $branchList, $roomList, NULL ) ) == TRUE):
				
					$externals['errors'] = $error;
					self::showRoomContEdit( $externals, $externals['branchID'], $roomContList, $roomList, $branchList, $amenityList, 'addCheck', 'Add' );
				else:
					self::addRoomCont( $externals, $roomList );
					bookaroom_settings::showMessage( 'roomConts/addSuccess' );
				endif;
				
				break;
				
			case 'add':
				if( empty( $externals['branchID'] ) or !in_array( $externals['branchID'], array_keys( $branchList ) ) ) :
					bookaroom_settings::showMessage( 'roomConts/noBranch' );
					break;
				endif;
				self::showRoomContEdit( NULL, $externals['branchID'], $roomContList, $roomList, $branchList, $amenityList, 'addCheck', 'Add' );
				break;
			
			default:
				self::showRoomContList( $roomContList, $roomList, $branchList, $amenityList );
				break;
				
		endswitch;
	}
	
	public static function addRoomCont( $externals, $roomList )
	# add a new branch
	{
		global $wpdb;
		
		$table_name			= $wpdb->prefix . "bookaroom_roomConts";
		$table_name_members	= $wpdb->prefix . "bookaroom_roomConts_members";
		
		# make room list
		# only use valid amenity ids and serialize
		$roomArr = array_intersect( array_keys( $roomList['id'] ), $externals['room'] );
		
		$roomArrSQL = array();
		
		$isPublic = ( $externals['isPublic'] == true ) ? 1 : 0;
		$hideDaily = ( $externals['hideDaily'] == true ) ? 1 : 0;
		$final = $wpdb->insert( $table_name, 
			array( 	'roomCont_desc'			=> $externals['roomContDesc'], 
					'roomCont_branch'		=> $externals['branchID'],
					'roomCont_isPublic'		=> $isPublic, 
					'roomCont_hideDaily'	=> $hideDaily, 
					'roomCont_occ'			=> $externals['occupancy'] ) );
		
		$roomContID = $wpdb->insert_id;
			
		foreach( $roomArr as $val ):
			$roomArrSQL[] = "( '{$roomContID}', '{$val}' )";
		endforeach;
		$roomSQL_final = implode( ", ", $roomArrSQL );
		
		$sql = "INSERT INTO `{$table_name_members}` ( `rcm_roomContID`, `rcm_roomID` ) VALUES {$roomSQL_final}";
		$wpdb->query( $sql );
	}
	
	
	public static function checkEditRoomConts( $externals, $roomContList, $branchList, $roomList, $roomContID )
	# check the room contianer to make sure everything is filled out
	# there are no dulicate names in the same branch
	# and the rooms are valid
	{
		$error = array();
		$final = NULL;
		# check name is filled and isn't duped in the same branch
		# check for empty room name
		if( empty( $externals['roomContDesc'] ) ):
			$error[] = 'You must enter a room container name.';	
		endif;
		
		# check dupe name FOR THAT CONTAINER - first, are there any containers?
		if( 	!empty( $roomContList['names'][$externals['branchID']] ) ):
			if( bookaroom_settings::dupeCheck( $roomContList['names'][$externals['branchID']], $externals['roomContDesc'], $externals['roomContID'] ) == 1 ):
			
				$error[] = 'That room container name is already in use at that branch. Please choose another.';	
			endif;
		endif;
		# clean out bad IDs for rooms and check to see if any are selected
		$roomError = FALSE;
		
		if( empty( $externals['room'] ) or !is_array( $externals['room'] ) ):
			$roomError = TRUE;
		else:
			
			$selectedRooms = array_intersect(  array_keys( $roomList['room'][$externals['branchID']] ), $externals['room'] );
			if( count( $selectedRooms ) == 0 ):
				$roomError = TRUE;
			endif;
		endif;
		
		if( $roomError ):
			$error[] = 'You must select at least one room to be in the container.';
		endif;
		
		# occupancy
		if( empty( $externals['occupancy'] ) ):
			$error[] = 'You must enter a maximum occupancy value.';
		elseif( !is_numeric( $externals['occupancy'] ) ):
			$error[] = 'You must enter a valid number for the maximum occupancy.';
		elseif( (float)$externals['occupancy'] !== (float)intval( $externals['occupancy'] ) ):
			$error[] = 'Really? Your maximum occupany can allow a frational human? Make it an integer, please.';
		endif;
		# if errors, implode and return error messages
	
		if( count( $error ) !== 0 ):
			$final = implode( "<br />", $error );
		endif;
		
		return $final;
	}
	
	public static function deleteRoomCont( $roomContID )
	# delete room container
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_roomConts";
		
		$sql = "DELETE FROM `{$table_name}` WHERE `roomCont_ID` = '{$roomContID}' LIMIT 1";
		$wpdb->query( $sql );
	}
	
	public static function editRoomCont( $externals, $roomList )
	# change the room container settings
	{
		global $wpdb;
		
		$table_name			= $wpdb->prefix . "bookaroom_roomConts";
		$table_name_members	= $wpdb->prefix . "bookaroom_roomConts_members";
		
		$roomContID = $externals['roomContID'];
		
		# make amenity list
		# if no amenities, then null
		if( empty( $externals['room'] ) ):
			$roomArr = NULL;
		else:
		# If there are some, only use valid amenity ids and serialize
			$goodArr = array_keys( $roomList['id'] );
			$roomArr = array_intersect( $goodArr, $externals['room'] );
		endif;
		$isPublic = ( !empty( $externals['isPublic'] ) ) ? 1 : 0;
		$hideDaily = ( !empty( $externals['hideDaily'] ) ) ? 1 : 0;
		
		$sql = "UPDATE `{$table_name}` SET `roomCont_desc` = '{$externals['roomContDesc']}', `roomCont_branch` = '{$externals['branchID']}', `roomCont_isPublic` = '{$isPublic}', `roomCont_hideDaily` = '{$hideDaily}',`roomCont_occ` = '{$externals['occupancy']}' WHERE `roomCont_ID` = '{$roomContID}'";
		
		$wpdb->query( $sql );
			
		foreach( $roomArr as $val ):
			$roomArrSQL[] = "( '{$roomContID}', '{$val}' )";
		endforeach;
		$roomSQL_final = implode( ", ", $roomArrSQL );
		
		$sql = "DELETE FROM `{$table_name_members}` WHERE `rcm_roomContID` = '{$roomContID}'";
		$wpdb->query( $sql );
		
		$sql = "INSERT INTO `{$table_name_members}` ( `rcm_roomContID`, `rcm_roomID` ) VALUES {$roomSQL_final}";
		$wpdb->query( $sql );
	}
	
	public static function getExternalsRoomCont()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'roomContID'		=> FILTER_SANITIZE_STRING,
							'branchID'			=> FILTER_SANITIZE_STRING, 
							'action'			=> FILTER_SANITIZE_STRING);
		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final += $getTemp;
		# setup POST variables
		$postArr = array(	'action'			=> FILTER_SANITIZE_STRING,
							'roomContID'		=> FILTER_SANITIZE_STRING, 
							'branchID'			=> FILTER_SANITIZE_STRING, 
							'isPublic'			=> FILTER_SANITIZE_STRING, 
							'hideDaily'			=> FILTER_SANITIZE_STRING, 
							'occupancy'			=> FILTER_SANITIZE_STRING, 
							'roomContDesc'		=> FILTER_SANITIZE_STRING, 							
							'room'				=> array(	'filter'    => FILTER_SANITIZE_STRING,
                           									'flags'     => FILTER_REQUIRE_ARRAY ) );
	
	
	
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) )
			$final += $postTemp;
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
	
	public static function getRoomContInfo( $roomContID )
	# get information about room container from database based on the ID
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_roomConts";
		$table_name_members	= $wpdb->prefix . "bookaroom_roomConts_members";
		$sql = "SELECT `roomCont`.`roomCont_ID`, `roomCont`.`roomCont_desc`, `roomCont`.`roomCont_branch`, `roomCont`.`roomCont_occ`, `roomCont`.`roomCont_isPublic`, `roomCont`.`roomCont_hideDaily`, 
				GROUP_CONCAT( `members`.`rcm_roomID` ) as `roomCont_roomArr` 
				FROM `$table_name` as `roomCont` 
				LEFT JOIN `$table_name_members` as `members` ON `roomCont`.`roomCont_ID` = `members`.`rcm_roomContID` 
				WHERE `roomCont`.`roomCont_ID` = '{$roomContID}'
				GROUP BY `roomCont`.`roomCont_ID`";
													
		$final = $wpdb->get_row( $sql, ARRAY_A );
		
		$roomContInfo = array('roomContID' => $roomContID, 'roomContDesc' => $final['roomCont_desc'], 'branchID' => $final['roomCont_branch'], 'room' => explode(',', $final['roomCont_roomArr'] ), 'occupancy'	=> $final['roomCont_occ'], 'isPublic' => $final['roomCont_isPublic'], 'hideDaily' => $final['roomCont_hideDaily'] );
		return $roomContInfo;
	}
	
	
	public static function getRoomContList( $isPublic = false )
	# get a list of room containers
	{
		global $wpdb;
		$roomContList = array();
		
		$table_name			= $wpdb->prefix . "bookaroom_roomConts";
		$table_name_members	= $wpdb->prefix . "bookaroom_roomConts_members";
		
		$where = NULL;
		
		if( $isPublic == true ):
			$where = "WHERE `roomCont`.`roomCont_isPublic` = '1'";
		endif;
		
		$sql = "SELECT `roomCont`.`roomCont_ID`, `roomCont`.`roomCont_desc`, `roomCont`.`roomCont_branch`, `roomCont`.`roomCont_occ`, `roomCont`.`roomCont_isPublic`, 
				`roomCont`.`roomCont_hideDaily`, 
				GROUP_CONCAT( `members`.`rcm_roomID` ) as `roomCont_roomArr` 
				FROM `$table_name` as `roomCont` 
				LEFT JOIN `$table_name_members` as `members` ON `roomCont`.`roomCont_ID` = `members`.`rcm_roomContID` 
				{$where}
				GROUP BY `roomCont`.`roomCont_ID` 
				ORDER BY `roomCont`.`roomCont_branch`, `roomCont`.`roomCont_desc`";
		
		
		$count = 0;
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		if( count( $cooked ) == 0):
			return array( 'id' => array(), 'names' => array(), 'branch' => array() );
		endif;
		foreach( $cooked as $key => $val):
			# check for rooms
			$roomsGood = ( empty( $val['roomCont_roomArr'] ) ) ? NULL : explode(',', $val['roomCont_roomArr'] );
			$roomContList['id'][$val['roomCont_ID']] = array( 'branchID' => $val['roomCont_branch'], 'rooms' => $roomsGood, 'desc' => $val['roomCont_desc'], 'occupancy' => $val['roomCont_occ'], 'isPublic' => $val['roomCont_isPublic'], 'hideDaily' => $val['roomCont_hideDaily'] );
			$roomContList['names'][$val['roomCont_branch']][$val['roomCont_ID']] = $val['roomCont_desc'];
			$roomContList['branch'][$val['roomCont_branch']][] = $val['roomCont_ID'];
			
		endforeach;
		
		return $roomContList;
	}
	
	public static function showRoomContDelete( $roomContID, $roomContList, $roomList, $branchList )
	# show delete page
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/roomConts/delete.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# replace amenity lines
		$room_more_line = repCon( 'room_more', $contents );
		$room_some_line = repCon( 'room_some', $contents );
		$room_one_line = repCon( 'room_one', $contents, TRUE );
		$room_none_line = repCon( 'room_none', $contents, TRUE );
		
		# replace base information
		$contents = str_replace( '#branch#', $branchList[$roomContList['id'][$roomContID]['branchID']], $contents );
		$contents = str_replace( '#roomContDesc#', $roomContList['id'][$roomContID]['desc'], $contents );
		$contents = str_replace( '#roomContID#', $roomContID, $contents );
		
		$isPublic = ( $roomContList['id'][$roomContID]['isPublic'] ) ? 'Yes' : 'No';
		$hideDaily = ( $roomContList['id'][$roomContID]['hideDaily'] ) ? 'Yes' : 'No';
		
		$contents = str_replace( '#isPublic#', $isPublic, $contents );
		$contents = str_replace( '#hideDaily#', $hideDaily, $contents );
		
		
		# amenities
		switch( count( $roomContList['id'][$roomContID]['rooms'] ) ):
			case 0:
				$contents = str_replace( '#room_some_line#', $room_none_line, $contents );
				break;
				
			case 1:
				$contents = str_replace( '#room_some_line#', $room_one_line, $contents );
				# replace room
				$contents = str_replace( '#room#', $roomList['id'][current( $roomContList['id'][$roomContID]['rooms'] )]['desc'], $contents );
				break;
				
			default:
				# replace rooms
				$temp = NULL;
				$final = array();
				$first = TRUE;
				
				# loop through rooms
				foreach( $roomContList['id'][$roomContID]['rooms'] as $key => $val ):
					# first room
					if( $first ):
						$first = FALSE;
						$room_some_line = str_replace( '#room1#', $roomList['id'][$val]['desc'], $room_some_line );
						continue;						
					endif;
					
					$temp = $room_more_line;
					$temp = str_replace( '#room_more#',  $roomList['id'][$val]['desc'], $temp );
					$final[] = $temp;
					
				endforeach;
				$room_some_line = str_replace( '#room_more_line#', implode( "\r\n", $final ), $room_some_line );
				$contents = str_replace( '#room_some_line#', $room_some_line, $contents );
				$contents = str_replace( '#rowSpan#', count( $final )+1, $contents );
				break;
				
		endswitch;
		
		
		echo $contents;
	}
	
	public static function showRoomContEdit( $roomContInfo, $branchID, $roomContList, $roomList, $branchList, $amenityList, $action, $actionName )
	# show edit page and fill with values
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/roomConts/edit.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# handle error line
		$error_line = repCon( 'error', $contents );
		if( empty( $roomContInfo['errors'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );	
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMsg#', $roomContInfo['errors'], $contents);	
		endif;
		
		# get lines
		$room_line = repCon( 'room', $contents );
		
		# base replace
		$contents = str_replace( '#action#', $action, $contents );
		$contents = str_replace( '#actionName#', $actionName, $contents );
		
		$contents = str_replace( '#occupancy#', $roomContInfo['occupancy'], $contents );
		$contents = str_replace( '#roomContID#', $roomContInfo['roomContID'], $contents );
		$contents = str_replace( '#roomContDesc#', $roomContInfo['roomContDesc'], $contents );
		
		$isPublic = ( $roomContInfo['isPublic'] ) ? ' checked="checked"' : NULL;
		$contents = str_replace( '#isPublic_checked#', $isPublic, $contents );
		
		$hideDaily = ( $roomContInfo['hideDaily'] ) ? ' checked="checked"' : NULL;
		$contents = str_replace( '#hideDaily_checked#', $hideDaily, $contents );
				
		$contents = str_replace( '#branchID#', $branchID, $contents );
		$contents = str_replace( '#branchName#', $branchList[$branchID], $contents );
		
		# list rooms and check any that are in the room container settings
		$r_final= array();
		$r_temp = NULL;
		
		foreach( $roomList['room'][$branchID] as $r_key => $r_val ):
			$r_temp = $room_line;
			
			$checked = ( !empty( $roomContInfo['room'] ) 
					and is_array( $roomContInfo['room'] ) 
					and in_array( $r_key, $roomContInfo['room'] ) ) 
						? ' checked="checked"' 
						: NULL;
			$r_temp = str_replace( '#room_checked#', $checked, $r_temp );
			$r_temp = str_replace( '#roomDesc#', $r_val, $r_temp );
			$r_temp = str_replace( '#roomID#', $r_key, $r_temp );
			$r_final[] = $r_temp;
		endforeach;
		
		$contents = str_replace( '#room_line#', implode( "\r\n", $r_final), $contents );
		
		echo $contents;
	}
	
	public static function showRoomContList( $roomContList, $roomList, $branchList, $amenityList )
	# show a list of rooms with edit and delete links, or, if none 
	# a message stating there are no branches
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/roomConts/mainAdmin.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		### setup placeholders
		# some or no actual containers
		$some_line	= repCon( 'some', $contents );
		$none_line	= repCon( 'none', $contents, TRUE );
		$public_line = repCon( 'public', $some_line );
		$hideDaily_line = repCon( 'hideDaily', $some_line );
		# actual room container line and none line
		$roomCont_some_line		= repCon( 'roomCont_some', $some_line );
		$roomCont_none_line		= repCon( 'roomCont_none', $some_line, TRUE );
		$add_line				= repCon( 'add', $some_line );
		$addNone_line			= repCon( 'addNone', $some_line, TRUE );
		
		$roomCont_rooms_line 	= repCon( 'roomCont_rooms', $roomCont_some_line );
		$roomCont_noRooms_line	= repCon( 'roomCont_noRooms', $roomCont_some_line, TRUE );
		
		
		# add line - check for branches and rooms for display
		$noneBranch_line	= repCon( 'noneBranch', $contents, TRUE );
		$noneRoom_line		= repCon( 'noneRoom', $contents );
		
		# add line for no branches or rooms
		if( count( $branchList ) == 0):
			$contents = str_replace( '#noneRoom_line#', $noneBranch_line, $contents );
		elseif( count( $roomList ) == 0 ):
			$contents = str_replace( '#noneRoom_line#', $noneRoom_line, $contents );
		else:
			$contents = str_replace( '#noneRoom_line#', NULL, $contents );
		endif;
		
		# show containers
		if( count( $branchList ) == 0 ):
			$contents = str_replace( '#some_line#', $none_line, $contents );
		else:
			# cycle branchse
			$b_temp = NULL;
			$b_final = array();
			
			foreach( $branchList as $b_key => $b_val ):
				$b_temp = $some_line;
				$b_temp = str_replace( '#branchName#', $b_val, $b_temp );
				#any rooms? If so, show the add line.
				if( empty( $roomList['room'][$b_key] ) or count( $roomList['room'][$b_key] ) == 0 ):
					$b_temp = str_replace( '#add_line#', $addNone_line, $b_temp );					
				else:
					$b_temp = str_replace( '#add_line#', $add_line, $b_temp );
					$b_temp = str_replace( '#branchID#', $b_key, $b_temp );
					
				endif;
				
				# no containers for this branch?
				if( empty( $roomContList['branch'][$b_key] ) ):
					$b_final[] = str_replace( '#roomCont_some_line#', $roomCont_none_line, $b_temp );
					continue;
				endif;
				
				# there are containers in this branch. Cycle through them
				
				
				$rc_temp = NULL;
				$rc_final = array();
				$count = 0;
				foreach( $roomContList['branch'][$b_key] as $rc_key => $rc_val ):
					# cycle through rooms
					$bg_class = ($count++%2) ? 'odd_row' : 'even_row';
					
					$rc_temp = $roomCont_some_line;
					$rc_temp = str_replace( '#bg_class#', $bg_class, $rc_temp );
					$rc_temp = str_replace( '#roomContDesc#', $roomContList['id'][$rc_val]['desc'], $rc_temp );
					$rc_temp = str_replace( '#roomContID#', $rc_val, $rc_temp );
					if( $roomContList['id'][$rc_val]['isPublic'] ):
						$rc_temp = str_replace( '#public_line#', $public_line, $rc_temp );						
					else:
						$rc_temp = str_replace( '#public_line#', NULL, $rc_temp );
					endif;
					
					if( $roomContList['id'][$rc_val]['hideDaily'] ):
						$rc_temp = str_replace( '#hideDaily_line#', $hideDaily_line, $rc_temp );						
					else:
						$rc_temp = str_replace( '#hideDaily_line#', NULL, $rc_temp );
					endif;
					
					# cycle through each room container
					$room_temp = NULL;
					$room_final = array();
					
					# are there any rooms?
					if( ( is_array( $roomList['id'] ) && is_array( $roomContList['id'][$rc_val]['rooms'] ) ) &&
					( count( array_intersect( array_keys( $roomList['id'] ), $roomContList['id'][$rc_val]['rooms'] ) ) == 0 ) ):
						$rc_final[] = str_replace( '#roomCont_rooms_line#', $roomCont_noRooms_line, $rc_temp );					
					else:
						if( is_array( $roomContList['id'][$rc_val]['rooms'] ) ):
							foreach( $roomContList['id'][$rc_val]['rooms'] as $room_key => $room_val ):
								$room_temp = $roomCont_rooms_line;
	
								$room_temp = str_replace( "#roomDesc#", $roomList['id'][$room_val]['desc'], $room_temp );
								$room_temp = str_replace( "#bg_class#", $bg_class, $room_temp );
								
								# amenity list
								if( count( $roomList['id'][$room_val]['amenity'] ) == 0 ):
									$room_temp = str_replace( '#amenityList#', 'None', $room_temp );
								else:
									$a_list = array();
									foreach(  $roomList['id'][$room_val]['amenity'] as $a_key => $a_val ):
										$a_list[] = $amenityList[$a_val];
									endforeach;
									$room_temp = str_replace( '#amenityList#', implode( ', ', $a_list ), $room_temp );
								endif;
							
								$room_final[] = $room_temp;
							endforeach;
						endif;
												
						$rc_final[] = str_replace( "#roomCont_rooms_line#", implode( "\r\n", $room_final ), $rc_temp );
					endif;
				endforeach;
				$b_temp = str_replace( '#roomCont_some_line#', implode( "\r\n", $rc_final ), $b_temp );
				
				$b_final[] = $b_temp;
			endforeach;
			
			$contents = str_replace( '#some_line#', implode( "\r\n", $b_final ), $contents );
		endif;
		
		echo $contents;
	}
}
?>