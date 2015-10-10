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

	public function SetVolume(integer $volume) {
		$group = $this->ReadPropertyInteger('Group');
		
		$this->SendDataToParent(json_encode(Array(
			"DataID" => "{31D661FC-4C47-42B2-AD7E-0D87064D780A}",
			"Group" => $group,
			"ValueType" => "Volume",
			"Value" => $volume
		)));
	}

	public function SetStatus(boolean $status) {
		$group = $this->ReadPropertyInteger('Group');
		
		$this->SendDataToParent(json_encode(Array(
			"DataID" => "{31D661FC-4C47-42B2-AD7E-0D87064D780A}",
			"Group" => $group,
			"ValueType" => "Mute",
			"Value" => $status == 0
		)));
	}

	public function SetSource(integer $sourceId) {
		$group = $this->ReadPropertyInteger('Group');
		
		$this->SendDataToParent(json_encode(Array(
			"DataID" => "{31D661FC-4C47-42B2-AD7E-0D87064D780A}",
			"Group" => $group,
			"ValueType" => "Source",
			"Value" => $sourceId
		)));
	}

	// receive data from parent --> update status
	public function ReceiveData($jsonString) {
		$group = $this->ReadPropertyInteger('Group');
		$response = json_decode($jsonString);
		
		switch($response->DataID) {
			case "{60D2151B-5D26-4B4F-8C98-A6CD451846D0}": // WHD DAM 6000
				if ($group !== $response->Group) return; // message is not for me (different group)
				SetValueInteger($this->GetIDForIdent("Volume"), $response->Volume);
				SetValueBoolean($this->GetIDForIdent("Status"), $response->Mute == 0);
				SetValueInteger($this->GetIDForIdent("Source"), $response->Source);				
				IPS_SetName($this->InstanceID, $response->Name);
				break;
			default:
				IPS_LogMessage('WHD DAM 6000 Group', "Error: Invalid DataID!");
		}
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