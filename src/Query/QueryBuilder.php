<?php

namespace YehiaTarek\ERPNext\Query;

use YehiaTarek\ERPNext\ERPNextClient;

/**
 * Fluent query builder for GET /api/resource/:doctype
 *
 * Usage:
 *   ERPNext::query('Sales Invoice')
 *       ->fields(['name', 'customer', 'grand_total'])
 *       ->filter('status', '=', 'Paid')
 *       ->filter('grand_total', '>', 1000)
 *       ->orderBy('grand_total', 'desc')
 *       ->limit(10)
 *       ->get();
 */
class QueryBuilder
{
    private array  $fields      = [];
    private array  $filters     = [];
    private array  $orFilters   = [];
    private array  $expand      = [];
    private ?string $orderBy    = null;
    private int    $limitStart  = 0;
    private int    $limitLength;
    private bool   $asDict      = true;
    private bool   $debug       = false;
    private bool   $expandLinks = false;

    public function __construct(
        private readonly ERPNextClient $client,
        private readonly string        $doctype,
        int                            $defaultLimit = 20,
    ) {
        $this->limitLength = $defaultLimit;
    }

    // -------------------------------------------------------------------------
    // Builder methods
    // -------------------------------------------------------------------------

    /**
     * Specify the fields to retrieve.
     *
     * @param  string[]  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Add a filter condition (AND-joined).
     *
     * @param  string  $field
     * @param  string  $operator  e.g. '=', '!=', '>', '<', '>=', '<=', 'like', 'in', 'not in'
     * @param  mixed   $value
     */
    public function filter(string $field, string $operator, mixed $value): static
    {
        $this->filters[] = [$field, $operator, $value];
        return $this;
    }

    /**
     * Add multiple AND filters at once.
     *
     * @param  array<array{0: string, 1: string, 2: mixed}>  $filters
     */
    public function filters(array $filters): static
    {
        foreach ($filters as $f) {
            $this->filters[] = $f;
        }
        return $this;
    }

    /**
     * Add an OR filter condition.
     */
    public function orFilter(string $field, string $operator, mixed $value): static
    {
        $this->orFilters[] = [$field, $operator, $value];
        return $this;
    }

    /**
     * Specify link fields to expand (returns the linked document inline).
     *
     * @param  string[]  $fields
     */
    public function expand(array $fields): static
    {
        $this->expand = $fields;
        return $this;
    }

    /**
     * Set the ordering.
     *
     * @param  string  $field
     * @param  string  $direction  'asc' or 'desc'
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $this->orderBy = $field . ' ' . $direction;
        return $this;
    }

    /** Set the maximum number of records to return. */
    public function limit(int $limit): static
    {
        $this->limitLength = $limit;
        return $this;
    }

    /** Set the offset (for pagination). */
    public function offset(int $offset): static
    {
        $this->limitStart = $offset;
        return $this;
    }

    /**
     * Helper for classic page-based pagination.
     *
     * @param  int  $page   1-based page number
     * @param  int  $perPage
     */
    public function paginate(int $page, int $perPage = 20): static
    {
        $this->limitLength = $perPage;
        $this->limitStart  = ($page - 1) * $perPage;
        return $this;
    }

    /** Return results as a list of lists instead of list of dicts. */
    public function asList(): static
    {
        $this->asDict = false;
        return $this;
    }

    /** Include debug info (executed SQL + execution time) in the response. */
    public function debug(): static
    {
        $this->debug = true;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Execute
    // -------------------------------------------------------------------------

    /**
     * Build query parameters and send the request.
     *
     * @return array  The 'data' array from ERPNext response.
     */
    public function get(): array
    {
        $params = [
            'limit_start'       => $this->limitStart,
            'limit_page_length' => $this->limitLength,
            'as_dict'           => $this->asDict ? 1 : 0,
        ];

        if (! empty($this->fields)) {
            $params['fields'] = json_encode($this->fields);
        }

        if (! empty($this->filters)) {
            $params['filters'] = json_encode($this->filters);
        }

        if (! empty($this->orFilters)) {
            $params['or_filters'] = json_encode($this->orFilters);
        }

        if (! empty($this->expand)) {
            $params['expand'] = json_encode($this->expand);
        }

        if ($this->orderBy !== null) {
            $params['order_by'] = $this->orderBy;
        }

        if ($this->debug) {
            $params['debug'] = 'True';
        }

        $response = $this->client->get(
            "/api/resource/{$this->doctype}",
            ['query' => $params]
        );

        return $response['data'] ?? [];
    }

    /**
     * Return only the first result, or null.
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    /**
     * Count total records matching the current filters (no limit applied).
     */
    public function count(): int
    {
        $params = ['doctype' => $this->doctype];

        if (! empty($this->filters)) {
            $params['filters'] = json_encode($this->filters);
        }

        if (! empty($this->orFilters)) {
            $params['or_filters'] = json_encode($this->orFilters);
        }

        $response = $this->client->get(
            '/api/method/frappe.client.get_count',
            ['query' => $params]
        );

        return (int) ($response['message'] ?? 0);
    }
}
