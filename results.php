<?php
// timezone for unix conversion help
date_default_timezone_set('America/New_York');

////////////////////////////////////////////
// vars
// quick error handling
$error = FALSE;

$Senate_exists = FALSE;
$Assembly_exists = FALSE;
$LBDC_exists = FALSE;

/// setup arrays
$test_restults = array(); // keyed by user id
$files_to_zip = array(); // files that get zipped
$code = '';

////////////////////////////////////////////
// GET vars
/// grab vars from post
if (isset($_GET['Assembly'])){
  $Assembly  = clean($_GET['Assembly']);
  $code .= 'Assembly, ';
}

if (isset($_GET['Senate'])){
  $Senate  =  clean($_GET['Senate']);
  $code .= 'Senate, ';
}

if (isset($_GET['LBDC'])){
  $LBDC   =  clean($_GET['LBDC']);
  $code .= 'LBDC, ';
}

if (isset($_GET['start']) ){
  $start_raw = clean($_GET['start']);
  $start = clean($_GET['start']).' 23:59:59';
  $start_unix = strtotime($start);
}else{
  $error = TRUE;
}

if (empty($_GET['Assembly'])&&empty($_GET['Senate']) &&empty($_GET['LBDC'])  ){
  $error = TRUE;
}
if (isset($_GET['end'])){
  $end_raw = clean($_GET['end']);
  $end = clean($_GET['end']).' 23:59:59';
  $end_unix = strtotime($end);
}else{
  $error = TRUE;
}


////////////////////////////////////////////
///functions
function clean($string){
  $var =  filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
  return $var;
}
/* creates a compressed zip file */
function create_zip($files = array(),$destination = '',$overwrite = false) {
  // if the zip file already exists and overwrite is false, return false
  if(file_exists($destination) && !$overwrite) { return false; }
  // vars
  $valid_files = array();
  // if files were passed in...
  if(is_array($files)) {
    // cycle through each file
    foreach($files as $file) {
      // make sure the file exists
      if(file_exists($file)) {
        $valid_files[] = $file;
      }
    }
  }
  // if we have good files...
  if(count($valid_files)) {
    // create the archive
    $zip = new ZipArchive();
    if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
      return false;
    }
    //add the files
    foreach($valid_files as $file) {
      $zip->addFile($file,$file);
    }
    //echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
    //close the zip -- done!
    $zip->close();
    //check to make sure the file exists
    return file_exists($destination);
  }else{
    return false;
  }
}

// split users into separate arrays for the orgs
function spliton($old_array, $tag ,$split_on){
  // filter array by tag to split it on
  $new_array = array();
  foreach ($old_array as $data) {
      if ($data[$tag] == $split_on){
           $new_array[] = $data; // still key it by user
      }
  }
  return $new_array;
  unset($new_array);
}

// write an array into a CSV file
function csv($Group, $array){
  $title = $Group.'_'.date("Y-m-d")."_".rand(10000,100000000);
  $headers = array();
  $fp = fopen('reports/'.$title.'.csv', 'w+');
  for ($i=0; $i < count($array); $i++) {
    if ($i == 0) {
      foreach ($array[$i] as $id => $text) {
        $headers[] = ucfirst(strtolower($id));
      }
      fputcsv($fp, $headers);
    }
    fputcsv($fp, $array[$i]);
  }
  fclose($fp);
  return $title;
}

////////////////////////////////////////////
//// Queries
$conn = mysql_connect('localhost', '', '');
if (!$conn) { $error = TRUE; }
mysql_select_db('moodle');

// grab users with test data & order by last name
$query= "SELECT u.id as userid, u.firstname , u.lastname, u.email, 
org.data as organization, u.city, office.data as office, location.data 
as location, phone.data as phone, (CASE WHEN u.confirmed = 0 THEN 'No' 
ELSE 'Yes' END) as confirmed, (CASE WHEN g.completed IS NULL THEN 
'Incomplete' ELSE from_unixtime(g.completed) END) as completed
    FROM mdl_user AS u
    LEFT JOIN mdl_user_info_data org ON (org.userid = u.id)
    LEFT JOIN mdl_user_info_data office ON (office.userid = u.id)
    LEFT JOIN mdl_user_info_data phone ON (phone.userid = u.id)
    LEFT JOIN mdl_user_info_data location ON (location.userid = u.id)
    LEFT JOIN mdl_lesson_grades g ON (g.userid = u.id)
    LEFT JOIN mdl_role_assignments ra ON (ra.userid = u.id)
    WHERE u.id != 1
    AND ra.roleid = (SELECT id FROM mdl_role WHERE shortname='student')
    AND org.fieldid = (SELECT id FROM mdl_user_info_field WHERE 
shortname='Organization')
    AND office.fieldid = (SELECT id FROM mdl_user_info_field WHERE 
shortname='Office')
    AND location.fieldid = (SELECT id FROM mdl_user_info_field WHERE 
shortname='Location')
    AND phone.fieldid = (SELECT id FROM mdl_user_info_field WHERE 
shortname='Phone')
    AND ((g.completed < '$end_unix' AND g.completed > '$start_unix') OR 
g.completed IS NULL )
    ORDER BY u.confirmed DESC, g.completed DESC";

$test_results = mysql_query($query);

// create an array of user data to combine on key of user id
while ($test_data = mysql_fetch_assoc($test_results)) {
    $test_restults[$test_data['userid']] = $test_data;
}

// var_dump($query);

// var_dump($test_restults);

////////////////////////////////////////////
// app logic
/// check to see if we have results,
if (empty($test_restults)){
  $error = TRUE;
} else {

  // use our array splitter to pull the data we need.
  $Senate_array = spliton($test_restults,"organization", "Senate");
  $Assembly_array = spliton($test_restults,"organization", "Assembly");
  $LBDC_array = spliton($test_restults,"organization", "LBDC");
  // var_dump($test_restults);
  // echo "<hr/>\n<br/>";
  // var_dump($Senate_array);
  // echo "<hr/>\n<br/>";
  // var_dump($Assembly_array);
  // echo "<hr/>\n<br/>";
  // var_dump($LBDC_array);
  // echo "<hr/>\n<br/>";

  /// proccess to create downloadable files, and check to see if content exists
  // if we have an array, and if we asked for it, process
  if (!empty($Senate_array) && (isset($Senate))){
    $Senate_exists = TRUE;
    $Senate_csv_title = csv("Senate", $Senate_array);
    $files_to_zip[] = 'reports/'.$Senate_csv_title.'.csv';
  }

  if (!empty($LBDC_array) && (isset($LBDC))){
    $LBDC_exists = TRUE;
    $LBDC_csv_title = csv("LBDC", $LBDC_array);
    $files_to_zip[] = 'reports/'.$LBDC_csv_title.'.csv';
  }

  if (!empty($Assembly_array) && (isset($Assembly))){
    $Assembly_exists = TRUE;
    $Assembly_csv_title = csv("Assembly", $Assembly_array);
    $files_to_zip[] = 'reports/'.$Assembly_csv_title.'.csv';
  }
  //// if we have files, zip them.
  if (!empty($files_to_zip)){
      $zipname = 'All_reports_'.rand(10000,100000000).'.zip';
      $result = create_zip($files_to_zip, 'reports/'.$zipname);
  }
}
////////////////////////////////////////////
// layout functions
// make a table if the data exists
function display_table($array, $title, $exists){
  if($exists == TRUE){
    $body="<div class='page-header down'>
    <h2 id='".$title."'>". count( $array)." Records for the ".$title."  <small> click to show   </small></h2>
  </div>
    <table id='".$title."' class='zebra-striped'>
      <thead>
        <tr>
          <td><strong>Full name Last, First</strong></td>
          <td><strong>Email</strong></td>
          <td><strong>Organization</strong></td>
          <td><strong>Office</strong></td>
          <td><strong>Location</strong></td>
          <td><strong>Phone</strong></td>
          <td><strong>Fully Registed</strong></td>
          <td><strong>Classes Completed</strong></td>
        </tr>
      </thead>
    <tbody>\n";
    foreach ($array as $fields) {
      $body .="\t\t<tr>\n";
      $body .="\t\t\t"."<td>".$fields['lastname'].', '.$fields['firstname']."</td>\n";
      $body .="\t\t\t"."<td>".$fields['email']."</td>\n";
      $body .="\t\t\t"."<td>".$fields['organization']."</td>\n";
      $body .="\t\t\t"."<td>".$fields['office']."</td>\n";
      $body .="\t\t\t"."<td>".$fields['location']."</td>\n";
      $body .="\t\t\t"."<td>".$fields['phone']."</td>\n";
      $body .="\t\t\t"."<td>".$fields['confirmed']."</td>\n";
      $body .="\t\t\t"."<td>".$fields['completed']."</td>\n";
      $body .="\t\t</tr>\n";
    }
    $body .="\t</tbody>\n\t</table>\n";
    echo $body;
  } else{ // if there are no records, lets tell them.
    $body="<div class='page-header down'><h2 id='".$title."'>". count( $array)." Records for the ".$title."  </h2></div>";
    echo $body;
  }
}
mysql_close();
if (empty($_GET['cron'])) {
?>
<!doctype html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]> <html class="no-js" lang="en">       <![endif]-->
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

  <script>
  $(document).ready(function() {

       $("#Senate").click(function(){
        $('table#Senate').toggle('fast');
      });
      $("#Assembly").click(function(){
        $('table#Assembly').toggle('fast');
      });
      $("#LBDC").click(function(){
        $('table#LBDC').toggle('fast');
      });
  });
  </script>


</head>
<body>
  <div class="container" >
  <?php // THROW AN ERROR IF IT GETS TRIPPED UP
  if ($error == TRUE) {
    echo '<div class="page-header down" id="top">
     <h1>We\'re having trouble finding that record </h1><br/>
     <a href="./" class="btn info">back to search</a><br/><br/>
     </div> <strong>passed the following:</strong>
     <pre>';
    print_r($_GET);
    echo '</pre></div></body>';
    exit;
  }
  ?>
     <div class="page-header down" id='top'>
      <h1>Results for <?php echo substr($code,0,-2); ?> <small>from <?php echo $start_raw; ?>  to <?php echo $end_raw; ?> </small></h1><br/>
      <a href='./' class='btn info'>back to search</a><br/><br/>
    </div>
    <section id="about">
      <div>
        <div class="page-header down">
          <h2> Files to download</h2>
        </div>
        <div title="" class="">
          <?php if (isset($Senate_csv_title)){ ?>
          <a href='reports/<?php echo $Senate_csv_title;?>.csv' class='btn' target='_blank'>Download the Senate report ( .csv ) </a>
          <br/> <br/>
          <?php }; if (isset($Assembly_csv_title)){ ?>
          <a href='reports/<?php echo $Assembly_csv_title;?>.csv' class='btn' target='_blank'>Download the Assembly report ( .csv ) </a>
          <br/> <br/>
          <?php }; if (isset($LBDC_csv_title)){ ?>
          <a href='reports/<?php echo $LBDC_csv_title;?>.csv' class='btn' target='_blank'>Download the LBDC report ( .csv ) </a>
          <?php };   if (isset($zipname)){ ?>
          <a href='reports/<?php echo $zipname;?>' class='btn' target='_blank'> All reports ( .zip )</a>
          <?php }; ?>
        </div>
      </div>
      <hr/>
      <div>
        <?php if (isset($_GET['Senate'])){display_table($Senate_array, 'Senate', $Senate_exists); }?>
      </div>

      <div>
        <?php if (isset($_GET['Assembly'])){ display_table($Assembly_array, 'Assembly', $Assembly_exists); } ?>
      </div>
      <div>
        <?php if (isset($_GET['LBDC'])){display_table($LBDC_array,'LBDC', $LBDC_exists); } ?>
      </div>
    <div id="csv">
      <?php  // print_r( $breakdown_numbers);?>
    </div>
    </section>
  </div>
</body>


<?php

}
else { // if its a cron job, why not send as a message
echo "Hello, \n\nPlease find attached the csv of training completed this week and all incomplete users.\n\nThanks,\nReport Robot\n\n";
  if (isset($Senate_csv_title)) {
    echo '<a href="http://training.legethics.com/report/nys/reports/'.$Senate_csv_title.'.csv" target="_blank">Download the Senate report</a><br/>';
  }

  if (isset($Assembly_csv_title)) {
    echo '<a href="http://training.legethics.com/report/nys/reports/'.$Assembly_csv_title.'.csv" target="_blank">Download the Assembly report</a><br/>';
  }

  if (isset($LBDC_csv_title)) {
    echo '<a href="http://training.legethics.com/report/nys/reports/'.$LBDC_csv_title.'.csv" target="_blank">Download the LBDC report</a>';
  }

  // if (isset($zipname)) {
  //   echo '<a href="http://training.legethics.com/report/nys/reports/'.$zipname.'" class="btn" target="_blank">All reports ( .zip )</a>';
  // }
}

?>
