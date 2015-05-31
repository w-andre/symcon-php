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
			
			
			if ($this->ReadPropertyBoolean("ShowOutput1") === true)
			{
				$this->RegisterVariableBoolean("StatusOutput1", "Status Output 1", "~Switch");
				$this->EnableAction("StatusOutput1");
				$this->RegisterVariableInteger("IntensityOutput1", "Intensity Output 1", "~Intensity.100");
				$this->EnableAction("IntensityOutput1");
			}
			if ($this->ReadPropertyBoolean("ShowOutput2") === true)
			{
				$this->RegisterVariableBoolean("StatusOutput2", "Status Output 2", "~Switch");
				$this->EnableAction("StatusOutput2");
				$this->RegisterVariableInteger("IntensityOutput2", "Intensity Output 2", "~Intensity.100");
				$this->EnableAction("IntensityOutput2");
			}
			if ($this->ReadPropertyBoolean("ShowOutput3") === true)
			{
				$this->RegisterVariableBoolean("StatusOutput3", "Status Output 3", "~Switch");
				$this->EnableAction("StatusOutput3");
				$this->RegisterVariableInteger("IntensityOutput3", "Intensity Output 3", "~Intensity.100");
				$this->EnableAction("IntensityOutput3");
			}
			if ($this->ReadPropertyBoolean("ShowRelay1") === true)
			{
				$this->RegisterVariableBoolean("StatusRelay1", "Status Relay 1", "~Switch");
				$this->EnableAction("StatusRelay1");
			}
			if ($this->ReadPropertyBoolean("ShowLightScene") === true)
			{
				$this->RegisterVariableInteger("LightScene", "Lichtszene", "LightScene.LCN");
				$this->EnableAction("LightScene");
				$this->RegisterVariableBoolean("LoadSaveLSSwitch", "Lichtszene speichern", "LoadSaveLSSwitch.LCN");
				$this->EnableAction("LoadSaveLSSwitch");
			}
					
			
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
					return = str_pad(strval($rr), 3, "0", STR_PAD_LEFT);
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