<?php
//
// Cron job to build Usage report for email delivery
// 0 16 * * 5 php /data/moodle/www/report/nys/cron.php
//
// Subscriptions are managed in: legethics.ini
//
// quarterly reports
// 0 0 30 6,9 * php /data/moodle/www/report/nys/cron.php --quarterly >>/var/log/cron.d/moodle_report.log
// 0 0 31 3,12 * php /data/moodle/www/report/nys/cron.php --quarterly >>/var/log/cron.d/moodle_report.log

require 'PHPMailer/PHPMailerAutoload.php';

echo "Starting Moodle report generation at ".date('Ymd-His')."\n";

$ini_array = parse_ini_file("legethics.ini", true);

// setup db connection
$dbconfig = $ini_array['database'];
try {
  $type = $dbconfig['type'];
  $host = $dbconfig['host'];
  $port = $dbconfig['port'];
  $user = $dbconfig['user'];
  $pass = $dbconfig['pass'];
  $name = $dbconfig['name'];
  $dbcon = new PDO("$type:host=$host;port=$port;dbname=$name", $user, $pass,
                   array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
  $stmt = prepare_db_query($dbcon);
}
catch (PDOException $e) {
  echo "PDOException:".$e->getMessage()."\n";
  return false;
}

$general_config = $ini_array['general'];
$smtp_config = $ini_array['smtp'];
$org_config = $ini_array['organizations'];
$active_orgs = explode(',', $org_config['active']);
$report_dir = $general_config['report.dir'];

# CLI options
$opts = getopt('q', ['quarterly']);
$report_t = (isset($opts['quarterly'])) ? "Quarterly" : "Weekly";


// run through list of offices
foreach ($active_orgs as $org) {
  $duration = (isset($opts['quarterly'])) ? 92 : $org_config[$org.'.duration']; ;
  $from = date('U', strtotime('-'.$duration.' days'));
  $to = date('U');
  $to_emails = $org_config[$org.'.email'];

  echo "Generating $org $report_t Report covering the past $duration days\n";

  // date range the organization wants to see
  $start_time = date('U', strtotime('-'.$duration.' days'));
  $end_time = date('U');

  // get matching records from database
  $result = get_db_results($stmt, $org, $start_time, $end_time);

  $out_filename = generate_output_file($report_dir, $org, $result);

  if ($out_filename) {
    $rc = send_email($org, $report_t, $smtp_config, $to_emails, $out_filename);
  }
  else {
    echo "$org: No output file to e-mail\n";
  }

  echo "- - - - - - \n";
}

echo "Finished Moodle report generation at ".date('Ymd-His')."\n";
return 0;


function prepare_db_query($dbcon)
{
  $sth = $dbcon->prepare("
    SELECT u.id as userid, u.firstname , u.lastname, u.email, org.data as organization, u.city, office.data as office, location.data as location, phone.data as phone, (CASE WHEN u.confirmed = 0 THEN 'No' ELSE 'Yes' END) as confirmed, (CASE WHEN g.completed IS NULL THEN 'Incomplete' ELSE from_unixtime(g.completed) END) as completed
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
    AND (( g.completed > ? AND g.completed < ?) OR g.completed IS NULL )
    ORDER BY u.confirmed DESC, g.completed DESC");
  return $sth;
} // prepare_db_query()


function get_db_results($sth, $org, $from, $to)
{
  $sth->execute(array($org, $from, $to));
  $res = $sth->fetchAll(PDO::FETCH_NUM);
  return $res;
} // get_db_results()


function generate_output_file($dir, $org, $res)
{
  // headers
  $headers = array('Userid', 'Firstname', 'Lastname', 'Email', 'Organization',
                   'City', 'Office', 'Location', 'Phone',
                   'Confirmed', 'Completed');

  // office specific csv report for attachment
  $file = "$dir/$org".'_'.date('Ymd-His').'.csv';
  $fp = fopen($file, 'w');
  if ($fp) {
    fputcsv($fp, $headers);
    $recordCount = 0;
    foreach ($res as $fields) {
      fputcsv($fp, $fields);
      $recordCount++;
    }
    fclose($fp);
    echo "$recordCount records saved to $file.\n";
    return $file;
  }
  else {
    echo "Failed to open $file for writing\n";
    return null;
  }
} // generate_output_file()


function send_email($org, $rptType, $smtp, $emails, $attachment)
{
  // send email
  $mail = new PHPMailer;
  $mail->isSMTP();
  $mail->Host = $smtp['host'];
  $mail->SMTPAuth = $smtp['auth'];

  $mail->setFrom($smtp['from'], $smtp['fromName']);
  $mail->addReplyTo($smtp['replyTo'], $smtp['replyToName']);

  foreach ($emails as $email) {
    $mail->addAddress($email);
  }

  foreach ($smtp['bcc'] as $email) {
    $mail->addBCC($email);
  }

  $mail->addAttachment($attachment, "$org $rptType Report.csv");
  $mail->WordWrap = 80;
  $mail->isHTML(false);

  $mail->Subject = "$org Leg Ethics $rptType training report";
  $mail->Body    = "Hello,\n\nPlease find attached to this e-mail the CSV file of $org employees who completed legislative ethics training this week, along with those employees who are still incomplete.\n\nIf you have any questions, please reply to this e-mail.\n\nThanks,\nLeg Ethics Report Server\n";

  if ($mail->send()) {
    echo "Message has been sent.\n";
    return true;
  }
  else {
   echo 'Message could not be sent.';
   echo 'Mailer Error: '.$mail->ErrorInfo;
   return false;
  }
} // send_email()