<?php /** @noinspection PhpMissingFieldTypeInspection */


class Translation extends DataObject {
	public $__table = 'translations';
	public $id;
	public $termId;
	public $languageId;
	public $translation;
	public $translated;
	public $googleTranslated;
	public $needsReview;
	public $lastCheckInCommunity;

	public function getNumericColumnNames(): array {
		return [
			'termId',
			'languageId',
			'translated',
			'needsReview',
		];
	}

	public function setTranslation($translation, $term = null) : void {
		if ($this->translation == $translation) {
			//Nothing to do, exit early
			return;
		}
		$this->translation = $translation;
		$this->translated = 1;
		$this->needsReview = 0;
		$this->update();

		if ($term == null) {
			$term = new TranslationTerm();
			$term->setId($this->termId);
			$term->find(true);
		}
		global $memCache;
		global $activeLanguage;
		$memCache->delete('translation_' . $activeLanguage->id . '_0_' . $term->getTerm());
		$memCache->delete('translation_' . $activeLanguage->id . '_1_' . $term->getTerm());

		//Send the translation to the community content server
		require_once ROOT_DIR . '/sys/SystemVariables.php';
		$systemVariables = SystemVariables::getSystemVariables();
		if ($systemVariables && !empty($systemVariables->communityContentUrl)) {
			require_once ROOT_DIR . '/sys/CurlWrapper.php';
			$curl = new CurlWrapper();
			$body = [
				'term' => $term->getTerm(),
				'translation' => $translation,
				'languageCode' => $activeLanguage->code,
			];
			/** @noinspection PhpUnusedLocalVariableInspection */
			$response = $curl->curlPostPage($systemVariables->communityContentUrl . '/API/CommunityAPI?method=setTranslation', $body);
			//$response = json_decode($response);
		}
	}

	protected function clearCachedData() : void {
		global $memCache;
		//The term lookup is only worth doing when there is shared memory to clear
		if (!empty($this->termId) && $memCache instanceof Memcache && $memCache->isSharedMemoryEnabled()) {
			$term = new TranslationTerm();
			$term->setId($this->termId);
			if ($term->find(true)) {
				require_once ROOT_DIR . '/sys/Translation/Translator.php';
				//Deleting a term removes its translations by termId alone, so clear every language then
				Translator::clearDatabaseTranslation($term->getTerm(), empty($this->languageId) ? null : (int)$this->languageId);
			}
		}
	}
}
