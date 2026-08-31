<?php /** @noinspection PhpMissingFieldTypeInspection */
require_once ROOT_DIR . '/sys/Mobile/SelfCheckBarcode.php';

class AspenPWASelfCheckSetting extends MobileSelfCheckSetting {
	private $_locations;
	private $_barcodeStyles;

	static $_objectStructure = [];
	static function getObjectStructure(string $context = ''): array {
		if (isset(self::$_objectStructure[$context]) && self::$_objectStructure[$context] !== null) {
			return self::$_objectStructure[$context];
		}
		$locationsList = [];
		$location = new Location();
		$location->selectAdd();
		$location->selectAdd('locationId');
		$location->selectAdd('displayName');
		$location->orderBy('displayName');
		if (!UserAccount::userHasPermission('Administer All Locations')) {
			$homeLibrary = Library::getPatronHomeLibrary();
			if (!empty($homeLibrary)) {
				$location->libraryId = $homeLibrary->libraryId;
			}
		}
		$location->find();
		while ($location->fetch()) {
			$locationsList[$location->locationId] = $location->displayName;
		}

		$allBarcodeStyles = MobileSelfCheckBarcode::getObjectStructure($context);

		$checkout_location_options = [
			'0' => 'Current Location User is Logged Into',
			'1' => 'User Home Location',
			'2' => 'Item Location (Koha 23.11+, Sierra, Symphony Only)'
		];
		$keyboardOptions = [
			'0' => 'Do not allow keyboard barcode entry',
			'1' => 'Use numeric keyboard',
			'2' => 'Use alphanumeric keyboard'
		];

		$structure = [
			'id' => [
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id',
			],
			'name' => [
				'property' => 'name',
				'type' => 'text',
				'label' => 'Name',
				'description' => 'The name for these settings',
				'maxLength' => 50,
				'required' => true,
			],
			'isEnabled' => [
				'property' => 'isEnabled',
				'type' => 'checkbox',
				'label' => 'Enable Self-Check',
				'description' => 'Whether or not patrons can self-check using Aspen progressive web app',
				'required' => false,
			],
			'checkoutLocation' => [
				'property' => 'checkoutLocation',
				'type' => 'enum',
				'values' => $checkout_location_options,
				'label' => 'Assign Checkouts To',
				'description' => 'Location where a checkout should be assigned',
				'required' => false,
			],
			'barcodeEntryKeyboardType' => [
				'property' => 'barcodeEntryKeyboardType',
				'type' => 'enum',
				'values' => $keyboardOptions,
				'label' => 'Type of keyboard to use for barcode entry',
				'description' => 'Choose numeric if barcodes only include numbers; alphanumeric if they may include letters',
				'required' => false,
			],
			'barcodeStyles' => [
				'property' => 'barcodeStyles',
				'type' => 'oneToMany',
				'label' => 'Valid Barcode Styles',
				'description' => 'Define valid barcode styles for the location',
				'keyThis' => 'selfCheckSettingsId',
				'subObjectType' => 'AspenLiDASelfCheckBarcode',
				'structure' => $allBarcodeStyles,
				'sortable' => false,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
				'hideInLists' => true,
				'canAddNew' => true,
				'canDelete' => true,
				'note' => 'Only allow the necessary styles. Too many styles have a negative impact on device battery consumption.'
			],
			'locations' => [
				'property' => 'locations',
				'type' => 'multiSelect',
				'listStyle' => 'checkboxSimple',
				'label' => 'Locations',
				'description' => 'Define locations that use these settings',
				'values' => $locationsList,
				'hideInLists' => true,
			],
		];

		if (!UserAccount::userHasPermission('Administer Aspen PWA Self-Check Settings')) {
			unset($structure['locations']);
		}

		self::$_objectStructure[$context] = $structure;
		return self::$_objectStructure[$context];
	}

	/** @noinspection PhpUnusedParameterInspection */
	public function getEditLink(string $context): string {
		return '/AspenPWA/SelfCheckSettings?objectAction=edit&id=' . $this->id;
	}
}