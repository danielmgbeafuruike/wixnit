<?php
    namespace wixnit\Base;

	use wixnit\App\Model;
    use wixnit\Data\Filter;
    use wixnit\Data\Order;

    class State extends Model
	{
		public string $Code = "";
		public string $Name = "";
		public string $Country = "";
		public array $Subdivition = [];

		public static function FIlterCountry($country): array
        {
            $code = is_a($country, "Country") ? strtolower($country->Code) : strtolower($country);
            return State::Get(new Filter(["country"=>$code]), new Order("name", Order::ASCENDING))->List;
        }
	}
