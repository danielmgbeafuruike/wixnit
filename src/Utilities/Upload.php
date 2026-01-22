<?php

    namespace Wixnit\Utilities;

	use Exception;

    class Upload
	{
		public string $type = "";
		public string $extension = "";
		public float $size = 0.0;
		public string $name = "";
		private string $temp = "";
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
                        $this->size = $arg["size"];
                        $this->name = $arg["name"];
                        $this->type = $arg["type"];
                        $this->temp = $arg["tmp_name"];
                        $this->extension = "";

                        $ext = "";
                        $e = explode(".", $this->name);
                        if(count($e) > 1)
                        {
                            $this->extension = $e[count($e) - 1];
                        }
                    }
                    else
                    {
                        
                    }
                }
                else if(isset($_FILES[$arg]))
                {
                    $this->file = $_FILES[$arg];
                    $this->size = $_FILES[$arg]["size"];
                    $this->name = $_FILES[$arg]["name"];
                    $this->type = $_FILES[$arg]["type"];
                    $this->temp = $_FILES[$arg]["tmp_name"];
                    $this->extension = "";

                    $ext = "";
                    $e = explode(".", $this->name);
                    if(count($e) > 1)
                    {
                        $this->extension = $e[count($e) - 1];
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
		
        /**
         * save the uploaded file to a directory
         * @param string $directory
         * @param string|null $newName
         * @return string|bool
         */
		public function save($directory, $newName=null): bool|string
		{
            if($newName == null)
            {
                $newName = $this->name;
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

        /**
         * save the uploaded file to a directory with a new name
         * @param string $directory
         * @param string|null $newName
         * @return string|bool
         */
        public function drop($directory, $newName): bool|string
        {
            if($newName == null)
            {
                $newName = $this->name;
                return $newName;
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

        /**
         * check if the uploaded file type matches the given type
         * @param string $type
         * @return bool
         */
        public function typeIs($type): bool
        {
            if($this->type == strtolower(trim($type)))
            {
                return true;
            }
            else
            {
                return false;
            }
        }
		
        /**
         * save the uploaded file to a directory with compression
         * @param string $directory
         * @param string|null $newName
         * @param int $quality
         * @param int $minSize
         * @return mixed
         */
		public function saveCompressed($directory, $newName, $quality, $minSize=100): mixed
        {
            if($newName == null)
            {
                $newName = $this->name;
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