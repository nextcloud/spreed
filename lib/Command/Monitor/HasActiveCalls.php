<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Monitor;

use OC\Core\Command\Base;
use OCA\Talk\Service\MetricsService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HasActiveCalls extends Base {

	public function __construct(
		protected MetricsService $metricsService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:active-calls')
			->setDescription('Allows you to check if calls are currently in process')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$numCalls = $this->metricsService->getNumberOfActiveCalls();

		if ($numCalls === 0) {
			if ($input->getOption('output') === 'plain') {
				$output->writeln('<info>No calls in progress</info>');
			} else {
				$data = ['calls' => 0, 'participants' => 0];
				$this->writeArrayInOutputFormat($input, $output, $data);
			}
			return 0;
		}

		$numSessions = $this->metricsService->getNumberOfSessionsInCalls();

		// We keep "participants" here, to not break scripting done with that command
		if ($input->getOption('output') === 'plain') {
			$output->writeln(sprintf('<error>There are currently %1$d calls in progress with %2$d participants</error>', $numCalls, $numSessions));
		} else {
			$data = ['calls' => $numCalls, 'participants' => $numSessions];
			$this->writeArrayInOutputFormat($input, $output, $data);
		}
		return 1;
	}
}
