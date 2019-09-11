<?php 
    require_once ('soapclient/SforceEnterpriseClient.php'); 
    $Subject = "";
    $OwnerId = NULL;
    if(isset($_GET['PrimaryPhone'])){$PrimaryPhone = $_GET['PrimaryPhone'];}
    if(isset($_GET['Disposition'])){$Disposition = $_GET['Disposition'];}
    if(isset($_GET['Notes'])){$Notes = $_GET['Notes'];}
    if(isset($_GET['Call_Recording'])){$Call_Recording = $_GET['Call_Recording'];}
    if(isset($_GET['CallDurationInSeconds'])){$CallDurationInSeconds = $_GET['CallDurationInSeconds'];}
    if(isset($_GET['WhoId'])){$WhoId = $_GET['WhoId'];}
    if(isset($_GET['FirstName'])){$FirstName = $_GET['FirstName'];}
    if(isset($_GET['LastName'])){$LastName = $_GET['LastName'];}
    if(isset($_GET['OwnerId'])){$OwnerId = $_GET['OwnerId'];}
    if(isset($_GET['Status'])){$Status = $_GET['Status'];}
    if(isset($_GET['desc_disposition'])){$desc_disposition = $_GET['desc_disposition'];}
    if(isset($_GET['due_date'])) $due_date = $_GET['due_date'];
    if(isset($_GET['Subject'])) $Subject = $_GET['Subject'];
    if(isset($_GET['calltype'])) $calltype = $_GET['calltype'];
    if(isset($_GET['callattempt'])) $callattempt = $_GET['callattempt'];
    if($Disposition == "Closed" && isset($_GET['closing_category'])) $closing_category = $_GET['closing_category'];
    if(isset($_GET['chasedata_id'])) $chasedata_id = $_GET['chasedata_id'];

    try {
           
        define("USERNAME", "g.sandoval@chasedatacorp.com");       
        define("PASSWORD", "1Nt3gr4t10n!_!");
        define("SECURITY_TOKEN", "j4RUbBkGl4PosPQOUkqIxwgz0");
        
    
        $mySforceConnection = new SforceEnterpriseClient();
        $mySforceConnection->createConnection("soapclient/enterprise.wsdl.xml");
        // this sets the endpoint for a sandbox login:

        // $mySforceConnection->setEndpoint('https://betterhearing.my.salesforce.com/services/Soap/c/32.0');

        $mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);
    
        // print_r($mylogin);
        if ($Disposition == "Closed" && !isset($closing_category)) {
            echo "Invalid parameters in Url!"; exit;
        }
       
        if (isset($FirstName) && isset($LastName) && isset($PrimaryPhone) && isset($Disposition) && isset($Notes) && isset($Call_Recording) && isset($WhoId) && isset($CallDurationInSeconds) && isset($Status)  && isset($calltype)  && isset($callattempt)){
            
            $Description = $FirstName . " " . $LastName . "\r\nPrimaryPhone:" . $PrimaryPhone . "\r\nDisposition:" . $desc_disposition .  "\r\nNotes:" . $Notes . "\r\nCall Recording:" . $Call_Recording; 

            /* first Completed Task */
            $sObject = array(
                "Description" => $Description,
                "CallDurationInSeconds" => $CallDurationInSeconds,
                "WhoId" => $WhoId,
                "OwnerId" => $OwnerId,
                "Status"  => $Status,
                "Type" => "Call",
                "Subject" => $calltype . " " . $PrimaryPhone,
                "Priority" => "Normal",
                "ActivityDate" => date('Y-m-d hh:mm:ss')
            );
            if (!$OwnerId) unset($sObject['OwnerId']);

            echo "**** Creating the 1st Completed Task:\r\n";
            $createResponse = $mySforceConnection->create(array($sObject), 'Task');
            print_r($createResponse);
            echo "<br>"; 

            /* 2nd Completed Task */
            $sObject['Subject'] = $desc_disposition . ": " . $callattempt;
            $sObject['ChaseData_CallDuration__c'] = $CallDurationInSeconds;
            echo "**** Creating the 2nd Completed Task:\r\n";            
            $createResponse = $mySforceConnection->create(array($sObject), 'Task');
            print_r($createResponse);
            echo "<br>";

            if (isset($due_date) && isset($Subject)){
                /* Cretae future Task */
                $date = strtotime($due_date);
                $sObject['ActivityDate'] = date('Y-m-d hh:mm:ss',$date);
                $sObject['Subject'] = $Subject;
                $sObject['Status'] = 'Not started';
                unset($sObject['CallDurationInSeconds']);
                
                $createResponse = $mySforceConnection->create(array($sObject), 'Task');
                echo "**** Creating the following:\r\n";
                print_r($createResponse);
            }            


            /* Update Lead Status as Disposition */
            echo "<br><br>**** Lead Update Result <br>";


            // if Disposition == "Not Reached" then call apex function
            if ($Disposition === "Not Reached") {
                // Define constants for the web service. We'll use these later
                $parsedURL = parse_url($mySforceConnection->getLocation());
                define ("_SFDC_SERVER_", substr($parsedURL['host'],0,strpos($parsedURL['host'], '.')));
                define ("_WS_NAME_", 'Lead_NotReached');
                define ("_WS_WSDL_", _WS_NAME_ . '.xml');
                define ("_WS_ENDPOINT_", 'https://' . _SFDC_SERVER_ . '.salesforce.com/services/wsdl/class/' . _WS_NAME_);
                define ("_WS_NAMESPACE_", 'http://soap.sforce.com/schemas/class/' . _WS_NAME_);

                $client = new SoapClient("soapclient/" . _WS_WSDL_);
                $sforce_header = new SoapHeader(_WS_NAMESPACE_, "SessionHeader", array("sessionId" => $mySforceConnection->getSessionId()));
                $client->__setSoapHeaders(array($sforce_header));
                 
                $wrkArray = array(
                                'leadId'=> $WhoId,
                                'clickedButtonType'=> "NotLVM"
                            );

                // Call the web service
                $response = $client->setLeadAsNotReached($wrkArray);
                // Output results to browser                
                print_r($response);
            }

            /* if Disposition is Closed then update Lead with closing category */
            if ($Disposition == 'Closed'){
                $LeadObj = array(
                                    "Id"     => $WhoId,
                                    "Status" => $Disposition,
                                    "Lead_Closing_Category__c" => $closing_category,
                                    "Dialfire_Contact_ID__c" => $chasedata_id,
                            );
            }else{
                $LeadObj = array(
                                    "Id"     => $WhoId,
                                    "Status" => $Disposition,
                                    "Dialfire_Contact_ID__c" => $chasedata_id,
                            );
            }

            $updateResponse = $mySforceConnection->update(array($LeadObj), 'Lead');
            print_r($updateResponse);
        }else{
            echo "Invalid parameters in Url!";
        }
    }catch (Exception $e) {
        echo $mySforceConnection->getLastRequest();
        echo $e->faultstring;
    }