<?php
    require_once ('soapclient/SforceEnterpriseClient.php');
    $numArray = ['double','int','boolean'];

    $mySforceConnection = new SforceEnterpriseClient();
    $mySforceConnection->createConnection("soapclient/enterprise.wsdl.xml");
    $mySforceConnection->setEndpoint('https://login.salesforce.com/services/Soap/c/32.0');
    
    $debug = 0;
    if (isset($_GET['debug'])) $debug = $_GET['debug'];
    unset($_GET['debug']);

    $host = '';
    $showlead = 0;
    if (isset($_GET['showlead'])) $showlead = $_GET['showlead'];
    unset($_GET['showlead']);

    $consoleview = 0;
    if (isset($_GET['consoleview'])) $consoleview = $_GET['consoleview'];
    unset($_GET['consoleview']);

    /* get format phone number string */
    function getPhoneText($numbers){
        return "(" . substr($numbers,0,3) . ") " . substr($numbers, 3,3) . "-" . substr($numbers, 6);
    }
    
    function getTypes($array, $types){
        $list = [];
        foreach ($array as $key => $value) {
            foreach ($types as $row) {
                if ($row->name == $key) $list[$key] = $row->type;
            }
        }
        return $list;
    }

    function adjustData($array, $types){
        foreach ($array as $key => $value) {
            if (isset($types[$key]) and $types[$key] == 'datetime'){
                $array[$key] = date('Y-m-d\TH:i:s\Z', strtotime($value));
            }

            if (strpos($value, '!TIME_EDT_') !== false){
                // $array[$key] = gmdate('Y-m-d\TH:i:s\Z', strtotime(str_replace('!TIME_EDT_','',$value)));
                $date = new DateTime(str_replace('!TIME_EDT_','',$value), new DateTimeZone('America/New_York'));
                $date->setTimezone(new DateTimeZone('UTC'));
                $array[$key] = $date->format('Y-m-d\TH:i:s\Z');
            }else if (strpos($value, '!TIME_CDT_') !== false){
                // $array[$key] = gmdate('Y-m-d\TH:i:s\Z', strtotime(str_replace('!TIME_CDT_','',$value)));
                $date = new DateTime(str_replace('!TIME_CDT_','',$value),new DateTimeZone('America/Chicago'));
                $date->setTimezone(new DateTimeZone('UTC'));
                $array[$key] = $date->format('Y-m-d\TH:i:s\Z');
                
            }else if (strpos($value, '!TIME_MST_') !== false){
                // $array[$key] = gmdate('Y-m-d\TH:i:s\Z', strtotime(str_replace('!TIME_MST_','',$value)));
                $date = new DateTime(str_replace('!TIME_MST_','',$value), new DateTimeZone('America/Denver'));
                $date->setTimezone(new DateTimeZone('UTC'));
                $array[$key] = $date->format('Y-m-d\TH:i:s\Z');
            }else if (strpos($value, '!TIME_') !== false){
                $array[$key] = gmdate('Y-m-d\TH:i:s\Z', strtotime(str_replace('!TIME_','',$value)));
            }
        }
        return $array;
    }

    function logger($str){
        $fh = fopen('log.txt', 'a') or die("can't open file");
        fwrite($fh, $str . "\r\n");
        fclose($fh);
    }
    

    function url_to_domain($url)
    {
        $host = @parse_url($url, PHP_URL_HOST);
        if (substr($host, 0, 4) == "www.")
            $host = substr($host, 4);
        return $host;
    }



    /*
     * function to get special values
     */
    $specialVals = array(
        "!DATENOW!" => gmdate("Y-m-d\TH:i:s\Z", strtotime('now')),
        "!TMRW!"    => gmdate("Y-m-d\TH:i:s\Z", strtotime('+1 day', strtotime('now'))),
        "!WEEK!"    => gmdate("Y-m-d\TH:i:s\Z", strtotime('+1 week', strtotime('now'))),
        "!MONTH!"    => gmdate("Y-m-d\TH:i:s\Z", strtotime('+1 month', strtotime('now'))),
    );
    function getSpecialVal($name){
        $specialVals = $GLOBALS['specialVals'];
        if (array_key_exists($name, $specialVals)){
            return $specialVals[$name];
        }
        return null;
    }

    if ($debug){
        logger(date('Y-m-d H:i:s'));
        logger("--------------------------------------------------------------");
    }

    ////////////////////////////////////////////////////////////////////////////////////

    $parse_array = [];
    foreach($_GET as $key => $value) {
        $checkVal = getSpecialVal($value);
        if ($checkVal!==null) $parse_array[$key] = $checkVal;
        else $parse_array[$key] = $value;
    }

    // echo "<pre>";
    // print_r($parse_array);
    // echo "</pre>";
    // exit();

    if (!isset($parse_array['U']) || !isset($parse_array['P']) || !isset($parse_array['Token'])){
        echo "UserName and PassWord are required!";
        exit();
    }

    $is_update = false;
    
    try {
        $res = $mySforceConnection->login($parse_array['U'], $parse_array['P'].$parse_array['Token']);
        $host = url_to_domain($res->serverUrl);
        
        echo "Sucessfully logged in\r\n";
        if ($debug) logger('Sucessfully logged in');

        unset($parse_array['U']);
        unset($parse_array['P']);
        unset($parse_array['Token']);


        $meta = $mySforceConnection->describeSObject('Lead')->fields;
        $types = getTypes($parse_array, $meta);
        $parse_array = adjustData($parse_array, $types);

        // echo "<pre>";
        // print_r($parse_array);
        // echo "</pre>";
        // exit;

        if (isset($parse_array['SearchType']) && isset($parse_array['SearchValue'])){
            foreach ($meta as $row) {
                if ($row->name == $parse_array['SearchType']) $type = $row->type;
            }

            if (!isset($type)){
                echo "The SearchType is Unknown Field in Lead";
                if ($debug) logger("The SearchType is Unknown Field in Lead");
                exit();
            }

            // special case in Phone
            // if ($parse_array['SearchType']=='Phone') $parse_array['SearchValue'] = getPhoneText($parse_array['SearchValue']);
            if (isset($parse_array['removeleading'])){
                if ($parse_array['removeleading'] != '')
                    $parse_array['SearchValue'] = substr($parse_array['SearchValue'], $parse_array['removeleading']); 
                unset($parse_array['removeleading']);
            }

            if (in_array($type, $numArray))
                $query = "Select l.Id From Lead l where " . $parse_array['SearchType'] . "=" . $parse_array['SearchValue'] . "";
            else
                $query = "Select l.Id From Lead l where " . $parse_array['SearchType'] . "='" . $parse_array['SearchValue'] . "'";

            unset($parse_array['SearchType']);
            unset($parse_array['SearchValue']);
            /* Run Query */
            $leads = $mySforceConnection->query($query);
            if ($debug) {
                logger("Query : " . $query);
                logger(json_encode($leads));
            }
            if ($leads->size > 0){
                $lead = $leads->records[0];
                $LeadObj = array(
                    "Id"     => $lead->Id,
                );
                $is_update = true;
            }
        }
        else{
            $LeadObj = array();
        }        

        foreach ($parse_array as $key => $value) {
            if (strpos($key, 'call_') !== false or $value ==='') continue;
            $LeadObj[$key] = $value;
        }

        if ($debug){
            logger("Lead Object fields:");
            logger(json_encode($LeadObj));
        }
        

        $sObject = [];
        foreach ($parse_array as $key => $value) {
            if (strpos($key, 'call_') !== false) {
                $sObject[str_replace('call_', '', $key)] = $value;
                $is_task = true;
            }
        }

        echo "<pre>";
        print_r($LeadObj);
        echo "</pre>";

        /* Update Lead */
        if ($is_update){
            echo "*** Updating Lead ...\r\n";
            if ($debug) logger("*** Updating Lead ...");
            $updateResponse = $mySforceConnection->update(array($LeadObj), 'Lead');
            $lead_id = $updateResponse[0]->id;
            print_r($updateResponse);
            if ($debug) logger(json_encode($updateResponse));

        }else{
            echo "*** Creating new Lead ...\r\n";
            if ($debug) logger("*** Creating new Lead ...");
            $createResponse = $mySforceConnection->create(array($LeadObj), 'Lead');
            $lead_id = $createResponse[0]->id;
            print_r($createResponse);
            if ($debug) logger(json_encode($createResponse));
        }
        echo "<br>";

        if ($lead_id == ''){
            if ($debug) logger("There is error in Lead Pushing");
            echo "There is error in Lead Pushing"; exit();
        }

        $is_task = false;
        /* first Completed Task */
        $sObject = array(
            "WhoId" => substr($lead_id, 0, strlen($lead_id)-3),
            "Status"  => "Completed",
        );

        foreach ($parse_array as $key => $value) {
            if (strpos($key, 'call_') !== false) {
                $sObject[str_replace('call_', '', $key)] = $value;
                $is_task = true;
            }
        }

        echo "<pre>";
        print_r($sObject);
        echo "</pre>";

        
        if ($is_task){
            echo "*** Creating the Completed Task:\r\n";
            if ($debug) {
                logger("Task Object fields:");
                logger("*** Creating the Completed Task");
                logger(json_encode($sObject));    
            }            

            $createResponse = $mySforceConnection->create(array($sObject), 'Task');
            echo "<pre>";
            print_r($createResponse);
            if ($debug) logger(json_encode($createResponse));
        }else{
            echo "Completed Task was not created because there is not fields";
            if ($debug) logger("Completed Task was not created because there is not fields");
        }
        echo "<br>";
    }catch (Exception $e) {
        echo $mySforceConnection->getLastRequest();
        echo $e->faultstring;
        if ($debug) logger($e->faultstring);
    }
    // download log file
    if ($debug){
        logger(' ');
        echo '<a href="../log.txt" id="download" download></a>';
        echo '<script>document.getElementById("download").click()</script>';
    }

    if (isset($lead_id) && isset($host) && $showlead === '1'){
        if ($consoleview){
            $url = "https://".$host ."/console#/".$lead_id;
        }
        else
            $url = 'https://' . $host . "/lightning/r/". $lead_id . "/view";

        echo $url;
        if ($debug) 
            logger("Redirect Url : " . $url);
        header('Location:' . $url, true);
        exit();
    }
?>