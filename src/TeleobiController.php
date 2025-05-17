<?php

require_once __DIR__ . '/../crest/crest.php';
require_once __DIR__ . '/../utils.php';

class TeleobiController
{
    public function sendMessage(string $customerPhone, int $templateId): bool
    {
        $url = 'https://dash.teleobi.com/api/v1/whatsapp/send/template';
        $params = [
            'apiToken' => CONFIG['TELEOBI_API_KEY'],
            'phone_number_id' => CONFIG['TELEOBI_PHONE_NUMBER_ID'],
            'phone_number' => $customerPhone,
            'template_id' => $templateId,
        ];

        $response = sendRequest($url, $params);
        if ($response === false) {
            return false;
        }
        $responseData = json_decode($response, true);
        if (isset($responseData['error'])) {
            return false;
        }
        if (isset($responseData['status']) && $responseData['status'] == 1) {
            return true;
        }
        if (isset($responseData['status']) && $responseData['status'] == 0) {
            return false;
        }
        return false;
    }
}
