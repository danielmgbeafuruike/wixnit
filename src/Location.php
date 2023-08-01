<?php

    namespace wixnit\Base;

    use \stdClass;
    use \Exception;

    class Location
    {
        public $Longitude = 0.00;
        public $Latitude = 0.00;
        public $Altitude = 0.00;
        public $Speed = 0.0;

        //propertie that will be sahred based on whether they exist or not
        protected $Country = "";
        protected $State = "";
        protected $City = "";
        protected $Address = "";


        public function getState()
        {
            return $this->State;
        }

        public function getCity()
        {
            return $this->City;
        }

        public function getCountry()
        {
            return $this->Country;
            //$stored = isset($_SESSION['country']) ? $_SESSION['country'] : "";
            //return Country::ByCode("ng");
        }

        public function getAddress()
        {
            return "";
        }

        public static function Find()
        {

        }

        public static function Nowhere()
        {
            $ret = new Location();
            $ret->Longitude = null;
            $ret->Latitude = null;
            return $ret;
        }

        /**
         * @return Location
         */
        public static function getUserLocation()
        {
            $location = new Location();
            return $location;
        }

        public static function FromLongLat($longitude, $latitude=null)
        {
            $ret = new Location();
            
            $ret->Longitude = doubleval($longitude);
            $ret->Latitude = doubleval($latitude);
            return $ret;
        }

        public static function FromJsonString($string)
        {
            $ret = new Location();
            
            if(is_string($string))
            {
                try{
                    $data = json_decode($string);

                    $ret->Longitude = isset($data->Longitude) ? doubleval($data->Longitude) : 0.00;
                    $ret->Latitude = isset($data->Latitude) ? doubleval($data->Latitude) : 0.00;
                    $ret->Altitude = isset($data->Altitude) ? doubleval($data->Altitude) : 0.00;
                    $ret->Speed = isset($data->Speed) ? doubleval($data->Speed) : 0.00;
                    $ret->Country = isset($data->Country) ? doubleval($data->Country) : 0.00;
                    $ret->State = isset($data->State) ? doubleval($data->State) : 0.00;
                    $ret->City = isset($data->City) ? doubleval($data->City) : 0.00;
                    $ret->Address = isset($data->Address) ? doubleval($data->Address) : 0.00;
                }
                catch(Exception $e){}
            } 
            return $ret;
        }

        public static function FromJsonObject($object)
        {
            $ret = new Location();
            
            if(is_object($object))
            {
                try{
                    $ret->Longitude = isset($object->Longitude) ? doubleval($object->Longitude) : 0.00;
                    $ret->Latitude = isset($object->Latitude) ? doubleval($object->Latitude) : 0.00;
                    $ret->Altitude = isset($object->Altitude) ? doubleval($object->Altitude) : 0.00;
                    $ret->Speed = isset($object->Speed) ? doubleval($object->Speed) : 0.00;
                    $ret->Country = isset($object->Country) ? doubleval($object->Country) : 0.00;
                    $ret->State = isset($object->State) ? doubleval($object->State) : 0.00;
                    $ret->City = isset($object->City) ? doubleval($object->City) : 0.00;
                    $ret->Address = isset($object->Address) ? doubleval($object->Address) : 0.00;
                }
                catch(\Exception $e){}
            } 
            return $ret;
        }

        public function Distance(Location $location)
        {
            return $this->measureDistance($this->Latitude, $this->Longitude, $location->Latitude, $location->Longitude);
        }

        public function toString()
        {
            $ret = new stdClass();
            $ret->Longitude = $this->Longitude;
            $ret->Latitude = $this->Latitude;
            $ret->Altitude = $this->Altitude;
            $ret->Speed = $this->Speed;
            $ret->Country = $this->getCountry();
            $ret->State = $this->getState();
            $ret->City = $this->getCity();
            $ret->Address = $this->getAddress();

            return json_encode($ret);
        }

        private function measureDistance($lat1, $lon1, $lat2, $lon2)
        {  // generally used geo measurement function
            $R = 6378.137; // Radius of earth in KM
            $dLat = $lat2 * pi() / 180 - $lat1 * pi() / 180;
            $dLon = $lon2 * pi() / 180 - $lon1 * pi() / 180;
            $a = sin($dLat/2) * sin($dLat/2) +
            cos($lat1 * pi() / 180) * cos($lat2 * pi() / 180) *
            sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $d = $R * $c;
            return $d * 1000; // meters
        }
    }