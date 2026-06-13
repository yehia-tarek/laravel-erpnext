<?php

namespace YehiaTarek\ERPNext\Resources;

use YehiaTarek\ERPNext\ERPNextClient;
use YehiaTarek\ERPNext\Facades\ERPNext;
use YehiaTarek\ERPNext\Query\QueryBuilder;

/**
 * Base class for typed ERPNext DocType resources.
 *
 * Extend this class to get a clean, Eloquent-style interface
 * for any ERPNext DocType.
 *
 * Example:
 *
 *   class SalesInvoice extends Document
 *   {
 *       protected static string $doctype = 'Sales Invoice';
 *   }
 *
 *   // List
 *   SalesInvoice::query()->filter('status', '=', 'Paid')->get();
 *
 *   // Get
 *   $invoice = SalesInvoice::find('SINV-00001');
 *
 *   // Create
 *   $invoice = SalesInvoice::create(['customer' => 'CUST-001', ...]);
 *
 *   // Update
 *   $invoice->update(['status' => 'Cancelled']);
 *
 *   // Delete
 *   $invoice->delete();
 */
abstract class Document
{
    protected static string $doctype = '';

    protected array $attributes = [];
    protected bool  $exists     = false;

    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->attributes = $attributes;
        $this->exists      = $exists;
    }

    // =========================================================================
    // Magic attribute access
    // =========================================================================

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    // =========================================================================
    // Static factory methods
    // =========================================================================

    /**
     * Start a fluent query on this DocType.
     */
    public static function query(): QueryBuilder
    {
        return ERPNext::query(static::getDoctype());
    }

    /**
     * Find a document by name. Returns null if not found.
     */
    public static function find(string $name, bool $expandLinks = false): ?static
    {
        try {
            $data = ERPNext::getDocument(static::getDoctype(), $name, $expandLinks);
            return new static($data, true);
        } catch (\YehiaTarek\ERPNext\Exceptions\DocumentNotFoundException) {
            return null;
        }
    }

    /**
     * Find a document by name or throw DocumentNotFoundException.
     */
    public static function findOrFail(string $name, bool $expandLinks = false): static
    {
        $data = ERPNext::getDocument(static::getDoctype(), $name, $expandLinks);
        return new static($data, true);
    }

    /**
     * Create a new document and return the hydrated instance.
     */
    public static function create(array $data): static
    {
        $result = ERPNext::createDocument(static::getDoctype(), $data);
        return new static($result, true);
    }

    /**
     * Return all documents (up to the configured default limit).
     *
     * @return static[]
     */
    public static function all(array $fields = []): array
    {
        $query = static::query();
        if (! empty($fields)) {
            $query->fields($fields);
        }
        return array_map(fn($row) => new static($row, true), $query->get());
    }

    // =========================================================================
    // Instance methods
    // =========================================================================

    /**
     * Persist changes to this document back to ERPNext.
     */
    public function save(): static
    {
        $name = $this->attributes['name'] ?? null;

        if ($this->exists && $name) {
            $result = ERPNext::updateDocument(static::getDoctype(), $name, $this->attributes);
        } else {
            $result = ERPNext::createDocument(static::getDoctype(), $this->attributes);
            $this->exists = true;
        }

        $this->attributes = $result;
        return $this;
    }

    /**
     * Partially update a document by sending only the changed fields.
     */
    public function update(array $data): static
    {
        $name = $this->attributes['name']
            ?? throw new \RuntimeException('Cannot update a document without a name.');

        $result = ERPNext::updateDocument(static::getDoctype(), $name, $data);
        $this->attributes = $result;
        return $this;
    }

    /**
     * Delete this document.
     */
    public function delete(): bool
    {
        $name = $this->attributes['name']
            ?? throw new \RuntimeException('Cannot delete a document without a name.');

        $success = ERPNext::deleteDocument(static::getDoctype(), $name);

        if ($success) {
            $this->exists = false;
        }

        return $success;
    }

    /**
     * Reload the document from ERPNext.
     */
    public function refresh(): static
    {
        $name = $this->attributes['name']
            ?? throw new \RuntimeException('Cannot refresh a document without a name.');

        $data = ERPNext::getDocument(static::getDoctype(), $name);
        $this->attributes = $data;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    // =========================================================================
    // Internal
    // =========================================================================

    protected static function getDoctype(): string
    {
        if (empty(static::$doctype)) {
            throw new \RuntimeException(
                'You must define the static $doctype property on ' . static::class
            );
        }
        return static::$doctype;
    }
}
