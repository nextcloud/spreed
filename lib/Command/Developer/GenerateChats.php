<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Developer;

use OC\Core\Command\Base;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Developer\ChatGenerator;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\Comments\IComment;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateChats extends Base {
	public function __construct(
		private readonly IConfig $config,
		private readonly IDBConnection $connection,
		private readonly RoomService $roomService,
		private readonly ParticipantService $participantService,
		private readonly ChatManager $chatManager,
		private readonly CommentsManager $commentsManager,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:developer:generate-chats')
			->setDescription('Seed-based generator for demo conversations with mixed users, groups, replies and message length variation (debug mode only)')
			->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Integer seed; two developers with the same seed and user/group pool produce identical conversation structure and content. Defaults to the current unix timestamp.')
			->addOption('rooms', null, InputOption::VALUE_REQUIRED, 'Number of rooms to create', '5')
			->addOption('min-messages', null, InputOption::VALUE_REQUIRED, 'Minimum messages per room', '5')
			->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Maximum messages per room', '500')
			->addOption('days', null, InputOption::VALUE_REQUIRED, 'Upper bound on the longest pause between messages, in days (rare day-break gap; most gaps stay under 5 minutes)', '14')
			->addOption('public-ratio', null, InputOption::VALUE_REQUIRED, 'Share of generated rooms that are public (0..1)', '0.15')
			->addOption('one-to-one-ratio', null, InputOption::VALUE_REQUIRED, 'Share of generated rooms that are one-to-one (0..1)', '0.30')
			->addOption('users', null, InputOption::VALUE_REQUIRED, 'Comma-separated user IDs to draw from (overrides auto-pick)')
			->addOption('groups', null, InputOption::VALUE_REQUIRED, 'Comma-separated group IDs to draw from (overrides auto-pick)')
			->addOption('user-pool-size', null, InputOption::VALUE_REQUIRED, 'Cap on distinct users used across all rooms (excludes --main-user)', '12')
			->addOption('group-pool-size', null, InputOption::VALUE_REQUIRED, 'Cap on distinct groups used across all rooms', '3')
			->addOption('main-user', null, InputOption::VALUE_REQUIRED, 'User added to every room as owner and used as the partner in every one-to-one')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$seedRaw = $input->getOption('seed');
		if ($seedRaw === null) {
			$seed = time();
			$output->writeln('<comment>No --seed provided; falling back to current timestamp: ' . $seed . '</comment>');
		} elseif (!is_numeric($seedRaw)) {
			$output->writeln('<error>--seed must be a number</error>');
			return 1;
		} else {
			$seed = (int)$seedRaw;
		}

		$rooms = (int)$input->getOption('rooms');
		$minMessages = (int)$input->getOption('min-messages');
		$maxMessages = (int)$input->getOption('max-messages');
		$days = (int)$input->getOption('days');
		$publicRatio = (float)$input->getOption('public-ratio');
		$oneToOneRatio = (float)$input->getOption('one-to-one-ratio');
		$userPoolSize = (int)$input->getOption('user-pool-size');
		$groupPoolSize = (int)$input->getOption('group-pool-size');

		if ($rooms < 1 || $minMessages < 0 || $maxMessages < $minMessages || $days < 1) {
			$output->writeln('<error>--rooms must be >=1, --min-messages >=0, --max-messages >= --min-messages, --days >=1</error>');
			return 1;
		}
		if ($publicRatio < 0 || $publicRatio > 1 || $oneToOneRatio < 0 || $oneToOneRatio > 1
			|| ($publicRatio + $oneToOneRatio) > 1) {
			$output->writeln('<error>--public-ratio and --one-to-one-ratio must be in [0,1] and sum to <=1</error>');
			return 1;
		}

		$users = $this->resolveUserPool($input->getOption('users'), $output);
		if ($users === null) {
			return 1;
		}
		$groups = $this->resolveGroupPool($input->getOption('groups'), $output);
		if ($groups === null) {
			return 1;
		}

		$mainUser = $input->getOption('main-user');
		if (is_string($mainUser) && $mainUser !== '') {
			if (!$this->userManager->userExists($mainUser)) {
				$output->writeln('<error>Unknown --main-user: ' . $mainUser . '</error>');
				return 1;
			}
			// The main user is added separately, so they must not also be sampled from the pool.
			$users = array_values(array_filter($users, static fn (string $uid): bool => $uid !== $mainUser));
		} else {
			$mainUser = null;
		}

		$needed = $mainUser !== null ? 1 : 2;
		if (count($users) < $needed) {
			$output->writeln('<error>Need at least ' . ($needed + ($mainUser !== null ? 1 : 0)) . ' users on the server to generate conversations</error>');
			return 1;
		}

		$generator = new ChatGenerator($seed);
		$userPool = $generator->pickPool($users, $userPoolSize);
		$groupPool = $generator->pickPool($groups, $groupPoolSize);

		$output->writeln(sprintf(
			'Seed %d%s — user pool: %s%s',
			$seed,
			$mainUser !== null ? ' / main user: ' . $mainUser : '',
			implode(', ', $userPool),
			$groupPool === [] ? '' : ' / group pool: ' . implode(', ', $groupPool)
		));

		$plans = $generator->planRooms($userPool, $groupPool, $rooms, $minMessages, $maxMessages, $days, $publicRatio, $oneToOneRatio, $mainUser);

		$totalMessages = 0;
		foreach ($plans as $plan) {
			$created = $this->materialiseRoom($plan, $seed, $output);
			if ($created !== null) {
				$totalMessages += $created;
			}
		}

		$output->writeln(sprintf('<info>Done. %d rooms, %d messages.</info>', count($plans), $totalMessages));
		return 0;
	}

	/**
	 * @param array{
	 *     type: int,
	 *     name: string,
	 *     owner: string,
	 *     users: list<string>,
	 *     groups: list<string>,
	 *     messages: list<array{author: string, text: string, replyTo: int|null, secondsAgo: int, silent: bool}>
	 * } $plan
	 * @return int|null Number of messages posted, or null on failure
	 */
	private function materialiseRoom(array $plan, int $seed, OutputInterface $output): ?int {
		$owner = $this->userManager->get($plan['owner']);
		if (!$owner instanceof IUser) {
			$output->writeln('<comment>Skipping room: owner ' . $plan['owner'] . ' not found</comment>');
			return null;
		}

		try {
			if ($plan['type'] === Room::TYPE_ONE_TO_ONE) {
				$other = $this->userManager->get($plan['users'][1] ?? '');
				if (!$other instanceof IUser) {
					$output->writeln('<comment>Skipping one-to-one: partner not found</comment>');
					return null;
				}
				$room = $this->roomService->createOneToOneConversation($owner, $other);
				// createOneToOneConversation only inserts $owner; add the partner so both can post.
				$this->participantService->ensureOneToOneRoomIsFilled($room);
			} else {
				$room = $this->roomService->createConversation($plan['type'], $plan['name'], $owner);
				$this->addExtraUsers($room, $owner, array_slice($plan['users'], 1));
				$this->addGroups($room, $plan['groups']);
			}
		} catch (\Throwable $e) {
			$output->writeln('<error>Failed to create room "' . $plan['name'] . '": ' . $e->getMessage() . '</error>');
			return null;
		}

		// Setup (room creation + participant adds) emits system messages with the current timestamp,
		// which would group them at the end of the conversation. Back-date them so they appear at
		// the top, just before the oldest planned chat message.
		$oldestSecondsAgo = $plan['messages'][0]['secondsAgo'] ?? 0;
		$this->backdateSetupSystemMessages($room, $oldestSecondsAgo);
		$this->postSeedMarker($room, $owner, $seed, $oldestSecondsAgo);

		$posted = $this->postMessages($room, $plan['messages'], $output);

		$output->writeln(sprintf(
			'  %s "%s" (%s) — %d users, %d groups, %d messages',
			$this->typeLabel($plan['type']),
			$room->getName() !== '' ? $room->getName() : $plan['name'],
			$room->getToken(),
			count($plan['users']),
			count($plan['groups']),
			$posted,
		));
		return $posted;
	}

	/**
	 * @param list<string> $extraUserIds
	 */
	private function addExtraUsers(Room $room, IUser $owner, array $extraUserIds): void {
		$participants = [];
		foreach ($extraUserIds as $userId) {
			$user = $this->userManager->get($userId);
			if (!$user instanceof IUser) {
				continue;
			}
			$participants[] = [
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'participantType' => Participant::USER,
			];
		}
		if ($participants !== []) {
			$this->participantService->addUsers($room, $participants, $owner);
		}
	}

	/**
	 * @param list<string> $groupIds
	 */
	private function addGroups(Room $room, array $groupIds): void {
		$existing = [];
		foreach ($groupIds as $groupId) {
			$group = $this->groupManager->get($groupId);
			if (!$group instanceof IGroup) {
				continue;
			}
			$this->participantService->addGroup($room, $group, $existing);
		}
	}

	/**
	 * @param list<array{author: string, text: string, replyTo: int|null, secondsAgo: int, silent: bool}> $messages
	 */
	private function postMessages(Room $room, array $messages, OutputInterface $output): int {
		$now = new \DateTimeImmutable();
		/** @var array<int, IComment> $posted */
		$posted = [];
		$participants = [];
		$count = 0;

		foreach ($messages as $index => $message) {
			$participant = $participants[$message['author']] ?? null;
			if ($participant === null) {
				try {
					$participant = $this->participantService->getParticipant($room, $message['author'], false);
				} catch (\Throwable) {
					continue;
				}
				$participants[$message['author']] = $participant;
			}

			$replyTo = null;
			if ($message['replyTo'] !== null && isset($posted[$message['replyTo']])) {
				$replyTo = $posted[$message['replyTo']];
			}

			$creationDateTime = \DateTime::createFromImmutable($now->sub(new \DateInterval('PT' . $message['secondsAgo'] . 'S')));

			try {
				$posted[$index] = $this->chatManager->sendMessage(
					$room,
					$participant,
					Attendee::ACTOR_USERS,
					$message['author'],
					$message['text'],
					$creationDateTime,
					$replyTo,
					'',
					$message['silent'],
					false,
				);
				$count++;
			} catch (\Throwable $e) {
				$output->writeln('<comment>  failed to post message ' . $index . ': ' . $e->getMessage() . '</comment>');
			}

			$this->commentsManager->removeFromCache($posted[$index]->getId());
		}
		return $count;
	}

	/**
	 * Post a small marker as the very first chat message in the room, recording the seed used to
	 * generate it. Timestamp sits between the back-dated system messages and the oldest planned
	 * message, so it appears at the top of the chat history.
	 */
	private function postSeedMarker(Room $room, IUser $owner, int $seed, int $oldestSecondsAgo): void {
		try {
			$participant = $this->participantService->getParticipant($room, $owner->getUID(), false);
		} catch (\Throwable) {
			return;
		}

		$timestamp = new \DateTime('@' . (time() - ($oldestSecondsAgo + 30)));

		try {
			$this->chatManager->sendMessage(
				$room,
				$participant,
				Attendee::ACTOR_USERS,
				$owner->getUID(),
				sprintf('_Generated by `talk:developer:generate-chats` with seed `%d`_', $seed),
				$timestamp,
				null,
				'',
				true,
				false,
			);
		} catch (\Throwable) {
			// Best-effort marker, don't block the run.
		}
	}

	/**
	 * Move system messages (room created, participant added, etc.) just before the oldest planned
	 * chat message, so they appear at the top of the conversation rather than clustered at the end.
	 */
	private function backdateSetupSystemMessages(Room $room, int $oldestSecondsAgo): void {
		$offsetSeconds = max(60, $oldestSecondsAgo + 60);
		$timestamp = new \DateTime('@' . (time() - $offsetSeconds));
		$timestamp->setTimezone(new \DateTimeZone('UTC'));

		$update = $this->connection->getQueryBuilder();
		$update->update('comments')
			->set('creation_timestamp', $update->createNamedParameter($timestamp, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($update->expr()->eq('object_type', $update->createNamedParameter('chat')))
			->andWhere($update->expr()->eq('object_id', $update->createNamedParameter((string)$room->getId())))
			->andWhere($update->expr()->eq('verb', $update->createNamedParameter('system')));
		$update->executeStatement();
	}

	/**
	 * @return list<string>|null sorted ascending for cross-server determinism; null on validation error
	 */
	private function resolveUserPool(mixed $override, OutputInterface $output): ?array {
		if (is_string($override) && $override !== '') {
			$ids = array_values(array_filter(array_map(trim(...), explode(',', $override)), static fn (string $s): bool => $s !== ''));
			foreach ($ids as $id) {
				if (!$this->userManager->userExists($id)) {
					$output->writeln('<error>Unknown user: ' . $id . '</error>');
					return null;
				}
			}
			sort($ids);
			return $ids;
		}

		$ids = [];
		$this->userManager->callForSeenUsers(static function (IUser $user) use (&$ids): void {
			if ($user->isEnabled()) {
				$ids[] = $user->getUID();
			}
		});
		sort($ids);
		return $ids;
	}

	/**
	 * @return list<string>|null sorted ascending for cross-server determinism; null on validation error
	 */
	private function resolveGroupPool(mixed $override, OutputInterface $output): ?array {
		if (is_string($override) && $override !== '') {
			$ids = array_values(array_filter(array_map(trim(...), explode(',', $override)), static fn (string $s): bool => $s !== ''));
			foreach ($ids as $id) {
				if ($this->groupManager->get($id) === null) {
					$output->writeln('<error>Unknown group: ' . $id . '</error>');
					return null;
				}
			}
			sort($ids);
			return $ids;
		}

		$ids = [];
		foreach ($this->groupManager->search('') as $group) {
			if ($group->getGID() !== 'admin') {
				$ids[] = $group->getGID();
			}
		}
		sort($ids);
		return $ids;
	}

	private function typeLabel(int $type): string {
		return match ($type) {
			Room::TYPE_ONE_TO_ONE => 'one-to-one',
			Room::TYPE_GROUP => 'group',
			Room::TYPE_PUBLIC => 'public',
			default => 'room',
		};
	}
}
