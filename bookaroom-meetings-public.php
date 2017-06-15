<?PHP
class bookaroom_public
# main settings functions
{
	# main funcion - initialize and search for action variable.
	# if no action, return the regular content
	public static function form( $val ){
		$calendarPage = get_option( 'bookaroom_reservation_URL' );
		$perms = get_option('permalink_structure');
		
		$myURLRaw = parse_url( $_SERVER['REQUEST_URI'] );
		$myURL = $myURLRaw['path'];
		if( $perms ):
			if( $myURL !== $calendarPage ):
				return $val;
			endif;
		else:
			parse_str( $myURLRaw['query'], $queryVals );
			if( $queryVals['page_id'] !== $calendarPage ):
				return $val;
			endif;
		endif;
	}
	
	public static function mainForm()
	{
		# get external variables from GET and POST
		$externals = self::getExternalsPublic();
		
		# includes
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-closings.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-cityManagement.php' );
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList( true );
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE, TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		$realAmenityList = bookaroom_settings_amenities::getAmenityList( true );
		$cityList = bookaroom_settings_cityManagement::getCityList( );
		
		# check action
		switch( $externals['action'] ):
			case 'checkForm':
			
				if( ( $errorMSG = self::showForm_checkHoursError( $externals['startTime'], $externals['endTime'], $externals['roomID'], $roomContList, $branchList ) ) == TRUE ):
					return self::showForm_hoursError( $errorMSG, $externals );
				elseif( FALSE !== ( $errorArr = self::showForm_checkErrors( $externals, $branchList, $roomContList, $roomList, $amenityList, $cityList ) ) ):
					return self::showForm_publicRequest( $externals['roomID'], $branchList, $roomContList, $roomList, $realAmenityList, $cityList, $externals, $errorArr );
				else:
					self::sendAlertEmail( $externals, $amenityList, $roomContList, $branchList );
					self::showForm_insertNewRequest( $externals, NULL, $cityList );
					return self::sendCustomerReceiptEmail( $externals, $amenityList, $roomContList, $branchList );
					
				endif;

				break;
			
			case 'fillForm':
				$externals['action'] = 'checkForm';
				# setup times
				$hoursList = array_filter( $externals['hours'], 'strlen' );
				$baseIncrement = get_option( 'bookaroom_baseIncrement' );
				$externals['startTime'] = current( $hoursList );
				$externals['endTime'] = end( $hoursList ) + ( $baseIncrement * 60 );
				
				# check for SESSION variables
				
				#$externals['contactState'] = 'OH';
				$externals['contactState'] = get_option( 'bookaroom_defaultState_name' );				
				$externals['nonProfit'] = FALSE;
				
				if( !empty( $_SESSION['savedMeetingFormVals']['sessionGo'] ) ):
					self::getSession( $externals );
				endif;
				
				if( ( $errorMSG = self::showForm_checkHoursError( $externals['startTime'], $externals['endTime'], $externals['roomID'], $roomContList, $branchList ) ) == TRUE ):
					return self::showForm_hoursError( $errorMSG, $externals );
				else:
					return self::showForm_publicRequest( $externals['roomID'], $branchList, $roomContList, $roomList, $realAmenityList, $cityList, $externals );
				endif;
				break;
				
			case 'calDate':
				$timestamp = self::makeTimestamp( $externals );
				return self::showRooms_day( $externals['roomID'], $externals['branchID'], $timestamp, $branchList, $roomContList, $roomList, $amenityList, $cityList );
				break;
			
			case 'reserve':
				return self::showRooms_day( $externals['roomID'], $externals['branchID'], $externals['timestamp'], $branchList, $roomContList, $roomList, $amenityList, $cityList );
				
				break;
					
			default:
				return self::showRooms( $branchList, $roomContList, $roomList, $amenityList );
				break;
		endswitch;
	}
	
	protected static function getExternalsPublic()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'				=> FILTER_SANITIZE_STRING,
							'roomID'				=> FILTER_SANITIZE_STRING, 
							'branchID'				=> FILTER_SANITIZE_STRING,
							'calMonth'				=> FILTER_SANITIZE_STRING,
							'calYear'				=> FILTER_SANITIZE_STRING,	
							'timestamp'				=> FILTER_SANITIZE_STRING,
							'resID'					=> FILTER_SANITIZE_STRING, 
							'hash'					=> FILTER_SANITIZE_STRING, );
		
		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = $getTemp;
		# setup POST variables
		$postArr = array(	'action'					=> FILTER_SANITIZE_STRING, 
							'amenity' 					=> array(	'filter' => FILTER_SANITIZE_STRING,
																	'flags'  => FILTER_FORCE_ARRAY ),
							'contactAddress1'			=> FILTER_SANITIZE_STRING, 
							'contactAddress2'			=> FILTER_SANITIZE_STRING, 
							'contactCity'				=> FILTER_SANITIZE_STRING, 
							'contactCityDrop'			=> FILTER_SANITIZE_STRING, 
							'contactEmail'				=> FILTER_SANITIZE_STRING, 
							'contactName'				=> FILTER_SANITIZE_STRING, 
							'contactPhonePrimary'		=> FILTER_SANITIZE_STRING, 
							'contactPhoneSecondary'		=> FILTER_SANITIZE_STRING, 
							'contactState'				=> FILTER_SANITIZE_STRING, 
							'contactWebsite'			=> FILTER_SANITIZE_STRING, 
							'contactZip'				=> FILTER_SANITIZE_STRING, 
							'desc'						=> FILTER_SANITIZE_STRING, 
							'endTime'					=> FILTER_SANITIZE_STRING, 
							'eventName'					=> FILTER_SANITIZE_STRING, 
							'hours' 					=> array(	'filter' => FILTER_SANITIZE_STRING,
																	'flags'  => FILTER_FORCE_ARRAY ),
							'libcardNum'				=> FILTER_SANITIZE_STRING, 
							'nonProfit'					=> FILTER_SANITIZE_STRING, 
							'notes'						=> FILTER_SANITIZE_STRING, 
							'numAttend'					=> FILTER_SANITIZE_STRING, 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'session'					=> FILTER_SANITIZE_STRING, 
							'social'					=> FILTER_SANITIZE_STRING, 
							'startTime'					=> FILTER_SANITIZE_STRING, 
							'timestamp'					=> FILTER_SANITIZE_STRING );
	
	
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) )
			$final = array_merge( $final, $postTemp );
		
		$arrayCheck = array_unique( array_merge( array_keys( $getArr ), array_keys( $postArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			elseif( is_array( $final[$key] ) ):
				$final[$key] = $final[$key];
			else:			
				$final[$key] = trim( $final[$key] );
			endif;
		endforeach;
		
		return $final;
	}
	
	public static function getClosings( $roomID, $timestamp, $roomContList )
	{
		global $wpdb;
		# create date information
		$dateInfo = getdate( $timestamp );
		
		$table_name = $wpdb->prefix . "bookaroom_closings";
		
		$sql = "SELECT `allClosed` , `roomsClosed`, `closingName` 
					FROM `{$table_name}` 
					WHERE 
(
					`type` = 'range' AND
				 	`reoccuring` = '0' AND 
UNIX_TIMESTAMP( CONCAT_WS( '-', CAST( startYear AS CHAR ), LPAD( CAST( startMonth AS CHAR ), 2, '00'), LPAD( CAST( startDay AS CHAR ), 2, '00') ) ) <= UNIX_TIMESTAMP( CONCAT_WS( '-', CAST( '{$dateInfo['year']}' AS CHAR ), LPAD( CAST( '{$dateInfo['mon']}' AS CHAR ), 2, '00'), LPAD( CAST( '{$dateInfo['mday']}' AS CHAR ), 2, '00') ) ) AND
UNIX_TIMESTAMP( CONCAT_WS( '-', CAST( endYear AS CHAR ), LPAD( CAST( endMonth AS CHAR ), 2, '00'), LPAD( CAST( endDay AS CHAR ), 2, '00') ) ) >=
UNIX_TIMESTAMP( CONCAT_WS( '-', CAST( '{$dateInfo['year']}' AS CHAR ), LPAD( CAST( '{$dateInfo['mon']}' AS CHAR ), 2, '00'), LPAD( CAST( '{$dateInfo['mday']}' AS CHAR ), 2, '00') ) ) 
					) OR 					
					(	
								`type` = 'date' AND
							 	`reoccuring` = '0' AND 
								`startDay` = '{$dateInfo['mday']}' AND 
								`startMonth` = '{$dateInfo['mon']}' AND 
								`startYear` = '{$dateInfo['year']}') OR 
					(`type` = 'date' AND `reoccuring` ='1' AND `startDay` = '{$dateInfo['mday']}' AND `startMonth` = '{$dateInfo['mon']}') OR 
					(`type` = 'range' AND `reoccuring` ='0' AND ('{$dateInfo['mday']}' >= `startDay` AND '{$dateInfo['mday']}' <= `endDay`) AND ('{$dateInfo['mon']}' >= `startMonth` AND '{$dateInfo['mon']}' <= `endMonth`) AND ('{$dateInfo['year']}' >= `startYear` AND '{$dateInfo['year']}' <= `endYear`) ) OR
(`type` = 'range' AND `reoccuring` ='1' AND ('{$dateInfo['mday']}' >= `startDay` AND '{$dateInfo['mday']}' <= `endDay`) AND ('{$dateInfo['mon']}' >= `startMonth` AND '{$dateInfo['mon']}' <= `endMonth`))";
		
		$raw = $wpdb->get_results( $sql, ARRAY_A );
		foreach( $raw as $key => $val ):
			if( $val['allClosed'] == '1' ):
				return $val['closingName'];
			endif;
			
			$rooms = unserialize( $val['roomsClosed'] );
			if( count( array_intersect( $roomContList['id'][$roomID]['rooms'], $rooms ) ) !== 0 ):
				return $val['closingName'];
			endif;
		endforeach;
		
		return false;
	}
	
	
	public static function getReservations( $roomID, $timestamp, $timeEnd = NULL, $res_id = NULL )
	# use timestamp only to get all day's reservation.
	# use timestamp as start time and timeEnd as end time to find reservations in a certain range.
	{
		global $wpdb;
		
		if( empty( $timeEnd ) ):
			$timeInfo = getdate( $timestamp );
		
			$startTime		= date( 'Y-m-d H:i:s', mktime( 0, 0, 0, $timeInfo['mon'], $timeInfo['mday'], $timeInfo['year'] ) );
			$endTime		= date( 'Y-m-d H:i:s', mktime( 0, 0, 0, $timeInfo['mon'], $timeInfo['mday']+1, $timeInfo['year'] ) - 1 );
		else:
			$increment	= get_option( 'bookaroom_baseIncrement' );
			$setupInc	= get_option( 'bookaroom_setupIncrement' );			
			$cleanupInc	= get_option( 'bookaroom_cleanupIncrement' );
			$startTime		= date( 'Y-m-d H:i:s', $timestamp + ($increment * 60 * $setupInc) );
			$endTime		= date( 'Y-m-d H:i:s', $timeEnd + ($increment * 60 * $cleanupInc) );
		endif;
		
		if( !empty( $res_id ) ):
			$where = " AND `res`.`res_id` != '{$res_id}'";
		else:
			$where = NULL;
		endif;
			
		$sql = "SELECT `res`.*, `ti`.* FROM `{$wpdb->prefix}bookaroom_times` as `ti` 
				LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` as `res` ON `ti`.`ti_extID` = `res`.`res_id` 
				WHERE ( `ti`.`ti_startTime`< '{$endTime}' AND `ti`.`ti_endTime` > '{$startTime}' )
				AND `ti`.`ti_roomID` IN (
					SELECT DISTINCT `roomMembers`.`rcm_roomContID` 
					FROM `{$wpdb->prefix}bookaroom_roomConts_members` as `roomMembers` 
					WHERE 	( ( `ti`.`ti_type` = 'meeting' AND ( `res`.me_status != 'archived' and `res`.`me_status` != 'denied')  ) OR 
							( `ti`.`ti_type` = 'event') )	AND 
					`rcm_roomID` IN ( 
						SELECT `roomMembers`.`rcm_roomID` FROM 					
						`{$wpdb->prefix}bookaroom_roomConts_members` as `roomMembers` 
						WHERE rcm_roomContID = '{$roomID}'{$where} ) );";
		$final = $wpdb->get_results( $sql, ARRAY_A );
		
		
		if( get_option('bookaroom_obfuscatePublicNames') == true ):
			foreach( $final as $key => &$val ):
				if( $val['ti_type'] !== 'event' ):
					$val['me_eventName'] = "In use";
				endif;
			endforeach;
		endif;
		return $final;
	}
	
	protected static function getSession( &$externals )
	{
		$valArr = array( 'action', 'amenity', 'contactAddress1', 'contactAddress2', 'contactCity', 'contactEmail', 'contactName', 'contactPhonePrimary', 'contactPhoneSecondary', 'contactState', 'contactWebsite', 'contactZip', 'desc', 'notes', 'eventName', 'nonProfit', 'numAttend', 'roomID' );
		
		foreach( $valArr as $key ):
			if(!empty( $_SESSION['savedMeetingFormVals'][$key] ) ):
				$externals[$key] = $_SESSION['savedMeetingFormVals'][$key];
			else:
				$externals[$key] = NULL;
			endif;
		endforeach;
		
		$_SESSION['savedMeetingFormVals']['sessionGo'] = FALSE;
		
		return $externals;
	}
	
	public static function getStates()
	{
		return array(	'AL'=>"Alabama", 'AK'=>"Alaska", 'AZ'=>"Arizona", 'AR'=>"Arkansas", 'CA'=>"California", 'CO'=>"Colorado", 'CT'=>"Connecticut", 'DE'=>"Delaware", 'DC'=>"District Of Columbia", 'FL'=>"Florida", 'GA'=>"Georgia", 'HI'=>"Hawaii", 'ID'=>"Idaho", 'IL'=>"Illinois", 'IN'=>"Indiana", 'IA'=>"Iowa", 'KS'=>"Kansas", 'KY'=>"Kentucky", 'LA'=>"Louisiana", 'ME'=>"Maine", 'MD'=>"Maryland", 'MA'=>"Massachusetts", 'MI'=>"Michigan", 'MN'=>"Minnesota", 'MS'=>"Mississippi", 'MO'=>"Missouri", 'MT'=>"Montana", 'NE'=>"Nebraska", 'NV'=>"Nevada", 'NH'=>"New Hampshire", 'NJ'=>"New Jersey", 'NM'=>"New Mexico", 'NY'=>"New York", 'NC'=>"North Carolina", 'ND'=>"North Dakota", 'OH'=>"Ohio", 'OK'=>"Oklahoma", 'OR'=>"Oregon", 'PA'=>"Pennsylvania", 'RI'=>"Rhode Island", 'SC'=>"South Carolina", 'SD'=>"South Dakota", 'TN'=>"Tennessee", 'TX'=>"Texas", 'UT'=>"Utah", 'VT'=>"Vermont", 'VA'=>"Virginia", 'WA'=>"Washington", 'WV'=>"West Virginia", 'WI'=>"Wisconsin", 'WY'=>"Wyoming");
	
	}
	
	protected static function makeTimestamp( $externals )
	{
		$curTimeInfo = getdate( current_time('timestamp') );
		# check for month
		if( empty( $externals['calMonth'] ) || !is_numeric( $externals['calMonth'] ) || ( $externals['calMonth'] < 1 || $externals['calMonth'] > 12 ) ):
			$month = $curTimeInfo['mon'];
		else:
			$month = $externals['calMonth'];
		endif;
		
		# check year
		if( empty( $externals['calYear'] ) || !is_numeric( $externals['calYear'] ) || ( $externals['calYear'] < $curTimeInfo['year']-1 || $externals['calYear'] > $curTimeInfo['year'] +3 ) ):
			$year = $curTimeInfo['year'];
		else:
			$year = $externals['calYear'];
		endif;
		
		$timestamp = mktime( 0, 0, 0, $month, 1, $year );		
		return $timestamp;
		
	}
	
	public static function showForm_checkErrors( &$externals, $branchList, $roomContList, $roomList, $amenityList, $cityList )
	{
		$final = array();
		# library card
		# is social true
		$libcardRegex = get_option( 'bookaroom_libcardRegex' );
		
		if( $externals['social'] ):
			if( empty( $externals['libcardNum'] ) ):
				$final['classes']['libcardNum'] = 'error';
				$final['errorMSG'][] = 'You must enter your <em>Library Card Number</em>.';			
			elseif( !empty( $libcardRegex ) && FALSE == preg_match( $libcardRegex, $externals['libcardNum'] ) ):
				$final['classes']['libcardNum'] = 'error';
				$final['errorMSG'][] = 'You must enter a valid <em>Library Card Number</em>.';			
			endif;
		endif;		
		
		# event name
#		if( empty( $externals['eventName'] ) ):
#			$final['classes']['eventName'] = 'error';
#			$final['errorMSG'][] = 'You must enter an event name in the <em>Event/Organization name</em> field.';
#		endif;
		
		# event name
#		if( empty( $externals['numAttend'] ) ):
#			$final['classes']['numAttend'] = 'error';
#			$final['errorMSG'][] = 'You must enter how many people you expect to attend in <em>Number of attendees</em> field.';
#		elseif( !is_numeric( $externals['numAttend'] )):
#			$final['classes']['numAttend'] = 'error';
#			$final['errorMSG'][] = 'You must enter valid number <em>Number of attendees</em> field.';		
#		elseif( $externals['numAttend'] < 1 || $externals['numAttend'] > $roomContList['id'][$externals['roomID']]['occupancy'] ):
#			$final['classes']['numAttend'] = 'error';
#			$final['errorMSG'][] = "The maximum occupancy of this room is <em>{$roomContList['id'][$externals['roomID']]['occupancy']}</em>. Please enter a valid <em>Number of attendees</em> field.";		
#		endif;
		
		# purposr of meeting (desc)
#		if( empty( $externals['desc'] ) ):
#			$final['classes']['desc'] = 'error';
#			$final['errorMSG'][] = 'You must enter a value in the <em>Purpose of meeting</em> field.';
#		endif;
		
		# contact name
		if( empty( $externals['contactName'] ) ):
			$final['classes']['contactName'] = 'error';
			$final['errorMSG'][] = 'You must enter a value in the <em>Contact name</em> field.';
		endif;
		# clean phone
		#$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['contactPhonePrimary'] );
		#if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
		# contact primary phone
		# ignore if regex is empty
		$phoneRegex = get_option( 'bookaroom_phone_regex' );
		$phoneExample = get_option( 'bookaroom_phone_example' );
	    
		$cleanPhone = str_replace( array( '(', ')', ' ' ), '', $externals['contactPhonePrimary'] );
		if( empty( $cleanPhone ) ):
				$final['classes']['contactPhonePrimary'] = 'error';
				$final['errorMSG'][] = 'You must enter a value in the <em>Primary phone</em> field.';
		elseif( !empty( $phoneRegex ) ):
		# check main phone against regex
			if( !preg_match( $phoneRegex, $cleanPhone ) ):
				$final['classes']['contactPhonePrimary'] = 'error';
				$final['errorMSG'][] = 'You must enter a valid phone number in the <em>Primary phone</em> field. Example: '.$phoneExample;			
			endif;
		endif;
			
		$cleanPhone = str_replace( array( '(', ')', ' ' ), '', $externals['contactPhoneSecondary'] );
		if( !empty( $cleanPhone ) and !empty( $phoneRegex ) ):
		# check main phone against regex
			if( !preg_match( $phoneRegex, $cleanPhone ) ):
				$final['classes']['contactPhoneSecondary'] = 'error';
				$final['errorMSG'][] = 'You must enter a valid phone number in the <em>Secondary phone</em> field. Example: '.$phoneExample;			
			endif;
		endif;
		
		# street address
#		if( empty( $externals['contactAddress1'] ) ):
#			$final['classes']['contactAddress1'] = 'error';
#			$final['errorMSG'][] = 'You must enter a value in the <em>Street Address</em> field.';
#		endif;
		# city
		#
		# first, is social selected
#		if( empty( $externals['social'] ) ):
#			if( empty( $externals['contactCity'] ) ):
#				$final['classes']['contactCity'] = 'error';
#				$final['errorMSG'][] = 'You must enter a value in the <em>City</em> field.';
#			endif;
#		else:
#			if( empty( $externals['contactCityDrop'] )):
#				$final['classes']['contactCity'] = 'error';
#				$final['errorMSG'][] = 'You must choose a <em>City</em> from the drop down.';
#			elseif( $externals['contactCityDrop'] == 'other' ):
#				$final['classes']['contactCity'] = 'error';
#				$final['errorMSG'][] = 'To use our meetings rooms for social events, you have to live in one of the cities listed in the <em>City</em> drop down. Please refer to our guidelines for more information.';
#			elseif( !array_key_exists( $externals['contactCityDrop'], $cityList ) ):
#				$final['classes']['contactCity'] = 'error';
#				$final['errorMSG'][] = 'You must choose a valid <em>City</em> from the drop down.';
#			endif;
#		endif;
		
		# state
#		if( get_option( 'bookaroom_addressType' ) ==  'usa' ):
#			if( empty( $externals['contactState'] ) ):
#				$final['classes']['contactState'] = 'error';
#				$final['errorMSG'][] = 'You must choose an item from the <em>State</em> drop-down.';
#			elseif( !array_key_exists( $externals['contactState'], self::getStates() ) ):
#				$final['classes']['contactState'] = 'error';
#				$final['errorMSG'][] = 'That state is invalid. Please choose a valid item from the <em>State</em> drop-down.';
#			endif;
#		elseif( empty( $externals['contactState'] ) ):
#				$final['classes']['contactState'] = 'error';
#				$stateName = get_option( 'bookaroom_state_name' );
#				$final['errorMSG'][] = "You must enter a value in the <em>{$stateName}</em> drop-down.";
#		endif;
		
		# zip code
#		$zipRegex = get_option( 'bookaroom_zip_regex' );
#		$zipExample = get_option( 'bookaroom_zip_example' );
#		
#		if( empty( $externals['contactZip'] ) ):
#			$final['classes']['contactZip'] = 'error';
#			$final['errorMSG'][] = 'You must enter a value in the <em>Zip code</em> field.';
#		elseif( !empty( $zipRegex ) ):
#			if( !preg_match( $zipRegex, $externals['contactZip'] ) ):
#				$final['classes']['contactZip'] = 'error';
#				$final['errorMSG'][] = 'You must enter a valid postal code. Example: '.$zipExample;
#			endif;
#		endif;
		
		# email address
		if( empty( $externals['contactEmail'] ) ):
			$final['classes']['contactEmail'] = 'error';
			$final['errorMSG'][] = 'You must enter a value in the <em>Email address</em> field.';
		elseif( !filter_var( $externals['contactEmail'], FILTER_VALIDATE_EMAIL ) ):
			$final['classes']['contactEmail'] = 'error';
			$final['errorMSG'][] = 'You must enter a valid address in the <em>Email address</em> field.';
		endif;
		
		# NEW
		if( empty( $final ) ) $final = FALSE;
		return $final;
	}
	
	public static function showForm_checkHoursError( $startTime, $endTime, $roomContID, $roomContList, $branchList, $res_id = NULL )
	{
		global $wpdb;
		$final = FALSE;
		
		# hours bad?
		# check for common logical errors
		################################################################
		$validStart		= strtotime( date( 'Y-m-d' ) ) + ( get_option( 'bookaroom_reserveBuffer' ) * 24 * 60 * 60 );
		$validEnd		= $validStart + ( get_option( 'bookaroom_reserveAllowed' ) * 24 * 60 * 60 );
		
		# find room's settings
		$branchID		= $branchList[$roomContList['id'][$roomContID]['branchID']];
		$dayOfWeek		= date( 'w', $startTime );
		
		$branchStart	= strtotime( date( 'Y-m-d', $startTime).' '.$branchID['branchOpen_'.$dayOfWeek] );
		$branchEnd		= strtotime( date( 'Y-m-d', $endTime).' '.$branchID['branchClose_'.$dayOfWeek] );
		$admin = is_user_logged_in();
		# start or end empty or invalid? 
		if( ( !is_numeric( $startTime ) || !is_numeric( $endTime ) ) || 
		# start or end come before valid start date (using reserveBuffer)?
			( ( $startTime <= $validStart || $endTime <= $validStart ) && empty( $res_id) && $admin == false ) ||
		# start or end come after valid end date (using reserveAllowed)?
			( ( $startTime >= $validEnd || $endTime >= $validEnd ) && empty( $res_id) && $admin == false ) ||
		# start or end come before branch open date?
			( $startTime < $branchStart || $endTime < $branchStart ) ||
		# start or end come after branch close date?
			( $startTime > $branchEnd || $endTime > $branchEnd ) ||
		# start or end date identical?
			( $startTime == $endTime ) ||
		# start come after end date?
			( $startTime > $endTime ) ):
			$final = 'Your start and/or end time is invalid. Please return to the time selection page and try again.';
		endif;
		
		$reservations = self::getReservations( $roomContID, $startTime, $endTime, $res_id );
		
		if( count( $reservations ) !== 0 ):
			$final = 'There is a meeting at that time. Please return to the time selection page and try again.';
		endif;
		
		if( !empty( $final ) && empty( $res_id ) && $admin == false ):
			$final .= '<br />Your form data has been saved. When you choose a new time, your form will retain the information you have already filled out.';
		endif;
		
		return $final;
	}
	
	protected static function showForm_hoursError( $errorMSG, $externals )
	{
		# save values into session data
		$valArr = array( 'action', 'amenity', 'contactAddress1', 'contactAddress2', 'contactCity', 'contactEmail', 'contactName', 'contactPhonePrimary', 'contactPhoneSecondary', 'contactState', 'contactWebsite', 'contactZip', 'desc', 'notes', 'eventName', 'nonProfit', 'numAttend', 'roomID' );
		
		foreach( $valArr as $key ):
			$_SESSION['savedMeetingFormVals'][$key] = $externals[$key];
		endforeach;
		
		$_SESSION['savedMeetingFormVals']['sessionGo'] = TRUE;
		
		$filename = BOOKAROOM_PATH . 'templates/public/error.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		
		$contents = str_replace( '#timestamp#', $externals['startTime'], $contents );
		$contents = str_replace( '#roomID#', $externals['roomID'], $contents );
		
		return $contents;
	}
	
	public static function showForm_insertNewRequest( $externals, $event = NULL, $cityList )
	{
		global $wpdb;
		
		$social = ( !empty( $externals['social'] ) ) ? true : false;
		if( $social == true ):
			$cityName = $cityList[$externals['contactCityDrop']];
		else:
			$cityName = esc_textarea( $externals['contactCity'] );
		endif;
		# nonprofit
		$nonProfit = ( empty(  $externals['nonProfit'] ) ) ? 0 : 1;
		
		if( empty( $externals['amenity'] ) ):
			$amenity = "";
		else:
			$amenity = serialize( $externals['amenity'] );
		endif;
		
		if( !empty( $event ) ):
			$event = TRUE;
			$pending = 'approved';
		else:
			$event = FALSE;
			$pending = 'pending';
		endif;
		
		$table_name = $wpdb->prefix . "bookaroom_reservations";
		$final = $wpdb->insert( $table_name, array( 
			'me_numAttend'				=> $externals['numAttend'],
			'me_eventName'				=> esc_textarea( $externals['eventName'] ),
			'me_desc'					=> esc_textarea( $externals['desc'] ),
			'me_contactName'			=> esc_textarea( $externals['contactName'] ),
			'me_contactPhonePrimary'	=> $externals['contactPhonePrimary'],
			'me_contactPhoneSecondary'	=> $externals['contactPhoneSecondary'],
			'me_contactAddress1'		=> esc_textarea( $externals['contactAddress1'] ),
			'me_contactAddress2'		=> esc_textarea( $externals['contactAddress2'] ),
			'me_contactCity'			=> $cityName, 
			'me_contactState'			=> $externals['contactState'],
			'me_contactZip'				=> $externals['contactZip'],
			'me_contactEmail'			=> esc_textarea( $externals['contactEmail'] ),
			'me_contactWebsite'			=> esc_textarea( $externals['contactWebsite'] ),
			'me_nonProfit'				=> $nonProfit,
			'me_amenity'				=> $amenity, 
			'me_notes'					=> esc_textarea( $externals['notes'] ),
			'me_libcardNum'				=> esc_textarea( $externals['libcardNum'] ),
			'me_social'					=> $social,
			'me_status'					=> $pending ) );
		$table_name = $wpdb->prefix . "bookaroom_times";
		$final = $wpdb->insert( $table_name, array( 
			'ti_startTime'				=> date( 'Y-m-d H:i:s', $externals['startTime'] ),#
			'ti_endTime'				=> date( 'Y-m-d H:i:s', $externals['endTime'] ),
			'ti_roomID'				=> $externals['roomID'], 
			'ti_type'				=> 'meeting', 
			'ti_extID'				=> $wpdb->insert_id ) );
		return TRUE;
	}
	
	public static function showForm_publicRequest( $roomContID, $branchList, $roomContList, $roomList, $amenityList, $cityList, $externals, $errorArr = NULL, $edit = NULL )
	{
		# get template
		if( !empty( $edit ) ):
			$filename = BOOKAROOM_PATH . 'templates/meetings/pending_edit.html';	
		else:
			$filename = BOOKAROOM_PATH . 'templates/public/publicRequest.html';	
		endif;
		
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		if( $edit == true ):
			$contents = str_replace( '#res_id#', $edit, $contents );
		endif;
		
		$contents = str_replace( '#formDate#', date( 'l, F jS, Y', $externals['startTime'] ), $contents );
		$contents = str_replace( '#startTime#', date( 'g:i a', $externals['startTime'] ), $contents );
		$contents = str_replace( '#endTime#', date( 'g:i a', $externals['endTime'] ), $contents );
		
		$contents = str_replace( '#realStartTime#', $externals['startTime'], $contents );
		$contents = str_replace( '#realEndTime#', $externals['endTime'], $contents );
		
		# room and branch name
		$contents = str_replace( '#roomName#', $roomContList['id'][$roomContID]['desc'], $contents );
		$contents = str_replace( '#branchName#', $branchList[$roomContList['id'][$roomContID]['branchID']]['branchDesc'], $contents );
		# notes
		#$contents = str_replace( '#roomName#', $externals['notes'], $contents );
		
		# amenities
		$formAmenitiesSome_line = repCon( 'formAmenitiesSome', $contents );
		$formAmenitiesNone_line = repCon( 'formAmenitiesNone', $contents, TRUE );
		
		$amenitiesCur = array();
		
		foreach( $roomContList['id'][$roomContID]['rooms'] as $key => $val):
			if( !empty( $roomList['id'][$val]['amenity'] ) ):
				$amenitiesCur += $roomList['id'][$val]['amenity'];
			endif;
		endforeach;
		
		$amenitiesCur = array_unique( $amenitiesCur );
		if( empty( $amenitiesCur ) ):
			$contents = str_replace( '#formAmenitiesSome_line#', $formAmenitiesNone_line, $contents );
		else:
			$final = array();
			$temp = NULL;
			if( !array( $externals['amenity'] ) ):
				$externals['amenity'] = array();
			endif;
			foreach( $amenitiesCur as $key => $val ):
				if( !array_key_exists( $val, $amenityList ) ):
					continue;
				endif;
				$temp = $formAmenitiesSome_line;
				$checked = ( !is_array( $externals['amenity'] ) || !in_array( $val, $externals['amenity'] ) ) ? NULL : ' checked="checked"';
				
				$temp = str_replace( '#amenityName#', $amenityList[$val], $temp );
				$temp = str_replace( '#amenityVal#', $val, $temp );
				$temp = str_replace( '#amenityChecked#', $checked, $temp );				
				
				$final[] = $temp;			
			endforeach;
			$contents = str_replace( '#formAmenitiesSome_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		#
		# social
		########################################################
		if(  true == ( @$externals['isSocial'] ) ):
			$ISyesChecked = ' checked="checked"';
			$ISnoChecked = NULL;
		else:
			$ISnoChecked = ' checked="checked"';
			$ISyesChecked = NULL;
		endif;
		$contents = str_replace( '#ISyesChecked#', $ISyesChecked, $contents );
		$contents = str_replace( '#ISnoChecked#', $ISnoChecked, $contents );
		
		######################################################
		#
		# GLOBAL ITEMS 
		#
		######################################################
		$stateDrop_line	= RepCon( 'stateDropCon', $contents );
		$stateText_line	= RepCon( 'stateTextCon', $contents, true );
		
		if( get_option( 'bookaroom_addressType' ) !== 'usa' ):
			$address1_name 	= get_option( 'bookaroom_address1_name');
			$address2_name 	= get_option( 'bookaroom_address2_name');
			$city_name 		= get_option( 'bookaroom_city_name');
			$state_name 	= get_option( 'bookaroom_state_name');
			$zip_name 		= get_option( 'bookaroom_zip_name');
			#
			# city input text
			######################################################
			$contents = str_replace( '#stateDropCon_line#', $stateText_line, $contents );
			$contents = str_replace( '#contactState#', $externals['contactState'], $contents );
			$contents = str_replace( '', null, $contents );
			
		else:
			$contents = str_replace( '#stateDropCon_line#', $stateDrop_line, $contents );
			$address1_name 	= 'Street Address';
			$address2_name 	= 'Address';
			$city_name 		= 'City';			
			$state_name 	= 'State';
			$zip_name 		= 'Zip Code';
			#
			# city drop
			######################################################
			if( empty( $edit ) ):
				$final = array();
				$contactCity_line = repCon( 'contactCityDrop', $contents );
				
				# choose message
				$temp = $contactCity_line;
				#check for selected
				if( empty( $externals['contactCityDrop'] ) ):
					$selected = ' selected="selected"';
				else:
					$selected = NULL;
				endif;
		
				$temp = str_replace( '#contactCityDrop_desc#', 'Choose One', $temp );
				$temp = str_replace( '#contactCityDrop_val#', NULL, $temp );
				$temp = str_replace( '#contactCityDrop_selected#', $selected, $temp );
				$final[] = $temp;
				
				# build city list
				if( empty( $cityList ) ) $cityList = array();
				
				foreach( $cityList as $key => $val ):
					$temp = $contactCity_line;
					if( $externals['contactCityDrop'] == $key ):
						$selected = ' selected="selected"';
					else:
						$selected = NULL;
					endif;
					
					$temp = str_replace( '#contactCityDrop_desc#', $val, $temp );
					$temp = str_replace( '#contactCityDrop_val#', $key, $temp );
					$temp = str_replace( '#contactCityDrop_selected#', $selected, $temp );
					$final[] = $temp;
				
				endforeach;
		
				# other
				$temp = $contactCity_line;
				if( $externals['contactCityDrop'] == 'other' ):
					$selected = ' selected="selected"';
				else:
					$selected = NULL;
				endif;
				
				$temp = str_replace( '#contactCityDrop_desc#', 'Other', $temp );
				$temp = str_replace( '#contactCityDrop_val#', 'other', $temp );
				$temp = str_replace( '#contactCityDrop_selected#', $selected, $temp );
				$final[] = $temp;
				
				#  build it
				$contents = str_replace( '#contactCityDrop_line#', implode( "\r\n", $final ), $contents );
			endif;		
		endif;	
			
		# form names
		$contents = str_replace( '#address1_name#', $address1_name, $contents );
		$contents = str_replace( '#address2_name#', $address2_name, $contents );
		$contents = str_replace( '#city_name#', $city_name, $contents );
		$contents = str_replace( '#state_name#', $state_name, $contents );
		$contents = str_replace( '#zip_name#', $zip_name, $contents );
		#
		# fill form elements
		######################################################
		$goodArr = array( 'action', 'contactAddress1', 'contactAddress2', 'contactCity', 'contactEmail', 'contactName', 'contactPhonePrimary', 'contactPhoneSecondary', 'contactWebsite', 'contactZip', 'desc', 'notes', 'eventName', 'numAttend' );
		
		foreach( $goodArr as $val ):
			$fillVal = empty( $externals[$val] ) ? NULL : htmlspecialchars_decode( $externals[$val] );
			
			$contents = str_replace( "#{$val}#", $fillVal, $contents );
		endforeach;
		$contents = str_replace( '#roomID#', $roomContID, $contents );
		# start and end time
		# 'endTime', 'startTime'
		
		# 'contactState'
		$stateDrop_line = repCon( 'stateDrop', $contents );
		$stateList = self::getStates();
		
		$final = array();
		$temp = NULL;
		
		$selected = ( empty( $externals['contactState'] ) || !array_key_exists( $externals['contactState'], $stateList ) ) ? ' selected="selected"' : NULL;
		$temp = $stateDrop_line;
		$temp = str_replace( '#stateDrop_desc#', 'Choose a state', $temp );
		$temp = str_replace( '#stateDrop_selected#', $selected, $temp );
		$temp = str_replace( '#stateDrop_val#', NULL, $temp );
		
		$final[] = $temp;
			
		foreach( $stateList as $key => $val ):
			$selected = ( !empty( $externals['contactState'] ) && $externals['contactState'] == $key ) ? ' selected="selected"' : NULL;
			$temp = $stateDrop_line;
			$temp = str_replace( '#stateDrop_desc#', $val, $temp );
			$temp = str_replace( '#stateDrop_selected#', $selected, $temp );
			$temp = str_replace( '#stateDrop_val#', $key, $temp );
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#stateDrop_line#', implode( "\r\n", $final ), $contents );
		
		# check boxes
		#############################################################
		
		# 'nonProfit'
		$NPyesChecked = $NPnoChecked = NULL;
				
		if( !empty( $externals['nonProfit'] ) ):
			$NPyesChecked = ' checked="checked"';
		else:
			$NPnoChecked = ' checked="checked"';
		endif;
		
		$contents = str_replace( '#NPyesChecked#', $NPyesChecked, $contents );
		$contents = str_replace( '#NPnoChecked#', $NPnoChecked, $contents );
		
		# 'social'
		$SOyesChecked = $SOnoChecked = NULL;
				
		if( !empty( $externals['social'] ) ):
			$SOyesChecked = ' checked="checked"';
		else:
			$SOnoChecked = ' checked="checked"';
		endif;
		
		$contents = str_replace( '#SOyesChecked#', $SOyesChecked, $contents );
		$contents = str_replace( '#SOnoChecked#', $SOnoChecked, $contents );
		
		# library card number
		############################################
		
		$contents = str_replace( '#libcardNum#', $externals['libcardNum'], $contents );
		
		# error classes
		$errorSome_line = repCon( 'errorSome', $contents );
		if( empty( $errorArr ) ):
			$contents = str_replace( '#errorSome_line#', NULL, $contents );
		else:
			$contents = str_replace( '#errorSome_line#', $errorSome_line, $contents );
			$errorSome_line = repCon( 'errorMsg', $contents );
			# ERROR CLASSES
			array_walk($errorArr['errorMSG'], create_function('&$val', '$val = "<li>$val</li>";' ) );
			$contents = str_replace( '#errorMSG#', implode( "\r\n", $errorArr['errorMSG'] ), $contents );
		endif;
		
		# error classes
		$goodArr = array( 'eventName', 'numAttend', 'desc', 'contactName', 'contactPhonePrimary', 'contactPhoneSecondary', 'contactAddress1', 'contactCity', 'contactState', 'contactZip', 'contactEmail', 'libcardNum' );
			
			$temp = NULL;
			$final = array();
			foreach( $goodArr as $key ):
				if( !empty( $errorArr['classes'][$key] ) ):
					$repVal = 'class="error" ';
				else:
					$repVal = NULL;
				endif;
				
				$contents = str_replace( "#{$key}_class#", $repVal, $contents );
			endforeach;	
			
		return $contents;
		
	}
	
	protected static function showRooms( $branchList, $roomContList, $roomList, $amenityList )
	{
		$filename = BOOKAROOM_PATH . 'templates/public/publicShowRooms.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		if( get_option( "bookaroom_hide_contract" ) == true ):
			$contents = str_replace( '#showContract#', "1", $contents );
		else:
			$contents = str_replace( '#showContract#', "0", $contents );
		
		endif;
			
		$contents = str_replace( '#bookaroom_content_contract#', json_encode( nl2br( get_option( 'bookaroom_content_contract' ) ) ), $contents  );
		# get lists
		$branchSome_line = repCon( 'branchSome', $contents );
		$branchNone_line = repCon( 'branchNone', $contents, TRUE );
		
		$branch_line = repCon( 'branch', $branchSome_line );
		$branchImage_line = repCon( 'branchImage', $branch_line );
		$branchRoom_line = repCon( 'branchRoom', $branch_line );
		$branchRoomNone_line = repCon( 'branchRoomNone', $branch_line, TRUE );
		$bookaroom_reservation_URL = makeLink_correctPermaLink( get_option( 'bookaroom_reservation_URL' ) );
		
		$temp = NULL;
		$final = array();
		$linkList = array();
		if( empty( $branchList ) || count( $branchList ) < 1 ):
			$contents = str_replace( '#branchSome_line#', $branchNone_line, $contents );			
		else:
			$contents = str_replace( '#branchSome_line#', $branchSome_line, $contents );
			
			foreach( $branchList as $key => $val ):
				$temp = $branch_line;
				# make link list
				$curLink = urlencode( $val['branchDesc'] );
				$linkList[$val['branchDesc']] = $curLink;
				
				$temp = str_replace( '#branchDesc#', $val['branchDesc'], $temp );
				$temp = str_replace( '#branchAddress#', nl2br( $val['branchAddress'] ), $temp );
				$temp = str_replace( '#branchMapLink#', $val['branchMapLink'], $temp );
				$temp = str_replace( '#branchAnchor#', $curLink, $temp );
				
				if( empty( $val['branchImageURL'] ) ):
					$temp = str_replace( '#branchImage_line#', NULL, $temp );
				else:
					$temp = str_replace( '#branchImage_line#', $branchImage_line, $temp );
					$temp = str_replace( '#branchImageURL#', $val['branchImageURL'], $temp );					
				endif;
				# room list
				$tinyTemp = NULL;
				$tinyFinal = array();
				
				# check for rooms
				if( empty( $roomContList ) || empty( $roomContList['branch'][$key] ) ):
					$temp = str_replace( '#branchRoom_line#', $branchRoomNone_line, $temp );
				else:
					# check each container for rooms
					$hasRooms = FALSE;
					foreach( $roomContList['branch'][$key] as $tinyKey ):
						$roomsInCont = $roomContList['id'][$tinyKey]['rooms'];
						
						if( count( $roomsInCont ) < 1 ):
							continue;
						endif;
						$hasRooms = TRUE;
						
						$tinyTemp = $branchRoom_line;
						
						$tinyTemp = str_replace( '#roomName#', $roomContList['id'][$tinyKey]['desc'], $tinyTemp );
						$tinyTemp = str_replace( '#roomCapacity#', $roomContList['id'][$tinyKey]['occupancy'], $tinyTemp );
						$tinyTemp = str_replace( '#roomID#', $tinyKey, $tinyTemp );
						$tinyTemp = str_replace( '#bookaroom_reservation_URL#', $bookaroom_reservation_URL, $tinyTemp );
						$roomAmenities = array();
						
						foreach( $roomContList['id'][$tinyKey]['rooms'] as $aKey ):
							if( !empty( $roomList['id'][$aKey]['amenity'] ) ):
								$roomAmenities +=  $roomList['id'][$aKey]['amenity'] ;
							endif;							
						endforeach;
						
						if( count( $roomAmenities ) == 0 ):
							$tinyTemp = str_replace( '#roomAmenities#', 'None', $tinyTemp );
						else:
							$roomAmenities = array_unique( $roomAmenities );
							
							foreach( $roomAmenities as $afKey => &$afVal ):
								if( !empty( $amenityList[$afVal] ) ):
									$afVal = $amenityList[$afVal];
								else:
									unset( $roomAmenities[$afKey] );
								endif;
							endforeach;
							
							array_filter( $roomAmenities );
							asort( $roomAmenities );
							$tinyTemp = str_replace( '#roomAmenities#', implode( '; ', $roomAmenities ), $tinyTemp );
						endif;
						
						$tinyFinal[] = $tinyTemp;
					endforeach;
					
					if( $hasRooms == FALSE ):
						$temp = str_replace( '#branchRoom_line#', $branchRoomNone_line, $temp );
					else:
						$temp = str_replace( '#branchRoom_line#', implode( "\r\n", $tinyFinal ), $temp );
					endif;
				endif;
				
				$final[] = $temp;
			endforeach;
			
			# make Link List
			foreach( $linkList as $key => &$val ):
				$val = "<a href=\"#{$val}\">{$key}</a>";
			endforeach;
			
			$contents = str_replace( '#branchLinkList#', implode( ' | ', $linkList ), $contents );
			
			$contents = str_replace( '#branch_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		return $contents;
	}
	public static function showRooms_day( $roomID, $branchID, $timestamp, $branchList, $roomContList, $roomList, $amenityList, $cityList )
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/public/publicShowRooms_day.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#permalinkCal#', CHUH_BaR_Main_permalinkFix(), $contents );
		$contents = str_replace( '#correctedLink#', makeLink_correctPermaLink( get_option( 'bookaroom_reservation_URL' ) ), $contents );
		
		# find time information
		# day of the week
		if( empty( $timestamp ) ):
			$timestamp = current_time('timestamp');
		endif;
		
		#$branchList = array();
		#$roomContList = array();
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		# get branch and room
		# if Room ID is not empty and is valid
		if( !empty( $roomContList ) && !empty( $roomID ) && in_array( $roomID, array_keys( $roomContList['id'] ) ) ):
			# find that room's branch
			# if there is no valid branch, both are null (can't have a 
			# valid room that has no branch)
			if( empty( $roomContList['id'][$roomID]['branchID'] ) ):
				$branchID = NULL;
				$roomID = NULL;
			# if valid branch, map it.
			else:
				$branchID = $roomContList['id'][$roomID]['branchID'];
			endif;
		# if the room is empty, check for a branch ID, make sure it's valid
		elseif( !empty( $branchID ) && in_array( $branchID, array_keys( $branchList ) ) ):
			# since we have a valid branch, lets see if there are any rooms (make sure that
			# the room list is an array and not empty )
			if( !empty( $roomContList['branch'][$branchID] ) && is_array( $roomContList['branch'][$branchID] ) ):
				# make the room ID the first room
				$roomID = current( $roomContList['branch'][$branchID] );
			else: 
				# if no rooms, show no available rooms
				$roomID = NULL;
			endif;
		else:
			$branchID = NULL;
			$roomID = NULL;
		endif;
		# get lines
		$branchSome_line		= repCon('branchSome', $contents );
		$branchSelected_line	= repCon('branchSelected', $contents, TRUE );
		$branchNoneExist_line	= repCon('branchNoneExist', $contents, TRUE );
		$roomSome_line			= repCon('roomSome', $contents);
		$roomSelected_line		= repCon('roomSelected', $contents, TRUE);
		$roomNoneOrBranch_line	= repCon('roomNoneOrBranch', $contents, TRUE );
		$roomNoneExist_line		= repCon('roomNoneExist', $contents, TRUE );
		$roomNoBranch_line		= repCon('roomNoBranch', $contents, TRUE );
		$roomClosed_line		= repCon('roomClosed', $contents, TRUE );
		# branch
		############################################
		# check for branch with no rooms
		
		# check if no branches
		if( count( $branchList ) == 0 ):
			$contents = str_replace( '#branchSome_line#', $branchNoneExist_line, $contents );
			$contents = str_replace( '#roomSome_line#', $roomNoneOrBranch_line, $contents );
			return $contents;
			return FALSE;
		# if branches, display them
		else:
			if( empty( $branchID ) ):
				$branchID = key( $branchList );
			endif;
			
			$temp = NULL;
			$final = array();
			
			foreach( $branchList as $key => $val ):
				# is this the current branch?
				if( !empty( $branchID ) && $branchID == $key ):
					$temp = $branchSelected_line;
					$temp = str_replace( '#branchName#', $val['branchDesc'], $temp );
					$final[] = $temp;
				# non-selected branch
				else:
					$temp = $branchSome_line;
					$temp = str_replace( '#branchName#', $val['branchDesc'], $temp );
					$temp = str_replace( '#branchID#', $val['branchID'], $temp );
					$temp = str_replace( '#timestamp#', $timestamp, $temp );
					$final[] = $temp;
				endif;
			endforeach;
			$contents = str_replace( '#branchSome_line#', implode( "\r\n", $final ), $contents );
		endif;
		# rooms
		#######################################################
		# if no rooms in branch show error
		if( empty( $roomContList['branch'][$branchID] ) ):
			$contents = str_replace( '#roomSome_line#', $roomNoneExist_line, $contents );
		else:
			# if roomID is empty, pick first room
			if( empty( $roomID ) ):
				$roomID = current( $roomContList['branch'][$branchID] );
			endif;
			
			# cycle rooms
			$temp = NULL;
			$final = array();
			foreach( $roomContList['branch'][$branchID] as $key => $val ):
	
				# if container is empty, skip
				if( empty( $roomContList['id'][$val]['rooms'] ) ):
					continue;
				endif;
				# is this the current room?
				if( $roomID == $val ):
					$temp = $roomSelected_line;
					$temp = str_replace( '#roomName#', $roomContList['id'][$val]['desc'], $temp );
					$temp = str_replace( '#occupancy#', $roomContList['id'][$val]['occupancy'], $temp );					
					$final[] = $temp;
				# non-selected branch
				else:
					$temp = $roomSome_line;
					$temp = str_replace( '#roomName#', $roomContList['id'][$val]['desc'], $temp );
					$temp = str_replace( '#occupancy#', $roomContList['id'][$val]['occupancy'], $temp );
					$temp = str_replace( '#roomID#', $val, $temp );
					$temp = str_replace( '#timestamp#', $timestamp, $temp );
					$final[] = $temp;	
				endif;
			endforeach;
			$contents = str_replace( '#roomSome_line#', implode( "\r\n", $final ), $contents );		
		endif;
		
		$contents = self::showRooms_smallCalendar( $contents, $roomID, $timestamp );
		
		# If no rooms, show no rooms in time area
		$hoursRoomsSome = repCon( 'hoursRoomsSome', $contents );
		$hoursRoomsNone = repCon( 'hoursRoomsNone', $contents, TRUE );
		$hoursRoomsClosed = repCon( 'hoursRoomsClosed', $contents, TRUE );
		
		$dayOfWeek = date( 'w', $timestamp );
		
		if( empty( $roomContList['branch'][$branchID] ) ):
			$contents = str_replace( '#hoursRoomsSome_line#', $hoursRoomsNone, $contents );
		elseif( empty( $branchList[$branchID]["branchOpen_{$dayOfWeek}"] ) || empty( $branchList[$branchID]["branchClose_{$dayOfWeek}"] ) ):
			$contents = str_replace( '#hoursRoomsSome_line#', $hoursRoomsClosed, $contents );
		else:
			$contents = str_replace( '#hoursRoomsSome_line#', $hoursRoomsSome, $contents );
			
			$hourReg_line			= repCon( 'hourReg', $contents );
			$hourReserved_line		= repCon( 'hourReserved', $contents, TRUE );
			$hourUnavailable_line	= repCon( 'hourUnavailable', $contents, TRUE );
			$hourSetup_line			= repCon( 'hourSetup', $contents, TRUE );
			$hourLast_line			= repCon( 'hourLast', $contents, TRUE );
			
			$timeInfo = getdate( $timestamp );
			$baseIncrement = get_option( 'bookaroom_baseIncrement' );
			
			$openTime = strtotime( date( 'Y-m-d '.$branchList[$branchID]["branchOpen_{$dayOfWeek}"], $timestamp ) );
			$closeTime = strtotime( date( 'Y-m-d '.$branchList[$branchID]["branchClose_{$dayOfWeek}"], $timestamp ) );
			$increments = ( ( $closeTime - $openTime ) / 60 ) / $baseIncrement;
			
			$temp = NULL;
			$final = array();
			
			# if this date is before the reserve buffer, make normal days setups
			$reserveBuffer = get_option( 'bookaroom_reserveBuffer' );				
			
			
			
			$tonightInfo = getdate( current_time('timestamp') );
			$tonight = mktime( 23, 59, 59, $tonightInfo['mon'], $tonightInfo['mday'] + ( $reserveBuffer -1 ), $tonightInfo['year'] );
			
			if( $reserveBuffer and $timestamp <= $tonight and !current_user_can( 'read' ) ):
				$hourReg_line = $hourUnavailable_line;
			endif;
			# if this date is after the allowed buffer, make normal days setups
			$allowedBuffer = get_option( 'bookaroom_reserveAllowed' );
			
			$tonightInfo = getdate( current_time('timestamp') );
			$tonight = mktime( 23, 59, 59, $tonightInfo['mon'], $tonightInfo['mday'] + $allowedBuffer, $tonightInfo['year'] );
			
			if( $timestamp >= $tonight and !current_user_can( 'read' ) ):
				$hourReg_line = $hourUnavailable_line;
			endif;
			
			# fill in instructions
			$unavail_all_line	= repCon( 'unavail_all', $contents );
			
			if( $allowedBuffer or $reserveBuffer ):
				$contents = str_replace( '#unavail_all_line#', $unavail_all_line, $contents );
			
				$unavail_pre_line	= repCon( 'unavail_pre', $contents );
				$unavail_post_line	= repCon( 'unavail_post', $contents );
				
				if( $reserveBuffer == 0 ):
					$contents = str_replace( '#unavail_pre_line#', NULL, $contents );
				else:
					$contents = str_replace( '#unavail_pre_line#', $unavail_pre_line, $contents );
					$contents = str_replace( '#unavail_pre_num#', $reserveBuffer, $contents );
					if( $reserveBuffer == 1 ):
						$contents = str_replace( '#unavail_pre_s#', NULL, $contents );
					else:
						$contents = str_replace( '#unavail_pre_s#', 's', $contents );
					endif;			
				endif;
					
				if( $allowedBuffer == 0 ):
					$contents = str_replace( '#unavail_post_line#', NULL, $contents );
				else:
					$contents = str_replace( '#unavail_post_line#', $unavail_post_line, $contents );
					$contents = str_replace( '#unavail_post_num#', $allowedBuffer, $contents );
					if( $allowedBuffer == 1 ):
						$contents = str_replace( '#unavail_post_s#', NULL, $contents );
					else:
						$contents = str_replace( '#unavail_post_s#', 's', $contents );
					endif;			
				endif;
				
				if( $allowedBuffer > 0 && $reserveBuffer > 0 ):
					$contents = str_replace( '#unavail_and#', ' and ', $contents );
				else:
					$contents = str_replace( '#unavail_and#', NULL, $contents );
				endif;
			else:
				$contents = str_replace( '#unavail_all_line#', NULL, $contents );
			endif;
			
			# get closings
			$closings = self::getClosings( $roomID, $timestamp, $roomContList );
			if( $closings !== false ):
				$hourReg_line = $hourReserved_line;
			endif;
			# get reservations
			$reservations = self::getReservations( $roomID, $timestamp );
			$incrementList = array();
			$cleanupIncrements = get_option( 'bookaroom_cleanupIncrement' );
			$setupIncrements = get_option( 'bookaroom_setupIncrement' );
			
			
			for( $i = 0; $i < $increments; $i++):
				# find increment offset  from start
				$curStart = $openTime + (  $baseIncrement * 60 * $i);
				$curEnd = $openTime + (  $baseIncrement * 60 * ($i+1) );
				
				if( $curEnd > $closeTime ):
					$curEnd = $closeTime; 
				endif;
				
				# last line?
				if( $i + $cleanupIncrements >= $increments ):
					$incrementList[$i]['type'] = 'last';
				else:	
					if( empty( $reservations ) ):
						if( $curStart < current_time( 'timestamp' ) ):
							$incrementList[$i]['type'] = 'unavailable';
						else:
							$incrementList[$i]['type'] = 'regular';
						endif;
					else:
						foreach( $reservations as $resKey => $resVal ):
							$resVal['timestampStart'] = strtotime( $resVal['ti_startTime'] );
							$resVal['timestampEnd'] = strtotime( $resVal['ti_endTime'] );
							
							# check if increment time is equal to or after start and before end
							if( $curStart >= $resVal['timestampStart'] && $curEnd <= $resVal['timestampEnd'] ):
								$incrementList[$i]['type'] = 'reserved';
								# show by type
								if( $resVal['ti_type'] == 'event' ):
									$incrementList[$i]['desc'] =  $resVal['ev_title'];
								else:
									#$incrementList[$i]['desc'] =  "In Use";
									$incrementList[$i]['desc'] =  $resVal['me_eventName'];
								endif;
								
								
								if( $curStart == $resVal['timestampStart'] ):
									# This adds unavailable slots before each reservation if there are setup increments
									if( (int)$cleanupIncrements !== 0 ):
										$incrementList[$i-1]['type'] = 'unavailable';
									endif;
									# setup time
									for( $s = $i-1; $s > ( $i-1-$setupIncrements ); $s--):
										if( !empty( $incrementList[$s]['type'] ) && $incrementList[$s]['type'] !== 'reserved' ):
											$incrementList[$s]['type'] = 'setup';
										endif;
									endfor;
								endif;
								
								#cleanup time
								$count = 1;
								
								if( $curEnd == $resVal['timestampEnd'] ):
									for( $s = $i+1; $s < ( $i+1+$cleanupIncrements ); $s++):
									if( $count++ > 20 ) die();
											$incrementList[$s]['type'] = 'setup';
									endfor;
								endif;
							
							else:
								if( empty( $incrementList[$i]['type'] ) ):
									$incrementList[$i]['type'] = 'regular';
								endif;
							endif;
						endforeach;
					endif;
				endif;
			endfor;
			
			for( $i = 0; $i < $increments; $i++):
				# find increment offset  from start
				switch( $incrementList[$i]['type'] ):
					case 'setup':
						$temp = $hourSetup_line;
						break;
					
					case 'reserved':
						$temp = $hourReserved_line;
						break;
						
					case 'regular':
						$temp = $hourReg_line;
						break;
						
					case 'last':
						$temp = $hourLast_line;
						break;
					case 'unavailable':
						$temp = $hourUnavailable_line;
						break;
					
					default:
						$temp = $hourSetup_line;
						break;
						
				endswitch;
				$curStart = $openTime + (  $baseIncrement * 60 * $i);
				$curEnd = $openTime + (  $baseIncrement * 60 * ($i+1) );
				
				if( $curEnd > $closeTime ):
					$curEnd = $closeTime; 
				endif;
				$timeDisp = date( 'g:i a', $curStart ) .' - '. date( 'g:i a', $curEnd );
				$temp = str_replace( '#timeDisp#', $timeDisp, $temp );
				$temp = str_replace( '#hoursCount#', $i, $temp );
				$temp = str_replace( '#iterTimestamp#', $curStart, $temp );
				if( !empty( $closings ) ):
					$temp = str_replace( '#desc#', 'Closed: '.$closings, $temp );
				elseif( !empty( $incrementList[$i]['desc'] ) ):
					$temp = str_replace( '#desc#', htmlspecialchars_decode( $incrementList[$i]['desc'] ), $temp );
				endif;
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#hourReg_line#', implode( "\r\n", $final ), $contents );
			
			# background and font colors
			$contents = str_replace( '#setupColor#', get_option( 'bookaroom_setupColor' ), $contents );
			$contents = str_replace( '#setupFont#', get_option( 'bookaroom_setupFont' ), $contents );
			$contents = str_replace( '#reservedColor#', get_option( 'bookaroom_reservedColor' ), $contents );
			$contents = str_replace( '#reservedFont#', get_option( 'bookaroom_reservedFont' ), $contents );
		endif;
		
		# fix form for permalinks
		$page_id_line = repCon( 'page_id', $contents );
		
		$serverURI = parse_url( $_SERVER['REQUEST_URI'] );
		$permStruc = get_option( 'permalink_structure' );
		
		if( empty( $permStruc ) ):
			# if no permalink structure, compare ID
			parse_str( $serverURI['query'], $pageInfo );			
			$contents = str_replace( '#page_id_line#', $page_id_line, $contents );
			$contents = str_replace( '#page_id#', $pageInfo['page_id'], $contents );
			$contents = str_replace( '#page_action#', NULL, $contents );
		else:
			$contents = str_replace( '#page_id_line#', NULL, $contents );
			$contents = str_replace( '#page_action#', rtrim( $serverURI['path'], '/' ), $contents );
		endif;
		
		$contents = str_replace( '#roomID#', $roomID, $contents );
		
		return $contents;
	
	}
	
	public static function showRooms_smallCalendar( $contents, $roomID, $timestamp = NULL )
	{
		# validate timestamp. If invalid or empty, make current time.
		if( empty( $timestamp ) || ( !is_numeric( $timestamp ) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX ) ) ):
   			$timestamp = current_time('timestamp');
		endif;
		
		$urlInfoRaw = parse_url( get_permalink() );
			
		$urlInfo = (!empty( $urlInfoRaw['query'] ) ) ? $urlInfoRaw['query'] : NULL;
		
		if( empty( $urlInfo ) ):
			$permalinkCal = '?';
		else:
			$permalinkCal = '?'.$urlInfo.'&';
		endif;
			
		# full month timestamp
		$timestampInfo = getdate( $timestamp );
		$thisMonth = mktime( 0, 0, 0, $timestampInfo['mon'], 1, $timestampInfo['year'] );
		
		# month and year
		$contents = str_replace( '#month#', date( 'F', $thisMonth ), $contents );
		$contents = str_replace( '#year#', date( 'Y', $thisMonth ), $contents );
		
		$nextMonth = mktime( 0, 0, 0, $timestampInfo['mon']+1, 1, $timestampInfo['year'] );
		$prevMonth = mktime( 0, 0, 0, $timestampInfo['mon']-1, 1, $timestampInfo['year'] );
		
		$contents = str_replace( '#nextTimestamp#', $nextMonth, $contents );
		$contents = str_replace( '#prevTimestamp#', $prevMonth, $contents );
		
		$contents = str_replace( '#monthRoomID#', $roomID, $contents );
				
		
		#$thisMonth = mktime( 0, 0, 0, 2, 1, 2009 );
		
		# grab lines
		$week_line 		= repCon( 'week', $contents );
		$noDay_line		= repCon( 'noDay', $week_line, TRUE );
		$selDay_line	= repCon( 'selDay', $week_line, TRUE );
		$today_line		= repCon( 'today', $week_line, TRUE );
		$regDay_line	= repCon( 'regDay', $week_line );
		
		# On which day of the week does this month start?
		$dayOfWeek = date( 'w', $thisMonth );
		# How many weeks are there in this month?
		$daysInMonth =  date( 't', $thisMonth );
		$weeksInMonth = ceil( ( $daysInMonth + $dayOfWeek ) / 7 );
		
		# cycle weeks
		$temp = NULL;
		$final = array();
		$curDay = 1;		
		for( $week = 0; $week < $weeksInMonth; $week++ ):
			$temp = $week_line;
			
			# cycle days per week
			$miniTemp = NULL;
			$miniFinal = array();
			
			for( $day = 1; $day <= 7; $day++ ):						
				# first, are there any buffer days?
				if( !empty( $dayOfWeek ) ):
					$miniTemp = $noDay_line;
					$dayOfWeek--;
					$miniFinal[] = $miniTemp;
					continue;
				endif;
				
				# second are we past the highest day?
				if( $curDay > $daysInMonth ):
					$miniTemp = $noDay_line;
					$miniFinal[] = $miniTemp;
					continue;
				endif;
				
				$thisTimeStamp = mktime(  0, 0, 0, $timestampInfo['mon'], $curDay, $timestampInfo['year'] );
				
				# is date selected?
				
				if( date( 'm-d-y', $timestamp ) == date( 'm-d-y', $thisTimeStamp ) ):
					$miniTemp = $selDay_line;
					
					$miniTemp = str_replace( '#dateNum#', $curDay++, $miniTemp );
					$miniTemp = str_replace( '#timestamp#', $thisTimeStamp, $miniTemp );
					$miniTemp = str_replace( '#roomID#', $roomID, $miniTemp );
					
					$miniFinal[] = $miniTemp;				
					continue;
				endif;
				# is it today?
				$curTimeInfo = getdate( current_time( 'timestamp' ) );
				
				if( $thisTimeStamp == mktime( 0, 0, 0, $curTimeInfo['mon'], $curTimeInfo['mday'], $curTimeInfo['year'] ) ):
					$miniTemp = $today_line;
					
					$miniTemp = str_replace( '#dateNum#', $curDay++, $miniTemp );
					$miniTemp = str_replace( '#timestamp#', $thisTimeStamp, $miniTemp );
					$miniTemp = str_replace( '#roomID#', $roomID, $miniTemp );
					#$miniTemp = str_replace( '#permalinkCal#', $permalinkCal, $miniTemp );
					
					$miniFinal[] = $miniTemp;				
					continue;				
				endif;
				
				$miniTemp = $regDay_line;
				
				$miniTemp = str_replace( '#dateNum#', $curDay++, $miniTemp );
				$miniTemp = str_replace( '#timestamp#', $thisTimeStamp, $miniTemp );
				$miniTemp = str_replace( '#roomID#', $roomID, $miniTemp );
				#$miniTemp = str_replace( '#permalinkCal#', $permalinkCal, $miniTemp );
				
				$miniFinal[] = $miniTemp;
				
			endfor;
			
			$temp = str_replace( '#regDay_line#', implode( "\r\n", $miniFinal ), $temp );
			$final[] = $temp;
		endfor;
		
		$contents = str_replace( '#week_line#', implode( "\r\n", $final ), $contents );
		# date dropdown
		$year_line	= repCon( 'year', $contents );
		$month_line	= repCon( 'month', $contents );
		
		$curInfo = getdate( current_time('timestamp') );
		
		$temp = NULL;
		$final = array();
		# make year
		for( $year = $curInfo['year'] - 1; $year < $curInfo['year'] + 3; $year++ ):
			if( $timestampInfo['year'] == $year ):
				$selected = ' selected="selected"';
			else:
				$selected = NULL;
			endif;
			
			$temp = $year_line;
			$temp = str_replace( '#yearDisp#', $year, $temp );
			$temp = str_replace( '#yearKey#', $year, $temp );
			$temp = str_replace( '#yearSelected#', $selected, $temp );
			
			$final[] = $temp;
		endfor;
		
		$contents = str_replace( '#year_line#', implode( "\r\n", $final ), $contents );
		
		$temp = NULL;
		$final = array();
		# make months
		for( $month = 1; $month <= 12; $month++ ):
			if( $timestampInfo['mon'] == $month):
				$selected = ' selected="selected"';
			else:
				$selected = NULL;
			endif;
			
			$temp = $month_line;
			$temp = str_replace( '#monthDisp#', date( 'F', mktime( 0, 0, 0, $month, 1, $timestampInfo['year']) ), $temp );
			$temp = str_replace( '#monthKey#', $month, $temp );
			$temp = str_replace( '#monthSelected#', $selected, $temp );
			
			$final[] = $temp;
		endfor;
				
		$contents = str_replace( '#month_line#', implode( "\r\n", $final ), $contents );
		
		$contents = str_replace( '#calRoomID#', $roomID, $contents );
		
		return $contents;	
	}
	
	public static function sendAlertEmail( $externals, $amenityList, $roomContList, $branchList, $admin = false )
	{
		$filename = BOOKAROOM_PATH . 'templates/public/adminNewRequestAlert.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		
		# Amenities
		if( empty( $externals['amenity'] ) ):
			$externals['amenityVal'] = "No amenities";
		else:
			$amentiyArr = array();
			foreach( $externals['amenity'] as $key => $val ):
				$amenityArr[] = $amenityList[$val];
			endforeach;
			$externals['amenityVal'] = implode( ', ', $amenityArr );
		endif;
		if( $externals['nonProfit'] == TRUE ):
			$externals['nonProfitDisp'] = 'Yes';
			$externals['roomDeposit'] = get_option( 'bookaroom_nonProfitDeposit' );
			$costIncrement =  get_option( 'bookaroom_nonProfitIncrementPrice' );
		else:
			$externals['nonProfitDisp'] = 'No';
			$externals['roomDeposit'] = get_option( 'bookaroom_profitDeposit' );
			$costIncrement =  get_option( 'bookaroom_profitIncrementPrice' );
		endif;
		
		#$roomCount = count( $roomContList['id'][$val['ti_roomID']]['rooms'] );
		$roomCount = 1;
		
		$externals['roomCost'] = ( ( ( ( $externals['endTime'] - $externals['startTime'] ) / 60 ) / get_option( 'bookaroom_baseIncrement' ) ) * $costIncrement ) * $roomCount;
		$externals['roomTotal'] = $externals['roomCost'] + $externals['roomDeposit'];
		
		# times
		$externals['formDate'] = date( 'l, F jS, Y', $externals['startTime'] );
		$externals['startTimeDisp'] = date( 'g:i a', $externals['startTime'] );
		$externals['endTimeDisp'] = date( 'g:i a', $externals['endTime'] );
		
		# branch name
		$roomID = $externals['roomID'];
		
		$externals['roomName'] = $roomContList['id'][$externals['roomID']]['desc'];
		$externals['branchName'] = $branchList[$roomContList['id'][$externals['roomID']]['branchID']]['branchDesc'];
		
		# payment link
		$externals['paymentLink'] = 'test.php';
		
		# array of all values
		$valArr = array( 'amenityVal','branchName','branchNames','contactAddress1','contactAddress2','contactCity','contactEmail','contactName','contactPhonePrimary','contactPhoneSecondary','contactState','contactWebsite','contactZip','desc','endTimeDisp','eventName','formDate','nonProfitDisp','numAttend', 'paymentLink', 'roomCost','roomDeposit','roomID','roomName','roomTotal','startTimeDisp' );
		
		foreach( $valArr as $key => $val ):
			$contents = @str_replace( "#{$val}#", $externals[$val], $contents );
		endforeach;
	
		$email = get_option( 'bookaroom_alertEmail' );;
		
		$fromName	= get_option( 'bookaroom_alertEmailFromName' );
		$fromEmail	= get_option( 'bookaroom_alertEmailFromEmail' );
		
		$replyToOnly	= ( true == get_option( 'bookaroom_emailReplyToOnly' ) ) ? "From: {$fromName}\r\nReply-To: {$fromName} <$fromEmail>\r\n" : "From: {$fromName} <$fromEmail>\r\n";
		
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
					$replyToOnly . 
				    'X-Mailer: PHP/' . phpversion();
			mail( $email, "Book a Room Event Created: {$externals['branchName']} [{$externals['roomName']}] by {$externals['contactName']} on {$externals['formDate']} from {$externals['startTimeDisp']} to {$externals['endTimeDisp']}", $contents, $headers );
		
		if( $admin == true ):
			mail( $contactEmail, "Your Book a Room Event Details: {$externals['branchName']} [{$externals['roomName']}] by {$externals['contactName']} on {$externals['formDate']} from {$externals['startTimeDisp']} to {$externals['endTimeDisp']}", $contents, $headers );
		endif;
	}
	
	public static function sendCustomerReceiptEmail( $externals, $amenityList, $roomContList, $branchList, $internal = false )
	{
		$fromName	= get_option( 'bookaroom_alertEmailFromName' );
		$fromEmail	= get_option( 'bookaroom_alertEmailFromEmail' );
		$replyToOnly	= ( true == get_option( 'bookaroom_emailReplyToOnly' ) ) ? "From: {$fromName}\r\nReply-To: {$fromName} <$fromEmail>\r\n" : "From: {$fromName} <$fromEmail>\r\n";
		
		if( $internal == TRUE ):
			$subject	= get_option( 'bookaroom_newInternal_subject' );
			$contents	= html_entity_decode( get_option( 'bookaroom_newInternal_body' ) );
		else:		
			$subject	= get_option( 'bookaroom_newAlert_subject' );
			$contents	= html_entity_decode( get_option( 'bookaroom_newAlert_body' ) );
		endif;
		
		$subject	= self::replaceItems( $subject, $externals, $amenityList, $roomContList, $branchList );
		$contents	= self::replaceItems( $contents, $externals, $amenityList, $roomContList, $branchList );
		
		$option['bookaroom_profitDeposit']				= get_option( 'bookaroom_profitDeposit' );
		$option['bookaroom_nonProfitDeposit']			= get_option( 'bookaroom_nonProfitDeposit' );
		$option['bookaroom_profitIncrementPrice']		= get_option( 'bookaroom_profitIncrementPrice' );
		$option['bookaroom_nonProfitIncrementPrice']	= get_option( 'bookaroom_nonProfitIncrementPrice' );
		$option['bookaroom_baseIncrement']				= get_option( 'bookaroom_baseIncrement' );
				
		$roomCount = count( $roomContList['id'][$externals['roomID']]['rooms'] );
				
		if( empty( $externals['nonProfit'] ) ):
			# find how many increments
			$minutes = ( ( $externals['endTime'] - $externals['startTime'] ) / 60 ) / $option['bookaroom_baseIncrement'] ;
			$roomPrice = $minutes * $option['bookaroom_profitIncrementPrice'] * $roomCount;
			$deposit = 	intval( $option['bookaroom_profitDeposit'] );
		else:
			# &&& find how many increments
			$minutes = ( ( $externals['endTime'] - $externals['startTime'] ) / 60 ) / $option['bookaroom_baseIncrement'] ;
			$roomPrice = $minutes * $option['bookaroom_nonProfitIncrementPrice'] * $roomCount;
			
			#$pendingList['id'][$val]['roomCost'] = ( ( ( ( strtotime( $pendingList['id'][$val]['endTime'] ) - strtotime( $pendingList['id'][$val]['startTime'] ) ) / 60 ) / get_option( 'bookaroom_baseIncrement' ) ) * $costIncrement );
			
			
			$deposit = 	intval( $option['bookaroom_nonProfitDeposit'] );	
		endif;
		
		$contents = str_replace( '{deposit}', $deposit, $contents );
		$contents = str_replace( '{roomPrice}', $roomPrice, $contents );
		$contents = str_replace( '{totalPrice}', $roomPrice+$deposit, $contents );
		
		# create email
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n" .

					"{$replyToOnly}: {$fromName} <{$fromEmail}>" . "\r\n" .					
				    'X-Mailer: PHP/' . phpversion();

		mail( $externals['contactEmail'], $subject, $contents, $headers );

		return $contents;
		
	}
	
	public static function replaceItems( $contents, $externals, $amenityList, $roomContList, $branchList )
	{
		$oldExternals = $externals;
		
		# Amenities
		if( empty( $externals['amenity'] ) ):
			$externals['amenity'] = "No amenities";
		else:
			$amentiyArr = array();
			foreach( $externals['amenity'] as $key => $val ):
				$amenityArr[] = $amenityList[$val];
			endforeach;
			$externals['amenity'] = implode( ', ', $amenityArr );
		endif;
		
		if( $externals['nonProfit'] == TRUE ):
			$externals['nonProfit'] = 'Yes';
		else:
			$externals['nonProfit'] = 'No';
		endif;

		# date 1 - two weeks from now
		$timeArr = getdate( time() );
		$date1 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+14, $timeArr['year'] );
		$date3 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+1, $timeArr['year'] );
		
		# two weeks before event		
		$timeArr = getdate( $externals['startTime'] );
		$date2 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']-14, $timeArr['year'] );
		
		
		# check dates
		$mainDate = min( $date1, $date2 );
		
		if( $mainDate < $date3 ):
			$mainDate = $date3;
		endif;

		$externals['paymentDate'] = date( 'l, F jS, Y', $mainDate );
		
		$externals['paymentLink'] = 'test.php';
				

		# times
		$externals['date'] = date( 'l, F jS, Y', $externals['startTime'] );
		$externals['startTime'] = date( 'g:i a', $externals['startTime'] );
		$externals['endTime'] = date( 'g:i a', $externals['endTime'] );
		
		# branch name
		$roomID = $externals['roomID'];
		
		$externals['roomName'] = $roomContList['id'][$externals['roomID']]['desc'];
		$externals['branchName'] = $branchList[$roomContList['id'][$externals['roomID']]['branchID']]['branchDesc'];

		# costs
		if( empty( $oldExternals['nonProfit'] ) ):
			$externals['roomDeposit'] = get_option( 'bookaroom_profitDeposit' );
			$costIncrement =  get_option( 'bookaroom_profitIncrementPrice' );
		else:
			$externals['roomDeposit'] = get_option( 'bookaroom_nonProfitDeposit' );
			$costIncrement =  get_option( 'bookaroom_nonProfitIncrementPrice' );
		endif;
		
		$externals['roomCost'] = ( ( ( ( $oldExternals['endTime'] - $oldExternals['startTime'] ) / 60 ) / get_option( 'bookaroom_baseIncrement' ) ) * $costIncrement );
		$externals['roomTotal'] = $externals['roomCost'] + $externals['roomDeposit'];
		
		
		# final			
		$goodArr = array( 'amenity','branchName','contactAddress1','contactAddress2','contactCity','contactEmail','contactName','contactPhonePrimary','contactPhoneSecondary','contactState','contactWebsite','contactZip','date','desc','endTime','eventName','nonProfit','numAttend','paymentDate','paymentLink','roomCost','roomDeposit','roomName','roomTotal','startTime','resID');
		
		#
		foreach( $goodArr as $val )
		{
			$name = '{'.$val.'}';
			$contents = str_replace( $name, $externals[$val], $contents );
		}
		
		
		return nl2br( $contents );
	}
}
?>
