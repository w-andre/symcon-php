<?
	class LCNGroup extends IPSModule
	{
		public function __construct($InstanceID)
		{
			//Never delete this line!
			parent::__construct($InstanceID);
			
			//These lines are parsed on Symcon Startup or Instance creation
			//You cannot use variables here. Just static values.
			$this->RegisterPropertyInteger("GroupNumber");
			$this->RegisterPropertyInteger("Unit");
			$this->RegisterPropertyInteger("Channel");
			$this->RegisterPropertyInteger("Ramp");
		}
		
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			// connect to Client Socket of LCN Gateway
			$this->CustomConnectParent();
					
			$this->RegisterProfileEx("LightScene.LCN", "Bulb", "", "", 1 /* Integer */, Array(
				Array(0, "Light Scene 1", "", -1),
				Array(1, "Light Scene 2", "", -1),
				Array(2, "Light Scene 3", "", -1),
				Array(3, "Light Scene 4", "", -1),
				Array(4, "Light Scene 5", "", -1),
				Array(5, "Light Scene 6", "", -1),
				Array(6, "Light Scene 7", "", -1),
				Array(7, "Light Scene 8", "", -1),
				Array(8, "Light Scene 9", "", -1),
				Array(9, "Light Scene 10", "", -1)
			));
			
			$this->RegisterProfileEx("LoadSaveLSSwitch.LCN", "", "", "", 0 /* Boolean */, Array(
				Array(0, "No", "", 16711680),
				Array(1, "Yes", "", 65280),
			));
			
			$unit = $this->ReadPropertyInteger("Unit");
			$keepOutput = $unit == 0;
			$keepRelay = $unit == 2;
			$keepLightScene = $unit == 4;
						
			$this->CustomMaintainVariable("Status", "Status", 0, "~Switch", 10, $keepOutput || $keepRelay);
			if ($keepRelay || $keepOutput) $this->EnableAction("Status");
			
			$this->CustomMaintainVariable("Intensity", "Intensity", 1, "~Intensity.100", 20, $keepOutput);
			if ($keepOutput) $this->EnableAction("Intensity");
				
			$this->CustomMaintainVariable("LightScene", "Light Scene", 1, "LightScene.LCN", 10, $keepLightScene);
			$this->CustomMaintainVariable("LoadSaveLSSwitch", "Save Light Scene", 0, "LoadSaveLSSwitch.LCN", 20, $keepLightScene);
			if ($keepLightScene)
			{
				$this->EnableAction("LightScene");
				$this->EnableAction("LoadSaveLSSwitch");
			}
		}
				
		public function LoadLightScene($sceneNo)
		{
			$ramp = $this->ReadPropertyInteger("Ramp");
			$rr = $this->GetRampFromSeconds($ramp);
			$this->LoadOrSaveLightScene("G", $this->ReadPropertyInteger("GroupNumber"), $sceneNo, "7", $rr, "A"); // all outputs
			$this->LoadOrSaveLightScene("G", $this->ReadPropertyInteger("GroupNumber"), $sceneNo, "0", "11111111", "A"); // all relays
		}
	
		public function SaveLightScene($sceneNo)
		{
			$ramp = $this->ReadPropertyInteger("Ramp");
			$rr = $this->GetRampFromSeconds($ramp);
			$this->LoadOrSaveLightScene("G", $this->ReadPropertyInteger("GroupNumber"), $sceneNo, "7", $rr, "S"); // all outputs AND all relays
		}
			
		public function SetIntensity($intensity)
		{
			$outputNo = $this->ReadPropertyInteger("Channel");
			$this->SetSpecificOutputIntensity($outputNo, $intensity);
		}
		
		
		public function SetSpecificOutputIntensity($outputNo, $intensity)
		{
			$ramp = $this->ReadPropertyInteger("Ramp");
			$this->SetSpecificOutputIntensityWithRamp($outputNo, $intensity, $ramp);
		}
		
		public function SetSpecificOutputIntensityWithRamp($outputNo, $intensity, $rampInSeconds)
		{
			$groupNo = $this->ReadPropertyInteger("GroupNumber");
			$rr = $this->GetRampFromSeconds($rampInSeconds);
			
			$pck = ">"
				. "G"		                                  			// G=group, M=module
				. "000"													// segment
				. str_pad(strval($groupNo), 3, "0", STR_PAD_LEFT)		// module or group number
				. "."
				. "A";													// output
			
			if ($outputNo == 10) /* 1 + 2 + 3 + 4 = 10 , requires at least module firmware 1805 */
				$pck = $pck
				. "Y"													// all outputs
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)		// output 1 intensity value 000...100
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)		// output 2 intensity value 000...100
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)		// output 3 intensity value 000...100
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)		// output 4 intensity value 000...100
				. strval($rr)											// ramp, 007 --> 3s
				. chr(10);
			else
				$pck = $pck
				. $outputNo												// output number
				. "DI"													// output intensity
				. str_pad(strval($intensity), 3, "0", STR_PAD_LEFT)		// output intensity value 000...100
				. strval($rr)											// ramp, 007 --> 3s
				. chr(10);
							
			$this->SendLcnPckCommand($pck);
		}
		
		public function SwitchRelay($switchOn)
		{
			$relayNo = $this->ReadPropertyInteger("Channel");
			$this->SwitchSpecificRelay($relayNo, $switchOn);
		}
		
		public function SwitchSpecificRelay($relayNo, $switchOn)
		{
			$groupNo = $this->ReadPropertyInteger("GroupNumber");
			$relayNo = $this->ReadPropertyInteger("Channel");
			$relayState = $switchOn == true ? "1" : "0";
			
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
			
			$this->SendLcnPckCommand($pck);
		}
		
		public function LoadOrSaveLightScene($targetType, $targetId, $sceneNo, $channels, $rr, $loadOrSave)
		{			
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
			
			$this->SendLcnPckCommand($pck);
		}
				
		public function RequestAction($ident, $value)
		{
			switch($ident) {
				case "Status":
				case "LoadSaveLSSwitch":
					SetValueBoolean($this->GetIDForIdent($ident), $value);
					
					$unit = $this->ReadPropertyInteger("Unit");
					switch($unit)
					{
						case 0: // output
							$intensity = $value ? 100 : 0;
							$this->SetIntensity($intensity);
							SetValueInteger($this->GetIDForIdent("Intensity"), $intensity);
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
					if ($saveSwitchState)
					{
						$this->SaveLightScene($value);
						SetValueBoolean($this->GetIDForIdent("LoadSaveLSSwitch"), false);
					}
					else
						$this->LoadLightScene($value);
					break;
				default:
					throw new Exception("Invalid ident");
			}
		}
		
		private function SendLcnPckCommand($pck)
		{
			$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $pck)));
		}		
				
		private static function GetRampFromSeconds($seconds)
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
		
		private function CustomMaintainVariable($ident, $name, $type, $profile, $position, $keep)
		{
			if($keep) {
				switch($type)
				{
					case 0:
						$this->RegisterVariableBoolean($ident, $name, $profile, $position);	
						break;
					case 1:
						$this->RegisterVariableInteger($ident, $name, $profile, $position);	
						break;
				}				
			} else {
				$vid = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
				if(!is_int($vid) || !IPS_VariableExists($vid))
					return; //bail out
				IPS_DeleteVariable($vid);
			}
		}
		
		protected function CustomConnectParent() {
		
			$instance = IPS_GetInstance($this->InstanceID);
			if($instance['ConnectionID'] == 0) {
				$gatewayListIds = IPS_GetInstanceListByModuleID('{9BDFC391-DEFF-4B71-A76B-604DBA80F207}');
				if(sizeof($gatewayListIds) > 0) {
					$gateway = IPS_GetInstance($gatewayList[0]);
					IPS_ConnectInstance($this->InstanceID, $gateway['ConnectionID']);
					return;
				}
			}
		}
		
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