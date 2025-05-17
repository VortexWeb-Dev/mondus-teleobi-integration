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
        $this->logger->logWebhook('new_message', $data);

        $name = $data['first_name'] ?? 'Unknown';
        $phone = $data['chat_id'] ?? null;

        $projectName = null;
        if (!empty($data['user_input_data']) && isset($data['user_input_data'][0]['question'])) {
            $projectName = extractProjectName($data['user_input_data'][0]['question']);
        }

        $contactId = $this->bitrix->createContact($name, $phone);
        $leadId = $this->bitrix->createLead($name, $phone, $contactId, true, CONFIG['CFT_LEADS_ENTITY_TYPE_ID'], $projectName);

        if (empty($leadId)) {
            $this->sendResponse(500, ['error' => 'Failed to create lead']);
            return;
        }

        

        $this->sendResponse(200, [
            'message' => 'New WhatsApp message data processed successfully and lead created successfully with ID: ' . $leadId,
        ]);
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

        $messageTemplate = <<<EOT
            Dear $customerName,

            We have received your lead and will be in touch with you soon.

            Regards,  
            Mondus Properties
            EOT;

        if ($this->teleobi->sendMessage($customerPhone, $messageTemplate)) {
            $this->sendResponse(200, [
                'message' => 'New Bitrix lead data processed successfully and WhatsApp message sent successfully',
            ]);
        } else {
            $this->sendResponse(500, [
                'error' => 'Failed to send WhatsApp message',
            ]);
        }
    }
}
