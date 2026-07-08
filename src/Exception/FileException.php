<?php

    namespace Wixnit\Exception;

    use Throwable;

    /**
     * Thrown by File and Directory utilities when a filesystem operation fails.
     * Each factory method attaches the path(s) involved as context so the
     * cause is obvious without digging through a stack trace.
     */
    class FileException extends WixnitException
    {
        public static function NotFound(string $path): self
        {
            return new self("File not found: '$path'.", ["path" => $path]);
        }

        public static function DirectoryNotFound(string $path): self
        {
            return new self("Directory not found: '$path'.", ["path" => $path]);
        }

        public static function NotReadable(string $path): self
        {
            return new self("File is not readable (check permissions): '$path'.", ["path" => $path]);
        }

        public static function NotWritable(string $path): self
        {
            return new self("File or directory is not writable (check permissions): '$path'.", ["path" => $path]);
        }

        public static function CopyFailed(string $from, string $to): self
        {
            return new self("Failed to copy '$from' to '$to'.", ["from" => $from, "to" => $to]);
        }

        public static function MoveFailed(string $from, string $to): self
        {
            return new self("Failed to move '$from' to '$to'.", ["from" => $from, "to" => $to]);
        }

        public static function DeleteFailed(string $path): self
        {
            return new self("Failed to delete: '$path'.", ["path" => $path]);
        }

        public static function WriteFailed(string $path): self
        {
            return new self("Failed to write to file: '$path'.", ["path" => $path]);
        }

        public static function ReadFailed(string $path): self
        {
            return new self("Failed to read file: '$path'.", ["path" => $path]);
        }

        public static function CreateDirectoryFailed(string $path): self
        {
            return new self("Failed to create directory: '$path'.", ["path" => $path]);
        }

        public static function InvalidPath(string $path, ?Throwable $previous = null): self
        {
            return new self("Invalid path: '$path'.", ["path" => $path], 0, $previous);
        }
    }
