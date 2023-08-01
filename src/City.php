<?php
    namespace wixnit;

    use wixnit\App\Model;
    use wixnit\Data\Filter;
    use wixnit\Data\Order;

    class City extends Model
	{
		public string $Country = "";
		public string $State = "";
		public string $Stateid = "";

		public string $Name = "";
		public float $Longitude = 0;
		public float $Latitude = 0;

        public static function ByCountry($country): array
        {
            $code = is_a($country, "Country") ? strtolower($country->Code) : strtolower($country);
            return City::Get(new Filter(['country'=>$code]), new Order('name', Order::ASCENDING))->List;
        }

        public static function ByState($state): array
        {
            $id = is_object($state) ? $state->Id : $state;
            return City::Get(new Filter(['stateid'=>$id]), new Order('name', Order::ASCENDING))->List;
        }
	}
