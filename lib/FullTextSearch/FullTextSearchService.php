<?php
declare(strict_types=1);


/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018
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


use OC\FullTextSearch\Model\DocumentAccess;
use OC\FullTextSearch\Model\IndexDocument;
use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCP\Comments\IComment;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\FullTextSearch\Model\IDocumentAccess;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\IURLGenerator;
use Symfony\Component\EventDispatcher\GenericEvent;


/**
 * Class FullTextSearchService
 *
 * @package OCA\Spreed\FullTextSearch
 */
class FullTextSearchService {


	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var Manager */
	private $roomManager;

	/** @var ChatManager */
	private $chatManager;

	/** @var IFullTextSearchManager */
	private $fullTextSearchManager;


	/**
	 * FullTextSearchService constructor.
	 *
	 * @param IURLGenerator $urlGenerator
	 * @param Manager $roomManager
	 * @param ChatManager $chatManager
	 * @param IFullTextSearchManager $fullTextSearchManager
	 */
	public function __construct(
		IUrlGenerator $urlGenerator, Manager $roomManager, ChatManager $chatManager,
		IFullTextSearchManager $fullTextSearchManager
	) {
		$this->urlGenerator = $urlGenerator;
		$this->roomManager = $roomManager;
		$this->chatManager = $chatManager;
		$this->fullTextSearchManager = $fullTextSearchManager;
	}


	public function onSendMessage(GenericEvent $e) {
		/** @var IComment $comment */
		$comment = $e->getArgument('comment');
		if ($comment->getObjectType() !== 'chat') {
			return;
		}

		$this->fullTextSearchManager->createIndex(
			TalkProvider::SPREED_PROVIDER_ID, $comment->getObjectId(), $comment->getActorId(),
			IIndex::INDEX_FULL
		);
	}


	/**
	 * @param IIndexDocument $document
	 */
	public function generateDocument(IIndexDocument $document) {
		$document->setAccess($this->generateDocumentAccessForRoom($document->getId()));

		$this->fillContent($document);
	}


	/**
	 * @param IIndex $index
	 *
	 * @return IIndexDocument
	 */
	public function updateDocument(IIndex $index): IIndexDocument {
		$document = new IndexDocument(TalkProvider::SPREED_PROVIDER_ID, $index->getDocumentId());

		$this->generateDocument($document);
		$document->setIndex($index);

		return $document;
	}


	/**
	 * @param ISearchResult $searchResult
	 */
	public function improveSearchResult(ISearchResult $searchResult) {
		foreach ($searchResult->getDocuments() as $document) {
			try {
				$board =
					$this->roomManager->getRoomById((int)$document->getId());
				$document->setLink(
					$this->urlGenerator->linkToRoute(
						'spreed.pagecontroller.showCall', ['token' => $board->getToken()]
					)
				);
			} catch (RoomNotFoundException $e) {
			}
		}
	}


	/**
	 * @param IIndexDocument $document
	 */
	private function fillContent(IIndexDocument $document) {
		$room = $this->roomManager->getRoomById((int)$document->getId());
		$document->setTitle($room->getName());

		$all = $this->chatManager->getHistory($room, 0, 100);

		$comments = [];
		foreach ($all as $comment) {
			if ($comment->getVerb() !== 'comment') {
				continue;
			}

			$dTime = $comment->getCreationDateTime();
			$date = $dTime->format('ymd');
			if (!array_key_exists($date, $comments)) {
				$comments[$date] = [];
			}

			$comments[$date][] = '<' . $comment->getActorId() . '> ' . $comment->getMessage();
		}

		foreach (array_keys($comments) as $day) {
			$document->addPart((string)$day, implode(" \n ", $comments[$day]));
		}
	}


	/**
	 * @param string $roomId
	 *
	 * @return IDocumentAccess
	 */
	private function generateDocumentAccessForRoom(string $roomId): IDocumentAccess {
		$room = $this->roomManager->getRoomById((int)$roomId);

		$ownerId = '';
		$users = [];
		$participants = $room->getParticipants();
		foreach ($participants as $participant) {
			switch ($participant->getParticipantType()) {
				case Participant::OWNER:
					$ownerId = $participant->getUser();
					break;

				case Participant::USER:
				case Participant::MODERATOR:
				case Participant::USER_SELF_JOINED:
					$users[] = $participant->getUser();
					break;
			}
		}

		$access = new DocumentAccess($ownerId);
		$access->addUsers($users);

		return $access;
	}

}

