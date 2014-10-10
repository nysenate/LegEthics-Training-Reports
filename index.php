<?php
  require_once 'tabs.php';

  $cur_year = date('Y');
  $sess_year = ($cur_year % 2 == 0) ? $cur_year - 1 : $cur_year;
  $startdate_session = "{$sess_year}-01-01";
  $startdate_weekago = date('Y-m-d', strtotime('-8 days'));
  $enddate = date('Y-m-d');
?>
<!doctype html>

<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]> <html class="no-js" lang="en">        <![endif]-->

<html>
<head>
<meta charset="utf-8">
<!-- You can use .htaccess and remove these lines to avoid edge case issues. -->
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="author" content="">
<meta name="description" content="">
<meta name="keywords" content="">
<!-- Mobile viewport optimized -->
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />

<link href="style.css" rel="stylesheet">

<script src="jquery.js"></script>
<script src="jquery-ui-1.8.16.custom.min.js"></script>
<script src="tabs.js"></script>

<script>
  $(function() {
    $('.tabs').tabs()
      $( ".datepicker" ).datepicker();
    $.datepicker.setDefaults({ dateFormat: 'yy-mm-dd' });
  });
</script>
</head>

<body>
<div class="container">

  <div class="page-header down" id="top">
    <h2>Legislative Ethics Report Generator</h2>
  </div>

  <section id="about">
    <ul class="tabs down">
      <li class="active"><a href="#range">User specified date range</a></li>
      <li><a href="#fixed">From start of session to date</a></li>
    </ul>

    <div class="tab-content" id="my-tab-content">
      <?php
        generate_tab_content('range', 'a specific timeframe',
                             $startdate_weekago, $enddate, true, false);
        generate_tab_content('fixed', 'an entire session',
                             $startdate_session, $enddate, false, true);
      ?>
    </div><!-- tab-content -->
  </section>
</div><!-- container -->
</body>
</html>
