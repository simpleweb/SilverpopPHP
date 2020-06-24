<?php

namespace Silverpop;

use Silverpop\Util\ArrayToXml;

class Transact {

    /**
     * Current version of the library
     *
     * Uses semantic versioning (http://semver.org/)
     *
     * @const string VERSION
     */
    const VERSION = '0.0.1';

    private $_baseUrl;

    /**
     * Constructor
     *
     * Sets $this->_baseUrl based on the engage server specified in config
     */
    public function __construct($engage_server) {
        $this->_baseUrl = 'https://transact' . $engage_server . '.goacoustic.com/XTMail';
    }

    /**
     * Submit transaction email
     *
     * Sends an email to the specified recipient ($recipient)
     *
     * ## Example
     *
     *     $engage->sendEmail(123, array(
     *         'EMAIL' => 'som@email.tld',
               'BODY_TYPE' => 'HTML',
               'PERSONALIZATION' => array(
	               array(
		               'TAG_NAME' => 'SomeParam',
		               'VALUE' => 'SomeValue'
	               ),
	               array(
		               'TAG_NAME' => 'SomeParam',
		               'VALUE' => 'SomeValue'
	               )
	            )
     *     ));
     *
     * @param int   $campaingID ID of capaing upon which to base the mailing.
     * @param array $recipient An array of $key => $value, where $key can be one of EMAIL, BODY_TYPE, PERSONALIZATION
     * @param int   $transactionID ID of transaction.
     * @param bool  $showAllSendDetail
     * @param bool  $sendAsBatch
     *
     * @throws \Exception
     * @return array RECIPIENT_DETAIL
     */
    public function submit($campaingID, $recipient, $transactionID = null, $showAllSendDetail = true, $sendAsBatch = false) {
        $data["XTMAILING"] = array(
	    	"CAMPAIGN_ID" => $campaingID,
	    	"SHOW_ALL_SEND_DETAIL" => ($showAllSendDetail ? "true" : "false"),
	    	"SEND_AS_BATCH" => ($sendAsBatch ? "true" : "false"),
	    	"NO_RETRY_ON_FAILURE" => "true",
	    	"RECIPIENT" => $recipient
        );
        
        if($transactionID !== null) {
	        $data["XTMAILING"]["TRANSACTION_ID"] = $transactionID;
        }

        $response = $this->_request($data);
        
        $result = $response["XTMAILING_RESPONSE"];
        
        if ($this->_isSuccess($result) && $result['EMAILS_SENT'] != 0) {
            return $result['RECIPIENT_DETAIL'];
        }
        
        throw new \Exception("Silverpop\\Transact::submit Error: ".$this->_getErrorFromResponse($result));
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

        $jsessionid = isset($this->_jsessionid) ? $this->_jsessionid : '';
        
        $response = $this->_httpPost($jsessionid, $xml);
        if ($response) {
            $arr =  \Silverpop\Util\xml2array($response);
            return $arr;
            if (isset($arr["XTMAILING_RESPONSE"])) {
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
    private function _httpPost($jsessionid, $xml) {
        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL,$this->_baseUrl);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_HTTPHEADER, array(
	        'Content-Type: text/xml;charset=UTF-8',
	        'Content-Length: '.strlen($xml)
        ));

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
    private function _getErrorFromResponse($result) {
	    if($result['ERROR_CODE'] != 0) {
			return $result['ERROR_STRING'];
		}
		
		if($result['NUMBER_ERRORS'] != 0) {
	    	return $result['RECIPIENT_DETAIL']['ERROR_STRING'];
	    }
	    
        return 'Unknown Server Error';
    }

    /**
     * Private method: determine whether a request was successful
     *
     */
    private function _isSuccess($result) {
	    if($result['ERROR_CODE'] == 0 && $result['NUMBER_ERRORS'] == 0) {
		    return true;
	    }
	    
        return false;
    }

}
