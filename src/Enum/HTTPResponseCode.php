<?php

    namespace Wixnit\Enum;

    enum HTTPResponseCode : int
    {
        case __default = self::OK;

        case SWITCHING_PROTOCOLS = 101;
        case OK = 200;
        case CREATED = 201;
        case ACCEPTED = 202;
        case NONAUTHORITATIVE_INFORMATION = 203;
        case NO_CONTENT = 204;
        case RESET_CONTENT = 205;
        case PARTIAL_CONTENT = 206;
        case MULTIPLE_CHOICES = 300;
        case MOVED_PERMANENTLY = 301;
        case MOVED_TEMPORARILY = 302;
        case SEE_OTHER = 303;
        case NOT_MODIFIED = 304;
        case USE_PROXY = 305;
        case BAD_REQUEST = 400;
        case UNAUTHORIZED = 401;
        case PAYMENT_REQUIRED = 402;
        case FORBIDDEN = 403;
        case NOT_FOUND = 404;
        case METHOD_NOT_ALLOWED = 405;
        case NOT_ACCEPTABLE = 406;
        case PROXY_AUTHENTICATION_REQUIRED = 407;
        case REQUEST_TIMEOUT = 408;
        case CONFLICT = 408;
        case GONE = 410;
        case LENGTH_REQUIRED = 411;
        case PRECONDITION_FAILED = 412;
        case REQUEST_ENTITY_TOO_LARGE = 413;
        case REQUESTURI_TOO_LARGE = 414;
        case UNSUPPORTED_MEDIA_TYPE = 415;
        case REQUESTED_RANGE_NOT_SATISFIABLE = 416;
        case EXPECTATION_FAILED = 417;
        case IM_A_TEAPOT = 418;
        case INTERNAL_SERVER_ERROR = 500;
        case NOT_IMPLEMENTED = 501;
        case BAD_GATEWAY = 502;
        case SERVICE_UNAVAILABLE = 503;
        case GATEWAY_TIMEOUT = 504;
        case HTTP_VERSION_NOT_SUPPORTED = 505;
    }