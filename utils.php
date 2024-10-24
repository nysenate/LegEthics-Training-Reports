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
    WITH user_fields AS (
        SELECT data.userid,
               MAX(CASE WHEN fieldid = 5 THEN data END) AS location,
               MAX(CASE WHEN fieldid = 6 THEN data END) AS organization,
               MAX(CASE WHEN fieldid = 7 THEN data END) AS office,
               MAX(CASE WHEN fieldid = 8 THEN data END) AS phone
          FROM mdl_user_info_data data
      GROUP BY data.userid
    )
    SELECT u.id as userid, u.firstname, u.lastname, u.email,
           uf.organization, u.city, uf.office,
           uf.location, uf.phone,
           (CASE WHEN u.confirmed = 0 THEN 'No' ELSE 'Yes' END) as confirmed,
           crs.shortname,
           (CASE WHEN g.completed IS NULL THEN 'Incomplete'
            ELSE from_unixtime(g.completed) END) as completed
      FROM mdl_lesson_grades g
      JOIN mdl_lesson l ON (g.lessonid = l.id)
      JOIN mdl_course crs ON (l.course = crs.id)
      JOIN mdl_user u ON (u.id = g.userid)
 LEFT JOIN user_fields uf ON (uf.userid = g.userid)
      JOIN mdl_role_assignments ra ON (ra.userid = g.userid)
      JOIN mdl_context ctxt ON (ra.contextid = ctxt.id)
     WHERE u.id != 1
       AND ra.roleid = (SELECT id FROM mdl_role WHERE shortname='student')
       AND ctxt.contextlevel = 50
       AND ctxt.instanceid = crs.id
       AND uf.organization = ?
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
                   'Confirmed', 'Course', 'Completed');

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

