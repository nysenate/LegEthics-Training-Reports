<?php

function get_config($ini_file = 'legethics.ini')
{
  static $cfg = null;

  if ($cfg === null) {
    $cfg = parse_ini_file($ini_file, true);
    if ($cfg === false) {
      $cfg = null;
    }
  }
  return $cfg;
} // get_config()


function get_db_connection($dbcfg)
{
  // setup db connection
  try {
    $host = $dbcfg['host'];
    $port = $dbcfg['port'];
    $user = $dbcfg['user'];
    $pass = $dbcfg['pass'];
    $name = $dbcfg['name'];
    $dbcon = new PDO("mysql:host=$host;port=$port;dbname=$name", $user, $pass,
                     array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    return $dbcon;
  }
  catch (PDOException $e) {
    echo "PDOException:".$e->getMessage()."\n";
    return false;
  }
} // get_db_connection()


function fetch_exam_results($dbcon, $org, $from, $to)
{
  $sth = $dbcon->prepare("
    SELECT u.id as userid, u.firstname , u.lastname, u.email,
           org.data as organization, u.city, office.data as office,
           location.data as location, phone.data as phone,
           (CASE WHEN u.confirmed = 0 THEN 'No' ELSE 'Yes' END) as confirmed,
           (CASE WHEN g.completed IS NULL THEN 'Incomplete'
            ELSE from_unixtime(g.completed) END) as completed
    FROM mdl_user AS u
    LEFT JOIN mdl_user_info_data org ON (org.userid = u.id)
    LEFT JOIN mdl_user_info_data office ON (office.userid = u.id)
    LEFT JOIN mdl_user_info_data phone ON (phone.userid = u.id)
    LEFT JOIN mdl_user_info_data location ON (location.userid = u.id)
    LEFT JOIN mdl_lesson_grades g ON (g.userid = u.id)
    LEFT JOIN mdl_role_assignments ra ON (ra.userid = u.id)
    WHERE u.id != 1
    AND ra.roleid = (SELECT id FROM mdl_role WHERE shortname='student')
    AND org.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname='Organization')
    AND office.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname='Office')
    AND location.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname='Location')
    AND phone.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname='Phone')
    AND org.data = ?
    AND ((g.completed > ? AND g.completed < ?) OR g.completed IS NULL)
    ORDER BY u.confirmed DESC, g.completed DESC");

  $sth->execute(array($org, $from, $to));
  $res = $sth->fetchAll(PDO::FETCH_ASSOC);
  return $res;
} // fetch_exam_results()


function generate_csv_file($dir, $org, $res)
{
  // headers
  $headers = array('Userid', 'Firstname', 'Lastname', 'Email', 'Organization',
                   'City', 'Office', 'Location', 'Phone',
                   'Confirmed', 'Completed');

  // office specific csv report for attachment
  $filename = $org.'_'.date('Ymd-His').'.csv';
  $filepath = $dir.DIRECTORY_SEPARATOR.$filename;
  $fp = fopen($filepath, 'w');
  if ($fp) {
    fputcsv($fp, $headers);
    $recordCount = 0;
    foreach ($res as $fields) {
      fputcsv($fp, $fields);
      $recordCount++;
    }
    fclose($fp);
    return array('filename' => $filename,
                 'filepath' => $filepath,
                 'count' => $recordCount);
  }
  else {
    return null;
  }
} // generate_csv_file()

