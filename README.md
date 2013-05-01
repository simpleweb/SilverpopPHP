# Silverpop PHP Client Library

PHP client library for the Silverpop API

## Installation

Installation via [composer](http://getcomposer.org) . Create a composer.json file in the root folder of you project and paste the code below.

```javascript
{
    "require": {
        "simpleweb/silverpopphp": "master-dev"
    }
}
```

With composer installed, just run `php composer.phar install` or simply
`composer install` if you [did a global install](http://getcomposer.org/doc/00-intro.md#globally).

## Usage

```php
<?php

// Include the library
require_once 'vendor/autoload.php';

// Require the Silverpop Namespace
use Silverpop\EngagePod;

// Set some useful variables
$databaseID   = 'XXX';
$templateID   = 'XXX';
$contactsList = 'XXX';

// Initialize the library
$silverpop = new EngagePod(array(
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
