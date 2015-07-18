<?
class HomeKitAccessory extends IPSModule {

	public function __construct($InstanceID) {
		//Never delete this line!
		parent::__construct($InstanceID);

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyInteger("DeviceType", 0);
		
		$this->RegisterPropertyInteger("PowerStateVariableId", 0);
		$this->RegisterPropertyString("PowerStateOn", "1");
		$this->RegisterPropertyString("PowerStateOff", "0");
		
		$this->RegisterPropertyInteger("BrightnessVariableId", 0);
		$this->RegisterPropertyInteger("BrightnessMaxValue", 100);
		
		$this->RegisterPropertyInteger("TargetDoorStateVariableId", 0);
		$this->RegisterPropertyString("TargetDoorStateOpen", "0");
		$this->RegisterPropertyString("TargetDoorStateClosed", "4");
		$this->RegisterPropertyString("TargetDoorStateOpening", "0");
		$this->RegisterPropertyString("TargetDoorStateClosing", "4");
		$this->RegisterPropertyString("TargetDoorStateStopped", "2");
		
		$this->RegisterPropertyInteger("CurrentDoorStateVariableId", 0);
		$this->RegisterPropertyString("CurrentDoorStateOpen", "0");
		$this->RegisterPropertyString("CurrentDoorStateClosed", "4");
		$this->RegisterPropertyString("CurrentDoorStateOpening", "0");
		$this->RegisterPropertyString("CurrentDoorStateClosing", "4");
		$this->RegisterPropertyString("CurrentDoorStateStopped", "2");
		
		$this->RegisterPropertyInteger("TargetTemperatureVariableId", 0);
		$this->RegisterPropertyInteger("CurrentTemperatureVariableId", 0);
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();
	}
	
	/*
		Switch and Light Bulb functions
	*/

	public function SetPowerState($value) {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("PowerStateVariableId");
		$this->SetTargetVariableValue($variableId, "PowerState", $value);
	}
	
	public function GetPowerState() {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("PowerStateVariableId");
		return $this->GetHomeKitValue($variableId, "PowerState");
	}
	
	/*
		Light Bulb functions
	*/
	
	public function SetBrightness($value) {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("BrightnessVariableId");
		
		// brightness variable specified?
		if ($variableId > 0) {
			$this->SetTargetVariableValue($variableId, "Brightness", $value);
			return;
		}
		
		// fallback to power state
		$variableId = $this->ReadPropertyInteger("PowerStateVariableId");
		$this->SetTargetVariableValue($variableId, "PowerState", $value > 0);
	}
	
	public function GetBrightness() {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("BrightnessVariableId");
		
		// brightness variable specified?
		if ($variableId > 0) return $this->GetHomeKitValue($variableId, "Brightness");
		
		// fallback to power state
		$variableId = $this->ReadPropertyInteger("PowerStateVariableId");
		return $this->GetHomeKitValue($variableId, "PowerState");
	}
	
	/*
		Thermostat functions
	*/
	
	public function SetTargetTemperature($value) {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("TargetTemperatureVariableId");
		$targetValue = $this->GetTargetValue("TargetTemperature", $value);
		$this->SetTargetVariableValue($variableId, "TargetTemperature", $targetValue);
	}
	
	public function GetTargetTemperature() {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("TargetTemperatureVariableId");
		return $this->GetHomeKitValue($variableId, "TargetTemperature");
	}
	
	public function GetCurrentTemperature() {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("CurrentTemperatureVariableId");
		return $this->GetHomeKitValue($variableId, "CurrentTemperature");
	}
	
	/*
		Garage Door Opener functions
	*/
	
	public function SetTargetDoorState($value) {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("TargetDoorStateVariableId");
		$targetValue = $this->GetTargetValue("TargetDoorState", $value);
		$this->SetTargetVariableValue($variableId, "TargetDoorState", $targetValue);
	}
	
	public function GetTargetDoorState() {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("TargetDoorStateVariableId");
		return $this->GetHomeKitValue($variableId, "TargetDoorState");
	}
	
	public function GetCurrentDoorState() {
		// get target variable id
		$variableId = $this->ReadPropertyInteger("CurrentDoorStateVariableId");
		return $this->GetHomeKitValue($variableId, "CurrentDoorState");
	}
	
	
	/*
		internal functions
	*/
	
	private function SetTargetVariableValue($variableId, $homeKitVariableType, $homeKitValue) {
		// get target variable object properties
		$variableObject = IPS_GetObject($variableId);
		$targetValue = $this->GetTargetValue($variableId, $homeKitVariableType, $homeKitValue);
		
		// request associated action for the specified variable and value
		IPS_RequestAction($variableObject["ParentID"], $variableObject["ObjectIdent"], $targetValue);
	}
	
	private function GetTargetValue($variableId, $homeKitVariableType, $homeKitValue) {
		$variable = IPS_GetVariable($variableId);
		
		$targetValueString = "";
		switch ($homeKitVariableType) {
			case "PowerState":
				$targetValueString = $homeKitValue 
					? $this->ReadPropertyString("PowerStateOn") 
					: $this->ReadPropertyString("PowerStateOff");
				break;
			case "Brightness":
				$maxValue = $this->ReadPropertyFloat("BrightnessMaxValue");
				$targetValue = ($homeKitValue / 100) * $maxValue;
				return $targetValue;
			case "TargetDoorState":
				switch ($homeKitValue) {
					case 0: //HMCharacteristicValueDoorState::Open
						$targetValueString = $this->ReadPropertyString("TargetDoorStateOpen");
						break;
					case 1: //HMCharacteristicValueDoorState::Closed
						$targetValueString = $this->ReadPropertyString("TargetDoorStateClosed");
						break;
					case 2: //HMCharacteristicValueDoorState::Opening
						$targetValueString = $this->ReadPropertyString("TargetDoorStateOpening");
						break;
					case 3: //HMCharacteristicValueDoorState::Closing
						$targetValueString = $this->ReadPropertyString("TargetDoorStateClosing");
						break;
					case 4: //HMCharacteristicValueDoorState::Stopped
						$targetValueString = $this->ReadPropertyString("TargetDoorStateStopped");
						break;
				}
				break;
			case "CurrentDoorState":
				switch ($homeKitValue) {
					case 0: //HMCharacteristicValueDoorState::Open
						$targetValueString = $this->ReadPropertyString("CurrentDoorStateOpen");
						break;
					case 1: //HMCharacteristicValueDoorState::Closed
						$targetValueString = $this->ReadPropertyString("CurrentDoorStateClosed");
						break;
					case 2: //HMCharacteristicValueDoorState::Opening
						$targetValueString = $this->ReadPropertyString("CurrentDoorStateOpening");
						break;
					case 3: //HMCharacteristicValueDoorState::Closing
						$targetValueString = $this->ReadPropertyString("CurrentDoorStateClosing");
						break;
					case 4: //HMCharacteristicValueDoorState::Stopped
						$targetValueString = $this->ReadPropertyString("CurrentDoorStateStopped");
						break;
				}
				break;
			case "CurrentTemperature": // value has to be float
			case "TargetTemperature": // value has to be float
			default:
				return $homeKitValue;
		}
		
		if ($targetValueString == "") return $homeKitValue;
		
		switch ($variable["VariableType"]) {
			case 0: // boolean
				return boolval($targetValueString);
			case 1: // integer
				return intval($targetValueString);
			case 2: // float
				return floatval($targetValueString);
			case 3: // string
				return $targetValueString;
			default:
				return $homeKitValue;
		}
	}
	
	private function GetHomeKitValue($variableId, $homeKitVariableType) {
		$variable = IPS_GetVariable($variableId);
		$value = GetValue($variableId);
		$valueString = strval($value);
		
		switch ($homeKitVariableType) {
			case "PowerState":
				return $valueString == $this->ReadPropertyString("PowerStateOn");
			case "Brightness":
				$maxValue = $this->ReadPropertyFloat("BrightnessMaxValue");
				$targetValue = ($value / $maxValue) * 100;
				return $targetValue;
			case "TargetDoorState":
				switch ($valueString) {
					case $this->ReadPropertyString("TargetDoorStateOpen"):
						return 0; //HMCharacteristicValueDoorState::Open;
					case $this->ReadPropertyString("TargetDoorStateClosed"):
						return 1; //HMCharacteristicValueDoorState::Closed;
					case $this->ReadPropertyString("TargetDoorStateOpening"):
						return 2; //HMCharacteristicValueDoorState::Opening;
					case $this->ReadPropertyString("TargetDoorStateClosing"):
						return 3; //HMCharacteristicValueDoorState::Closing;
					case $this->ReadPropertyString("TargetDoorStateStopped"):
						return 4; //HMCharacteristicValueDoorState::Stopped;
				}
				break;
			case "DoorState":
				switch ($valueString) {
					case $this->ReadPropertyString("CurrentDoorStateOpen"):
						return 0; //HMCharacteristicValueDoorState::Open;
					case $this->ReadPropertyString("CurrentDoorStateClosed"):
						return 1; //HMCharacteristicValueDoorState::Closed;
					case $this->ReadPropertyString("CurrentDoorStateOpening"):
						return 2; //HMCharacteristicValueDoorState::Opening;
					case $this->ReadPropertyString("CurrentDoorStateClosing"):
						return 3; //HMCharacteristicValueDoorState::Closing;
					case $this->ReadPropertyString("CurrentDoorStateStopped"):
						return 4; //HMCharacteristicValueDoorState::Stopped;
				}
				break;
			case "CurrentTemperature": // value has to be float
			case "TargetTemperature": // value has to be float
			default:
				return $value;
		}
		
		return $value;
	}
}

/*
class HMCharacteristicValueDoorState extends SplEnum {
	const __default = self::Open;

	const Open = 0;
	const Closed = 1;
	const Opening = 2;
	const Closing = 3;
	const Stopped = 4;
}

class HMCharacteristicValueHeatingCooling extends SplEnum {
	const __default = self::Off;

	const Off = 0;
	const Heat = 1;
	const Cool = 2;
	const Auto = 3;
}*/

?>