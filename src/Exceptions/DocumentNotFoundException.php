<?php

namespace YehiaTarek\ERPNext\Exceptions;

class DocumentNotFoundException extends ERPNextException
{
    public static function forDoctype(string $doctype, string $name): static
    {
        return new static("Document '{$name}' of type '{$doctype}' not found.", 404);
    }
}
