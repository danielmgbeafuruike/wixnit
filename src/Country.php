<?php
    namespace Wixnit;

	use Wixnit\App\Model;
    use Wixnit\Data\Filter;

    class Country extends Model
	{
		public string $Name = "";
		public string $Code = "";
		public string $Capital = "";
		public string $Phonecode = "";
		public string $Continent = "";
		public string $Currency = "";

		public string $Timezone = "";
		public string $Region = "";

		public string $Subregion = "";
		public string $Code3 = "";
		public string $Language = "";

        public static function FromLocation(Location $location): ?Country
        {
            return Country::ByCode($location->getCountry());
        }

        public static function ByCode($country_code): Country
        {
            $ret = new Country();
            $res = Country::Get(new Filter(['code'=>$country_code]));

            if($res->Count() > 0)
            {
                $ret = $res[0];
            }
            return $ret;
        }
	}
