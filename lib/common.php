<?php

//####################################################################
//### defines ########################################################
//####################################################################

define("BySerial", 1);
define("ByDomain", 2);
define("ByOrganisation", 3);

// The $_FILES key for uploaded reports
define('REPORT_FILE', 'report_file');

//####################################################################
//### utility functions ##############################################
//####################################################################

function util_formatDate($date, $format) {
    $answer = date($format, strtotime($date));
    return $answer;
};


function util_checkUploadedFile($file_name, &$options = array()) {
    if (!isset($_FILES[$file_name]) || $_FILES[$file_name]['size'] == 0) {
        throw new Exception("missing file");
    }
    if (!is_uploaded_file($_FILES[$file_name]['tmp_name'])) {
        throw new Exception("non-uploaded file");
    }

    if (isset($options['mimetype'])) {
        $mimetypes = !is_array($options['mimetype']) ? array($options['mimetype']) : $options['mimetype'];
        if (!in_array($_FILES[$file_name]['type'], $mimetypes)) {
            throw new Exception("invalid filetype: {$_FILES[$file_name]['type']}");
        }
        $options['mimetype'] = $_FILES[$file_name]['type'];
    }
    return $_FILES[$file_name]['tmp_name'];
}

function util_extractGZip($file_name, &$xml_file_name) {
    $gz_fd = fopen('compress.zlib://'.$file_name, 'r');
    if (!$gz_fd) {
        throw new Exception("unable to open gzip file");
    }

    $dot = strrpos($file_name, '.');
    $xml_file_name = ($dot !== false ? substr($file_name, $dot) : $file_name);

    return $gz_fd;
}

function util_extractZip($file_name, &$xml_file_name) {
    // We must decompress the zip file
    $zip = new ZipArchive();
    if (!$zip->open($file_name)) {
        throw new Exception("unable to open zipfile: ".$zip->getStatusString());
    }

    if ($zip->numFiles != 1) {
        $numFiles = $zip->numFiles;
        $zip->close();
        throw new Exception("unexpected file count in zipfile: $numFiles");
    }

    $xml_file_name = $zip->getNameIndex(0);
    if (!$xml_file_name || strcasecmp(substr($xml_file_name, -3), 'xml')) {
        throw new Exception("expected xml file, got: $xml_file_name");
    }

    $xml_fd = $zip->getStream($xml_file_name);
    if (!$xml_fd) {
        $msg = sprintf("failed to get stream for zip entry %s: %s", $zip->getNameIndex(0), $zip->getStatusString());
        $zip->close();
        throw new Exception($msg);
    }
    $zip->close();

    return $xml_fd;
}

//####################################################################
//### report-wranglin functions ######################################
//####################################################################


function report_checkXML($xml_data) {
    $data = @simplexml_load_string($xml_data);
    if ($data === false) {
        throw new Exception("failed to load xml data from file");
    }

    $root_name = $data->getName();
    if ($root_name != 'feedback') {
        throw new Exception("unexpected xml root element: {$root_name}");
    }
    return $data;
}

function report_importXML($xml, $replace_report, &$log = null) {
    $metadata = $xml->xpath('(/feedback/report_metadata)')[0];
    $policy = $xml->xpath('/feedback/policy_published')[0];

    $serial = null;
    $reports = db_execute("SELECT org,reportid,serial FROM report WHERE reportid = ?", (string)$metadata->report_id);

    if (!empty($reports) && count($reports) > 1) {
        $log[] = "unexpected number of reports for id {$metadata->report_id}";
        return false;
    }

    // We already have that report, replace if asked to, else use it
    if (!empty($reports)) {
        if ($replace_report) {
            $log[] = "Replacing old report {$reports[0]['org']}, {$reports[0]['reportid']}";
            db_execute('DELETE FROM rptrecord WHERE serial=?', $reports[0]['serial']);
            db_execute('DELETE FROM report WHERE serial=?', $reports[0]['serial']);
        } else {
            $log[] = "Report {$reports[0]['org']}, {$reports[0]['reportid']} already known";
            return true;
        }
    }

    // This is a report we don't know about
    $stmt = db_execute("INSERT INTO report(mindate,maxdate,
        domain,org,reportid,
        email,extra_contact_info,
        policy_adkim,policy_aspf,policy_p,policy_sp,policy_pct)
    VALUES(FROM_UNIXTIME(?),FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?)",
        $metadata->date_range->begin, $metadata->date_range->end,
        $policy->domain, $metadata->org_name, $metadata->report_id,
        $metadata->email, $metadata->extra_contact_info,
        $policy->adkim, $policy->aspf, $policy->p, $policy->sp, (int)$policy->pct
    );
    $serial = $stmt->insert_id;

    $records = $xml->xpath('/feedback/record');
    foreach ($records as $record) {
        $ip = $ip6 = null;
        $ipval = $record->row->source_ip;
        if (ip2long($ipval)) {
            $ip = unpack("N", inet_pton($ipval));
            $ip = $ip[1];
        } else {
            $ip6 = unpack("H*", inet_pton($ipval));
            $ip6 = $ip6[1];
        }

        $success = db_execute("INSERT INTO rptrecord(
            serial,ip,ip6,rcount,
            disposition,spf_align,dkim_align,reason,
            dkimdomain,dkimresult,
            spfdomain,spfresult,
            identifier_hfrom)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",
            $serial, $ip, $ip6, $record->row->count,
            $record->row->policy_evaluated->disposition,
            $record->row->policy_evaluated->spf, $record->row->policy_evaluated->dkim,
            $record->row->policy_evaluated->reason,
            $record->auth_results->dkim->domain, $record->auth_results->dkim->result,
            $record->auth_results->spf->domain,  $record->auth_results->spf->result,
            $record->identifiers->header_from
        );
    }

    return true;
}

function report_importFile($filename, $replace_report, &$log = array()) {
    $xml_fd = null;
    $xml_file_name = null;
    $type = mime_content_type($filename);
    switch ($type) {
        case 'application/zip':
            $xml_fd = util_extractZip($filename, $xml_file_name);
            break;

        case 'application/x-gzip':
            $xml_fd = util_extractGZip($filename, $xml_file_name);
            break;

        case 'application/xml':
            $xml_fd = fopen($filename, 'r');
            if (!$xml_fd) {
                throw new Exception("failed to open xml file: $file_name");
            }
            $xml_file_name = $filename;
            break;

        default:
            throw new Exception("unknown file type \"{$type}\" for \"{$filename}\"");
            break;
    }

    $xml_data = stream_get_contents($xml_fd);
    if (!$xml_data) {
        throw new Exception("file was empty: $xml_file_name");
    }

    $report_data = report_checkXML($xml_data);

    $success = report_importXML($report_data, $replace_report, $log);
    if (!$success) {
        throw new Exception("failed to import report");
    }
    return $success;
}

//####################################################################
//### submit handlers ################################################
//####################################################################

function submit_handleReport() {
    if (!isset($_POST['submit_report'])) return;

    $replace_report = (isset($_POST['replace_report']) && $_POST['replace_report'] == "1");

    try {
        $options = array('mimetype' => array('text/xml', 'application/zip', 'application/x-gzip'));
        $file_name = util_checkUploadedFile(REPORT_FILE, $options);

        $log = array();
        $success = report_importFile($file_name, $replace_report, $log);

        $log = implode("\n", $log);

        $html = <<<HTML
        <div class="message success">successfully imported report from file</div>
        <div class="output">Output:<br><pre>{$log}</pre></div>
HTML;
        return $html;
    } catch (Exception $e) {
        return "<div class=\"message error\">".$e->getMessage()."</div>";
    }
}

//####################################################################
//### database functions #############################################
//####################################################################

// Make a MySQL Connection using mysqli
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($mysqli->connect_errno) {
    echo "Error: Failed to make a MySQL connection, here is why: \n";
    echo "Errno: " . $mysqli->connect_errno . "\n";
    echo "Error: " . $mysqli->connect_error . "\n";
    exit;
}

function db_execute($sql) {
    global $mysqli;
    $args = func_get_args();
    array_shift($args); // Drop $sql parameter from our arguments

    $stmt = mysqli_stmt_init($mysqli);

    $success = $stmt->prepare($sql);
    if (!$success) {
        $msg = sprintf("mysqli_prepare: %s (%d)", $mysqli->error, $mysqli->errno);
        throw new Exception($msg);
    }

    $type = '';
    $refs = array();
    foreach ($args as $key => $arg) {
        if (is_integer($arg))    $type .= 'i';
        elseif (is_double($arg)) $type .= 'd';
        else                     $type .= 's';
        $refs[$key] = &$args[$key];
    }

    array_unshift($refs, $type);

    call_user_func_array(array($stmt, 'bind_param'), $refs);

    $result = $stmt->execute();
    if (!$result) {
        $msg = sprintf("mysqli_stmt_execute: %s (%d)", $stmt->error, $stmt->errno);
        throw new Exception($msg);
    }

    if ($stmt->affected_rows != -1) {
        // this looks like a not-SELECT, return our statement
        return $stmt;
    }

    // This was a SELECT, grab the results
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    return $results;
}

function getDomainAvailable()
{
  global $mysqli;
  $domains = array();
  $sql = "SELECT DISTINCT domain FROM report";
  $sql .= " WHERE mindate > DATE_SUB(NOW(), INTERVAL ".MYSQL_DAY_RETENTION." DAY)"; // limit display by date
  $query = $mysqli->query($sql) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  while ($row = $query->fetch_row()) {
      $domains[] = $row[0];
  }
  return $domains;
  $mysqli->free();
}

function getOrganizatioAvailable()
{
  global $mysqli;
  $orgs = array();
  $sql = "SELECT DISTINCT org FROM report";
  $sql .= " WHERE mindate > DATE_SUB(NOW(), INTERVAL ".MYSQL_DAY_RETENTION." DAY)"; // limit display by date
  $query = $mysqli->query($sql) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  while ($row = $query->fetch_row()) {
      $orgs[] = $row[0];
  }
  asort($orgs);
  return $orgs;
  $mysqli->free();
}

function getAllowedReports($domainChoose=null, $organizationChoose=null)
{
  global $mysqli;

  // Get allowed reports and cache them - using serial as key
  $allowed_reports = array(BySerial => array(), ByDomain => array(), ByOrganisation => array());

  # Include the rcount via left join, so we do not have to make an sql query for every single report.
  $sql = "SELECT report.* , SUM(rptrecord.rcount) AS rcount FROM `report`";
  $sql .= " LEFT JOIN rptrecord ON report.serial = rptrecord.serial";
  $sql .= " WHERE mindate > DATE_SUB(NOW(), INTERVAL ".MYSQL_DAY_RETENTION." DAY)"; // limit display by date
  // Get domain selection
  $sql .= ($domainChoose != 'all') ? " AND domain='".$domainChoose."' " : "";
  // Get organization selection
  $sql .= ($organizationChoose != 'all') ? " AND org='".$organizationChoose."' " : "";
  $sql .= " GROUP BY serial ORDER BY mindate DESC";

  $query = $mysqli->query($sql) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  while($row = $query->fetch_assoc())
  {
    $allowed_reports[BySerial][$row['serial']] = $row;
    //make a list of serials by domain and by organisation
    $allowed_reports[ByDomain][$row['domain']][] = $row['serial'];
    $allowed_reports[ByOrganisation][$row['org']][] = $row['serial'];
  }
  return $allowed_reports;
}

// Get common data for detail report
function getDataReportCommon($reportID)
{
  global $mysqli;
  $result = array();
  $sql = "SELECT mindate, maxdate, domain, org FROM report";
  $sql .= " WHERE serial = '".$reportID."' LIMIT 1";
  $query = $mysqli->query($sql) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  $result = $query->fetch_assoc();
  return $result;
}

// Get complete data for detail report
function getDataReport($reportID)
{
  global $mysqli;
  $result = array();
  $i = 0;
  $sql = "SELECT * FROM rptrecord WHERE serial = $reportID";
  $query = $mysqli->query($sql) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  while($row = $query->fetch_assoc()) {
    $result[$i] = $row;
    if (($row['dkimresult'] == "fail") && ($row['spfresult'] == "fail")) {
        $result[$i]['status'] = "red";
    } elseif (($row['dkimresult'] == "fail") || ($row['spfresult'] == "fail")) {
        $result[$i]['status'] = "orange";
    } elseif (($row['dkimresult'] == "pass") && ($row['spfresult'] == "pass")) {
        $result[$i]['status'] = "lime";
    } else {
        $result[$i]['status'] = "yellow";
    };
    if ( $row['ip'] ) {
        $result[$i]['ip'] = long2ip($row['ip']);
    }
    if ( $row['ip6'] ) {
        $result[$i]['ip'] = inet_ntop($row['ip6']);
    }
    $i++;
  }
  return $result;
}

function getStatisticDomain($domain, $org)
{
  global $mysqli;
  $stat = array();

  // Get Total processed count
  $sql = "SELECT SUM(rptrecord.rcount) AS Processed, report.mindate ";
  $sql .= " FROM report";
  $sql .= " LEFT JOIN rptrecord ON report.serial = rptrecord.serial";
  $sql .= " WHERE mindate > DATE_SUB(NOW(), INTERVAL ".MYSQL_DAY_RETENTION." DAY)";
  if ($domain != 'all')
    $sql .= " AND report.domain='".$domain."' ";
  if ($org != 'all')
    $sql .= " AND report.org='".$org."' ";
  $query = $mysqli->query($sql) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  $res = $query->fetch_assoc();

  // Get Fully Aligned count
  $sql1 = "SELECT SUM(rptrecord.rcount) AS FullyAligned ";
  $sql1 .= " FROM report";
  $sql1 .= " LEFT JOIN rptrecord ON report.serial = rptrecord.serial";
  $sql1 .= " WHERE mindate > DATE_SUB(NOW(), INTERVAL ".MYSQL_DAY_RETENTION." DAY)";
  if ($domain != 'all')
    $sql1 .= " AND report.domain='".$domain."' ";
  if ($org != 'all')
    $sql1 .= " AND report.org='".$org."' ";
  $sql1 .= " AND rptrecord.dkimresult = 'pass'";
  $sql1 .= " AND rptrecord.spfresult = 'pass'";
  $query = $mysqli->query($sql1) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  $res1 = $query->fetch_assoc();

  // Get failed count
  $sql2 = "SELECT SUM(rptrecord.rcount) AS Failed ";
  $sql2 .= " FROM report";
  $sql2 .= " LEFT JOIN rptrecord ON report.serial = rptrecord.serial";
  $sql2 .= " WHERE mindate > DATE_SUB(NOW(), INTERVAL ".MYSQL_DAY_RETENTION." DAY)";
  if ($domain != 'all')
    $sql2 .= " AND report.domain='".$domain."' ";
  if ($org != 'all')
    $sql2 .= " AND report.org='".$org."' ";
  $sql2 .= " AND ( rptrecord.dkimresult = 'fail' OR rptrecord.spfresult = 'fail')";
  $query = $mysqli->query($sql2) or die("Query failed: ".$mysqli->error." (Error #" .$mysqli->errno.")");
  $res2 = $query->fetch_assoc();

  return array_merge($res, $res1, $res2);
}
?>
