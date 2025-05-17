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

// Sends a request to the specified URL with the given parameters
function sendRequest(string $url, array $params, string $method = 'POST', array $headers = []): ?string
{
    $ch = curl_init();

    $defaultHeaders = [
        'Accept: application/json',
    ];

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $defaultHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
    } else {
        $url .= '?' . http_build_query($params);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log('cURL error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    if ($httpCode >= 400) {
        error_log("HTTP error $httpCode from $url: $response");
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return $response;
}
