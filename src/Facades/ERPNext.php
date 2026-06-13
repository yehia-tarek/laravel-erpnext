<?php

namespace YehiaTarek\ERPNext\Facades;

use Illuminate\Support\Facades\Facade;
use YehiaTarek\ERPNext\ERPNextManager;
use YehiaTarek\ERPNext\Query\QueryBuilder;

/**
 * @method static array       listDocuments(string $doctype, array $params = [])
 * @method static array       getDocument(string $doctype, string $name, bool $expandLinks = false)
 * @method static array       createDocument(string $doctype, array $data)
 * @method static array       updateDocument(string $doctype, string $name, array $data)
 * @method static bool        deleteDocument(string $doctype, string $name)
 * @method static mixed       call(string $method, array $params = [], string $verb = 'GET')
 * @method static mixed       callGet(string $method, array $params = [])
 * @method static mixed       callPost(string $method, array $params = [])
 * @method static array       uploadFile(string $filePath, ?string $doctype = null, ?string $docname = null, ?string $fieldname = null, bool $isPrivate = false)
 * @method static array       uploadFileContent(string $content, string $filename, ?string $doctype = null, ?string $docname = null, bool $isPrivate = false)
 * @method static QueryBuilder query(string $doctype)
 * @method static string      getLoggedUser()
 * @method static \YehiaTarek\ERPNext\ERPNextClient connection(string $name = 'default')
 *
 * @see \YehiaTarek\ERPNext\ERPNextManager
 * @see \YehiaTarek\ERPNext\ERPNextClient
 */
class ERPNext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ERPNextManager::class;
    }
}
