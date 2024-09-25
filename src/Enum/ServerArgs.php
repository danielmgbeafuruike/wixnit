<?php

    namespace Wixnit\Enum;

    enum ServerArgs : string
    {
        case MIBDIRS = "MIBDIRS";
        case MYSQL_HOME = "MYSQL_HOME";
        case OPENSSL_CONF = "OPENSSL_CONF";
        case PHP_PEAR_SYSCONF_DIR = "PHP_PEAR_SYSCONF_DIR";
        case PHPRC = "PHPRC";
        case TMP = "TMP";
        case HTTP_HOST = "HTTP_HOST";
        case HTTP_CONNECTION = "HTTP_CONNECTION";
        case HTTP_UPGRADE_INSECURE_REQUESTS = "HTTP_UPGRADE_INSECURE_REQUESTS";
        case HTTP_USER_AGENT = "HTTP_USER_AGENT";
        case HTTP_ACCEPT = "HTTP_ACCEPT";
        case HTTP_SEC_FETCH_SITE = "HTTP_SEC_FETCH_SITE";
        case HTTP_SEC_FETCH_MODE = "HTTP_SEC_FETCH_MODE";
        case HTTP_SEC_FETCH_USER = "HTTP_SEC_FETCH_USER";
        case HTTP_SEC_FETCH_DEST = "HTTP_SEC_FETCH_DEST";
        case HTTP_SEC_CH_UA = "HTTP_SEC_CH_UA";
        case HTTP_SEC_CH_UA_MOBILE = "HTTP_SEC_CH_UA_MOBILE";
        case HTTP_SEC_CH_UA_PLATFORM = "HTTP_SEC_CH_UA_PLATFORM";
        case HTTP_ACCEPT_ENCODING = "HTTP_ACCEPT_ENCODING";
        case HTTP_ACCEPT_LANGUAGE = "HTTP_ACCEPT_LANGUAGE";
        case HTTP_COOKIE = "HTTP_COOKIE";
        case PATH = "PATH";
        case SystemRoot = "SystemRoot";
        case COMSPEC = "COMSPEC";
        case PATHEXT = "PATHEXT";
        case WINDIR = "WINDIR";
        case SERVER_SIGNATURE = "SERVER_SIGNATURE";
        case SERVER_SOFTWARE = "SERVER_SOFTWARE";
        case SERVER_NAME = "SERVER_NAME";
        case SERVER_ADDR = "SERVER_ADDR";
        case SERVER_PORT = "SERVER_PORT";
        case REMOTE_ADDR = "REMOTE_ADDR";
        case DOCUMENT_ROOT = "DOCUMENT_ROOT";
        case REQUEST_SCHEME = "REQUEST_SCHEME";
        case CONTEXT_PREFIX = "CONTEXT_PREFIX";
        case CONTEXT_DOCUMENT_ROOT = "CONTEXT_DOCUMENT_ROOT";
        case SERVER_ADMIN = "SERVER_ADMIN";
        case SCRIPT_FILENAME = "SCRIPT_FILENAME";
        case REMOTE_PORT = "REMOTE_PORT";
        case GATEWAY_INTERFACE = "GATEWAY_INTERFACE";
        case SERVER_PROTOCOL = "SERVER_PROTOCOL";
        case REQUEST_METHOD = "REQUEST_METHOD";
        case QUERY_STRING = "QUERY_STRING";
        case REQUEST_URI = "REQUEST_URI";
        case SCRIPT_NAME = "SCRIPT_NAME";
        case PHP_SELF = "PHP_SELF";
        case REQUEST_TIME_FLOAT = "REQUEST_TIME_FLOAT";
        case REQUEST_TIME = "REQUEST_TIME";
    }