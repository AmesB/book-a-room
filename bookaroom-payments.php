<?PHP
class bookaroom_creditCardPayments
{
	public static function checkPaymentForm( $externals ) {
		$error = FALSE;
		if( empty( $externals['email'] ) ):
			$error = "You haven't entered an email address. Please enter an email and try again.";
		elseif( !filter_var( $externals['email'], FILTER_VALIDATE_EMAIL ) ):
			$error = "Please enter a valid email address.";
		endif;	
		
		return $error;
	}
	
	public function finishedSubmission( $form ) {
		global $wpdb;
		
		
		$wpdb->update( "{$wpdb->prefix}bookaroom_reservations", array( 'me_creditCardPaid' => 1, 'me_status' => 'approved' ), array( 'res_id' => $_GET['res_id'] ) );

		
		
	}
	
	private static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'				=> FILTER_SANITIZE_STRING, 
							'res_id'				=> FILTER_SANITIZE_STRING, 
							'hash'					=> FILTER_SANITIZE_STRING, 							
										);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final += $getTemp;
		endif;

		# setup POST variables
		$postArr = array(	'action'				=> FILTER_SANITIZE_STRING, 
							'email'					=> FILTER_SANITIZE_STRING, 
										);
	
	
	
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


		return $final;		
	}

	public static function makeEmailPaymentLink( $isSalt, $res_id )
	{
		$paymentLink = get_option( 'bookaroom_paymentLink' );
		
		if( empty( $paymentLink ) ):
			return NULL;
		endif;
		
		if( empty( $isSalt ) || strlen( $isSalt ) !== 32 ):
			$salt = uniqid(mt_rand(), true);
			$wpdb->update( $table_name, array( 'me_salt' => $salt ), array( 'res_id' => $res_id ) );
		else:
			$salt = $isSalt;
		endif;
	
		$hash = md5( $salt.$res_id );
		
		return "<a href=\"{$paymentLink}?hash={$hash}&res_id={$res_id}\">Click here to pay online with a credit card</a>.";
	}
	
	public static function managePayments() {
		$externals = self::getExternals();
		$contents = NULL;
		
		switch( $externals['action'] ):
			case 'checkForm':
				if( TRUE == ( $error = self::checkPaymentForm( $externals ) ) ):
					$contents = self::showPaymentEmailForm( $externals, $error );
				else:
					self::sendPaymentEmail( $externals['email'] );
					$contents = self::showPaymentEmailFormSuccess( $externals['email'] );
					break;
				endif;
				
				break;
			default:
				$contents = self::showPaymentEmailForm( $externals );
				break;
		endswitch;
		
		return $contents;
		
	}

	public static function returnIncomingValidation( $form )
	{
		global $wpdb;
		$status = NULL;
		
		$externals = self::getExternals();
		
		# check for correct form based on settings URL
		$paymentFormURL = get_option( "bookaroom_paymentLink" );
		
		if( $paymentFormURL !==  get_permalink() ):
			return $form;
		endif;
		
		$hash		= ( !empty( $externals['hash'] ) )		? $externals['hash'] 		: NULL;
		$res_id		= ( !empty( $externals['res_id'] ) )	? $externals['res_id']		: NULL;
		

			
		if( empty( $externals['hash'] ) || empty( $externals['res_id'] ) ):
			$status = 'noHash';
			return $form;
		endif;
	
		# get salt from ticket
		$table_name = $wpdb->prefix . "bookaroom_reservations";

		$resRaw = $wpdb->get_row( "SELECT `res`.`me_contactEmail`, `times`.`ti_startTime`,  `times`.`ti_endTime`, `times`.`ti_roomID`, `res`.`me_nonProfit`, `res`.`me_salt`, `res`.`me_status`, `res`.`me_eventName`, `res`.`ev_title`, `res`.`me_status`, `res`.`me_contactName`, `res`.`me_desc` FROM `{$wpdb->prefix}bookaroom_reservations` as `res` LEFT JOIN `{$wpdb->prefix}bookaroom_times` as `times` ON `res`.`res_id` = `times`.`ti_extID` WHERE `res`.`res_id` = '{$res_id}'", ARRAY_A );

		$salt = $resRaw['me_salt'];
		$newHash = md5( $salt.$res_id );
		
		# check if a valid reservation
		if( $hash !== $newHash ):
			$status = 'error';
		#check if event instead
		elseif( empty( $resRaw['me_eventName'] ) || !empty( $resRaw['ev_title'] ) ):
			# event, not a registration!
			$resStatus = 'This is an event, not a reservation.';
			$status = 'event';
		else:	
			# check payment status
			$status = $resRaw['me_status'];
		endif;		
		
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomCount = count( $roomContList['id'][$resRaw['ti_roomID']]['rooms'] );

		if( $resRaw['me_nonProfit'] == TRUE ):
			$roomDeposit = get_option( 'bookaroom_nonProfitDeposit' );
			$costIncrement =  get_option( 'bookaroom_nonProfitIncrementPrice' );
		else:
			$roomDeposit = get_option( 'bookaroom_profitDeposit' );
			$costIncrement =  get_option( 'bookaroom_profitIncrementPrice' );
		endif;
		
		
		
		$roomPrice = ( ( ( ( strtotime( $resRaw['ti_endTime'] ) - strtotime( $resRaw['ti_startTime'] ) ) / 60 ) / get_option( 'bookaroom_baseIncrement' ) ) * $costIncrement * $roomCount );
		$totalPrice = $roomPrice + $roomDeposit;
		
		# find form entries
		foreach( $form['fields'] as $key => $val ):

			switch( $val['label'] ):
				case 'Status':
					$form['fields'][$key]['defaultValue'] = $status;
					break;
				case 'Room Cost':
					$form['fields'][$key]['basePrice'] = $roomPrice;
					break;
				case 'Deposit':
					$form['fields'][$key]['basePrice'] = $roomDeposit;
					break;
				case 'Total':
					$form['fields'][$key]['basePrice'] = $totalPrice;
					break;
			endswitch;
		endforeach;
		
		
		# replace info in form area for user and ticket information
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );

		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );

		$branch = $branchList[$roomContList['id'][$resRaw['ti_roomID']]['branchID']]['branchDesc'];
		$roomDesc = $roomContList['id'][$resRaw['ti_roomID']]['desc'];


	    foreach( $form['fields'] as &$field ):
			switch( $field['adminLabel'] ):
				case 'email':
						$field['defaultValue'] = $resRaw['me_contactEmail'];
						break;
				
				case 'userName':
						$field['defaultValue'] = $resRaw['me_contactName'];
						break;
				
				case 'regName':
						$field['defaultValue'] = $resRaw['me_eventName'];
						break;
				
				case 'regDesc':
						$field['defaultValue'] = $resRaw['me_desc'];
						break;
				
				case 'regDate':
						$field['defaultValue'] = date( 'l F, jS, Y', strtotime( $resRaw['ti_startTime'] ) );
						break;
				
				case 'regStartTime':
						$field['defaultValue'] = date( 'g:i a', strtotime( $resRaw['ti_startTime'] ) );
						break;
				
				case 'regEndTime':
						$field['defaultValue'] = date( 'g:i a', strtotime( $resRaw['ti_endTime'] ) );
						break;
				
				case 'regRoomDesc':
						$field['defaultValue'] = $roomDesc;
						break;
				
				case 'regBranch':
						$field['defaultValue'] = $branch;
						break;
			endswitch;
			
			if( $field['label'] == 'Reservation Information' ):
				$tempCont = "<h4>Reservation Information</h4>
							<table class=\"tableNice\">
							  <tr>
								<td><strong>#eventName#</strong></td>
							  </tr>
							  <tr>
								<td>#desc#</td>
							  </tr>
							  <tr>
								<td><em>From #startTime# to #endTime# on #date#</em></td>
							  </tr>
							  <tr>
								<td><em>#roomDesc# at #branch#</em></td>
							  </tr>
								<tr>
								<td>Reserved to #userName#</td>
							  </tr>
							</table>";
		
			
				$tempCont = str_replace( '#eventName#', $resRaw['me_eventName'], $tempCont );
				$tempCont = str_replace( '#desc#', $resRaw['me_desc'], $tempCont );
				$tempCont = str_replace( '#userName#', $resRaw['me_contactName'], $tempCont );
				$tempCont = str_replace( '#startTime#', date( 'g:i a', strtotime( $resRaw['ti_startTime'] ) ), $tempCont );
				$tempCont = str_replace( '#endTime#', date( 'g:i a', strtotime( $resRaw['ti_endTime'] ) ), $tempCont );
				$tempCont = str_replace( '#date#', date( 'l, F jS, Y', strtotime( $resRaw['ti_startTime'] ) ), $tempCont );
			
			
				$tempCont = str_replace( '#branch#', $branch, $tempCont );
				$tempCont = str_replace( '#roomDesc#', $roomDesc, $tempCont );
				$field['content'] = $tempCont;
			endif;
		endforeach;
		
		return $form;
	}
		
	public static function sendPaymentEmail( $email ) {
		$filename = BOOKAROOM_PATH . 'templates/payments/paymentEmail.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		# get all reservations from this email
		global $wpdb;
		$final = array();
		
		$sql = "SELECT `res`.`me_salt`, 
					`res`.`res_id`, 
					`res`.`me_contactName`, 
					`res`.`me_eventName`, 
					`res`.`me_contactAddress1`, 
					`res`.`me_contactAddress2`, 
					`res`.`me_contactCity`, 
					`res`.`me_contactState`, 
					`res`.`me_contactZip`, 
					`res`.`me_desc`, 
					`res`.`me_nonProfit`, 
					`res`.`me_creditCardPaid`, 
					`times`.`ti_startTime`, 
					`times`.`ti_endTime`, 
					`times`.`ti_roomID`
					
					
					FROM `{$wpdb->prefix}bookaroom_reservations` AS `res`
					LEFT JOIN `{$wpdb->prefix}bookaroom_times` as `times` ON `res`.`res_id` = `times`.`ti_extID` 
					WHERE `res`.`me_contactEmail` = '{$email}' AND `res`.`me_creditCardPaid` = '0' AND `res`.`me_status` = 'pendPayment'
					ORDER BY `times`.`ti_startTime` ASC"; 
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		# configure email
		
		$some_line = repCon( 'some', $contents );
		$none_line = repCon( 'none', $contents, TRUE );

		$item_line = repCon( 'item', $some_line );
		
		
		# replace email
		$contents = str_replace( '#userEmail#', $email, $contents );
		
		# no outstanding items?
		if( count( $cooked ) == 0 ):
			$contents = str_replace( '#some_line#', $none_line, $contents );
		else:
			require_once( BOOKAROOM_PATH . '/bookaroom-meetings-rooms.php' );
			require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
			require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
			$roomContList = bookaroom_settings_roomConts::getRoomContList();
			$roomList = bookaroom_settings_rooms::getRoomList();
			$branchList = bookaroom_settings_branches::getBranchList( TRUE );
	
			$contents = str_replace( '#some_line#', $some_line, $contents );
			
			$baseIncrement = get_option( 'bookaroom_baseIncrement' );
					
			
			foreach( $cooked as $key => $val ):
				$temp = $item_line;
				
				$temp = str_replace( '#eventName#', $val['me_eventName'], $temp );
				$temp = str_replace( '#desc#', $val['me_desc'], $temp );

				$temp = str_replace( '#startTime#', date( 'g:i a', strtotime( $val['ti_startTime'] ) ), $temp );
				$temp = str_replace( '#endTime#', date( 'g:i a', strtotime( $val['ti_endTime'] ) ), $temp );
				$temp = str_replace( '#date#', date( 'l, F jS, Y', strtotime( $val['ti_startTime'] ) ), $temp );

				#$temp = str_replace( '#eventName#', $val['me_eventName'], $temp );
				$roomCount = count( $roomContList['id'][$val['ti_roomID']]['rooms'] );
				
				if( $val['me_nonProfit'] == TRUE ):
					$costIncrement =  get_option( 'bookaroom_nonProfitIncrementPrice' );
					$deposit = get_option( 'bookaroom_nonProfitDeposit' );
				else:
					$costIncrement =  get_option( 'bookaroom_profitIncrementPrice' );
					$deposit = get_option( 'bookaroom_profitDeposit' );
				endif;
				
				$roomPrice = ( ( ( ( strtotime( $val['ti_endTime'] ) - strtotime( $val['ti_startTime'] ) ) / 60 ) / $baseIncrement ) * $costIncrement * $roomCount );
				$total = $roomPrice + $deposit;
				
				if( empty( $total ) ):
					continue;
				endif;
				$temp = str_replace( '#deposit#', $deposit, $temp );
				$temp = str_replace( '#roomCost#', $roomPrice, $temp );
				$temp = str_replace( '#total#', $total, $temp );
				
				# make room and branch names
				$branch = $branchList[$roomContList['id'][$val['ti_roomID']]['branchID']]['branchDesc'];
				$roomDesc = $roomContList['id'][$val['ti_roomID']]['desc'];

				$temp = str_replace( '#branch#', $branch, $temp );
				$temp = str_replace( '#roomDesc#', $roomDesc, $temp );
				
				
				$temp = str_replace( '#paymentLink#', self::makeEmailPaymentLink( $val['me_salt'], $val['res_id'] ), $temp );
				$final[] = $temp;
			endforeach;
		endif;
		
		$contents = str_replace( '#item_line#', implode( "\r\n", $final ), $contents );
		
		$fromName	= get_option( 'bookaroom_alertEmailFromName' );	
		$fromEmail	= get_option( 'bookaroom_alertEmailFromEmail' );			
		$replyToOnly	= ( true == get_option( 'bookaroom_emailReplyToOnly' ) ) ? "From: {$fromName}\r\nReply-To: {$fromName} <$fromEmail>\r\n" : "From: {$fromName} <$fromEmail>\r\n";
		
		$subject	= 'Book a Room Payments Due';
		
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
		$replyToOnly . 
		'X-Mailer: PHP/' . phpversion();
				
		mail( $email, $subject, $contents, $headers );		
		
		return $contents;
	}
	
	public static function showPaymentEmailForm( $externals, $error = NULL ) {
		$filename = BOOKAROOM_PATH . 'templates/payments/mainIndex.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# error
		$error_line = repCon( 'errorSome', $contents );
		
		if( !empty( $error ) ):
			$contents = str_replace( '#errorSome_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $error, $contents );
		else:
			$contents = str_replace( '#errorSome_line#', NULL, $contents );
		endif;
		
		# replace email 
		$contents = str_replace( '#email#', $externals['email'], $contents );
		
		# replace link
		$contents = str_replace( '#paymentLink#', get_option( "bookaroom_paymentLink" ), $contents );
		
			
		return $contents;
	}
	
	public static function showPaymentEmailFormSuccess( $email ) {
		$filename = BOOKAROOM_PATH . 'templates/payments/paymentEmail_success.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$paymentFormURL = get_option( "bookaroom_paymentLink" );
		
		$contents = str_replace( '#paymentLink#', $paymentFormURL, $contents );
		
		$contents = str_replace( '#userEmail#', $email, $contents );
		
		return $contents;
	}
	
}
?>