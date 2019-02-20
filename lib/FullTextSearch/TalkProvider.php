<?php
declare(strict_types=1);


/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Spreed\FullTextSearch;


use OC\FullTextSearch\Model\IndexDocument;
use OC\FullTextSearch\Model\SearchTemplate;
use OCA\Spreed\Manager;
use OCP\FullTextSearch\IFullTextSearchPlatform;
use OCP\FullTextSearch\IFullTextSearchProvider;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\IIndexOptions;
use OCP\FullTextSearch\Model\IRunner;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\FullTextSearch\Model\ISearchTemplate;
use OCP\IL10N;


class TalkProvider implements IFullTextSearchProvider {


	const SPREED_PROVIDER_ID = 'spreed';


	/** @var IL10N */
	private $l10n;

	/** @var Manager */
	private $roomManager;

	/** @var FullTextSearchService */
	private $fullTextSearchService;


	/** @var IRunner */
	private $runner;

	/** @var IIndexOptions */
	private $indexOptions = [];


	public function __construct(
		IL10N $l10n, Manager $roomManager, FullTextSearchService $fullTextSearchService
	) {
		$this->l10n = $l10n;
		$this->roomManager = $roomManager;
		$this->fullTextSearchService = $fullTextSearchService;
	}


	/**
	 * return unique id of the provider
	 */
	public function getId(): string {
		return self::SPREED_PROVIDER_ID;
	}


	/**
	 * return name of the provider
	 */
	public function getName(): string {
		return 'Talk';
	}


	/**
	 * @return array
	 */
	public function getConfiguration(): array {
		return [];
	}


	/**
	 * @param IRunner $runner
	 */
	public function setRunner(IRunner $runner) {
		$this->runner = $runner;
	}


	/**
	 * @param IIndexOptions $options
	 */
	public function setIndexOptions(IIndexOptions $options) {
		$this->indexOptions = $options;
	}


	/**
	 * @return ISearchTemplate
	 */
	public function getSearchTemplate(): ISearchTemplate {
		$template = new SearchTemplate('icon-fts-talk', 'fulltextsearch');

		return $template;
	}


	/**
	 *
	 */
	public function loadProvider() {
	}


	/**
	 * @param string $userId
	 *
	 * @return string[]
	 */
	public function generateChunks(string $userId): array {
		return [];
	}


	/**
	 * @param string $userId
	 *
	 * @param string $chunk
	 *
	 * @return IIndexDocument[]
	 */
	public function generateIndexableDocuments(string $userId, string $chunk): array {
		$documents = [];
		$rooms = $this->roomManager->getRoomsForParticipant($userId);
		foreach ($rooms as $room) {
			$document = new IndexDocument(self::SPREED_PROVIDER_ID, (string)$room->getId());
			$document->setTitle($room->getName());

			$documents[] = $document;
		}

		return $documents;
	}


	/**
	 * @param IIndexDocument $document
	 */
	public function fillIndexDocument(IIndexDocument $document) {
		$this->updateRunnerInfoArray(
			[
				'info' => $document->getId(),
				'title' => $document->getTitle()
			]
		);

		$this->fullTextSearchService->generateDocument($document);
	}


	/**
	 * @param IIndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate(IIndexDocument $document): bool {
		return false;
//		return $this->filesService->isDocumentUpToDate($document);
	}


	/**
	 * @param IIndex $index
	 *
	 * @return IIndexDocument
	 */
	public function updateDocument(IIndex $index): IIndexDocument {
		$document = $this->fullTextSearchService->updateDocument($index);
		$this->updateRunnerInfo('info', $document->getId());

		return $document;
	}


	/**
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onInitializingIndex(IFullTextSearchPlatform $platform) {
	}


	/**
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onResettingIndex(IFullTextSearchPlatform $platform) {
	}


	/**
	 * not used yet
	 */
	public function unloadProvider() {
	}


	/**
	 * before a search, improve the request
	 *
	 * @param ISearchRequest $request
	 */
	public function improveSearchRequest(ISearchRequest $request) {
		$request->addPart('*');
	}


	/**
	 * after a search, improve results
	 *
	 * @param ISearchResult $searchResult
	 */
	public function improveSearchResult(ISearchResult $searchResult) {
		$this->fullTextSearchService->improveSearchResult($searchResult);
	}


	/**
	 * @param string $info
	 * @param string $value
	 */
	private function updateRunnerInfo(string $info, string $value) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->setInfo($info, $value);
	}

	/**
	 * @param array $info
	 */
	private function updateRunnerInfoArray(array $info) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->setInfoArray($info);
	}


}
