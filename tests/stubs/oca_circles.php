<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Circles {
	interface IFederatedUser {
	}
}

namespace OCA\Circles\Model {
	class FederatedUser implements \OCA\Circles\IFederatedUser {
		public function getUserId(): string {
		}
		public function getUserType(): int {
		}
		public function getDisplayName(): string {
		}
		public function isLocal(): bool {
		}
		public function getBasedOn(): ?Circle {
		}
	}
}

namespace OCA\Circles\Events {

	use OCA\Circles\Model\Circle;
	use OCA\Circles\Model\Member;

	class AddingCircleMemberEvent extends CircleMemberGenericEvent {
		public function getCircle(): Circle {
		}
		public function getMember(): Member {
		}
	}

	class RemovingCircleMemberEvent extends CircleMemberGenericEvent {
		public function getCircle(): Circle {
		}
		public function getMember(): Member {
		}
		/** @return \OCA\Circles\Events\CircleGenericEvent::REMOVED|\OCA\Circles\Events\CircleGenericEvent::LEFT */
		public function getType(): int {
		}
		public function getFederatedEvent(): \OCA\Circles\Model\Federated\FederatedEvent {
		}
	}
}

namespace OCA\Circles\Exceptions {
	class FederatedItemException extends \Exception {
	}

	class FederatedItemNotFoundException extends FederatedItemException {
	}

	class CircleNotFoundException extends FederatedItemNotFoundException {
	}
}

namespace OCA\Circles\Events {

	use OCA\Circles\Model\Circle;
	use OCA\Circles\Model\Member;
	use OCP\EventDispatcher\Event;

	class CircleGenericEvent extends Event {
		public const REMOVED = 4;
		public const LEFT = 5;
		public const LEVEL = 6;
		public const NAME = 7;
		public function getCircle(): Circle {
		}
	}

	class EditingCircleEvent extends CircleGenericEvent {
		public function getFederatedEvent(): \OCA\Circles\Model\Federated\FederatedEvent {
		}
	}

	class CircleEditedEvent extends CircleGenericEvent {
		public function getFederatedEvent(): \OCA\Circles\Model\Federated\FederatedEvent {
		}
	}

	class CircleMemberGenericEvent extends CircleGenericEvent {
		public function getMember(): Member {
		}
	}

	class CircleResultGenericEvent extends Event {
		public function getCircle(): Circle {
		}
		public function hasMember(): bool {
		}
		public function getMember(): ?Member {
		}
	}

	class CircleDestroyedEvent extends CircleResultGenericEvent {
	}

	class CircleMemberAddedEvent extends CircleResultGenericEvent {
		public function getType(): int {
		}
	}

	class CircleMemberEditedEvent extends CircleResultGenericEvent {
		public function getType(): int {
		}
		public function getNewLevel(): int {
		}
	}

	class EditingCircleMemberEvent extends CircleMemberGenericEvent {
		public function getType(): int {
		}
		public function getLevel(): int {
		}
	}
}

namespace OCA\Circles\Service {

	/**
	 * Internal class of Circles, used by CirclesAdapter for contact members
	 */
	class ContactService {
		/**
		 * @return string[]
		 */
		public function getMailAddresses(string $contactPath): array {
		}

		/**
		 * Only contacts of this instance, empty otherwise
		 *
		 * @return string[]
		 */
		public function getMailAddressesFromMember(\OCA\Circles\Model\Member $member): array {
		}
	}

	class FederatedUserService {
		public function getCurrentUser(): ?\OCA\Circles\Model\FederatedUser {
		}
		public function setCurrentUser(\OCA\Circles\IFederatedUser $federatedUser): void {
		}
		public function bypassCurrentUserCondition(bool $bypass): void {
		}
		public function canBypassCurrentUserCondition(): bool {
		}
		public function generateFederatedUser(string $federatedId, int $type = 0): \OCA\Circles\Model\FederatedUser {
		}
	}

	class CircleService {
		public function circleLeave(string $circleId, bool $force = false): array {
		}
		public function circleJoin(string $circleId, ?string $invitationCode = null): array {
		}
		public function updateName(string $circleId, string $name): array {
		}
		public function updateDisplayName(string $circleId, string $displayName): array {
		}
		public function updateDescription(string $circleId, string $description): array {
		}
		public function updateConfig(string $circleId, int $config): array {
		}
	}
}

namespace OCA\Circles\Db {
	use OCA\Circles\Model\Member;

	/**
	 * Internal class of Circles, used by CirclesAdapter for the memberships
	 * of the current user (no public API)
	 */
	class MemberRequest {
		/**
		 * @return Member[]
		 */
		public function getMembersBySingleId(string $singleId): array {
		}

		/**
		 * @return Member[]
		 */
		public function getMembersByUserId(string $userId): array {
		}
	}
}

namespace OCA\Circles\Model\Federated {

	class FederatedEvent {
		public function getClass(): string {
		}
		public function getData(): \OCA\Circles\Tools\Model\SimpleDataStore {
		}
	}
}

namespace OCA\Circles\Tools\Model {

	class SimpleDataStore {
		/** @return list<string> */
		public function keys(): array {
		}
		public function hasKey(string $key): bool {
		}
		public function g(string $key): string {
		}
		public function gBool(string $key): bool {
		}
	}
}

namespace OCA\Circles\Service {

	class AvatarService {
		public function getAvatar(string $circleId): ?\OCP\Files\SimpleFS\ISimpleFile {
		}
	}
}
