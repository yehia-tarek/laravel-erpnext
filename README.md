# Laravel ERPNext

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yehia-tarek/laravel-erpnext.svg?style=flat-square)](https://packagist.org/packages/yehia-tarek/laravel-erpnext)
[![Total Downloads](https://img.shields.io/packagist/dt/yehia-tarek/laravel-erpnext.svg?style=flat-square)](https://packagist.org/packages/yehia-tarek/laravel-erpnext)
[![License](https://img.shields.io/packagist/l/yehia-tarek/laravel-erpnext.svg?style=flat-square)](https://packagist.org/packages/yehia-tarek/laravel-erpnext)

A robust, fluent Laravel package for seamlessly interacting with the ERPNext / Frappe REST API. It provides an elegant interface for CRUD operations, a powerful query builder, typed document resources, file uploads, and multiple authentication strategies.

---

## 📋 Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [CRUD Operations](#crud-operations)
  - [Fluent Query Builder](#fluent-query-builder)
  - [Remote Method Calls](#remote-method-calls)
  - [File Uploads](#file-uploads)
- [Advanced Features](#advanced-features)
  - [Typed Document Resources](#typed-document-resources)
  - [Multiple Connections](#multiple-connections)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [License](#license)

---

## Requirements

| Dependency | Version |
|------------|---------|
| PHP        | `^8.1`  |
| Laravel    | `^10.0` or `^11.0` |
| Guzzle     | `^7.5`  |

## Installation

You can install the package via Composer:

```bash
composer require yehia-tarek/laravel-erpnext
```

Publish the configuration file to define your ERPNext connection settings:

```bash
php artisan vendor:publish --tag="erpnext-config"
```

## Configuration

Add your ERPNext credentials to your application's `.env` file. The package supports multiple authentication methods, with Token Authentication being the recommended approach for API integrations.

```env
ERPNEXT_BASE_URL=https://mycompany.erpnext.com

# --- Token Auth (Recommended) ---
ERPNEXT_AUTH_METHOD=token
ERPNEXT_API_KEY=your_api_key
ERPNEXT_API_SECRET=your_api_secret

# --- Password Auth (Session-based) ---
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
To use Token Authentication, generate API keys from your ERPNext instance:
1. Go to **User List** → open the desired user.
2. Click on the **Settings** tab.
3. Expand the **API Access** section and click **Generate Keys**.
4. Copy the **API Secret** immediately (it is only shown once).
5. Note the **API Key** displayed in the same section.

---

## Authentication

The package automatically handles authentication based on your `.env` configuration.

- **Token:** Sends `Authorization: token {api_key}:{api_secret}` with every request.
- **Password:** Performs a login request on the first API call and reuses the returned cookie session for subsequent requests.
- **OAuth 2.0:** Sends `Authorization: Bearer {access_token}` with every request.

---

## Quick Start

After configuring your `.env` file, you can verify the connection using the `ERPNext` facade:

```php
use YehiaTarek\ERPNext\Facades\ERPNext;

$user = ERPNext::getLoggedUser(); 
// Returns: "admin@example.com"
```

---

## Usage

### CRUD Operations

The package provides intuitive methods to interact with ERPNext documents.

**Create a Document**
```php
$invoice = ERPNext::createDocument('Sales Invoice', [
    'customer' => 'ACME Corp',
    'items'    => [
        ['item_code' => 'ITEM-001', 'qty' => 2, 'rate' => 150],
    ],
]);

echo $invoice['name']; // e.g., "SINV-00001"
```

**Read a Document**
```php
$invoice = ERPNext::getDocument('Sales Invoice', 'SINV-00001');
echo $invoice['grand_total'];

// Expand link fields to fetch full related documents instead of just their names
$expandedInvoice = ERPNext::getDocument('Sales Invoice', 'SINV-00001', expandLinks: true);
echo $expandedInvoice['customer']['customer_name']; // Returns the full Customer document
```

**Update a Document**
```php
ERPNext::updateDocument('Sales Invoice', 'SINV-00001', [
    'status' => 'Paid',
]);
```

**Delete a Document**
```php
ERPNext::deleteDocument('Sales Invoice', 'SINV-00001'); // Returns true on success
```

### Fluent Query Builder

Retrieve multiple documents using a fluent, Eloquent-like query builder that wraps the `GET /api/resource/:doctype` endpoint.

```php
use YehiaTarek\ERPNext\Facades\ERPNext;

$invoices = ERPNext::query('Sales Invoice')
    ->fields(['name', 'customer', 'grand_total', 'status'])
    ->filter('status', '=', 'Paid')
    ->filter('grand_total', '>', 5000)
    ->orderBy('grand_total', 'desc')
    ->limit(25)
    ->get(); // Returns an array of arrays
```

**Available Builder Methods**

| Method | Description |
|---|---|
| `fields(array)` | Specify which fields to fetch. |
| `filter(field, op, value)` | Add an `AND` filter condition. |
| `filters(array)` | Add multiple `AND` filters at once. |
| `orFilter(field, op, value)` | Add an `OR` filter condition. |
| `expand(array)` | Expand specific link fields inline. |
| `orderBy(field, direction)` | Sort results (`asc` or `desc`). |
| `limit(int)` | Limit the number of records returned. |
| `offset(int)` | Skip a specific number of records. |
| `paginate(page, perPage)` | Utilize page-based pagination. |
| `asList()` | Return results as a `List[List]` instead of `List[dict]`. |
| `debug()` | Include executed SQL in the response. |
| `get()` | Execute the query and return an array. |
| `first()` | Execute and return the first item or `null`. |
| `count()` | Return the total count of matching documents. |

**Supported Filtering Operators**
```php
->filter('status', '=',      'Paid')
->filter('amount', '>',      1000)
->filter('amount', '>=',     500)
->filter('name',   'like',   'SINV-%')
->filter('status', 'in',     ['Paid', 'Unpaid'])
->filter('status', 'not in', ['Cancelled'])
->filter('note',   '!=',     null)
```

### Remote Method Calls

Call any whitelisted Python method on your ERPNext instance.

```php
// GET method (read-only)
$user = ERPNext::callGet('frappe.auth.get_logged_user');

// POST method (mutates data)
ERPNext::callPost('frappe.client.submit', [
    'doc' => ['doctype' => 'Sales Invoice', 'name' => 'SINV-00001'],
]);

// Generic call specifying the HTTP verb explicitly
ERPNext::call('erpnext.accounts.doctype.payment_entry.payment_entry.get_outstanding_reference_documents', [
    'args' => ['party_type' => 'Customer', 'party' => 'CUST-001'],
], 'POST');
```

### File Uploads

Upload files directly from your local filesystem or via raw content.

**Upload from Local Path**
```php
$file = ERPNext::uploadFile(
    filePath: storage_path('app/invoice.pdf'),
    doctype:  'Sales Invoice',
    docname:  'SINV-00001',
    fieldname: 'attachment',
    isPrivate: true,
);

echo $file['file_url'];
```

**Upload from Raw Content**
```php
$file = ERPNext::uploadFileContent(
    content:  $pdfContent,
    filename: 'report.pdf',
    doctype:  'Sales Invoice',
    docname:  'SINV-00001',
);
```

---

## Advanced Features

### Typed Document Resources

For a more structured, object-oriented approach, you can define typed resources that extend the base `Document` class. This provides an Eloquent-like experience for specific DocTypes.

```php
namespace App\ERPNext;

use YehiaTarek\ERPNext\Resources\Document;

class SalesInvoice extends Document
{
    protected static string $doctype = 'Sales Invoice';
}
```

**Usage Example:**
```php
use App\ERPNext\SalesInvoice;

// Query
$paid = SalesInvoice::query()
    ->fields(['name', 'customer', 'grand_total'])
    ->filter('status', '=', 'Paid')
    ->orderBy('modified', 'desc')
    ->get();

// Find
$invoice = SalesInvoice::find('SINV-00001');
echo $invoice->grand_total;

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

// Delete & Refresh
$invoice->delete();
$invoice->refresh();
```

### Multiple Connections

If you need to interact with multiple ERPNext instances (e.g., staging and production), define them in `config/erpnext.php`:

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

You can easily switch connections using the `connection()` method:

```php
$stagingInvoice = ERPNext::connection('staging')->getDocument('Sales Invoice', 'SINV-00001');
```

---

## Error Handling

The package throws specific exceptions for different API error scenarios, allowing you to handle them gracefully.

| Exception | Trigger |
|---|---|
| `AuthenticationException` | 401/403 responses or missing credentials. |
| `DocumentNotFoundException` | 404 response from ERPNext. |
| `ValidationException` | ERPNext `ValidationError` (422). |
| `ERPNextException` | Fallback for all other API errors. |

**Example:**
```php
use YehiaTarek\ERPNext\Exceptions\DocumentNotFoundException;
use YehiaTarek\ERPNext\Exceptions\ValidationException;
use YehiaTarek\ERPNext\Exceptions\ERPNextException;

try {
    $doc = ERPNext::getDocument('Sales Invoice', 'SINV-GHOST');
} catch (DocumentNotFoundException $e) {
    // Handle 404
} catch (ValidationException $e) {
    $context = $e->getContext(); // Raw response body as array
} catch (ERPNextException $e) {
    // Handle generic API errors
}
```

---

## Testing

Run the package tests using Composer:

```bash
composer test
```

### Mocking in Your Application Tests

You can easily mock the `ERPNext` facade in your application's test suite:

```php
use YehiaTarek\ERPNext\Facades\ERPNext;

ERPNext::shouldReceive('getDocument')
    ->with('Sales Invoice', 'SINV-00001', false)
    ->andReturn(['name' => 'SINV-00001', 'status' => 'Paid']);
```

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
