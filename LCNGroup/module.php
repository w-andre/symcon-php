<?
	class LCNGroup extends IPSModule
	{
		public function __construct($InstanceID)
		{
			//Never delete this line!
			parent::__construct($InstanceID);
			
			//These lines are parsed on Symcon Startup or Instance creation
			//You cannot use variables here. Just static values.
			$this->RegisterPropertyInteger("GroupNumber", 0);
			
			$this->RegisterPropertyBoolean("ShowOutput1", 0);
			$this->RegisterPropertyBoolean("ShowOutput2", 0);
			$this->RegisterPropertyBoolean("ShowOutput3", 0);
			
			$this->RegisterPropertyBoolean("ShowRelay1", 0);
			$this->RegisterPropertyBoolean("ShowRelay2", 0);
			$this->RegisterPropertyBoolean("ShowRelay3", 0);
			$this->RegisterPropertyBoolean("ShowRelay4", 0);
			$this->RegisterPropertyBoolean("ShowRelay5", 0);
			$this->RegisterPropertyBoolean("ShowRelay6", 0);
			$this->RegisterPropertyBoolean("ShowRelay7", 0);
			$this->RegisterPropertyBoolean("ShowRelay8", 0);
			
			$this->RegisterPropertyBoolean("ShowLightScene", 0);
						
			$this->RegisterPropertyInteger("Ramp", 3);
			$this->RegisterPropertyInteger("LCNClientSocketId", 0);
		}
		
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterProfileEx("LightScene.LCN", "Bulb", "", "", 1 /* Integer */, Array(
				Array(0, "Lichtszene 1", "", -1),
				Array(1, "Lichtszene 2", "", -1),
				Array(2, "Lichtszene 3", "", -1),
				Array(3, "Lichtszene 4", "", -1),
				Array(4, "Lichtszene 5", "", -1),
				Array(5, "Lichtszene 6", "", -1),
				Array(6, "Lichtszene 7", "", -1),
				Array(7, "Lichtszene 8", "", -1),
				Array(8, "Lichtszene 9", "", -1),
				Array(9, "Lichtszene 10", "", -1)
			));
			
			$this->RegisterProfileEx("LoadSaveLSSwitch.LCN", "", "", "", 0 /* Boolean */, Array(
				Array(0, "Nein", "", 16711680),
				Array(1, "Ja", "", 65280),
			));
			
			$this->UpdateInstance();
		}
		
		private function UpdateVariables($name, $displayName, $type, $position)
		{
			$keep = $this->ReadPropertyBoolean("Show" . $name);
			
			switch($type)
			{
				case 0: // output
					$this->MaintainVariable("Status" . $name, "Status " . $displayName, 0, "~Switch", $position, $keep);
					$this->MaintainAction("Status" . $name, $keep);
					$this->MaintainVariable("Intensity" . $name, "Intensity " . $displayName, 1, "~Intensity.100", $position + 1, $keep);
					$this->MaintainAction("Intensity" . $name, $keep);
					break;
				case 1: // relay
					$this->MaintainVariable("Status" . $name, "Status " . $displayName, 0, "~Switch", $position, $keep);
					$this->MaintainAction("Status" . $name, $keep);
					break;
				case 2: // light scene
					$this->MaintainVariable($name, $displayName, 1, "LightScene.LCN", $position, $keep);
					$this->MaintainAction($name, $keep);
					$this->MaintainVariable("LoadSaveLSSwitch", "Save " . $displayName, 0, "LoadSaveLSSwitch.LCN", $position + 1, $keep);
					$this->MaintainAction("LoadSaveLSSwitch", $keep);
					break;
			}
		}
		
		private function UpdateInstance()
		{
			$this->UpdateVariables("Output1", "Output 1", 0, 10);
			$this->UpdateVariables("Output2", "Output 2", 0, 20);
			$this->UpdateVariables("Output3", "Output 3", 0, 30);
			$this->UpdateVariables("Relay1", "Relay 1", 1, 40);
			$this->UpdateVariables("Relay2", "Relay 2", 1, 50);
			$this->UpdateVariables("Relay3", "Relay 3", 1, 60);
			$this->UpdateVariables("Relay4", "Relay 4", 1, 70);
			$this->UpdateVariables("Relay5", "Relay 5", 1, 80);
			$this->UpdateVariables("Relay6", "Relay 6", 1, 90);
			$this->UpdateVariables("Relay7", "Relay 7", 1, 100);
			$this->UpdateVariables("Relay8", "Relay 8", 1, 110);
			$this->UpdateVariables("LightScene", "Light Scene", 2, 120);
		}
		
		public function LoadLightScene($sceneNo)
		{
			try
			{
				$rr = $this->GetRamp();
				$this->LoadOrSaveLightScene("G", $this->ReadPropertyInteger("GroupNumber"), $sceneNo, "7", $rr, "A");
			}
			catch(Exception $e)
			{
				IPS_LogMessage("LCNGroup", "Exception: " . $e->getMessage());
			}
		}
	
		public function SaveLightScene($sceneNo)
		{
			try
			{
				$rr = $this->GetRamp();
				$this->LoadOrSaveLightScene("G", $this->ReadPropertyInteger("GroupNumber"), $sceneNo, "7", $rr, "S");
			} 
			catch(Exception $e)
			{
				IPS_LogMessage("LCNGroup", "Exception: " . $e->getMessage());
			}
		}
		
		private function GetRamp()
		{
			$seconds = $this->RegisterPropertyInteger("Ramp");
			$rr = $this->GetRampFromSeconds($seconds);
			return $rr;
		}
		
		private function GetRampFromSeconds($seconds)
		{
			switch($seconds)
			{
				case 0: return "000";
				case 0.25: return "001";
				case 0.50: return "002";
				case 0.66: return "003";
				case 1: return "004";
				case 1.40: return "005";
				case 2: return "006";
				case 3: return "007";
				case 4: return "008";
				case 5: return "009";
				default:
					$rr = ($seconds - 6) / 2 + 10;
					return str_pad(strval($rr), 3, "0", STR_PAD_LEFT);
			}			
		}
		
		private function LoadOrSaveLightScene($targetType, $targetId, $sceneNo, $channels, $rr, $loadOrSave) {
			$pck = ">"
			. $targetType                                  			// G=group, M=module
			. "000"													// segment
			. str_pad(strval($targetId), 3, "0", STR_PAD_LEFT)		// module or group number
			. "."
			. "SZ"													// light scene
			. $loadOrSave											// A=load, S=save
			. $channels												// 1=output 1, 2=output 2, 4=output 3, 0=relay (outputs are added together, 5=A1+A3, 7=all)
			. str_pad(strval($sceneNo), 3, "0", STR_PAD_LEFT)		// light scene 00 - 09, 15: take value from counter
			. strval($rr)											// ramp, 007 --> 3s or relay state, e.g. 10111011
			. chr(10);
			
			$id = $this->ReadPropertyInteger("LCNClientSocketId");
			CSCK_SendText($id, $pck);
		}
		
		public function SetIntensity($outputNo, $intensity)
		{
			$groupNo = $this->ReadPropertyInteger("GroupNumber");
			
			$pck = ">"
			. "G"		                                  			// G=group, M=module
			. "000"													// segment
			. str_pad(strval($groupNo), 3, "0", STR_PAD_LEFT)		// module or group number
			. "."
			. "A"													// output
			. $outputNo												// output number
			. "DI"													// output intensity
			. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)		// output intensity value 000...100
			. strval($rr)											// ramp, 007 --> 3s
			. chr(10);
			
			$id = $this->ReadPropertyInteger("LCNClientSocketId");
			CSCK_SendText($id, $pck);
		}
		
		public function SwitchRelay($relayNo, $switchOn)
		{
			$groupNo = $this->ReadPropertyInteger("GroupNumber");
			$relayState = $switchOn === true ? "1" : "0";
			
			$pck = ">"
			. "G"		                                  			// G=group, M=module
			. "000"													// segment
			. str_pad(strval($groupNo), 3, "0", STR_PAD_LEFT)		// module or group number
			. "."
			. "R8"													// relay
			. substr("--------", 0, $relayNo - 1) 					// do not change state for other relays
			. $relayState		                        			// target relay state
			. substr("--------", 0, 8 - $relayNo)	     			// do not change state for other relays
			. chr(10);
			
			$id = $this->ReadPropertyInteger("LCNClientSocketId");
			CSCK_SendText($id, $pck);
		}
				
		public function RequestAction($ident, $value)
		{
			switch($ident) {
				case "LightScene":
					$saveSwitchState = GetValueBoolean($this->GetIDForIdent("LoadSaveLSSwitch"));
					if ($saveSwitchState === true)
						$this->Save($value);
					else
						$this->Load($value);
					break;
				case "LoadSaveLSSwitch":
					SetValueBoolean($this->GetIDForIdent($ident), $value);
					break;
				default:
					throw new Exception("Invalid ident");
			}
		}
		
		//Remove on next Symcon update
		protected function RegisterProfile($name, $icon, $prefix, $suffix, $minValue, $maxValue, $stepSize, $profileType) {
		
			if(!IPS_VariableProfileExists($name)) {
				IPS_CreateVariableProfile($name, $profileType);
				IPS_SetVariableProfileIcon($name, $icon);
				IPS_SetVariableProfileText($name, $prefix, $suffix);
				IPS_SetVariableProfileValues($name, $minValue, $maxValue, $stepSize);
				return true;
			} else {
				$profile = IPS_GetVariableProfile($name);
				if($profile['ProfileType'] != $profileType)
					throw new Exception("Variable profile type does not match for profile " . $name);
				return false;
			}
		}
		
		protected function RegisterProfileEx($name, $icon, $prefix, $suffix, $profileType, $associations) {
		
			$result = $this->RegisterProfile($name, $icon, $prefix, $suffix, $associations[0][0], $associations[sizeof($associations)-1][0], 0, $profileType);
			if (!$result) return; // do not set associations if the profile did already exist
			
			foreach($associations as $association) {
				IPS_SetVariableProfileAssociation($name, $association[0], $association[1], $association[2], $association[3]);
			}
			
		}
	}
?>