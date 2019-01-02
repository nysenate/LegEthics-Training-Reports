<?php
//
// Cron job to build weekly usage report for email delivery
//   0 16 * * 5 php /data/moodle/www/report/nys/cron.php
// For quarterly reports:
//   0 0 30 6,9 * php /data/moodle/www/report/nys/cron.php --quarterly
//   0 0 31 3,12 * php /data/moodle/www/report/nys/cron.php --quarterly
//
// Subscriptions are managed in: legethics.ini
//

require_once 'utils.php';

echo "Starting Moodle report generation at ".date('Ymd-His')."\n";

$cfg = get_config('legethics.ini');
if ($cfg === null) {
  echo "ERROR: Unable to load config file\n";
  exit(1);
}

// Confirm all four major sections of the config file are present.
foreach (['general','database','smtp','organizations'] as $sect) {
  if (!isset($cfg[$sect])) {
    echo "ERROR: Config file is missing the [$sect] section\n";
    exit(1);
  }
}

$dbcon = get_db_connection($cfg['database']);
if ($dbcon === false) {
  echo "ERROR: Unable to connect to database\n";
  exit(1);
}

$gen_config = $cfg['general'];
if (!isset($gen_config['report.dir']) || !isset($gen_config['phpmailer.dir'])) {
  echo "ERROR: Both report.dir and phpmailer.dir must be set\n";
  exit(1);
}
else {
  $report_dir = $gen_config['report.dir'];
  $phpmailer_dir = $gen_config['phpmailer.dir'];
  if (is_dir($report_dir) == false || is_dir($phpmailer_dir) == false) {
    echo "ERROR: Both report.dir and phpmailer.dir must exist\n";
    exit(1);
  }
}

$org_config = $cfg['organizations'];
if (isset($org_config['active'])) {
  $active_orgs = explode(',', $org_config['active']);
}
else {
  $active_orgs = [];
}


require_once "$phpmailer_dir/PHPMailer.php";
require_once "$phpmailer_dir/SMTP.php";
require_once "$phpmailer_dir/Exception.php";

// Define our own mailer called CronMailer, which simply extends the
// standard PHPMailer by overriding the default constructor.
// Newer versions of PHPMailer attempt to register a class autoloader.
// However, the version included with Moodle does not include the autoloader
// for performance reasons.  We don't need it, so we override it.
// Otherwise, an error is thrown when PHPMailer is instantiated.

class CronMailer extends PHPMailer\PHPMailer\PHPMailer
{
  public function __construct() { }
}

# CLI options
$opts = getopt('q', ['quarterly']);
$report_t = (isset($opts['quarterly'])) ? "Quarterly" : "Weekly";

// run through list of offices
foreach ($active_orgs as $org) {
  $duration = (isset($opts['quarterly'])) ? 92 : $org_config[$org.'.duration']; ;
  $from = date('U', strtotime('-'.$duration.' days'));
  $to = date('U');

  if (empty($org_config[$org.'.email'])) {
    echo "WARN: No e-mail addresses specified for $org; skipping\n";
    continue;
  }

  $to_emails = $org_config[$org.'.email'];

  echo "Generating $org $report_t Report covering the past $duration days\n";

  // date range the organization wants to see
  $start_time = date('U', strtotime('-'.$duration.' days'));
  $end_time = date('U');

  // get matching records from database
  $result = fetch_exam_results($dbcon, $org, $start_time, $end_time);
  $out_fileinfo = generate_csv_file($report_dir, $org, $result);

  if ($out_fileinfo) {
    $recnum = $out_fileinfo['count'];
    $filepath = $out_fileinfo['filepath'];
    echo "$recnum records saved to $filepath\n";
    $rc = send_email($org, $report_t, $cfg['smtp'], $to_emails, $filepath);
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
  $mail = new CronMailer;
  $mail->isSMTP();
  $mail->Host = $smtp['host'];
  $mail->SMTPAuth = $smtp['auth'];

  $mail->setFrom($smtp['from'], $smtp['fromName']);
  $mail->addReplyTo($smtp['replyTo'], $smtp['replyToName']);

  if (empty($emails)) {
    echo "WARN: $org has no e-mail addresses to send to\n";
    return false;
  }

  foreach ($emails as $email) {
    $mail->addAddress($email);
  }

  if (!empty($smtp['bcc']) && is_array($smtp['bcc'])) {
    foreach ($smtp['bcc'] as $email) {
      $mail->addBCC($email);
    }
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
