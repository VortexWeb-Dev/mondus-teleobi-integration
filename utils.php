<?php

require_once __DIR__ . "/crest/crest.php";

// Gets the responsible person ID from the agent email
function getResponsiblePersonId(string $agentEmail): ?int
{
    $responsiblePersonId = null;

    $response = CRest::call('user.get', [
        'filter' => [
            'EMAIL' => $agentEmail
        ]
    ]);

    if (isset($response['result'][0]['ID'])) {
        $responsiblePersonId = $response['result'][0]['ID'];
    }

    return $responsiblePersonId;
}

// Extracts the project name from the user input data
function extractProjectName($input)
{
    $matches = [];
    preg_match('/interest in ([^!]+)!/i', $input, $matches);
    return trim($matches[1] ?? '');
}
