<?php

namespace YehiaTarek\ERPNext;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use YehiaTarek\ERPNext\Auth\PasswordAuth;
use YehiaTarek\ERPNext\Contracts\AuthDriver;
use YehiaTarek\ERPNext\Exceptions\AuthenticationException;
use YehiaTarek\ERPNext\Exceptions\DocumentNotFoundException;
use YehiaTarek\ERPNext\Exceptions\ERPNextException;
use YehiaTarek\ERPNext\Exceptions\ValidationException;
use YehiaTarek\ERPNext\Query\QueryBuilder;
use Psr\Http\Message\ResponseInterface;

class ERPNextClient
{
    private Client $http;

    public function __construct(
        private readonly string     $baseUrl,
        private readonly AuthDriver $auth,
        private readonly array      $config = [],
    ) {
        $this->http = $this->buildHttpClient();

        // Perform session login for password auth
        if ($auth instanceof PasswordAuth) {
            $auth->authenticate($this->http);
        }
    }

    // =========================================================================
    // Document (Resource) API
    // =========================================================================

    /**
     * List documents for a given DocType.
     * Returns the full response array (including 'data').
     * Prefer using query() for a richer interface.
     *
     * @return array
     */
    public function listDocuments(string $doctype, array $params = []): array
    {
        return $this->get("/api/resource/{$doctype}", ['query' => $params]);
    }

    /**
     * Get a single document.
     *
     * @throws DocumentNotFoundException
     */
    public function getDocument(string $doctype, string $name, bool $expandLinks = false): array
    {
        $params = [];
        if ($expandLinks) {
            $params['expand_links'] = 'True';
        }

        $response = $this->get(
            "/api/resource/{$doctype}/{$name}",
            $params ? ['query' => $params] : []
        );

        return $response['data'] ?? $response;
    }

    /**
     * Create a new document.
     */
    public function createDocument(string $doctype, array $data): array
    {
        $response = $this->post("/api/resource/{$doctype}", ['json' => $data]);
        return $response['data'] ?? $response;
    }

    /**
     * Update an existing document (partial update supported).
     */
    public function updateDocument(string $doctype, string $name, array $data): array
    {
        $response = $this->put("/api/resource/{$doctype}/{$name}", ['json' => $data]);
        return $response['data'] ?? $response;
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(string $doctype, string $name): bool
    {
        $response = $this->delete("/api/resource/{$doctype}/{$name}");
        return ($response['message'] ?? '') === 'ok';
    }

    // =========================================================================
    // Remote Method Calls
    // =========================================================================

    /**
     * Call a whitelisted Frappe/ERPNext Python method.
     *
     * @param  string  $method  Dotted path, e.g. 'frappe.auth.get_logged_user'
     * @param  array   $params  Query params (GET) or body params (POST)
     * @param  string  $verb    'GET' or 'POST'
     */
    public function call(string $method, array $params = [], string $verb = 'GET'): mixed
    {
        $endpoint = '/api/method/' . $method;

        $response = match (strtoupper($verb)) {
            'POST'  => $this->post($endpoint, ['json' => $params]),
            default => $this->get($endpoint, ['query' => $params]),
        };

        return $response['message'] ?? $response;
    }

    /**
     * Convenience: GET method call.
     */
    public function callGet(string $method, array $params = []): mixed
    {
        return $this->call($method, $params, 'GET');
    }

    /**
     * Convenience: POST method call.
     */
    public function callPost(string $method, array $params = []): mixed
    {
        return $this->call($method, $params, 'POST');
    }

    // =========================================================================
    // File Uploads
    // =========================================================================

    /**
     * Upload a file to ERPNext.
     *
     * @param  string       $filePath      Absolute path to the local file
     * @param  string|null  $doctype       Attach to this DocType (optional)
     * @param  string|null  $docname       Attach to this document name (optional)
     * @param  string|null  $fieldname     Which field to attach the file to (optional)
     * @param  bool         $isPrivate     Whether the file should be private
     * @return array                       The uploaded file document
     */
    public function uploadFile(
        string  $filePath,
        ?string $doctype   = null,
        ?string $docname   = null,
        ?string $fieldname = null,
        bool    $isPrivate = false,
    ): array {
        $multipart = [
            [
                'name'     => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
            [
                'name'     => 'is_private',
                'contents' => $isPrivate ? '1' : '0',
            ],
        ];

        if ($doctype) {
            $multipart[] = ['name' => 'doctype', 'contents' => $doctype];
        }
        if ($docname) {
            $multipart[] = ['name' => 'docname', 'contents' => $docname];
        }
        if ($fieldname) {
            $multipart[] = ['name' => 'fieldname', 'contents' => $fieldname];
        }

        $response = $this->request('POST', '/api/method/upload_file', [
            'multipart' => $multipart,
        ]);

        return $response['message'] ?? $response;
    }

    /**
     * Upload a file from raw binary content.
     */
    public function uploadFileContent(
        string  $content,
        string  $filename,
        ?string $doctype   = null,
        ?string $docname   = null,
        bool    $isPrivate = false,
    ): array {
        $multipart = [
            [
                'name'     => 'file',
                'contents' => $content,
                'filename' => $filename,
            ],
            [
                'name'     => 'is_private',
                'contents' => $isPrivate ? '1' : '0',
            ],
        ];

        if ($doctype) {
            $multipart[] = ['name' => 'doctype', 'contents' => $doctype];
        }
        if ($docname) {
            $multipart[] = ['name' => 'docname', 'contents' => $docname];
        }

        $response = $this->request('POST', '/api/method/upload_file', [
            'multipart' => $multipart,
        ]);

        return $response['message'] ?? $response;
    }

    // =========================================================================
    // Query Builder
    // =========================================================================

    /**
     * Start a fluent query for the given DocType.
     */
    public function query(string $doctype): QueryBuilder
    {
        return new QueryBuilder($this, $doctype, $this->config['defaults']['limit'] ?? 20);
    }

    // =========================================================================
    // Authentication helpers
    // =========================================================================

    /**
     * Return the currently logged-in user (useful for verifying credentials).
     */
    public function getLoggedUser(): string
    {
        return $this->callGet('frappe.auth.get_logged_user');
    }

    // =========================================================================
    // Low-level HTTP methods
    // =========================================================================

    public function get(string $endpoint, array $options = []): array
    {
        return $this->request('GET', $endpoint, $options);
    }

    public function post(string $endpoint, array $options = []): array
    {
        return $this->request('POST', $endpoint, $options);
    }

    public function put(string $endpoint, array $options = []): array
    {
        return $this->request('PUT', $endpoint, $options);
    }

    public function delete(string $endpoint, array $options = []): array
    {
        return $this->request('DELETE', $endpoint, $options);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    private function request(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $defaultHeaders = array_merge(
            ['Accept' => 'application/json'],
            $this->auth->getHeaders()
        );

        // For password auth, inject cookie jar
        if ($this->auth instanceof PasswordAuth) {
            $options['cookies'] = $this->auth->getCookieJar();
        }

        // Merge headers
        $options['headers'] = array_merge(
            $defaultHeaders,
            $options['headers'] ?? []
        );

        // Do not set Content-Type for multipart (Guzzle sets it automatically with boundary)
        if (! isset($options['multipart']) && ! isset($options['headers']['Content-Type'])) {
            $options['headers']['Content-Type'] = 'application/json';
        }

        try {
            $response = $this->http->request($method, $url, $options);
            return $this->parseResponse($response);
        } catch (ClientException $e) {
            return $this->handleClientException($e);
        } catch (ServerException $e) {
            throw new ERPNextException(
                'ERPNext server error: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if (empty($body)) {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ERPNextException('Failed to decode ERPNext response as JSON: ' . $body);
        }

        return $decoded;
    }

    private function handleClientException(ClientException $e): never
    {
        $statusCode = $e->getResponse()->getStatusCode();
        $body       = (string) $e->getResponse()->getBody();
        $decoded    = json_decode($body, true) ?? [];

        $excType = $decoded['exc_type'] ?? null;
        $message = $decoded['message']
            ?? $decoded['exception']
            ?? $e->getMessage();

        // Extract the human-readable part of exception string
        if (isset($decoded['exc'])) {
            $lines   = array_filter(explode("\n", $decoded['exc']));
            $message = end($lines) ?: $message;
        }

        throw match (true) {
            $statusCode === 404                               => new DocumentNotFoundException($message, 404, $e, $decoded),
            $statusCode === 401 || $statusCode === 403        => new AuthenticationException($message, $statusCode, $e, $decoded),
            $excType === 'ValidationError'                    => new ValidationException($message, 422, $e, $decoded),
            default                                           => new ERPNextException($message, $statusCode, $e, $decoded),
        };
    }

    private function buildHttpClient(): Client
    {
        return new Client([
            'timeout'         => $this->config['http']['timeout'] ?? 30,
            'connect_timeout' => $this->config['http']['connect_timeout'] ?? 10,
            'verify'          => $this->config['http']['verify'] ?? true,
        ]);
    }
}
