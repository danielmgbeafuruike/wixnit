<?php

    namespace Wixnit\Validation;

    class Validation
    {
        private array $arg_data = [];

        //protected fields to be used for validating and all
        protected array $values = [];
        protected array $valueValidations = [];


        //public fields that will be populated when test is called
        public array $errorValues = [];
        public string $errorText = "";
        public array $errors = [];




        public function __construct(array $request_data=null) 
        {
            $this->arg_data = ($request_data != null) ? $request_data : $this->arg_data;
        }

        /**
         * add values to be validated
         * @param array $args
         * @return void
         */
        function addValues($args=[])
        {
            $keys = array_keys($args);

            for($i = 0; $i < count($keys); $i++)
            {
                $this->values[] = $keys[$i];
                $this->valueValidations[] = $args[$keys[$i]];
            }
        }

        /**
         * test the values against the validations
         * @return bool
         */
        function test() : bool
        {
            $failed = false;

            for($i = 0; $i < count($this->values); $i++)
            {
                if(count($this->valueValidations) > $i)
                {
                    $vs = explode("|", $this->valueValidations[$i]);

                    //check if the variable is set
                    if(!isset($this->arg_data[$this->values[$i]]))
                    {
                        if(in_array("required", $vs))
                        {
                            $failed = true;
                        }
                        $this->errors[] = [$this->values[$i] => "variable was not set"];
                        $this->errorValues[] = $this->values[$i];
                    }
                    else
                    {
                        /**
                         * Check the variable type
                         */
                        if(in_array("bool", $vs))
                        {
                            if(($this->arg_data[$this->values[$i]] !== true) && ($this->arg_data[$this->values[$i]] !== false) && ($this->arg_data[$this->values[$i]] !== "true") &&
                                ($this->arg_data[$this->values[$i]] !== "false") && ($this->arg_data[$this->values[$i]] !== "0") && ($this->arg_data[$this->values[$i]] !== "1"))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "a boolean value was expected"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("number", $vs))
                        {
                            if(!is_int($this->arg_data[$this->values[$i]]) && !is_double($this->arg_data[$this->values[$i]]) && is_float($this->arg_data[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "a number was expeceted"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("string", $vs) || in_array("text", $vs))
                        {
                            if(!is_string($this->arg_data[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "a string value was expected"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("email", $vs))
                        {
                            if(!filter_var($this->arg_data[$this->values[$i]], FILTER_VALIDATE_EMAIL))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "is not a valid email address"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("phone", $vs))
                        {
                            if(!preg_match('/^[0-9]{11}+$/', $this->arg_data[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "Invalid phone number"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("url", $vs) || in_array("link", $vs) || in_array("website", $vs) || in_array("uri", $vs))
                        {
                            if(!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $this->arg_data[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "is not a valid url"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("plain", $vs))
                        {
                            if(!preg_match("/^[a-zA-Z-' ]*$/", $this->arg_data[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "only alphabets and white spaces are allowed"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("date", $vs))
                        {
                            if((count(explode("/", $this->arg_data[$this->values[$i]])) != 3) && (count(explode("-", $this->arg_data[$this->values[$i]])) != 3))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "invalid date value"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("time", $vs))
                        {
                            if(count(explode(":", $this->arg_data[$this->values[$i]])) != 2)
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "invalid time value"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }

                        /**
                         * validate the variable min and max length
                         */

                        if(in_array("min", $vs))
                        {
                            $min = 0;
                            $minIndex = array_search("min", $vs);
                            if($minIndex !== false && isset($vs[$minIndex + 1]))
                            {
                                $min = (int)$vs[$minIndex + 1];
                            }

                            if(strlen($this->arg_data[$this->values[$i]]) < $min)
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "minimum length is " . $min];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }

                        if(in_array("max", $vs))
                        {
                            $max = 0;
                            $maxIndex = array_search("max", $vs);
                            if($maxIndex !== false && isset($vs[$maxIndex + 1]))
                            {
                                $max = (int)$vs[$maxIndex + 1];
                            }

                            if(strlen($this->arg_data[$this->values[$i]]) > $max)
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "maximum length is " . $max];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                    }
                }
                else
                {
                    $failed = true;
                }
            }
            return !$failed;
        }

        /**
         * get the error text
         * @return string
         */

        public function getErrorText(): string
        {
            $this->errorText = "";
            if(count($this->errors) > 0)
            {
                foreach($this->errors as $error)
                {
                    foreach($error as $key => $value)
                    {
                        $this->errorText .= "$key: $value\n";
                    }
                }
            }
            return $this->errorText;
        }

        /**
         * get the error values
         * @return array
         */

        public function getErrorValues(): array
        {
            return $this->errorValues;
        }

        /**
         * get the errors
         * @return array
         */

        public function getErrors(): array
        {
            return $this->errors;
        }
    }