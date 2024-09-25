<?php

    namespace Wixnit\Enum;

    enum HTTPMethod : string
    {
        case GET = "GET";
        case POST = "POST";
        case PUT = "PUT";
        case HEAD = "HEAD";
        case OPTION = "OPTION";
        case PATCH = "PATCH";
        case DELETE = "DELETE";
        case ANY = "*";
    }