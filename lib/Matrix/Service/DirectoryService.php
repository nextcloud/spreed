<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Find rooms and users on the homeserver of a linked account, so they can be
 * joined or invited without knowing their address
 *
 * @psalm-import-type TalkMatrixDirectoryRoom from ResponseDefinitions
 * @psalm-import-type TalkMatrixDirectoryUser from ResponseDefinitions
 */
class DirectoryService {
	public const int MAX_LIMIT = 50;

	public function __construct(
		private readonly AccountService $accountService,
		private readonly MatrixRoomMapper $roomMapper,
	) {
	}

	/**
	 * @param string $since Pagination token of the previous page
	 * @return array{rooms: list<TalkMatrixDirectoryRoom>, nextBatch: ?string, total: ?non-negative-int}
	 * @throws MatrixException
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function searchRooms(Account $account, string $searchTerm, int $limit, string $since = ''): array {
		$result = $this->accountService->getClient($account)->searchPublicRooms(trim($searchTerm), $this->clamp($limit), $since);

		$tokens = $this->roomMapper->findConversationTokens(array_column($result['rooms'], 'roomId'), $account->getUserId());
		$rooms = [];
		foreach ($result['rooms'] as $room) {
			$room['token'] = $tokens[$room['roomId']] ?? null;
			$rooms[] = $room;
		}
		return ['rooms' => $rooms, 'nextBatch' => $result['nextBatch'], 'total' => $result['total']];
	}

	/**
	 * @return array{users: list<TalkMatrixDirectoryUser>, limited: bool}
	 * @throws MatrixException
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function searchUsers(Account $account, string $searchTerm, int $limit): array {
		$searchTerm = trim($searchTerm);
		if ($searchTerm === '') {
			return ['users' => [], 'limited' => false];
		}
		$result = $this->accountService->getClient($account)->searchUsers($searchTerm, $this->clamp($limit));
		$result['users'] = array_values(array_filter(
			$result['users'],
			static fn (array $user): bool => $user['mxid'] !== $account->getMxid(),
		));
		return $result;
	}

	private function clamp(int $limit): int {
		return max(1, min($limit, self::MAX_LIMIT));
	}
}
