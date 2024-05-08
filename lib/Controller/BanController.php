<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Model\Attendee;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type TalkBan from ResponseDefinitions
 */
class BanController extends AEnvironmentAwareController {
	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Ban an actor or IP address
	 *
	 * Required capability: `ban-v1`
	 *
	 * @param 'users'|'groups'|'circles'|'emails'|'federated_users'|'phones'|'ip' $actorType Type of actor to ban, or `ip` when banning a clients remote address
	 * @psalm-param Attendee::ACTOR_*|'ip' $actorType Type of actor to ban, or `ip` when banning a clients remote address
	 * @param string $actorId Actor ID or the IP address or range in case of type `ip`
	 * @param string $internalNote Optional internal note
	 * @return DataResponse<Http::STATUS_OK, TalkBan, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Ban successfully
	 * 400: Actor information is invalid
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	public function banActor(string $actorType, string $actorId, string $internalNote = ''): DataResponse {
		if ($actorId === 'wrong') {
			return new DataResponse([
				'error' => 'actor',
			], Http::STATUS_BAD_REQUEST);
		}


		return new DataResponse(
			[
				'id' => random_int(1, 1337),
				'actorType' => $this->participant->getAttendee()->getActorType(),
				'actorId' => $this->participant->getAttendee()->getActorId(),
				'bannedType' => $actorType,
				'bannedId' => $actorId,
				'bannedTime' => time(),
				'internalNote' => $internalNote ?: 'Lorem ipsum',
			],
			Http::STATUS_OK
		);
	}

	/**
	 * List the bans of a conversation
	 *
	 * Required capability: `ban-v1`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkBan>, array{}>
	 *
	 * 200: List all bans
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	public function listBans(): DataResponse {
		return new DataResponse([
			$this->randomBan(Attendee::ACTOR_USERS, 'test'),
			$this->randomBan(Attendee::ACTOR_USERS, '123456'),
			$this->randomBan(Attendee::ACTOR_FEDERATED_USERS, 'admin@nextcloud.local'),
			$this->randomBan('ip', '127.0.0.1'),
			$this->randomBan('ip', '127.0.0.1/32'),
			$this->randomBan('ip', '127.0.0.0/24'),
			$this->randomBan('ip', '::1/24'),
			$this->randomBan('ip', '2001:0db8:85a3::/48'),
		], Http::STATUS_OK);
	}

	/**
	 * Unban an actor or IP address
	 *
	 * Required capability: `ban-v1`
	 *
	 * @param int $banId ID of the ban to be removed
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Unban successfully or not found
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	public function unbanActor(int $banId): DataResponse {
		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @psalm-return TalkBan
	 */
	protected function randomBan(string $actorType, string $actorId): array {
		return [
			'id' => random_int(1, 1337),
			'actorType' => $this->participant->getAttendee()->getActorType(),
			'actorId' => $this->participant->getAttendee()->getActorId(),
			'bannedType' => $actorType,
			'bannedId' => $actorId,
			'bannedTime' => random_int(1514747958, 1714747958),
			'internalNote' => '#NOTE#' . $actorType . '#' . $actorId . '#' . sha1($actorType . $actorId),
		];
	}
}
