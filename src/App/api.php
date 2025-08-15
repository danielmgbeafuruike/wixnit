<?php

namespace Wixnit\App;

use stdClass;
use Wixnit\Enum\HTTPResponseCode;

class api
{
    /**
     * Constructs a standard API response object.
     *
     * @param string $status The status of the response (e.g. 'success', 'error').
     * @param string $message A human-readable message.
     * @param HTTPResponseCode $code An HTTP status code wrapped in an enum.
     * @param mixed|null $data Optional response data.
     * @return stdClass The structured response object.
     */
    private static function build(string $status, string $message, HTTPResponseCode $code, $data = null): stdClass
    {
        $ret = new stdClass();
        $ret->status = $status;
        $ret->message = $message;
        $ret->code = $code->value;
        $ret->data = $data;
        return $ret;
    }

    /**
     * Returns a successful 200 OK response.
     *
     * @param mixed|null $data Optional response data.
     * @param string $message Optional success message.
     * @return stdClass
     */
    public static function Success($data = null, string $message = "Operation successful"): stdClass
    {
        return self::build('success', $message, HTTPResponseCode::OK, $data);
    }

    /**
     * Returns a 201 Created response.
     *
     * @param mixed|null $data Optional response data.
     * @param string $message Optional success message.
     * @return stdClass
     */
    public static function Created($data = null, string $message = "Resource created successfully"): stdClass
    {
        return self::build('success', $message, HTTPResponseCode::CREATED, $data);
    }

    /**
     * Returns a 202 Accepted response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function Accepted(string $message = "Request accepted for processing"): stdClass
    {
        return self::build('success', $message, HTTPResponseCode::ACCEPTED);
    }

    /**
     * Returns a 204 No Content response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function NoContent(string $message = "No content available"): stdClass
    {
        return self::build('no.content', $message, HTTPResponseCode::NO_CONTENT);
    }

    /**
     * Returns a 400 Bad Request response.
     *
     * @param string $message Optional error message.
     * @return stdClass
     */
    public static function BadRequest(string $message = "Bad request"): stdClass
    {
        return self::build('bad.request', $message, HTTPResponseCode::BAD_REQUEST);
    }

    /**
     * Returns a 401 Unauthorized response.
     *
     * @param string $message Optional error message.
     * @return stdClass
     */
    public static function Unauthorized(string $message = "Unauthorized access"): stdClass
    {
        return self::build('unauthorized', $message, HTTPResponseCode::UNAUTHORIZED);
    }

    /**
     * Returns a 403 Forbidden response.
     *
     * @param string $message Optional error message.
     * @return stdClass
     */
    public static function Forbidden(string $message = "Forbidden access"): stdClass
    {
        return self::build('forbidden', $message, HTTPResponseCode::FORBIDDEN);
    }

    /**
     * Returns a 404 Not Found response.
     *
     * @param string $message Optional error message.
     * @return stdClass
     */
    public static function NotFound(string $message = "Resource not found"): stdClass
    {
        return self::build('not.found', $message, HTTPResponseCode::NOT_FOUND);
    }

    /**
     * Returns a 405 Method Not Allowed response.
     *
     * @param string $message Optional error message.
     * @return stdClass
     */
    public static function MethodNotAllowed(string $message = "Method not allowed"): stdClass
    {
        return self::build('method.not.allowed', $message, HTTPResponseCode::METHOD_NOT_ALLOWED);
    }

    /**
     * Returns a 409 Conflict response.
     *
     * @param string $message Optional error message.
     * @return stdClass
     */
    public static function Conflict(string $message = "Conflict in request"): stdClass
    {
        return self::build('conflict', $message, HTTPResponseCode::CONFLICT);
    }

    /**
     * Returns a 410 Gone response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function Gone(string $message = "Resource no longer available"): stdClass
    {
        return self::build('gone', $message, HTTPResponseCode::GONE);
    }

    /**
     * Returns a 412 Precondition Failed response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function PreconditionFailed(string $message = "Precondition failed"): stdClass
    {
        return self::build('precondition.failed', $message, HTTPResponseCode::PRECONDITION_FAILED);
    }

    /**
     * Returns a 413 Payload Too Large response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function PayloadTooLarge(string $message = "Payload too large"): stdClass
    {
        return self::build('payload.too.large', $message, HTTPResponseCode::PAYLOAD_TOO_LARGE);
    }

    /**
     * Returns a 415 Unsupported Media Type response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function UnsupportedMediaType(string $message = "Unsupported media type"): stdClass
    {
        return self::build('unsupported.media.type', $message, HTTPResponseCode::UNSUPPORTED_MEDIA_TYPE);
    }

    /**
     * Returns a 422 Validation Error response.
     *
     * @param array $errors An array of validation errors with 'message' keys.
     * @return stdClass
     */
    public static function ValidationError(array $errors): stdClass
    {
        //$message = "Validation failed" . implode(", ", array_map(fn($error) => $error['message'], $errors));
        $message = "Validation failed";
        return self::build('validation.error', $message, HTTPResponseCode::UNPROCESSABLE_CONTENT, ['errors' => $errors]);
    }

    /**
     * Returns a 429 Too Many Requests response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function TooManyRequests(string $message = "Too many requests"): stdClass
    {
        return self::build('too.many.requests', $message, HTTPResponseCode::TOO_MANY_REQUESTS);
    }

    /**
     * Returns a 431 Header Fields Too Large response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function RequestHeaderFieldsTooLarge(string $message = "Header fields too large"): stdClass
    {
        return self::build('header.fields.too.large', $message, HTTPResponseCode::REQUEST_HEADER_FIELDS_TOO_LARGE);
    }

    /**
     * Returns a 451 Unavailable For Legal Reasons response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function UnavailableForLegalReasons(string $message = "Unavailable for legal reasons"): stdClass
    {
        return self::build('unavailable.legal', $message, HTTPResponseCode::UNAVAILABLE_FOR_LEGAL_REASONS);
    }

    /**
     * Returns a 500 Internal Server Error response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function InternalServerError(string $message = "Internal server error"): stdClass
    {
        return self::build('internal.server.error', $message, HTTPResponseCode::INTERNAL_SERVER_ERROR);
    }

    /**
     * Returns a 501 Not Implemented response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function NotImplemented(string $message = "Feature not implemented"): stdClass
    {
        return self::build('not.implemented', $message, HTTPResponseCode::NOT_IMPLEMENTED);
    }

    /**
     * Returns a 504 Gateway Timeout response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function GatewayTimeout(string $message = "Gateway timeout"): stdClass
    {
        return self::build('gateway.timeout', $message, HTTPResponseCode::GATEWAY_TIMEOUT);
    }

    /**
     * Returns a 503 Service Unavailable response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function ServiceUnavailable(string $message = "Service unavailable"): stdClass
    {
        return self::build('service.unavailable', $message, HTTPResponseCode::SERVICE_UNAVAILABLE);
    }

    /**
     * Returns a 510 Not Extended response.
     *
     * @param string $message Optional message.
     * @return stdClass
     */
    public static function NotExtended(string $message = "Further extensions required"): stdClass
    {
        return self::build('not.extended', $message, HTTPResponseCode::NOT_EXTENDED);
    }

    /**
     * Returns a custom response with given status, message, and code.
     *
     * @param string $status Custom status string.
     * @param string $message Custom message.
     * @param HTTPResponseCode $code HTTP status code enum.
     * @param mixed|null $data Optional data payload.
     * @return stdClass
     */
    public static function Custom(string $status, string $message, HTTPResponseCode $code = HTTPResponseCode::OK, $data = null): stdClass
    {
        return self::build($status, $message, $code, $data);
    }

    /**
     * Returns an error response using a specified HTTP status code enum.
     *
     * @param string $message Error message.
     * @param HTTPResponseCode $code HTTP status code enum.
     * @param mixed|null $data Optional data payload.
     * @return stdClass
     */
    public static function ErrorFromEnum(string $message, HTTPResponseCode $code, $data = null): stdClass
    {
        return self::build('error', $message, $code, $data);
    }
}