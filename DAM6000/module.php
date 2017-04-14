<?
class WHDDAM6000 extends IPSModule {

	public function Create() {
		//Never delete this line!
		parent::Create();
		
		$this->RegisterPropertyString("Host", "");
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();
		
		// connect to parent
		$host = $this->ReadPropertyString("Host");
		if (strlen($host) == 0) return;
		
		$this->ConnectAndConfigureParent($host);
		
		// get source list to update variable profile
		$this->GetSourceList();
		
		// reset properties
		$this->ResetMessageBuffer();
	}

	public function ResetMessageBuffer() {
		$this->SetBuffer("MessageBuffer", "");
		$this->SetBuffer("MessageLength", "-1");
	}

	private function WriteProperty($name, $value) {	
		if(!isset($this->Configuration))
			$this->Configuration = json_decode(IPS_GetConfiguration($this->InstanceID));
		
		if(!isset($this->Configuration->$name))
			throw new Exception("Invalid Property $name");
		
		$this->Configuration->$name = $value;
		IPS_SetConfiguration($this->InstanceID, json_encode($this->Configuration));
	}
	
	public function ForwardData($jsonString) {
		$response = json_decode($jsonString);
		
		switch($response->DataID) {
			case "{31D661FC-4C47-42B2-AD7E-0D87064D780A}": // message from WHDDAM6000Group
				switch($response->ValueType) {
					case "Volume":
						$this->SetGroupVolume($response->Group, $response->Value);
						break;
					case "Source":
						$this->SetGroupSource($response->Group, $response->Value);
						break;
					case "Mute":
						$this->SetGroupMute($response->Group, $response->Value);
						break;
				}		
				break;
			default:
				IPS_LogMessage('WHD DAM 6000', "Error: Invalid DataID!");
				break;
		}
	}

	public function ReceiveData($jsonString) {
		$jsonString = preg_replace('/[\\\]"}$/i', '\\\\\\"}', $jsonString);
		$response = json_decode($jsonString);
		
		switch($response->DataID) {
			case "{018EF6B5-AB94-40C6-AA53-46943E824ACF}": // Client Socket --> WHD response
				$messageBuffer = $this->GetBuffer("MessageBuffer");
				$messageLength = intval($this->GetBuffer("MessageLength"));
						
				$hexMessage = bin2hex(utf8_decode($response->Buffer));
				$messageBuffer .= $hexMessage;
				
				if ($messageLength === -1)
					$messageLength = hexdec(substr($hexMessage, 4, 2));
				
				if (strlen($messageBuffer) !== 6 + $messageLength * 2) {
					$this->SetBuffer("MessageBuffer", $messageBuffer);
					$this->SetBuffer("MessageLength", strval($messageLength));
				} else {
					$this->ResetMessageBuffer();
					$message = $this->DecodeDamMessage($messageBuffer);
					$this->ProcessResponse($message);
				}
				break;
			default:
				IPS_LogMessage('WHD DAM 6000', "Error: Invalid DataID!");
				break;
		}
	}

	private function ProcessResponse($xml) {
		switch($xml->getName()) {
			case "AckSourceList":
				$sourceList = [];
				foreach($xml->SourceList->children() as $child)
					array_push($sourceList, Array(intval($child->Id), strval($child->Name), "", -1));
				$this->RegisterProfileIntegerEx("Source.WHDDAM6000", "Information", "", "", $sourceList);
				break;
			case "AckGroup":
				$name = strval($xml->Group->Name);
				if (strpos($name, ":") > 0) $name = substr($name, strpos($name, ":") + 1);
			
				$groupInfo = Array(
					"DataID" => "{60D2151B-5D26-4B4F-8C98-A6CD451846D0}",
					"Group" => intval($xml->Group->Id),
					"Name" => $name,
					"Volume" => intval($xml->Group->Vol),
					"Source" => $xml->Group->DSource
						? intval($xml->Group->DSource->Id)
						: intval($xml->Group->ASource->Id),
					"Mute" => intval($xml->Group->Mute),
					"Standby" => intval($xml->Group->Standby),
					"DNDActive" => intval($xml->Group->DNDActive)
				);
				$this->SendDataToChildren(json_encode($groupInfo));
				break;
			default:
				IPS_LogMessage('WHD DAM 6000', ($message->asXml()));
				break;
		}
	}

	public function GetSourceList() {
		$requestXml = new SimpleXMLElement(
			"<GetSourceList>" .
			"<MsgTag>123</MsgTag>" .
			"</GetSourceList>");
		
		$this->SendRequest($requestXml);
	}

	public function GetGroupList() {
		$requestXml = new SimpleXMLElement(
			"<GetGroupList>" .
			"<MsgTag>123</MsgTag>" .
			"</GetGroupList>");
		
		$this->SendRequest($requestXml);
	}

	public function GetGroup(integer $group) {
		$requestXml = new SimpleXMLElement(
			"<GetGroup>" .
			"<Group>" .
			"<Id>" . $group . "</Id>" .
			"</Group>" .
			"<MsgTag>123</MsgTag>" .
			"</GetGroup>");
		$this->SendRequest($requestXml);
	}
	
	public function SetGroup(integer $group, integer $source = NULL, integer $volume = NULL, boolean $mute = NULL) {
		
		$requestXmlString = "<SetGroup><Group><Id>" . $group . "</Id>";
		
		if ($source !== NULL) {
			$sourceType = $source === 6
				? "DSource"
				: "ASource";			
			
			$requestXmlString .= 
				"<" . $sourceType . ">" .
				"<Id>" . $source . "</Id>" .
				"</" . $sourceType . ">";			
		}
		
		if ($volume !== NULL)
			$requestXmlString .= "<Vol>" . $volume . "</Vol>";
		
		if ($mute !== NULL)
			$requestXmlString .= "<Mute>" . $mute . "</Mute>";
		
		$requestXmlString .= "</Group><MsgTag>123</MsgTag></SetGroup>";
				
		$requestXml = new SimpleXMLElement($requestXmlString);
		$this->SendRequest($requestXml);
	}

	public function SetGroupSource(integer $group, integer $source) {
		$sourceType = $source === 6
			? "DSource"
			: "ASource";
		
		$requestXml = new SimpleXMLElement(
			"<SetGroup>" .
			"<Group>" .
			"<Id>" . $group . "</Id>" .
			"<" . $sourceType . ">" .
			"<Id>" . $source . "</Id>" .
			"</" . $sourceType . ">" .
			"</Group>" .
			"<MsgTag>123</MsgTag>" .
			"</SetGroup>");
		
		$this->SendRequest($requestXml);
	}

	public function SetGroupVolume(integer $group, integer $volume) {
		$requestXml = new SimpleXMLElement(
			"<SetGroup>" .
			"<Group>" .
			"<Id>" . $group . "</Id>" .
			"<Vol>" . $volume . "</Vol>" .
			"</Group>" .
			"<MsgTag>123</MsgTag>" .
			"</SetGroup>");
		
		$this->SendRequest($requestXml);
	}

	public function SetGroupMute(integer $group, boolean $mute) {
		$requestXml = new SimpleXMLElement(
			"<SetGroup>" .
			"<Group>" .
			"<Id>" . $group . "</Id>" .
			"<Mute>" . $mute . "</Mute>" .
			"</Group>" .
			"<MsgTag>123</MsgTag>" .
			"</SetGroup>");
		
		$this->SendRequest($requestXml);
	}

	private function SendRequest($requestXml) {
		$data = $this->EncodeDamMessage($requestXml);
		$this->SendDataToParent(json_encode(Array(
			"DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}",
			"Buffer" => hex2bin($data)
		)));
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

	private function DecodeDamMessageName($messageId) {
		switch($messageId) {
			case 32: return "Id";
			case 34: return "Name";
			case 35: return "Vol";
			case 45: return "Status";
			case 49: return "DAMDatetime";
			case 59: return "DNDActive";
			case 60: return "Mute";
			case 61: return "LSM";
			case 62: return "LSMList";
			case 63: return "GroupList";
			case 64: return "Group";
			case 84: return "DSource";
			case 85: return "ASource";
			case 87: return "SourceList";
			case 91: return "Standby";
			case 99: return "MsgTag";
			case 100: return "SetGroup";
			case 101: return "AckGroup";
			case 104: return "GetGroup";
			case 106: return "GetGroupList";
			case 107: return "AckGroupList";
			case 119: return "GetSourceList";
			case 120: return "AckSourceList";
			default: return $messageId;
		}
	}

	private function EncodeDamMessageName($messageName) {
		switch($messageName) {
			case "Id": return 32;
			case "Name": return 34;
			case "Vol": return 35;
			case "Status": return 45;
			case "DAMDatetime": return 49;
			case "DNDActive": return 59;
			case "Mute": return 60;
			case "LSM": return 61;
			case "LSMList": return 62;
			case "GroupList": return 63;
			case "Group": return 64;
			case "DSource": return 84;
			case "ASource": return 85;
			case "SourceList": return 87;
			case "Standby": return 91;
			case "MsgTag": return 99;
			case "SetGroup": return 100;
			case "AckGroup": return 101;
			case "GetGroup": return 104;
			case "GetGroupList": return 106;
			case "AckGroupList": return 107;
			case "GetSourceList": return 119;
			case "AckSourceList": return 120;
			default: return $messageName;
		}
	}

	private function GetDamMessageType($messageName) {
		switch($messageName) {
			case "LSM":
			case "LSMList":
			case "GroupList":
			case "Group":
			case "DSource":
			case "ASource":
			case "SourceList":
			case "SetGroup":
			case "AckGroup":
			case "GetGroup":
			case "GetGroupList":
			case "AckGroupList":
			case "GetSourceList":
			case "AckSourceList":
				return 127; // constructed type
			case "Id":
			case "Name":
			case "Vol":
			case "Status":
			case "DAMDatetime":
			case "DNDActive":
			case "Mute":
			case "Standby":
			case "MsgTag":
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
				return hexdec($value) == 255
				? 1
				: 0; // boolean
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
				return $this->GetHexValue(intval($value)); // int
			case 59: // DND-active
			case 60: // Mute
			case 91: // Standby
				$return = boolval($value)
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

	private function EncodeDamMessage($xml) {
		$hexMessage = "";
		
		$messageName = $xml->getName();
		$messageType = $this->GetDamMessageType($messageName);
		$messageId = $this->EncodeDamMessageName($messageName);
		
		if ($xml->count() > 0) {
			$value = "";
			foreach ($xml->children() as $child)
				$value .= $this->EncodeDamMessage($child);
		} else {
			$value = $this->EncodeDamMessageValue((string)$xml, $messageId);
		}
		
		$length = strlen(hex2bin($value));
		$hexMessage .= $this->GetHexValue($messageType) 
			. $this->GetHexValue($messageId) 
			. $this->GetHexValue($length) 
			. $value;
		
		return $hexMessage;
	}

	private function DecodeDamMessage($hexMessage, $parentMessageName = "") {
		$xml = $parentMessageName === ""
			? NULL
			: new SimpleXMLElement("<" . $parentMessageName . "></" . $parentMessageName . ">");
		
		while(strlen($hexMessage) > 0) {
			$messageType = hexdec(substr($hexMessage, 0, 2));
			$messageId = hexdec(substr($hexMessage, 2, 2));
			$messageName = $this->DecodeDamMessageName($messageId);
			$length = hexdec(substr($hexMessage, 4, 2));
			$hexValue = substr($hexMessage, 6, $length * 2);
			
			if ($messageType == 127) { // constructed type
				$messageXml = $this->DecodeDamMessage($hexValue, $messageName);
				if ($xml === NULL) $xml = $messageXml;
				else $this->xml_adopt($xml, $messageXml);
			} else if ($messageType == 95) { // primitive type
				$value = $this->DecodeDamMessageValue($hexValue, $messageId);
				$xml->addChild($messageName, $value);
			} else {
				IPS_LogMessage('WHD DAM 6000', 'Error occurred during message decoding!');
			}
			
			$hexMessage = substr($hexMessage, 6 + (2 * $length));
		}

		$this->SendDebug("Decode DAM message result", $xml->asXml(), 0);
		return $xml;
	}

	private function xml_adopt($root, $new, $namespace = null) {
		// first add the new node
		$node = $root->addChild($new->getName(), (string) $new, $namespace);
		
		// add any attributes for the new node
		foreach($new->attributes() as $attr => $value)
			$node->addAttribute($attr, $value);

		// get all namespaces, include a blank one
		$namespaces = array_merge(array(null), $new->getNameSpaces(true));
		
		// add any child nodes, including optional namespace
		foreach($namespaces as $space)
			foreach ($new->children($space) as $child)
				$this->xml_adopt($node, $child, $space);
	}

	private function RegisterProfileInteger($name, $icon, $prefix, $suffix, $minValue, $maxValue, $stepSize) {
		if(!IPS_VariableProfileExists($name)) {
			IPS_CreateVariableProfile($name, 1);
		} else {
			$profile = IPS_GetVariableProfile($name);
			if($profile['ProfileType'] != 1)
				throw new Exception("Variable profile type does not match for profile " . $name);
		}

		IPS_SetVariableProfileIcon($name, $icon);
		IPS_SetVariableProfileText($name, $prefix, $suffix);
		IPS_SetVariableProfileValues($name, $minValue, $maxValue, $stepSize);
	}

	private function RegisterProfileIntegerEx($name, $icon, $prefix, $suffix, $associations) {
		if ( sizeof($associations) === 0 ){
			$minValue = 0;
			$maxValue = 0;
		} else {
			$minValue = $associations[0][0];
			$maxValue = $associations[sizeof($associations)-1][0];
		}

		$this->RegisterProfileInteger($name, $icon, $prefix, $suffix, $minValue, $maxValue, 0);

		foreach($associations as $association)
			IPS_SetVariableProfileAssociation($name, $association[0], $association[1], $association[2], $association[3]);
	}

}
?>