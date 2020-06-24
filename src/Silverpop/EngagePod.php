<?php

namespace Silverpop;

use Silverpop\Util\ArrayToXml;

class EngagePod {

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
        $this->_baseUrl = 'https://api-campaign-us-' . $config['engage_server'] . '.goacoustic.com/XMLAPI';
        $this->_login($config['username'], $config['password']);

    }

    /**
     * Terminate the session with Silverpop.
     *
     * @return bool
     */
    public function logOut() {
      $data["Envelope"] = array(
        "Body" => array(
          "Logout" => ""
        ),
      );
      $response = $this->_request($data);
      $result = $response["Envelope"]["Body"]["RESULT"];
      return $this->_isSuccess($result);
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
            throw new \Exception("GetLists Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Get mailing templates
     *
     */
    public function getMailingTemplates($isPrivate = true) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetMailingTemplates" => array(
                    "VISIBILITY" => ($isPrivate ? '0' : '1'),
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['MAILING_TEMPLATE']))
                return $result['MAILING_TEMPLATE'];
            else {
                return array(); //?
            }
        } else {
            throw new \Exception("GetLists Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Calculate a query
     *
     */
    public function calculateQuery($databaseID) {
        $data["Envelope"] = array(
            "Body" => array(
                "CalculateQuery" => array(
                    "QUERY_ID" => $databaseID,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return $result["JOB_ID"];
        } else {
            throw new \Exception("Silverpop says: ".$response["Envelope"]["Body"]["Fault"]["FaultString"]);
        }
    }

    /**
     * Get scheduled mailings
     *
     */
    public function getScheduledMailings() {
        $data['Envelope'] = array(
            'Body' => array(
                'GetSentMailingsForOrg' => array(
                    'SCHEDULED' => null,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return $result;
        } else {
            throw new Exception("Silverpop says: ".$response["Envelope"]["Body"]["Fault"]["FaultString"]);
        }
    }

    /**
     * Get the meta information for a list
     *
     */
    public function getListMetaData($databaseID) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetListMetaData" => array(
                    "LIST_ID" => $databaseID,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return $result;
        } else {
            throw new \Exception("Silverpop says: ".$response["Envelope"]["Body"]["Fault"]["FaultString"]);
        }
    }

    /**
     * Remove a contact
     *
     */
    public function removeContact($databaseID, $email, $customer_id=false) {
        $data["Envelope"] = array(
            "Body" => array(
                "RemoveRecipient" => array(
                    "LIST_ID" => $databaseID,
                    "EMAIL" => $email,
                ),
            ),
        );
        /*
         * This should be optional because not every database will have a 'customer_id' key field.
         */
        if ( $customer_id !== FALSE ) {
            $data['Envelope']['Body']['RemoveRecipient']['COLUMN'][] = array("NAME"=>"customer_id", "VALUE"=>$customer_id);
        }

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return true;
        } else {
            if ($response["Envelope"]["Body"]["Fault"]["FaultString"]=="Error removing recipient from list. Recipient is not a member of this list."){
                return true;
            } else {
                throw new \Exception("Silverpop says: ".$response["Envelope"]["Body"]["Fault"]["FaultString"]);
            }
        }
    }

    /**
     * Add a contact to a list
     * https://kb.silverpop.com/kb/Engage/API/API_XML/XML_API_Developer_Guide/03_Contact_XML_Interfaces/02_Database_Management_Interfaces_-_Contact/01_Add_a_Contact
     */
    public function addContact($databaseID, $updateIfFound, $columns, $contactListID = false, $sendAutoReply = false, $allowHTML = false, $createdFrom = 1, $visitorKey = '', $syncFields = []) {
        $data["Envelope"] = array(
            "Body" => array(
                "AddRecipient" => array(
                    "LIST_ID" => $databaseID,
                    "CREATED_FROM" => $createdFrom,
                    "SEND_AUTOREPLY"  => ($sendAutoReply ? 'true' : 'false'),
                    "UPDATE_IF_FOUND" => ($updateIfFound ? 'true' : 'false'),
                    "ALLOW_HTML" => ($allowHTML ? 'true' : 'false'),
                    "VISITOR_KEY" => $visitorKey,
                    "CONTACT_LISTS" => ($contactListID) ? array("CONTACT_LIST_ID" => $contactListID) : '',
                    "COLUMN" => array(),
                ),
            ),
        );
        foreach ($columns as $name => $value) {
            $data["Envelope"]["Body"]["AddRecipient"]["COLUMN"][] = array("NAME" => $name, "VALUE" => $value);
        }
        foreach ($syncFields as $name => $value) {
            $data["Envelope"]["Body"]["AddRecipient"]["SYNC_FIELDS"]["SYNC_FIELD"][] = array("NAME" => $name, "VALUE" => $value);
        }
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['RecipientId']))
                return $result['RecipientId'];
            else {
                throw new \Exception('Recipient added but no recipient ID was returned from the server.');
            }
        } else {
            throw new \Exception("AddRecipient Error: ".$this->_getErrorFromResponse($response));
        }
    }

    public function addContactToContactList($contactId, $contactListId, $columns) {
        $data["Envelope"] = array(
            "Body" => array(
                "AddContactToContactList" => array(
                    "CONTACT_ID" => $contactId,
                    "CONTACT_LIST_ID" => $contactListId,
                ),
            ),
        );
        foreach ($columns as $name => $value) {
            $data["Envelope"]["Body"]["AddContactToContactList"]["COLUMN"][] = array("NAME" => $name, "VALUE" => $value);
        }
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return true;
        } else {
            throw new \Exception("AddRecipient Error: ".$this->_getErrorFromResponse($response));
        }
    }

    public function getContact($databaseID, $email = null, $recipientId = null, $encodedRecipientId = null , $returnContactLists = false, $columns = null)
    {

      if ( empty( $email ) && empty( $recipientId ) ) {
        throw new \Exception('One of Email address or Recipient ID must have a value.');
      }

      $data["Envelope"] = array(
        "Body" => array(
          "SelectRecipientData" => array(
            "LIST_ID" => $databaseID,
            "EMAIL"   => empty($recipientId) ? $email : null,
            "RECIPIENT_ID" => !empty($recipientId) ? $recipientId : null,
            "ENCODED_RECIPIENT_ID" => !empty($encodedRecipientId) ? $encodedRecipientId : null,
            "RETURN_CONTACT_LISTS" => (bool) $returnContactLists,
          ),
        ),
      );

      if ( !empty($columns) && is_array($columns) ) {
        $column_data = array();
        foreach ($columns as $key => $value ) {
          $column_data[] = array(
            "NAME" => $key,
            "VALUE" => $columns[$key],
          );
        }
        $data["Envelope"]["Body"]["SelectRecipientData"]["COLUMN"] = $column_data;
      }

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['RecipientId']))
                return $result;
            else {
                throw new \Exception('Recipient added but no recipient ID was returned from the server.');
            }
        } else {
            throw new \Exception("AddRecipient Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Double opt in a contact
     *
     * @param  string $databaseID
     * @param  string $email
     *
     * @throws \Exception
     * @throw  Exception in case of error
     * @return int recipient ID
     */
    public function doubleOptInContact($databaseID, $email) {
        $data["Envelope"] = array(
            "Body" => array(
                "DoubleOptInRecipient" => array(
                    "LIST_ID"         => $databaseID,
                    "COLUMN"          => array(
                        array(
                            'NAME'  => 'EMAIL',
                            'VALUE' => $email,
                        ),
                    ),
                ),
            ),
        );

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['RecipientId']))
                return $result['RecipientId'];
            else {
                throw new \Exception('Recipient added but no recipient ID was returned from the server.');
            }
        }

        throw new \Exception("DoubleOptInRecipient Error: ".$this->_getErrorFromResponse($response));
    }

    /**
     * Update a contact.
     *
     * @param int    $databaseID
     * @param string $oldEmail
     * @param array  $columns
     *
     * @throws \Exception
     * @return int recipient ID
     */
    public function updateContact($databaseID, $oldEmail, $columns, $visitorKey = '', $syncFields = []) {
        $data["Envelope"] = array(
            "Body" => array(
                "UpdateRecipient" => array(
                    "LIST_ID"         => $databaseID,
                    "OLD_EMAIL"       => $oldEmail,
                    "CREATED_FROM"    => 1,        // 1 = created manually
                    "VISITOR_KEY"     => $visitorKey,
                    "COLUMN" => array(),
                ),
            ),
        );
        foreach ($columns as $name => $value) {
            $data["Envelope"]["Body"]["UpdateRecipient"]["COLUMN"][] = array("NAME" => $name, "VALUE" => $value);
        }
        foreach ($syncFields as $name => $value) {
            $data["Envelope"]["Body"]["UpdateRecipient"]["SYNC_FIELDS"]["SYNC_FIELD"][] = array("NAME" => $name, "VALUE" => $value);
        }
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['RecipientId']))
                return $result['RecipientId'];
            else {
                throw new \Exception('Recipient added but no recipient ID was returned from the server.');
            }
        }

        throw new \Exception("UpdateRecipient Error: ".$this->_getErrorFromResponse($response));
    }

    /**
     * Opt out a contact
     *
     * @param int    $databaseID
     * @param string $email
     * @param array  $columns
     *
     * @throws \Exception
     * @return boolean true on success
     */
    public function optOutContact($databaseID, $email, $columns = array()) {
        $data["Envelope"] = array(
            "Body" => array(
                "OptOutRecipient" => array(
                    "LIST_ID"         => $databaseID,
                    "EMAIL"           => $email,
                    "COLUMN" => array(),
                ),
            ),
        );
        $columns['EMAIL'] = $email;
        foreach ($columns as $name => $value) {
            $data["Envelope"]["Body"]["OptOutRecipient"]["COLUMN"][] = array("NAME" => $name, "VALUE" => $value);
        }

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            return true;
        }

        throw new \Exception("OptOutRecipient Error: ".$this->_getErrorFromResponse($response));
    }

    /**
     * Create a new query
     *
     * Takes a list of criteria and creates a query from them
     *
     * @param string $queryName The name of the new query
     * @param int    $parentListId List that this query is derived from
     * @param        $parentFolderId
     * @param        $condition
     * @param bool   $isPrivate
     *
     * @throws \Exception
     * @internal param string $columnName Column that the expression will run against
     * @internal param string $operators Operator that will be used for the expression
     * @internal param string $values
     * @return int ListID of the query that was created
     */
    public function createQuery($queryName, $parentListId, $parentFolderId, $condition, $isPrivate = true) {
        $data['Envelope'] = array(
            'Body' => array(
                'CreateQuery' => array(
                    'QUERY_NAME' => $queryName,
                    'PARENT_LIST_ID' => $parentListId,
                    'PARENT_FOLDER_ID' => $parentFolderId,
                    'VISIBILITY' => ($isPrivate ? '0' : '1'),
                    'CRITERIA' => array(
                        'TYPE' => 'editable',
                        'EXPRESSION' => $condition,
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
                throw new \Exception('Query created but no query ID was returned from the server.');
            }
        } else {
            throw new \Exception("createQuery Error: ".$this->_getErrorFromResponse($response));
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
     * @param int      $templateID ID of template upon which to base the mailing.
     * @param int      $targetID ID of database, query, or contact list to send the template-based mailing.
     * @param string   $mailingName Name to assign to the generated mailing.
     * @param int      $scheduledTimestamp When the mailing should be scheduled to send. This must be later than the current timestamp.
     * @param array    $optionalElements An array of $key => $value, where $key can be one of SUBJECT, FROM_NAME, FROM_ADDRESS, REPLY_TO, SUBSTITUTIONS
     * @param bool|int $saveToSharedFolder
     * @param array    $suppressionLists
     *
     * @throws \Exception
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
                throw new \Exception('Email scheduled but no mailing ID was returned from the server.');
        } else {
            throw new \Exception("SendEmail Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Send a single transactional email
     *
     * Sends an email to the specified email address ($emailID) using the mailingId
     * of the autoresponder $mailingID. You can optionally include database keys
     * to match if multikey database is used (not for replacement).
     *
     * ## Example
     *
     *     $engage->sendMailing("someone@somedomain.com", 149482, array("COLUMNS" => array(
     *         'COLUMN' => array(
     *             array(
     *                 'Name' => 'FIELD_IN_TEMPLATE',
     *                 'Value' => "value to MATCH",
     *             ),
     *         )
     *     )));
     *
     * @param string $emailID ID of users email, must be opted in.
     * @param int    $mailingID ID of template upon which to base the mailing.
     * @param array  $optionalKeys additional keys to match reciepent
     *
     * @throws \Exception
     * @return int $mailingID
     */
    public function sendMailing($emailID, $mailingID, $optionalKeys = array()) {
        $data["Envelope"] = array(
            "Body" => array(
                "SendMailing" => array(
                    "MailingId"         => $mailingID,
                    "RecipientEmail"    => $emailID,
                ),
            ),
        );
        foreach ($optionalKeys as $key => $value) {
            $data["Envelope"]["Body"]["SendMailing"][$key] = $value;
        }

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            return true;
        } else {
            throw new \Exception("SendEmail Error: ".$this->_getErrorFromResponse($response));
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
                throw new \Exception('Import table query created but no job ID was returned from the server.');
            }
        } else {
            throw new \Exception("importTable Error: ".$this->_getErrorFromResponse($response));
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
                throw new \Exception('Purge table query created but no job ID was returned from the server.');
            }
        } else {
            throw new \Exception("purgeTable Error: ".$this->_getErrorFromResponse($response));
        }

    }

    /**
	 * This interface inserts or updates relational data
	 *
	 * For each Row that is passed in:
	 * - If a row is found having the same key as the passed in row, update the record.
	 * - If no matching row is found, insert a new row setting the column values to those passed in the request.
	 *
	 * Only one hundred rows may be passed in a single insertUpdateRelationalTable call!
	 */
    public function insertUpdateRelationalTable($tableId, $rows) {
	    $processedRows = array();
        $attribs = array();
	    foreach($rows as $row) {
		    $columns = array();
		    foreach($row as $name => $value)
		    {
			    $columns['COLUMN'][] = $value;
			    $attribs[5]['COLUMN'][] = array('name' => $name);
		    }

		    $processedRows['ROW'][] = $columns;
	    }

	    $data["Envelope"] = array(
            "Body" => array(
                "InsertUpdateRelationalTable" => array(
                    "TABLE_ID" => $tableId,
                    "ROWS" => $processedRows,
                ),
            ),
        );

        $response = $this->_request($data, array(), $attribs);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            return true;
        } else {
            throw new \Exception("insertUpdateRelationalTable Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
	 * This interface deletes records from a relational table.
	 */
    public function deleteRelationalTableData($tableId, $rows) {
	    $processedRows = array();
        $attribs = array();
	    foreach($rows as $row) {
		    $columns = array();
		    foreach($row as $name => $value)
		    {
			    $columns['KEY_COLUMN'][] = $value;
			    $attribs[5]['KEY_COLUMN'][] = array('name' => $name);
		    }

		    $processedRows['ROW'][] = $columns;
	    }

	    $data["Envelope"] = array(
            "Body" => array(
                "DeleteRelationalTableData" => array(
                    "TABLE_ID" => $tableId,
                    "ROWS" => $processedRows,
                ),
            ),
        );

        $response = $this->_request($data, array(), $attribs);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            return true;
        } else {
            throw new \Exception("deleteRelationalTableData Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Import a list/database
     *
     * Requires a file to import and a mapping file to be in the 'upload' directory of the Engage FTP server
     *
     * Returns the data job id
     *
     */
    public function importList($fileName, $mapFileName) {

        $data["Envelope"] = array(
            "Body" => array(
                "ImportList" => array(
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
                throw new \Exception('Import list query created but no job ID was returned from the server.');
            }
        } else {
            throw new \Exception("importList Error: ".$this->_getErrorFromResponse($response));
        }

    }

    /**
     * Exports contact data from a database, query, or contact list. Engage exports the results to a CSV
     * file, then adds that file to the FTP account associated with the current session.
     *
     * @param string $listId Unique identifier for the database, query, or contact list Engage is exporting.
     * @param string $exportType Specifies which contacts to export.
     * @param string $exportFormat Specifies the format (file type) for the exported data.
     * @param array $exportColumns XML node used to request specific custom database columns to export for each contact. If EXPORT_COLUMNS is not specified, all database columns will be exported.
     * @param null|string $email If specified, this email address receives notification when the job is complete.
     * @param null|string $fileEncoding Defines the encoding of the exported file.
     * @param bool $addToStoredFiles Use the ADD_TO_STORED_FILES parameter to write the output to the Stored Files folder within Engage.
     * @param null|string $dateStart Specifies the beginning boundary of information to export (relative to the last modified date). If time is included, it must be in 24-hour format.
     * @param null|string $dateEnd Specifies the ending boundary of information to export (relative to the last modified date). If time is included, it must be in 24-hour format.
     * @param bool $useCreatedDate If included, the DATE_START and DATE_END range will be relative to the contact create date rather than last modified date.
     * @param bool $includeLeadSource Specifies whether to include the Lead Source column in the resulting file.
     * @param null|string $listDateFormat Used to specify the date format of the date fields in your exported file if date format differs from "mm/dd/yyyy" (month, day, and year can be in any order you choose).
     * @return mixed
     * @throws \Exception
     */
    public function exportList($listId, $exportType, $exportFormat, $exportColumns = array(), $email = null, $fileEncoding = null, $addToStoredFiles = false, $dateStart = null, $dateEnd = null, $useCreatedDate = false, $includeLeadSource = false, $listDateFormat = null)
    {
        $data["Envelope"] = array(
            "Body" => array(
                "ExportList" => array(
                    "LIST_ID" => $listId,
                    "EXPORT_TYPE" => $exportType,
                    "EXPORT_FORMAT" => $exportFormat
                )
            )
        );

        if ($exportColumns) {
            foreach ($exportColumns as $column) {
                $data["Envelope"]["Body"]["ExportList"]["EXPORT_COLUMNS"]["COLUMN"][] = $column;
            }
        }

        if ($email) {
            $data["Envelope"]["Body"]["ExportList"]["EMAIL"] = $email;
        }

        if ($fileEncoding) {
            $data["Envelope"]["Body"]["ExportList"]["FILE_ENCODING"] = $fileEncoding;
        }

        if ($addToStoredFiles) {
            $data["Envelope"]["Body"]["ExportList"]["ADD_TO_STORED_FILES"] = "";
        }

        if ($dateStart) {
            $data["Envelope"]["Body"]["ExportList"]["DATE_START"] = $dateStart;
        }

        if ($dateEnd) {
            $data["Envelope"]["Body"]["ExportList"]["DATE_END"] = $dateEnd;
        }

        if ($useCreatedDate) {
            $data["Envelope"]["Body"]["ExportList"]["USE_CREATED_DATE"] = "";
        }

        if ($includeLeadSource) {
            $data["Envelope"]["Body"]["ExportList"]["INCLUDE_LEAD_SOURCE"] = "";
        }

        if ($listDateFormat) {
            $data["Envelope"]["Body"]["ExportList"]["LIST_DATE_FORMAT"] = $listDateFormat;
        }

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            if (isset($result['JOB_ID']))
                return array("JOB_ID" => $result['JOB_ID'], "FILE_PATH" => $result['FILE_PATH']);
            else {
                throw new \Exception('Export list created but no job ID was returned from the server.');
            }
        } else {
            throw new \Exception("exportList Error: ".$this->_getErrorFromResponse($response));
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
                return $result;
            else {
                throw new Exception('Job status query was successful but no status was found.');
            }
        } else {
            throw new \Exception("getJobStatus Error: ".$this->_getErrorFromResponse($response));
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
            throw new \Exception("Login Error: ".$this->_getErrorFromResponse($response));
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

        if (is_array($data))
        {
            $atx = new ArrayToXml($data, $replace, $attribs);;
            $xml = $atx->getXML();
        }
        else
        {
            //assume raw xml otherwise, we need this because we have to build
            //  our own sometimes because assoc arrays don't support same name keys
            $xml = $data;
        }

        $fields = array(
            "jsessionid" => isset($this->_jsessionid) ? $this->_jsessionid : '',
            "xml" => $xml,
        );
        $response = $this->_httpPost($fields);
        if ($response) {
            $arr =  \Silverpop\Util\xml2array($response);
            if (isset($arr["Envelope"]["Body"]["RESULT"]["SUCCESS"])) {
                return $arr;
            } else {
                throw new \Exception("HTTP Error: Invalid data from the server");
            }
        } else {
            throw new \Exception("HTTP request failed");
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
        //set headers in array
        $headers = array(
         'Expect:',
         'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        );
        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
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
        if (isset($result['SUCCESS']) && in_array(strtolower($result["SUCCESS"]), array('true', 'success'))) {
            return true;
        }
        return false;
    }

}
