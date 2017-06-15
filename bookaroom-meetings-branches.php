<?PHP
class bookaroom_settings_branches
{
	############################################
	#
	# Branch Management
	#
	############################################
	public static function bookaroom_admin_branches()
	{
		$branchList = self::getBranchList();
		# figure out what to do
		# first, is there an action?
		$externals = self::getExternalsBranch();
		
		switch( $externals['action'] )
		{
			case 'deleteCheck':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['branchID'], $branchList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'branches/IDerror' );
				else:
					# show delete screen
					$branchInfo = self::getBranchInfo( $externals['branchID'] );
					$roomContList	= bookaroom_settings_roomConts::getRoomContList();
					$roomList		= bookaroom_settings_rooms::getRoomList();
					$container = self::makeRoomAndContList( $branchInfo, $roomContList, $roomList );	
					self::deleteBranch( $branchInfo, $container );
					bookaroom_settings::showMessage( 'branches/deleteSuccess' );
				endif;
				break;
			
			case 'delete':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['branchID'], $branchList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'branches/IDerror' );
				else:
					# show delete screen
					$branchInfo = self::getBranchInfo( $externals['branchID'] );
					$roomContList	= bookaroom_settings_roomConts::getRoomContList();
					$roomList		= bookaroom_settings_rooms::getRoomList();
					self::showBranchDelete( $branchInfo, $roomContList, $roomList );
				endif;
				
				break;
				
			case 'addCheck':
				# check entries
				if( ( $errors = self::checkEditBranch( $externals, $branchList ) ) == NULL ):
					self::addBranch( $externals );
					bookaroom_settings::showMessage( 'branches/addSuccess' );

					break;	
				endif;
				
				$externals['errors'] = $errors;
				# show edit screen
				self::showBranchEdit( $externals, 'addCheck', 'Add' );

				break;
				
			case 'add':
				self::showBranchEdit( NULL, 'addCheck', 'Add' );
				break;
				
			case 'editCheck':
				# check entries
				if( ( $errors = self::checkEditBranch( $externals, $branchList ) ) == NULL ):
					self::editBranch( $externals );
					bookaroom_settings::showMessage( 'branches/editSuccess' );

					break;	
				endif;
				
				$externals['errors'] = $errors;
				
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['branchID'], $branchList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'branches/IDerror' );
				else:
					# show edit screen
					self::showBranchEdit( $externals, 'editCheck', 'Edit', $externals );
				endif;
				
				break;
				
			case 'edit':
			
				# check that there is an ID and it is valid
				
				if( bookaroom_settings::checkID( $externals['branchID'], $branchList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'branches/IDerror' );
				else:
					# show edit screen
					$branchInfo = self::getBranchInfo( $externals['branchID'] );
					
					self::showBranchEdit( $branchInfo, 'editCheck', 'Edit', $externals );
				endif;
				
				break;
				
			default:
				self::showBranchList( $branchList );		
				break;
		}
	
	}
	
	# sub functions:
	############################################
	
	public static function addBranch( $externals )
	# add a new branch
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_branches";
		
		$finalTime = array();
		
		foreach( array( 'Open', 'Close' ) as $type ):
			for( $d = 0; $d <= 6; $d++ ):
				#open time
				$name = "branch{$type}_{$d}";
				$pmName = $name.'PM';
				
				if( empty( $externals[$name] ) ):
					$finalTime[$type][$d] = NULL;
				else:
					list( $h , $m ) = explode( ":",$externals[$name] );
					$timeVal = ( $h * 60 ) + $m;
					
					if( !empty( $externals[$pmName] ) ):
						$timeVal += 720;
					endif;
					
					$finalTime[$type][$d] = date( 'G:i:s', strtotime( '1/1/2000 00:00:00' ) + ( $timeVal * 60 ) );
				endif;
			endfor;
		endforeach;
		
		if( $externals['branch_isPublic'] == 'true' ):
			$branch_isPublic = 1;
		else:
			$branch_isPublic = 0;
		endif;

		if( $externals['branch_hasNoloc'] == 'true' ):
			$branch_hasNoloc = 1;
		else:
			$branch_hasNoloc = 0;
		endif;

		
		$final = $wpdb->insert( $table_name, 
			array( 	'branchDesc' => $externals['branchDesc'], 
					'branchAddress' => $externals['branchAddress'], 
					'branchMapLink' => $externals['branchMapLink'], 
					'branchImageURL' => $externals['branchImageURL'], 
					'branch_isPublic' =>$branch_isPublic, 
					'branch_hasNoloc' =>$branch_hasNoloc, 
					'branchOpen_0' => $finalTime['Open'][0], 
					'branchOpen_1' => $finalTime['Open'][1], 
					'branchOpen_2' => $finalTime['Open'][2], 
					'branchOpen_3' => $finalTime['Open'][3], 
					'branchOpen_4' => $finalTime['Open'][4], 
					'branchOpen_5' => $finalTime['Open'][5], 
					'branchOpen_6' => $finalTime['Open'][6], 
					'branchClose_0' => $finalTime['Close'][0], 
					'branchClose_1' => $finalTime['Close'][1], 
					'branchClose_2' => $finalTime['Close'][2], 
					'branchClose_3' => $finalTime['Close'][3], 
					'branchClose_4' => $finalTime['Close'][4], 
					'branchClose_5' => $finalTime['Close'][5], 
					'branchClose_6' => $finalTime['Close'][6] ) );

	}
	
	public static function checkEditBranch( &$externals, $branchList )
	# check the name for duplicates, the times for correct format and non-equal
	# or close after open
	{
		
		# check times
		$timeArr = array();
		$dayname = array( 0=>'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday' );
		$final = NULL;
		$error = array();
		
		foreach( $externals as $key => $val ):
			$day		= NULL;
			$type		= NULL;
			$timeVal	= NULL;
			# check for open or close
			if( stristr( $key, 'branchOpen' ) or stristr( $key, 'branchClose' ) ):
			
				switch( substr( $key, 0, 10 ) ):
					case 'branchOpen':
						$type = 'open';
						$errorType = 'opening';
						break;
					case 'branchClos':
						$type = 'close';
						$errorType = 'closing';
						break;
					default:
						die( 'Error!' );
						break;
				endswitch;
				# is checkbox?
				
				if( substr( $key, -2) == 'PM' ):
					$day = substr( $key, -3, 1);
					if( !is_null( $val ) ):
						# get day val
						$timeVal = 720;
					endif;
					
					if( $externals[ substr( $key, 0, -2) ] == '12:00' ):
						$timeVal = NULL;
					endif;
				else:
					#find day of the week
					$day = substr( $key, -1, 1);
					# is valid value?
					if( empty( $val )):
						continue;
					endif;
									
					if( count( explode( ":",$val ) ) !== 2 ):
						$error[] = "The {$errorType} time for {$dayname[$day]} is invalid.";
						continue;
					endif;

					
					list( $h , $m ) = explode( ":",$val );
					
					# not numeric?
					if( !is_numeric( $h ) or !is_numeric( $m ) ):
						$error[] = "The {$errorType} time for {$dayname[$day]} is invalid.";
						continue;
					endif;
					
					# invalid times?
					if ( ( $h <=12 and $h >=0  ) and ( $m <=59 and $m >=0 ) ):
						# get day
						$timeVal = ( $h * 60 ) + $m;
					else:
						$error[] = "The {$errorType} time for {$dayname[$day]} is invalid.";
						continue;
					endif;
				endif;

				# check for, and create, array entry if empty
				if( empty( $timeArr[$day][$type] ) ):
					$timeArr[$day][$type] = $timeVal;
				else:
					$timeArr[$day][$type] += $timeVal;
				endif;
				
			endif;	
			
		endforeach;
		
		# check close-before-opens and closed days
		for( $d = 0; $d <= 6; $d++ ):			
			# first, clear check if empty
			foreach( array( 'Open', 'Close' ) as $val ):
				$name = "branch{$val}_{$d}";
				$namePM = $name.'PM';
			
				if( empty( $externals[$name] ) ):					
					$externals[$namePM] = NULL;
					$typeName = strtolower( $val );
					$timeArr[$d][$typeName] = NULL;
				endif;
			endforeach;
			
			
			if( !$timeArr[$d]['close'] and !$timeArr[$d]['open'] ):
				#
			elseif( empty( $timeArr[$d]['close'] ) or empty( $timeArr[$d]['open'] ) ):
				$error[] = "Your must enter both a close and open time on {$dayname[$d]} or leave both blank if the branch is closed.";
			elseif( $timeArr[$d]['close'] <= $timeArr[$d]['open'] ):
				$error[] = "Your close time must come after your opening time on {$dayname[$d]}.";
			endif;
		endfor;
		
		# check for public
		if( empty( $externals['branch_isPublic'] ) ):
			$error[] = 'You must choose if this branch is availble for public scheduling.';	
		endif;

		# check for noloc
		if( empty( $externals['branch_hasNoloc'] ) ):
			$error[] = 'You must choose if this branch is has a "No location" option.';	
		endif;
		
		# check for empty branch name
		if( empty( $externals['branchAddress'] ) ):
			$error[] = 'You must enter an address.';	
		endif;

		# check for empty branch name
		if( empty( $externals['branchMapLink'] ) ):
			$error[] = 'You must enter a map link.';	
		endif;
		
		# check for empty branch name
		if( empty( $externals['branchDesc'] ) ):
			$error[] = 'You must enter a branch name.';	
		endif;
				
		# check dupe name		
		if( bookaroom_settings::dupeCheck( $branchList, $externals['branchDesc'], $externals['branchID'] ) == 1 ):
			$error[] = 'That branch name is already in use. Please choose another.';	
		endif;
		
		# if errors, implode and return error messages

		if( count( $error ) !== 0 ):
			$final = implode( "<br />", $error );
		endif;
		
		return $final;

	}

	public static function deleteBranch( $branchInfo, $container )
	# add a new branch
	{
		global $wpdb;
		

		$table_name = $wpdb->prefix . "bookaroom_branches";
		
		$sql = "DELETE FROM `{$table_name}` WHERE `branchID` = '{$branchInfo['branchID']}' LIMIT 1";
		$wpdb->query( $sql );
		
		$finalRooms = array();
		foreach( $container as $key => $val ):
			$finalRooms = array_unique( array_merge( $finalRooms, $val['rooms'] ) );
		endforeach;
		
		if( !empty( $finalRooms ) ):
			$table_name = $wpdb->prefix . "bookaroom_rooms";

			$finalRoomsImp = implode( ',', $finalRooms );
			$sql = "DELETE FROM `{$table_name}` WHERE `roomID` IN ({$finalRoomsImp}) LIMIT 1";
			$wpdb->query( $sql );
		endif;
		
		unset( $container[NULL] );

		$finalRoomConts =array_keys( $container );
		
		if( !empty( $finalRoomConts ) ):
			$table_name = $wpdb->prefix . "bookaroom_roomConts";

			$finalRoomsContsImp = implode( ',', $finalRoomConts );
			$sql = "DELETE FROM `{$table_name}` WHERE `roomCont_ID` IN ({$finalRoomsContsImp}) LIMIT 1";
			$wpdb->query( $sql );
		endif;
		
		return FALSE;
	}
	
	public static function editBranch( $externals )
	# change the branch settings
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_branches";
		
		$finalTime = array();
		
		foreach( array( 'Open', 'Close' ) as $type ):
			for( $d = 0; $d <= 6; $d++ ):
				#open time
				$name = "branch{$type}_{$d}";
				$pmName = $name.'PM';
				if( empty( $externals[$name] ) ):
					$finalTime[$type][$d] = NULL;
					$typeCast[$type][$d] = NULL;					
				else:
					list( $h , $m ) = explode( ":",$externals[$name] );
					# check for noon
					$timeVal = ( $h * 60 ) + $m;
					if( !empty( $externals[$pmName] ) ):
						if( $h !== '12' ):
							$timeVal += 720;
						endif;
					endif;
					$finalTime[$type][$d] = date( 'G:i:s', strtotime( '1/1/2000 00:00:00' ) + ( $timeVal * 60 ) );
					$typeCast[$type][$d] = '%s';
				endif;
			endfor;			
		endforeach;

		if( $externals['branch_isPublic'] == 'true' ):
			$branch_isPublic = 1;
		else:
			$branch_isPublic = 0;
		endif;
		
		if( $externals['branch_hasNoloc'] == 'true' ):
			$branch_hasNoloc = 1;
		else:
			$branch_hasNoloc = 0;
		endif;
		
		$final = $wpdb->update( $table_name, 
			array( 	'branchDesc' => $externals['branchDesc'], 
					'branchAddress' => $externals['branchAddress'], 
					'branchMapLink' => $externals['branchMapLink'],
					'branchImageURL' => $externals['branchImageURL'],
					'branch_isPublic' => $branch_isPublic,
					'branch_hasNoloc' => $branch_hasNoloc,
					'branchOpen_0' => $finalTime['Open'][0], 
					'branchOpen_1' => $finalTime['Open'][1], 
					'branchOpen_2' => $finalTime['Open'][2], 
					'branchOpen_3' => $finalTime['Open'][3], 
					'branchOpen_4' => $finalTime['Open'][4], 
					'branchOpen_5' => $finalTime['Open'][5], 
					'branchOpen_6' => $finalTime['Open'][6], 
					'branchClose_0' => $finalTime['Close'][0], 
					'branchClose_1' => $finalTime['Close'][1], 
					'branchClose_2' => $finalTime['Close'][2], 
					'branchClose_3' => $finalTime['Close'][3], 
					'branchClose_4' => $finalTime['Close'][4], 
					'branchClose_5' => $finalTime['Close'][5], 
					'branchClose_6' => $finalTime['Close'][6] ), 
			array( 'branchID' =>  $externals['branchID'] ), 
			array( '%s', '%s', '%s', '%s', '%s', '%s', 
					$typeCast['Open'][0], 
					$typeCast['Open'][1], 
					$typeCast['Open'][2], 
					$typeCast['Open'][3], 
					$typeCast['Open'][4], 
					$typeCast['Open'][5], 
					$typeCast['Open'][6], 
					$typeCast['Close'][0], 
					$typeCast['Close'][1], 
					$typeCast['Close'][2], 
					$typeCast['Close'][3], 
					$typeCast['Close'][4], 
					$typeCast['Close'][5], 
					$typeCast['Close'][6] ) );

	}
	
	public static function getBranchInfo( $branchID )
	# get information about branch from daabase based on the ID
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_branches";
		
		$final = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `branchID` = %d", $branchID ) );

		$branchInfo = array( 'branch_hasNoloc' => $final->branch_hasNoloc, 'branch_isPublic' => $final->branch_isPublic, 'branchDesc' => $final->branchDesc, 'branchID' => $final->branchID, 'branchAddress' => $final->branchAddress , 'branchMapLink' => $final->branchMapLink, 'branchImageURL' => $final->branchImageURL );

		
		# parse the times and convert from 24:00:00 to a 12:00 with a bit for PM
		foreach( $final as $key => $val ):
			if( !in_array( substr( $key, 0, 10 ), array( 'branchOpen', 'branchClos' ) ) ):
				continue;
			endif;
			
			if( empty( $val ) || $val == '00:00:00' ):
				$branchInfo[$key] = NULL;
			else:
				# make name for PM
				$name = $key . 'PM';
				$convTime = strtotime( '1/1/2000 '.$val );
				
				$branchInfo[$key] = date( "g:i", $convTime );
				$branchInfo[$name] = date( "a", $convTime ) == 'pm' ? TRUE : FALSE;
			endif;					
		endforeach;
		
		if( true == $branchInfo['branch_isPublic'] ):
			$branchInfo['branch_isPublic'] = 'true';
		else:
			$branchInfo['branch_isPublic'] = 'false';
		endif;
		
		if( true == $branchInfo['branch_hasNoloc'] ):
			$branchInfo['branch_hasNoloc'] = 'true';
		else:
			$branchInfo['branch_hasNoloc'] = 'false';
		endif;
		
		return $branchInfo;
	}
	
	public static function getBranchList( $full = NULL, $branch_isPublic = false )
	# get a list of all of the branches. Return NULL on no branches
	# otherwise, return an array with the unique ID of each branch
	# as the key and the description as the val
	{
		global $wpdb;
		$final = array();
		
		if( $branch_isPublic == true):
			$where = 'WHERE `branch_isPublic` = 1 ';
		else:
			$where = NULL;
		endif;
		
		$table_name = $wpdb->prefix . "bookaroom_branches";
		$sql = "SELECT `branchID`, `branchDesc`, `branchAddress`, `branchMapLink`, `branchImageURL`, `branch_isPublic`, `branch_hasNoloc`, `branchOpen_0`, `branchOpen_1`, `branchOpen_2`, `branchOpen_3`, `branchOpen_4`, `branchOpen_5`, `branchOpen_6`, `branchClose_0`, `branchClose_1`, `branchClose_2`, `branchClose_3`, `branchClose_4`, `branchClose_5`, `branchClose_6` FROM `$table_name` {$where}ORDER BY `branchDesc`";

		$count = 0;
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		if( count( $cooked ) == 0 ):
			return array();
		endif;
		
		foreach( $cooked as $key => $val ):
			if( $full ):
				$final[$val['branchID']] = $val;
			else:
				$final[$val['branchID']] = $val['branchDesc'];
			endif;
		endforeach;
		
		return $final;
 	}

	public static function getExternalsBranch()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'branchID'				=> FILTER_SANITIZE_STRING,
							'action'				=> FILTER_SANITIZE_STRING);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final += $getTemp;

		# setup POST variables
		$postArr = array(	'action'				=> FILTER_SANITIZE_STRING,
							'branchID'				=> FILTER_SANITIZE_STRING, 
							'branchDesc'			=> FILTER_SANITIZE_STRING, 
							'branch_isPublic'		=> FILTER_SANITIZE_STRING, 
							'branch_hasNoloc'		=> FILTER_SANITIZE_STRING, 							
							'branchAddress'			=> FILTER_SANITIZE_STRING, 
							'branchMapLink'			=> FILTER_SANITIZE_STRING, 
							'branchImageURL'		=> FILTER_SANITIZE_STRING, 
							'branchOpen_0'			=> FILTER_SANITIZE_STRING,
							'branchOpen_0PM'		=> FILTER_SANITIZE_STRING,
							'branchClose_0'			=> FILTER_SANITIZE_STRING,
							'branchClose_0PM'		=> FILTER_SANITIZE_STRING,
							'branchOpen_1'			=> FILTER_SANITIZE_STRING,
							'branchOpen_1PM'		=> FILTER_SANITIZE_STRING,
							'branchClose_1'			=> FILTER_SANITIZE_STRING,
							'branchClose_1PM'		=> FILTER_SANITIZE_STRING,
							'branchOpen_2'			=> FILTER_SANITIZE_STRING,
							'branchOpen_2PM'		=> FILTER_SANITIZE_STRING,
							'branchClose_2'			=> FILTER_SANITIZE_STRING,
							'branchClose_2PM'		=> FILTER_SANITIZE_STRING,
							'branchOpen_3'			=> FILTER_SANITIZE_STRING,
							'branchOpen_3PM'		=> FILTER_SANITIZE_STRING,
							'branchClose_3'			=> FILTER_SANITIZE_STRING,
							'branchClose_3PM'		=> FILTER_SANITIZE_STRING,
							'branchOpen_4'			=> FILTER_SANITIZE_STRING,
							'branchOpen_4PM'		=> FILTER_SANITIZE_STRING,
							'branchClose_4'			=> FILTER_SANITIZE_STRING,
							'branchClose_4PM'		=> FILTER_SANITIZE_STRING,
							'branchOpen_5'			=> FILTER_SANITIZE_STRING,
							'branchOpen_5PM'		=> FILTER_SANITIZE_STRING,
							'branchClose_5'			=> FILTER_SANITIZE_STRING,
							'branchClose_5PM'		=> FILTER_SANITIZE_STRING,
							'branchOpen_6'			=> FILTER_SANITIZE_STRING,
							'branchOpen_6PM'		=> FILTER_SANITIZE_STRING,
							'branchClose_6'			=> FILTER_SANITIZE_STRING,
							'branchClose_6PM'		=> FILTER_SANITIZE_STRING);
	
	
	
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) )
			$final += $postTemp;

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
	
	public static function makeRoomAndContList( $branchInfo, $roomContList, $roomList )
	{
		$branchID = $branchInfo['branchID'];
		$container = array();
		
		# rooms and room containers
		# cycle through each room and map to container (or none), then
		
		# cycle through any containers that don't have rooms.

		# first cycle containers
		$containers = array();
		$doneRoomList = array();
		if( !empty( $roomContList['names'][$branchID] ) && count( $roomContList['names'][$branchID] ) !== 0 ):
			foreach( $roomContList['names'][$branchID] as $key => $val ):
				$container[$key]['name'] = $val;
				$container[$key]['rooms'] = $roomContList['id'][$key]['rooms'];
				$doneRoomList = array_merge( $doneRoomList, $roomContList['id'][$key]['rooms'] );
				sort( $container[$key]['rooms'] );
			endforeach;
			$doneRoomList = array_unique( $doneRoomList );
			sort( $doneRoomList );			
		endif;
		
		# check for any rooms not in final room list
		$allRoomsBranch = array();
		if( !empty( $roomList['room'][$branchID] ) ):
			$allRoomsBranch = array_keys( $roomList['room'][$branchID] );
		endif;
		$unknown = array_diff( $allRoomsBranch, $doneRoomList );
		
		if( count( $unknown ) !== 0 ):
			$container[NULL] = array( 'name' => 'No container', 'rooms' => $unknown );
		endif;
		
		return $container;	
	}
	
	public static function showBranchDelete( $branchInfo, $roomContList, $roomList )
	# show delete page and fill with values
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/branches/delete.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		foreach( array( 'branchDesc', 'branchID', 'branchMapLink', 'branchImageURL' ) as $key ):
			$contents = str_replace( "#{$key}#", $branchInfo[$key], $contents );
		endforeach;
		$contents = str_replace( '#branchAddress#', nl2br( $branchInfo['branchAddress'] ), $contents );
		
		for( $d = 0; $d <= 6; $d++ ):			
			# find if closed

			if( empty( $branchInfo["branchOpen_{$d}"] ) or empty( $branchInfo["branchClose_{$d}"] ) ):
				$timeDisp = 'Closed';
			else:
				# get open and close time
				$openTime = $branchInfo["branchOpen_{$d}"] ;
				$openTime .= ( empty( $branchInfo["branchOpen_{$d}PM"] ) ) ? ' AM' : ' PM';
				$closeTime = $branchInfo["branchClose_{$d}"] ;
				$closeTime .= ( empty( $branchInfo["branchClose_{$d}PM"] ) ) ? ' AM' : ' PM';
				$timeDisp = $openTime.' to '.$closeTime;
			endif;
			
			$contents = str_replace( "#times_{$d}#", $timeDisp, $contents );
		endfor;
		
		# ROOMS in the branch
		# lines
		$closedRoomsSome_line	= repCon( 'closedRoomsSome', $contents );
		$roomsList_line			= repCon( 'roomsList', $closedRoomsSome_line );
		$containerLink_line		= repCon( 'containerLink', $closedRoomsSome_line );
		$containerNoLink_line	= repCon( 'containerNoLink', $closedRoomsSome_line, TRUE );
		
		$closedRoomsNone_line = repCon( 'closedRoomsNone', $contents, TRUE );

		$container = self::makeRoomAndContList( $branchInfo, $roomContList, $roomList );		
		
		# show room list based on empty or not		
		if( count( $container ) == 0 ):
			$contents = str_replace( '#closedRoomsSome_line#', $closedRoomsNone_line, $contents );
		else:
			$final = array();
			$temp = NULL;
			
			foreach( $container as $key => $val ):
				$temp = $closedRoomsSome_line;

				if($key == NULL ):
					$temp = str_replace( '#containerLink_line#', $containerNoLink_line, $temp );
				else:
					$temp = str_replace( '#containerLink_line#', $containerLink_line, $temp );
				endif;

				$temp = str_replace( '#container#', $val['name'], $temp );
				$temp = str_replace( '#roomContID#', $key, $temp );

				# make room list
				$r_temp = NULL;
				$r_final = array();

				foreach( $val['rooms'] as $r_val ):
					$r_temp = $roomsList_line;
					
					$r_temp = str_replace( '#roomName#', $roomList['id'][$r_val]['desc'], $r_temp );
					$r_temp = str_replace( '#roomID#', $r_val, $r_temp );
					
					$r_final[] = $r_temp;
				endforeach;
				
				$temp = str_replace( '#roomsList_line#', implode( '<br />', $r_final ), $temp );
				
				$final[] = $temp;
			endforeach;
			#$contents = str_replace( '#roomsList_line#', implode( '<br />', $final ), $contents );
			
			$contents = str_replace( '#closedRoomsSome_line#', implode( "\r\n", $final ), $contents );
		endif;

		$contents = str_replace( '#branchName#', $branchInfo['branchDesc'], $contents );
		
		echo $contents;
	}
	
	public static function showBranchEdit( $branchInfo, $action, $actionName, $externals = array() )
	# show edit page and fill with values
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/branches/edit.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# format branchInfo
		if( !is_array( $branchInfo ) or empty( $branchInfo ) ):
			$branchInfo = array( 'branchDesc' => NULL, 'branchID' => NULL, 'branchAddress' => NULL, 'branchMapLink' => NULL, 'branchImageURL' => NULL, 'branchOpen_0' => NULL, 'branchOpen_1' => NULL, 'branchOpen_2' => NULL, 'branchOpen_3' => NULL, 'branchOpen_4' => NULL, 'branchOpen_5' => NULL, 'branchOpen_6' => NULL, 'branchClose_0' => NULL, 'branchClose_1' => NULL, 'branchClose_2' => NULL, 'branchClose_3' => NULL, 'branchClose_4' => NULL, 'branchClose_5' => NULL, 'branchClose_6' => NULL	);
		endif;
		
		# add action - add or edit difference
		$contents = str_replace( '#action#', $action, $contents );
		$contents = str_replace( '#actionName#', $actionName, $contents );

		
		# handle error line
		$error_line = repCon( 'error', $contents );
		if( empty( $branchInfo['errors'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );	
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMsg#', $branchInfo['errors'], $contents);	
		endif;
		# replace description and ID
		foreach( array( 'branchDesc', 'branchID', 'branchMapLink', 'branchAddress', 'branchImageURL' ) as $key ):
			$contents = str_replace( "#{$key}#", $branchInfo[$key], $contents );
		endforeach;
		#$contents = str_replace( '#branchAddress#', nl2br( $branchInfo['branchAddress'] ), $contents );
		
		# parse and replace times and checkboxes
		foreach( $branchInfo as $key => $val ):
			# checkboxes
			if( substr( $key, -2 ) == "PM" ):
				$checked = ( $val == TRUE ) ? ' checked="checked"' : NULL;
				$contents = str_replace( $key.'_checked', $checked, $contents );
			else:
				$contents = str_replace( '#'.$key.'#', $val, $contents );	
			endif;
		endforeach;
		
		if( !empty( $branchInfo['branch_isPublic'] ) and $branchInfo['branch_isPublic'] == 'true' ):
			$branch_isPublicTrue = ' checked="checked"';
			$branch_isPublicFalse = NULL;
		else:
			$branch_isPublicTrue = NULL;
			$branch_isPublicFalse = ' checked="checked"';
		endif;
		
		$contents = str_replace( '#branch_isPublicTrue#', $branch_isPublicTrue, $contents );
		$contents = str_replace( '#branch_isPublicFalse#', $branch_isPublicFalse, $contents );	
		
		
		if( !empty( $branchInfo['branch_hasNoloc'] ) and $branchInfo['branch_hasNoloc'] == 'true' ):
			$branch_hasNolocTrue = ' checked="checked"';
			$branch_hasNolocFalse = NULL;
		else:
			$branch_hasNolocTrue = NULL;
			$branch_hasNolocFalse = ' checked="checked"';
		endif;
		
		$contents = str_replace( '#branch_hasNolocTrue#', $branch_hasNolocTrue, $contents );
		$contents = str_replace( '#branch_hasNolocFalse#', $branch_hasNolocFalse, $contents );	
		
		echo $contents;
	}
	
	public static function showBranchIDError()
	# show error page for invalid ID
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/branches/IDerror.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
	
	public static function showBranchList( $branchList )
	# show a list of branches with edit and delete links, or, if none 
	# a message stating there are no branches
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/branches/mainAdmin.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# setup placeholders
		$some_line = repCon( 'some', $contents );
		$none_line = repCon( 'none', $contents, TRUE );
		
		if( is_null( $branchList ) ):
			# if null, then replace with the 'no branches' line
			$contents = str_replace( '#some_line#', $none_line, $contents );
		else:
			# if there are branches, put back placeholder
			$contents = str_replace( '#some_line#', $some_line, $contents );
			
			# parse through the branches and create the list
			$line_line = repCon( 'line', $contents );
			
			$final = array();
			$temp = NULL;
			$count = 0;
			
			foreach( $branchList as $key => $val ):
				$temp = $line_line;
				$bg_class = ($count++%2) ? 'odd_row' : 'even_row';
				
				$temp = str_replace( '#bg_class#', $bg_class, $temp );
				$temp = str_replace( '#branchName#', $val, $temp );
				$temp = str_replace( '#branchID#', $key, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#line_line#', implode( "\r\n", $final ), $contents );
			
		endif;
		
		echo $contents;
	}
}
?>