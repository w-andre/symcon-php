<?
class SonosGatway extends IPSModule {

	public function Create() {
		//Never delete this line!
		parent::Create();
	}

	public function ApplyChanges() {

		//Never delete this line!
		parent::ApplyChanges();

	}


	public function RequestAction($ident, $value) {
		switch ($ident) {
			default:
				throw new Exception("Invalid ident");
		}
	}
}
?>