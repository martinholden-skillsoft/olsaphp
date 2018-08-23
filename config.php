<?php
unset($CFG);
$CFG = new stdclass();

//Define the OLSA Web Service Settings
$CFG->endpoint    = 'https://{customer}.skillwsa.com/olsa/services/Olsa';
$CFG->customerid    = '{customerid}';
$CFG->sharedsecret    = '{sharedsecret}';


//Define the version of Skillport, this controls whether Skillport8 Extended login
//functions are used allowing optional setting of Skillport 8 profile fields.
//Valid values 7 or 8
$CFG->skillportversion = 8;

//Define OLSA constants
//SO_GetMultiActionSignOnURLExtended - valid Action Types
$CFG->action_enum8 = array('catalog','learningplan','home','summary','launch');

?>