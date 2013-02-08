<?php

require_once 'EngagePod/xmlLib.php';

class EngagePod4 {

    /**
     * Current version of the library
     *
     * Uses semantic versioning (http://semver.org/)
     *
     * @const string VERSION
     */
    const VERSION = '0.0.2';

    private $_baseUrl;
    private $_session_encoding;
    private $_jsessionid;
    private $_username;
    private $_password;

    /**
     * Constructor
     * 
     * Sets $this->_baseUrl based on the engage server specified in config
     */
    public function __construct($config) {

        // It would be a good thing to cache the jsessionid somewhere and reuse it across multiple requests
        // otherwise we are authenticating to the server once for every request
        $this->_baseUrl = 'http://api' . $config['engage_server'] . '.silverpop.com/XMLAPI';
        $this->_login($config['username'], $config['password']);

    }

    /**
     * Fetches the contents of a list
     * 
     * $listType can be one of:
     *
     * 0 - Databases
     * 1 - Queries
     * 2 - Both Databases and Queries
     * 5 - Test Lists
     * 6 - Seed Lists
     * 13 - Suppression Lists
     * 15 - Relational Tables
     * 18 - Contact Lists
     *
     */
    public function getLists($listType = 2, $isPrivate = true, $folder = null) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetLists" => array(
                    "VISIBILITY" => ($isPrivate ? '0' : '1'),
                    "FOLDER_ID" => $folder,
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

    /**
     * Add a contact to a list
     */
    public function addContact($databaseID, $updateIfFound, $columns) {
        $data["Envelope"] = array(
            "Body" => array(
                "AddRecipient" => array(
                    "LIST_ID" => $databaseID,
                    "CREATED_FROM" => 1,        // 1 = created manually, 2 = opted in
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
                throw new Exception('Recipient added but no recipient ID was returned from the server.');
            }
        } else {
            throw new Exception("AddRecipient Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Create a new query
     *
     * Takes a list of criteria and creates a query from them
     *
     * @param string $queryName The name of the new query
     * @param int $parentListId List that this query is derived from
     * @param string $columnName Column that the expression will run against
     * @param string $operators Operator that will be used for the expression
     * @param string $values
     * @param bool $isPrivate
     * @return int ListID of the query that was created
     */
    public function createQuery($queryName, $parentListId, $columnName, $operators, $values, $isPrivate = true) {
        $data['Envelope'] = array(
            'Body' => array(
                'CreateQuery' => array(
                    'QUERY_NAME' => $queryName,
                    'PARENT_LIST_ID' => $parentListId,
                    'VISIBILITY' => ($isPrivate ? '0' : '1'),
                    'CRITERIA' => array(
                      'TYPE' => 'editable',
                      'EXPRESSION' => array(
                          'TYPE' => 'TE',
                          'COLUMN_NAME' => $columnName,
                          'OPERATORS' => $operators,
                          'VALUES' => $values,
                      ),
                    ),
                ),
            ),
        );

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            if (isset($result['ListId']))
                return $result['ListId'];
            else {
                throw new Exception('Query created but no query ID was returned from the server.');
            }
        } else {
            throw new Exception("createQuery Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Send email
     *
     * Sends an email to the specified list_id ($targetID) using the template
     * $templateID. You can optionally include substitutions that will act on
     * the template to fill in dynamic bits of data.
     *
     * ## Example
     *
     *     $engage->sendEmail(123, 456, "Example Mailing with unique name", time() + 60, array(
     *         'SUBSTITUTIONS' => array(
     *             array(
     *                 'NAME' => 'FIELD_IN_TEMPLATE',
     *                 'VALUE' => "Dynamic value to replace in template",
     *             ),
     *         )
     *     ));
     *
     * @param int $templateID ID of template upon which to base the mailing.
     * @param int $targetID ID of database, query, or contact list to send the template-based mailing.
     * @param string $mailingName Name to assign to the generated mailing.
     * @param int $scheduledTimestamp When the mailing should be scheduled to send. This must be later than the current timestamp.
     * @param array $optionalElements An array of $key => $value, where $key can be one of SUBJECT, FROM_NAME, FROM_ADDRESS, REPLY_TO, SUBSTITUTIONS
     * @param bool $saveToSharedFolder
     * @return int $mailingID
     */
    public function sendEmail($templateID, $targetID, $mailingName, $scheduledTimestamp, $optionalElements = array(), $saveToSharedFolder = 0, $suppressionLists = array()) {
        $data["Envelope"] = array(
            "Body" => array(
                "ScheduleMailing" => array(
                    "SEND_HTML" => true,
                    "SEND_TEXT" => true,
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

        if (is_array($suppressionLists) && count($suppressionLists) > 0) {
            $data["Envelope"]["Body"]["ScheduleMailing"]['SUPPRESSION_LISTS']['SUPPRESSION_LIST_ID'] = $suppressionLists;
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

    /**
     * Import a table
     *
     * Requires a file to import and a mapping file to be in the 'upload' directory of the Engage FTP server
     *
     * Returns the data job id
     *
     */
    public function importTable($fileName, $mapFileName) {

        $data["Envelope"] = array(
            "Body" => array(
                "ImportTable" => array(
                    "MAP_FILE" => $mapFileName,
                    "SOURCE_FILE" => $fileName,
                ),
            ),
        );

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            if (isset($result['JOB_ID']))
                return $result['JOB_ID'];
            else {
                throw new Exception('Import table query created but no job ID was returned from the server.');
            }
        } else {
            throw new Exception("importTable Error: ".$this->_getErrorFromResponse($response));
        }

    }

    /**
     * Purge a table
     *
     * Clear the contents of a table, useful before importing new content
     *
     * Returns the data job id
     *
     */
    public function purgeTable($tableName, $isPrivate = true) {

        $data["Envelope"] = array(
            "Body" => array(
                "PurgeTable" => array(
                    "TABLE_NAME" => $tableName,
                    "TABLE_VISIBILITY" => ($isPrivate ? '0' : '1'),
                ),
            ),
        );

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            if (isset($result['JOB_ID']))
                return $result['JOB_ID'];
            else {
                throw new Exception('Purge table query created but no job ID was returned from the server.');
            }
        } else {
            throw new Exception("purgeTable Error: ".$this->_getErrorFromResponse($response));
        }

    }

    /**
     * Get a data job status
     *
     * Returns the status or throws an exception
     *
     */
    public function getJobStatus($jobId) {

        $data["Envelope"] = array(
            "Body" => array(
                "GetJobStatus" => array(
                    "JOB_ID" => $jobId
                ),
            ),
        );

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            if (isset($result['JOB_STATUS']))
                return $result['JOB_STATUS'];
            else {
                throw new Exception('Job status query was successful but no status was found.');
            }
        } else {
            throw new Exception("getJobStatus Error: ".$this->_getErrorFromResponse($response));
        }

    }

    /**
     * Private method: authenticate with Silverpop
     *
     */
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

    /**
     * Private method: generate the full request url
     *
     */
    private function _getFullUrl() {
        return $this->_baseUrl . (isset($this->_session_encoding) ? $this->_session_encoding : '');
    }

    /**
     * Private method: make the request
     *
     */
    private function _request($data, $replace = array(), $attribs = array()) {

        $atx = new ArrayToXML($data, $replace, $attribs);

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

    /**
     * Private method: post the request to the url
     *
     */
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

    /**
     * Private method: parse an error response from Silverpop
     *
     */
    private function _getErrorFromResponse($response) {
        if (isset($response['Envelope']['Body']['Fault']['FaultString']) && !empty($response['Envelope']['Body']['Fault']['FaultString'])) {
            return $response['Envelope']['Body']['Fault']['FaultString'];
        }
        return 'Unknown Server Error';
    }

    /**
     * Private method: determine whether a request was successful
     *
     */
    private function _isSuccess($result) {
        if (isset($result['SUCCESS']) && (strtolower($result["SUCCESS"]) === "true")) {
            return true;
        }
        return false;
    }

}
