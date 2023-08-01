<?php

    namespace Wixnit\Utilities;

	use Wixnit\Data\DB;

    class File
	{
		public $Id = "";
		public $Created = 0;
		public $Name = "";
		public $Type = "";
		public $Path = "";
		public $Int_name = "";
		public $Abs_path = "";
		public $Alt = "";
		public $Extension = "";
		public $Size = 0;

		function __construct($arg=null)
		{
			if($arg != null)
			{
				$db = DB::GetDB();

				$res = $db->query("SELECT * FROM file WHERE fileid='$arg'");

				if($res->num_rows > 0)
				{
					$row = $res->fetch_assoc();
				
					$this->Id = $row['fileid'];
					$this->Created = new WixDate($row['created']);
					$this->Name = $row['name'];
					$this->Type = $row['type'];
					$this->Path = $row['path'];
					$this->Int_name = $row['int_name'];
					$this->Abs_path = $row['abs_path'];
					$this->Alt = $row['alt'];
					$this->Extension = $row['extension'];
					$this->Size = $row['size'];

					if(file_exists($this->Abs_path."/".$this->Name))
                    {
                        $this->Size = filesize($this->Path."/".$this->Name);
                    }
				}

				if(file_exists($arg))
                {
                    $this->Id = "";
                    $this->Created = new WixDate(time());

                    $n = explode("/", $arg);
                    if(count($n) > 1)
                    {
                        $this->Name = $n[count($n) - 1];
                    }
                    else
                    {
                        $this->Name = $n[0];
                    }

                    $this->Type = "Unknown";
                    $this->Path = "";
                    $this->Int_name = $this->Name;
                    $this->Abs_path = $arg;
                    $this->Alt = "";

                    $e = explode(".", $arg);
                    if(count($e) > 1)
                    {
                        $this->Extension = $e[count($e) - 1];
                    }
                    $this->Size = filesize($arg);
                }

			}
		}

		public function Save()
		{
			$db = DB::GetDB();

			$id = $this->Id;
			$created = time();
			$name = addslashes($this->Name);
			$type = addslashes($this->Type);
			$path = addslashes($this->Path);
			$int_name = addslashes($this->Int_name);
			$abs_path = addslashes($this->Abs_path);
			$alt = addslashes($this->Alt);
			$extension = addslashes($this->Extension);
			$size = floatval($this->Size);

			if($res = $db->query("SELECT fileid FROM file WHERE fileid='$id'")->num_rows > 0)
			{
				$db->query("UPDATE file SET name='$name',type='$type',path='$path',int_name='$int_name',abs_path='$abs_path',alt='$alt',extension='$extension',size='$size' WHERE fileid = '$id'");
			}
			else
			{
				redo: ;
				$id = Random::Generate(16);
				if($db->query("SELECT fileid FROM file WHERE fileid='$id'")->num_rows > 0)
				{
					goto redo;
				}
				$this->Id = $id;
				$db->query("INSERT INTO file(fileid,created,name,type,path,int_name,abs_path,alt,extension,size) VALUES ('$id','$created','$name','$type','$path','$int_name','$abs_path','$alt','$extension','$size')");
			}
		}

		public function Delete()
		{
			$db = DB::GetDB();

			$id = $this->Id;
			$db->query("DELETE FROM file WHERE fileid='$id'");

			if(file_exists($this->Path."/".$this->Name))
            {

            }
		}

		public function Rename($newname)
        {
            return rename($this->Path."/".$this->Name, $this->path."/".$newname);
        }

        public function Exist()
        {
            return file_exists($this->Path."/".$this->Name);
        }

        public function MoveTo($newDir)
        {
            return rename($this->Path."/".$this->Name, $newDir."/".$this->Name);
        }

		public static function Search($term='')
		{
			$db = DB::GetDB();
			$ret = array();
			$i = 0;

			$res = $db->query("SELECT fileid FROM file WHERE name LIKE '%$term%' OR type LIKE '%$term%' OR path LIKE '%$term%' OR int_name LIKE '%$term%' OR abs_path LIKE '%$term%' OR alt LIKE '%$term%' OR extension LIKE '%$term%' OR size LIKE '%$term%'");
			while(($row = $res->fetch_assoc()) != null)
			{
				$ret[$i] = $row['fileid'];
				$i++;
			}
			return File::GroupInitialize($ret);
		}

		public static function Filter($term='', $field='fileid')
		{
			$db = DB::GetDB();
			$ret = array();
			$i = 0;

			$res = $db->query("SELECT fileid FROM file WHERE ".$field." ='$term'");
			while(($row = $res->fetch_assoc()) != null)
			{
				$ret[$i] = $row['fileid'];
				$i++;
			}
			return File::GroupInitialize($ret);
		}

		public static function Order($field='id', $order='DESC')
		{
			$db = DB::GetDB();
			$ret = array();
			$i = 0;

			$res = $db->query("SELECT fileid FROM file ORDER BY ".$field." ".$order."");
			while(($row = $res->fetch_assoc()) != null)
			{
				$ret[$i] = $row['fileid'];
				$i++;
			}
			return File::GroupInitialize($ret);
		}

		public static function GroupInitialize($array=null, $orderBy='id', $order='DESC'): array
        {
			$db = DB::GetDB();
			$ret = array();
			$i = 0;

			$query = "";

			if(is_array($array) === true)
			{
				if(count($array) == 0)
				{
					return $ret;
				}
				else
				{
					for($i = 0; $i < count($array); $i++)
					{
						if($query == "")
						{
							$query = " WHERE Fileid='".$array[$i]."'";
						}
						else
						{
							$query .= " OR Fileid ='".$array[$i]."'";
						}
					}
				}
			}
			$i = 0;
			$res = $db->query("SELECT * FROM file".$query." ORDER BY ".$orderBy." ".$order);
			while(($row = $res->fetch_assoc()) != null)
			{
				$ret[$i] = new File();
				$ret[$i]->Id = $row['fileid'];
				$ret[$i]->Created = new WixDate($row['created']);
				$ret[$i]->Name = $row['name'];
				$ret[$i]->Type = $row['type'];
				$ret[$i]->Path = $row['path'];
				$ret[$i]->Int_name = $row['int_name'];
				$ret[$i]->Abs_path = $row['abs_path'];
				$ret[$i]->Alt = $row['alt'];
				$ret[$i]->Extension = $row['extension'];
				$ret[$i]->Size = $row['size'];
				$i++;
			}
			return $ret;
		}
	}
