<?php
require_once __DIR__ . "/../crest/crest.php";
require_once __DIR__ . "/../utils.php";

define('CONFIG', require_once __DIR__ . '/../config.php');

class WebhookController
{
    private const ALLOWED_ROUTES = [
        'newMessage' => 'handleNewMessage',
        'newLead' => 'handleNewLead',
    ];

    private LoggerController $logger;
    private BitrixController $bitrix;
    private TeleobiController $teleobi;

    public function __construct()
    {
        $this->logger = new LoggerController();
        $this->bitrix = new BitrixController();
        $this->teleobi = new TeleobiController();
    }

    // Handles incoming webhooks
    public function handleRequest(string $route): void
    {
        try {
            $this->logger->logRequest($route);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendResponse(405, [
                    'error' => 'Method Not Allowed. Only POST is accepted.'
                ]);
            }

            if (!array_key_exists($route, self::ALLOWED_ROUTES)) {
                $this->sendResponse(404, [
                    'error' => 'Resource not found'
                ]);
            }

            $handlerMethod = self::ALLOWED_ROUTES[$route];

            $data = $this->parseRequestData();
            if ($data === null) {
                $this->sendResponse(400, [
                    'error' => 'Invalid JSON data'
                ]);
            }

            $this->$handlerMethod($data);
        } catch (Throwable $e) {
            $this->logger->logError('Error processing request', $e);
            $this->sendResponse(500, [
                'error' => 'Internal server error'
            ]);
        }
    }

    // Parses incoming JSON data
    private function parseRequestData(): ?array
    {
        $rawData = file_get_contents('php://input');
        $jsonData = json_decode($rawData, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            return $jsonData;
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        return null;
    }

    // Sends response back to the webhook
    private function sendResponse(int $statusCode, array $data): void
    {
        header("Content-Type: application/json");
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    // Handles newMessage webhook event
    public function handleNewMessage(array $data): void
    {
        try {
            $this->logger->logWebhook('new_message', $data);

            $name = $data['first_name'] ?? 'Unknown';
            $phone = $data['chat_id'] ?? null;

            if (empty($phone)) {
                $this->logger->logError('Missing phone number in webhook data', null, $data);
                $this->sendResponse(400, ['error' => 'Phone number is required']);
                return;
            }

            $projectName = null;
            if (!empty($data['user_input_data']) && isset($data['user_input_data'][0]['question'])) {
                $projectName = extractProjectName($data['user_input_data'][0]['question']);
            }

            // Step 1: Create contact
            $contactId = $this->bitrix->createContact($name, $phone);
            if (!$contactId) {
                $this->logger->logError('Failed to create contact', null, ['name' => $name, 'phone' => $phone]);
                $this->sendResponse(500, ['error' => 'Failed to create contact']);
                return;
            }

            // Step 2: Create lead (CFT)
            $leadId = $this->bitrix->createLead($name, $phone, $contactId, true, CONFIG['CFT_LEADS_ENTITY_TYPE_ID'], $projectName);
            if (!$leadId) {
                $this->logger->logError('Failed to create lead', null, [
                    'name' => $name,
                    'phone' => $phone,
                    'contactId' => $contactId,
                    'project' => $projectName
                ]);
                $this->sendResponse(500, ['error' => 'Failed to create lead']);
                return;
            }

            // Step 3: Send WhatsApp template message
            $messageSent = $this->teleobi->sendMessage($phone, CONFIG['TELEOBI_TEMPLATE_ID']);
            var_dump($messageSent);
            if (!$messageSent) {
                $this->logger->logError('Failed to send WhatsApp message', null, ['phone' => $phone]);
                $this->sendResponse(500, [
                    'error' => 'Lead created (ID: ' . $leadId . '), but failed to send WhatsApp message',
                ]);
                return;
            }

            // Success
            $this->sendResponse(200, [
                'message' => 'Processed successfully',
                'lead_id' => $leadId,
                'contact_id' => $contactId
            ]);
        } catch (\Throwable $e) {
            $this->logger->logError('Unhandled exception in handleNewMessage', null, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }


    // Handles newLead webhook event
    public function handleNewLead(array $data): void
    {
        $this->logger->logWebhook('new_lead', $data);

        $leadId = $data['data']['FIELDS']['ID'] ?? null;
        $entityTypeId = $data['data']['FIELDS']['ENTITY_TYPE_ID'] ?? null;
        if (empty($leadId)) {
            $this->sendResponse(400, ['error' => 'Invalid lead ID']);
            return;
        }

        $lead = $this->bitrix->getLeadById($leadId, $entityTypeId);
        if (empty($lead)) {
            $this->sendResponse(404, ['error' => 'Lead not found']);
            return;
        }

        $contactId = $lead['CONTACT_ID'] ?? $lead['contactId'] ?? null;
        if (empty($contactId)) {
            $this->sendResponse(404, ['error' => 'Contact not found']);
            return;
        }

        $contact = $this->bitrix->getContact($contactId);
        if (empty($contact)) {
            $this->sendResponse(404, ['error' => 'Contact not found']);
            return;
        }

        $customerName = $contact['NAME'] ?? 'Customer';
        $customerPhone = $contact['PHONE'][0]['VALUE'] ?? null;
        $customerEmail = $contact['EMAIL'][0]['VALUE'] ?? null;

        // Pending
    }
}
