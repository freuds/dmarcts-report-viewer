<?php

require_once(realpath(dirname(__FILE__))."/config.php");
require_once(realpath(dirname(__FILE__))."/lib/common.php");
require_once(realpath(dirname(__FILE__))."/lib/template.php");

//####################################################################
//### main ###########################################################
//####################################################################
// Mode report data ou pas
$reportID = (isset($_REQUEST['report']) && is_numeric($_REQUEST['report'])) ? $_REQUEST['report'] : false;

$html = array();

$html[] = "<!DOCTYPE html>";
$html[] = "<html lang=\"en\">";
$html[] = " <head>";
$html[] = "   <meta http-equiv='content-type' content='text/html; charset=UTF-8' />";
$html[] = "   <meta http-equiv='cache-control' content='no-cache, must-revalidate' />";
$html[] = "    <title>DMARC Report Viewer</title>";
if ($reportID == false)
{
  $html[] = "    <link rel='stylesheet' type='text/css' href='//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css' />";
  $html[] = "    <link rel='stylesheet' type='text/css' href='./css/default.css' />";
  $html[] = "    <script type=\"text/javascript\" src=\"//cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js\"></script>";
  $html[] = "    <script type=\"text/javascript\" src=\"//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js\"></script>";
  $html[] = "    <script type=\"text/javascript\" src=\"./js/default.js\"></script>";
}

$html[] = " </head>";
$html[] = "<body>";


if ($reportID == false)
{
  // list of domain available
  $domainAvailable = getDomainAvailable();
  $domainChoose = (isset($_REQUEST['domain']) && in_array($_REQUEST['domain'], $domainAvailable)) ? $_REQUEST['domain'] : "all";

  // list of Organization available
  $organizationAvailable = getOrganizatioAvailable();
  $organizationChoose = (isset($_REQUEST['org']) && in_array($_REQUEST['org'], $organizationAvailable)) ? $_REQUEST['org'] : "all";

  // Get list of allowed reports
  $allowed_reports = getAllowedReports($domainChoose, $organizationChoose);

  $html[] = "<div class='top-global'><a href='https://".$urlhomebase."/'>webdmarc</a></div>";
  $html[] = "<table cellpadding=0 cellspacing=0 border=0 width='100%' style='margin-top: 10px;'>";
  $html[] = tmpl_searchForm();
  $html[] = " <tr>";
  $html[] = "     <td class='bordure'>";
  $html[] = "     <fieldset>";
  $html[] = "     <legend>DMARC Reports</legend>";

  // Display Summary by domain/org
  $html[] = tmpl_reportSummary($domainChoose, $organizationChoose);
  $html[] = tmpl_reportList($allowed_reports);
  $html[] = "     </fieldset>";
  $html[] = "     <div id='reportdata'></div>";
  $html[] = "     </td>";
  $html[] = " </tr>";
  $html[] = "</table>";

} else {
  $html[] = tmpl_reportData($reportID);
}
$html[] = "</body>";
$html[] = "</html>";

print_r(implode("\n ",$html));
?>
