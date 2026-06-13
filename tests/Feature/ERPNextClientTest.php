<?php

namespace \ERPNext\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use YehiaTarek\ERPNext\Auth\TokenAuth;
use YehiaTarek\ERPNext\ERPNextClient;
use YehiaTarek\ERPNext\Exceptions\DocumentNotFoundException;
use YehiaTarek\ERPNext\Exceptions\ValidationException;
use YehiaTarek\ERPNext\Tests\TestCase;

class ERPNextClientTest extends TestCase
{
    private function makeClient(array $responses): ERPNextClient
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $http    = new Client(['handler' => $handler]);

        $auth   = new TokenAuth('test_key', 'test_secret');
        $client = new ERPNextClient('https://demo.erpnext.com', $auth, []);

        // Inject the mock HTTP client via reflection
        $ref = new \ReflectionProperty(ERPNextClient::class, 'http');
        $ref->setAccessible(true);
        $ref->setValue($client, $http);

        return $client;
    }

    public function test_get_document_returns_data(): void
    {
        $payload = ['data' => ['name' => 'SINV-00001', 'status' => 'Paid']];

        $client = $this->makeClient([
            new Response(200, [], json_encode($payload)),
        ]);

        $result = $client->getDocument('Sales Invoice', 'SINV-00001');

        $this->assertSame('SINV-00001', $result['name']);
        $this->assertSame('Paid', $result['status']);
    }

    public function test_create_document_returns_new_record(): void
    {
        $created = [
            'data' => [
                'name'     => 'SINV-00002',
                'doctype'  => 'Sales Invoice',
                'customer' => 'CUST-001',
            ],
        ];

        $client = $this->makeClient([
            new Response(200, [], json_encode($created)),
        ]);

        $result = $client->createDocument('Sales Invoice', ['customer' => 'CUST-001']);

        $this->assertSame('SINV-00002', $result['name']);
    }

    public function test_delete_document_returns_true_on_success(): void
    {
        $client = $this->makeClient([
            new Response(202, [], json_encode(['message' => 'ok'])),
        ]);

        $this->assertTrue($client->deleteDocument('Sales Invoice', 'SINV-00001'));
    }

    public function test_404_throws_document_not_found_exception(): void
    {
        $this->expectException(DocumentNotFoundException::class);

        $body = ['exc_type' => 'DoesNotExistError', 'message' => 'Not found'];

        $client = $this->makeClient([
            new Response(404, [], json_encode($body)),
        ]);

        $client->getDocument('Sales Invoice', 'SINV-GHOST');
    }

    public function test_call_method_returns_message(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['message' => 'admin@example.com'])),
        ]);

        $result = $client->callGet('frappe.auth.get_logged_user');

        $this->assertSame('admin@example.com', $result);
    }

    public function test_list_documents_returns_data_array(): void
    {
        $payload = [
            'data' => [
                ['name' => 'CUST-001'],
                ['name' => 'CUST-002'],
            ],
        ];

        $client = $this->makeClient([
            new Response(200, [], json_encode($payload)),
        ]);

        $result = $client->listDocuments('Customer');

        $this->assertCount(2, $result['data']);
    }

    public function test_authorization_header_is_set_correctly(): void
    {
        $requests = [];

        $mock    = new MockHandler([
            new Response(200, [], json_encode(['message' => 'admin'])),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(\GuzzleHttp\Middleware::history($requests));

        $http   = new Client(['handler' => $handler]);
        $auth   = new TokenAuth('my_api_key', 'my_api_secret');
        $client = new ERPNextClient('https://demo.erpnext.com', $auth, []);

        $ref = new \ReflectionProperty(ERPNextClient::class, 'http');
        $ref->setAccessible(true);
        $ref->setValue($client, $http);

        $client->callGet('frappe.auth.get_logged_user');

        $sentHeader = (string) ($requests[0]['request']->getHeader('Authorization')[0] ?? '');
        $this->assertSame('token my_api_key:my_api_secret', $sentHeader);
    }
}
