<?php

    namespace Wixnit\Routing;
    use Wixnit\Interfaces\IRequest;
    use Wixnit\Utilities\Convert;
    use Exception;

    class Request implements IRequest
    {
        private array $Params = [];
        private array $headers = [];
        private string $Type = "POST";

        private bool $rawReturn = false;

        public string $URL = "";

		function __construct($path)
		{
			$this->URL = $path;
		}

        public function AddParameter($name_valuePair, $value = null)
        {
            // TODO: Implement AddParameter() method.

            if(is_a($name_valuePair, "NameValuePair"))
            {
                $this->Params[$name_valuePair->Name] = $name_valuePair->Value;
                return true;
            }
            else
            {
                if($value != null)
                {
                    $this->Params[$name_valuePair] = $value;
                    return true;
                }
            }
        }

        public function AddRange($name_valuePair_array)
        {
            // TODO: Implement AddRange() method.

            if(is_array($name_valuePair_array))
            {
                for($i = 0; $i < count($name_valuePair_array); $i++)
                {
                    if(is_a($name_valuePair_array[$i], "NameValuePair"))
                    {
                        $this->Params[$name_valuePair_array[$i]->Name] = $name_valuePair_array[$i]->Value;
                    }
                }
            }
        }

        public function AddHeader($header)
        {
            array_push($this->headers, $header);
        }

        public function SetType($type='POST')
        {
            if(strtoupper($type) === "POST")
            {
                $this->Type = "POST";
            }
            if(strtoupper($type) === "GET")
            {
                $this->Type = "GET";
            }
        }

        public function returnRawData($status=true)
        {
            $this->rawReturn = Convert::ToBool($status);
        }

        /**
         * @return Response
         * @throws Exception
         */
        public function Execute($preProcess=false)
        {
            // TODO: Implement Execute() method.

			try {
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $this->URL);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->Type);

				if($this->Type === "POST")
                {
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($this->Params));
                }

                if(count($this->headers) > 0)
                {
                    curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
                }

				$resp = curl_exec($curl);
				curl_close($curl);

				if(Convert::ToBool($preProcess))
                {
                    if((is_array(json_decode($resp))) || (is_object(json_decode($resp))))
                    {
                        $ret = json_decode($resp);

                        if((isset($ret->Content)) && (isset($ret->Type)))
                        {
                            $response = new Response($ret->Content, $ret->Type, 200, "JSON");
                        }
                        else
                        {
                            $response = new Response($ret, "UNKNOWN", 200, "JSON");
                        }
                    }
                    else
                    {
                        $ret = $resp;
                        $response = new Response($ret, "UNKNOWN", 200, "TEXT");
                    }
                    return $response;
                }
				else
                {
                    if($this->rawReturn)
                    {
                        return $resp;
                    }
                    else
                    {
                        return new Response($resp, 'TEXT', 200, 'TEXT');
                    }
                }
			}
			catch (Exception $e)
			{
				throw new Exception("Request failed");
			}
        }


        public function GetURL()
        {
            // TODO: Implement GetDomain() method.
            return $this->Path;
        }


        private function BuildPath()
        {
            if($this->Domain != "")
            {
                $this->URL = strtolower(strpos(strtolower($this->Domain . $this->Path), "http://")
                === 0 ? $this->Domain . $this->Path : "http://" . $this->Domain . $this->Path);

                return true;
            }
            else
            {
                return false;
            }
        }
    }