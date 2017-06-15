<?PHP
class bookaroom_settings_closings
{
	protected $roomContList	= array();
	protected $roomList		= array();
	protected $branchList	= array();
	protected $amenityList	= array();
	protected $externals	= array();
	protected $closings		= array();

	public static function bookaroom_admin_closings()
	{	
		
		$roomContList	= bookaroom_settings_roomConts::getRoomContList();
		$roomList		= bookaroom_settings_rooms::getRoomList();
		$branchList		= bookaroom_settings_branches::getBranchList();
		$amenityList	= bookaroom_settings_amenities::getAmenityList();
		$closings		= self::getClosingsList();
		# figure out what to do
		# first, is there an action?
		$externals		= self::getExternalsClosings();
		$error = NULL;
		
		switch( $externals['action'] ):
			case 'add':
				self::showClosingsEdit( NULL, $roomList, $branchList, 'addCheck', 'Add' );
				break;
			
			case 'addCheck':
				if( ( $errors = self::checkClosingsEdit( $externals, $roomList ) ) == NULL ):
					if( self::addClosing( $externals ) == FALSE ):
						bookaroom_settings::showMessage( 'closings/addError' );
					else:
						bookaroom_settings::showMessage( 'closings/addSuccess' );
					endif;
					break;	
				endif;
				
				$externals['errors'] = $errors;
				# show edit screen
				self::showClosingsEdit( $externals, $roomList, $branchList, 'addCheck', 'Add' );
				
				break;
			
			case 'edit':
				if( ( $closingInfo = self::getClosingInfo( $externals['closingID'] ) ) == FALSE ):
					bookaroom_settings::showMessage( 'closings/IDerror' );
					break;
				endif;
				
				self::showClosingsEdit( $closingInfo, $roomList, $branchList, 'editCheck', 'Edit' );
				break;
			
			case 'editCheck':
				if( ( $errors = self::checkClosingsEdit( $externals, $roomList ) ) == NULL ):
					if( self::editClosing( $externals ) == FALSE ):
						bookaroom_settings::showMessage( 'closings/addError' );
					else:
						bookaroom_settings::showMessage( 'closings/editSuccess' );
					endif;
					break;	
				endif;
				break;
				
			case 'delete':
				if( ( $closingInfo = self::getClosingInfo( $externals['closingID'] ) ) == FALSE ):
					bookaroom_settings::showMessage( 'closings/IDerror' );
					break;
				endif;
				
				self::showClosingsDelete( $closingInfo, $roomList, $branchList );
				break;
			
			case 'deleteCheck':
				if( ( $closingInfo = self::getClosingInfo( $externals['closingID'] ) ) == FALSE ):
					bookaroom_settings::showMessage( 'closings/IDerror' );
					break;
				endif;
				
				self::deleteClosing( $closingInfo['closingID'] );
				bookaroom_settings::showMessage( 'closings/deleteSuccess' );
				break;
			
			case 'deleteMulti':
				# check for valid IDS
				$multiError = NULL;
				
				if( self::checkDeleteMultiError( $externals['closingMulti'], $closings, $multiError ) == TRUE ):
					self::showClosings( $closings, $roomContList, $roomList, $branchList, $amenityList, $multiError );
					break;
				endif;
				self::showDeleteMulti( $externals['closingMulti'], $closings, $roomContList, $roomList, $branchList, $amenityList );
				break;
			
			case 'deleteMultiCheck':
				$multiError = NULL;
				if( self::checkDeleteMultiError( $externals['closingMulti'], $closings, $multiError ) == TRUE ):
					self::showClosings( $closings, $roomContList, $roomList, $branchList, $amenityList, $multiError );
					break;
				endif;
					self::deleteClosingMulti( $externals['closingMulti'] );
					
					bookaroom_settings::showMessage( 'closings/deleteSuccess' );
				break;
				
			default:
				self::showClosings( $closings, $roomContList, $roomList, $branchList, $amenityList );
				break;
			
		endswitch;
	}
	
	protected static function addClosing( $externals )
	# add a new branch
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_closings";
		
		switch( $externals['selType'] ):
			case 'date':
				$userInfo = wp_get_current_user();
				$userName = $userInfo->display_name.' ['.$userInfo->user_login.']';
				
				$reoccuring = ( $externals['date_reoccuring'] ) ? TRUE : FALSE;
				
				# all rooms?
				switch( $externals['roomType'] ):
					case 'choose':
						$roomsClosed = serialize( $externals['rooms'] );
						$allClosed = FALSE;
						break;
					case 'all':
						$roomsClosed = NULL;
						$allClosed = TRUE;
						break;
						
					default:
						die( 'Error: wrong room type' );
						break;
				endswitch;

				$final = $wpdb->insert( $table_name, array( 	
					'reoccuring'		=> $reoccuring,
					'type'				=> $externals['selType'],
					'startDay'			=> $externals['date_single_day'],
					'startMonth'		=> $externals['date_single_month'],
					'startYear'			=> $externals['date_single_year'],
					'allClosed'			=> $allClosed,
					'roomsClosed'		=> $roomsClosed,
					'closingName'		=> $externals['closingName'], 
					'username'			=> $userName ) );
					return TRUE;
				break;

			case 'range':
				$userInfo = wp_get_current_user();
				$userName = $userInfo->display_name.' ['.$userInfo->user_login.']';
				$reoccuring = ( $externals['dateRange_reoccuring'] ) ? TRUE : FALSE;
				# all rooms?
				switch( $externals['roomType'] ):
					case 'choose':
						$roomsClosed = serialize( $externals['rooms'] );
						$allClosed = FALSE;
						break;
					case 'all':
						$roomsClosed = NULL;
						$allClosed = TRUE;
						break;
						
					default:
						die( 'Error: wrong room type' );
						break;
				endswitch;

				$final = $wpdb->insert( $table_name, array( 	
					'reoccuring'		=> $reoccuring,
					'type'				=> $externals['selType'],
					'endDay'			=> $externals['date_end_day'],
					'endMonth'			=> $externals['date_end_month'],
					'endYear'			=> $externals['date_end_year'],
					'startDay'			=> $externals['date_start_day'],
					'startMonth'		=> $externals['date_start_month'],
					'startYear'			=> $externals['date_start_year'],					
					'allClosed'			=> $allClosed,
					'roomsClosed'		=> $roomsClosed,
					'closingName'		=> $externals['closingName'], 
					'username'			=> $userName ) );
					return TRUE;
				break;
				
			default:
				return FALSE;
				break;
		endswitch;
	}
	
	protected static function checkClosingsEdit( $externals, $roomList )
	{
		# check for errors
		$errors = array();
		$final = NULL;
		# type selected
		
		# closing name
		if( empty( $externals['closingName'] ) ):
			$errors[] = 'You must enter a name for this closing.';
		endif;

		switch( $externals['selType'] ):
			case 'date':
				self::checkDateError( $externals['date_single_year'], $externals['date_single_month'], $externals['date_single_day'], $externals['date_reoccuring'], 'date', $errors );
				break;

			case 'range':
				$startError = self::checkDateError( $externals['date_start_year'], $externals['date_start_month'], $externals['date_start_day'], $externals['dateRange_reoccuring'], 'start date', $errors );
				$endError = self::checkDateError( $externals['date_end_year'], $externals['date_end_month'], $externals['date_end_day'], $externals['dateRange_reoccuring'], 'end date', $errors );
				
				# start date is different and before end date
				# check for no errors in dates
				if( ( $startError or $endError ) == FALSE ):
					# make year
					
					if( $externals['dateRange_reoccuring'] ):
						$startYear = $endYear = 2012;
					else:
						$startYear = $externals['date_start_year'];
						$endYear = $externals['date_end_year'];
					endif;
					# make times
					$startTime = mktime( 1, 1, 1, $externals['date_start_month'], $externals['date_start_day'], $startYear );
					$endTime = mktime( 1, 1, 1, $externals['date_end_month'], $externals['date_end_day'], $endYear );
					if( $startTime == $endTime ):
						$errors[] = 'Your dates are the same. Instead of a range, choose a single date.';
					elseif( $startTime > $endTime ):
						$errors[] = 'Your start time comes after your end time.';
					endif;

				endif;
				break;

			case 'special':
				$errors[] = 'You chose special.';
				break;

			default:
				$errors[] = 'You must select a valid closing type.';
				break;
				
		endswitch;
		
		# check type - just in case!		
		# with special
		# $goodTypeArr = array( 'date' => 'Date', 'range' => 'Range', 'special' => 'Special' );
		$goodTypeArr = array( 'date' => 'Date', 'range' => 'Range');
		
		if( empty( $externals['selType'] ) or !array_key_exists( $externals['selType'], $goodTypeArr ) ):
			$errors[] = 'You much choose a closing type.';
		endif;
		
		
		# check for closings
		if( empty( $externals['roomType'] ) ):
			$errors[] = 'You must select a rooms type.';
		elseif( $externals['roomType'] !== 'all' ):
			if( empty( $externals['rooms'] ) ):
				$errors[] = 'You must choose at least one room to close.';			
			elseif( count( array_intersect( array_keys( $roomList['id'] ), $externals['rooms'] ) ) == 0 ):
				$errors[] = 'Somehow, you\'ve chosed invalid rooms. Please try again.';
			endif;
		endif;
					 
		# closing types
		if( count( $errors ) !== 0 ):
			$finalErrors = implode( '<br /><br />', $errors );
			return $finalErrors;
		else:
			return NULL;
		endif;

	}

	protected static function checkDateError( $year, $month, $day, $reoccuringName, $displayName, &$errors )
	{
			$startErrors = count( $errors );
			
			# empty month
			if( empty( $month ) or ( !is_numeric( $month ) or $month < 1 or $month > 12 ) ):
				$errors[] = "You must choose a month for your {$displayName}.";
			endif;
			# empty day
			if( empty( $day ) or  ( !is_numeric( $day ) or $day < 1 or $day > 31 ) ):
				$errors[] = "You must choose a day for your {$displayName}.";
			endif;
			# year if not reoccuring
			
			if( empty( $reoccuringName ) && ( empty( $year ) or !is_numeric( $year ) ) ):
				$errors[] = "If not reoccuring, you must choose a year for your {$displayName}.";
			endif;
			
			# is there that many days in the average month
			#
			# first ignore reoccuring leap year by setting the year to a leap year
			$curYear = ( $reoccuringName == TRUE ) ? 2012 : $year;
			$numDaysMonth = date( 't', mktime( 1, 1, 1, $month, 1, $curYear ) );
			if( $day > $numDaysMonth ):
				$errors[] = "There aren't that many days in the month for your {$displayName}.";
			endif;
			
			# if new errors, return TRUE, otherwise return FALSE
			return ( $startErrors !== count( $errors ) ) ? TRUE : FALSE;
	}
	
	protected static function checkDeleteMultiError( &$multiList, $closings, &$error )
	{
		if( !is_array( $multiList ) or count( $multiList ) == 0 ):
			$error = 'You must select at least one closing to delete.';
			return TRUE;
		endif;
		
		# is valid numbers?
		
		$realClosings = array_unique( array_keys(  $closings['times']['live'] + $closings['times']['expired'] ) );
		$multiList = array_intersect( $multiList, $realClosings );
		if( count( $multiList ) == 0):
			$error = 'There are no valid IDs from the list you chose. Please try again..';
			return TRUE;
		endif;
		
		return FALSE;
		
	}
	
	protected static function editClosing( $externals )
	# edit a closing
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "bookaroom_closings";
		
		switch( $externals['selType'] ):
			case 'date':
				$userInfo = wp_get_current_user();
				$userName = $userInfo->display_name.' ['.$userInfo->user_login.']';
				
				$reoccuring = ( $externals['date_reoccuring'] ) ? TRUE : FALSE;
				
				# all rooms?
				switch( $externals['roomType'] ):
					case 'choose':
						$roomsClosed = serialize( $externals['rooms'] );
						$allClosed = FALSE;
						break;
					case 'all':
						$roomsClosed = NULL;
						$allClosed = TRUE;
						break;
						
					default:
						die( 'Error: wrong room type' );
						break;
				endswitch;

				$final = $wpdb->update( $table_name, array( 	
					'reoccuring'		=> $reoccuring,
					'type'				=> $externals['selType'],
					'startDay'			=> $externals['date_single_day'],
					'startMonth'		=> $externals['date_single_month'],
					'startYear'			=> $externals['date_single_year'],
					'allClosed'			=> $allClosed,
					'roomsClosed'		=> $roomsClosed,
					'closingName'		=> $externals['closingName'], 
					'username'			=> $userName ), 
					array( 'closingID' =>  $externals['closingID'] ) );
					return TRUE;
				break;

			case 'range':
				$userInfo = wp_get_current_user();
				$userName = $userInfo->display_name.' ['.$userInfo->user_login.']';
				$reoccuring = ( $externals['dateRange_reoccuring'] ) ? TRUE : FALSE;
				# all rooms?
				switch( $externals['roomType'] ):
					case 'choose':
						$roomsClosed = serialize( $externals['rooms'] );
						$allClosed = FALSE;
						break;
					case 'all':
						$roomsClosed = NULL;
						$allClosed = TRUE;
						break;
						
					default:
						die( 'Error: wrong room type' );
						break;
				endswitch;

				$final = $wpdb->update( $table_name, array( 	
					'reoccuring'		=> $reoccuring,
					'type'				=> $externals['selType'],
					'endDay'			=> $externals['date_end_day'],
					'endMonth'			=> $externals['date_end_month'],
					'endYear'			=> $externals['date_end_year'],
					'startDay'			=> $externals['date_start_day'],
					'startMonth'		=> $externals['date_start_month'],
					'startYear'			=> $externals['date_start_year'],					
					'allClosed'			=> $allClosed,
					'roomsClosed'		=> $roomsClosed,
					'closingName'		=> $externals['closingName'], 
					'username'			=> $userName ),
					array( 'closingID' =>  $externals['closingID'] ) );
					return TRUE;
				break;
				
			default:
				return FALSE;
				break;
		endswitch;
	}
	
	protected static function dateDropDown( $extVals, $dropName, $contents, $yearsAhead = 4 )
	# make date drop down
	{
		$weekArr = self::getWeekArray();
		
		# month
		$monthLine = repCon( "{$dropName}_month", $contents );
		$temp = NULL;
		$final = array();
		$weekArr['month'] = array( NULL =>'Month' ) + $weekArr['month'];
		foreach( $weekArr['month'] as $key => $val ):
			$temp = $monthLine;
			$selected = ( $extVals["{$dropName}_month"] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( "#{$dropName}_month_disp#", $val, $temp );
			$temp = str_replace( "#{$dropName}_month_val#", $key, $temp );
			$temp = str_replace( "#{$dropName}_month_selected#", $selected, $temp );
			$final[] = $temp;
		endforeach;
		$contents = str_replace( "#{$dropName}_month_line#", implode( "\r\n", $final ), $contents );
		
		# day
		$dayLine = repCon( "{$dropName}_day", $contents );
		$temp = NULL;
		$final = array();
		$dayArr = array( NULL =>'Day' );
		for( $d=1; $d <= 31; $d++ ):
			$dayStamp = mktime( 1,1,1, 10, $d, 2012 );
			$dayArr[$d] = date( 'jS', $dayStamp );
		endfor;
		foreach( $dayArr as $key => $val ):
			$temp = $dayLine;
			$selected = ( $extVals["{$dropName}_day"] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( "#{$dropName}_day_disp#", $val, $temp );
			$temp = str_replace( "#{$dropName}_day_val#", $key, $temp );
			$temp = str_replace( "#{$dropName}_day_selected#", $selected, $temp );
			$final[] = $temp;
		endforeach;
		$contents = str_replace( "#{$dropName}_day_line#", implode( "\r\n", $final ), $contents );
				
		# year
		$yearLine = repCon( "{$dropName}_year", $contents );
		$temp = NULL;
		$final = array();
		$yearArr = array( NULL =>'Year' );
		$thisYear = date( 'Y' );
		for( $y=$thisYear; $y <= $yearsAhead + $thisYear; $y++ ):

			$yearArr[$y] = $y;
		endfor;
		foreach( $yearArr as $key => $val ):
			$temp = $yearLine;
			$selected = ( $extVals["{$dropName}_year"] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( "#{$dropName}_year_disp#", $val, $temp );
			$temp = str_replace( "#{$dropName}_year_val#", $key, $temp );
			$temp = str_replace( "#{$dropName}_year_selected#", $selected, $temp );
			$final[] = $temp;
		endforeach;
		$contents = str_replace( "#{$dropName}_year_line#", implode( "\r\n", $final ), $contents );	
		return $contents;
	}
	
	protected static function deleteClosing( $closingID )
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_closings";
		
		$sql = "DELETE FROM `{$table_name}` WHERE `closingID` = '{$closingID}' LIMIT 1";
		$wpdb->query( $sql );

	}
	
	protected static function deleteClosingMulti( $multiList )
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_closings";
		$mutliString = implode( ',', $multiList );
		$limit = count( $multiList );
		$sql = "DELETE FROM `{$table_name}` WHERE `closingID` IN ({$mutliString}) LIMIT {$limit}";
		$wpdb->query( $sql );
	}
	
	protected static function doClosings( $closingArr, $closingTimes, $term, $contents )
	{
		$weekArr = self::getWeekArray();
		
		$someItem_line	= repCon( "some{$term}", $contents );
		$item_line		= repCon( "item{$term}", $someItem_line );
		$noneItem_line	= repCon( "none{$term}", $contents, TRUE );
		
		if( empty( $closingArr )):
			$contents = str_replace( "#some{$term}_line#", $noneItem_line, $contents );
		else:
			$contents = str_replace( "#some{$term}_line#", $someItem_line, $contents );
			
			$temp = NULL;
			$final = array();
			$count = 0;
			
			foreach( $closingTimes as $key => $val):
				$bg_class = ($count++%2) ? 'odd_row' : 'even_row';

				$temp = $item_line;
				$temp = str_replace( '#bg_class#', $bg_class, $temp );
				$temp = str_replace( '#closingName#', $closingArr[$key]['closingName'], $temp );
				$temp = str_replace( '#type#', ucfirst( $closingArr[$key]['type'] ), $temp );
				
				# reoocuring
				$reoccuring = ( empty( $closingArr[$key]['reoccuring'] ) ) ? 'No' : 'Yes';
				$temp = str_replace( '#reoccuring#', $reoccuring, $temp );
				
				$dateDesc = ( $closingArr[$key]['reoccuring'] == TRUE ) ? 'D, M jS' : 'D, M jS Y';
				
				# date
				switch( $closingArr[$key]['type'] ):
					/*
					case 'special':
						$date = date( 'D, M jS, Y', $closingArr[$key]['startTime'] ).'<br />'.
							"[{$weekArr['week'][$closingArr[$key]['spWeek']]} {$weekArr['day'][$closingArr[$key]['spDay']]} of {$weekArr['month'][$closingArr[$key]['spMonth']]} {$closingArr[$key]['spYear']}]";
						break;
					*/	
					case 'range':
						
						$date = date( $dateDesc, $closingArr[$key]['startTime'] ) .' to <br />' .
									date( $dateDesc, $closingArr[$key]['endTime'] );

						break;
						
					case 'date':
						$date = date( $dateDesc, $closingArr[$key]['startTime'] );
						break;
						
					default:
						$date = "Unknown date type: {$closingArr[$key]['type']}";
						break;						
				endswitch;
				
				$temp = str_replace( '#date#', $date, $temp );
				
				# rooms
				switch( $closingArr[$key]['allClosed'] ):
					case false:
						$rooms = self::makeRooms( $closingArr[$key]['roomsClosed'] );
						break;
						
					default:
						$rooms = 'All';
						break;
						
				endswitch;

				$temp = str_replace( '#rooms#', $rooms, $temp );

				# ID for form and edit/delete
				$temp = str_replace( '#closingID#', $closingArr[$key]['closingID'], $temp );
				
				$final[] = $temp;
			endforeach;
			$contents = str_replace( "#item{$term}_line#", implode( "\r\n", $final ), $contents );
			
		endif;
		
		return $contents;		
	}
	
	protected static function getClosingInfo( $closingID )
	{
		global $wpdb;

		$table_name = $wpdb->prefix . "bookaroom_closings";
		$sql = "SELECT `closingID` as `closingID`, `reoccuring`, `type` as `selType`, `startDay`, `startMonth`, `startYear`, `endDay`, `endMonth`, `endYear`, `closingName`, `allClosed`, `roomsClosed` FROM `$table_name` WHERE `closingID` = '{$closingID}'";
		
		$cooked = $wpdb->get_row( $sql, ARRAY_A );
		
		if( empty( $cooked ) ):
			return FALSE;
		endif;
		
		$final = array();
		
		# simple substitutions
		$easyArr = array( 'closingName', 'closingID', 'date_single_month', 'date_single_day', 'date_single_year', 'date_start_month', 'date_start_day', 'date_start_year', 'date_end_month', 'date_end_day', 'date_end_year', 'specialYear', 'specialMonth', 'specialDay', 'specialWeek', 'date_reoccuring', 'dateRange_reoccuring', 'special_reoccuring', 'selType' );
		
		foreach( $easyArr as $key => $val):
			$final[$val] = ( empty( $cooked[$val] ) ) ? NULL : $cooked[$val];
		endforeach;
		
		# roomType = all rooms or selected rooms
		if( $cooked['allClosed'] == TRUE ):
			$final['roomType'] = 'all';
			$final['rooms'] = NULL;
		else:
			$final['roomType'] = 'choose';
			$final['rooms'] = unserialize( $cooked['roomsClosed'] );
		endif;
		# dates
		switch( $cooked['selType'] ):
			case 'date':
				$final['date_reoccuring']		= ( $cooked['reoccuring'] == TRUE ) ? TRUE : FALSE;				
				$final['date_single_month']		= $cooked['startMonth'];
				$final['date_single_day']		= $cooked['startDay'];
				$final['date_single_year']		= $cooked['startYear'];
				break;
			case 'range':
				$final['dateRange_reoccuring']	= ( $cooked['reoccuring'] == TRUE ) ? TRUE : FALSE;
				$final['date_start_month']		= $cooked['startMonth'];
				$final['date_start_day']		= $cooked['startDay'];
				$final['date_start_year']		= $cooked['startYear'];
				$final['date_end_month']		= $cooked['endMonth'];
				$final['date_end_day']			= $cooked['endDay'];
				$final['date_end_year']			= $cooked['endYear'];
				break;
		endswitch;			

		return $final;
	}
	
	public static function getClosingsList()
	# get a list of all of the cllsings. Return NULL on no closings
	# otherwise, return an array with the closing information
	{
		global $wpdb;
		$weekArr = self::getWeekArray();
		
		$table_name = $wpdb->prefix . "bookaroom_closings";
		$sql = "SELECT `closingID`, `reoccuring`, `type`, `startDay`, `startMonth`, `startYear`, `endDay`, `endMonth`, `endYear`, `spWeek`, `spDay`, `spMonth`, `spYear`, `closingName`, `allClosed`, `roomsClosed` FROM `$table_name` ";
		
		$count = 0;
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		if( count( $cooked ) == 0 ):
			return NULL;
		endif;
		
		foreach( array( 'live', 'expired' ) as $status ):
			$final['times'][$status] = array();
			$final[$status] = array();
		endforeach;
		
		foreach( $cooked as $key => $val ):
			
			switch( $val['type'] ):
				case 'date':
					$val['startYear'] = ( $val['reoccuring'] == TRUE ) ? date( 'Y' ) : $val['startYear'];
					$val['startTime'] = mktime( 0, 0, 0, $val['startMonth'], $val['startDay'], $val['startYear'] );					
					
					break;
				
				case 'range':
					$val['startYear'] = ( $val['reoccuring'] == TRUE ) ? date( 'Y' ) : $val['startYear'];
					$val['endYear'] = ( $val['reoccuring'] == TRUE ) ? date( 'Y' ) : $val['endYear'];
					
					$val['startTime'] = mktime( 0, 0, 0, $val['startMonth'], $val['startDay'], $val['startYear'] );
					$val['endTime'] = mktime( 23, 59, 59, $val['endMonth'], $val['endDay'], $val['endYear'] );
					
					break;
				/*
				case 'special':
				
					$val['spYear'] = ( $val['reoccuring'] == TRUE ) ? date( 'Y' ) : $val['spYear'];
					$dayOfWeek = $weekArr['day'][$val['spDay']];
					$val['startTime'] = strtotime( "{$weekArr['week'][$val['spWeek']]} {$weekArr['day'][$val['spDay']]} of {$weekArr['month'][$val['spMonth']]} {$val['spYear']}" );
					break;
				*/	
			endswitch;
			$val['niceStartTime'] = date('l, F jS, Y', $val['startTime'] );	
			
			$type = ( $val['reoccuring'] == FALSE && $val['startTime'] < time() ) ? 'expired' : 'live';

			# rooms
			
			$final[$type][(string)$val['closingID']] = $val;
			
		endforeach;
		# sort

		foreach( array( 'live', 'expired' ) as $status ):
			$times = array();

			if( empty( $final[$status] ) ):

				continue;
			endif;
			
			foreach( $final[$status] as $key => $val ):
				$times[$key] = $val['startTime'];
			endforeach;
			asort( $times );
			$final['times'][$status] = $times;
		endforeach;
		
		return $final;
 	}
	
	protected static function getExternalsClosings()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'				=> FILTER_SANITIZE_STRING, 
							'closingID'				=> FILTER_SANITIZE_STRING,);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = $getTemp;
		# setup POST variables
		$postArr = array(	'action'				=> FILTER_SANITIZE_STRING,
							'closingID'				=> FILTER_SANITIZE_STRING,
							'closingName'			=> FILTER_SANITIZE_STRING,
							'roomType'				=> FILTER_SANITIZE_STRING,
							'date_single_month'		=> FILTER_SANITIZE_STRING,
							'date_single_day'		=> FILTER_SANITIZE_STRING,
							'date_single_year'		=> FILTER_SANITIZE_STRING,
							'selType'				=> FILTER_SANITIZE_STRING,
							'date_start_month'		=> FILTER_SANITIZE_STRING,
							'date_start_day'		=> FILTER_SANITIZE_STRING,
							'date_start_year'		=> FILTER_SANITIZE_STRING,
							'date_end_month'		=> FILTER_SANITIZE_STRING,
							'date_end_day'			=> FILTER_SANITIZE_STRING,
							'date_end_year'			=> FILTER_SANITIZE_STRING,
							'date_reoccuring'		=> FILTER_SANITIZE_STRING,
							'dateRange_reoccuring'	=> FILTER_SANITIZE_STRING,
							'special_reoccuring'	=> FILTER_SANITIZE_STRING,
							'specialWeek'			=> FILTER_SANITIZE_STRING,
							'specialDay'			=> FILTER_SANITIZE_STRING,
							'specialMonth'			=> FILTER_SANITIZE_STRING,
							'specialYear'			=> FILTER_SANITIZE_STRING,
							'roomChecked'			=> array(	'filter'    => FILTER_SANITIZE_STRING,
                           										'flags'     => FILTER_REQUIRE_ARRAY ),
							'closingMulti'			=> array(	'filter'    => FILTER_SANITIZE_STRING,
                           										'flags'     => FILTER_REQUIRE_ARRAY ) );
	
	
	
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) )
			$final = array_merge( $final, $postTemp );

		$rooms = array();
		# check rooms
		if( !empty( $final['roomChecked'] ) ):
			$rooms = array();
			foreach( $final['roomChecked'] as $key => $val ):
				if( substr( $key, 0, 5 ) == 'room_' ):
					$rooms[] = substr( $key, 5 );
				endif;
			endforeach;
		endif;
		
		# check multiple closings
		$closingMulti = array();
		if( !empty( $final['closingMulti'] ) ):
			$closingMulti = $final['closingMulti'];
		endif;
		
		unset( $final['roomChecked'] );
		
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
		
		$final['rooms'] = $rooms;
		$final['closingMulti'] = $closingMulti;
		
		return $final;
	}
	
	protected static function getWeekArray()
	# return a list of common date related things.
	# day = key(1-7) = val('Sunday' - 'Satuday')
	# week = key(1-4) = val('First' - 'Fourth')
	# month = key(1-12) - val = month names
	{
		
		return array( 'day' => array( 1 => 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), 'week' => array( 1 => 'First', 'Second', 'Third', 'Fourth' ), 'month' => array( 1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July ', 'August', 'September', 'October', 'November', 'December') );
	}
	
	protected static function makeRooms( $roomSer )
	# make a clean list of rooms (by branch) from the list of room IDs
	{
		# check if empty list
		if( empty( $roomSer ) ):
			return 'Error!';
		endif;
		
		# unserialize and check for empty array
		$roomArr = unserialize( $roomSer );
		
		if( empty( $roomArr ) ):
			return 'No rooms!';
		endif;
		
		$roomList		= bookaroom_settings_rooms::getRoomList();
		$branchList		= bookaroom_settings_branches::getBranchList();
		
		# check for valid rooms

		$finalArr = array_intersect( $roomArr, array_keys( $roomList['id'] ) );
		if( empty( $roomArr ) ):
			return 'No rooms!';
		endif;
		
		# make nice list
		$temp = array();
		$branches = array();
		$final = array();
		
		foreach( $finalArr as $key => $val ):
			$temp[$branchList[$roomList['id'][$val]['branch']]][] = $roomList['id'][$val]['desc'];
		endforeach;
		
		# clean branch list
		foreach( $temp as $key => $val ):
			$bigTemp = '<strong>'.$key.'</strong><br />';
			$bigTemp .= implode( '<br />', $val );
			$final[] = $bigTemp;
		endforeach;


		return implode( '<br />', $final );
	}
	
	protected static function roomTypeDropDown( $closingInfo, $contents )
	# make a drop down for closed room type - all rooms or choose
	{
		#
		$goodTypes = array( NULL => 'Room Closing Type', 'all' => 'All rooms', 'choose' => 'Choose rooms' );
		
		$roomType_line = repCon( 'roomType', $contents );
		
		$temp = NULL;
		$final = array();
		
		foreach( $goodTypes as $key => $val ):
			$temp = $roomType_line;
			
			$selected = ( $closingInfo['roomType'] == $key ) ? ' selected="selected"' : NULL;
			
			$temp = str_replace( '#roomTypeVal#', $key, $temp );
			$temp = str_replace( '#roomTypeDesc#', $val, $temp );
			$temp = str_replace( '#roomTypeSelected#', $selected, $temp );
			
			
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#roomType_line#', implode( "\r\n", $final ), $contents );
		
		return $contents;
	}
	
	protected static function showClosings( $closings, $roomContList, $roomList, $branchList, $amenityList, $multiError = NULL )
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/closings/mainAdmin.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# errors
		# setup lines
		$contents = self::doClosings( $closings['live'], $closings['times']['live'], 'Closings', $contents );
		$contents = self::doClosings( $closings['expired'], $closings['times']['expired'], 'Expired', $contents );
		
		# multi error
		$multiError_line = repCon( 'multiError', $contents );
			
		if( empty( $multiError ) ):
			$contents = str_replace( '#multiError_line#', NULL, $contents );
		else:
			$contents = str_replace( '#multiError_line#', $multiError_line, $contents );
			$contents = str_replace( '#multiError#', $multiError, $contents );
			
		endif;
		echo $contents;
	}
	
	protected static function showClosingsEdit( $closingInfo, $roomList, $branchList, $action, $actionName  )
	{
		$filename = BOOKAROOM_PATH . 'templates/closings/edit.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );	
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$weekArr = self::getWeekArray();

		# handle error line
		$error_line = repCon( 'error', $contents );
		if( empty( $closingInfo['errors'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );	
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMsg#', $closingInfo['errors'], $contents);	
		endif;
		
		# simple replaces
		$contents = str_replace( "#closingName#", $closingInfo['closingName'], $contents );
		$contents = str_replace( "#closingID#", $closingInfo['closingID'], $contents );
		$contents = str_replace( "#action#", $action, $contents );
		$contents = str_replace( "#actionName#", $actionName, $contents );
		
		# drop downs
		$contents = self::roomTypeDropDown( $closingInfo, $contents );
		$contents = self::dateDropDown( $closingInfo, 'date_single', $contents );
		$contents = self::dateDropDown( $closingInfo, 'date_start', $contents );
		$contents = self::dateDropDown( $closingInfo, 'date_end', $contents );
		$contents = self::specialDropDown( $closingInfo, $contents );

		# reoccuring
		foreach( array( 'date_reoccuring', 'dateRange_reoccuring', 'special_reoccuring' ) as $val ):
			if( $closingInfo[$val] == TRUE ):
				$contents = str_replace( "#{$val}Checked#", ' checked="checked"', $contents );				
			else:
				$contents = str_replace( "#{$val}Checked#", NULL, $contents );
			endif;
		endforeach;

		# select type
		$type_line = repCon( 'type', $contents );
		# with special
		# foreach( array( NULL => 'Choose One', 'date' => 'Date', 'range' => 'Range', 'special' => 'Special' ) as $key => $val ):
		foreach( array( NULL => 'Choose One', 'date' => 'Date', 'range' => 'Range') as $key => $val ):
			$temp = $type_line;
			
			$temp = str_replace( '#typeVal#', $key, $temp );
			$temp = str_replace( '#typeDesc#', $val, $temp );
			
			if( $closingInfo['selType'] == $key ):
				$temp = str_replace( '#typeSelected#', ' selected="selected"', $temp);
			else:
				$temp = str_replace( '#typeSelected#', NULL, $temp);
			endif;
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#type_line#', implode( "\r\n", $final ), $contents );
		
		# room list
		$final = array();
		$branchList_line = repCon( 'branchList', $contents );
		$selRoom_line = repCon( 'selRoom', $branchList_line );
		$init_open = array();
		foreach( $branchList as $key => $val ):
			$init_open[] = "\"branch_{$key}\"";
			#branch 
			$tempBranch = $branchList_line;
			
			$tempBranch = str_replace( '#branch_desc#', $val, $tempBranch );
			$tempBranch = str_replace( '#branch_id#', $key, $tempBranch );
			
			# rooms
			$finalRoom = array();
			foreach( $roomList['room'][$key] as $key2 => $val2 ):
				$tempRoomEach = $selRoom_line;

				$tempRoomEach = str_replace( '#selRoom_desc#', $roomList['id'][$key2]['desc'], $tempRoomEach );
				$tempRoomEach = str_replace( '#room_id#', $key2, $tempRoomEach );
				$finalRoom[] = $tempRoomEach;
			endforeach;
			$final[] = str_replace( '#selRoom_line#', implode( $finalRoom ), $tempBranch );
		endforeach;
			
		$contents = str_replace( '#branchList_line#', implode( "\r\n", $final ), $contents );
		$contents = str_replace( '#init_open#', implode( ", ", $init_open ), $contents );
		
		# rooms checked
		$preChecked_line = repCon( 'preChecked', $contents );
		if( empty( $closingInfo['rooms'] ) or count( $closingInfo['rooms'] ) < 1 ):
			$contents = str_replace( '#preChecked_line#', NULL, $contents );
		else:
			$temp = NULL;
			$final = array();
			
			foreach( $closingInfo['rooms'] as $key => $val ):
				$temp = $preChecked_line;
				$temp = str_replace( '#preChecked_id#', $val, $temp );
				$final[] = $temp;	
			endforeach;
			$contents = str_replace( '#preChecked_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		echo $contents;
	}
	
	protected static function showClosingsDelete( $closingInfo, $roomList, $branchList )
	# show the imfo and option to permanantly delete a closing
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/closings/delete.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# simple replace
		$contents = str_replace( '#closingID#', $closingInfo['closingID'], $contents );
		$contents = str_replace( '#desc#', $closingInfo['closingName'], $contents );
		$contents = str_replace( '#selType#', ucfirst( $closingInfo['selType'] ), $contents );
		
		
		#date				
		$reoccuring = 'No';
		switch( $closingInfo['selType'] ):
			case 'range':
			if( $closingInfo['dateRange_reoccuring'] == TRUE ):
					$dateDesc = 'D, M jS';
					$year = date('y');
					$reoccuring = 'Yes';					
				else:
					$dateDesc = 'D, M jS Y';
					$year = $closingInfo['date_end_year'];
				endif;
				$startTime = mktime( 1,1,1, $closingInfo['date_start_month'], $closingInfo['date_start_day'], $year );
				$endTime = mktime( 1,1,1, $closingInfo['date_end_month'], $closingInfo['date_end_day'], $year);

				$date = date( $dateDesc, $startTime ).' to <br />'.date( $dateDesc, $endTime );

				break;
				
			case 'date':
				if( $closingInfo['date_reoccuring'] == TRUE ):
					$dateDesc = 'D, M jS';
					$year = date('y');
					$reoccuring = 'Yes';
				else:
					$dateDesc = 'D, M jS Y';
					$year = $closingInfo['date_single_year'];
				endif;			
				$startTime = mktime( 1,1,1, $closingInfo['date_single_month'], $closingInfo['date_single_day'], $year );
				$date = date( $dateDesc, $startTime );
				break;
				
			default:
				$date = "Unknown date type: {$closingInfo['type']}";
				break;						
		endswitch;
		
		$contents = str_replace( '#date#', $date, $contents );
		
		#rooms 
		switch( $closingInfo['roomType'] ):
			case 'all':
				$rooms = 'All';
				break;

			case 'choose':
				$rooms = self::makeRooms( serialize( $closingInfo['rooms'] ) );
				break;
		endswitch;
			
		$contents = str_replace( '#rooms#', $rooms, $contents );
		
		$contents = str_replace( '#reoccuring#', $reoccuring, $contents );
		
		echo $contents;
	}
	
	protected static function showDeleteMulti( $multiList, $closings, $roomContList, $roomList, $branchList, $amenityList )
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/closings/deleteMulti.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$multiClosing_line = repCon( 'multiClosing', $contents );
		
		$temp = NULL;
		$final = array();
		$count = 1;
		
		
		
		foreach( $multiList as $key => $val ):
			$temp = $multiClosing_line;
			
			$bg_class = ( $count++ % 2 ) ? 'even_row_no' : 'odd_row_no';
			$temp = str_replace( '#bg_class#', $bg_class, $temp );

			$temp = str_replace( '#closingID#', $closings['expired'][$val]['closingID'], $temp );
			$temp = str_replace( '#closingName#', $closings['expired'][$val]['closingName'], $temp );
			$temp = str_replace( '#type#', ucfirst( $closings['expired'][$val]['type'] ), $temp );
			

			# date and eoccuring
			
			switch( $closings['expired'][$val]['type'] ):
				case 'date':
					$dateStamp = mktime( 0, 0, 0,  $closings['expired'][$val]['startMonth'],  $closings['expired'][$val]['startDay'], $closings['expired'][$val]['startYear'] );	
					$dateDisp =  ( $closings['expired'][$val]['reoccuring'] == TRUE ) ? date( 'D, M jS', $dateStamp ) : date( 'D, M jS Y', $dateStamp );
					
					break;
				
				case 'range':
					$startStamp = mktime( 0, 0, 0,  $closings['expired'][$val]['startMonth'],  $closings['expired'][$val]['startDay'],  $closings['expired'][$val]['startYear'] );
					$endStamp = mktime( 23, 59, 59,  $closings['expired'][$val]['endMonth'],  $closings['expired'][$val]['endDay'], $closings['expired'][$val]['endYear'] );
					$startTime =  ( $closings['expired'][$val]['reoccuring'] == TRUE ) ? date( 'D, M jS', $startStamp ) : date( 'D, M jS Y', $startStamp );
					$endTime =  ( $closings['expired'][$val]['reoccuring'] == TRUE ) ? date( 'D, M jS', $endStamp ) : date( 'D, M jS Y', $endStamp );
					$dateDisp = $startTime.' to <br />'.$endTime;
					break;
					
				default:
					die( 'Wrong date type in meeting-room-closings > showDeleteMulti['.$closings['expired'][$val]['type'].']' );
					break;
			endswitch;
			
			$temp = str_replace( '#date#', $dateDisp, $temp );
			
			# rooms
			if( $closings['expired'][$val]['allClosed'] == TRUE ):
				$rooms = 'All';
			else:
				$rooms = self::makeRooms( $closings['expired'][$val]['roomsClosed'] );
			endif;
			
			$temp = str_replace( '#rooms#', $rooms, $temp );
						
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#multiClosing_line#', implode( "\r\n", $final ), $contents );
		
		echo $contents;
	}
	
	protected static function specialDropDown( $extVals, $contents, $yearsAhead = 4 )
	# make date drop down
	{
		$weekArr = self::getWeekArray();
		
		# month
		$monthLine = repCon( "specialMonth", $contents );
		$temp = NULL;
		$final = array();
		$weekArr['month'] = array( NULL =>'Month' ) + $weekArr['month'];
		foreach( $weekArr['month'] as $key => $val ):
			$temp = $monthLine;
			$selected = ( $extVals["specialMonth"] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( "#specialMonth_disp#", $val, $temp );
			$temp = str_replace( "#specialMonth_val#", $key, $temp );
			$temp = str_replace( "#specialMonth_selected#", $selected, $temp );
			$final[] = $temp;
		endforeach;
		$contents = str_replace( "#specialMonth_line#", implode( "\r\n", $final ), $contents );

		# day
		$dayLine = repCon( "specialDay", $contents );
		$temp = NULL;
		$final = array();
		$weekArr['day'] = array( NULL =>'Day' ) + $weekArr['day'];
		foreach( $weekArr['day'] as $key => $val ):
			$temp = $dayLine;
			$selected = ( $extVals["specialDay"] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( "#specialDay_disp#", $val, $temp );
			$temp = str_replace( "#specialDay_val#", $key, $temp );
			$temp = str_replace( "#specialDay_selected#", $selected, $temp );
			$final[] = $temp;
		endforeach;
		$contents = str_replace( "#specialDay_line#", implode( "\r\n", $final ), $contents );
	
		# week
		$weekLine = repCon( "specialWeek", $contents );
		$temp = NULL;
		$final = array();
		$weekArr['week'] = array( NULL =>'week' ) + $weekArr['week'];
		foreach( $weekArr['week'] as $key => $val ):
			$temp = $weekLine;
			$selected = ( $extVals["specialWeek"] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( "#specialWeek_disp#", $val, $temp );
			$temp = str_replace( "#specialWeek_val#", $key, $temp );
			$temp = str_replace( "#specialWeek_selected#", $selected, $temp );
			$final[] = $temp;
		endforeach;
		$contents = str_replace( "#specialWeek_line#", implode( "\r\n", $final ), $contents );
		
		# year
		$yearLine = repCon( "specialYear", $contents );
		$temp = NULL;
		$final = array();
		$yearArr = array( NULL =>'Year' );
		$thisYear = date( 'Y' );
		for( $y=$thisYear; $y <= $yearsAhead + $thisYear; $y++ ):

			$yearArr[$y] = $y;
		endfor;
		foreach( $yearArr as $key => $val ):
			$temp = $yearLine;
			$selected = ( $extVals["specialYear"] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( "#specialYear_disp#", $val, $temp );
			$temp = str_replace( "#specialYear_val#", $key, $temp );
			$temp = str_replace( "#specialYear_selected#", $selected, $temp );
			$final[] = $temp;
		endforeach;
		$contents = str_replace( "#specialYear_line#", implode( "\r\n", $final ), $contents );	
		
		return $contents;
	}	
}
?>