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

    public function __construct()
    {
        $this->logger = new LoggerController();
        $this->bitrix = new BitrixController();
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

        // Process new WhatsApp message received in Teleobi

        $this->sendResponse(200, [
            'message' => 'New WhatsApp message data processed successfully',
        ]);
    }

    // Handles newLead webhook event
    public function handleNewLead(array $data): void
    {
        $this->logger->logWebhook('new_lead', $data);

        // Process new lead created in Bitrix

        $this->sendResponse(200, [
            'message' => 'New Bitrix lead data processed successfully',
        ]);
    }
}
