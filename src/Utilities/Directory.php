<?php

    namespace Wixnit\Utilities;

    use Wixnit\Exception\FileException;

    /**
     * Static helpers for working with whole directories. For single-file operations
     * (read/write/copy/hash a single file), see the `File` class.
     */
    class Directory
    {
        /**
         * create a directory (and any missing parent directories)
         * @param string $path
         * @param int $permissions
         * @return bool
         * @throws FileException if the directory can't be created
         */
        public static function Create(string $path, int $permissions = 0777): bool
        {
            if(is_dir($path))
            {
                return true;
            }

            if(!mkdir($path, $permissions, true))
            {
                throw FileException::CreateDirectoryFailed($path);
            }
            return true;
        }

        /**
         * recursively delete a directory and everything inside it
         * @param string $path
         * @return bool
         * @throws FileException if the directory doesn't exist or can't be fully removed
         */
        public static function Delete(string $path): bool
        {
            if(!is_dir($path))
            {
                throw FileException::DirectoryNotFound($path);
            }

            Directory::Clean($path);

            if(!rmdir($path))
            {
                throw FileException::DeleteFailed($path);
            }
            return true;
        }

        /**
         * recursively copy a directory (and everything inside it) to a new location
         * @param string $from
         * @param string $to
         * @return bool
         * @throws FileException if the source directory doesn't exist or a file fails to copy
         */
        public static function Copy(string $from, string $to): bool
        {
            if(!is_dir($from))
            {
                throw FileException::DirectoryNotFound($from);
            }

            Directory::Create($to);

            $entries = scandir($from);
            for($i = 0; $i < count($entries); $i++)
            {
                $entry = $entries[$i];
                if(($entry === ".") || ($entry === ".."))
                {
                    continue;
                }

                $fromPath = $from."/".$entry;
                $toPath = $to."/".$entry;

                if(is_dir($fromPath))
                {
                    Directory::Copy($fromPath, $toPath);
                }
                else
                {
                    File::Copy($fromPath, $toPath);
                }
            }
            return true;
        }

        /**
         * move (rename) a directory to a new location
         * @param string $from
         * @param string $to
         * @return bool
         * @throws FileException if the source directory doesn't exist or the move fails
         */
        public static function Move(string $from, string $to): bool
        {
            if(!is_dir($from))
            {
                throw FileException::DirectoryNotFound($from);
            }

            $destinationParent = dirname($to);
            if(!is_dir($destinationParent))
            {
                Directory::Create($destinationParent);
            }

            if(!rename($from, $to))
            {
                throw FileException::MoveFailed($from, $to);
            }
            return true;
        }

        /**
         * calculate the total size (in bytes) of everything inside a directory, recursively
         * @param string $path
         * @return int
         * @throws FileException if the directory doesn't exist
         */
        public static function Size(string $path): int
        {
            if(!is_dir($path))
            {
                throw FileException::DirectoryNotFound($path);
            }

            $total = 0;
            $entries = scandir($path);

            for($i = 0; $i < count($entries); $i++)
            {
                $entry = $entries[$i];
                if(($entry === ".") || ($entry === ".."))
                {
                    continue;
                }

                $fullPath = $path."/".$entry;
                $total += is_dir($fullPath) ? Directory::Size($fullPath) : filesize($fullPath);
            }
            return $total;
        }

        /**
         * list the files directly inside a directory (optionally recursing into subdirectories)
         * @param string $path
         * @param bool $recursive
         * @return array<string> full paths of each file found
         * @throws FileException if the directory doesn't exist
         */
        public static function Files(string $path, bool $recursive = false): array
        {
            if(!is_dir($path))
            {
                throw FileException::DirectoryNotFound($path);
            }

            $ret = [];
            $entries = scandir($path);

            for($i = 0; $i < count($entries); $i++)
            {
                $entry = $entries[$i];
                if(($entry === ".") || ($entry === ".."))
                {
                    continue;
                }

                $fullPath = $path."/".$entry;

                if(is_dir($fullPath))
                {
                    if($recursive)
                    {
                        $ret = array_merge($ret, Directory::Files($fullPath, true));
                    }
                }
                else
                {
                    $ret[] = $fullPath;
                }
            }
            return $ret;
        }

        /**
         * list the subdirectories directly inside a directory (optionally recursing)
         * @param string $path
         * @param bool $recursive
         * @return array<string> full paths of each subdirectory found
         * @throws FileException if the directory doesn't exist
         */
        public static function Directories(string $path, bool $recursive = false): array
        {
            if(!is_dir($path))
            {
                throw FileException::DirectoryNotFound($path);
            }

            $ret = [];
            $entries = scandir($path);

            for($i = 0; $i < count($entries); $i++)
            {
                $entry = $entries[$i];
                if(($entry === ".") || ($entry === ".."))
                {
                    continue;
                }

                $fullPath = $path."/".$entry;

                if(is_dir($fullPath))
                {
                    $ret[] = $fullPath;
                    if($recursive)
                    {
                        $ret = array_merge($ret, Directory::Directories($fullPath, true));
                    }
                }
            }
            return $ret;
        }

        /**
         * delete everything inside a directory, but keep the directory itself
         * @param string $path
         * @return bool
         * @throws FileException if the directory doesn't exist
         */
        public static function Clean(string $path): bool
        {
            if(!is_dir($path))
            {
                throw FileException::DirectoryNotFound($path);
            }

            $entries = scandir($path);
            for($i = 0; $i < count($entries); $i++)
            {
                $entry = $entries[$i];
                if(($entry === ".") || ($entry === ".."))
                {
                    continue;
                }

                $fullPath = $path."/".$entry;

                if(is_dir($fullPath) && !is_link($fullPath))
                {
                    Directory::Clean($fullPath);
                    rmdir($fullPath);
                }
                else
                {
                    unlink($fullPath);
                }
            }
            return true;
        }
    }
