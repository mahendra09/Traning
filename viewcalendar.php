<html>
  <head>
    <style type="text/css">
      html {
        font-family: sans-serif;
      }
      .trips {
        color:red;
      }
      .courses {
        color:blue;
      }
      .canceled {
        color:orange;
        text-decoration:line-through;
      }
      .marketing {
        color:green;
      }
      tr>td {
        vertical-align: top;
      }
      td.time {
        width: 110px;
      }
      td.date {
        width: 140px;
      }
      td.summary {
        font-weight:bold;
      }
      td {
        padding: 0 0 5px 0;
        border-bottom: 2px solid grey;
      }
      td td {
        border-bottom: none;
      }
      span.location {
        font-weight:normal;
        color:black;
        margin-left:10px;
      }
    </style>
  </head>
  <body>
    [html comment broken up, to keep syntax highlighting in the php below]
    < ! -- // having a html comment around php keeps php debug output from echo, print_r etc in source view
<?php
  /******************
  * list calendar-events mimicking the google calendar agenda-view
  * allows to show a non-public calendars to non-authenticated clients
  * code fragments from
  * http://www.daimto.com/google-calendar-api-with-php-service-account/
  * https://developers.google.com/google-apps/calendar/v3/reference/events/list#response
  * https://developers.google.com/google-apps/calendar/quickstart/php#step_3_set_up_the_sample
  ******************/
  require_once 'vendor/autoload.php';
  session_start();
  $Email_address = 'k....e@b....3.iam.gserviceaccount.com';
  // for security reasons, keep key_file outside webroot:
  $key_file_location = '/home/t...../...41.p12';
  $client = new Google_Client();
  $client-> setApplicationName("K....");
  $key = file_get_contents($key_file_location);
  // the calendars to query
  $calendars = array(
    'trips'    => 'k....st@group.calendar.google.com',
    'courses'  => '3....4s@group.calendar.google.com',
    'canceled' => 'p....f@group.calendar.google.com',
  );
  $agenda = array(); // the merged result from all calendarsi
  $maxResults = 15; // no. of results to get (per calendar)
  $firstDate = new DateTime(); // the date from which on we want the agenda
  $firstDate->setTime(0,0,0); // date "without" time, we think in full days only
  // $firstDate->modify('+2 days'); // testing other start-dates
  setlocale (LC_ALL, 'de_DE'); // to get weekdays & monthnames correct
  $scopes ="https://www.googleapis.com/auth/calendar.readonly";
  $cred = new Google_Auth_AssertionCredentials(
    $Email_address,
    array($scopes),
    $key
  );
  $client->setAssertionCredentials($cred);
  if($client->getAuth()->isAccessTokenExpired()) {
    $client->getAuth()->refreshTokenWithAssertion($cred);
  }
  $service = new Google_Service_Calendar($client);

  foreach($calendars as $cal_name => $cal_id) {
    // get the dates from each calendar
    $calendar_res = $service->calendars->get($cal_id);
    $optParams = array(
      'maxResults' => $maxResults,
      'orderBy' => 'startTime',
      'singleEvents' => true,
      'timeMin' => $firstDate->format('c')
    );
    $events = $service->events->listEvents($calendar_res->id, $optParams);

    foreach ($events->getItems() as $event) {
      $startDate = new DateTime();
      $endDate = new DateTime();
      // full-day events use 'date', others 'dateTime' so we need to treat separately:
      if(isset($event->start->date)){
        // it's a full day event, only a date is given
        $startDate->setTimestamp(strtotime($event->start->date));
        $endDate->setTimestamp(strtotime($event->end->date));
        // full-day end-date is returned by google as the next day (midnight),
        // correct this for our display:
        $endDate->sub(new DateInterval('P1D'));
        // remove times, they would contain data from the last processed non- full-day event
        // also, we will test ifset to recognize full- against non- full-day events
        unset($startTime);
        unset($endTime);
      }else{
        // it's a non-full day, having start/end dates AND times
        $startDate->setTimestamp(strtotime($event->start->dateTime));
        $endDate->setTimestamp(strtotime($event->end->dateTime));
        // extract times
        $startTime = $startDate->format('G:i');
        $endTime = $endDate->format('G:i');
        // set times to zero, so date comparison works correctly
        $startDate->setTime(0,0,0);
        $endDate->setTime(0,0,0);
      }

      // for every day of the event, make an entry in the agenda
      $currDate = $startDate; // the date we are about to add an entry to
      while ($endDate >= $currDate){
        // don't add entries that are before our first wanted date
        if ($currDate >= $firstDate){
          if (isset ($startTime)){
            $time = $startTime . " - " . $endTime;
          }else{
            $time = "Ganztägig";
          };
          // we save the date in a way so the agenda-array can later be sorted by it
          $agenda[$currDate->format('Y-m-d')][] =
            array(
              'cal' => $cal_name,
              'summary' => $event->getSummary(),
              'location' => $event->getLocation(),
              'start' => $startDate->format('Y-m-d') . " - " . $startTime,
              'end' => $endDate->format('Y-m-d') . " - " . $endTime,
              'time' => $time
            );
        };
        // go to next day
        $currDate->modify('+1 day');
      };
    }
  }

  // the agenda-array is not yet sorted, events were added by calendar by date, not just by date
  ksort ($agenda); // sort by key (date)
  //var_dump($agenda);
  //print_r($agenda);
?>
    // end of html comment around php (keeps debug output in source view) -->

<?
  //output
  echo "    <table>";
  foreach ($agenda as $aDate => $events){
    // a row for every date
    echo "\n      <tr>";
    echo "\n        <td class=\"date\">" . strftime('%a %e. %B', strtotime($aDate)) . "</td>";
    echo "\n        <td>";
    // a table of events for every day
    echo "\n          <table >";
    foreach ($events as $aEvent){
      // a row for every event
      echo "\n            <tr>";
      echo "\n              <td class=\"time\">" . $aEvent['time'] ."</td>";
      echo "\n              <td class=\"" . $aEvent['cal'] . " summary\">" . $aEvent['summary'];
      echo "\n                <span class=\"location\">" . $aEvent['location'] . "</span>";
      echo "\n              </td>";
      echo "\n            </tr>";
     };
    echo "\n          </table>";
    ech