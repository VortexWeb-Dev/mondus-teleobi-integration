<?php

require_once __DIR__ . '/../crest/crest.php';

class BitrixController
{
    public function addLead(array $leadData): ?int
    {
        if (empty($leadData['TITLE'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Missing required lead field: TITLE']);
            exit;
        }

        $result = CRest::call('crm.lead.add', [
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

    public function getLeadByCallId(string $callId): ?array
    {
        if (empty($callId)) {
            return null;
        }

        $result = CRest::call('crm.lead.list', [
            'filter' => [
                'UF_CRM_1743492578846' => $callId
            ],
            'order' => [
                'ID' => 'DESC'
            ]
        ]);

        if (isset($result['result'][0])) {
            return $result['result'][0];
        } else {
            return null;
        }
    }

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
}
