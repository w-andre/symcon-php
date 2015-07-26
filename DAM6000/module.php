<?
class WHDDAM6000 extends IPSModule {

	public function __construct($InstanceID) {
		//Never delete this line!
		parent::__construct($InstanceID);
		
		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyString("Host", "");
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();
		
		// connect to parent
		$host = $this->ReadPropertyString("Host");
		$this->ConnectAndConfigureParent($host);
		
		// get source list to update variable profile
		$this->GetSourceList();
	}

	public function ReceiveData($jsonString) {
		IPS_LogMessage('WHD DAM 6000', json_encode($jsonString));
	}

	public function GetSourceList() {
		$request = Array(
			"Get-Source-list" => Array(
				Array("Msg-tag" => 123)
			)
		);
		$this->SendRequest($request);
	}

	public function GetGroupList() {
		$request = Array(
			"Get-Group-list" => Array(
				Array("Msg-tag" => 123)
			)
		);
		$this->SendRequest($request);
	}

	public function GetGroup($group) {
		$request = Array(
			"Get-Group" => Array(
				Array(
					"Group" => Array(
						"Id" => $group
					)
				),
				Array("Msg-tag" => 123)
			)
		);
		$this->SendRequest($request);
	}

	public function SetGroupSource($group, $source) {
		
		$sourceType = $source === 6
			? "DSource"
			: "ASource";
		
		$request = Array(
			"Set-Group" => Array(
				Array(
					"Group" => Array(
						Array("Id" => $group),
						Array($sourceType => $source)
					)
				),
				Array("Msg-tag" => 123)
			)
		);
		$this->SendRequest($request);
	}

	public function SetGroupVolume($group, $volume) {
		$request = Array(
			"Set-Group" => Array(
				Array(
					"Group" => Array(
						Array("Id" => $group),
						Array("Vol" => $volume)
					)
				),
				Array("Msg-tag" => 123)
			)
		);
		$this->SendRequest($request);
	}

	public function SetGroupMute($group, $mute) {
		$request = Array(
			"Set-Group" => Array(
				Array(
					"Group" => Array(
						Array("Id" => $group),
						Array("Mute" => $volume)
					)
				),
				Array("Msg-tag" => 123)
			)
		);
		$this->SendRequest($request);
	}

	private function SendRequest($request) {
		$data = $this->EncodeDamMessage($request);
		$this->SendDataToParent(hex2bin($data));
	}

	private function ConnectAndConfigureParent($host) {
		$moduleId = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
		$instance = IPS_GetInstance($this->InstanceID);
		$parentInstance = NULL;
		
		if($instance['ConnectionID'] == 0) {
			// instance has no connected parent
			
			// search if parent instance does already exist
			$ids = IPS_GetInstanceListByModuleID($moduleId);
			for ($i = 0; $i < sizeof($ids); $i++) {
				$name = IPS_GetName($ids[$i]);
				if ($name == "WHD DAM 6000 Client Socket") {
					// parent instance found --> connect
					$parentInstance = IPS_GetInstance($ids[$i]);
					IPS_ConnectInstance($this->InstanceID, $ids[$i]);
					break;
				}
			}
			
			if(!isset($parentInstance)) {
				// instance does not exist yet --> create a new one
				$parentId = IPS_CreateInstance($moduleId);
				$parentInstance = IPS_GetInstance($parentId);
				IPS_SetName($parentId, "WHD DAM 6000 Client Socket");
				IPS_ConnectInstance($this->InstanceID, $parentId);
			
			}
		} else {
			// instance already has a connected parent --> get instance
			$parentInstance = IPS_GetInstance($instance['ConnectionID']);
		}
		
		// update parent instance configuration
		IPS_SetConfiguration($parentInstance['InstanceID'], json_encode(Array(
			"Open" => true,
			"Host" => $host,
			"Port" => 6000
		)));
		IPS_ApplyChanges($parentInstance['InstanceID']);
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


	private function DecodeDamMessageName($messageId) {
		switch($messageId) {
			case 32: return "Id";
			case 34: return "Name";
			case 35: return "Vol";
			case 45: return "Status";
			case 49: return "DAM-Datetime";
			case 59: return "DND-active";
			case 60: return "Mute";
			case 61: return "LSM";
			case 62: return "LSM-list";
			case 63: return "Group-list";
			case 64: return "Group";
			case 84: return "DSource";
			case 85: return "ASource";
			case 87: return "Source-list";
			case 91: return "Standby";
			case 99: return "Msg-tag";
			case 100: return "Set-Group";
			case 101: return "Ack-Group";
			case 104: return "Get-Group";
			case 106: return "Get-Group-list";
			case 107: return "Ack-Group-list";
			case 119: return "Get-Source-list";
			case 120: return "Ack-Source-list";
			default: return $messageId;
		}
	}

	private function EncodeDamMessageName($messageName) {
		switch($messageName) {
			case "Id": return 32;
			case "Name": return 34;
			case "Vol": return 35;
			case "Status": return 45;
			case "DAM-Datetime": return 49;
			case "DND-active": return 59;
			case "Mute": return 60;
			case "LSM": return 61;
			case "LSM-list": return 62;
			case "Group-list": return 63;
			case "Group": return 64;
			case "DSource": return 84;
			case "ASource": return 85;
			case "Source-list": return 87;
			case "Standby": return 91;
			case "Msg-tag": return 99;
			case "Set-Group": return 100;
			case "Ack-Group": return 101;
			case "Get-Group": return 104;
			case "Get-Group-list": return 106;
			case "Ack-Group-list": return 107;
			case "Get-Source-list": return 119;
			case "Ack-Source-list": return 120;
			default: return $messageName;
		}
	}

	private function GetDamMessageType($messageName) {
		switch($messageName) {
			case "LSM":
			case "LSM-list":
			case "Group-list":
			case "Group":
			case "DSource":
			case "ASource":
			case "Source-list":
			case "Set-Group":
			case "Ack-Group":
			case "Get-Group":
			case "Get-Group-list":
			case "Ack-Group-list":
			case "Get-Source-list":
			case "Ack-Source-list":
				return 127; // constructed type
			case "Id":
			case "Name":
			case "Vol":
			case "Status":
			case "DAM-Datetime":
			case "DND-active":
			case "Mute":
			case "Standby":
			case "Msg-tag":
			default:
				return 95; // primitive type
		}
	}

	private function DecodeDamMessageValue($value, $messageId) {
		switch($messageId) {
			case 32: // Id
			case 35: // Vol
			case 45: // Status
			case 99: // Msg-tag
				return hexdec($value); // int
			case 59: // DND-active
			case 60: // Mute
			case 91: // Standby
				return hexdec($value) == 255; // boolean
			case 34: // Name
				return hex2bin($value); // string
			default:
				return $value;
		}
	}

	private function EncodeDamMessageValue($value, $messageId) {
		switch($messageId) {
			case 32: // Id
			case 35: // Vol
			case 45: // Status
			case 99: // Msg-tag
				return $this->GetHexValue($value); // int
			case 59: // DND-active
			case 60: // Mute
			case 91: // Standby
				$return = $value
					? $this->GetHexValue(255)
					: $this->GetHexValue(0); // boolean
			case 34: // Name
				return bin2hex($value); // string
			default:
				return $value;
		}
	}

	private function GetHexValue($value) {
		return substr("00" . dechex($value), -2);
	}

	private function EncodeDamMessage($message, $parentMessageId = -1) {
		$hexMessage = "";
		if (!is_array($message))
			return $this->EncodeDamMessageValue($message, $parentMessageId);

		foreach ($message as $key => $value) {
			if (is_string($key)) {
				$messageType = $this->GetDamMessageType($key);
				$messageId = $this->EncodeDamMessageName($key);
				$value = $this->EncodeDamMessage($value, $messageId);
				$length = strlen(hex2bin($value));
				$hexMessage .= $this->GetHexValue($messageType) 
					. $this->GetHexValue($messageId) 
					. $this->GetHexValue($length) 
					. $value;
			} else {
				$value = $this->EncodeDamMessage($value, $parentMessageId);
				$hexMessage .= $value;
			}
		}
		return $hexMessage;
	}

	private function DecodeDamMessage($hexMessage) {
		$message = [];
		
		while(strlen($hexMessage) > 0) {
			$messageType = hexdec(substr($hexMessage, 0, 2));
			$messageId = hexdec(substr($hexMessage, 2, 2));
			$messageName = $this->DecodeDamMessageName($messageId);
			$length = hexdec(substr($hexMessage, 4, 2));
			$hexValue = substr($hexMessage, 6, $length * 2);
			
			if ($messageType == 127) { // constructed type
				$value = $this->DecodeDamMessage($hexValue);
			} else if ($messageType == 95) { // primitive type
				$value = $this->DecodeDamMessageValue($hexValue, $messageId);
			} else {
				echo "ERROR";
			}
			
			array_push($message, Array($messageName => $value));
			
			$hexMessage = substr($hexMessage, 6 + (2 * $length));
		}
		return $message;
	}

}
?>