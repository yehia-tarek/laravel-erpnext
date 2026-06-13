# Laravel ERPNext

A Laravel package for interacting with ERPNext / Frappe REST API. Supports token, password, and OAuth 2.0 authentication; fluent query builder; full CRUD; remote method calls; and file uploads.

---

## Requirements

| Dependency | Version        |
| ---------- | -------------- |
| PHP        | ^8.1           |
| Laravel    | ^10.0 \| ^11.0 |
| Guzzle     | ^7.5           |

---

## Installation

```bash
composer require yehia-tarek/laravel-erpnext
```

Publish the config file:

```bash
php artisan vendor:publish --tag=erpnext-config
```

---

## Configuration

Add your ERPNext credentials to `.env`:

```env
ERPNEXT_BASE_URL=https://mycompany.erpnext.com

# --- Token Auth (recommended) ---
ERPNEXT_AUTH_METHOD=token
ERPNEXT_API_KEY=your_api_key
ERPNEXT_API_SECRET=your_api_secret

# --- Password Auth ---
# ERPNEXT_AUTH_METHOD=password
# ERPNEXT_USERNAME=admin
# ERPNEXT_PASSWORD=secret

# --- OAuth 2.0 ---
# ERPNEXT_AUTH_METHOD=oauth
# ERPNEXT_ACCESS_TOKEN=your_bearer_token

# --- HTTP Options ---
ERPNEXT_TIMEOUT=30
ERPNEXT_VERIFY_SSL=true
```

### Generating API Keys in ERPNext

1. Go to **User List** → open a user.
2. Click the **Settings** tab.
3. Expand **API Access** and click **Generate Keys**.
4. Copy the **API Secret** immediately (shown only once).
5. Also note the **API Key** in that section.

---

## Quick Start

```php
use YehiaTarek\ERPNext\Facades\ERPNext;

// Verify connection
$user = ERPNext::getLoggedUser(); // e.g. "admin@example.com"
```

---

## Authentication

### Token (recommended)

```env
ERPNEXT_AUTH_METHOD=token
ERPNEXT_API_KEY=abc123
ERPNEXT_API_SECRET=xyz789
```

Every request sends `Authorization: token abc123:xyz789`.

### Password (session-based)

```env
ERPNEXT_AUTH_METHOD=password
ERPNEXT_USERNAME=admin
ERPNEXT_PASSWORD=secret
```

The package performs a login on first use and reuses the cookie session.

### OAuth 2.0

```env
ERPNEXT_AUTH_METHOD=oauth
ERPNEXT_ACCESS_TOKEN=your_bearer_token
```

Sends `Authorization: Bearer your_bearer_token`.

---

## CRUD Operations

### Create a document

```php
$invoice = ERPNext::createDocument('Sales Invoice', [
    'customer'    => 'ACME Corp',
    'items'       => [
        ['item_code' => 'ITEM-001', 'qty' => 2, 'rate' => 150],
    ],
]);

echo $invoice['name']; // SINV-00001
```

### Read a document

```php
$invoice = ERPNext::getDocument('Sales Invoice', 'SINV-00001');
echo $invoice['grand_total'];

// Expand all link fields (returns the full linked document instead of just the name)
$invoice = ERPNext::getDocument('Sales Invoice', 'SINV-00001', expandLinks: true);
echo $invoice['customer']['customer_name']; // expanded Customer doc
```

### Update a document (partial update)

```php
$invoice = ERPNext::updateDocument('Sales Invoice', 'SINV-00001', [
    'status' => 'Paid',
]);
```

### Delete a document

```php
ERPNext::deleteDocument('Sales Invoice', 'SINV-00001'); // true on success
```

---

## Query Builder

The fluent query builder wraps `GET /api/resource/:doctype`.

```php
use YehiaTarek\ERPNext\Facades\ERPNext;

$invoices = ERPNext::query('Sales Invoice')
    ->fields(['name', 'customer', 'grand_total', 'status'])
    ->filter('status', '=', 'Paid')
    ->filter('grand_total', '>', 5000)
    ->orderBy('grand_total', 'desc')
    ->limit(25)
    ->get(); // returns array of arrays
```

### Available builder methods

| Method                       | Description                                 |
| ---------------------------- | ------------------------------------------- |
| `fields(array)`              | Fields to fetch                             |
| `filter(field, op, value)`   | Add an AND filter                           |
| `filters(array)`             | Add multiple AND filters at once            |
| `orFilter(field, op, value)` | Add an OR filter                            |
| `expand(array)`              | Expand link fields inline                   |
| `orderBy(field, direction)`  | Sort results                                |
| `limit(int)`                 | Max records to return                       |
| `offset(int)`                | Records to skip                             |
| `paginate(page, perPage)`    | Page-based pagination                       |
| `asList()`                   | Return `List[List]` instead of `List[dict]` |
| `debug()`                    | Include executed SQL in response            |
| `get()`                      | Execute and return array                    |
| `first()`                    | Execute, return first item or `null`        |
| `count()`                    | Count matching documents                    |

### Filtering operators

```php
->filter('status', '=',      'Paid')
->filter('amount', '>',      1000)
->filter('amount', '>=',     500)
->filter('name',   'like',   'SINV-%')
->filter('status', 'in',     ['Paid', 'Unpaid'])
->filter('status', 'not in', ['Cancelled'])
->filter('note',   '!=',     null)
```

### Pagination example

```php
// Page 2, 15 records per page
$records = ERPNext::query('Customer')
    ->fields(['name', 'customer_name', 'territory'])
    ->paginate(page: 2, perPage: 15)
    ->get();
```

---

## Remote Method Calls

Call any whitelisted Python method on ERPNext.

```php
// GET method (read-only)
$user = ERPNext::callGet('frappe.auth.get_logged_user');

// POST method (mutates data)
ERPNext::callPost('frappe.client.submit', [
    'doc' => ['doctype' => 'Sales Invoice', 'name' => 'SINV-00001'],
]);

// Generic (specify verb explicitly)
ERPNext::call('erpnext.accounts.doctype.payment_entry.payment_entry.get_outstanding_reference_documents', [
    'args' => ['party_type' => 'Customer', 'party' => 'CUST-001'],
], 'POST');
```

---

## File Uploads

```php
// Upload from a local file path
$file = ERPNext::uploadFile(
    filePath: storage_path('app/invoice.pdf'),
    doctype:  'Sales Invoice',
    docname:  'SINV-00001',
    fieldname: 'attachment',
    isPrivate: true,
);

echo $file['file_url'];

// Upload from raw content (e.g. generated PDF)
$file = ERPNext::uploadFileContent(
    content:  $pdfContent,
    filename: 'report.pdf',
    doctype:  'Sales Invoice',
    docname:  'SINV-00001',
);
```

---

## Typed Document Resources

Extend `Document` for an Eloquent-style interface per DocType:

```php
<?php

namespace App\ERPNext;

use YehiaTarek\ERPNext\Resources\Document;

class SalesInvoice extends Document
{
    protected static string $doctype = 'Sales Invoice';
}
```

```php
use App\ERPNext\SalesInvoice;

// Fluent query
$paid = SalesInvoice::query()
    ->fields(['name', 'customer', 'grand_total'])
    ->filter('status', '=', 'Paid')
    ->orderBy('modified', 'desc')
    ->get();

// Find
$invoice = SalesInvoice::find('SINV-00001');
echo $invoice->grand_total;
echo $invoice->customer;

// Find or fail (throws DocumentNotFoundException)
$invoice = SalesInvoice::findOrFail('SINV-00001');

// Create
$invoice = SalesInvoice::create([
    'customer' => 'ACME Corp',
    'items'    => [...],
]);

// Update (partial)
$invoice->update(['status' => 'Paid']);

// Save (create or update based on whether 'name' is present)
$invoice = new SalesInvoice(['customer' => 'ACME Corp', ...]);
$invoice->save();

// Delete
$invoice->delete();

// Reload from ERPNext
$invoice->refresh();
```

---

## Multiple Connections

Configure additional connections in `config/erpnext.php`:

```php
return [
    'base_url' => env('ERPNEXT_BASE_URL'),
    'auth'     => [...],

    'connections' => [
        'staging' => [
            'base_url' => env('ERPNEXT_STAGING_URL'),
            'auth'     => [
                'method'     => 'token',
                'api_key'    => env('ERPNEXT_STAGING_API_KEY'),
                'api_secret' => env('ERPNEXT_STAGING_API_SECRET'),
            ],
        ],
    ],
];
```

```php
// Use a named connection
$invoice = ERPNext::connection('staging')->getDocument('Sales Invoice', 'SINV-00001');
```

---

## Error Handling

| Exception                   | When                              |
| --------------------------- | --------------------------------- |
| `AuthenticationException`   | 401 / 403, or missing credentials |
| `DocumentNotFoundException` | 404 response                      |
| `ValidationException`       | ERPNext `ValidationError` (422)   |
| `ERPNextException`          | All other API errors              |

```php
use YehiaTarek\ERPNext\Exceptions\DocumentNotFoundException;
use YehiaTarek\ERPNext\Exceptions\ValidationException;
use YehiaTarek\ERPNext\Exceptions\ERPNextException;

try {
    $doc = ERPNext::getDocument('Sales Invoice', 'SINV-GHOST');
} catch (DocumentNotFoundException $e) {
    // 404
} catch (ValidationException $e) {
    // ERPNext validation failed
    $context = $e->getContext(); // raw response body as array
} catch (ERPNextException $e) {
    // anything else
}
```

---

## Testing

```bash
composer test
```

### Mocking in your app tests

```php
use YehiaTarek\ERPNext\Facades\ERPNext;

ERPNext::shouldReceive('getDocument')
    ->with('Sales Invoice', 'SINV-00001', false)
    ->andReturn(['name' => 'SINV-00001', 'status' => 'Paid']);
```

---

## License

MIT
