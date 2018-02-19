<?php

//####################################################################
//### template functions #############################################
//####################################################################

/* display report summary per domain */
function tmpl_reportSummary($dom, $org)
{
  $stat = getStatisticDomain($dom, $org);

  $tot['Processed'] = (empty($stat['Processed'])) ? 0 : $stat['Processed'];
  $tot['FullyAligned'] = (empty($stat['FullyAligned'])) ? 0 : $stat['FullyAligned'];
  $tot['Failed'] = (empty($stat['Failed'])) ? 0 : $stat['Failed'];

  $pct['FullAligned'] = round($stat['FullyAligned'] * 100 / $stat['Processed'], 2);
  $pct['Failed'] = round($stat['Failed'] * 100 / $stat['Processed'], 2);

  $tot['mindate'] = (empty($stat['mindate'])) ? "No Statistic available" : "Statistics since : ".util_formatDate($stat['mindate'], 'j F Y');

  $reportSummary[] = "<table border=0 cellpadding=0 cellspacing=0 class='reportSummary center'>";
  $reportSummary[] = "<tr>";
  $reportSummary[] = "      <td width='33%'><h3 class='processed'>".$tot['Processed']."</h3><p>Processed</p></td>";
  $reportSummary[] = "      <td width='33%'><h3 class='fullaligned'>".$tot['FullyAligned']."</h3><p><span class='fullaligned'>".$pct['FullAligned']."%</span>&nbsp;Fully Aligned</p></td>";
  $reportSummary[] = "      <td width='33%'><h3 class='failed'>".$tot['Failed']."</h3><p><span class='failed'>".$pct['Failed']."%</span>&nbsp;Failed</p></td>";
  $reportSummary[] = "</tr>";
  $reportSummary[] = "<tr>";
  $reportSummary[] = "      <td width='100%' colspan='3'><span class='stat'>".$tot['mindate']."</span></td>";
  $reportSummary[] = "</tr>";
  $reportSummary[] = "</table>";

  return implode("\n" ,$reportSummary);
}

/* display search form */
function tmpl_searchForm() {
  global $domainAvailable, $domainChoose;
  global $organizationAvailable, $organizationChoose;

  $reportSearchForm[] = "<tr>";
  $reportSearchForm[] = " <td class='reportsearchform' align='center'>";
  $reportSearchForm[] = "   <label for='domain'>Domains : </label>";
  $reportSearchForm[] = "   <select id='id_domain' name='domain'>";
  $reportSearchForm[] = "     <option value='all'>All</option>";
  foreach($domainAvailable as $i => $dn)
  {
    $_sel = ($domainChoose == $dn) ? "selected=\"selected\"" : "";
    $reportSearchForm[] = "     <option value='".$dn."' ".$_sel.">".$dn."</option>";
  }
  $reportSearchForm[] = "   </select>";


  $reportSearchForm[] = "&nbsp;&nbsp;";
  $reportSearchForm[] = "   <label for='org'>Organizations : </label>";
  $reportSearchForm[] = "   <select id='id_org' name='org'>";
  $reportSearchForm[] = "     <option value='all'>All</option>";
  foreach($organizationAvailable as $i => $org)
  {
    $_sel = ($organizationChoose == $org) ? "selected=\"selected\"" : "";
    $reportSearchForm[] = "   <option value='".$org."' ".$_sel.">".$org."</option>";
  }
  $reportSearchForm[] = "   </select>";

  $reportSearchForm[] = "&nbsp;&nbsp;";

  $reportSearchForm[] = " <button id='id_action_reset' style='height: 25px;' class='ui-button ui-corner-all ui-widget ui-button-icon-only'>";
  $reportSearchForm[] = " <span class='ui-button-icon ui-icon ui-icon-gear'></span>";
  $reportSearchForm[] = " <span class='ui-button-icon-space'></span>";
  $reportSearchForm[] = " </button>";
  $reportSearchForm[] = " </td>";
  $reportSearchForm[] = "</tr>";

  return implode("\n  ",$reportSearchForm);
}

/* display report list */
function tmpl_reportList($allowed_reports) {
    $separator = "&amp;";

    $reportlist[] = "<table class='reportlist'>";
    $reportlist[] = "  <thead>";
    $reportlist[] = "    <tr>";
    $reportlist[] = "      <th>Start Date</th>";
    $reportlist[] = "      <th>End Date</th>";
    $reportlist[] = "      <th>Domain</th>";
    $reportlist[] = "      <th>Reporting Organization</th>";
    $reportlist[] = "      <th>Report ID</th>";
    $reportlist[] = "      <th>Messages</th>";
    $reportlist[] = "    </tr>";
    $reportlist[] = "  </thead>";
    $reportlist[] = "  <tbody>";

    foreach ($allowed_reports[BySerial] as $row) {

        $reportLink = array();
        $date_output_format = "r";
        $reportlist[] =  "    <tr>";
        $reportlist[] =  "      <td class='right'>". util_formatDate($row['mindate'], $date_output_format). "</td>";
        $reportlist[] =  "      <td class='right'>". util_formatDate($row['maxdate'], $date_output_format). "</td>";
        $reportlist[] =  "      <td class='center'>". $row['domain']. "</td>";
        $reportlist[] =  "      <td class='center'>". $row['org']. "</td>";
        $reportlist[] =  "      <td class='center'><a id='linkreport:".$row['serial']."' href='#'>". $row['reportid']. "</a></td>";
        $reportlist[] =  "      <td class='center'>". $row['rcount']. "</td>";
        $reportlist[] =  "    </tr>";
    }
    $reportlist[] =  "  </tbody>";
    $reportlist[] =  "</table>";

    #indent generated html by 2 extra spaces
    return implode("\n  ",$reportlist);
}

/* display report data */
function tmpl_reportData($reportnumber) {

    $commonData = getDataReportCommon($reportnumber);
    $detailData = getDataReport($reportnumber);

    if (isset($commonData) && count($commonData) > 0) {
        $reportdata[] = "<div class='center reportdesc'><p>Report from ".$commonData['org']." for ".$commonData['domain']."<br>(". util_formatDate($commonData['mindate'], "r" ). " - ".util_formatDate($commonData['maxdate'], "r" ).")</p></div>";
    } else {
        return "Unknown report number!";
    }
    $reportdata[] = "<a id='rpt".$reportnumber."'></a>";
    $reportdata[] = "<table class='reportdata'>";
    $reportdata[] = "  <thead>";
    $reportdata[] = "    <tr>";
    $reportdata[] = "      <th>IP Address</th>";
    $reportdata[] = "      <th>Host Name</th>";
    $reportdata[] = "      <th>Message Count</th>";
    $reportdata[] = "      <th>Disposition</th>";
    $reportdata[] = "      <th>Reason</th>";
    $reportdata[] = "      <th>DKIM Domain</th>";
    $reportdata[] = "      <th>Raw DKIM Result</th>";
    $reportdata[] = "      <th>SPF Domain</th>";
    $reportdata[] = "      <th>Raw SPF Result</th>";
    $reportdata[] = "    </tr>";
    $reportdata[] = "  </thead>";
    $reportdata[] = "  <tbody>";

    foreach ($detailData as $key => $entry)
    {
      $reportdata[] = "    <tr class='".$entry['status']."'>";
      $reportdata[] = "      <td>". $entry['ip']. "</td>";
      $reportdata[] = "      <td>". gethostbyaddr($entry['ip']). "</td>";
      $reportdata[] = "      <td>". $entry['rcount']. "</td>";
      $reportdata[] = "      <td>". $entry['disposition']. "</td>";
      $reportdata[] = "      <td>". $entry['reason']. "</td>";
      $reportdata[] = "      <td>". $entry['dkimdomain']. "</td>";
      $reportdata[] = "      <td>". $entry['dkimresult']. "</td>";
      $reportdata[] = "      <td>". $entry['spfdomain']. "</td>";
      $reportdata[] = "      <td>". $entry['spfresult']. "</td>";
      $reportdata[] = "    </tr>";
    }
    $reportdata[] = "  </tbody>";
    $reportdata[] = "</table>";

    $reportdata[] = "<!-- End of report rata -->";
    $reportdata[] = "";

    #indent generated html by 2 extra spaces
    return implode("\n  ",$reportdata);
}

/* display import form */
function tmpl_importForm() {
    $replace_checked = (isset($_POST['replace_report']) && $_POST['replace_report'] == "1" ? ' checked' : '');
    $html = <<<HTML
    <h1>DMARC Import</h1>
    <form class="import_report" method="post" enctype="multipart/form-data">
        <label for="report_file">DMARC report:</label>&nbsp;
        <input type="file" name="report_file" id="report_file">
        <input type="checkbox" name="replace_report" id="replace_report" value="1"{$replace_checked}>&nbsp;
        <label for="replace_report">Replace report</label>
        <input type="submit" name="submit_report">
    </form>
HTML;
    return $html;
}

?>
