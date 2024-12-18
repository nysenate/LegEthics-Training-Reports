<?php

function get_local_config($ini_file = 'legethics.ini')
{
  static $cfg = null;

  if ($cfg === null) {
    $cfg = parse_ini_file($ini_file, true);
    if ($cfg === false) {
      $cfg = null;
    }
  }
  return $cfg;
} // get_local_config()


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


/**
 * An Explanation:
 * Previously, there was only one course. So, this query
 * looked at all system users who were students.
 * Now, there are mutliple courses. So, we can't look
 * at all users anymore. We need to look at course
 * enrollments to see all students who have completed
 * a course and also students who have not.
 *
 * For now, the "Orientation" course has been
 * hardcoded to avoid seeing enrollments in other courses.
 * The problem with seeing other courses (right now)
 * is that the other "Refresher" courses do not have
 * grades associated with them, and therefore do not
 * appear in the mdl_lesson_grades table and therefore
 * will muddy-up the results of this report. This query
 * will need to change if we want to include other
 * reports that don't have grades.
**/
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
           CONCAT(crs.shortname, ': ', l.name),
           (CASE WHEN g.completed IS NULL THEN 'Incomplete'
            ELSE from_unixtime(g.completed) END) as completed
      FROM mdl_user_enrolments ue
      JOIN mdl_user u ON (ue.userid = u.id)
 LEFT JOIN user_fields uf ON (uf.userid = u.id)
      JOIN mdl_enrol enrol ON (ue.enrolid = enrol.id)
      JOIN mdl_course crs ON (crs.id = enrol.courseid)
      JOIN mdl_lesson l ON (l.course = crs.id)
 LEFT JOIN mdl_lesson_grades g ON (g.lessonid = l.id AND g.userid = u.id)
     WHERE u.id !=1
       AND crs.id = 6
       AND uf.organization = ? 
       AND ((g.completed > ? AND g.completed < ?) OR g.completed IS NULL)
  ORDER BY u.confirmed DESC, g.completed DESC, u.lastname ASC, u.firstname ASC");

  $sth->execute(array($org, $from, $to));
  $res = $sth->fetchAll(PDO::FETCH_ASSOC);
  return $res;
} // fetch_exam_results()


function generate_csv_file($dir, $org, $res)
{
  // headers
  $headers = array('Userid', 'Firstname', 'Lastname', 'Email', 'Organization',
                   'City', 'Office', 'Location', 'Phone',
                   'Confirmed', 'Lesson', 'Completed');

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

