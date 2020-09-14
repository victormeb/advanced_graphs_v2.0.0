<?php
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

/*
TODO:
- check user rights over report
- get report metadata and pass it as parameters to markdown

*/

$user_rights = REDCap::getUserRights();
$user_name = array_keys($user_rights)[0];
$pid = 1*$user_rights[$user_name]["project_id"];

//obtain parameters from referrer
$url = $_SERVER["HTTP_REFERER"];
$parts = parse_url($url, PHP_URL_QUERY);
parse_str($parts, $query);

$lf1=isset($query["lf1"]) ? $query["lf1"] : "";
$lf2=isset($query["lf2"]) ? $query["lf2"] : "";
$lf3=isset($query["lf3"]) ? $query["lf3"] : "";

$report_id = isset($query['report_id']) ? 1*$query['report_id'] : 0;

$error = "";


if(!is_numeric($pid) || !is_numeric($report_id) || $report_id<1) {
	if(!is_numeric($pid) ) {
		$error = "missing or invalid pid";
	}
	else {
		$error = "missing or invalid report_id, you need to run and display a report in REDCap to produce Advanced Graphs";
	}
} else {
	if(!$module->isEnabledProject($pid)) {
		$error = "project pid=$pid not included as valid project in the External Module control panel configuration. Check with your REDCap Admin.";
	} else {
		$token = $module->getProjectToken($pid);
		if (!preg_match("/^[A-F|0-9]{32}$/", $token)) {
			$error = "The token $token found at External Module control panel is not valid";			
		} else {

			$data = array(
				'token' => $token,
				'content' => 'project',
				'format' => 'json',
				'returnFormat' => 'json'
			);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $module->getServerAPIurl());
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
			$output = json_decode(curl_exec($ch));
			
			curl_close($ch);
			if(!isset($output->project_id)  ) {
				$error = "API Token invalid. Check with the REDCap Admin";
			}
			else {
				if($output->project_id != $pid ) {
					$error = "This is a different project that does not match the API Token. Check with the REDCap Admin";
				}
				else {
					$dynamic_filter1 = "";
					$dynamic_filter2 = "";
					$dynamic_filter3 = "";
					// get report metadata to validate if user has access right to the report
					$user_has_access_right = false;
					$sql = "SELECT * FROM redcap_reports WHERE project_id = $pid AND report_id = $report_id";

					$result = db_query($sql);
					while($row = mysqli_fetch_array($result,MYSQLI_ASSOC))
					{
						$dynamic_filter1 = $row["dynamic_filter1"];
						$dynamic_filter2 = $row["dynamic_filter2"];
						$dynamic_filter3 = $row["dynamic_filter3"];
					   
					   $user_has_access_right = true; // TO DO  validate
					}
					if (!$user_has_access_right==true) {
						$error = "user has no access to report";
					} else {
						$server_url = $module->getServerAPIurl();
						
						// get system parameters
						// in some cases returns an array instead of string so take first element
										
						$r_path = $module->getSystemSetting("r-path");
						if(is_array($r_path)){
							$r_path = $r_path[0];
						}
						
						$pandocPath = $module->getSystemSetting("pandoc-path");
						if(is_array($pandocPath)){
							$pandocPath = $pandocPath[0];
						}
						if($pandocPath!="") {
							$pandocPath = "Sys.setenv(RSTUDIO_PANDOC='$pandocPath');"; 
						}

						$arr_libPaths = $module->getSystemSetting("r-libraries-path");
						if(is_array($arr_libPaths)){
							$arr_libPaths = $arr_libPaths[0];
						}
						$libPaths = "";
						if (count($arr_libPaths)>0) {
							foreach($arr_libPaths as $libPath) {
								if ($libPaths!="") {
									$libPaths.=", ";
								}
								$libPaths .= "'$libPath'";
							}
							$libPaths = ".libPaths(c($libPaths));";
						}				
								
						$module_physical_path = str_replace("\\","/",$module->getModulePath());
						
						$markdown_file_path = $module_physical_path . "R_Tables_and_Plots.Rmd";				
						
						$output_folder = $module_physical_path . "output";
						
						if (!is_dir ($output_folder) && !mkdir($output_folder)) {
							$error ="Output folder not available.";
						} else {
							
							if(!is_writable($output_folder)) {
								$error="Output folder is not writable";
							} else {
								$output_file_name = $output_folder . "/" . "p_" . $pid . "_r_" . $report_id . "_u_" . $user_name . ".html";
								
								$arr_params = Array("pid" => $pid,
												"reportId" => $report_id,
												"token" => $token,
												"server_url" => $server_url,
												"dynamic_filter1" => $dynamic_filter1,
												"dynamic_filter2" => $dynamic_filter2,
												"dynamic_filter3" => $dynamic_filter3,
												"lf1" => $lf1,
												"lf2" => $lf2,
												"lf3" => $lf3,
												);
								$params = "";
								foreach($arr_params as $key=>$value) {
									if ($params!="") {
										$params.=", ";
									}
									$params .= $key . "='$value'";
								}
								$params = "list($params)";

								$exec_output = "";
								//die('"' . $r_path . '" -e "' . $libPaths . ' ' . $pandocPath . ' rmarkdown::render(\'' . $markdown_file_path . '\', params = ' . $params . ', output_file = \'' . $output_file_name . '\')" 2>&1');
								exec('"' . $r_path . '" -e "' . $libPaths . ' ' . $pandocPath . ' rmarkdown::render(\'' . $markdown_file_path . '\', params = ' . $params . ', output_file = \'' . $output_file_name . '\')" 2>&1', $exec_output);
								//print_r($exec_output);
								// check if the execution was successful to show the file. In other case show error
								// apparently if $exec_output==Array() there were an error
								// if it was ok, the last element of $exec_output array should be 
								// Output created: $output_file_name
								// It needs more testing
								
								if(!$exec_output || !is_array($exec_output) || count($exec_output)==0) {
									$error = "There were an error during R markdown execution.";
								} else {
									if(end($exec_output) != "Output created: $output_file_name") {
										$error="Unexpected output " . utf8_encode(implode("<br/>",$exec_output));
									} else {						
										//read the newly created HTML file and return it as string for web browser to consume
										require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';	
										readfile($output_file_name);
										require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
									}
								}
							}
						}
					}
				}
			}
		}
	}
}

if ($error != "") {
	//TODO: evaluate to log error instead of show it
	require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';	
	echo "<div class='alert danger'><strong>" . $module->getModuleName() . " error: </strong><br>$error</div>";
	require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
} 
?>