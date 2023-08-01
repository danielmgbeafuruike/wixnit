<?php

    namespace wixnit\Validation;

    class Validation
    {
        //protected fields to be used for validating and all
        protected array $values = [];
        protected array $valueValidations = [];


        //public fields that will be populated when test is called
        public array $errorValues = [];
        public string $errorText = "";
        public array $errors = [];

        function addValues($args=[])
        {
            $keys = array_keys($args);

            for($i = 0; $i < count($keys); $i++)
            {
                $this->values[] = $keys[$i];
                $this->valueValidations[] = $args[$keys[$i]];
            }
        }

        function test() : bool
        {
            $failed = false;

            for($i = 0; $i < count($this->values); $i++)
            {
                if(count($this->valueValidations) > $i)
                {
                    $vs = explode("|", $this->valueValidations[$i]);

                    //check if the variable is set
                    if(!isset($_REQUEST[$this->values[$i]]))
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
                            if(($_REQUEST[$this->values[$i]] !== true) && ($_REQUEST[$this->values[$i]] !== false) && ($_REQUEST[$this->values[$i]] !== "true") &&
                                ($_REQUEST[$this->values[$i]] !== "false") && ($_REQUEST[$this->values[$i]] !== "0") && ($_REQUEST[$this->values[$i]] !== "1"))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "a boolean value was expected"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("number", $vs))
                        {
                            if(!is_int($_REQUEST[$this->values[$i]]) && !is_double($_REQUEST[$this->values[$i]]) && is_float($_REQUEST[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "a number was expeceted"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("string", $vs))
                        {
                            if(!is_string($_REQUEST[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "a string value was expected"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("email", $vs))
                        {
                            if(!filter_var($_REQUEST[$this->values[$i]], FILTER_VALIDATE_EMAIL))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "is not a valid email address"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("phone", $vs))
                        {
                            if(!preg_match('/^[0-9]{11}+$/', $_REQUEST[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "Invalid phone number"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("url", $vs))
                        {
                            if(!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $_REQUEST[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "is not a valid url"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("plain", $vs))
                        {
                            if(!preg_match("/^[a-zA-Z-' ]*$/", $_REQUEST[$this->values[$i]]))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "only alphabets and white spaces are allowed"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("date", $vs))
                        {
                            if((count(explode("/", $_REQUEST[$this->values[$i]])) != 3) && (count(explode("-", $_REQUEST[$this->values[$i]])) != 3))
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "invalid date value"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }
                        if(in_array("time", $vs))
                        {
                            if(count(explode(":", $_REQUEST[$this->values[$i]])) != 2)
                            {
                                $failed = true;

                                $this->errors[] = [$this->values[$i] => "invalid time value"];
                                $this->errorValues[] = $this->values[$i];
                            }
                        }

                        /**
                         * validate the variable min and max length
                         */
                    }
                }
                else
                {
                    $failed = true;
                }
            }
            return !$failed;
        }
    }