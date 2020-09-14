<?php
namespace VIHA\AdvancedGraphs;

use \REDCap as REDCap;
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use \DateTime;
use \DateTimeZone;

class AdvancedGraphs extends \ExternalModules\AbstractExternalModule
{
	private $enabled_projects;
	
    public function __construct()
    {
		global $conn;
        parent::__construct();
		
		// enabled projects (pid and token at this module configuration)
		$arr_projects_api_tokens = $this->getSystemSetting("projects-api-tokens");
		$arr_projects_pids = $this->getSystemSetting("project-pid");
		$arr_projects_tokens = $this->getSystemSetting("project-token");

		$this->enabled_projects = Array();
		foreach($arr_projects_api_tokens as $i => $valid) {
			if ($valid=="true") {
				$this->enabled_projects[$arr_projects_pids[$i]] = $arr_projects_tokens[$i];
			}
		}		
    }
	
	function redcap_module_save_configuration($project_id) {

				$error = "";
				$r_path = $this->getSystemSetting("r-path");
				if(is_array($r_path)){
					$r_path = $r_path[0];
				}
				if ($r_path=="" || !file_exists($r_path)) {
					$error .= "\nInvalid RScript path: $r_path";
				}
				
				$pandocPath = $this->getSystemSetting("pandoc-path");
				if(is_array($pandocPath)){
					$pandocPath = $pandocPath[0];
				}
				if ($pandocPath=="" || !is_dir($pandocPath)) {
					$error .= "\nInvalid Pandoc path: $pandocPath";
				}

				$arr_libPaths = $this->getSystemSetting("r-libraries-path");
				if(is_array($arr_libPaths)){
					$arr_libPaths = $arr_libPaths[0];
				}
				if (count($arr_libPaths)>0) {
					foreach($arr_libPaths as $libPath) {
						if ($libPath=="" || !is_dir($libPath)) {
							$error .= "\nInvalid ath to R libraries: $libPath";
						}
					}
				}	
		if($error!="") {
			die($error);
		}
	}
	
	//redcap_module_link_check_display($project_id, $link): Triggered when each link defined in config.json 
	//is rendered. Override this method and return null if you don't want to 
	//display the link, or modify and return the $link parameter as desired. This method also controls 
	//whether pages will load if users access their URLs directly.
	// $link = Array ( [name] => Advanced Graphs [icon] => gear [url] => http://localhost/redcap_8-4/redcap_v8.5.8/ExternalModules/?prefix=advanced_graphs&page=advanced_graphs [prefix] => advanced_graphs )
	
	//show link only in enabled project (with pid and token at config) AND if page is DataExport 
	//and report_id is in QUERY_STRING
    function redcap_module_link_check_display($project_id, $link)
    {		
		$current_page_is_this_module = strpos($_SERVER["PHP_SELF"],"/ExternalModules/") > -1 && strpos($_SERVER["QUERY_STRING"],"prefix=" . $this->PREFIX) > -1;

		$current_page_is_export_report = strpos($_SERVER["PHP_SELF"],"/DataExport/") > -1 && strpos($_SERVER["QUERY_STRING"],"&report_id=") > -1;
	
		if($link["prefix"]==$this->PREFIX) {			
			$link["target"] = "_blank";
//			if (!($current_page_is_this_module || ($current_page_is_export_report && $this->isEnabledProject($project_id)))) {
			if (!($current_page_is_this_module || $current_page_is_export_report )) {
				$link=null;
			} 
		}
		return $link;
    }
/*	
	function redcap_module_project_enable($version, $project_id) {
		echo "version: " . $version;
		echo "project_id: " . $project_id;
		$version_n_pid = $version . " " . $project_id;
		if($version_n_pid!="") {
			throw new Exception("This is a test");
			//die($version_n_pid);
		}
	}
*/
	function redcap_every_page_top ( int $project_id ) {
		$current_page_is_export_report = strpos($_SERVER["PHP_SELF"],"/DataExport/") > -1 && strpos($_SERVER["QUERY_STRING"],"&report_id=") > -1;
		if($current_page_is_export_report) {
		
		}
		
	}
	
	function isEnabledProject($project_id) {
		return array_key_exists($project_id,$this->enabled_projects);
	}
	
	function getServerAPIurl() {
		return $GLOBALS["redcap_base_url"] . "api/";
	}
	
	function getProjectToken($project_id) {
		$token = null;
		if($this->isEnabledProject($project_id)) {
			$token = $this->enabled_projects[$project_id];
		} 
		return $token;
	}
	
	function getParameter($parameter_name,$default_value="", $method="post") {
		global $_POST,$_GET,$_FILES;
		$result=null;
		if ($method=="post") {
		  if(isset($_POST[$parameter_name])){
			if ($_POST[$parameter_name]!="") {
			  $result= $_POST[$parameter_name];
			} else {
			  $result= $default_value;
			}
		  } else  {
			if(isset($_GET[$parameter_name])){
			  if ($_GET[$parameter_name]!="") {
				$result= $_GET[$parameter_name];
			  } else {
				$result= $default_value;
			  }
			} else {
				if(isset($_FILES[$parameter_name])){
				  if ($_FILES[$parameter_name]["name"]!="") {
					$result= $_FILES[$parameter_name];
				  } else {
					$result= $default_value;
				  }
				} else {
				  $result= $default_value;
				}
			 }
		   }
		} else if ($method=="GET") {
		  if(isset($_GET[$parameter_name])){
			if ($_GET[$parameter_name]!="") {
			  $result= $_GET[$parameter_name];
			} else {
			  $result= $default_value;
			}
		  } else  {
			if(isset($_POST[$parameter_name])){
			  if ($_POST[$parameter_name]!="") {
				$result= $_POST[$parameter_name];
			  } else {
				$result= $default_value;
			  }
			} else {
				if(isset($_FILES[$parameter_name])){
				  if ($_FILES[$parameter_name]!="") {
					$result= $_FILES[$parameter_name];
				  } else {
					$result= $default_value;
				  }
				} else {
				  $result= $default_value;
				}
			 }
		   }
		}
		
		$result=str_replace("\\\"","\"",$result) ;
		$result=str_replace("\\'","'",$result) ;
		$result=str_replace("\\\\'","\\'",$result) ;
		$result=str_replace("\\\\\"","\\\"",$result) ;	
	return $result;
}

}