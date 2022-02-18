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
		
		$recordData = json_decode($recordData,true);
		$dob = false;
		$menarche = false;
		$parity = false;
		$firstBirth = false;
		$tubalLigation = false;
		$endometriosis = false;
		$ocUse = false;
		$prsBc = false;
		$mhtUse = false;
		$weight = false;
		$bmi = false;
		$alcohol = false;
		$height = false;
		
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
			if($thisEvent["tubal_ligation"] != "") {
				$tubalLigation = ($thisEvent["tubal_ligation"] == "1" ? "Y" : "N");
			}
			if($thisEvent["diagnosed_with_endometriosis"] != "") {
				$endometriosis = ($thisEvent["diagnosed_with_endometriosis"] == 1 ? "Y" : "N");
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
				$height *= 2.54;
			}
			
			if($height !== false && $weight !== false && $bmi === false) {
				## Convert height to meters and get BMI to one decimal place
				$bmi = round($weight / ($height / 100) / ($height / 100) * 10 ) / 10;
				
				## If age less than 20, calc BMI percentile and flag if above 85%
				$bmi85Level = [];
				$f = fopen(__DIR__."/bmi_table_cdc.csv","r");
				$headers = fgetcsv($f);
				
				while($row = fgetcsv($f)) {
					$bmi85Level[$row[0]][$row[1]] = $row[11];
				}
				
				$ageMonthsCalc = datediff($dob,date("Y-m-d"),"M");
				if($bmi85Level[2][floor($ageMonthsCalc)] <= $bmi) {
					## TODO flag as exceeding 85 percentile for under 20
				}
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
		
		$meTreeJson = file_get_contents(__DIR__."/metree.json");
		$meTreeJson = json_decode($meTreeJson,true);
		
		$pedigreeData = [];
		$familyId = substr(reset($meTreeJson)["uuid"],0,7);
		
		$alternateParents = [];
		$currentAltId = 0;
		
		$defaultPerson = [
			"FamId" => $familyId,
			"Name" => 0,
			"Target" => 0,
			"IndivID" => 0,
			"FathID" => 0,
			"MothID" => 0,
			"Sex" => 0,
			"MZtwin" => 0,
			"Dead" => 0,
			"Age" => 0,
			"Yob" => 0,
			"BC1" => 0,
			"BC2" => 0,
			"OC" => 0,
			"PRO" => 0,
			"PAN" => 0,
			"Ashkn" => 0,
			"BRCA1" => "0:0",
			"BRCA2" => "0:0",
			"PALB2" => "0:0",
			"ATM" => "0:0",
			"CHEK2" => "0:0",
			"BARD1" => "0:0",
			"RAD51C" => "0:0",
			"BRIP1" => "0:0",
			"ER:PR:HER2:CK14:CK56" => [0,0,0,0,0]
		];
		
		foreach($meTreeJson as $thisRow) {
			$thisPerson = [];
			
			foreach($defaultPerson as $thisField => $thisValue) {
				$thisPerson[$thisField] = $thisValue;
			}
			
			if($thisRow["firstName"] == "") {
				$thisPerson["Name"] = substr($thisRow["uuid"],0,7);
			}
			else {
				$thisPerson["Name"] = substr($thisRow["firstName"],0,7);
			}
			
			if($thisRow["relation"] == "SELF") {
				$thisPerson["Target"] = 1;
				$thisPerson["BRCA1"] = "S:N";
				$thisPerson["BRCA2"] = "S:N";
				$thisPerson["PALB2"] = "S:N";
				$thisPerson["ATM"] = "S:N";
			}
			
			$thisPerson["IndivID"] = substr($thisRow["uuid"],0,7);
			
			if($thisRow["father"] != "") {
				$thisPerson["FathID"] = substr($thisRow["father"],0,7);
			}
			
			if($thisRow["mother"] != "") {
				$thisPerson["MothID"] = substr($thisRow["mother"],0,7);
				
				## If this is a child of the main person
				if($thisPerson["MothID"] == $thisPerson["FamID"] && $thisPerson["birthDate"]) {
					if($firstBirth === false || strtotime($thisRow["birthDate"]) < $firstBirth) {
						$firstBirth = strtotime($thisRow["birthDate"]);
					}
				}
			}
			
			## If father missing
			if($thisPerson["MothID"] !== 0 && $thisPerson["FathID"] === 0) {
				if(!array_key_exists($thisPerson["MothID"],$alternateParents)) {
					$alternateParents[$thisPerson["MothID"]] = "AFATH".$currentAltId;
					$currentAltId++;
				}
				
				$thisPerson["FathID"] = $alternateParents[$thisPerson["MothID"]];
			}
			
			## If mother missing
			if($thisPerson["MothID"] === 0 && $thisPerson["FathID"] !== 0) {
				if(!array_key_exists($thisPerson["FathID"],$alternateParents)) {
					$alternateParents[$thisPerson["FathID"]] = "AMOTH".$currentAltId;
					$currentAltId++;
				}
				
				$thisPerson["MothID"] = $alternateParents[$thisPerson["FathID"]];
			}
			
			$thisPerson["Sex"] = ($thisRow["gender"] == "female" ? "F" : "M");
			
			## Check if person is identical twin and mark MZtwin as 1
			if(is_array($thisRow["multiple"])) {
				foreach($thisRow["multiple"]["identical"] as $thisTwin) {
					if($thisTwin == $thisRow["uuid"]) {
						$thisPerson["MZtwin"] = 1;
					}
				}
			}
			
			if($thisRow["living"] == "Deceased") {
				$thisPerson["Dead"] = 1;
			}
			
			if($thisRow["age"] != "") {
				$thisPerson["Age"] = $thisRow["age"];
			}
			
			if($thisRow["birthDate"] != "") {
				$thisPerson["Yob"] = substr($thisRow["birthDate"],0,4);
			}
			
			foreach($thisRow["conditions"] as $thisCondition) {
				$ageAtCondition = $thisCondition["age"];
				if($thisCondition["ageUnknown"]) {
					$ageAtCondition = "AU";
				}
				
				if($thisCondition["id"] == "breast_cancer") {
					if($thisPerson["BC1"] == 0) {
						$thisPerson["BC1"] = $ageAtCondition;
					}
					elseif($thisPerson["BC2"] == 0) {
						$thisPerson["BC2"] = $ageAtCondition;
					}
				}
				
				if($thisCondition["id"] == "ovarian_cancer") {
					$thisPerson["OC"] = $ageAtCondition;
				}
				
				if($thisCondition["id"] == "prostate_cancer") {
					$thisPerson["PRO"] = $ageAtCondition;
				}
				
				if($thisCondition["id"] == "pancreatic_cancer") {
					$thisPerson["PAN"] = $ageAtCondition;
				}
			}
			
			if(is_array($thisRow["ethnicity"])) {
				foreach($thisRow["ethnicity"] as $thisEthnicity) {
					if($thisEthnicity == "Ashkenazi Jewish") {
						$thisPerson["Ashkn"] = 1;
					}
				}
			}
			
			$thisPerson["ER:PR:HER2:CK14:CK56"] = implode(":",$thisPerson["ER:PR:HER2:CK14:CK56"]);
			
			## Calc age at first birth by comparing oldest child DOB to person DOB
			if($firstBirth) {
				$dobTs = strtotime($dob);
				
				$firstBirth = floor(($firstBirth - $dobTs) / 365.25 / 24 / 60 / 60);
			}
			
			## TODO Haven't found any BRCA or other genetic testing examples in MeTree test data
			
			$pedigreeData[] = $thisPerson;
		}
		
		foreach($alternateParents as $thisParent) {
			$thisPerson = [];
			
			foreach($defaultPerson as $thisField => $thisValue) {
				$thisPerson[$thisField] = $thisValue;
			}
			
			$thisPerson["IndivID"] = $thisParent;
			$thisPerson["Name"] = $thisParent;
			
			if(substr($thisParent,0,5) == "AMOTH") {
				$thisPerson["Sex"] = "F";
			}
			else {
				$thisPerson["Sex"] = "M";
			}
			
			$thisPerson["ER:PR:HER2:CK14:CK56"] = implode(":",$thisPerson["ER:PR:HER2:CK14:CK56"]);
			
			$pedigreeData[] = $thisPerson;
		}
		
		$headers = [
			"FamID","Name","Target","IndivID","FathID",
			"MothID","Sex","MZtwin","Dead","Age","Yob",
			"BC1","BC2","OC","PRO","PAN","Ashkn","BRCA1",
			"BRCA2","PALB2","ATM","CHEK2","RAD51D","RAD51C",
			"BRIP1","ER:PR:HER2:CK14:CK56"
		];
		
		$history = implode("\t",$headers);
		foreach($pedigreeData as $thisPerson) {
			$history .= "\n".implode("\t",$thisPerson);
		}
		$history = "FamID	Name	Target	IndivID	FathID	MothID	Sex	MZtwin	Dead	Age	Yob	BC1	BC2	OC	PRO	PAN	Ashkn	BRCA1	BRCA2	PALB2	ATM	CHEK2	RAD51D	RAD51C	BRIP1	ER:PR:HER2:CK14:CK56
41ebc07	Aundrea	1	41ebc07	e4a2c9a	586ec09	F	0	0	57	1963	0	0	0	0	0	0	S:N	S:N	S:N	S:N	0:0	0:0	0:0	0:0	0:0:0:0:0
41ebc07	e4a2c9a	0	e4a2c9a	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
41ebc07	586ec09	0	586ec09	0	0	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0";
		## Temp data section since some things are broken/missing on survey
//		$history = "FamID	Name	Target	IndivID	FathID	MothID	Sex	MZtwin	Dead	Age	Yob	BC1	BC2	OC	PRO	PAN	Ashkn	BRCA1	BRCA2	PALB2	ATM	CHEK2	RAD51D	RAD51C	BRIP1	ER:PR:HER2:CK14:CK56
//XXXX	pa	0	m21	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
//XXXX	ma	0	f21	0	0	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
//XXXX	me	1	ch1	m21	f21	F	0	0	35 1986 0	0	0	0	0	0	S:N	S:N	S:N	S:N	0:0	0:0	0:0	0:0	0:0:0:0:0";
		
		$dataString = $this->compressRecordData($dob, $menarche, $parity, $firstBirth, $ocUse,
												$mhtUse, $weight, $bmi, $alcohol, $height,
												$tubalLigation, $endometriosis, $history);
		
		error_log($dataString);
		if($dataString !== false) {
			$responseJson = $this->sendRequest($dataString);
			
			$response = json_decode($responseJson, true);
			$foundError = false;
			foreach($response as $responseKey => $responseRow) {
				if(strpos($responseKey,"Error") !== false) {
					$foundError = true;
					error_log("Found Errror: ".var_export($responseRow,true));
				}
			}
			if(!$foundError) {
				error_log(var_export($response,true));
			}
		}
		else {
			error_log("Failed to send");
		}
		
		## Do something to save the response to the record
	}
	
	public function compressRecordData($dob, $menarche, $parity, $firstBirth, $ocUse,
									   $mhtUse, $weight, $bmi, $alcohol, $height,
										$tubalLigation, $endometriosis, $history) {
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
			$dataString .= "##MHT_use=".$mhtUse."\n";
		}
		if($tubalLigation) {
			$dataString .= "##TL=".$tubalLigation."\n";
		}
		if($endometriosis) {
			$dataString .= "##Endo=".$endometriosis."\n";
		}
		
		$dataString .= "##".$history."\n";
		
		return $dataString;
	}
	
	public function sendRequest($pegigreeData) {
		$pegigreeData = str_replace("\r\n","\\n",$pegigreeData);
		$pegigreeData = str_replace("\n","\\n",$pegigreeData);
		$pegigreeData = str_replace("\t","\\t",$pegigreeData);
		
		## TODO user_id needs to be project setting
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