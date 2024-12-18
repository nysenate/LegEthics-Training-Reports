<?php
require_once 'utils.php';

$error = false;
$orgs = array();
$files_to_zip = array();

foreach (array('Assembly', 'Senate', 'LBDC') as $org) {
  if (isset($_GET[$org])) {
    $orgs[$org] = array();
  }
}

if (count($orgs) == 0) {
  echo "ERROR: At least one organization must be specified\n";
  exit(1);
}

if (isset($_GET['start'])) {
  $startdt = clean($_GET['start']);
  $start_unix = strtotime($startdt.' 00:00:00');
}
else {
  $error = true;
}

if (isset($_GET['end'])) {
  $enddt = clean($_GET['end']);
  $end_unix = strtotime($enddt.' 23:59:59');
}
else {
  $error = true;
}

$cfg = get_local_config('legethics.ini');
$report_dir = $cfg['general']['report.dir'];

$dbcon = get_db_connection($cfg['database']);
if ($dbcon === false) {
  echo "ERROR: Unable to connect to database\n";
  exit(1);
}

foreach ($orgs as $name => $val) {
  $orgs[$name] = fetch_exam_results($dbcon, $name, $start_unix, $end_unix);
  $csvinfo = generate_csv_file($report_dir, $name, $orgs[$name]);
  if ($csvinfo) {
    $files_to_zip[$name] = $csvinfo['filename'];
  }
}

if (empty($files_to_zip)) {
  $zip_filename = null;
}
else {
  $zipinfo = create_zipfile($report_dir, 'All_reports', $files_to_zip);
  $zip_filename = $zipinfo['filename'];
}


/****************************************************************************/

function clean($string)
{
  return filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
} // clean()


/* creates a compressed zip file */
function create_zipfile($dir, $base, $files)
{
  $filename = $base.'_'.date('Ymd-His').'.zip';
  $filepath = $dir.DIRECTORY_SEPARATOR.$filename;
  // if the zip file already exists, return
  if (file_exists($filepath)) {
    return null;
  }

  $valid_files = array();
  if (is_array($files)) {
    foreach ($files as $file) {
      if (file_exists($dir.DIRECTORY_SEPARATOR.$file)) {
        $valid_files[] = $file;
      }
    }
  }

  $file_count = count($valid_files);

  if ($file_count > 0) {
    $zip = new ZipArchive();
    if ($zip->open($filepath, ZIPARCHIVE::CREATE) !== true) {
      return null;
    }
    foreach ($valid_files as $file) {
      $zip->addFile($dir.DIRECTORY_SEPARATOR.$file, $file);
    }
    $zip->close();
    return array('filename' => $filename,
                 'filepath' => $filepath,
                 'count' => $file_count);
  }
  else {
    return null;
  }
} // create_zipfile()


function display_table($org, $recs)
{
  $rec_cnt = count($recs);
  if ($rec_cnt > 0) {
    $body = "  <div class='page-header down'>
    <h2 id='$org'>$rec_cnt records for the $org  <small>click to show</small></h2>
  </div>
  <table id='$org' class='zebra-striped'>
    <thead>
      <tr>
      <td><strong>Full name</strong></td>
      <td><strong>Email</strong></td>
      <td><strong>Organization</strong></td>
      <td><strong>Office</strong></td>
      <td><strong>Location</strong></td>
      <td><strong>Phone</strong></td>
      <td><strong>Confirmed</strong></td>
      <td><strong>Completed</strong></td>
      </tr>
    </thead>
  <tbody>\n";

    foreach ($recs as $rec) {
      $body .= "\t\t<tr>\n";
      $body .= "\t\t\t<td>".$rec['lastname'].', '.$rec['firstname']."</td>\n";
      $body .= "\t\t\t<td>".$rec['email']."</td>\n";
      $body .= "\t\t\t<td>".$rec['organization']."</td>\n";
      $body .= "\t\t\t<td>".$rec['office']."</td>\n";
      $body .= "\t\t\t<td>".$rec['location']."</td>\n";
      $body .= "\t\t\t<td>".$rec['phone']."</td>\n";
      $body .= "\t\t\t<td>".$rec['confirmed']."</td>\n";
      $body .= "\t\t\t<td>".$rec['completed']."</td>\n";
      $body .= "\t\t</tr>\n";
    }

    $body .= "\t</tbody>\n\t</table>\n";
  }
  else { // if there are no records
    $body = "<div class='page-header down'>\n<h2 id='$org'>$rec_cnt records for the $org</h2>\n</div>\n";
  }

  echo $body;
} // display_table()
?>

<!doctype html>
<?php
if ($error == true) {
  echo "<html>\n<body>\n";
  echo '<div class="page-header down" id="top">
        <h1>We are having trouble finding that record</h1><br/>
        <a href="./" class="btn info">Back to search</a><br/><br/>
        <strong>Passed the following:</strong>
        <pre>';
  print_r($_GET);
  echo "</pre>\n</div>\n</body>\n</html>\n";
  exit(1);
}
?>
<html>
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
  <div class="container">
    <div class="page-header down" id="top">
    <h1>Results for <?php echo implode(', ', array_keys($orgs));?> <small>from <?php echo $startdt;?> to <?php echo $enddt;?> </small></h1><br/>
    <a href="./" class="btn info">Back to search</a><br/><br/>
    </div>
  
    <section id="about">
    <div>
      <div class="page-header down">
      <h2>Files to download</h2>
      </div>
      <div title="" class="">
      <?php
        foreach ($files_to_zip as $org_name => $filename) {
          echo "<a href='download.php?file=$filename' class='btn' target='_blank'>Download the $org_name report (.csv)</a>\n";
        }
        if ($zip_filename) {
          echo "<a href='download.php?file=$zip_filename' class='btn' target='_blank'>Download all reports (.zip)</a>\n";
        }
      ?>
      </div>
    </div>
    <hr/>
    <?php
      foreach ($orgs as $org_name => $org_recs) {
        echo "<div>\n";
        display_table($org_name, $org_recs);
        echo "</div>\n";
      }
    ?>
    </section>
  </div>
</body>
</html>
