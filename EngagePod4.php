<?php

require_once 'EngagePod4/xmlLib.php';

class EngagePod4 {
    private $_baseUrl = 'http://api4.silverpop.com/XMLAPI';
    private $_session_encoding;
    private $_jsessionid;
    private $_username;
    private $_password;

    public function __construct($username, $password) {
        // It would be a good thing to cache the jsessionid somewhere and reuse it across multiple requests
        // otherwise we are authenticating to the server once for every request
        $this->_login($username, $password);
    }

    public function select($databaseName, $listName) {
        $this->useDatabase($databaseName);
        $this->useList($listName);
    }

    /**
     * $listType can be one of
     0 - Databases
     1 - Queries
     2 - Both Databases and Queries
     5 - Test Lists
     6 - Seed Lists
     13 - Suppression Lists
     15 - Relational Tables
     18 - Contact Lists
     *
     */
    public function getLists($listType = 2, $isPrivate = true) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetLists" => array(
                    "VISIBILITY" => ($isPrivate ? '0' : '1'),
                    "LIST_TYPE" => $listType,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['LIST']))
                return $result['LIST'];
            else {
                return array(); //?
            }
        } else {
            throw new Exception("GetLists Error: ".$this->_getErrorFromResponse($response));
        }
    }

    public function addContact($databaseID, $updateIfFound, $columns) {
        $data["Envelope"] = array(
            "Body" => array(
                "AddRecipient" => array(
                    "LIST_ID" => $databaseID,
                    "CREATED_FROM" => 1,        // 1 = created manually
                    "UPDATE_IF_FOUND" => ($updateIfFound ? 'true' : 'false'),
                    "COLUMN" => array(),
                ),
            ),
        );
        foreach ($columns as $name => $value) {
            $data["Envelope"]["Body"]["AddRecipient"]["COLUMN"][] = array("NAME" => $name, "VALUE" => $value);
        }
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['RecipientId']))
                return $result['RecipientId'];
            else {
                d($response);
                throw new Exception('Recipient added but no recipient ID was returned from the server.');
            }
        } else {
            throw new Exception("AddRecipient Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * $templateID - ID of template upon which to base the mailing.
     * $targetID - ID of database, query, or contact list to send the template-based mailing.
     * $mailingName - Name to assign to the generated mailing.
     * $scheduledTimestamp - When the mailing should be scheduled to send. This must be later than the current timestamp.
     * $optionalElements - An array of $key => $value, where $key can be one of
     * 						SEND_HTML,
     * 						SEND_AOL,
     * 						SEND_TEXT,
     * 						SUBJECT,
     * 						FROM_NAME,
     * 						FROM_ADDRESS,
     * 						REPLY_TO
     * $saveToSharedFolder - true/false
     *
     */
    public function sendEmail($templateID, $targetID, $mailingName, $scheduledTimestamp, $optionalElements = array(), $saveToSharedFolder = 0) {
        $data["Envelope"] = array(
            "Body" => array(
                "ScheduleMailing" => array(
                    "TEMPLATE_ID" => $templateID,
                    "LIST_ID" => $targetID,
                    "MAILING_NAME" => $mailingName,
                    "VISIBILITY" => ($saveToSharedFolder ? '1' : '0'),
                    "SCHEDULED" => date("m/d/Y h:i:s A",$scheduledTimestamp),
                ),
            ),
        );
        foreach ($optionalElements as $key => $value) {
            $data["Envelope"]["Body"]["ScheduleMailing"][$key] = $value;
        }

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['MAILING_ID']))
                return $result['MAILING_ID'];
            else
                throw new Exception('Email scheduled but no mailing ID was returned from the server.');
        } else {
            throw new Exception("SendEmail Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /* Private Functions */

    private function _login($username, $password) {
        $data["Envelope"] = array(
            "Body" => array(
                "Login" => array(
                    "USERNAME" => $username,
                    "PASSWORD" => $password,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            $this->_jsessionid = $result['SESSIONID'];
            $this->_session_encoding = $result['SESSION_ENCODING'];
            $this->_username = $username;
            $this->_password = $password;
        } else {
            throw new Exception("Login Error: ".$this->_getErrorFromResponse($response));
        }
    }

    private function _getFullUrl() {
        return $this->_baseUrl . (isset($this->_session_encoding) ? $this->_session_encoding : '');
    }

    private function _request($data) {
        $atx = new ArrayToXML( $data, array(), array() );
        $fields = array(
            "jsessionid" => isset($this->_jsessionid) ? $this->_jsessionid : '',
            "xml" => $atx->getXML(),
        );
        $response = $this->_httpPost($fields);
        if ($response) {
            $arr = xml2array($response);
            if (isset($arr["Envelope"]["Body"]["RESULT"]["SUCCESS"])) {
                return $arr;
            } else {
                throw new Exception("HTTP Error: Invalid data from the server");
            }
        } else {
            throw new Exception("HTTP request failed");
        }
    }

    private function _httpPost($fields) {
        $fields_string = http_build_query($fields);
        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL,$this->_getFullUrl());
        curl_setopt($ch,CURLOPT_POST,count($fields));
        curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

        //execute post
        $result = curl_exec($ch);

        //close connection
        curl_close($ch);

        return $result;
    }

    private function _getErrorFromResponse($response) {
        if (isset($response['Envelope']['Body']['Fault']['FaultString']) && !empty($response['Envelope']['Body']['Fault']['FaultString'])) {
            return $response['Envelope']['Body']['Fault']['FaultString'];
        }
        d($response['Envelope']);
        return 'Unknown Server Error';
    }

    private function _isSuccess($result) {
        if (isset($result['SUCCESS']) && (strtolower($result["SUCCESS"]) === "true")) {
            return true;
        }
        return false;
    }
}

// For debugging
function d($obj) {
    if (false) {
        ini_set("xdebug.var_display_max_data", 10000);
        ini_set("xdebug.var_display_max_depth", 10);
        ini_set("xdebug.var_display_max_children", 1000);
        var_dump($obj);
    }
}

