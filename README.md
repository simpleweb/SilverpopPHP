# Silverpop PHP Client Library

PHP client library for the Silverpop API

## Usage

```php
<?php

// Include the library
require_once 'lib/EngagePod4.php';

// Set some useful variables
$databaseID   = 'XXX';
$templateID   = 'XXX';
$contactsList = 'XXX';

// Initialize the library
$silverpop = new EngagePod4(array(
  'username'       => 'XXX',
  'password'       => 'XXX',
  'engage_server'  => 4,
));

// Fetch all contact lists
$lists = $silverpop->GetLists(18);
var_dump($lists);

// Add a record to a contact
$recipientID = $silverpop->addContact(
  $databaseID,
  true,
  array(
    'name'  => 'christos',
    'email' => 'chris@simpleweb.co.uk',
  )
);
echo $recipientID;

// Create a new mailing and send in 1 minute
$mailingID = $silverpop->sendEmail(
  $templateID,
  $databaseID,
  'API Mailing Test - ' . date("d/m/Y H:i:s", time()),
  time() + 60,
);
echo $mailingID;
```
