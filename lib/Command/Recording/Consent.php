<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Recording;

use OC\Core\Command\Base;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ConsentService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Consent extends Base {

	public function __construct(
		protected Manager $roomManager,
		protected ConsentService $consentService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:recording:consent')
			->setDescription('List all matching consent that were given to be audio and video recorded during a call (requires administrator or moderator configuration)')
			->addOption(
				'token',
				null,
				InputOption::VALUE_REQUIRED,
				'Limit to the given conversation'
			)
			->addOption(
				'actor-type',
				null,
				InputOption::VALUE_REQUIRED,
				'Limit to the given actor (only valid when --actor-id is also provided)'
			)
			->addOption(
				'actor-id',
				null,
				InputOption::VALUE_REQUIRED,
				'Limit to the given actor (only valid when --actor-type is also provided)'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$token = $input->getOption('token');
		$actorType = $input->getOption('actor-type');
		$actorId = $input->getOption('actor-id');
		if (($actorType !== null) !== ($actorId !== null)) {
			$output->writeln('<error>actor-type and actor-id must either both be specified or both left out</error>');
			return 1;
		}

		$room = null;
		if ($token !== null) {
			try {
				$room = $this->roomManager->getRoomByToken($token);
			} catch (RoomNotFoundException) {
				$output->writeln('<error>Conversation could not be found by token</error>');
				return 2;
			}
		}

		if ($actorType) {
			if ($room instanceof Room) {
				$consentData = $this->consentService->getConsentForRoomByActor($room, $actorType, $actorId);
			} else {
				$consentData = $this->consentService->getConsentForActor($actorType, $actorId);
			}
		} elseif ($room instanceof Room) {
			$consentData = $this->consentService->getConsentForRoom($room);
		} else {
			$output->writeln('<error>No conversation or actor provided</error>');
			return 3;
		}

		$this->writeTableInOutputFormat(
			$input,
			$output,
			array_map(static fn (\OCA\Talk\Model\Consent $consent) => $consent->jsonSerialize(), $consentData)
		);

		return 0;
	}

}
