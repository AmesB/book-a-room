<?PHP
class bookaroom_customerSearch
{
	static $branchList;
	static $roomContList;
	
	public static function bookaroom_findUser()
	{
			require_once( BOOKAROOM_PATH . 'sharedFunctions.php' );
		
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
		
		# vaiables from includes
		self::$roomContList = bookaroom_settings_roomConts::getRoomContList();
		self::$branchList = bookaroom_settings_branches::getBranchList( TRUE );		

		# first, is there an action? 
		$externals = self::getExternals();

		switch( $externals['action'] ):
			case 'filterResults':
				# check for errors
				$errorResult = self::getSearchErrorMessage( $externals );
				
				if( $errorResult['isError'] == true ):
					self::showSearchForm( $externals, $errorResult['errorMSG'], $errorResult['bgArr'] );
				else:
					# get search values
					$roomInfo = getRoomInfo( $externals['roomID'], self::$branchList, self::$roomContList, true, true );
					$results = self::searchRegistrations( $roomInfo, $externals['startDate'], $externals['endDate'], $externals['searchName'], $externals['searchEmail'], $externals['searchPhone'] );
					self::showSearchForm( $externals, NULL, NULL, $results );
				endif;
				
				break;
				
			default:
				self::showSearchForm( $externals );
				break;
		endswitch;
	}
	
	
	protected static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = 	array(	'action'				=> FILTER_SANITIZE_STRING, 
				 	);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = $getTemp;
			
		# setup POST variables
		$postArr =	array(	'action'				=> FILTER_SANITIZE_STRING,
							'branchID'				=> FILTER_SANITIZE_STRING,
							'noloc-branchID'		=> FILTER_SANITIZE_STRING,
							'roomID'				=> FILTER_SANITIZE_STRING,
							'startDate'				=> FILTER_SANITIZE_STRING,
							'endDate'				=> FILTER_SANITIZE_STRING,
							'searchName'			=> FILTER_SANITIZE_STRING,
							'searchEmail'			=> FILTER_SANITIZE_STRING,
							'searchPhone'			=> FILTER_SANITIZE_STRING, 
					);
		
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
	
	public static function getSearchErrorMessage( &$externals )
	# look at incomping variables for the search and find any errors.
	{
		# get required vars
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-branches.php' );
		$errorArr = $bgArr = array();
		
		# check dates
		# if start date is empty or invalid, clear it, else make it unix timestamp
		if( !empty( $externals['startDate'] ) and ( ( $startDate = strtotime( $externals['startDate'] ) ) == false ) or is_numeric( $externals['startDate'] ) ):
			$errorArr[] = 'Your start date is invalid.';
			$bgArr[] = 'startDate';
			$startDate = NULL;
		endif;
		
		# if end date is empty or invalis, clear it, else make it unix timestamp
		if( !empty( $externals['endDate'] ) and ( ( $endDate = strtotime( $externals['endDate'] ) ) == false ) or is_numeric( $externals['endDate'] ) ):
			$errorArr[] = 'Your end date is invalid.';			
			$bgArr[] = 'endDate';
			$endDate = NULL;
		endif;
				
		# check to see if two dates are selected
		if( !empty( $startDate ) and !empty( $endDate ) ):
			if( $startDate > $endDate ):
				$errorArr[] = 'Your start date comes after your end date.';
				$bgArr[] = 'startDate';
				$bgArr[] = 'endDate';
			endif;
		endif;
		
		# validate non-empty emails
		if( !empty( $externals['searchEmail'] ) and !filter_var( $externals['searchEmail'], FILTER_VALIDATE_EMAIL ) ):
			$errorArr[] = 'Your email address is incorrectly formatted.';
			$bgArr[] = 'searchEmail';
		endif;

		# check phone	
		if( !empty( $externals['searchPhone'] ) ):
			# if not empty, remove everything except digits.
			$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['searchPhone'] );
			# if there are 11 digits, check for a 1 at the start and remove it
			if ( strlen( $cleanPhone ) == 11 ):
				$cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
				
			endif;
			
			# if there are not 10 digits, the phone number is invalid.
			if ( strlen( $cleanPhone ) !== 10 ):
				$errorArr[] = 'Your phone number is incorrectly formatted. Please enter 10 digits, including area code.';
				$bgArr[] = 'searchPhone';
			else:
				$externals['searchPhone'] = "(" . substr($cleanPhone, 0, 3) . ") " . substr($cleanPhone, 3, 3) . "-" . substr($cleanPhone, 6);
			endif;
		endif;
		
		# check empty search
		$errorMSG = NULL;
		$isError = false;
		
		if( count( $errorArr ) > 0 ):
			$errorMSG = implode( '<br />', $errorArr );
			$isError = true;			
		endif;
		
		return array( 'isError' => $isError, 'errorMSG' => $errorMSG, 'bgArr' => $bgArr );
	}
	
	public static function searchRegistrations( $roomInfo = array(), $startDate = NULL , $endDate = NULL, $searchName = NULL, $searchEmail = NULL, $searchPhone = NULL )
	# search for registrations based on search terms
	# return an array of events based on those registrations
	#
	# $roomInfo is an array with 3 variables. Only one should be true (or none). They include:
	# 	$cur_roomID				= The current selected room ID
	# 	$cur_branchID			= If an entire branch is selected, this is the ID
	# 	$cur_noloc_branchID		= If a no-location is selected for a branch, this is the branchID
	#
	# other search filters:
	# 	$startDate		= UNIX timestamp of the start date (can be NULL)
	# 	$endDate		= UNIX timestamp of the end date (can be NULL)
	# 	$searchName		= Partial or full name to search
	# 	$seachEmail		= Full email address to search
	# 	$searchPhone	= Full phone number to search
	
	{
		global $wpdb;
		
		# start making the MySQL call
		#
		# the where array will build the WHERE filters based on incoming variables.
		$where		= NULL;
		$whereArr	= array();
		
		# search for name
		if( !empty( $searchName ) ):
			$whereArr[] = "( MATCH ( `reg`.`reg_fullName` ) AGAINST ( \"{$searchName}\" IN NATURAL LANGUAGE MODE ) or `reg`.`reg_fullName` = \"{$searchName}\" )";
		endif;
		
		# check for roomID
		if( !empty( $roomInfo['cur_roomID'] ) ):
			$whereArr[] = "`ti`.`ti_roomID` = '{$roomInfo['cur_roomID']}'";
		endif;
		
		# check for no location branch
		if( !empty( $roomInfo['cur_noLocation_branch'] ) ):
			$whereArr[] = "`ti`.`ti_noLocation_branch` = '{$roomInfo['cur_noLocation_branch']}'";
		endif;
		
		
		
				
		# check for branch
		if( !empty( $roomInfo['cur_branchID'] ) ):
			# find all room containers in that branch
			$roomList = implode( ',', self::$roomContList['branch'][$roomInfo['cur_branchID']] );
			# compare against imploded list of room containers in that branch
			$whereArr[] = "( `ti`.`ti_roomID` IN ( {$roomList} ) or `ti`.`ti_noLocation_branch` = '{$roomInfo['cur_branchID']}' )";
		endif;
		
		# start date
		if( !empty( $startDate ) ):
			$startDateSQL = date( 'Y-m-d H:i:s', strtotime( $startDate ) );
			$whereArr[] = "`ti`.`ti_startTime` >= '{$startDateSQL}'";
		endif;

		# end date
		if( !empty( $endDate ) ):
			$endDateSQL =  date( 'Y-m-d H:i:s', strtotime( $endDate ) + 86399 );
			$whereArr[] = "`ti`.`ti_startTime` <= '{$endDateSQL}'";
		endif;
		
		# email
		if( !empty( $searchEmail) ):
			$whereArr[] = "`reg`.`reg_email` LIKE \"%{$searchEmail}%\" or `reg`.`reg_email` = \"{$searchEmail}\"";

		endif;
		
		# email
		if( !empty( $searchPhone) ):
		
			$cleanPhone = preg_replace( "/[^0-9]/", '', $searchPhone );
			
			# if there are 11 digits, check for a 1 at the start and remove it
			if ( strlen( $cleanPhone ) == 11 ):
				$cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
				$searchPhone = "(" . substr($cleanPhone, 0, 3) . ") " . substr($cleanPhone, 3, 3) . "-" . substr($cleanPhone, 6);
			else:
			
			endif;

		
			$whereArr[] = "`reg`.`reg_phone` = \"{$searchPhone}\"";
		endif;
		
		# collapse where if not empty
		if( count( $whereArr ) > 0 ):
			$where = 'WHERE '.implode( " and \r\n", $whereArr );
		else:
			$where = NULL;
		endif;
		
#		$sql = "	SELECT `reg`.*, `ti`.`ti_startTime`, `res`.`ev_desc`
		$sql = "	SELECT 	MATCH ( `reg`.`reg_fullName` ) AGAINST ( \"{$searchName}\" IN NATURAL LANGUAGE MODE ) as score, 
							`reg`.`reg_fullName`, 
							`reg`.`reg_phone`, 
							`reg`.`reg_email`, 
							`reg`.`reg_eventID`, 
							`ti`.`ti_startTime`, 
							`ti`.`ti_endTime`, 
							`ti`.`ti_roomID`, 
							`ti`.`ti_noLocation_branch`,  
							`ti`.`ti_extraInfo`, 
							`ti`.`ti_id`, 
							`res`.`res_id`, 
							`res`.`ev_desc`, 
							`res`.`ev_title`, 
							`res`.`ev_maxReg`,  
							`rc`.`reg_dateReg`, 
							COUNT( `rc`.`reg_id` ) as `regCount`, 
							
							COUNT( DISTINCT `regCount2`.`reg_id` ) + 1 as `regCount` 

					FROM `{$wpdb->prefix}bookaroom_registrations` AS `reg` 
					LEFT JOIN `{$wpdb->prefix}bookaroom_times` AS `ti` ON `reg`.`reg_eventID` = `ti`.`ti_id`					
					LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` AS `res` ON `ti`.`ti_extID` = `res`.`res_id`
					LEFT JOIN `{$wpdb->prefix}bookaroom_registrations` as `rc` ON `reg`.`reg_eventID` = `rc`.`reg_eventID` 
					
					LEFT JOIN `{$wpdb->prefix}bookaroom_registrations` as `regCount2` ON ( `reg`.`reg_eventID` = `regCount2`.`reg_eventID` AND UNIX_TIMESTAMP( `reg`.`reg_dateReg` ) > UNIX_TIMESTAMP( `regCount2`.`reg_dateReg` ) )
					
					{$where} 
					GROUP BY `reg`.`reg_id` 
					ORDER BY score DESC, `reg`.`reg_fullName`, `reg`.`reg_email`, `reg`.`reg_phone`, `ti`.`ti_startTime` 
					
					";
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		if( count( $cooked ) == 0 ):
			return array();
		else:
			return $cooked;
		endif;
		
	}
	
	/*
	public static function showSearchForm( $externals, $errorMSG = NULL, $errorBG = array(), $results = array() )
	# grab the template file and replace things to make a pretty page.
	# this includes the branch drop, search options, error messages and
	# form fields
	{
		$filename = BOOKAROOM_PATH . 'templates'.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.'customerSearch.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		# include CSS
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# common value replace
		$goodArr = array( 'startDate', 'endDate', 'searchName', 'searchEmail', 'searchPhone' );
		
		foreach( $goodArr as $val ):
			$contents = str_replace( "#{$val}#", $externals[$val], $contents );
		endforeach;
		unset( $val );
		
		# branch drop down
		#
		# get room info from raw roomID (checking for branch and noloc branch)
		$roomInfo = getRoomInfo( $externals['roomID'], self::$branchList, self::$roomContList, true, true );
		$contents = branchListDrop( $contents, $roomInfo['cur_roomID'], $roomInfo['cur_branchID'], $roomInfo['cur_noloc_branchID'], self::$branchList, self::$roomContList, true, true);
		
		
		
		
		
		# errors
		# setup template replacements
		$error_line	= repCon( 'error', $contents );
		# if there's an error message, replace the error line with it and the backgrounds with
		# error classes
		if( !empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', $error_line, $contents );			
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		# if no error message, kill the error line container.
		else:
			$contents = str_replace( '#error_line#', NULL, $contents );
		endif;
		
		# search results
		#
		# setup template replacements
		$search_line		= repCon( 'search', $contents );
		$searchItem_line	= repCon( 'searchItem', $search_line );
		$searchNone_line	= repCon( 'searchNone', $search_line, TRUE );
		$phone_line			= repCon( 'phone', $searchItem_line );
		$email_line			= repCon( 'email', $searchItem_line );
		$reg_line			= repCon( 'reg', $searchItem_line );
		$waiting_line		= repCon( 'waiting', $searchItem_line, TRUE );
		
		# remove search results for non-search action
		if( $externals['action'] !== 'filterResults' ):
			$contents = str_replace( '#search_line#', NULL, $contents );		
		elseif( count( $results ) == 0 ):
			# add back search results
			$contents = str_replace( '#search_line#', $search_line, $contents );
			# replace search item line with no results found line
			$contents = str_replace( '#searchItem_line#', $searchNone_line, $contents );
			
		else:
			# add back search results
			$contents = str_replace( '#search_line#', $search_line, $contents );
			
			# loop through and create rows
			$final = array();
			$temp = NULL;
			
			foreach( $results as $key => $val ):
				if( empty( $val['ti_id'] )):
					continue;
				endif;
				# grab the template line
				$temp = $searchItem_line;
				
				
				# replace values
				$temp = str_replace( '#eventID#', $val['ti_id'], $temp );
				
				$temp = str_replace( '#name#', $val['reg_fullName'], $temp );
				$temp = str_replace( '#date#', date( 'D, M jS Y', strtotime( $val['ti_startTime'] ) ), $temp );
				$temp = str_replace( '#time#', date( 'g:i a', strtotime( $val['ti_startTime'] ) ).' to '.date( 'g:i a', strtotime( $val['ti_endTime'] ) ), $temp );
				
				$temp = str_replace( '#title#', $val['ev_title'], $temp );
				$temp = str_replace( '#desc#', make_brief( $val['ev_desc'], 100), $temp );
				$temp = str_replace( '#extraInfo#', $val['ti_extraInfo'], $temp );
				
				# check registrations
				if( $val['regCount'] > $val['ev_maxReg'] ):
					$temp = str_replace( '#reg_line#', $waiting_line, $temp );
					$temp = str_replace( '#curWait#', $val['regCount'], $temp );
					$temp = str_replace( '#totalWait#', $val['ev_maxReg'], $temp );

					
				else:
					$temp = str_replace( '#reg_line#', $reg_line, $temp );					
				endif;
				
				# find branchname
				#
				# if nolocation is true, get branch from that and make room name no location
				if( !empty( $val['ti_noLocation_branch'] ) ):
					$branchName = self::$branchList[$val['ti_noLocation_branch']]['branchDesc'];
					$roomName = 'No location required.';
				else:
					$branchName = self::$branchList[self::$roomContList['id'][$val['ti_roomID']]['branchID']]['branchDesc'];
					$roomName = self::$roomContList['id'][$val['ti_roomID']]['desc'];
				endif;
				
				$temp = str_replace( '#branch#', $branchName, $temp );
				$temp = str_replace( '#room#', $roomName, $temp );
			
				# is there phone?
				if( !empty( $val['reg_phone'] ) ):
					$temp = str_replace( '#phone_line#', $phone_line, $temp );
					$temp = str_replace( '#phoneNumber#', $val['reg_phone'], $temp );
				else:
					$temp = str_replace( '#phone_line#', NULL, $temp );
				endif;
				
				# is there email?
				if( !empty( $val['reg_email'] ) ):
					$temp = str_replace( '#email_line#', $email_line, $temp );
					$temp = str_replace( '#emailAddress#', $val['reg_email'], $temp );
				else:
					$temp = str_replace( '#email_line#', NULL, $temp );
				endif;
				
				
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#searchItem_line#', implode( "\r\n", $final ), $contents );
			
		endif;
		
		echo $contents;
	}
	*/
	
	public static function showSearchForm( $externals, $errorMSG = NULL, $errorBG = array(), $results = array() )
	# grab the template file and replace things to make a pretty page.
	# this includes the branch drop, search options, error messages and
	# form fields
	{
		$filename = BOOKAROOM_PATH . 'templates'.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.'customerSearch.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		# include CSS
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# common value replace
		$goodArr = array( 'startDate', 'endDate', 'searchName', 'searchEmail', 'searchPhone' );
		
		foreach( $goodArr as $val ):
			$contents = str_replace( "#{$val}#", $externals[$val], $contents );
		endforeach;
		unset( $val );
		
		# branch drop down
		#
		# get room info from raw roomID (checking for branch and noloc branch)
		$roomInfo = getRoomInfo( $externals['roomID'], self::$branchList, self::$roomContList, true, true );
		$contents = branchListDrop( $contents, $roomInfo['cur_roomID'], $roomInfo['cur_branchID'], $roomInfo['cur_noloc_branchID'], self::$branchList, self::$roomContList, true, true);
		
		
		
		
		
		# errors
		# setup template replacements
		$error_line	= repCon( 'error', $contents );
		# if there's an error message, replace the error line with it and the backgrounds with
		# error classes
		if( !empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', $error_line, $contents );			
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		# if no error message, kill the error line container.
		else:
			$contents = str_replace( '#error_line#', NULL, $contents );
		endif;
		
		# search results
		#
		# setup template replacements
		$search_line		= repCon( 'search', $contents );
		$searchItem_line	= repCon( 'searchItem', $search_line );
		$searchNone_line	= repCon( 'searchNone', $search_line, TRUE );
		$phone_line			= repCon( 'phone', $searchItem_line );
		$email_line			= repCon( 'email', $searchItem_line );
		$reg_line			= repCon( 'reg', $searchItem_line );
		$waiting_line		= repCon( 'waiting', $searchItem_line, TRUE );
		
		# remove search results for non-search action
		if( $externals['action'] !== 'filterResults' ):
			$contents = str_replace( '#search_line#', NULL, $contents );		
		elseif( count( $results ) == 0 ):
			# add back search results
			$contents = str_replace( '#search_line#', $search_line, $contents );
			# replace search item line with no results found line
			$contents = str_replace( '#searchItem_line#', $searchNone_line, $contents );
			
		else:
			# add back search results
			$contents = str_replace( '#search_line#', $search_line, $contents );
			
			# loop through and create rows
			$final = array();
			$temp = NULL;
			
			foreach( $results as $key => $val ):
				if( empty( $val['ti_id'] )):
					continue;
				endif;
				# grab the template line
				$temp = $searchItem_line;
				
				
				# replace values
				$temp = str_replace( '#eventID#', $val['ti_id'], $temp );
				
				$temp = str_replace( '#name#', $val['reg_fullName'], $temp );
				$temp = str_replace( '#date#', date( 'D, M jS Y', strtotime( $val['ti_startTime'] ) ), $temp );
				$temp = str_replace( '#time#', date( 'g:i a', strtotime( $val['ti_startTime'] ) ).' to '.date( 'g:i a', strtotime( $val['ti_endTime'] ) ), $temp );
				
				$temp = str_replace( '#title#', $val['ev_title'], $temp );
				$temp = str_replace( '#desc#', make_brief( $val['ev_desc'], 100), $temp );
				$temp = str_replace( '#extraInfo#', $val['ti_extraInfo'], $temp );
				
				# check registrations
				
				if( $val['regCount'] > $val['ev_maxReg'] ):
					$temp = str_replace( '#reg_line#', $waiting_line, $temp );
					$temp = str_replace( '#curWait#', $val['regCount'], $temp );
					$temp = str_replace( '#totalWait#', $val['ev_maxReg'], $temp );

					
				else:
					$temp = str_replace( '#reg_line#', $reg_line, $temp );					
				endif;
				
				# find branchname
				#
				# if nolocation is true, get branch from that and make room name no location
				if( !empty( $val['ti_noLocation_branch'] ) ):
					$branchName = self::$branchList[$val['ti_noLocation_branch']]['branchDesc'];
					$roomName = 'No location required.';
				else:
					$branchName = self::$branchList[self::$roomContList['id'][$val['ti_roomID']]['branchID']]['branchDesc'];
					$roomName = self::$roomContList['id'][$val['ti_roomID']]['desc'];
				endif;
				
				$temp = str_replace( '#branch#', $branchName, $temp );
				$temp = str_replace( '#room#', $roomName, $temp );
			
				# is there phone?
				if( !empty( $val['reg_phone'] ) ):
					$temp = str_replace( '#phone_line#', $phone_line, $temp );
					$temp = str_replace( '#phoneNumber#', $val['reg_phone'], $temp );
				else:
					$temp = str_replace( '#phone_line#', NULL, $temp );
				endif;
				
				# is there email?
				if( !empty( $val['reg_email'] ) ):
					$temp = str_replace( '#email_line#', $email_line, $temp );
					$temp = str_replace( '#emailAddress#', $val['reg_email'], $temp );
				else:
					$temp = str_replace( '#email_line#', NULL, $temp );
				endif;
				
				
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#searchItem_line#', implode( "\r\n", $final ), $contents );
			
		endif;
		
		echo $contents;
	}
}