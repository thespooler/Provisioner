<?PHP
/*
This file, when run from the web, creates all the needed packages in the releases folder and also generates http://www.provisioner.net/releases
*/
$master_xml = array();
echo "<pre>";
define("MODULES_DIR", "/chroot/home/tm1000/public_html/repo/endpoint");
define("RELEASE_DIR", "/chroot/home/tm1000/public_html/release");

set_time_limit(0);

foreach (glob(MODULES_DIR."/*", GLOB_ONLYDIR) as $filename) {
	flush_buffers();
    if(file_exists($filename."/brand_data.xml")) {
		$brand_xml = xml2array($filename."/brand_data.xml");
		echo "==============".$brand_xml['data']['brands']['name']."==============\n";
		echo "Found brand_data.xml in ". $filename ." continuing...\n";
		echo "\tAttempting to parse data into array....";
		$excludes = "";
		flush_buffers();
		if(!empty($brand_xml)) {
			if(!empty($brand_xml['data']['brands']['brand_id'])) {
				echo "Looks Good...Moving On\n";
				$key = $brand_xml['data']['brands']['brand_id'];
				$master_xml['brands'][$key]['name'] =  $brand_xml['data']['brands']['name'];
				$master_xml['brands'][$key]['directory'] =  $brand_xml['data']['brands']['directory'];
				create_brand_pkg($master_xml['brands'][$key]['directory'],$brand_xml['data']['brands']['version'],$brand_xml['data']['brands']['name']);
			} else {
				echo "\n\tError with the XML in file (brand_id is blank?): ". $filename."/brand_data.xml";
			}
		} else {
			echo "\n\tError with the XML in file: ". $filename."/brand_data.xml";
		}
		echo "\n\n";
	}
}
copy("/chroot/home/tm1000/public_html/repo/autoload.php","/chroot/home/tm1000/public_html/repo/setup.php");
$endpoint_max[0] = filemtime("/chroot/home/tm1000/public_html/repo/autoload.php");
$endpoint_max[1] = filemtime("/chroot/home/tm1000/public_html/repo/endpoint/base.php");

$endpoint_max = max($endpoint_max);

exec("tar zcf ".RELEASE_DIR."/provisioner_net.tgz --exclude .svn -C /chroot/home/tm1000/public_html/repo/ setup.php endpoint/base.php");

$filename = "/chroot/home/tm1000/public_html/repo/commit_message.txt";
$handle = fopen($filename, "r");
$c_message = fread($handle, filesize($filename));
fclose($handle);

$html = "======= Provisioner.net Library Releases ======= \n == Note: This page is edited by an outside script and can not be edited == \n Latest Commit Message: //".$c_message."//\n<html>";

$fp = fopen(MODULES_DIR.'/master.xml', 'w');
$data = "";
$data .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!--This File is Auto Generated by the Publish Script! -->\n<data>";

$data .= "\n\t<last_modified>".$endpoint_max."</last_modified>";
$data .= "\n\t<package>provisioner_net.tgz</package>";

$html .= "<hr><h3>Provisoner.net Package (Last Modified: ".date('m/d/Y',$endpoint_max)." at ".date("G:i",$endpoint_max).")</h3>";
$html .= "<a href='/release/provisioner_net.tgz'>provisioner_net.tgz</a>";

foreach($master_xml['brands'] as $master_list) {
	$data .= "\n\t<brands>";
	
	$data .= "\n\t\t<name>".$master_list['name']."</name>";
	$data .= "\n\t\t<directory>".$master_list['directory']."</directory>";
	
	$data .= "\n\t</brands>";	
}

$data .= "\n</data>";

fwrite($fp, $data);
fclose($fp);

	echo "\t\t\tCreating JSON file of master.xml\n";
	$xml = simplexml_load_file(MODULES_DIR."/master.xml");
	$json = json_encode($xml);
	$fp2 = fopen(MODULES_DIR."/master.json", 'w');
	fwrite($fp2, $json);
	fclose($fp2);

copy(MODULES_DIR."/master.xml", RELEASE_DIR."/master.xml");

$html .= "<hr><h3>Master List File</h3>";
$html .= "<a href='/release/master.xml'>master.xml</a>";

$html .= "<hr><h3>Brand Packages</h3>".$brands_html;

$html .= "</html>";
echo "\nDone!";
$fp = fopen('/chroot/home/tm1000/provisioner.net/data/pages/releases.txt', 'w');
fwrite($fp, $html);
fclose($fp);

function fix_single_array_keys($array) {
	if((empty($array[0])) AND (!empty($array))) {
		$array_n[0] = $array;
		return($array_n);
	} elseif(!empty($array)) {
		return($array);
	} else {
		return("");
	}	
}

function flush_buffers(){
    ob_end_flush();
    //ob_flush();
    flush();
    ob_start();
}

function create_brand_pkg($rawname,$version,$brand_name) {	
	global $brands_html;
	$version = str_replace(".","_",$version);
	
	$pkg_name = $rawname . "-" . $version;
	
	if(!file_exists(RELEASE_DIR."/".$rawname)) {
		mkdir(RELEASE_DIR."/".$rawname);
		
	}
	$family_list = "\n<!--Below is Auto Generated-->";
	$z = 0;
	foreach (glob(MODULES_DIR."/".$rawname."/*", GLOB_ONLYDIR) as $family_folders) {
		flush_buffers();
		if(file_exists($family_folders."/family_data.xml")) {
			$family_xml = xml2array($family_folders."/family_data.xml");
			echo "\n\t==========".$family_xml['data']['name']."==========\n";
			echo "\tFound family_data.xml in ". $family_folders ."\n";
			$i=0;
			
			$dir_iterator = new RecursiveDirectoryIterator($family_folders."/");
			$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
			
			foreach ($iterator as $family_files) {
				if((!is_dir($family_files)) && (dirname($family_files) != $family_folders."/firmware") && (dirname($family_files) != $family_folders."/json")) {
					if(basename($family_files) != "family_data.xml") {
						$files_array[$i] = filemtime($family_files);
						echo "\t\tParsing File: ".basename($family_files)."|".$files_array[$i]."\n";
						if(pathinfo($family_files, PATHINFO_EXTENSION) == "xml") {
							if(file_exists($family_folders."/json/")) {
								echo "\t\t\tCreating JSON file of ". basename($family_files) ."\n";
								$xml = simplexml_load_file($family_files);
								$json = json_encode($xml);
								$fp2 = fopen($family_folders."/json/".pathinfo($family_files, PATHINFO_FILENAME).".json", 'w');
								fwrite($fp2, $json);
								fclose($fp2);
							} else {
								if (!mkdir($family_folders."/json/")) {
								    echo "\t\t\tFailed to create JSON folder...\n";
								} else {
									echo "\t\t\tCreating JSON file of ". basename($family_files) ."\n";
									$xml = simplexml_load_file($family_files);
									$json = json_encode($xml);
									$fp2 = fopen($family_folders."/json/".pathinfo($family_files, PATHINFO_FILENAME).".json", 'w');
									fwrite($fp2, $json);
									fclose($fp2);
								}	
							}
						}
						$i++;
					}
				} 
			}
			
			$family_max = max($files_array);
			$family_max_array[$z] = $family_max;
			echo "\t\t\tTotal Family Timestamp: ". $family_max ."\n";
						
			if(file_exists($family_folders."/firmware")) {
				echo "\t\tFound Firmware Folder in ".$family_xml['data']['directory']."\n";
				echo "\t\t\tCreating Firmware Package\n";
				flush_buffers();
				exec("tar zcf ".RELEASE_DIR."/".$rawname."/".$family_xml['data']['directory']."_firmware.tgz --exclude .svn -C ".$family_folders." firmware");
				$firmware_md5 = md5_file(RELEASE_DIR."/".$rawname."/".$family_xml['data']['directory']."_firmware.tgz");
				$x=0;
				foreach (glob($family_folders."/firmware/*") as $firmware_files) {
					flush_buffers();
					if(!is_dir($firmware_files)) {
						$firmware_files_array[$x] = filemtime($firmware_files);
						echo "\t\t\t\tParsing File: ".basename($firmware_files)."|".$firmware_files_array[$x]."\n";
						$x++;
					}
				}
				
				$firmware_max = max($firmware_files_array);
                echo "\t\t\t\t\tTotal Firmware Timestamp: ". $firmware_max ."\n";
				
				echo "\t\t\tPackage MD5 SUM: ".$firmware_md5."\n";
				
				echo "\t\t\tAdding Firmware Package Information to family_data.xml File\n";
				
				if($firmware_max > $family_max) {
					echo "\t\t\tFirmware Timestamp is newer than Family Timestamp, updating Family Timestamp to match\n";
					$family_max = $firmware_max;
				}
                
				$fp = fopen($family_folders."/family_data.xml", 'r');
				$contents = fread($fp, filesize($family_folders."/family_data.xml"));
				fclose($fp);
				
				$pattern = "/<firmware_ver>(.*?)<\/firmware_ver>/si";
				$parsed = "<firmware_ver>".$firmware_max."</firmware_ver>";
				$contents = preg_replace($pattern, $parsed, $contents, 1);
				
				$pattern = "/<firmware_md5sum>(.*?)<\/firmware_md5sum>/si";
				$parsed = "<firmware_md5sum>".$firmware_md5."</firmware_md5sum>";
				$contents = preg_replace($pattern, $parsed, $contents, 1);

				$pattern = "/<firmware_pkg>(.*?)<\/firmware_pkg>/si";
				$parsed = "<firmware_pkg>".$family_xml['data']['directory']."_firmware.tgz</firmware_pkg>";
				$contents = preg_replace($pattern, $parsed, $contents, 1);

				
				$fp = fopen($family_folders."/family_data.xml", 'w');
				fwrite($fp, $contents);
				fclose($fp);
				
				if(file_exists($family_folders."/family_data.xml")) {
					echo "\t\t\tCreating JSON file of family_data.xml\n";
					$xml = simplexml_load_file($family_folders."/family_data.xml");
					$json = json_encode($xml);
					$fp2 = fopen($family_folders."/family_data.json", 'w');
					fwrite($fp2, $json);
					fclose($fp2);
				} else {
					if (!mkdir($family_folders."/json/")) {
					    echo "\t\t\tFailed to create JSON folder...\n";
					} else {
						echo "\t\t\tCreating JSON file of family_data.xml\n";
						$xml = simplexml_load_file($family_folders."/family_data.xml");
						$json = json_encode($xml);
						$fp2 = fopen($family_folders."/family_data.json", 'w');
						fwrite($fp2, $json);
						fclose($fp2);
					}	
				}
			}
			
			$z++;
			
			echo "\tComplete..Continuing..\n";
            
			$family_list .= "
			<family>
				<name>".$family_xml['data']['name']."</name>
				<directory>".$family_xml['data']['directory']."</directory>
				<version>".$family_xml['data']['version']."</version>
				<description>".fix_single_array_keys($family_xml['data']['description'])."</description>
				<changelog>".fix_single_array_keys($family_xml['data']['changelog'])."</changelog>
				<id>".$family_xml['data']['id']."</id>
				<last_modified>".$family_max."</last_modified>
			</family>";
	    }
	}
	$family_list .= "\n<!--End Auto Generated-->\n";
	
	echo "\n\t==========".$brand_name."==========\n";
	echo "\tCreating Completed Package\n";
	exec("tar zcf ".RELEASE_DIR."/".$rawname."/".$pkg_name.".tgz --exclude .svn --exclude firmware -C ".MODULES_DIR." ".$rawname);
	$brand_md5 = md5_file(RELEASE_DIR."/".$rawname."/".$pkg_name.".tgz");
	
	$fp = fopen(MODULES_DIR."/".$rawname."/brand_data.xml", 'r');
	$contents = fread($fp, filesize(MODULES_DIR."/".$rawname."/brand_data.xml"));
	fclose($fp);
	
	$pattern = "/<family_list>(.*?)<\/family_list>/si";
	$parsed = "<family_list>".$family_list."\n\t\t</family_list>";
	$contents = preg_replace($pattern, $parsed, $contents, 1);
	
	$pattern = "/<md5sum>(.*?)<\/md5sum>/si";
	$parsed = "<md5sum>".$family_md5."</md5sum>";
	$contents = preg_replace($pattern, $parsed, $contents, 1);
	
	$pattern = "/<package>(.*?)<\/package>/si";
	$parsed = "<package>".$pkg_name.".tgz</package>";
	$contents = preg_replace($pattern, $parsed, $contents, 1);
	
	$i=0;
	foreach (glob(MODULES_DIR."/".$rawname."/*") as $brand_files) {
		if((!is_dir($brand_files)) AND (basename($brand_files) != "brand_data.xml")) {
			$brand_files_array[$i] = filemtime($brand_files);
			echo "\t\tParsing File: ".basename($brand_files)."|".$brand_files_array[$i]."\n";
			$i++;
		}
	}
	
	$brand_max = max($brand_files_array);
	echo "\t\t\tTotal Brand Timestamp: ".$brand_max."\n";
	echo "\t\tPackage MD5 SUM: ".$brand_md5."\n";
	
	
	$pattern = "/<last_modified>(.*?)<\/last_modified>/si";
	$parsed = "<last_modified>".$brand_max."</last_modified>";
	$contents = preg_replace($pattern, $parsed, $contents, 1);
	
	$pattern = "/<md5sum>(.*?)<\/md5sum>/si";
	$parsed = "<md5sum>".$brand_md5."</md5sum>";
	$contents = preg_replace($pattern, $parsed, $contents, 1);
	
	$fp = fopen(MODULES_DIR."/".$rawname."/brand_data.xml", 'w');
	fwrite($fp, $contents);
	fclose($fp);
	

			echo "\t\t\tCreating JSON file of brand_data.xml\n";
			$xml = simplexml_load_file(MODULES_DIR."/".$rawname."/brand_data.xml");
			$json = json_encode($xml);
			$fp2 = fopen(MODULES_DIR."/".$rawname."/brand_data.json", 'w');
			fwrite($fp2, $json);
			fclose($fp2);
	
	copy(MODULES_DIR."/".$rawname."/brand_data.xml", RELEASE_DIR."/".$rawname."/".$rawname.".xml");
	
	$temp = max($family_max_array);
	$brand_max = max($brand_max,$temp);
	
	$brands_html .= "<h4>".$rawname." (Last Modified: ".date('m/d/Y',$brand_max)." at ".date("G:i",$brand_max).")</h4>";
	$brands_html .= "XML File: <a href='/release/".$rawname."/".$rawname.".xml'>".$rawname.".xml</a><br/>";
	$brands_html .= "Package File: <a href='/release/".$rawname."/".$pkg_name.".tgz'>".$pkg_name.".tgz</a><br/>";
	echo "\tComplete..Continuing..\n";
}

function xml2array($url, $get_attributes = 1, $priority = 'tag')
{
    $contents = "";
    if (!function_exists('xml_parser_create'))
    {
        return array ();
    }
    $parser = xml_parser_create('');
    if (!($fp = @ fopen($url, 'rb')))
    {
        return array ();
    }
    while (!feof($fp))
    {
        $contents .= fread($fp, 8192);
    }
    fclose($fp);
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values)
        return; //Hmm...
    $xml_array = array ();
    $parents = array ();
    $opened_tags = array ();
    $arr = array ();
    $current = & $xml_array;
    $repeated_tag_index = array (); 
    foreach ($xml_values as $data)
    {
        unset ($attributes, $value);
        extract($data);
        $result = array ();
        $attributes_data = array ();
        if (isset ($value))
        {
            if ($priority == 'tag')
                $result = $value;
            else
                $result['value'] = $value;
        }
        if (isset ($attributes) and $get_attributes)
        {
            foreach ($attributes as $attr => $val)
            {
                if ($priority == 'tag')
                    $attributes_data[$attr] = $val;
                else
                    $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
            }
        }
        if ($type == "open")
        { 
            $parent[$level -1] = & $current;
            if (!is_array($current) or (!in_array($tag, array_keys($current))))
            {
                $current[$tag] = $result;
                if ($attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                $current = & $current[$tag];
            }
            else
            {
                if (isset ($current[$tag][0]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                { 
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    ); 
                    $repeated_tag_index[$tag . '_' . $level] = 2;
                    if (isset ($current[$tag . '_attr']))
                    {
                        $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                        unset ($current[$tag . '_attr']);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                $current = & $current[$tag][$last_item_index];
            }
        }
        elseif ($type == "complete")
        {
            if (!isset ($current[$tag]))
            {
                $current[$tag] = $result;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                if ($priority == 'tag' and $attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
            }
            else
            {
                if (isset ($current[$tag][0]) and is_array($current[$tag]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    if ($priority == 'tag' and $get_attributes and $attributes_data)
                    {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                {
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    ); 
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $get_attributes)
                    {
                        if (isset ($current[$tag . '_attr']))
                        { 
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset ($current[$tag . '_attr']);
                        }
                        if ($attributes_data)
                        {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                }
            }
        }
        elseif ($type == 'close')
        {
            $current = & $parent[$level -1];
        }
    }
    return ($xml_array);
}
?>