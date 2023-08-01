<?php

    namespace wixnit\Utilities;

	use Exception;

    class Upload
	{
		public $Type = "";
		public $Extension = "";
		public $Size = 0.0;
		public $Name = "";

		private $Temp = "";
		private $file = null;
		
		function __construct($arg=null)
		{
			if($arg != null)
            {
                if(isset($arg['tmp_name']))
                {
                    if(is_uploaded_file($arg['tmp_name']))
                    {
                        $this->file = $arg;
                        $this->Size = $arg["size"];
                        $this->Name = $arg["name"];
                        $this->Type = $arg["type"];
                        $this->Temp = $arg["tmp_name"];
                        $this->Extension = "";

                        $ext = "";
                        $e = explode(".", $this->Name);
                        if(count($e) > 1)
                        {
                            $this->Extension = $e[count($e) - 1];
                        }
                    }
                    else
                    {
                        try {
                            $log = new Log();
                            $log->Event = "File upload attack detected";
                            $log->Source = $_SERVER['PHP_SELF'];
                            $log->Save();
                        }
                        catch(Exception $e)
                        {
                            //Exception handling
                        }
                    }
                }
                else if(isset($_FILES[$arg]))
                {
                    $this->file = $_FILES[$arg];
                    $this->Size = $_FILES[$arg]["size"];
                    $this->Name = $_FILES[$arg]["name"];
                    $this->Type = $_FILES[$arg]["type"];
                    $this->Temp = $_FILES[$arg]["tmp_name"];
                    $this->Extension = "";

                    $ext = "";
                    $e = explode(".", $this->Name);
                    if(count($e) > 1)
                    {
                        $this->Extension = $e[count($e) - 1];
                    }
                }
            }
			else
            {
                if(!empty($_FILES))
                {

                }
            }
		}
		
		public function Save($directory, $newName=null)
		{
            if($newName == null)
            {
                $newName = $this->Name;
            }
            if($this->file != null)
            {
                move_uploaded_file($this->file['tmp_name'], $directory."/".$newName);
                return $newName;
            }
            else
            {
                return false;
            }
		}

        public function Drop($directory, $newName)
        {
            if($newName == null)
            {
                $newName = $this->Name;
            }
            else if($this->file != null)
            {
                move_uploaded_file($this->file['tmp_name'], $directory."/".$newName);
                return $newName;
            }
            else
            {
                return false;
            }
        }

        public function TypeIs($type)
        {
            if($this->Type == strtolower(trim($type)))
            {
                return true;
            }
            else
            {
                return false;
            }
        }
		
		public function SaveCompressed($directory, $newName, $quality, $minSize=100)
        {
            if($newName == null)
            {
                $newName = $this->Name;
            }
            if($this->file != null)
            {
                $info = getimagesize($this->file['tmp_name']);
                if ($info['mime'] == 'image/jpeg') $image = imagecreatefromjpeg($this->file['tmp_name']);
                elseif ($info['mime'] == 'image/gif') $image = imagecreatefromgif($this->file['tmp_name']);
                elseif ($info['mime'] == 'image/png') $image = imagecreatefrompng($this->file['tmp_name']);
                imagejpeg($image, $directory."/".$newName, $quality);

                return $newName;
            }
            else
            {
                return false;
            }
        }
	}