<?php
namespace Vanderbilt\BoadiceaCanrisk;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class BoadiceaCanrisk extends AbstractExternalModule
{
	public function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance ) {
		$recordData = \REDCap::getData([
			"project_id" => $project_id,
			"records" => $record,
			"return_format" => "json"
		]);
		error_log("Starting BOADICEA");
		$recordData = json_decode($recordData,true);
		$dob = false;
		$menarche = false;
		$parity = false;
		$firstBirth = false;
		$ocUse = false;
		$mhtUse = false;
		$weight = false;
		$bmi = false;
		$alcohol = false;
		$height = false;
		$history = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["date_of_birth"] != "") {
				$dob = $thisEvent["date_of_birth"];
			}
			if($thisEvent["age_first_period"] != "") {
				$menarche = $thisEvent["age_first_period"];
			}
			if($thisEvent["how_many_children_do_you_h"] != "") {
				$parity = $thisEvent["how_many_children_do_you_h"];
			}
			if($thisEvent["date_of_birth_child"] != "") {
				if($dob !== false) {
					$firstBirth = floor(datediff($dob,$thisEvent["date_of_birth_child"],"y"));
				}
			}
			if($thisEvent["taken_oral_contraceptive_pill"] != "") {
				if($thisEvent["taken_oral_contraceptive_pill"] == "2") {
					$ocUse = "N";
				}
				else if($thisEvent["taken_oral_contraceptive_pill"] == "1") {
					if($thisEvent["pill_taken_last_two_years"] == "1") {
						$ocUse = "C";
					}
					else if($thisEvent["pill_taken_last_two_years"] == "0") {
						$ocUse = "F";
					}
					switch($thisEvent["how_many_years_have_you_ta"]) {
						case 1:
							$ocUse .= "1";
							break;
						case 2:
							$ocUse .= "3";
							break;
						case 3:
							$ocUse .= "7";
							break;
						case 4:
							$ocUse .= "13";
							break;
						case 5:
							$ocUse .= "15";
							break;
					}
				}
			}
			if($thisEvent["hrt_for_menopause"] != "") {
				switch($thisEvent["hrt_for_menopause"]) {
					case 1:
						$mhtUse = "N";
						break;
					case 2:
						$mhtUse = "F";
						break;
					case 3:
					case 4:
						if($thisEvent["type_of_hrt"] == "1") {
							$mhtUse = "E";
						}
						else {
							$mhtUse = "C";
						}
						break;
				}
			}
			if($thisEvent["current_weight"] != "") {
				$weight = $thisEvent["current_weight"];
				$weight /= 2.2;
			}
			if($thisEvent["height_feet"] != "") {
				$height = $thisEvent["height_feet"] * 12;
				$height += (int)$thisEvent["height_inches"];
				$height *= 0.0254;
			}
			
			if($height !== false && $weight !== false) {
				$bmi = round($weight / $height / $height * 10) / 10;
				$height = round($height * 100);
			}
			
			if($thisEvent["drink_containing_alcohol"] != "") {
				switch($thisEvent["drink_containing_alcohol"]) {
					case 1:
						$freq = 0;
						$alcohol = "0";
						break;
					case 6:
						$alcohol = "NA";
						break;
					case 2:
						$freq = 1/30;
						break;
					case 3:
						$freq = 3/30;
						break;
					case 4:
						$freq = 2.5/7;
						break;
					case 5:
						$freq = 4/7;
						break;
				}
				
				if($alcohol != "NA" && $alcohol !== "0") {
					switch($thisEvent["how_many_drinks_do_you_have"]) {
						case 1:
							$alcohol = "0";
							break;
						case 2:
							$alcohol = $freq * 1.5;
							break;
						case 3:
							$alcohol = $freq * 3.5;
							break;
						case 4:
							$alcohol = $freq * 5.5;
							break;
						case 5:
							$alcohol = $freq * 7.5;
							break;
						case 6:
							$alcohol = $freq * 10;
							break;
						case 7:
							$alcohol = "NA";
							break;
					}
				}
				
				if($alcohol != "NA" && $alcohol != "0") {
					$alcohol = round($alcohol * 10) / 10;
				}
			}
		}
		## Temp data section since some things are broken/missing on survey
		$firstBirth = 25;
		$history = "FamID	Name	Target	IndivID	FathID	MothID	Sex	MZtwin	Dead	Age	Yob	BC1	BC2	OC	PRO	PAN	Ashkn	BRCA1	BRCA2	PALB2	ATM	CHEK2	RAD51D	RAD51C	BRIP1	ER:PR:HER2:CK14:CK56
XXXX	pa	0	m21	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	ma	0	f21	0	0	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	me	1	ch1	m21	f21	F	0	0	35 1986	0	0	0	0	0	0	S:N	S:N	S:N	S:N	0:0	0:0	0:0	0:0	0:0:0:0:0";
		
		$dataString = $this->compressRecordData($dob, $menarche, $parity, $firstBirth, $ocUse,
												$mhtUse, $weight, $bmi, $alcohol, $height,
												$history);
		error_log("Have Data String\n".$dataString);
		if($dataString !== false) {
			$response = $this->sendRequest($dataString);
			error_log(var_export($response,true));
		}
		else {
			error_log("Failed to send");
		}
		
		## Do something to save the response to the record
	}
	
	public function compressRecordData($dob, $menarche, $parity, $firstBirth, $ocUse,
									   $mhtUse, $weight, $bmi, $alcohol, $height,
										$history) {
		if($dob === false || $menarche === false || $parity === false || $weight === false ||
				$height === false || $alcohol === false || $history === false) {
			return false;
		}
		
		$dataString = "##CanRisk 1.0\n".
			"##menarch=$menarche\n".
			"##BMI=$bmi\n".
			"##alcohol=$alcohol\n".
			"##height=$height\n".
			"##parity=$parity\n";
		
		if($firstBirth) {
			$dataString .= "##First_live_birth=".$firstBirth."\n";
		}
		if($ocUse) {
			$dataString .= "##OC_use=".$ocUse."\n";
		}
		if($mhtUse) {
			$dataString .= "##MHT_use".$mhtUse."\n";
		}
		
		$dataString .= "##".$history."\n";
		
		return $dataString;
	}
	
	public function sendRequest($pegigreeData) {
		$pegigreeData = str_replace("\r\n","\\n",$pegigreeData);
		$pegigreeData = str_replace("\n","\\n",$pegigreeData);
		$pegigreeData = str_replace("\t","\\t",$pegigreeData);
		
		$data = '{"mut_freq":"UK","cancer_rates":"UK","user_id":"mcguffk","pedigree_data":"'.$pegigreeData.'"}';
		
		$apiUrl = $this->getProjectSetting("api-url");
		$apiToken = $this->getProjectSetting("auth-token");
		echo $apiToken."<br />";
		$ch = curl_init($apiUrl);
		curl_setopt($ch,CURLOPT_HTTPHEADER, [
			"Authorization: Token ".$apiToken,
			"Content-Type: application/json"
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$output = curl_exec($ch);
		curl_close($ch);
		
		return $output;
	}
}