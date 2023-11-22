<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Command\User;

use OC\Core\Command\Base;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TransferOwnership extends Base {
	private RoomService $roomService;

	public function __construct(
		private ParticipantService $participantService,
		private Manager $manager,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('talk:user:transfer-ownership')
			->setDescription('Adds the destination-user with the same participant type to all (not one-to-one) conversations of source-user')
			->addArgument(
				'source-user',
				InputArgument::REQUIRED,
				'Owner of conversations which shall be moved'
			)
			->addArgument(
				'destination-user',
				InputArgument::REQUIRED,
				'User who will be the new owner of the conversations'
			)
			->addOption(
				'include-non-moderator',
				null,
				InputOption::VALUE_NONE,
				'Also include conversations where the source-user is a normal user'
			)
			->addOption(
				'remove-source-user',
				null,
				InputOption::VALUE_NONE,
				'Remove the source-user from the conversations'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sourceUID = $input->getArgument('source-user');
		$destinationUID = $input->getArgument('destination-user');

		$destinationUser = $this->userManager->get($destinationUID);
		if ($destinationUser === null) {
			$output->writeln('<error>Destination user could not be found.</error>');
			return 1;
		}

		$includeNonModeratorRooms = $input->getOption('include-non-moderator');
		$removeSourceUser = $input->getOption('remove-source-user');

		$modified = 0;
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_USERS, $sourceUID);
		foreach ($rooms as $room) {
			if ($room->getType() !== Room::TYPE_GROUP && $room->getType() !== Room::TYPE_PUBLIC) {
				// Skip one-to-one, changelog and any other room types
				continue;
			}

			$sourceParticipant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $sourceUID);

			if ($sourceParticipant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
				continue;
			}

			if (!$includeNonModeratorRooms && !$sourceParticipant->hasModeratorPermissions()) {
				continue;
			}

			try {
				$destinationParticipant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $destinationUser->getUID());

				$targetType = $this->shouldUpdateParticipantType($sourceParticipant->getAttendee()->getParticipantType(), $destinationParticipant->getAttendee()->getParticipantType());

				if ($targetType !== null) {
					$this->participantService->updateParticipantType(
						$room,
						$destinationParticipant,
						$sourceParticipant->getAttendee()->getParticipantType()
					);
					$modified++;
				}
			} catch (ParticipantNotFoundException $e) {
				$this->participantService->addUsers($room, [
					[
						'actorType' => Attendee::ACTOR_USERS,
						'actorId' => $destinationUser->getUID(),
						'displayName' => $destinationUser->getDisplayName(),
						'participantType' => $sourceParticipant->getAttendee()->getParticipantType(),
					]
				]);
				$modified++;
			}

			if ($removeSourceUser) {
				$this->participantService->removeAttendee($room, $sourceParticipant, AAttendeeRemovedEvent::REASON_REMOVED);
			}
		}

		$output->writeln('<info>Added or promoted user ' . $destinationUser->getUID() . ' in ' . $modified . ' rooms.</info>');
		return 0;
	}

	protected function shouldUpdateParticipantType(int $sourceParticipantType, int $destinationParticipantType): ?int {
		if ($sourceParticipantType === Participant::OWNER) {
			if ($destinationParticipantType === Participant::OWNER) {
				return null;
			}
			return $sourceParticipantType;
		}

		if ($sourceParticipantType === Participant::MODERATOR) {
			if ($destinationParticipantType === Participant::OWNER || $destinationParticipantType === Participant::MODERATOR) {
				return null;
			}
			return $sourceParticipantType;
		}

		if ($sourceParticipantType === Participant::USER) {
			if ($destinationParticipantType !== Participant::USER_SELF_JOINED) {
				return null;
			}
			return $sourceParticipantType;
		}

		return null;
	}
}
