<?php
namespace Vanderbilt\BoadiceaCanrisk;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class BoadiceaCanrisk extends AbstractExternalModule
{
	private static $recordCache = [];
	
	public function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance ) {
		$recordData = $this->getRecordData($project_id, $record);
		
		list($age,$dob, $enrolledAge) = $this->getPatientAgeAndDOB($recordData);
		
		## Run youth BMI calc if less than 20 years old
		if($age < 20) {
			$this->calcJuvenileBmiPercentile($project_id, $record);
		}
		## Only run CanRisk calculations if adult
		if($enrolledAge >= 18) {
			$this->runBoadiceaPush($project_id,$record);
		}
		
		$chdPrs = false;
		$bcPrs = false;
		foreach($recordData as $thisEvent) {
			if($thisEvent["module_chd_prs"] !== "" && $thisEvent["module_chd_prs"] !== NULL) {
				$chdPrs = $thisEvent["module_chd_prs"];
			}
			if($thisEvent["module_breast_cancer_prs"] !== "" && $thisEvent["module_breast_cancer_prs"] !== NULL) {
				$bcPrs = $thisEvent["module_breast_cancer_prs"];
			}
		}
		
		## Parse Broad data to save PRS Scores
		if($chdPrs === false || $bcPrs === false) {
			$this->pullBroadDataIntoRecord($project_id,$record);
		}
		
		if($age >= 40) {
			$this->runCHDCalc($project_id,$record);
		}
	}
	
	public function getRecordData($project_id, $record) {
		if(!array_key_exists($record,self::$recordCache)) {
			$recordData = \REDCap::getData([
				"project_id" => $project_id,
				"records" => $record,
				"return_format" => "json"
			]);
			
			self::$recordCache[$record] = json_decode($recordData,true);
		}
		
		return self::$recordCache[$record];
	}
	
	public function getPatientAgeAndDOB($recordData) {
		$dob = false;
		$enrolledAge = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["date_of_birth"] != "") {
				$dob = $thisEvent["date_of_birth"];
			}
			if($thisEvent["date_of_birth_child"] != "") {
				$dob = $thisEvent["date_of_birth_child"];
			}
			if($thisEvent["age"] != "") {
				$enrolledAge = $thisEvent["age"];
			}
		}
		
		$age = datediff($dob,date("Y-m-d"),"y");
		return [$age, $dob, $enrolledAge];
	}
	
	public function getPatientSex($recordData) {
		$sex = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["sex_at_birth"] != "") {
				$sex = $thisEvent["sex_at_birth"];
			}
		}
		
		return $sex;
	}
	
	## Only use the instance with [metree_import_complete] == 2
	public function findCompletedMeTree($project_id, $record) {
		$recordData = $this->getRecordData($project_id, $record);
		$meTreeFile = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["metree_import_complete"] == "2") {
				$meTreeFile = (int)$thisEvent["metree_import_json_file"];
			}
		}
		
		$meTreeData = false;
		
		if($meTreeFile) {
			$q = $this->query("SELECT *
					FROM redcap_edocs_metadata
					WHERE doc_id = ?
						AND project_id = ?",
					[$meTreeFile,$project_id]);
			
			while($row = db_fetch_assoc($q)) {
				$fileName = $row["stored_name"];
				$meTreeData = file_get_contents(EDOC_PATH.$fileName);
			}
		}
		
		return $meTreeData;
	}
	
	## Only use the instance with [invitae_import_complete] == 2
	public function findCompletedInvitae($project_id, $record) {
		$recordData = $this->getRecordData($project_id, $record);
		$invitaeFile = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["invitae_import_complete"] == "2") {
				$invitaeFile = (int)$thisEvent["invitae_import_json_file"];
			}
		}
		
		$invitaeData = false;
		
		if($invitaeFile) {
			$q = $this->query("SELECT *
					FROM redcap_edocs_metadata
					WHERE doc_id = ?
						AND project_id = ?",
				[$invitaeFile,$project_id]);
			
			while($row = db_fetch_assoc($q)) {
				$fileName = $row["stored_name"];
				$invitaeData = file_get_contents(EDOC_PATH.$fileName);
			}
		}
		
		return $invitaeData;
	}
	
	## Only use the instance with [broad_ordering_complete] == 2
	public function findCompletedBroad($project_id, $record) {
		$recordData = $this->getRecordData($project_id, $record);
		$broadFile = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["broad_ordering_complete"] == "2") {
				$broadFile = (int)$thisEvent["broad_import_json_file"];
			}
		}
		
		$broadData = false;
		
		if($broadFile) {
			$q = $this->query("SELECT *
					FROM redcap_edocs_metadata
					WHERE doc_id = ?
						AND project_id = ?",
				[$broadFile,$project_id]);
			
			while($row = db_fetch_assoc($q)) {
				$fileName = $row["stored_name"];
				$broadData = file_get_contents(EDOC_PATH.$fileName);
			}
		}
		
		return $broadData;
	}
	
	public function pullBroadDataIntoRecord($project_id, $record) {
		$broadData = $this->findCompletedBroad($project_id,$record);
		
		$broadData = json_decode($broadData,true);
		
		if(empty($broadData)) {
			return false;
		}
		foreach($broadData["condition_results"] as $thisCondition) {
			if($thisCondition["condition"]["display"] == "coronary heart disease") {
				$prsScore = $thisCondition["prs_score"];
				$dataToSave = [
					$this->getProject()->getRecordIdField() => $record,
					"module_chd_prs" => $prsScore
				];
				$results = \REDCap::saveData([
					"dataFormat" => "json",
					"data" => json_encode([$dataToSave]),
					"project_id" => $project_id
				]);
				
				if($results["errors"] && count($results["errors"]) > 0) {
					error_log("Save data error: ".var_export($results,true));
				}
			}
			if($thisCondition["condition"]["display"] == "breast cancer") {
				$prsScore = $thisCondition["prs_score"];
				$dataToSave = [
					$this->getProject()->getRecordIdField() => $record,
					"module_breast_cancer_prs" => $prsScore
				];
				$results = \REDCap::saveData([
					"dataFormat" => "json",
					"data" => json_encode([$dataToSave]),
					"project_id" => $project_id
				]);
				
				if($results["errors"] && count($results["errors"]) > 0) {
					error_log("Save data error: ".var_export($results,true));
				}
			}
		}
	}
	
	
	public function runCHDCalc($project_id, $record) {
		$recordData = $this->getRecordData($project_id,$record);
		$prsScore = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["module_chd_prs"] !== "" && $thisEvent["module_chd_prs"] !== NULL) {
				$prsScore = $thisEvent["module_chd_prs"];
			}
		}
		list($age,$dob,$enrolledAge) = $this->getPatientAgeAndDOB($recordData);
		
		## TODO, only run if high risk flag (polygenic risk, monogenic risk, family history risk)

		## Run the additional calculations if we were able to find a PRS Score
		if($prsScore !== "" && $prsScore !== false && $age >= 40) {
			$sex = false;
			$race = false;
			$chol = false;
			$hdl = false;
			$sbp = false;
			$hyper = false;
			$diabetes = -1;
			$smoking = -1;
			
			foreach($recordData as $thisEvent) {
				if($thisEvent["sex_at_birth"] != "") {
					$sex = (($thisEvent["sex_at_birth"] == "1" ? "F" : ($thisEvent["sex_at_birth"] == "2" ? "M" : false)));
				}
				if($thisEvent["race_at_enrollment___1"] !== "") {
					if($thisEvent["race_at_enrollment___3"] == "1") {
						$race = "AA";
					}
					elseif($thisEvent["race_at_enrollment___4"] == "1") {
						$race = "HIS";
					}
					elseif($thisEvent["race_at_enrollment___7"] == "1") {
						$race = "EUR";
					}
					else {
						$race = "OTHER";
					}
				}
				if($thisEvent["totalcholest_value_most_recent"] !== "") {
					$chol = $thisEvent["totalcholest_value_most_recent"];
				}
				if($thisEvent["hdl_value_most_recent"] !== "") {
					$hdl = $thisEvent["hdl_value_most_recent"];
				}
				if($thisEvent["sbp_value_most_recent"] !== "") {
					$sbp = $thisEvent["sbp_value_most_recent"];
				}
				if($thisEvent["meds_lower_bp_2"] !== "") {
					$hyper = $thisEvent["meds_lower_bp_2"];
				}
				if($thisEvent["type_1_diabetes___1"] !== "") {
					$diabetes = ($diabetes === true) || ($thisEvent["type_1_diabetes___1"] == "1");
				}
				if($thisEvent["type_1_diabetes_3___1"] !== "") {
					$diabetes = ($diabetes === true) || ($thisEvent["type_1_diabetes_3___1"] == "1");
				}
				if($thisEvent["type_2_diabetes___1"] !== "") {
					$diabetes = ($diabetes === true) || ($thisEvent["type_2_diabetes___1"] == "1");
				}
				if($thisEvent["type_2_diabetes_3___1"] !== "") {
					$diabetes = ($diabetes === true) || ($thisEvent["type_2_diabetes_3___1"] == "1");
				}
				if($thisEvent["smoked_100_more_cigarettes"] !== "") {
					$smoking = ($thisEvent["smoked_100_more_cigarettes"] == "1" && ($thisEvent["now_smoke"] == "1" || $thisEvent["now_smoke"] == "2"));
				}
			}
			
			list($cs,$is) = $this->calculateChdPrs($age,$sex,$race,$chol,$hdl,$sbp,$hyper,$diabetes,$smoking,$prsScore);
			
			$dataToSave = [
				$this->getProject()->getRecordIdField() => $record,
				"module_chd_int_score" => $is,
				"module_chd_clinic_score" => $cs
			];
			$results = \REDCap::saveData([
				"dataFormat" => "json",
				"data" => json_encode([$dataToSave]),
				"project_id" => $project_id
			]);
			
			if($results["errors"] && count($results["errors"]) > 0) {
				error_log("Save data error: ".var_export($results,true));
			}
			
		}
		else {
//			error_log("Doesn't qualify for CHD: $age ~ $prsScore");
		}
	}
	
	public function calculateChdPrs($age,$sex,$race,$chol,$hdl,$sbp,$hyper,$diabetes,$smoking,$prsScore) {
		
		## If we have all the data, run the calc and save to REDCap
		if(($age !== false) && ($sex !== false) && ($race !== false) &&
				($chol !== false) && ($hdl !== false) && ($sbp !== false) &&
				($diabetes !== -1) && ($smoking !== -1) && $prsScore !== false) {
			$smoking = $smoking ? 1 : 0;
			$diabetes = $diabetes ? 1 : 0;
			$values = 		 [log($age), pow(log($age),2),log($chol),log($age) * log($chol),log($hdl),log($age)*log($hdl),
				($hyper ? log($sbp) : 0),log($age) * ($hyper ? log($sbp) : 0),($hyper ? 0 : log($sbp)),
				log($age) * ($hyper ? 0 : log($sbp)),$smoking,log($age) * $smoking,$diabetes];

			if($sex == "M" && $race == "AA") {
				$coefficient = [2.469, 0, 0.302, 0, -0.307, 0, 1.916, 0, 1.809, 0, 0.549, 0, 0.645];
				$mean = 19.54;
				$survival = 0.8954;
			}
			elseif($sex == "M") {
				$coefficient = [12.344, 0, 11.853, -2.664, -7.990, 1.769, 1.797, 0, 1.764, 0, 7.837, -1.795, 0.658];
				$mean = 61.18;
				$survival = 0.9144;
			}
			elseif($sex == "F" && $race == "AA") {
				$coefficient = [17.114, 0, 0.940, 0, -18.920, 4.475, 29.291, -6.432, 27.820, -6.087, 0.691, 0, 0.874];
				$mean = 86.61;
				$survival = 0.9533;
			}
			else {
				$coefficient = [-29.799, 4.884, 13.540, -3.114, -13.578, 3.149, 2.019, 0, 1.957, 0, 7.574, -1.665, 0.661];
				$mean = -29.18;
				$survival = 0.9665;
			}
			
			$raceHr = [
				"AA" => 1.18,
				"HIS" => 1.39,
				"EUR" => 1.60,
				"OTHER" => 1.60
			];
			
			$hr = $raceHr[$race];
			
			$product = array_map(function($x,$y) {return $x * $y;},$coefficient,$values);
//			echo "\n<br />Prod: ".var_export($product,true)." Mean: $mean Surv: $survival\n<br />";
			$cs = 1 - (pow($survival,exp(array_sum($product) - $mean)));
			$is = 1 - (pow($survival,exp(array_sum($product) - $mean + $prsScore * log($hr))));
			
			return [round($cs*100,2),round($is*100,2)];
		}
		else {
//				error_log("Didn't have all the data for CHD: ".($sex !== false)." && ".($race !== false) ." && ". ($chol !== false) ." && ". ($hdl !== false) ." && ". ($sbp !== false) ." && ". ($hyper !== false) ." && ". ($diabetes !== -1) ." && ". ($smoking != -1));
			return ["",""];
		}
	}
	
	public function runBoadiceaPush($project_id, $record) {
		$recordData = $this->getRecordData($project_id,$record);
		
		## Check if already ran BOADICEA for this patient
		foreach($recordData as $thisEvent) {
			if($thisEvent["module_boadicea_can_risk"] != "") {
				return false;
			}
		}
		
		$sexAtBirth = false;
		$menarche = false;
		$parity = false;
		$firstBirth = false;
		$tubalLigation = false;
		$endometriosis = false;
		$ocUse = false;
		$prsBC = false;
		$prsOC = false;
		$mhtUse = false;
		$alcohol = false;
		
		list($height, $weight, $bmi) = $this->extractHeightWeightBmi($recordData);
		list($ageCurrent,$dob) = $this->getPatientAgeAndDOB($recordData);
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["age_first_period"] != "") {
				$menarche = $thisEvent["age_first_period"];
			}
			if($thisEvent["had_any_pregnancies"] == 2) {
				$parity = 0;
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
			if($thisEvent["module_breast_cancer_prs"] != "") {
				$prsBC = $thisEvent["module_breast_cancer_prs"];
			}
			if($thisEvent["module_ovarian_cancer_prs"] != "") {
				$prsOC = $thisEvent["module_ovarian_cancer_prs"];
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
		
		## Test MeTree file
//		$meTreeJson = file_get_contents(__DIR__."/metree.json");
		
		## Pull completed MeTree file
		$meTreeJson = $this->findCompletedMeTree($project_id, $record);
		$meTreeJson = json_decode($meTreeJson,true);
		
		if(empty($meTreeJson)) {
			return false;
		}
		
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
				$thisPerson["BRCA1"] = "T:N";
				$thisPerson["BRCA2"] = "T:N";
				$thisPerson["PALB2"] = "T:N";
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
		
		## Parse Invitae data and add genetic risk factors
		$geneMappings = [
			"assessment_brca1" => "BRCA1",
			"assessment_brca2" => "BRCA2",
			"assessment_palb2" => "PALB2",
		];
		
		## These genes appear to be missing from the Invitae data
//		"NM_000051.3" => "ATM",
//		"NM_007194.3" => "CHEK2",
//		"NM_002878.3" => "RAD51D",
//		"NM_058216.2" => "RAD51C",
//		"NM_032043.2" => "BRIP1",
//			"" => "ER",
//				"" => "PR",
//				"" => "HER2",
//				"" => "CK14",
//				"" => "CK56",
		
		foreach($recordData as $thisEvent) {
			foreach($geneMappings as $redcapField => $geneName) {
				if($thisEvent[$redcapField] == "Present") {
					$thisPerson[$geneName] = "T:P";
				}
			}
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
		
		## Test family history data
//		$history = "FamID	Name	Target	IndivID	FathID	MothID	Sex	MZtwin	Dead	Age	Yob	BC1	BC2	OC	PRO	PAN	Ashkn	BRCA1	BRCA2	PALB2	ATM	CHEK2	RAD51D	RAD51C	BRIP1	ER:PR:HER2:CK14:CK56
//	41ebc07	Aundrea	1	41ebc07	e4a2c9a	586ec09	F	0	0	57	1963	0	0	0	0	0	0	S:N	S:N	S:N	S:N	0:0	0:0	0:0	0:0	0:0:0:0:0
//	41ebc07	e4a2c9a	0	e4a2c9a	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
//	41ebc07	586ec09	0	586ec09	0	0	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0";
		## Temp data section since some things are broken/missing on survey
		//		$history = "FamID	Name	Target	IndivID	FathID	MothID	Sex	MZtwin	Dead	Age	Yob	BC1	BC2	OC	PRO	PAN	Ashkn	BRCA1	BRCA2	PALB2	ATM	CHEK2	RAD51D	RAD51C	BRIP1	ER:PR:HER2:CK14:CK56
		//XXXX	pa	0	m21	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
		//XXXX	ma	0	f21	0	0	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
		//XXXX	me	1	ch1	m21	f21	F	0	0	35 1986 0	0	0	0	0	0	S:N	S:N	S:N	S:N	0:0	0:0	0:0	0:0	0:0:0:0:0";
		
		$dataString = $this->compressRecordDataForBoadicea($dob, $menarche, $parity, $firstBirth, $ocUse,
			$mhtUse, $weight, $bmi, $alcohol, $height,
			$tubalLigation, $endometriosis, $prsBC,$prsOC,$history);
		
		if($dataString !== false) {
			$responseJson = $this->sendBoadiceaRequest($dataString);
			$response = json_decode($responseJson, true);
			$foundError = false;
			$boadiceaErrors = "";
			foreach($response as $responseKey => $responseRow) {
				if(strpos($responseKey,"Error") !== false) {
					$foundError = true;
					$boadiceaErrors .= var_export($responseRow,true);
				}
			}
			foreach($response["warnings"] as $thisWarning) {
				if($thisWarning == "lifetime_cancer_risk not provided") {
					$foundError = true;
					$boadiceaErrors .= "Lifetime cancer risk not provided. Does MeTree indicate this patient already had cancer?";
				}
			}
			
			if(!$foundError) {
				## Save the response to the record
				$cancerRisk = $response["pedigree_result"][0]["lifetime_cancer_risk"][0]["breast cancer risk"]["percent"];
				
				$saveData = [
					$this->getProject($project_id)->getRecordIdField() => $record,
					"module_boadicea_can_risk" => $cancerRisk
				];
				
				$results = \REDCap::saveData([
					"project_id" => $project_id,
					"data" => json_encode([$saveData]),
					"dataFormat" => "json"
				]);
				
				## Reset the boadicea errors field so that it's clear there were no errors
				$saveData = [
					$this->getProject($project_id)->getRecordIdField() => $record,
					"module_boadicea_errors" => ""
				];
				
				$results = \REDCap::saveData([
					"project_id" => $project_id,
					"data" => json_encode([$saveData]),
					"dataFormat" => "json",
					"overwriteBehavior" => "overwrite"
				]);
			}
			else {
				## Save the BOADICEA error messages to a field so user can see it
				$saveData = [
					$this->getProject($project_id)->getRecordIdField() => $record,
					"module_boadicea_errors" => $boadiceaErrors
				];
				
				$results = \REDCap::saveData([
					"project_id" => $project_id,
					"data" => json_encode([$saveData]),
					"dataFormat" => "json"
				]);
			}
		}
		else {
			error_log("Failed to send");
		}
	}
	
	public function compressRecordDataForBoadicea($dob, $menarche, $parity, $firstBirth, $ocUse,
												  $mhtUse, $weight, $bmi, $alcohol, $height,
												  $tubalLigation, $endometriosis, $prsBC, $prsOC, $history) {
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
		if($prsBC) {
			$dataString .= "##PRS_BC=alpha=0.45&zscore=".$prsBC."\n";
		}
		if($prsOC) {
			$dataString .= "##PRS_OC=".$prsOC."\n";
		}
		
		$dataString .= "##".$history."\n";
		
		return $dataString;
	}
	
	public function sendBoadiceaRequest($pedigreeData) {
		$pedigreeData = str_replace("\r\n","\\n",$pedigreeData);
		$pedigreeData = str_replace("\n","\\n",$pedigreeData);
		$pedigreeData = str_replace("\t","\\t",$pedigreeData);
		
		## TODO user_id needs to be project setting
		$data = '{"mut_freq":"UK","cancer_rates":"UK","user_id":"mcguffk","pedigree_data":"'.$pedigreeData.'"}';
		
		$apiUrl = $this->getProjectSetting("api-url");
		$apiToken = $this->getProjectSetting("auth-token");
		
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
	
	public function calcJuvenileBmiPercentile($project_id, $record) {
		$recordData = $this->getRecordData($project_id,$record);
		
		## If already have this data, don't overwrite as age at weight measurement will change
		foreach($recordData as $thisEvent => $thisData) {
			if($thisData["module_ped_bmi"] !== NULL && $thisData["module_ped_bmi"] !== "") {
				return;
			}
		}
		
		list($height, $weight, $bmi) = $this->extractHeightWeightBmi($recordData);
		list($age,$dob) = $this->getPatientAgeAndDOB($recordData);
		$sex = $this->getPatientSex($recordData);
		
		## For intersex/prefer not to answer, use male for BMI flag calculation
		if($sex != 1 && $sex != 2) {
			$sex = 1;
		}
		
		if($height !== false && $weight !== false && $bmi !== false && $dob !== false) {
			## If age less than 20, calc BMI percentile and flag if above 85%
			$bmi85Level = [];
			$f = fopen(__DIR__."/bmi_table_cdc.csv","r");
			$headers = fgetcsv($f);
			
			while($row = fgetcsv($f)) {
				$bmi85Level[$row[0]][$row[1]] = $row[11];
			}
			
			$ageMonthsCalc = datediff($dob,date("Y-m-d"),"M");
			
			$saveData = [
				$this->getProject($project_id)->getRecordIdField() => $record,
				"module_ped_bmi" => 2
			];
			
			if($bmi85Level[$sex][(string)(floor($ageMonthsCalc) + 0.5)] <= $bmi) {
				$saveData["module_ped_bmi"] = 1;
			}
				
			$results = \REDCap::saveData([
				"project_id" => $project_id,
				"data" => json_encode([$saveData]),
				"dataFormat" => "json"
			]);
		}
	}
	
	public function extractHeightWeightBmi($recordData ) {
		$height = false;
		$weight = false;
		$bmi = false;
		
		foreach($recordData as $thisEvent) {
			if($thisEvent["current_weight"] != "") {
				$weight = $thisEvent["current_weight"];
				$weight /= 2.2;
			}
			if($thisEvent["current_weight_child"] != "") {
				$weight = $thisEvent["current_weight_child"];
				$weight /= 2.2;
			}
			if($thisEvent["height_feet"] != "") {
				$height = $thisEvent["height_feet"] * 12;
				$height += (int)$thisEvent["height_inches"];
				$height *= 2.54;
			}
			if($thisEvent["height_feet_child"] != "") {
				$height = $thisEvent["height_feet_child"] * 12;
				$height += (int)$thisEvent["height_inches_child"];
				$height *= 2.54;
			}
		}
		
		if($height && $weight) {
			## Convert height to meters and get BMI to one decimal place
			$bmi = round($weight / ($height / 100) / ($height / 100) * 10 ) / 10;
		}
		
		return [$height, $weight, $bmi];
	}
	
}