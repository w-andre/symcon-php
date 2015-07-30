<?
class WHDDAM6000Group extends IPSModule {

	public function Create() {
		//Never delete this line!
		parent::Create();

		$this->RegisterPropertyInteger('Group', 0);
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();

		// connect to parent WHD DAM 6000
		$this->ConnectParent('{92B68DFF-49BA-470B-8200-5FF809E88ED0}');

		// register variables
		$this->RegisterVariableBoolean('Status', 'Status', '~Switch', 10);
		$this->EnableAction('Status');
		$this->RegisterVariableInteger('Volume', 'Volume', '~Intensity.100', 20);
		$this->EnableAction('Volume');
		$this->RegisterVariableInteger('Source', 'Source', 'Source.WHDDAM6000', 30);
		$this->EnableAction('Source');
	}

	public function SetVolume($volume) {
		$group = $this->ReadPropertyInteger('Group');
		
		$this->SendDataToParent(json_encode(Array(
			"DataId" => "{31D661FC-4C47-42B2-AD7E-0D87064D780A}",
			"Group" => $group,
			"ValueType" => "Volume",
			"Value" => $volume
		)));
		
		SetValueInteger($this->GetIDForIdent("Volume"), $volume);
	}

	public function SetStatus($mute) {
		$group = $this->ReadPropertyInteger('Group');
		
		$this->SendDataToParent(json_encode(Array(
			"DataId" => "{31D661FC-4C47-42B2-AD7E-0D87064D780A}",
			"Group" => $group,
			"ValueType" => "Status",
			"Value" => $mute
		)));
		
		SetValueBoolean($this->GetIDForIdent("Status"), $mute);
	}

	public function SetSource($sourceId) {
		$group = $this->ReadPropertyInteger('Group');
		
		$this->SendDataToParent(json_encode(Array(
			"DataId" => "{31D661FC-4C47-42B2-AD7E-0D87064D780A}",
			"Group" => $group,
			"ValueType" => "Source",
			"Value" => $sourceId
		)));
		
		SetValueInteger($this->GetIDForIdent("Source"), $sourceId);
	}

	// receive data from parent --> update status
	public function ReceiveData($jsonString) {
		$group = $this->ReadPropertyInteger('Group');
		
		$data = json_decode($jsonString);
		$groupData = $data[$group];
		
		SetValueInteger($this->GetIDForIdent("Volume"), $groupData["Volume"]);
		SetValueBoolean($this->GetIDForIdent("Status"), $groupData["Status"]);
		SetValueInteger($this->GetIDForIdent("Source"), $groupData["Source"]);
	}

	public function RequestAction($ident, $value) {
		switch ($ident) {
			case 'Status':
				SetValueBoolean($this->GetIDForIdent($ident), $value);
				$this->SetStatus($value);
				break;
			case 'Volume':
				SetValueInteger($this->GetIDForIdent($ident), $value);
				$this->SetVolume($value);
				break;
			case 'Source':
				SetValueInteger($this->GetIDForIdent($ident), $value);
				$this->SetSource($value);
				break;
			default:
				throw new Exception('Invalid ident');
		}
	}
}
?>