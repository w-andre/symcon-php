<?
	class LCNGroupLightScene extends IPSModule
	{
		public function __construct($InstanceID)
		{
			//Never delete this line!
			parent::__construct($InstanceID);
			
			//These lines are parsed on Symcon Startup or Instance creation
			//You cannot use variables here. Just static values.
			$this->RegisterPropertyString("LCNGroupNumber", "");
			$this->RegisterPropertyString("Ramp", "007");
			$this->RegisterPropertyString("LCNClientSocketId", "");
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
			
			$this->RegisterVariableInteger("LightScene", "Lichtszene", "LightScene.LCN");
			$this->EnableAction("LightScene");
			$this->RegisterVariableBoolean("LoadSaveLSSwitch", "Speichern", "LoadSaveLSSwitch.LCN");
			$this->EnableAction("LoadSaveLSSwitch");
			
		}
		
		public function Load($sceneNo){
			$this->LoadOrSaveLightScene("G", $this->ReadPropertyString("LCNGroupNumber"), $sceneNo, "7", "007", "A");
		}
		
		public function Save($sceneNo){
			$this->LoadOrSaveLightScene("G", $this->ReadPropertyString("LCNGroupNumber"), $sceneNo, "7", "007", "S");
		}
		
		private function LoadOrSaveLightScene($targetType, $targetId, $sceneNo, $channels, $rr, $loadOrSave) {
			$pck = ">"
			. $targetType                                  			// G=group, M=module
			. "000"													// segment
			. str_pad($targetId, 3, "0", STR_PAD_LEFT)				// module or group number
			. "."
			. "SZ"													// light scene
			. $loadOrSave											// A=load, S=save
			. $channels												// 1=output 1, 2=A2, 4=A3, 0=Relais (outputs are added together, 5=A1+A3, 7=all)
			. str_pad(strval($sceneNo), 3, "0", STR_PAD_LEFT)		// light scene 00 - 09, 15: take value from counter
			. $rr													// ramp, 007 --> 3s or Relais, e.g. 10111011
			. chr(10);
			
			$id = intval($this->ReadPropertyString("LCNClientSocketId"));
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