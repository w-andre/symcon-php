<?
class LCNGroup extends IPSModule {

	public function Create() {
		//Never delete this line!
		parent::Create();
		
		$this->RegisterPropertyInteger("Segment", 0);
		$this->RegisterPropertyInteger("Group", 0);
		$this->RegisterPropertyInteger("Unit", 0);
		$this->RegisterPropertyInteger("Channel", 0);
		$this->RegisterPropertyInteger("Ramp", 3);
	}

	public function ApplyChanges() {

		//Never delete this line!
		parent::ApplyChanges();

		// connect to LCN Gateway
		$this->ConnectParent('{9BDFC391-DEFF-4B71-A76B-604DBA80F207}');

		// register variable profile for light scenes
		// do not overwrite existing associations (allow renaming light scenes)
		$this->RegisterProfileEx("LightScene.LCN", "Bulb", "", "", 1 /* Integer */, Array(
			Array(1, "Light Scene 1", "", -1),
			Array(2, "Light Scene 2", "", -1),
			Array(3, "Light Scene 3", "", -1),
			Array(4, "Light Scene 4", "", -1),
			Array(5, "Light Scene 5", "", -1),
			Array(6, "Light Scene 6", "", -1),
			Array(7, "Light Scene 7", "", -1),
			Array(8, "Light Scene 8", "", -1),
			Array(9, "Light Scene 9", "", -1),
			Array(10, "Light Scene 10", "", -1)
		));

		// register variable profile for load/save light scene switch
		$this->RegisterProfileEx("LoadSaveLSSwitch.LCN", "", "", "", 0 /* Boolean */, Array(
			Array(0, "No", "", 16711680),
			Array(1, "Yes", "", 65280)
		));

		// get current unit configuration
		$unit = $this->ReadPropertyInteger("Unit");

		// update variables for current configuration
		switch ($unit) {
			case 0: // output
				$this->MaintainVariable("Status", "Status", 0, "~Switch", 10, true);
				$this->EnableAction("Status");
				$this->MaintainVariable("Intensity", "Intensity", 1, "~Intensity.100", 20, true);
				$this->EnableAction("Intensity");
				$this->MaintainVariable("LightScene", "LightScene", 1, "LightScene.LCN", 10, false);
				$this->MaintainVariable("LoadSaveLSSwitch", "Save Light Scene", 0, "LoadSaveLSSwitch.LCN", 20, false);
				break;
			case 2: // relay
				$this->MaintainVariable("Status", "Status", 0, "~Switch", 10, true);
				$this->EnableAction("Status");
				$this->MaintainVariable("Intensity", "Intensity", 1, "~Intensity.100", 20, false);
				$this->MaintainVariable("LightScene", "Light Scene", 1, "LightScene.LCN", 10, false);
				$this->MaintainVariable("LoadSaveLSSwitch", "Save Light Scene", 0, "LoadSaveLSSwitch.LCN", 20, false);
				break;
			case 4: // light scene
				$this->MaintainVariable("Status", "Status", 0, "~Switch", 10, false);
				$this->MaintainVariable("Intensity", "Intensity", 1, "~Intensity.100", 20, false);
				$this->MaintainVariable("LightScene", "Lichtszene", 1, "LightScene.LCN", 10, true);
				$this->EnableAction("LightScene");
				$this->MaintainVariable("LoadSaveLSSwitch", "Speichern", 0, "LoadSaveLSSwitch.LCN", 20, true);
				$this->EnableAction("LoadSaveLSSwitch");
				break;
		}
	}

	protected function RegisterProfile($name, $icon, $prefix, $suffix, $minValue, $maxValue, $stepSize, $profileType) {
		if (!IPS_VariableProfileExists($name)) {
			IPS_CreateVariableProfile($name, $profileType);
			IPS_SetVariableProfileIcon($name, $icon);
			IPS_SetVariableProfileText($name, $prefix, $suffix);
			IPS_SetVariableProfileValues($name, $minValue, $maxValue, $stepSize);
			return true;
		} else {
			$profile = IPS_GetVariableProfile($name);
			if ($profile['ProfileType'] != $profileType)
				throw new Exception("Variable profile type does not match for profile " . $name);
			return false;
		}
	}

	protected function RegisterProfileEx($name, $icon, $prefix, $suffix, $profileType, $associations) {
		$result = $this->RegisterProfile($name, $icon, $prefix, $suffix, $associations[0][0], $associations[sizeof($associations) - 1][0], 0, $profileType);
		if (!$result) return; // do not set associations if the profile does already exist (allow renaming)

		foreach($associations as $association) {
			IPS_SetVariableProfileAssociation($name, $association[0], $association[1], $association[2], $association[3]);
		}
	}

	/*
		LCN specific functions
	 */

	public function LoadLightScene(integer $sceneNo) {
		$segment = $this->ReadPropertyInteger("Segment");
		$target = $this->ReadPropertyInteger("Group");
		$ramp = $this->ReadPropertyInteger("Ramp");
		$rr = $this->GetRampFromSeconds($ramp);
		
		$this->LoadOrSaveLightScene(1, $segment, $target, $sceneNo, "7", $rr, "A"); // all outputs
		$this->LoadOrSaveLightScene(1, $segment, $target, $sceneNo, "0", "11111111", "A"); // all relays
	}

	public function SaveLightScene(integer $sceneNo) {
		$segment = $this->ReadPropertyInteger("Segment");
		$target = $this->ReadPropertyInteger("Group");
		$ramp = $this->ReadPropertyInteger("Ramp");
		$rr = $this->GetRampFromSeconds($ramp);
		
		$this->LoadOrSaveLightScene(1, $segment, $target, $sceneNo, "7", $rr, "S"); // all outputs (relays are always saved)
	}

	public function SetIntensity(integer $intensity) {
		$outputNo = $this->ReadPropertyInteger("Channel");
		$this->SetSpecificOutputIntensity($outputNo, $intensity);
	}

	public function SetSpecificOutputIntensity(integer $outputNo, integer $intensity) {
		$ramp = $this->ReadPropertyInteger("Ramp");
		$this->SetSpecificOutputIntensityWithRamp($outputNo, $intensity, $ramp);
	}

	public function SetSpecificOutputIntensityWithRamp(integer $outputNo, integer $intensity, float $rampInSeconds) {
		$segment = $this->ReadPropertyInteger("Segment");
		$target = $this->ReadPropertyInteger("Group");
		$rr = $this->GetRampFromSeconds($rampInSeconds);
		
		if ($outputNo == 10) /* 1 + 2 + 3 + 4 = 10 , requires at least module firmware 1805 */
			$data = "Y"												// all outputs
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)	// output 1 intensity value 000...100
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)	// output 2 intensity value 000...100
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)	// output 3 intensity value 000...100
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)	// output 4 intensity value 000...100
				. strval($rr);										// ramp, 007 --> 3s
		else
			$data = $outputNo										// output number
				. "DI"												// output intensity
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)	// output intensity value 000...100
				. strval($rr);										// ramp, 007 --> 3s

		$this->SendLcnPckCommand(1, $segment, $target, "A", $data);
	}

	public function SwitchRelay(boolean $switchOn) {
		$relayNo = $this->ReadPropertyInteger("Channel");
		$this->SwitchSpecificRelay($relayNo, $switchOn);
	}

	public function SwitchSpecificRelay(integer $relayNo, boolean $switchOn) {
		$segment = $this->ReadPropertyInteger("Segment");
		$target = $this->ReadPropertyInteger("Group");
		$relay = $this->ReadPropertyInteger("Channel");
		$relayState = $switchOn == true ? "1" : "0";

		$data = substr("--------", 0, $relay - 1)	// do not change state for other relays
			. $relayState							// target relay state
			. substr("--------", 0, 8 - $relay);	// do not change state for other relays

		$this->SendLcnPckCommand(1, $segment, $target, "R8", $data);
	}

	public function LoadOrSaveLightScene(integer $address, integer $segment, integer $target, integer $sceneNo, integer $channels, integer $rr, boolean $loadOrSave) {
		$data = $loadOrSave											// A=load, S=save
			. $channels												// 1=output 1, 2=output 2, 4=output 3, 0=relay (outputs are added together, 5=A1+A3, 7=all)
			. str_pad(strval($sceneNo - 1), 3, "0", STR_PAD_LEFT)	// light scene 00 - 09, 15: take value from counter
			. strval($rr);

		$this->SendLcnPckCommand($address, $segment, $target, "SZ", $data);
	}

	public function RequestAction($ident, $value) {
		switch ($ident) {
			case "Status":
			case "LoadSaveLSSwitch":
				SetValueBoolean($this->GetIDForIdent($ident), $value);

				$unit = $this->ReadPropertyInteger("Unit");
				switch ($unit) {
					case 0: // output
						$intensity = $value ? 100 : 0;
						SetValueInteger($this->GetIDForIdent("Intensity"), $intensity);
						$this->SetIntensity($intensity);
						break;
					case 2: // relay
						$this->SwitchRelay($value);
						break;
				}
				break;
			case "Intensity":
				SetValueInteger($this->GetIDForIdent($ident), $value);
				SetValueBoolean($this->GetIDForIdent("Status"), $value > 0);
				$this->SetIntensity($value);
				break;
			case "LightScene":
				SetValueInteger($this->GetIDForIdent($ident), $value);
				$saveSwitchState = GetValueBoolean($this->GetIDForIdent("LoadSaveLSSwitch"));
				if ($saveSwitchState) {
					SetValueBoolean($this->GetIDForIdent("LoadSaveLSSwitch"), false);
					$this->SaveLightScene($value);
				} else
					$this->LoadLightScene($value);
				break;
			default:
				throw new Exception("Invalid ident");
		}
	}

	/*
		Send LCN PCK command to LCN Gateway instance.
		
		@param	int		$address	0 for module, 1 for group
		@param	int		$segment	LCN segment
		@param	int		$target		module/group id
		@param	string	$function	PCK function, e.g. "SZ" for controlling light scenes
		@param	string	$data		PCK function data, e.g. "A700007" to load light scene 1 for all outputs with a ramp of 3s
	*/
	private function SendLcnPckCommand($address, $segment, $target, $function, $data) {
		$this->SendDataToParent(json_encode(Array(
			"DataID"	=> "{C5755489-1880-4968-9894-F8028FE1020A}",
			"Address"	=> $address,
			"Segment"	=> $segment,
			"Target"	=> $target,
			"Function"	=> $function,
			"Data"		=> $data)
		));
	}

	private static function GetRampFromSeconds($seconds) {
		switch ($seconds) {
		case 0:
			return "000";
		case 0.25:
			return "001";
		case 0.50:
			return "002";
		case 0.66:
			return "003";
		case 1:
			return "004";
		case 1.40:
			return "005";
		case 2:
			return "006";
		case 3:
			return "007";
		case 4:
			return "008";
		case 5:
			return "009";
		default:
			$rr = ($seconds - 6) / 2 + 10;
			return str_pad(strval($rr), 3, "0", STR_PAD_LEFT);
		}
	}
}
?>