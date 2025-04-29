<?php

require_once __DIR__ . '/../crest/crest.php';

class BitrixController
{
    public function updateLead(array $leadData, int $leadId): ?int
    {
        if (empty($leadData['TITLE'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Missing required lead field: TITLE']);
            exit;
        }

        $result = CRest::call('crm.lead.update', [
            'id' => $leadId,
            'fields' => $leadData,
            'params' => ["REGISTER_SONET_EVENT" => "Y"]
        ]);

        if (isset($result['result'])) {
            return $result['result'];
        } else {
            return null;
        }
        exit;
    }

    public function getContact(int $contactId): ?array
    {
        $response = CRest::call('crm.contact.get', [
            'ID' => $contactId
        ]);

        if (isset($response['result'])) {
            return $response['result'];
        } else {
            return null;
        }
    }

    public function getLeadById(int $leadId): ?array
    {
        $response = CRest::call('crm.lead.get', [
            'ID' => $leadId
        ]);

        if (isset($response['result'])) {
            return $response['result'];
        } else {
            return null;
        }
    }
}
