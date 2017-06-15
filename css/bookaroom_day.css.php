<?php
#header("Content-type: text/css");
$selectedColor	= $_GET['selectedColor'];
print_r( $_GET );
#$selectedFont	= get_option( 'bookaroom_setupFont' );
#$reservedColor	= get_option( 'bookaroom_reservedColor' );
#$reservedFont	= get_option( 'bookaroom_reservedFont' );

?>
#hoursTable .calHoursSetup td {
	background: <?php echo $selectedColor; ?>;	
    color: <?php echo $selectedFont; ?>;	
}

#hoursTable .calHoursReserved td {
	background: <?php echo $reservedColor; ?>;	
    color: <?php echo $reservedFont; ?>;	
}
/* ----------------------------------------- */
#botRow
{
	border-spacing: 10px;
	display: table;
}
#botRow .col1
{
	border: thin solid #666;
	border-radius: 20px;
	display: table-cell;
	vertical-align: top;
	width: 33%;
}
#botRow .col2
{
	border-radius: 20px;
	display: table-cell;
	float: right;
	margin-bottom: -20px;
	vertical-align: top;
	width: 100%;
}
#botRow .col2 .options
{
	margin: 0px;
	padding: 0px;
	vertical-align: top;
}
#botRow .header
{
	font-size: 1.3em;
	font-weight: bold;
}
#botRow .instructions
{
	background: #EEE;
	border-bottom-color: #666;
	border-bottom-left-radius: 12px;
	border-bottom-right-radius: 12px;
	border-bottom-style: solid;
	border-bottom-width: thin;
	border-top-left-radius: 20px;
	border-top-right-radius: 20px;
	height: auto;
}
#botRow .instructions, #botRow .options
{
	padding: 10px;
	vertical-align: top;
}
#calDisplay
{
	display: table;
	margin: 0px;
	padding: 0px;
	width: 100%;
}
#calDisplay .calWeek
{
	float: left;
	width: 100%;
}
#calDisplay .calWeek .calCell
{
	display: table-cell;
	float: left;
	text-align: center;
	width: 14.2%;
}
#calDisplay .calWeek .calCell
{
	cursor: pointer;
}
#calDisplay .calWeek .calCell .calContent
{
	background-color: #EEE;
	border: thin solid #666;
	margin-bottom: 2px;
	margin-right: 2px;
	padding: 4px;
}
#calDisplay .calWeek .calCell .calContent a, #calDisplay .calWeek .calCell .calToday a
{
	height: 100%;
	left: 0;
	position: absolute;
	top: 0;
	width: 100%;
}
#calDisplay .calWeek .calCell .calContent, #calDisplay .calWeek .calCell .calToday
{
	position: relative;
	text-decoration: none;
}
#calDisplay .calWeek .calCell .calNoDay
{
	background-color: #FFF;
	border: thin solid #EEE;
	margin-bottom: 2px;
	margin-right: 2px;
	padding: 4px;
}
#calDisplay .calWeek .calCell .calSelectedDay
{
	background-color: #4F4;
	border: thin solid #000;
	font-weight: bold;
	margin-bottom: 2px;
	margin-right: 2px;
	padding: 4px;
}
#calDisplay .calWeek .calCell .calToday
{
	background-color: #FFC;
	border: thin solid #000;
	margin-bottom: 2px;
	margin-right: 2px;
	padding: 4px;
}
#calDisplay .calWeek .calCell .dayHeader
{
	background-color: #666;
	border: thin solid #333;
	color: #FFF;
	font-weight: bold;
	margin-bottom: 2px;
	margin-right: 2px;
	padding: 4px;
}
#calForm
{
	margin-bottom: 10px;
	position: relative;
}
#calForm #calYear, #calForm #calMonth
{
	background: #EEE;
	color: #000;
	display: block;
	font-size: .9em;
	height: 28px;
	margin: 2px;
	padding: 2px;
}
#calForm #submitCal
{
	background: #EEE;
	color: #000;
	display: block;
	font-size: 1em;
	height: 28px;
	margin: 4px;
	padding: 4px;
}
#calForm .calContain
{
	clear: right;
	float: left;
}
#calFormCont
{
	display: inline-block;
}
#hoursTable
{
	border: thin solid #666;
	border-radius: 16px;
	width: 100%;
}

#hoursTable .calTime
{
	white-space: nowrap;
}
#hoursTable tr td
{
	text-align: center;
	border-bottom-width: thin;
	border-bottom-style: solid;
	border-bottom-color: #666;
	border-top-style: none;
	border-right-style: none;
	border-left-style: none;
	padding: 2px;
}
#hoursTable tr:nth-child(1) .calCheckBox
{
	border-radius: 16px 0 0 0;
}
#hoursTable tr:nth-child(1) .calStatus
{
	border-radius: 0 16px 0 0;
}
#hoursTable tr:nth-child(1) td
{
	background: #666;
	color: #EEE;
	font-weight: bold;
}
#hoursTable tr:nth-child(even)
{
	background: #FFF;
}
#hoursTable tr:nth-child(odd)
{
	background: #EEE;
}
#hoursTable tr:nth-last-child(1) .calCheckBox
{
	border-radius: 0 0 0 16px;

}
#hoursTable tr:nth-last-child(1) .calStatus
{
	border-radius: 0 0 16px 0;
}
#hoursTable tr:nth-last-child(1) td {
	border: none;
}
#topRow
{
	border-spacing: 10px;
	display: table;
}
#topRow .col
{
	border: thin solid #666;
	border-radius: 20px;
	display: table-cell;
	width: 33%;
}
#topRow .header
{
	font-size: 1.3em;
	font-weight: bold;
}
#topRow .instructions
{
	background: #EEE;
	border-bottom-color: #999;
	border-bottom-left-radius: 12px;
	border-bottom-right-radius: 12px;
	border-bottom-style: solid;
	border-bottom-width: thin;
	border-top-left-radius: 20px;
	border-top-right-radius: 20px;
	height: 150px;
}
#topRow .instructions, #topRow .options
{
	padding: 10px;
}
#topRow .itemDesc
{
	font-size: .9em;
	padding-left: 30px;
}
#topRow .normalItem
{
	padding-left: 20px;
}
#topRow .normalItem a:hover
{
	text-decoration: underline;
}
#topRow .selectedItem
{
	font-size: 1.3em;
	font-weight: bold;
	padding-left: 10px;
}
.calNav
{
	text-align: center;
	width: 100%;
}
.calNav .curMonth
{
	float: left;
	font-size: 1.1em;
	font-weight: bold;
	text-align: center;
	width: 50%;
}
.calNav .nextMonth
{
	float: left;
	text-align: right;
	width: 25%;
}
.calNav .prevMonth
{
	clear: left;
	float: left;
	width: 25%;
}
@media screen and (max-width:960px)
{
	#botRow
	{
		display: block;
		float: left;
		position: relative;
		width: 100%;
	}
	#botRow .col1
	{
		border: thin solid #666;
		border-radius: 20px;
		display: block;
		float: left;
		margin-bottom: 10px;
		width: 100%;
	}
	#botRow .col2
	{
		display: block;
		float: left;
		margin-bottom: 10px;
		width: 100%;
	}
	#topRow
	{
		display: block;
		float: left;
		position: relative;
		width: 100%;
	}
	#topRow .col
	{
		border: thin solid #666;
		border-radius: 20px;
		display: block;
		float: left;
		margin-bottom: 10px;
		width: 100%;
	}
	#topRow .instructions
	{
		height: auto;
	}
}