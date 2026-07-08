<?php

    namespace Wixnit\Utilities;

    class Money
    {
        /**
         * @var array<string, string> fallback currency symbols, used when the "intl" extension isn't available
         */
        private static array $symbols = [
            "USD" => "$", "EUR" => "€", "GBP" => "£", "JPY" => "¥", "NGN" => "₦",
            "INR" => "₹", "CNY" => "¥", "KRW" => "₩", "AUD" => "A$", "CAD" => "C$",
        ];

        /**
         * format a numeric amount as a currency string, e.g. Money::Format(1234.5, "USD") -> "$1,234.50".
         * Uses PHP's intl extension for proper locale-aware formatting when it's available,
         * and falls back to a simple symbol + thousands-separator format otherwise.
         * @param float $amount
         * @param string $currency ISO 4217 currency code, e.g. "USD", "EUR", "NGN"
         * @param string $locale
         * @return string
         */
        public static function Format(float $amount, string $currency = "USD", string $locale = "en_US"): string
        {
            if(class_exists("NumberFormatter"))
            {
                $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
                $formatted = $formatter->formatCurrency($amount, $currency);

                if($formatted !== false)
                {
                    return $formatted;
                }
            }

            $symbol = Money::$symbols[strtoupper($currency)] ?? (strtoupper($currency)." ");
            return $symbol.number_format($amount, 2);
        }

        /**
         * parse a formatted money string back into a float, e.g. Money::Parse("$1,234.50") -> 1234.5.
         * Strips currency symbols, thousands separators, and whitespace; keeps digits, a single
         * decimal point, and a leading minus sign for negative amounts.
         * @param string $formatted
         * @return float
         */
        public static function Parse(string $formatted): float
        {
            $isNegative = str_contains($formatted, "-") || (str_starts_with(trim($formatted), "(") && str_ends_with(trim($formatted), ")"));

            $clean = preg_replace('/[^0-9.]/', '', $formatted);
            $value = (float) $clean;

            return $isNegative ? -abs($value) : $value;
        }

        /**
         * convert an amount from one currency to another using a given exchange rate.
         * This library doesn't fetch live rates - pass in the rate you want applied
         * (1 unit of the source currency = $rate units of the target currency).
         * @param float $amount
         * @param float $rate
         * @return float
         */
        public static function Convert(float $amount, float $rate): float
        {
            return Money::Round($amount * $rate);
        }

        /**
         * round a monetary amount to the given number of decimal places (defaults to 2, i.e. cents)
         * @param float $amount
         * @param int $precision
         * @return float
         */
        public static function Round(float $amount, int $precision = 2): float
        {
            return round($amount, $precision);
        }

        /**
         * calculate the tax amount for a given price and tax rate (does not add it to the price -
         * add the result to $amount yourself if you need the tax-inclusive total)
         * @param float $amount
         * @param float $taxRatePercent e.g. 7.5 for 7.5%
         * @return float
         */
        public static function Tax(float $amount, float $taxRatePercent): float
        {
            return Money::Round($amount * ($taxRatePercent / 100));
        }

        /**
         * calculate the discount amount for a given price and discount rate (does not subtract it
         * from the price - subtract the result from $amount yourself if you need the sale price)
         * @param float $amount
         * @param float $discountPercent e.g. 20 for 20% off
         * @return float
         */
        public static function Discount(float $amount, float $discountPercent): float
        {
            return Money::Round($amount * ($discountPercent / 100));
        }
    }
