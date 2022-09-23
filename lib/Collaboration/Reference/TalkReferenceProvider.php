<?php

declare(strict_types = 1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */


namespace OCA\Talk\Collaboration\Reference;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IURLGenerator;

class TalkReferenceProvider implements IReferenceProvider {

	protected IURLGenerator $urlGenerator;
	protected Manager $manager;
	protected ?string $userId;

	public function __construct(IURLGenerator $urlGenerator,
								Manager $manager,
								?string $userId) {
		$this->urlGenerator = $urlGenerator;
		$this->manager = $manager;
		$this->userId = $userId;
	}


	public function matchReference(string $referenceText): bool {
		return $this->getTalkAppLinkToken($referenceText) !== null;
	}

	private function getTalkAppLinkToken(string $referenceText): ?string {
		$indexPhpUrl = $this->urlGenerator->getAbsoluteURL('/index.php/call/');
		if (str_starts_with($referenceText, $indexPhpUrl)) {
			return substr($referenceText, strlen($indexPhpUrl)) ?: null;
		}

		$rewriteUrl = $this->urlGenerator->getAbsoluteURL('/call/');
		if (str_starts_with($referenceText, $rewriteUrl)) {
			return substr($referenceText, strlen($rewriteUrl)) ?: null;
		}

		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$reference = new Reference($referenceText);
			try {
				$this->fetchReference($reference);
			} catch (RoomNotFoundException $e) {
				$reference->setRichObject('call', null);
				$reference->setAccessible(false);
			}
			return $reference;
		}

		return null;
	}

	/**
	 * @throws RoomNotFoundException
	 */
	private function fetchReference(Reference $reference): void {
		if ($this->userId === null) {
			throw new RoomNotFoundException();
		}

		$token = $this->getTalkAppLinkToken($reference->getId());
		if ($token === null) {
			throw new RoomNotFoundException();
		}

		$room = $this->manager->getRoomByToken($token, $this->userId);

		$reference->setTitle($room->getDisplayName($this->userId));
		$reference->setDescription($room->getDescription());
		$reference->setUrl($this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]));
		$reference->setImageUrl($this->getRoomIconUrl($room, $this->userId));

		$reference->setRichObject('call', [
			'id' => $room->getToken(),
			'name' => $reference->getTitle(),
			'link' => $reference->getUrl(),
			'call-type' => $this->getRoomType($room),
		]);
	}

	/**
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->getTalkAppLinkToken($referenceId) ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $this->userId ?? '';
	}

	protected function getRoomIconUrl(Room $room, string $userId): string {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			$participants = json_decode($room->getName(), true);

			foreach ($participants as $p) {
				if ($p !== $userId) {
					return $this->urlGenerator->linkToRouteAbsolute(
						'core.avatar.getAvatar',
						[
							'userId' => $p,
							'size' => 64,
						]
					);
				}
			}
		}

		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('spreed', 'changelog.svg'));
	}

	protected function getRoomType(Room $room): string {
		switch ($room->getType()) {
			case Room::TYPE_ONE_TO_ONE:
				return 'one2one';
			case Room::TYPE_GROUP:
				return 'group';
			case Room::TYPE_PUBLIC:
				return 'public';
			default:
				return 'unknown';
		}
	}
}
