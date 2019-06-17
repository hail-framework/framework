<?php

namespace Hail\Http;


interface RequestMethod
{
    public const HEAD = 'HEAD',
        GET = 'GET',
        POST = 'POST',
        PUT = 'PUT',
        PATCH = 'PATCH',
        DELETE = 'DELETE',
        PURGE = 'PURGE',
        OPTIONS = 'OPTIONS',
        TRACE = 'TRACE',
        CONNECT = 'CONNECT';
}
