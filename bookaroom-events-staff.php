<?PHP
class bookaroom_events_staff
{
	public static function bookaroom_staffCalendar()
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
			case 'searchReturn':
				$_SESSION['bookaroom_temp_search_settings'] = $externals;
				$results = self::getEventList( $externals );
				self::showSearchedEvents( $externals, $results, true );
				break;

			case 'search':
				$_SESSION['bookaroom_temp_search_settings'] = $externals;
				$curTime = getdate( time() );
				$externals['startDate'] = date( 'm/d/Y', mktime( 0, 0, 0, $curTime['mon'], $curTime['mday'], $curTime['year'] ) );
				$externals['endDate'] = date( 'm/d/Y', mktime( 0, 0, 0, $curTime['mon'], $curTime['mday']+31, $curTime['year'] ) -1 );
				self::showSearchedEvents( $externals, array(), true );
				break;

			
			case 'checkReg':
				if( empty( $externals['eventID'] ) or ( $eventInfo = self::checkID( $externals['eventID'] ) ) == false ):
					#self::
					echo 'error';
					break;				
				endif;

				# bad hash?

				if( empty( $_SESSION['bookaroom_RegFormSub'] ) or $externals['bookaroom_RegFormSub'] !== $_SESSION['bookaroom_RegFormSub'] ):
					$errorMSG = 'Either there was a problem processing your form, or you are trying to refresh an already completed form. Please fill out the form again.';
					# check event ID
					$_SESSION['bookaroom_RegFormSub'] = md5( rand( 1, 500000000000 ) );
					unset( $_POST );
					$externals = array( 'fullName' => NULL, 'phone' => NULL, 'email' => NULL, 'notes' => NULL );
					$externals['bookaroom_RegFormSub'] = $_SESSION['bookaroom_RegFormSub'];
					self::viewEvent( $eventInfo, $externals, $errorMSG );
					break;
				endif;
					
				if( ( $errorMSG = self::checkReg( $externals ) ) !== false ):
					self::viewEvent( $eventInfo, $externals, $errorMSG );
					break;
				endif;
				
				self::addRegistration( $externals );
				
				self::viewEvent( $eventInfo, $externals, $errorMSG, true );
				unset( $_SESSION['bookaroom_RegFormSub'] );

				break;
				
			case 'viewEvent':
				# check event ID
				if( empty( $externals['eventID'] ) or ( $eventInfo = self::checkID( $externals['eventID'] ) ) == false ):
					#self::
					echo 'error';
					break;				
				endif;
				$_SESSION['bookaroom_RegFormSub'] = md5( rand( 1, 500000000000 ) );
				
				$externals['bookaroom_RegFormSub'] = $_SESSION['bookaroom_RegFormSub'];
				
				
				self::viewEvent( $eventInfo, $externals );
				break;
				
			default:
				#self::showCalendar( $externals['timestamp'], $externals['filter'], $externals['age'], $externals['category'] );
				self::showCalendar( $externals['timestamp'], $externals['searchTerms'] );
				break;
				
		endswitch;
	}
	
	
	protected static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'				=> FILTER_SANITIZE_STRING, 
							'eventID'				=> FILTER_SANITIZE_STRING, 
							'timestamp'				=> FILTER_SANITIZE_STRING, 
							'filter'				=> FILTER_SANITIZE_STRING, 
							'age'					=> FILTER_SANITIZE_STRING, 
							'category'				=> FILTER_SANITIZE_STRING,
							'searchTerms'			=> FILTER_SANITIZE_STRING,
							 );

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = $getTemp;
			
		# setup POST variables
		$postArr = array(	'published'				=> FILTER_SANITIZE_STRING,
							'searchTerms'			=> FILTER_SANITIZE_STRING,
							'ageGroup'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'categoryGroup'				=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'branchID'				=> FILTER_SANITIZE_STRING,
							'startDate'				=> FILTER_SANITIZE_STRING,
							'endDate'				=> FILTER_SANITIZE_STRING,
							'action'				=> FILTER_SANITIZE_STRING,
							'bookaroom_RegFormSub'		=> FILTER_SANITIZE_STRING, 
							'eventID'				=> FILTER_SANITIZE_STRING, 
							'timestamp'				=> FILTER_SANITIZE_STRING,
							'fullName'				=> FILTER_SANITIZE_STRING,
							'phone'					=> FILTER_SANITIZE_STRING,
							'email'					=> FILTER_SANITIZE_STRING,
							'notes'					=> FILTER_SANITIZE_STRING,
							'roomChecked'			=> array(	'filter'    => FILTER_SANITIZE_STRING,
                           										'flags'     => FILTER_REQUIRE_ARRAY ) );
	

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
	
	protected static function showCalendar( $timestampRaw = NULL, $filter = NULL, $age = NULL, $category = NULL )
	{
		#s$wpdb = new wpdb($settings['events_bookaroom_db_username'], $settings['events_bookaroom_db_password'], $settings['events_bookaroom_db_database'], $settings['events_bookaroom_db_host']);


		$filename = BOOKAROOM_PATH . 'templates/events/calendar.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$monthEvents = self::getMonthEvents( $timestampRaw, $filter, $age, $category );
		
		# include CSS
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# filters
		##################################
		$filter_line = repCon( 'filter', $contents );
		
		if( empty( $age ) and empty( $category ) ):
			$contents = str_replace( '#filter_line#', NULL, $contents );
		else:
			$contents = str_replace( '#filter_line#', $filter_line, $contents );
			
			$ageFilter_line = repCon( 'ageFilter', $contents );
			$catFilter_line = repCon( 'catFilter', $contents );
			
			if( empty( $age ) ):
				$contents = str_replace( '#ageFilter_line#', NULL, $contents );
			else:
				$contents = str_replace( '#ageFilter_line#', $ageFilter_line, $contents );
				$ageDisp = explode( ',', $age );
				$ageDisp = implode( ', ', $ageDisp );
				$contents = str_replace( '#ageList#', ucwords( $ageDisp ), $contents );
			endif;

			if( empty( $category ) ):
				$contents = str_replace( '#catFilter_line#', NULL, $contents );
			else:
				$contents = str_replace( '#catFilter_line#', $catFilter_line, $contents );
				$catDisp = explode( ',', $category );
				$catDisp = implode( ', ', $catDisp );
				$contents = str_replace( '#catList#', ucwords( $catDisp ), $contents );
			endif;			
		endif;
		
		# check for valid timestamp
		if( empty( $timestampRaw ) or FALSE == ((string) (int) $timestampRaw === $timestampRaw) && ($timestampRaw <= PHP_INT_MAX) && ($timestampRaw >= ~PHP_INT_MAX) ):
			$timestampRaw = time();
		endif;
		
		$contents = str_replace( '#timestamp#', $timestampRaw, $contents );
		
		$timestampArr = getdate( $timestampRaw );
		$timestamp = mktime( 0, 0, 0, $timestampArr['mon'], $timestampArr['mday'], $timestampArr['year'] );

		############################################################
		#
		# show the calendar
		#
		############################################################
		
		# current year
		$curYearDisp = date( 'Y', $timestamp );
		$contents = str_replace( '#curYear#', $curYearDisp, $contents );
		
		# current month
		$curMonthDisp = date( 'F', $timestamp );
		$contents = str_replace( '#curMonth#', $curMonthDisp, $contents );
		
		# month list
		############################################################
		
		# previous year
		$prevYearRaw = mktime( 0, 0, 0, 1, 1, $timestampArr['year'] - 1 );
		$prevYearDisp = date( 'Y', $prevYearRaw );
		$contents = str_replace( '#prevYearTimestamp#', $prevYearRaw, $contents );
		$contents = str_replace( '#prevYearDisp#', $prevYearDisp, $contents );
				
		# next year
		$nextYearRaw = mktime( 0, 0, 0, 1, 1, $timestampArr['year'] + 1 );
		$nextYearDisp = date( 'Y', $nextYearRaw );
		$contents = str_replace( '#nextYearTimestamp#', $nextYearRaw, $contents );
		$contents = str_replace( '#nextYearDisp#', $nextYearDisp, $contents );
		
		# age and cat
		$contents = str_replace( '#age#', $age, $contents );
		$contents = str_replace( '#category#', $category, $contents );
		
		# make month list
		############################################################
		
		$realCurMonth = $timestampArr['mon'];
		$monthDisp_line		= repCon( 'monthDisp', $contents );
		$monthDispSel_line	= repCon( 'monthDispSel', $contents, TRUE );
		
		$final = array();
		$temp = NULL;
		
		for( $m=1; $m<=12; $m++):
			# make display and timestamp for current month
			$curMonthTimestamp = mktime( 0, 0, 0, $m, 1, $timestampArr['year'] );
			$curMonthDisp = date( 'M', $curMonthTimestamp );

			# check if this is the right month
			if( $realCurMonth == $m ):
				$temp = $monthDispSel_line;
			else:
				$temp = $monthDisp_line;
				# for link, add link value
				$temp = str_replace( '#monthTimestamp#', $curMonthTimestamp, $temp );				
			endif;
			
			$temp = str_replace( '#monthDisp#', $curMonthDisp, $temp );
			
			$final[] = $temp;
			
		endfor;
		
		$contents = str_replace( '#monthDisp_line#', implode( "\r\n", $final ), $contents );

		############################################################
		#
		# week navigation
		#
		############################################################
		
		# find how many weeks in the month.
		
		# days in month
		$daysInMonth = date( 't', $timestamp );
		
		# add offset
		$dayOffset = date( 'w', $timestamp );

		$weeks = ceil( ( $daysInMonth + $dayOffset ) / 7 );
		$contents = str_replace( '#weeks#', $weeks, $contents );
		
		$temp = NULL;
		$final = array();
		$weekNav_line = repCon( 'weekNav', $contents );
		
		for( $w=1; $w<=$weeks; $w++ ):
			# find first day of week
			switch( $w ):
				case 1:
					$startDay = 1;
					$endDay = 7 - $dayOffset;
					$nextStart = $endDay+1;					
					break;
					
				default:
					$startDay = $nextStart;
					$endDay = $startDay + 6;
					$nextStart = $endDay+1;
					break;
				
				case $weeks:
					$startDay = $nextStart;
					$endDay = $daysInMonth;
					break;
			endswitch;

			# make week nav line
			
			$temp = $weekNav_line;

			
			$startDayDisp = date( 'jS', mktime( 0, 0, 0, $timestampArr['mon'], $startDay, $timestampArr['year'] ) );
			$endDayDisp = date( 'jS', mktime( 0, 0, 0, $timestampArr['mon'], $endDay, $timestampArr['year'] ) );
			
			if( $startDayDisp !== $endDayDisp ):
				$temp = str_replace( '#dateRange#', $startDayDisp . ' - '. $endDayDisp, $temp );
			else:
				$temp = str_replace( '#dateRange#', $startDayDisp, $temp );
			endif;
			
			$temp = str_replace( '#dateLink#', $startDay, $temp );
			$final[] = $temp;

		endfor;
		
		$contents = str_replace( '#weekNav_line#', implode( "\r\n", $final ), $contents );

		############################################################
		#
		# day list
		#
		############################################################
		
		$day_line = repCon( 'day', $contents );
		$noEvents_line = repCon( 'noEvents', $day_line, true );
		$event_line = repCon( 'event', $day_line );
		
		$temp = NULL;
		$final = array();

		for( $d=1; $d<=$daysInMonth; $d++ ):
			# make timestamp
			$dateTimestamp = mktime( 0, 0, 0, $timestampArr['mon'], $d, $timestampArr['year'] );
			
			$temp = $day_line;
			$temp = str_replace( '#curDay#', $d, $temp );
			$temp = str_replace( '#dayName#',  date( 'l', $dateTimestamp ), $temp );
			$temp = str_replace( '#daySecondary#', date( 'F jS', $dateTimestamp ), $temp );
			
			# make mini calendar

			$temp = self::makeMiniCalendar( $temp, $dateTimestamp );
			
			# fina events
			if( empty( $monthEvents[$d] ) ):
				$temp = str_replace( '#event_line#', $noEvents_line, $temp );
			else:
				$eventTemp = null;
				$eventFinal = array();
				foreach( $monthEvents[$d] as $eventNow ):
					$eventTemp = $event_line;
					# times
					$startTime = date( 'g:i a', strtotime( $eventNow['ti_startTime'] ) );
					$endTime = date( 'g:i a', strtotime( $eventNow['ti_endTime'] ) );
					if( $startTime == '12:00 am' ):
						$eventTemp = str_replace( '#times#', 'All Day', $eventTemp );
					else:
						$eventTemp = str_replace( '#times#', $startTime.' - '.$endTime, $eventTemp );
					endif;
					$eventTemp = str_replace( '#eventName#', $eventNow['ev_title'], $eventTemp );
					$eventTemp = str_replace( '#eventDesc#', $eventNow['ev_desc'], $eventTemp );					
					$eventTemp = str_replace( '#eventID#', $eventNow['ti_id'], $eventTemp );
					if( empty( $eventNow['ti_extraInfo'] ) ):
						$eventTemp = str_replace( '#extraInfo#', NULL, $eventTemp );
					else:
						$eventTemp = str_replace( '#extraInfo#', '<br />'.$eventNow['ti_extraInfo'], $eventTemp );
					endif;
									
					# registration
					if( $eventNow['ev_regType'] !== 'yes' ):
						$eventTemp = str_replace( '#registration#', 'No registration required', $eventTemp );
					else:

			

						# get reg info
						$regInfo = self::getRegInfo( $eventNow['ti_id'] );


						# since they can register, first see if it's not full, then show the form
						if( count( $regInfo ) < $eventNow['ev_maxReg'] ):
							$eventTemp = str_replace( '#registration#', 'Registration required, slots available.', $eventTemp );							
						# else, if number is under total reg + waiting list, show waiting list
						elseif( count( $regInfo ) < ( $eventNow['ev_maxReg'] + $eventNow['ev_waitingList'] ) ):
							$eventTemp = str_replace( '#registration#', 'Registration required. Program is full but waiting list is open.', $eventTemp );							
						# else, show waiting list full
						else:
							$eventTemp = str_replace( '#registration#', 'Registration required. Program is full.', $eventTemp );	
						endif;



					
					endif;
					
					$eventFinal[] = $eventTemp;
				endforeach;
				$temp = str_replace( '#event_line#', implode( "\r\n", $eventFinal ), $temp );
			endif;
				
			$final[] = $temp;
		endfor;
		
		$contents = str_replace( '#day_line#', implode( "\r\n", $final ), $contents );
		
		
		###############################################
		# Search functions
		###############################################
		#
		
		$contents = str_replace( '#searchTerms#', $filter, $contents );
		
		echo $contents;
		
	}

	protected static function getMonthEvents( $timestamp, $filter = NULL, $age = NULL, $category = NULL )
	{
		global $wpdb;
		
		if( empty( $timestamp )):
			$timestamp = time();
		endif;

		$catList = self::getCatList();
		$ageList = self::getAgeList();
		
		# find first of the month
		$monthFirst = date( 'Y-m-01', $timestamp ) . ' 00:00:00';
		$monthLast = date( 'Y-m-t', $timestamp ) . ' 23:59:59';
		
		$where = array();
		
		# is there an age filter?
		if( !empty( $age ) ):
			$curAgeList = explode( ',', $age );
		
			if( !is_null( $curAgeList ) ):
				array_walk( $curAgeList, function( &$value, $index ) use ( $ageList ) {
					$value = trim( $value );

					if( ( $foundIt = array_search( strtolower( $value ), $ageList['all'] ) ) ):
						$value = "'{$foundIt}'";
					else:
						$value = NULL;
					endif;
				} );
				
				$curAgeList = array_filter( $curAgeList );
				
				if( count( $curAgeList ) > 0 ):
					$where[] = "`ti`.`ti_extID` IN ( SELECT `ea`.`ea_eventID` FROM `{$wpdb->prefix}bookaroom_eventAges` as `ea` WHERE `ea`.`ea_ageID` IN (".implode(',', $curAgeList).") )";
				endif;
			endif;
		endif;
				
		# is there an category filter?
		if( !empty( $category ) ):
			$curCatList = explode( ',', $category );
			
			array_walk( $curCatList, function( &$value, $index ) use ( $catList ) {
				$value = trim( $value );
	
				if( ( $foundIt = array_search( strtolower( $value ), $catList['all'] ) ) ):
					$value = "'{$foundIt}'";
				else:
					$value = NULL;
				endif;
			} );
			
			$curCatList = array_filter( $curCatList );
			
			if( count( $curCatList ) > 0 ):
				$where[] = "`ti`.`ti_extID` IN ( SELECT `ec`.`ec_eventID` FROM `{$wpdb->prefix}bookaroom_eventCats` as `ec` WHERE `ec`.`ec_catID` IN (".implode(',', $curCatList).") )";
			endif;
		endif;
			

		# search term
		if( !empty( $filter ) ):
			$where[] = " MATCH ( `ev`.`ev_desc`, `ev`.`ev_presenter`, `ev`.`ev_privateNotes`, `ev`.`ev_publicEmail`, `ev`.`ev_publicName`, `ev`.`ev_submitter`, `ev`.`ev_title`, `ev`.`ev_website`, `ev`.`ev_webText` ) AGAINST ('{$filter}' IN NATURAL LANGUAGE MODE )";
			$scoreWhere = "`score` DESC, ";
		else:
			$scoreWhere = NULL;
		endif;

		if( count( $where ) > 0 ):
			$whereFinal = ' AND '.implode( ' AND ', $where );
		else:
			$whereFinal = NULL;
		endif;
						
		$sql = "SELECT MATCH ( `ev`.`ev_desc`, `ev`.`ev_presenter`, `ev`.`ev_privateNotes`, `ev`.`ev_publicEmail`, `ev`.`ev_publicName`, `ev`.`ev_submitter`, `ev`.`ev_title`, `ev`.`ev_website`, `ev`.`ev_webText` ) AGAINST ('{$filter}' IN NATURAL LANGUAGE MODE ) as `score`, 
		`ti`.`ti_id`, `ti`.`ti_extID`, `ti`.`ti_startTime`, `ti`.`ti_endTime`, `ti`.`ti_roomID`, `ev`.`ev_desc`, `ev`.`ev_maxReg`, `ev`.`ev_waitingList`, `ev`.`ev_presenter`, `ev`.`ev_privateNotes`, `ev`.`ev_publicEmail`, `ev`.`ev_publicName`, `ev`.`ev_publicPhone`, `ev`.`ev_noPublish`, `ev`.`ev_regStartDate`, `ev`.`ev_regType`, `ev`.`ev_submitter`, `ev`.`ev_title`, `ev`.`ev_website`, `ev`.`ev_webText`, `ti`.`ti_extraInfo`, 
				group_concat(DISTINCT `ea`.`ea_ageID` separator ', ') as `ageID`, group_concat(DISTINCT `ages`.`age_desc` separator ', ') as `ages`, 
				group_concat(DISTINCT `ec`.`ec_catID` separator ', ') as `catID`, group_concat(DISTINCT `cats`.`categories_desc` separator ', ') as `cats`
				FROM  `{$wpdb->prefix}bookaroom_times` as `ti`
				LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` as `ev` ON `ti`.`ti_extID` = `ev`.`res_id`
				LEFT JOIN `{$wpdb->prefix}bookaroom_eventAges` as `ea` on `ea`.`ea_eventID` = `ti`.`ti_extID`
				LEFT JOIN `{$wpdb->prefix}bookaroom_event_ages` as `ages` on `ea`.`ea_ageID` = `ages`.`age_id`
				
				LEFT JOIN `{$wpdb->prefix}bookaroom_eventCats` as `ec` on `ec`.`ec_eventID` = `ti`.`ti_extID`
				LEFT JOIN `{$wpdb->prefix}bookaroom_event_categories` as `cats` on `ec`.`ec_catID` = `cats`.`categories_id`
				
				WHERE `ti`.`ti_type` =  'event'
				AND `ti`.`ti_startTime` >=  '{$monthFirst}'
				AND `ti`.`ti_endTime` <=  '{$monthLast}' 
				{$whereFinal} 
				AND `ev`.`ev_regType` = 'staff' 
				GROUP BY `ti`.`ti_id` 
				ORDER BY {$scoreWhere}`ti`.`ti_startTime`";

		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		$final = array();
		
		foreach( $cooked as $val ):
			$dateInfo = getdate( strtotime( $val['ti_startTime'] ) );
			$final[$dateInfo['mday']][] = $val;
		endforeach;

		return $final;
	}
	
	protected static function getAgeList( )
	{
		global $wpdb;
		
		$sql = "SELECT `age_id`, `age_desc`, `age_order`, `age_active` FROM `{$wpdb->prefix}bookaroom_event_ages` ";
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		$final = array( 'active' => array(), 'inactive' => array(), 'all' => array(), 'status' => array() , 'order' => array() );
			
		foreach( $cooked as $key => $val ):
			$active = ( empty( $val['age_active'] ) ) ? 'inactive' : 'active';
			$final[$active][$val['age_id']]		= $val;
			$final['all'][$val['age_id']]		= strtolower( $val['age_desc'] );
			$final['status'][$val['age_id']]	= $val['age_active'];
			$final['order'][$val['age_order']]	= $val['age_id'];
		endforeach;
		
		ksort( $final['order'] );
		
		return $final;
	}
	
	public static function getCatList( )
	{
		global $wpdb;
		
		$sql = "SELECT `categories_id`, `categories_desc`, `categories_order`, `categories_active` FROM `{$wpdb->prefix}bookaroom_event_categories` ";
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		$final = array( 'active' => array(), 'inactive' => array(), 'all' => array(), 'status' => array(), 'order' => array() );
			
		foreach( $cooked as $key => $val ):
			$active = ( empty( $val['categories_active'] ) ) ? 'inactive' : 'active';
			$final[$active][$val['categories_id']]		= $val;
			$final['all'][$val['categories_id']]		= strtolower( $val['categories_desc'] );
			$final['status'][$val['categories_id']]	= $val['categories_active'];
			$final['order'][$val['categories_order']]	= $val['categories_id'];
		endforeach;
		
		ksort( $final['order'] );
		
		return $final;
	}
	
	protected static function makeMiniCalendar( $contents, $timestamp )
	{
		$smallCalDay_line = repCon( 'smallCalDay', $contents );
		$smallCal_line = repCon( 'smallCal', $contents );
		
		
		# find first of month
		$timestampArr = getdate( $timestamp );
		$monthTimestamp = mktime( 0, 0, 0, $timestampArr['mon'], 1, $timestampArr['year'] );
		
		# days in month
		$daysInMonth = date( 't', $monthTimestamp );
		
		# add offset
		$dayOffset = date( 'w', $monthTimestamp );

		$weeks = ceil( ( $daysInMonth + $dayOffset ) / 7 );
		
		$calendarArr = array();
		$count = 1;
		
		$calendarArr = array();
		
		$weekFinal = array();
		$weekTemp = NULL;
		
		for( $w=1; $w<=$weeks; $w++ ):
			$weekTemp = $smallCal_line;
			
			$dayTemp = NULL;
			$dayFinal = array();
				
			switch( $w ):
				case 1:
					for( $d=0; $d< $dayOffset; $d++):
						$dayTemp = $smallCalDay_line;
						$dayTemp = str_replace( '#dayDisp#', '', $dayTemp );
						$dayTemp = str_replace( '#dayClass#', ' noDay', $dayTemp );
						$dayFinal[] = $dayTemp;
					endfor;

					for( $d = $dayOffset; $d <=6; $d++):
						$dayTemp = $smallCalDay_line;
						$dayTemp = str_replace( '#dayDisp#', $count, $dayTemp );
						
						if( $timestampArr['mday'] == $count ):
							$dayTemp = str_replace( '#dayClass#', ' selDay', $dayTemp );
						else:
							$dayTemp = str_replace( '#dayClass#', NULL, $dayTemp );
						endif;
						$count++;
						$dayFinal[] = $dayTemp;
					endfor;					
					
					$endDay = 7 - $dayOffset;
					$nextStart = $endDay+1;		
					break;
					
				default:
					$startDay = $nextStart;
					$endDay = $startDay + 6;
					$nextStart = $endDay+1;
					
					for( $d = $startDay; $d <= $startDay+6; $d++):
						$dayTemp = $smallCalDay_line;
						$dayTemp = str_replace( '#dayDisp#', $count, $dayTemp );
						if( $timestampArr['mday'] == $d ):
							$dayTemp = str_replace( '#dayClass#', ' selDay', $dayTemp );
						else:
							$dayTemp = str_replace( '#dayClass#', NULL, $dayTemp );
						endif;
						$count++;
						$dayFinal[] = $dayTemp;
					endfor;		
					break;
				
				case $weeks:
					$weekLeft = 7;
					for( $d = $count; $d <=$daysInMonth; $d++):
						$dayTemp = $smallCalDay_line;
						$dayTemp = str_replace( '#dayDisp#', $count, $dayTemp );
						
						if( $timestampArr['mday'] == $count ):
							$dayTemp = str_replace( '#dayClass#', ' selDay', $dayTemp );
						else:
							$dayTemp = str_replace( '#dayClass#', NULL, $dayTemp );
						endif;
						$count++;
						$weekLeft--;
						$dayFinal[] = $dayTemp;
						
					endfor;
					
					for( $d=1; $d <= $weekLeft; $d++):
						$dayTemp = $smallCalDay_line;
						$dayTemp = str_replace( '#dayDisp#', '', $dayTemp );
						$dayTemp = str_replace( '#dayClass#', ' noDay', $dayTemp );
						$dayFinal[] = $dayTemp;
					endfor;
					
					$startDay = $nextStart;
					$endDay = $daysInMonth;
					break;
			endswitch;
			
			$weekFinal[] = implode( "\r\n", $dayFinal );
			$final[] = str_replace( '#smallCalDay_line#', implode( $weekFinal ), $smallCal_line );
			$weekFinal = array();
		endfor;
		
		
		$contents = str_replace( '#smallCal_line#', implode( "\r\n", $final ), $contents );
		
		return $contents;	
	}
	
	protected static function getRegInfo( $eventID )
	{
		global $wpdb;
		
		$sql = "	SELECT `reg_id`, `reg_fullName`, `reg_phone`, `reg_email`, `reg_notes`, `reg_dateReg` 
					FROM `{$wpdb->prefix}bookaroom_registrations` 
					WHERE `reg_eventID` = '{$eventID}' 
					ORDER BY `reg_dateReg`";
					
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		return $cooked;
		
	}
	
	protected static function checkID( $eventID )
	{
		global $wpdb;
				
		$sql = "SELECT `ti`.`ti_id`, `ti`.`ti_type`, `ti`.`ti_extID`, `ti`.`ti_created`, `ti`.`ti_startTime`, `ti`.`ti_endTime`, `ti`.`ti_roomID`, 
`res`.`res_id`, `res`.`res_created`, `res`.`ev_desc`, `res`.`ev_maxReg`, `res`.`ev_presenter`, `res`.`ev_privateNotes`, `res`.`ev_publicEmail`, `res`.`ev_publicName`, `res`.`ev_publicPhone`, `res`.`ev_noPublish`, `res`.`ev_regStartDate`, `res`.`ev_regType`, `res`.`ev_submitter`, `res`.`ev_title`, `res`.`ev_website`, `res`.`ev_webText`, `res`.`ev_waitingList`, 
		`ti`.`ti_noLocation_branch`, `ti`.`ti_extraInfo`, 
		group_concat(DISTINCT `ea`.`ea_ageID` separator ', ') as `ageID`, group_concat(DISTINCT `ages`.`age_desc` separator ', ') as `ages`, 
		group_concat(DISTINCT `ec`.`ec_catID` separator ', ') as `catID`, group_concat(DISTINCT `cats`.`categories_desc` separator ', ') as `cats`
		FROM `{$wpdb->prefix}bookaroom_times` AS `ti` 
		LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` as `res` ON `ti`.`ti_extID` = `res`.`res_id` 
		LEFT JOIN `{$wpdb->prefix}bookaroom_eventAges` as `ea` on `ea`.`ea_eventID` = `ti`.`ti_extID`
		LEFT JOIN `{$wpdb->prefix}bookaroom_event_ages` as `ages` on `ea`.`ea_ageID` = `ages`.`age_id`
		
		LEFT JOIN `{$wpdb->prefix}bookaroom_eventCats` as `ec` on `ec`.`ec_eventID` = `ti`.`ti_extID`
		LEFT JOIN `{$wpdb->prefix}bookaroom_event_categories` as `cats` on `ec`.`ec_catID` = `cats`.`categories_id`		
		WHERE `ti`.`ti_type` = 'event' AND `ti`.`ti_id` = '{$eventID}'
		
		GROUP BY `ti`.`ti_id`";
		
		
		$eventInfo = $wpdb->get_results( $sql, ARRAY_A );

		if( $wpdb->num_rows == 0 ):
			return FALSE;
		endif;

		return $eventInfo[0];
		
	}

	protected static function viewEvent( $eventInfo, $externals, $errorMSG = NULL, $isSuccess = false )
	{
		# load template		
		$filename = BOOKAROOM_PATH . 'templates/events/viewEvent.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		global $wpdb;
		
		$ageList		= self::getAgeList();
		$catList		= self::getCatList();

		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
				
		$contents = str_replace('#eventTitle#', $eventInfo['ev_title'], $contents );
		$contents = str_replace('#eventDesc#', $eventInfo['ev_desc'], $contents );
		
		if( empty( $eventInfo['ev_desc'] ) ):
			$contents = str_replace('#extraInfo#', NULL, $contents );			
		else:
			$contents = str_replace('#extraInfo#', '<br />'.$eventInfo['ti_extraInfo'], $contents );
		endif;
		
		$contents = str_replace('#eventDate#', date( 'l, M. jS', strtotime( $eventInfo['ti_startTime'] ) ), $contents );

		# branch and room
		if( !empty( $eventInfo['ti_noLocation_branch'] ) ):
			$contents = str_replace('#eventBranch#', $branchList[$eventInfo['ti_noLocation_branch']]['branchDesc'], $contents );
			$contents = str_replace('#eventRoom#', 'No location specified', $contents );
		else:	
			$contents = str_replace('#eventBranch#', $branchList[$roomContList['id'][$eventInfo['ti_roomID']]['branchID']]['branchDesc'], $contents );
			$contents = str_replace('#eventRoom#', $roomContList['id'][$eventInfo['ti_roomID']]['desc'], $contents );
		endif;
		

		$contents = str_replace( '#eventAges#', $eventInfo['ages'], $contents );
		
		# categories
		$contents = str_replace( '#eventCats#', $eventInfo['cats'], $contents );
		
		
		############################
		
		$startTime = date( 'g:i a', strtotime( $eventInfo['ti_startTime'] ) );
		$endTime = date( 'g:i a', strtotime( $eventInfo['ti_endTime'] ) );
		
		if( $startTime == '12:00 am' ):
			$contents = str_replace( '#eventTime#', 'All Day', $contents );			
		else:
			$contents = str_replace( '#eventTime#', $startTime.' - '.$endTime, $contents );
		endif;
		
		
		#
		# Presenter info
		#
		#####################################
		$fullPresenter_line = repCon( 'fullPresenter', $contents );
		
		if( empty( $eventInfo['ev_presenter'] ) and empty( $eventInfo['ev_publicName'] ) and empty( $eventInfo['ev_publicEmail'] ) and empty( $eventInfo['ev_publicPhone'] ) and empty( $eventInfo['ev_website'] ) ):
			$contents = str_replace( '#fullPresenter_line#', NULL, $contents );
		else:
			$contents = str_replace( '#fullPresenter_line#', $fullPresenter_line, $contents );
			
			
			$presenter_line = repCon( 'presenter', $contents );
			if( empty( $eventInfo['ev_presenter'] ) ):
				$contents = str_replace('#presenter_line#', NULL, $contents );			
			else:
				$contents = str_replace('#presenter_line#', $presenter_line, $contents );
				$contents = str_replace('#eventPresenter#', $eventInfo['ev_presenter'], $contents );
			endif;
			
			$count = 0;
			$publicName_line = repCon( 'publicName', $contents );
			if( empty( $eventInfo['ev_publicName'] ) ):
				$contents = str_replace('#publicName_line#', NULL, $contents );			
			else:
				$contents = str_replace('#publicName_line#', $publicName_line, $contents );
				$contents = str_replace('#eventContact#', $eventInfo['ev_publicName'], $contents );
				$count++;
			endif;
	
			$publicEmail_line = repCon( 'publicEmail', $contents );
			if( empty( $eventInfo['ev_publicEmail'] ) ):
				$contents = str_replace('#publicEmail_line#', NULL, $contents );			
			else:
				$contents = str_replace('#publicEmail_line#', $publicEmail_line, $contents );
				$contents = str_replace('#eventEmail#', $eventInfo['ev_publicEmail'], $contents );
				$count++;
			endif;
			
			$phone_line = repCon( 'phone', $contents );
			
			if( empty( $eventInfo['ev_publicPhone'] ) ):
				$contents = str_replace('#phone_line#', NULL, $contents );			
			else:
				$contents = str_replace('#phone_line#', $phone_line, $contents );
				$contents = str_replace('#eventPhone#', $eventInfo['ev_publicPhone'], $contents );
				$count++;
			endif;
							
			$website_line = repCon( 'website', $contents );
			if( empty( $eventInfo['ev_website'] ) ):
				$contents = str_replace('#website_line#', NULL, $contents );			
			else:
				$contents = str_replace('#website_line#', $website_line, $contents );
				if( empty( $eventInfo['ev_webText'] ) ):
					$eventInfo['ev_webText'] = $eventInfo['ev_website'];
				endif;
				$contents = str_replace('#eventWebsite#', $eventInfo['ev_website'], $contents );
				$contents = str_replace('#eventWebtext#', $eventInfo['ev_webText'], $contents );
				$count++;
			endif;
		endif;
		
		
		#
		# registration
		#
		####################################
		
		$error_line			= repCon( 'error', $contents );
		$reg_line			= repCon( 'reg', $contents );
		$waitingList_line	= repCon( 'waitingList', $contents, true );
		$noReg_line			= repCon( 'noReg', $contents, true );
		$waitingFull_line	= repCon( 'waitingFull', $contents, true );
		$success_line		= repCon( 'success', $contents, true );
		

		# if no reg, remove error line and reg line
		if( $isSuccess == true ):
			$contents = str_replace( '#error_line#', NULL, $contents );
			$contents = str_replace( '#reg_line#', $success_line, $contents );
			
			$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['phone'] );
			if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
			$externals['phone'] = "(" . substr($cleanPhone, 0, 3) . ") " . substr($cleanPhone, 3, 3) . "-" . substr($cleanPhone, 6);

			$goodArr = array( 'fullName', 'bookaroom_RegFormSub', 'phone', 'email', 'notes' );

			foreach( $goodArr as $val ):
				$contents = str_replace( "#{$val}#", $externals[$val], $contents );
			endforeach;


		elseif( $eventInfo['ev_regType'] !== 'staff' ):
			$contents = str_replace( '#error_line#', NULL, $contents );
			$contents = str_replace( '#reg_line#', $noReg_line, $contents );
		else:

			# get reg info
			$regInfo = self::getRegInfo( $eventInfo['ti_id'] );

		
			$doReg = false;
			# since they can register, first see if it's not full, then show the form
			if( count( $regInfo ) < $eventInfo['ev_maxReg'] ):
				$contents = str_replace( '#reg_line#', $reg_line, $contents );
				$doReg = true;				
			# else, if number is under total reg + waiting list, show waiting list
			elseif( count( $regInfo ) < ( $eventInfo['ev_maxReg'] + $eventInfo['ev_waitingList'] ) ):
				$contents = str_replace( '#reg_line#', $waitingList_line, $contents );
				$doReg = true;
			# else, show waiting list full
			else:
				$contents = str_replace( '#reg_line#', $waitingFull_line, $contents );
				$contents = str_replace( '#error_line#', NULL, $contents );
			endif;
			
			if( $doReg == true  ):
				# fill in the blanks
				$goodArr = array( 'fullName', 'bookaroom_RegFormSub', 'phone', 'email', 'notes' );
				
				foreach( $goodArr as $val ):
					$contents = str_replace( "#{$val}#", $externals[$val], $contents );
				endforeach;
			endif;

		endif;
		
		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );

		# error messsage?	
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );			
		endif;
		
		echo $contents;
	}

	protected static function checkReg( $externals )
	{
		$error = array();
		
		# check for empty values
		if( empty( $externals['fullName'] ) ):
			$error[] = 'You must enter the full name of the person who is registering.';
		endif;
		
		if( empty( $externals['phone'] ) and empty( $externals['email'] ) ):
			$error[] = 'You must enter contact information; either a phone number or email address where you can be reached.';			
		else:
			if( !empty( $externals['phone'] ) ):
				$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['phone'] );
				if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
				if( !is_numeric( $cleanPhone ) || strlen( $cleanPhone ) !== 10 ):
					$error[] = 'You must enter a valid phone number.';
				endif;
				
			endif;
			
			if( !empty( $externals['email'] ) and !filter_var( $externals['email'], FILTER_VALIDATE_EMAIL ) ):
				$error[] = "Please enter a valid email address.";
			endif;
		endif;
		
		if( count( $error )!== 0 ):
			return implode( "<br />", $error );
		else:
			return false;
		endif;	
		
		
	}
	
	protected static function addRegistration( $externals )
	{
		global $wpdb;
			
		if( empty( $externals['phone'] ) ):
			$cleanPhone = NULL;
		else:
			$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['phone'] );
			if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );

			$cleanPhone = "(" . substr($cleanPhone, 0, 3) . ") " . substr($cleanPhone, 3, 3) . "-" . substr($cleanPhone, 6);	
		endif;
		
		$final = $wpdb->insert( $wpdb->prefix . "bookaroom_registrations",
			array( 	'reg_eventID' => $externals['eventID'], 
					'reg_fullname' => $externals['fullName'], 
					'reg_phone' => $cleanPhone, 
					'reg_email' => $externals['email'], 
					'reg_notes' => $externals['notes'], 					
					 ) );
		
	}
		
}
?>