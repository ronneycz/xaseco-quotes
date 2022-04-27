<?php

/*
================================================================================
Description:    Allows to invoke random quotes using /quote command.
                Provides easy maintenance of quotes inside xml config file and
                possibility to add or modify quotes without restarting XASECO
                using /quote reload command - as MasterAdmin.

Author:         ronney
Version:        v1.0
Date:           2022-04-27

Dependencies:   none
================================================================================
*/

Aseco::registerEvent('onSync', 'q_onSync');
Aseco::addChatCommand('quote', 'Tells a random quote from this server');

global $q_index, $q_lastindex, $q_quote;


function q_onSync ($aseco)
{
    global $q_index, $q_lastindex, $q_quote;

    // without this, quote on index 0 (first in xml) can't be randomized on plugin start
    $q_index = -1;
    $q_lastindex = -1;

    // make sure quotes removed from xml get removed, ensure no duplicated quotes in memory
    $q_quote = array();

    // load and parse the config file
    if ($quote = $aseco->xml_parser->parseXml('quotes.xml', true, true))
    {
        // take each quote
        foreach ($quote['QUOTES']['ENTRY'] as $quote)
        {
            // used for logging
            ++$index;

            // check that parameters exist
            if (!isset($quote['QUOTE']) || !isset($quote['AUTHOR']))
            {
                if (!isset($quote['QUOTE']))
                {
                    $aseco->console('[plugin.quotes.php] Warning: parameter <quote> missing in "quotes.xml" - entry #' . $index);
                }
                if (!isset($quote['AUTHOR']))
                {
                    $aseco->console('[plugin.quotes.php] Warning: parameter <author> missing in "quotes.xml" - entry #' . $index);
                }

                // don't add quotes with missing parameter(s)
                continue;
            }

            // convert from array to string
            $quote['QUOTE'] = join($quote['QUOTE']);
            $quote['AUTHOR'] = join($quote['AUTHOR']);

            // check that parameters are set
            if (empty($quote['QUOTE']) || empty($quote['AUTHOR']))
            {
                if (empty($quote['QUOTE']))
                {
                    $aseco->console('[plugin.quotes.php] Warning: parameter <quote> empty in "quotes.xml" - entry #' . $index);
                }
                if (empty($quote['AUTHOR']))
                {
                    $aseco->console('[plugin.quotes.php] Warning: parameter <author> empty in "quotes.xml" - entry #' . $index);
                }

                // don't add quotes with empty parameter(s)
                continue;
            }

            // make each quote accessible on an index
            $q_quote[] = $quote;
        }

        // warn about too few valid quotes
        if (count($q_quote) < 2)
        {
            $aseco->console('[plugin.quotes.php] Warning: too few valid quotes in "quotes.xml" - 2 required!');
        }
    }
    else
    {
        trigger_error('[plugin.quotes.php] Could not read/parse config file "quotes.xml"!', E_USER_ERROR);
    }
}

function chat_quote ($aseco, $command)
{
    global $q_index, $q_lastindex, $q_quote;

    // allow masteradmin to reload quotes on the fly :)
	if ($aseco->isMasterAdmin($command['author']) && strtoupper($command['params']) == 'RELOAD')
    {
        // fake the event to read the config file
        q_onSync($aseco);
        $aseco->console('[plugin.quotes.php] MasterAdmin ' . $command['author']->login . ' reloads the configuration.');

        // output the message
        $message = '{#admin}> Reload of the configuration "quotes.xml" done.';
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
    }
    else
    {
        // 2+ quotes are required, else there's nothing to randomize
        if (count($q_quote) < 2)
        {
            $message = '{#admin}> Too few valid quotes in "quotes.xml" - 2 required!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
            return;
        }

        // randomize quote and don't repeat the previous quote
        do
        {
            $q_index = mt_rand(0, count($q_quote)-1);
        }
        while ($q_index == $q_lastindex);

        // output the message
        $message = '$fff[$ff0QUOTE$fff]$ff0 ' . $q_quote[$q_index]['QUOTE'] . ' $fff~' . $q_quote[$q_index]['AUTHOR'];
        $aseco->client->query('ChatSendServerMessage', $message);

        // save index for comparison, we don't want the same quote twice in a row
        $q_lastindex = $q_index;
    }
}

?>
