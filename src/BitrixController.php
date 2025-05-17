<?php

require_once __DIR__ . '/../crest/crest.php';

class BitrixController
{
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

    public function createContact(string $name, string $phone): ?int
    {
        // Check if the contact already exists
        $existingContact = CRest::call('crm.contact.list', [
            'filter' => ['PHONE' => $phone, 'NAME' => $name],
            'select' => ['ID']
        ]);
        if (isset($existingContact['result'][0]['ID'])) {
            return $existingContact['result'][0]['ID'];
        }

        $fields = [
            'NAME' => $name,
            'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']]
        ];

        $response = CRest::call('crm.contact.add', [
            'fields' => $fields,
            'params' => ['REGISTER_SONET_EVENT' => 'Y']
        ]);

        if (isset($response['result'])) {
            return $response['result'];
        } else {
            return null;
        }
    }

    public function getLeadById(int $leadId, ?int $entityTypeId = null): ?array
    {
        $url = $entityTypeId ? 'crm.item.get' : 'crm.lead.get';
        $params = $entityTypeId ? ['id' => $leadId, 'entityTypeId' => $entityTypeId] : ['ID' => $leadId];

        $response = CRest::call($url, $params);

        if (isset($response['result']['item'])) {
            return $response['result']['item'];
        } else if (isset($response['result'])) {
            return $response['result'];
        } else {
            return null;
        }
    }

    public function createLead(string $name, string $phone, int $contactId, ?bool $cft = false, ?int $entityTypeId = null, ?string $projectName = null): ?int
    {
        if ($cft && !$entityTypeId) {
            throw new InvalidArgumentException('Entity Type ID is required when creating a SPA item.');
        }

        $fields = $cft ? [
            'title' => $name,
            'ufCrm3_1744794263' => $name,
            'ufCrm3_1747476512' => $phone,
            'ufCrm3_1744794218' => $projectName,
            'ufCrm3_1743830518902' => CONFIG['WHATSAPP_CONNECTING_MODE_ID'],
            'contactId' => $contactId,
        ] : [
            'TITLE' => $name,
            'NAME' => $name,
            'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
            'CONTACT_ID' => $contactId,
        ];

        if ($projectName) {
            if ($cft) {
                $fields['title'] .= " - $projectName";
            } else {
                $fields['TITLE'] .= " - $projectName";
            }
        }

        $url = $cft ? 'crm.item.add' : 'crm.lead.add';
        $params = ['entityTypeId' => $entityTypeId, 'fields' => $fields];

        $response = CRest::call($url, $params);

        if (isset($response['result']['item']['id'])) {
            return $response['result']['item']['id'];
        } else if (isset($response['result'])) {
            return $response['result'];
        } else {
            error_log("Bitrix createLead error: " . print_r($response, true));
            return null;
        }
    }
}
