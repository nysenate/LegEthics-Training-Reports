<?php
//
// Cron job to build usage report for email delivery
// 0 16 * * 5 php /data/moodle/www/report/nys/cron.php
//
// Subscriptions are managed in: legethics.ini
//
// quarterly reports
// 0 0 30 6,9 * php /data/moodle/www/report/nys/cron.php --quarterly >>/var/log/cron.d/moodle_report.log
// 0 0 31 3,12 * php /data/moodle/www/report/nys/cron.php --quarterly >>/var/log/cron.d/moodle_report.log

require_once 'PHPMailer/PHPMailerAutoload.php';
require_once 'utils.php';

echo "Starting Moodle report generation at ".date('Ymd-His')."\n";

$cfg = get_config('legethics.ini');
$dbcon = get_db_connection($cfg['database']);
if ($dbcon === false) {
  echo "ERROR: Unable to connect to database\n";
  exit(1);
}

$report_dir = $cfg['general']['report.dir'];
$org_config = $cfg['organizations'];
$active_orgs = explode(',', $org_config['active']);

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
  $result = fetch_exam_results($dbcon, $org, $start_time, $end_time);
  $out_fileinfo = generate_csv_file($report_dir, $org, $result);

  if ($out_fileinfo) {
    $filename = $out_fileinfo['file'];
    $rc = send_email($org, $report_t, $cfg['smtp'], $to_emails, $filename);
  }
  else {
    echo "$org: No output file to e-mail\n";
  }

  echo "- - - - - - \n";
}

echo "Finished Moodle report generation at ".date('Ymd-His')."\n";
return 0;


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

  $mail->Subject = "$org Legislative Ethics $rptType training report";
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
