<?php
    require_once ('soapclient/SforceEnterpriseClient.php');
    define("USERNAME", "g.sandoval@chasedatacorp.com");       
    define("PASSWORD", "1Nt3gr4t10n!_!");
    define("SECURITY_TOKEN", "j4RUbBkGl4PosPQOUkqIxwgz0");

    $mySforceConnection = new SforceEnterpriseClient();
    $mySforceConnection->createConnection("soapclient/wsdl.xml");
    // this sets the endpoint for a sandbox login:

    $mySforceConnection->setEndpoint('https://betterhearing.my.salesforce.com/services/Soap/c/32.0');
    $mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);
	
    $id = '00Q1o00000Ok3AP';


    function getLeadDetails($mySforceConnection, $leadId){
        $query = "SELECT Id, Status, Name, Desired_appointment__c, ISO_Country_Code__c, Anzahl_Versuche__c,Last_try_date__c, OwnerId FROM Lead WHERE Id ='" . $leadId . "'";
        $leads = $mySforceConnection->query($query);            

        if ($leads->size > 0){
            return $leads->records[0];
        }
    }

    function validateLeadAsOpen($leadRecord){
        if (isset($leadRecord->Status) && strpos($leadRecord->Status, 'Closed') !==false) {
            return false;
        }
        return true;
    }

    function setNotReachedFieldValuesOnLead($leadRecord){
        if (isset($leadRecord->Status) && strpos($leadRecord->Status, 'Recall agreement') !==false) {
            $leadRecord->Desired_appointment__c = null;
        }

        if ($leadRecord->Anzahl_Versuche__c != null) {
            $leadRecord->Anzahl_Versuche__c = $leadRecord->Anzahl_Versuche__c + 1;
        } else {
            $leadRecord->Anzahl_Versuche__c = 1;
        }

        $leadRecord->Status = 'Not reached';
        $leadRecord->Last_try_date__c = date('Y-m-d\TH:i:s\Z');
        return $leadRecord;
    }


    $leadRecord = getLeadDetails($mySforceConnection,$id);

    if (validateLeadAsOpen($leadRecord)) {
        $leadRecord = setNotReachedFieldValuesOnLead($leadRecord);
    } else {
        echo "Lead '" . $leadRecord->Name . "' was closed!";exit();
    }

    try {
        $updateResponse = $mySforceConnection->update(array($leadRecord),'Lead');
        $lead_id = $updateResponse[0]->id;
        echo "Lead '" . $leadRecord->Name . "' was successfully updated!";
        if ($lead_id == ''){
            echo " There is error in Lead Updating"; exit();
        }

        /* Completed Task */
        $sObject = array(
            "WhoId" => substr($lead_id, 0, strlen($lead_id)-3),            
            "Status"  => "Completed",
            "OwnerId" => $leadRecord->OwnerId,
            "Subject" => "Lead Not Reached :" . $leadRecord->Anzahl_Versuche__c ,
            "Description" => "Lead Not Reached :" . $leadRecord->Anzahl_Versuche__c,
            "Type" => "Call",
            "Priority" => "Normal",
            "ActivityDate" => date('Y-m-d\TH:i:s\Z')
        );
        $mySforceConnection->create(array($sObject), 'Task');
        echo "Task was successfully pushed to Lead '" . $leadRecord->Name . "'";
    }catch (Exception $e) {
        echo $mySforceConnection->getLastRequest();
        echo $e->faultstring;
    }