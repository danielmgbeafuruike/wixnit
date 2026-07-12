<?php

namespace Wixnit\Utilities;

use Wixnit\Enum\DBFieldType;
use Wixnit\Interfaces\ISerializable;

/**
 * Changes from the previous version, per review:
 *
 * Bug fixes (behavior was actually wrong before):
 *  - FromAHex(): alpha was never divided by 255 (stored as 0-255 instead
 *    of 0-1) while blue incorrectly WAS divided by 255. Both now match
 *    FromHex()'s (correct) handling.
 *  - init(): opacity was truncated via Convert::ToInt(), collapsing
 *    almost any 0-1 float to 0. Now uses Convert::ToFloat().
 *  - init(): a phantom, undeclared $this->alpha was being set instead
 *    of $this->opacity in the JSON-string branch. Removed.
 *  - init(): Color::FromHex() can return null for a malformed hex
 *    string; that was previously dereferenced unchecked, causing a
 *    fatal error. Now falls back to black/opaque on invalid input,
 *    consistent with this class's existing "never throw on hydration"
 *    behavior.
 *
 * New in this version:
 *  - red/green/blue/opacity are now private, with getRed()/getGreen()/
 *    getBlue()/getOpacity() accessors. Direct writes like
 *    `$color->red = 999;` are no longer possible anywhere in the
 *    codebase — if something depended on that, it needs updating to
 *    use FromRGBO()/withRed()/withGreen()/withBlue()/withOpacity()
 *    instead.
 *  - Channel values are now clamped at construction (0-255 for RGB,
 *    0-1 for opacity) instead of only being clamped defensively inside
 *    toHex()/toAHex(). This also fixes a related bug: an out-of-range
 *    channel (e.g. 500) used to produce a malformed hex string, since
 *    dechex(500) is 3 hex digits and str_pad doesn't truncate.
 *  - withRed()/withGreen()/withBlue() added for symmetry with the
 *    existing withOpacity(), so every channel has an immutable "with"
 *    mutator.
 */
class Color implements ISerializable
{
    private int $red = 0;
    private int $green = 0;
    private int $blue = 0;
    private float $opacity = 1;

    function __construct(mixed $arg = null)
    {
        $this->init($arg);
    }

    /**
     * hydrate the object from parameter
     * @param mixed $arg
     * @return void
     */
    private function init($arg = null): void
    {
        if (is_object($arg)) {
            $this->red = isset($arg->red) ? self::clampChannel(Convert::ToInt($arg->red)) : 0;
            $this->green = isset($arg->green) ? self::clampChannel(Convert::ToInt($arg->green)) : 0;
            $this->blue = isset($arg->blue) ? self::clampChannel(Convert::ToInt($arg->blue)) : 0;
            $this->opacity = isset($arg->opacity) ? self::clampOpacity(Convert::ToFloat($arg->opacity)) : 0;
        } else if (is_string($arg)) {
            if (strpos($arg, "#") !== false) {
                $col = Color::FromHex($arg);

                if ($col !== null) {
                    $this->red = $col->red;
                    $this->green = $col->green;
                    $this->blue = $col->blue;
                    $this->opacity = $col->opacity;
                }
                // else: malformed hex string — leave defaults (black, opaque)
                // rather than the previous behavior of a fatal error.
            } else {
                try {
                    $obj = json_decode($arg);

                    $this->red = isset($obj->red) ? self::clampChannel(Convert::ToInt($obj->red)) : 0;
                    $this->green = isset($obj->green) ? self::clampChannel(Convert::ToInt($obj->green)) : 0;
                    $this->blue = isset($obj->blue) ? self::clampChannel(Convert::ToInt($obj->blue)) : 0;
                    $this->opacity = isset($obj->opacity) ? self::clampOpacity(Convert::ToFloat($obj->opacity)) : 0;
                } catch (\Exception $exception) {
                }
            }
        }
    }

    /**
     * convert the color to hex string
     * @return string
     */
    public function toHex(): string
    {
        $red = str_pad(dechex($this->red), 2, "0", STR_PAD_LEFT);
        $green = str_pad(dechex($this->green), 2, "0", STR_PAD_LEFT);
        $blue = str_pad(dechex($this->blue), 2, "0", STR_PAD_LEFT);
        $opacity = str_pad(dechex((int) round($this->opacity * 255)), 2, "0", STR_PAD_LEFT);

        return "#" . $red . $green . $blue . $opacity;
    }

    /**
     * convert the color to ahex string
     * @return string
     */
    public function toAHex(): string
    {
        $red = str_pad(dechex($this->red), 2, "0", STR_PAD_LEFT);
        $green = str_pad(dechex($this->green), 2, "0", STR_PAD_LEFT);
        $blue = str_pad(dechex($this->blue), 2, "0", STR_PAD_LEFT);
        $opacity = str_pad(dechex((int) round($this->opacity * 255)), 2, "0", STR_PAD_LEFT);

        return "#" . $opacity . $red . $green . $blue;
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    public function getRed(): int
    {
        return $this->red;
    }

    public function getGreen(): int
    {
        return $this->green;
    }

    public function getBlue(): int
    {
        return $this->blue;
    }

    public function getOpacity(): float
    {
        return $this->opacity;
    }

    // -----------------------------------------------------------------
    // Immutable "with" mutators
    // -----------------------------------------------------------------

    /**
     * Get a cloned color object with red set to the given value
     * @param int $red
     * @return Color
     */
    public function withRed(int $red): Color
    {
        return Color::FromRGBO($red, $this->green, $this->blue, $this->opacity);
    }

    /**
     * Get a cloned color object with green set to the given value
     * @param int $green
     * @return Color
     */
    public function withGreen(int $green): Color
    {
        return Color::FromRGBO($this->red, $green, $this->blue, $this->opacity);
    }

    /**
     * Get a cloned color object with blue set to the given value
     * @param int $blue
     * @return Color
     */
    public function withBlue(int $blue): Color
    {
        return Color::FromRGBO($this->red, $this->green, $blue, $this->opacity);
    }

    /**
     * Get a cloned color object with opacity set opacity
     * @param float $opacity
     * @return Color
     */
    public function withOpacity(float $opacity): Color
    {
        return Color::FromRGBO($this->red, $this->green, $this->blue, $opacity);
    }

    #region static method

    /**
     * create Color object from red, green & blue values
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param float $opacity
     */
    public static function FromRGBO(int $red, int $green, int $blue, float $opacity = 1): Color
    {
        $ret = new Color();
        $ret->red = self::clampChannel($red);
        $ret->green = self::clampChannel($green);
        $ret->blue = self::clampChannel($blue);
        $ret->opacity = self::clampOpacity($opacity);
        return $ret;
    }

    /**
     * create Color object from hex string
     * @param string $hex
     * @return Color | null
     */
    public static function FromHex(string $hex): ?Color
    {
        // Remove the hash if present
        $hex = ltrim($hex, '#');

        // Handle short (3 or 4 character) and full (6 or 8 character) hex codes
        if (strlen($hex) == 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
            $a = 1; // Default alpha
        } elseif (strlen($hex) == 4) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
            $a = hexdec(str_repeat(substr($hex, 3, 1), 2)) / 255;
        } elseif (strlen($hex) == 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $a = 1; // Default alpha
        } elseif (strlen($hex) == 8) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $a = hexdec(substr($hex, 6, 2)) / 255;
        } else {
            // Invalid hex color
            return null;
        }

        return Color::FromRGBO($r, $g, $b, $a);
    }

    /**
     * create Color object from ahex string
     * @param string $ahex
     * @return Color | null
     */
    public static function FromAHex(string $ahex): ?Color
    {
        // Remove the hash if present
        $ahex = ltrim($ahex, '#');

        // Handle short (3 or 4 character) and full (6 or 8 character) hex codes
        if (strlen($ahex) == 3) {
            $r = hexdec(str_repeat(substr($ahex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($ahex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($ahex, 2, 1), 2));
            $a = 1; // Default alpha
        } elseif (strlen($ahex) == 4) {
            $a = hexdec(str_repeat(substr($ahex, 0, 1), 2)) / 255;
            $r = hexdec(str_repeat(substr($ahex, 1, 1), 2));
            $g = hexdec(str_repeat(substr($ahex, 2, 1), 2));
            $b = hexdec(str_repeat(substr($ahex, 3, 1), 2));
        } elseif (strlen($ahex) == 6) {
            $r = hexdec(substr($ahex, 0, 2));
            $g = hexdec(substr($ahex, 2, 2));
            $b = hexdec(substr($ahex, 4, 2));
            $a = 1; // Default alpha
        } elseif (strlen($ahex) == 8) {
            $a = hexdec(substr($ahex, 0, 2)) / 255;
            $r = hexdec(substr($ahex, 2, 2));
            $g = hexdec(substr($ahex, 4, 2));
            $b = hexdec(substr($ahex, 6, 2));
        } else {
            // Invalid hex color
            return null;
        }

        return Color::FromRGBO($r, $g, $b, $a);
    }
    #endregion

    #region internals

    private static function clampChannel(int $value): int
    {
        return max(0, min(255, $value));
    }

    private static function clampOpacity(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    #endregion

    #region implement ISerializable methods

    /**
     * get db field type for creating the appropriate db field type for saving the class to db
     * @return DBFieldType
     */
    public function _dbType(): DBFieldType
    {
        return DBFieldType::VARCHAR;
    }

    /**
     * prepare the object for saving to db
     * @return int
     */
    public function _serialize(): string
    {
        return $this->toHex();
    }

    /**
     * re-populate object from data rceived from db
     * @param mixed $data
     * @return void
     */
    public function _deserialize($data): void
    {
        $this->init($data);
    }
    #endregion
}
