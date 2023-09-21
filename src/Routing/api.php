<?php

    namespace Wixnit\Routing;

    class api
    {
        const success = "success";
        const failed = "failed";
        const error = "error";
        const not_found = "no-found";
        const conflict = "conflict";
        const forbidden = "forbidden";
        const access_denied = "access_denied";
        const authentication_required = "authentication_required";

        public static function Deserializepayload(): array
        {
            return [];
        }


        public static function respond($status = api::failed, $message = "", $data=null, $headers=[])
        {
            $response_status = 0;

            switch ($status)
            {
                case api::failed:
                    $response_status = ResponseCode::BAD_REQUEST;
                    break;
                case api::error:
                    $response_status = ResponseCode::INTERNAL_SERVER_ERROR;
                    break;
                case api::conflict:
                    $response_status = ResponseCode::CONFLICT;
                    break;
                case api::forbidden:
                    $response_status = ResponseCode::FORBIDDEN;
                    break;
                case api::access_denied:
                    $response_status = ResponseCode::UNAUTHORIZED;
                    break;
                case api::not_found:
                    $response_status = ResponseCode::NOT_FOUND;
                    break;
                case api::authentication_required:
                    $response_status = ResponseCode::OK;
                    break;
                default:
                    $response_status = ResponseCode::OK;
            }

            http_response_code($response_status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status"=>$status, "message"=>strval($message), "data"=>$data]);
        }

        public  static function Error($message = "", $data=null, $headers=[])
        {
            api::respond(
                api::error,
                $message,
                $data,
                $headers
            );
        }

        public  static function Success($message="", $data=null, $headers=[])
        {
            api::respond(
                api::success,
                $message,
                $data,
                $headers
            );
        }

        public  static function ok($message="", $data=null, $headers=[])
        {
            api::respond(
                api::success,
                $message,
                $data,
                $headers
            );
        }

        public  static function Failed($message="", $data=null, $headers=[])
        {
            api::respond(
                api::failed,
                $message,
                $data,
                $headers
            );
        }

        public  static function  AccessDenied($message="", $data=null, $headers=[])
        {
            api::respond(
                api::access_denied,
                $message,
                $data,
                $headers
            );
        }
    }