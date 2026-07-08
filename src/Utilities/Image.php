<?php

    namespace Wixnit\Utilities;

    use GdImage;
    use Wixnit\Exception\FileException;
    use Wixnit\Exception\ImageException;

    /**
     * Static image manipulation helpers built on the GD extension. Every operation here
     * reads an image from disk, transforms it, and writes the result back out - there's
     * no in-memory image object to juggle between calls.
     */
    class Image
    {
        /**
         * resize an image to fit within the given dimensions, preserving aspect ratio
         * @param string $source
         * @param string $destination
         * @param int $width
         * @param int $height
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Resize(string $source, string $destination, int $width, int $height): bool
        {
            if(($width <= 0) || ($height <= 0))
            {
                throw ImageException::InvalidDimensions($width, $height);
            }

            $img = Image::load($source);
            $srcWidth = imagesx($img);
            $srcHeight = imagesy($img);

            $ratio = min($width / $srcWidth, $height / $srcHeight);
            $newWidth = (int) round($srcWidth * $ratio);
            $newHeight = (int) round($srcHeight * $ratio);

            $resized = Image::blankCanvas($newWidth, $newHeight, $img);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

            return Image::save($resized, $destination);
        }

        /**
         * crop a region out of an image
         * @param string $source
         * @param string $destination
         * @param int $x left offset of the crop region
         * @param int $y top offset of the crop region
         * @param int $width
         * @param int $height
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Crop(string $source, string $destination, int $x, int $y, int $width, int $height): bool
        {
            if(($width <= 0) || ($height <= 0))
            {
                throw ImageException::InvalidDimensions($width, $height);
            }

            $img = Image::load($source);
            $cropped = Image::blankCanvas($width, $height, $img);
            imagecopy($cropped, $img, 0, 0, $x, $y, $width, $height);

            return Image::save($cropped, $destination);
        }

        /**
         * rotate an image by the given number of degrees (clockwise)
         * @param string $source
         * @param string $destination
         * @param float $degrees
         * @param int $backgroundColor RGB packed background color to fill exposed corners with (default: white)
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Rotate(string $source, string $destination, float $degrees, int $backgroundColor = 0xFFFFFF): bool
        {
            $img = Image::load($source);
            //imagerotate's angle is counter-clockwise, negate it so callers can think in clockwise degrees
            $rotated = imagerotate($img, -$degrees, $backgroundColor);

            return Image::save($rotated, $destination);
        }

        /**
         * stamp a watermark image onto another image
         * @param string $source
         * @param string $watermarkPath
         * @param string $destination
         * @param string $position one of "top-left", "top-right", "bottom-left", "bottom-right", "center"
         * @param int $padding pixels of padding from the edge (ignored for "center")
         * @param int $opacityPercent 0 (invisible) to 100 (fully opaque)
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Watermark(string $source, string $watermarkPath, string $destination, string $position = "bottom-right", int $padding = 10, int $opacityPercent = 100): bool
        {
            $img = Image::load($source);
            $mark = Image::load($watermarkPath);

            $imgWidth = imagesx($img);
            $imgHeight = imagesy($img);
            $markWidth = imagesx($mark);
            $markHeight = imagesy($mark);

            switch($position)
            {
                case "top-left":
                    $x = $padding; $y = $padding;
                    break;
                case "top-right":
                    $x = $imgWidth - $markWidth - $padding; $y = $padding;
                    break;
                case "bottom-left":
                    $x = $padding; $y = $imgHeight - $markHeight - $padding;
                    break;
                case "center":
                    $x = (int) (($imgWidth - $markWidth) / 2); $y = (int) (($imgHeight - $markHeight) / 2);
                    break;
                case "bottom-right":
                default:
                    $x = $imgWidth - $markWidth - $padding; $y = $imgHeight - $markHeight - $padding;
                    break;
            }

            imagecopymerge($img, $mark, $x, $y, 0, 0, $markWidth, $markHeight, max(0, min(100, $opacityPercent)));

            return Image::save($img, $destination);
        }

        /**
         * create a square-cropped thumbnail of an image
         * @param string $source
         * @param string $destination
         * @param int $size the width and height of the resulting square thumbnail
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Thumbnail(string $source, string $destination, int $size = 150): bool
        {
            if($size <= 0)
            {
                throw ImageException::InvalidDimensions($size, $size);
            }

            $img = Image::load($source);
            $srcWidth = imagesx($img);
            $srcHeight = imagesy($img);

            //crop to a centered square first, then resize that square down to the target size
            $cropSize = min($srcWidth, $srcHeight);
            $cropX = (int) (($srcWidth - $cropSize) / 2);
            $cropY = (int) (($srcHeight - $cropSize) / 2);

            $square = Image::blankCanvas($cropSize, $cropSize, $img);
            imagecopy($square, $img, 0, 0, $cropX, $cropY, $cropSize, $cropSize);

            $thumb = Image::blankCanvas($size, $size, $img);
            imagecopyresampled($thumb, $square, 0, 0, 0, 0, $size, $size, $cropSize, $cropSize);

            return Image::save($thumb, $destination);
        }

        /**
         * re-save an image at a lower quality to reduce file size (JPEG/WEBP only - PNG uses lossless
         * compression levels instead, which this maps the quality value onto)
         * @param string $source
         * @param string $destination
         * @param int $quality 0 (smallest/worst) to 100 (largest/best)
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Compress(string $source, string $destination, int $quality = 75): bool
        {
            $img = Image::load($source);
            return Image::save($img, $destination, max(0, min(100, $quality)));
        }

        /**
         * convert an image from one format to another (based on each path's file extension)
         * @param string $source
         * @param string $destination
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Convert(string $source, string $destination): bool
        {
            $img = Image::load($source);
            return Image::save($img, $destination);
        }

        /**
         * convert an image to grayscale
         * @param string $source
         * @param string $destination
         * @return bool
         * @throws ImageException|FileException
         */
        public static function Grayscale(string $source, string $destination): bool
        {
            $img = Image::load($source);
            imagefilter($img, IMG_FILTER_GRAYSCALE);
            return Image::save($img, $destination);
        }


        #region private helpers

        /**
         * load an image file into a GD resource, based on its actual content type
         * @param string $path
         * @return GdImage
         * @throws ImageException|FileException
         */
        private static function load(string $path): GdImage
        {
            if(!extension_loaded("gd"))
            {
                throw ImageException::GDNotAvailable();
            }

            if(!File::Exists($path))
            {
                throw FileException::NotFound($path);
            }

            $mime = File::Mime($path);

            $img = match($mime)
            {
                "image/jpeg" => @imagecreatefromjpeg($path),
                "image/png" => @imagecreatefrompng($path),
                "image/gif" => @imagecreatefromgif($path),
                "image/webp" => @imagecreatefromwebp($path),
                "image/bmp" => @imagecreatefrombmp($path),
                default => false,
            };

            if($img === false)
            {
                throw ImageException::LoadFailed($path);
            }
            return $img;
        }

        /**
         * create a blank truecolor canvas, copying over transparency support from a reference image
         * @param int $width
         * @param int $height
         * @param GdImage $referenceImage
         * @return GdImage
         */
        private static function blankCanvas(int $width, int $height, GdImage $referenceImage): GdImage
        {
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
            imagealphablending($canvas, true);

            return $canvas;
        }

        /**
         * save a GD image resource to disk, choosing the encoder based on the destination's file extension
         * @param GdImage $img
         * @param string $destination
         * @param int $quality used for JPEG (0-100) and PNG (mapped to compression level 0-9)
         * @return bool
         * @throws ImageException
         */
        private static function save(GdImage $img, string $destination, int $quality = 90): bool
        {
            $destinationDir = dirname($destination);
            if(!is_dir($destinationDir))
            {
                mkdir($destinationDir, 0777, true);
            }

            $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

            $ret = match($extension)
            {
                "jpg", "jpeg" => imagejpeg($img, $destination, $quality),
                "png" => imagepng($img, $destination, (int) round((100 - $quality) / 11.111)),
                "gif" => imagegif($img, $destination),
                "webp" => imagewebp($img, $destination, $quality),
                default => throw ImageException::UnsupportedFormat($destination),
            };

            if(!$ret)
            {
                throw ImageException::SaveFailed($destination);
            }
            return true;
        }
        #endregion
    }
