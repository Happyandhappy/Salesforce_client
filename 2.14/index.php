<?php 
    require_once ('soapclient/SforceEnterpriseClient.php');
    $numArray = ['double','int','boolean'];

    
    $mySforceConnection = new SforceEnterpriseClient();
    $mySforceConnection->createConnection("soapclient/enterprise.wsdl.xml");    
    $mySforceConnection->setEndpoint('https://login.salesforce.com/services/Soap/c/32.0');        

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
        }
        return $array;
    }

    ////////////////////////////////////////////////////////////////////////////////////

    $parse_array = [];
    foreach($_GET as $key => $value) {
        $parse_array[$key] = $value;
    }
    if (!isset($parse_array['U']) || !isset($parse_array['P']) || !isset($parse_array['Token'])){
        echo "UserName and PassWord are required!";
        exit();
    }

    try {
        $mySforceConnection->login($parse_array['U'], $parse_array['P'].$parse_array['Token']);
        echo "Sucessfully loggedin\r\n";

        unset($parse_array['U']);
        unset($parse_array['P']);
        unset($parse_array['Token']);


        $meta = $mySforceConnection->retrieve('Id','ApexClass',[]);
        echo "<pre>";
        print_r($meta);
        echo "</pre>";
        exit();



        
        $types = getTypes($parse_array, $meta);
        $parse_array = adjustData($parse_array, $types);

        if (isset($parse_array['SearchType']) && isset($parse_array['SearchValue'])){
            foreach ($meta as $row) {
                if ($row->name == $parse_array['SearchType']) $type = $row->type;
            }

            if (!isset($type)){
                echo "The SearchType is Unknown Field in Lead";
                exit();
            }

            // special case in Phone
            if ($parse_array['SearchType']=='Phone') $parse_array['SearchValue'] = getPhoneText($parse_array['SearchValue']);

            if (in_array($type, $numArray))
                $query = "Select l.Id From Lead l where " . $parse_array['SearchType'] . "=" . $parse_array['SearchValue'] . "";
            else
                $query = "Select l.Id From Lead l where " . $parse_array['SearchType'] . "='" . $parse_array['SearchValue'] . "'";

            unset($parse_array['SearchType']);
            unset($parse_array['SearchValue']);

            /* Run Query */
            $leads = $mySforceConnection->query($query);

            if ($leads->size > 0){
                $lead = $leads->records[0];
                $LeadObj = array(
                    "Id"     => $lead->Id,
                );
            }
        }
        else{
            $LeadObj = array();
        }

        foreach ($parse_array as $key => $value) {                    
            if (strpos($key, 'call_') !== false) continue;
            $LeadObj[$key] = $value;
        }

        // var_dump($LeadObj);
        /* Update Lead */
        echo "*** Updating Lead ...\r\n";
        $updateResponse = $mySforceConnection->update(array($LeadObj), 'Lead');
        $lead_id = $updateResponse[0]->id;
        print_r($updateResponse);
        echo "<br>";

        if ($lead_id == ''){
            echo " There is error in Lead Pushing"; exit();
        }
        /* first Completed Task */
        $sObject = array(
            "WhoId" => substr($lead_id, 0, strlen($lead_id)-3),            
            "Status"  => "Completed",
        );

        foreach ($parse_array as $key => $value) {
            if (strpos($key, 'call_') !== false) $sObject[str_replace('call_', '', $key)] = $value;
        }

        echo "<pre>";
        print_r($sObject);
        echo "</pre>";

        

        echo "*** Creating the Completed Task:\r\n";
        $createResponse = $mySforceConnection->create(array($sObject), 'Task');
        echo "<pre>";
        print_r($createResponse);
        echo "<br>";
    }catch (Exception $e) {
        echo $mySforceConnection->getLastRequest();
        echo $e->faultstring;
    }