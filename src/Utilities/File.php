<?php

    namespace Wixnit\Utilities;

    use Wixnit\Exception\FileException;

    /**
     * Static helpers for working with individual files on disk. For directory-level
     * operations (creating/copying/cleaning whole folders), see the `Directory` class.
     */
    class File
    {
        /**
         * check whether a file exists (and is actually a file, not a directory)
         * @param string $path
         * @return bool
         */
        public static function Exists(string $path): bool
        {
            return is_file($path);
        }

        /**
         * copy a file to a new location, creating the destination directory if needed
         * @param string $from
         * @param string $to
         * @param bool $overwrite
         * @return bool
         * @throws FileException if the source doesn't exist or the copy fails
         */
        public static function Copy(string $from, string $to, bool $overwrite = true): bool
        {
            if(!File::Exists($from))
            {
                throw FileException::NotFound($from);
            }

            if(!$overwrite && File::Exists($to))
            {
                return false;
            }

            $destinationDir = dirname($to);
            if(!is_dir($destinationDir))
            {
                mkdir($destinationDir, 0777, true);
            }

            if(!copy($from, $to))
            {
                throw FileException::CopyFailed($from, $to);
            }
            return true;
        }

        /**
         * move (rename) a file to a new location, creating the destination directory if needed
         * @param string $from
         * @param string $to
         * @param bool $overwrite
         * @return bool
         * @throws FileException if the source doesn't exist or the move fails
         */
        public static function Move(string $from, string $to, bool $overwrite = true): bool
        {
            if(!File::Exists($from))
            {
                throw FileException::NotFound($from);
            }

            if(!$overwrite && File::Exists($to))
            {
                return false;
            }

            $destinationDir = dirname($to);
            if(!is_dir($destinationDir))
            {
                mkdir($destinationDir, 0777, true);
            }

            if(!rename($from, $to))
            {
                throw FileException::MoveFailed($from, $to);
            }
            return true;
        }

        /**
         * delete a file. Returns false (rather than throwing) if the file doesn't exist,
         * since "the file is gone" is the desired end state either way.
         * @param string $path
         * @return bool
         * @throws FileException if the file exists but can't be deleted
         */
        public static function Delete(string $path): bool
        {
            if(!File::Exists($path))
            {
                return false;
            }

            if(!unlink($path))
            {
                throw FileException::DeleteFailed($path);
            }
            return true;
        }

        /**
         * get a file's extension (without the leading dot), lowercased
         * @param string $path
         * @return string
         */
        public static function Extension(string $path): string
        {
            return strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }

        /**
         * get a file's MIME type by inspecting its actual content (not just its extension)
         * @param string $path
         * @return string
         * @throws FileException if the file doesn't exist
         */
        public static function Mime(string $path): string
        {
            if(!File::Exists($path))
            {
                throw FileException::NotFound($path);
            }

            $mime = mime_content_type($path);
            return ($mime !== false) ? $mime : "application/octet-stream";
        }

        /**
         * get a file's size in bytes
         * @param string $path
         * @return int
         * @throws FileException if the file doesn't exist
         */
        public static function Size(string $path): int
        {
            if(!File::Exists($path))
            {
                throw FileException::NotFound($path);
            }
            return filesize($path);
        }

        /**
         * calculate a file's hash digest (defaults to sha256)
         * @param string $path
         * @param string $algorithm any algorithm accepted by PHP's hash() function, e.g. "md5", "sha1", "sha256"
         * @return string
         * @throws FileException if the file doesn't exist
         */
        public static function Hash(string $path, string $algorithm = "sha256"): string
        {
            if(!File::Exists($path))
            {
                throw FileException::NotFound($path);
            }
            return hash_file($algorithm, $path);
        }

        /**
         * send a file to the browser as a download attachment and stop script execution.
         * @param string $path
         * @param string|null $downloadName the filename the browser should save it as (defaults to the real filename)
         * @return void
         * @throws FileException if the file doesn't exist
         */
        public static function Download(string $path, ?string $downloadName = null): void
        {
            if(!File::Exists($path))
            {
                throw FileException::NotFound($path);
            }

            $downloadName = $downloadName ?? basename($path);

            header("Content-Description: File Transfer");
            header("Content-Type: ".File::Mime($path));
            header("Content-Disposition: attachment; filename=\"".$downloadName."\"");
            header("Content-Transfer-Encoding: binary");
            header("Content-Length: ".File::Size($path));
            header("Cache-Control: no-cache, must-revalidate");

            readfile($path);
            exit;
        }

        /**
         * update a file's modification time, creating an empty file if it doesn't exist yet (like the unix `touch` command)
         * @param string $path
         * @param int|null $time defaults to the current time
         * @return bool
         * @throws FileException if the operation fails
         */
        public static function Touch(string $path, ?int $time = null): bool
        {
            $destinationDir = dirname($path);
            if(!is_dir($destinationDir))
            {
                mkdir($destinationDir, 0777, true);
            }

            $ret = ($time === null) ? touch($path) : touch($path, $time);

            if(!$ret)
            {
                throw FileException::WriteFailed($path);
            }
            return true;
        }

        /**
         * read a file's entire contents as a string
         * @param string $path
         * @return string
         * @throws FileException if the file doesn't exist or can't be read
         */
        public static function Read(string $path): string
        {
            if(!File::Exists($path))
            {
                throw FileException::NotFound($path);
            }

            $contents = file_get_contents($path);

            if($contents === false)
            {
                throw FileException::ReadFailed($path);
            }
            return $contents;
        }

        /**
         * write content to a file, replacing anything already there. Creates the destination
         * directory if it doesn't exist.
         * @param string $path
         * @param string $content
         * @return bool
         * @throws FileException if the write fails
         */
        public static function Write(string $path, string $content): bool
        {
            $destinationDir = dirname($path);
            if(!is_dir($destinationDir))
            {
                mkdir($destinationDir, 0777, true);
            }

            if(file_put_contents($path, $content) === false)
            {
                throw FileException::WriteFailed($path);
            }
            return true;
        }

        /**
         * append content to the end of a file, creating it (and its directory) if it doesn't exist
         * @param string $path
         * @param string $content
         * @return bool
         * @throws FileException if the write fails
         */
        public static function Append(string $path, string $content): bool
        {
            $destinationDir = dirname($path);
            if(!is_dir($destinationDir))
            {
                mkdir($destinationDir, 0777, true);
            }

            if(file_put_contents($path, $content, FILE_APPEND | LOCK_EX) === false)
            {
                throw FileException::WriteFailed($path);
            }
            return true;
        }
    }
