<?
class LCNKeys extends IPSModule {

	public function Create() {
		//Never delete this line!
		parent::Create();
		
		$this->RegisterPropertyInteger("Module", 0);

		$keyTableConfig = "[" .
			"{\"keyName\":\"X1\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X2\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X3\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X4\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X5\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X6\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X7\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X8\",\"hit\":0,\"make\":0,\"break\":0}" . 
			"]";

		$this->RegisterPropertyString("KeyTableA", str_replace("X", "A", $keyTableConfig));
		$this->RegisterPropertyString("KeyTableB", str_replace("X", "B", $keyTableConfig));
		$this->RegisterPropertyString("KeyTableC", str_replace("X", "C", $keyTableConfig));
		$this->RegisterPropertyString("KeyTableD", str_replace("X", "D", $keyTableConfig));
	}

	public function ApplyChanges() {

		//Never delete this line!
		parent::ApplyChanges();

		// connect to LCN Gateway
		//$this->ConnectParent('{9BDFC391-DEFF-4B71-A76B-604DBA80F207}');

		$lcnModuleInstanceId = $this->ReadPropertyInteger("Module");
		if ($lcnModuleInstanceId == 0) return;

		$lcnDataInstanceId = $this->GetLcnDataInstanceId();
		$this->UpdateLcnDataConnection($lcnDataInstanceId, $lcnModuleInstanceId);
		$this->CreateEventOnLcnDataCommandVariable($lcnDataInstanceId);
	}

	private function GetLcnDataInstanceId() {
		$childrenIds = IPS_GetChildrenIDs($this->InstanceID);
		$lcnDataModuleId = "{A26E7E5A-A7C5-4063-8BE0-ED8BB26F8411}";
		$lcnDataInstanceId = 0;

		foreach ($childrenIds as $childId) {
			$childObject = IPS_GetObject($childId);
			if ($childObject["ObjectType"] != 1) continue;
			
			$childInstance = IPS_GetInstance($childId);
			if ($childInstance["ModuleInfo"]["ModuleID"] != $lcnDataModuleId) continue;
			
			$lcnDataInstanceId = $childId;
			break;
		}

		if ($lcnDataInstanceId == 0) {
			$lcnDataInstanceId = IPS_CreateInstance($lcnDataModuleId);
			IPS_SetName($lcnDataInstanceId, "LCN Data");
			IPS_SetParent($lcnDataInstanceId, $this->InstanceID);
			IPS_SetHidden($lcnDataInstanceId, true);
			IPS_SetConfiguration($lcnDataInstanceId, "{\"DataType\": 4}");
			IPS_ApplyChanges($lcnDataInstanceId);
		}

		return $lcnDataInstanceId;
	}

	private function UpdateLcnDataConnection(int $lcnDataInstanceId, int $lcnModuleInstanceId) {
		$lcnDataInstance = IPS_GetInstance($lcnDataInstanceId);

		if ($lcnDataInstance["ConnectionID"] != $lcnModuleInstanceId && $lcnModuleInstanceId != 0) {
			if($lcnDataInstance["ConnectionID"] > 0) {
				IPS_DisconnectInstance($lcnDataInstanceId);
			}
			IPS_ConnectInstance($lcnDataInstanceId, $lcnModuleInstanceId);
		}
	}

	private function CreateEventOnLcnDataCommandVariable(int $lcnDataInstanceId) {
		$commandVariableId = IPS_GetObjectIDByIdent("CData", $lcnDataInstanceId);
		$variableEventIds = IPS_GetVariableEventList($commandVariableId);
		foreach($variableEventIds as $eventId) {
			if (IPS_EventExists($eventId))
				IPS_DeleteEvent($eventId);
		}

		$eventId = IPS_CreateEvent(0);
		IPS_SetEventTrigger($eventId, 0, $commandVariableId);
		IPS_SetParent($eventId, $commandVariableId);
		IPS_SetEventScript($eventId, "LCNKEYS_HandleKeyEvent(" . $this->InstanceID . ", GetValue(\$_IPS['TARGET']));");
		IPS_SetEventActive($eventId, true);
	}

	public function HandleKeyEvent(string $skhData) {
		$this->SendDebug("Raw SKH-Data", $skhData, 0);

		if (strlen($skhData) != 6) return;

		$key = strtoupper(dechex(intval(substr($skhData, 0, 3))));
		$commandType = dechex(intval(substr($skhData, 3, 3)));
		switch ($commandType) {
			case "1":
				$commandType = "hit";
				break;
			case "2":
				$commandType = "make";
				break;
			case "3":
				$commandType = "break";
				break;
			default:
				return;			
		}
		
		$keyTable = substr($key, 0, 1);
		$keyNo = intval(substr($key, 1, 1));

		$this->SendDebug("Key", $key, 0);
		$this->SendDebug("Command-Type", $commandType, 0);

		$this->RunKeyScript($keyTable, $keyNo, $commandType);
	}

	public function CreateScriptForCompleteKeyTable(string $keyTable) {
		if ($keyTable != "A" && $keyTable != "B" && $keyTable != "C" && $keyTable != "D") return;
		
		$scriptId = IPS_CreateScript(0);
		IPS_SetName($scriptId, "Key-Table $keyTable-Script");
		IPS_SetParent($scriptId, $this->InstanceID);
		IPS_SetScriptContent($scriptId, "<?

if (\$_IPS['KeyTable'] != '$keyTable') return;

switch(\$_IPS['KeyNo']) {
	case 1:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	case 2:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	case 3:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	case 4:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	case 5:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	case 6:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	case 7:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	case 8:
		switch(\$_IPS['CommandType']) {
			case 'hit':
				break;
			case 'make':
				break;
			case 'break':
				break;
			default:
				return;
		}
		break;
	default:
		return;
}

?>");
		$this->SetScriptForCompleteKeyTable($keyTable, $scriptId);
	}

	public function SetScriptForCompleteKeyTable(string $keyTable, int $scriptId) {
		if ($keyTable != "A" && $keyTable != "B" && $keyTable != "C" && $keyTable != "D") return;

		$keyTableConfig = "[" .
			"{\"keyName\":\"X1\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X2\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X3\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X4\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X5\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X6\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X7\",\"hit\":0,\"make\":0,\"break\":0}," . 
			"{\"keyName\":\"X8\",\"hit\":0,\"make\":0,\"break\":0}" . 
			"]";
		$keyTableConfig = str_replace("X", $keyTable, $keyTableConfig);
		$keyTableConfig = str_replace("0", $scriptId, $keyTableConfig);

		$config = json_decode(IPS_GetConfiguration($this->InstanceID), true);
		$config["KeyTable" . $keyTable] = $keyTableConfig;
		
		IPS_SetConfiguration($this->InstanceID, json_encode($config));
		IPS_ApplyChanges($this->InstanceID);
	}

	private function RunKeyScript(string $keyTable, int $keyNo, string $commandType) {
		if ($keyNo < 1 || $keyNo > 8) return;

		$keyTableString = $this->ReadPropertyString("KeyTable" . $keyTable);
		$keyTableKeys = json_decode($keyTableString, true);

		$keyConfig = $keyTableKeys[$keyNo - 1];
		$this->SendDebug("Target key config", json_encode($keyConfig), 0);

		$targetScriptId = $keyConfig[$commandType];

		if ($targetScriptId == 0 ) {
			$this->SendDebug("Script", "No Script configured for this key and command type!", 0);
		} else {
			$this->SendDebug("Script", "Run Script with id: " . $targetScriptId, 0);
			IPS_RunScriptEx($targetScriptId, Array(
				"KeyTable" => $keyTable,
				"KeyNo" => $keyNo,
				"CommandType" => $commandType,
				"ModuleInstanceID" => $this->ReadPropertyInteger("Module")));
		}
	}
}
?>