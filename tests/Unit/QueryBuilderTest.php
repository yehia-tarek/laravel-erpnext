<?php

namespace YehiaTarek\ERPNext\Tests\Unit;

use Mockery;
use YehiaTarek\ERPNext\ERPNextClient;
use YehiaTarek\ERPNext\Query\QueryBuilder;
use YehiaTarek\ERPNext\Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    private ERPNextClient $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(ERPNextClient::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_basic_get_builds_correct_params(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->withArgs(function (string $endpoint, array $options) {
                $q = $options['query'] ?? [];
                return $endpoint === '/api/resource/Customer'
                    && $q['limit_start'] === 0
                    && $q['limit_page_length'] === 20;
            })
            ->andReturn(['data' => []]);

        $builder = new QueryBuilder($this->mockClient, 'Customer');
        $builder->get();
    }

    public function test_fields_are_json_encoded(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->withArgs(function (string $endpoint, array $options) {
                $q = $options['query'] ?? [];
                return $q['fields'] === json_encode(['name', 'customer_name']);
            })
            ->andReturn(['data' => []]);

        (new QueryBuilder($this->mockClient, 'Customer'))
            ->fields(['name', 'customer_name'])
            ->get();
    }

    public function test_filter_is_json_encoded(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->withArgs(function (string $endpoint, array $options) {
                $q       = $options['query'] ?? [];
                $filters = json_decode($q['filters'], true);
                return $filters === [['status', '=', 'Active']];
            })
            ->andReturn(['data' => []]);

        (new QueryBuilder($this->mockClient, 'Customer'))
            ->filter('status', '=', 'Active')
            ->get();
    }

    public function test_pagination(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->withArgs(function (string $endpoint, array $options) {
                $q = $options['query'] ?? [];
                return $q['limit_start'] === 20 && $q['limit_page_length'] === 10;
            })
            ->andReturn(['data' => []]);

        (new QueryBuilder($this->mockClient, 'Customer'))
            ->paginate(page: 3, perPage: 10)
            ->get();
    }

    public function test_first_limits_to_one(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->withArgs(function (string $endpoint, array $options) {
                return ($options['query']['limit_page_length'] ?? null) === 1;
            })
            ->andReturn(['data' => [['name' => 'CUST-001']]]);

        $result = (new QueryBuilder($this->mockClient, 'Customer'))->first();

        $this->assertSame(['name' => 'CUST-001'], $result);
    }

    public function test_first_returns_null_when_empty(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->andReturn(['data' => []]);

        $result = (new QueryBuilder($this->mockClient, 'Customer'))->first();

        $this->assertNull($result);
    }

    public function test_order_by_appends_direction(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->withArgs(function (string $endpoint, array $options) {
                return ($options['query']['order_by'] ?? '') === 'modified desc';
            })
            ->andReturn(['data' => []]);

        (new QueryBuilder($this->mockClient, 'Customer'))
            ->orderBy('modified', 'desc')
            ->get();
    }
}
