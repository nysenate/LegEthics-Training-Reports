<?php
date_default_timezone_set('America/New_York');
function round_nearest($no,$near){
 	if($no %$near != 0) {
		return round(($no+$near/2)/$near)*$near;
	}else{
		return $no;
	}
}
/// connect to database
$conn = mysql_connect('localhost', '', '');
if (!$conn) {  die('Could not connect: ' . mysql_error()); }
mysql_select_db('moodle');

if ($_POST['t']){
	$type = htmlspecialchars(mysql_real_escape_string($_POST['t']));
}


if ($_POST['c']){
	$code = htmlspecialchars(mysql_real_escape_string($_POST['c']));
}

if ($_POST['s']){
	$start_raw = htmlspecialchars(mysql_real_escape_string($_POST['s']));
	$start = htmlspecialchars(mysql_real_escape_string($_POST['s'])).' 23:59:59';
}

if ($_POST['e']){
	$end_raw = htmlspecialchars(mysql_real_escape_string($_POST['e']));
	$end = htmlspecialchars(mysql_real_escape_string($_POST['e'])).' 23:59:59';
}
//echo $start .' - '. $end;
 
// 

//// csv generation
// $title = $code.'_'.rand(100,100000);
// $fp = fopen('reports/'.$title.'.csv', 'w+');
// foreach ($csv as $fields) {
//     fputcsv($fp, $fields);
// }
// fclose($fp);


?>
<!doctype html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]> <html class="no-js" lang="en"> 		   <![endif]-->
<head>
	<meta charset="utf-8">
  	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
 	<meta name="author" content="">
	<meta name="description" content="">
	<meta name="keywords" content="">
  	<meta name="viewport" content="width=device-width,initial-scale=1">
	<meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
 	<link href="style.css" rel="stylesheet">
	<script src="jquery.js"></script>
	<script src="tablesort.js"></script>
	<script>
		$(function() {
			$("table#local,table#longdistance,table#tollfree,table#number").tablesorter({ sortList: [[0,1]] });
			$("#local").click(function(){
				$('table#local').toggle('fast');
			});
			$("#longdistance").click(function(){
				$('table#longdistance').toggle('fast');
			});
			$("#tollfree").click(function(){
				$('table#tollfree').toggle('fast');
			});
			$("#Numberbreakdown").click(function(){
				$('table#number').toggle('fast');
			});
		
		});
	</script>
	<style>
 		table#local,table#longdistance, table#tollfree, table#number { display:none; } 
	</style>
 
</head>
<body>
	<div class="container">

<? if ($error == TRUE){
	echo '<div class="page-header down" id="top">
	  <h2>We\'re having trouble finding that record, <small> <a href="index.php">back to search</a></small></h2>
	 </div></div></body>';
	exit;
}?>
<div class="page-header down" id='top'>
  <h2><? echo $type; ?> records for #<? echo $code; ?> <small>from <?php echo $start_raw; ?>  to <?php echo $end_raw; ?> <a href='index.php'>back to search</a></small></h2>
	<a href='reports/<?echo $title;?>.csv' target='_blank'>Download the CSV</a> 

 </div>
 	<section id="about">
	 	<div class="page-header down">
			<h1 id="breakdown">Total <? echo $type; ?> Usage <small>    </small></h1>
		</div>
		<div title="" class="row">
			<div class="span8"> <? echo '<h1>'.$number." Calls</h1>"; ?>
					<? echo '<h3> <a href="#local">'.$number_total_loc." Local calls</a></h3>"; ?>
				  	<? echo '<h3><a href="#longdistance">'.$number_total_ld." Long Distance calls</a></h3>"; ?>
				  	<? echo '<h3><a href="#tollfree">'.$number_total_TF." Toll-Free calls</a></h3>"; ?> 
				
				</div>
	     	<div class="span8"> <? echo '<h1>'.round($totalSec/60)." Mins </h1>"; ?>
				<? echo '<h3> <a href="#local">'.round($number_loc/60)." Mins Local</a></h3>"; ?>
			  	<? echo '<h3><a href="#longdistance">'.round($number_ld/60)." Mins Long Distance</a></h3>"; ?> 
			  	<? echo '<h3><a href="#tollfree">'.round($number_TF/60)." Mins Toll-Free </a></h3>"; ?> 
			
			</div>
		 	<!-- <div class="span-one-third"> <? echo '<h1> $'. (round($number_loc/60)*.04 + round($number_ld/60)*.06)." Due </h1>"; ?>
					<? echo '<h3> $'.round($number_loc/60)*.04." in Local charges</a></h3>"; ?>
					<? echo '<h3>$'.round($number_ld/60)*.06." in Long Distance charges</a></h3>"; ?> 
				</div> -->
	  	</div>
		<hr/>
	
 	<!-- <hr/>
	
	<div title="" class=" ">
		<h5> Files for client</h5>
		<a href='reports/<?echo $title;?>.csv' target='_blank'>Download the CSV (zipped)</a>, <a href='reports/<?echo $title;?>.csv' target='_blank'>Download the PDF (zipped)</a> , <a href='reports/<?echo $title;?>.csv' target='_blank'>Download the Combo (CSV + PDF) </a>
 	</div> -->
 
	

 

<div >
	<div class="page-header down">
		<h1 id="local">Local <small> click to show   </small></h1>
	</div>
    
<table id="local" class="zebra-striped">
	<thead>
<tr>	
	<th>calldate</th>
	<th>seconds </th>
	<th>From</th>
	<th>To</th>
	<!-- <th>Account code</th> -->
	<th>Type</th>
</tr>
  </thead>
 <tbody>
<? echo $table; ?>
</tbody>
</table>
</div>
<div id="name">
	<div class="page-header down">
		<h1 id="longdistance">Long Distance <small>click to show </small></h1>
	</div>

<table id="longdistance" class="zebra-striped">
	<thead>
<tr>	
	<th>calldate</th>
	<th>seconds</th>
	<th>From</th>
	<th>To</th>
	<!-- <th>Account code</th> -->
	<th>Type </th>
</tr>
  </thead>
 <tbody>
<?php echo ($table_ld); ?>
</tbody>

</table>
<div >
	<div class="page-header down">
		<h1 id="tollfree">Toll-Free <small> click to show   </small></h1>
	</div>
    
<table id="tollfree" class="zebra-striped">
	<thead>
<tr>	
	<th>calldate</th>
	<th>seconds </th>
	<th>From</th>
	<th>To</th>
	<!-- <th>Account code</th> -->
	<th>Type</th>
</tr>
  </thead>
 <tbody>
<? echo $tollfree_table; ?>
</tbody>
</table>
</div>
	</div>
		<div >
			<div class="page-header down">
				<h1 id="Numberbreakdown">Number breakdown<small>  <?echo count($breakdown_numbers)-1; // $number_breakdown_total; ?> total </small></h1>
			</div>

		<table id="number" class="zebra-striped">
			<thead>
		<tr>	
			<th>Number</th>
			<th>Total Mins</th>
			<th>Local Mins</th>
			<th>Long Distance Mins</th>
		</tr>
		  </thead>
		 <tbody>
	<?php		
	 	foreach($array as $key => $value){
			echo '<tr><td>'.$key."</td> ";
			print_r( '<td>'.round(($value['local'] + $value['long distance'] ) / 60).'</td>');
	   		foreach ($value as $child_value){
				echo ' <td>'.round($child_value/ 60)."</td>";
	 		}
	 		echo '</tr>';
		}
		echo '<tr>
		<td><strong>Totals</strong></td>
		<td> <strong>'.round($totalSec/60).'</strong></td>
		<td><strong>'.round($number_loc/60).'</strong></td>
		<td><strong>'.round($number_ld/60).'</strong></td>
	 	</tr>
		';
	?>
		</tbody>
		</table>
		</div>	
	 
	<div id="csv">
		<?  // print_r( $breakdown_numbers);?>
	</div>
 
	<!-- <div id="csv">
		<? //echo "<pre>";
 	//	print_r($array);
		//echo "</pre>";
		?>
	</div> -->
 
</section></div></body>
<? mysql_close();

