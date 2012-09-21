# Silverpop PHP Client Library

PHP client library for the Silverpop API

## Usage

```php
<?php

// Include the library
require_once 'library/EngagePod4.php';

$databaseID = "";
$mailingID = "";
$contactsList = "";

// Initialize the library
$pod = new EngagePod4(array(
  'username'       => 'XXX',
  'password'       => 'XXX',
  'engage_server'  => 4,
));

// Fetch all contact lists
$lists = $pod->GetLists(18);
var_dump($lists);

// Add a record to a contact
$recipientID = $pod->addContact($databaseID, true, array("name" => "christos", "email" => "chris@simpleweb.co.uk"));

// Create a new mailing
$mailingID = $pod->sendEmail($mailingID, $databaseID, "API Mailing Test - ".date("d/m/Y H:i:s",time()), time() + 60);
var_dump($mailingID);
```
