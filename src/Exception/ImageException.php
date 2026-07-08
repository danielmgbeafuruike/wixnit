<?php

    namespace Wixnit\Exception;

    class ImageException extends WixnitException
    {
        public static function GDNotAvailable(): self
        {
            return new self("The GD extension is not available, but is required to work with images. Enable the 'gd' PHP extension.");
        }

        public static function UnsupportedFormat(string $path): self
        {
            return new self("Unsupported or unrecognized image format: '$path'. Supported formats are JPEG, PNG, GIF, WEBP and BMP.", ["path" => $path]);
        }

        public static function LoadFailed(string $path): self
        {
            return new self("Failed to load image: '$path'. The file may be corrupt or is not a valid image.", ["path" => $path]);
        }

        public static function SaveFailed(string $path): self
        {
            return new self("Failed to save image to: '$path'. Check that the directory exists and is writable.", ["path" => $path]);
        }

        public static function InvalidDimensions(int $width, int $height): self
        {
            return new self("Invalid image dimensions requested: {$width}x{$height}. Width and height must both be greater than 0.", ["width" => $width, "height" => $height]);
        }
    }
