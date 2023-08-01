<?php

    namespace wixnit\Base;

	use wixnit\App\Model;
    use wixnit\WixnitData\Filter;

    class Currency extends Model
	{
		public string $Name = "";
		public string $Country = "";
		public string $Symbol = "";
		public string $Country_code = "";
		public string $Code = "";
		public float $Value = 0;


        public static function ByName($name): ?Currency
        {
            $res = Currency::Get(new Filter(["country"=>$name]));
            if($res->Count() > 0)
            {
                return $res[0];
            }
            return null;
        }

        public function ConvertTo(Currency $currency, $amount): float
        {
            $toUsd = ($amount / $this->Value);
            return $toUsd * $currency->Value;
        }

        public static function FromLocation(Location $location): Currency
        {
            $code = $location->CountryCode;
            $ret = Currency::ByCountry(Country::ByCode("us"));
            $res = DB::GetDB()->query("SELECT currencyid FROM currency WHERE country_code='$code'");

            if($res->num_rows > 0)
            {
                $row = $res->fetch_assoc();
                $ret = new Currency($row['currencyid']);
            }
            return $ret;
        }

        public static function ByCountry(Country $country): ?Currency
        {
            $code = $country->Code;
            $res = Currency::Get(new Filter(["country_code"=>$code]));

            if($res->Count() > 0)
            {
                return $res[0];
            }
            return null;
        }

        public static function ByCode($code): ?Currency
        {
            $res = Currency::Get(new Filter(["code"=>$code]));

            if($res->Count() > 0)
            {
               return  $res[0];
            }
            return null;
        }
	}
