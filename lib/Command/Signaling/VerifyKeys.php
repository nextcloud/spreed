<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Signaling;

use OC\Core\Command\Base;
use OCA\Talk\Config;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VerifyKeys extends Base {

	public function __construct(
		private IConfig $config,
		private Config $talkConfig,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:signaling:verify-keys')
			->setDescription('Verify if the stored public key matches the stored private key for the signaling server')
			->addOption('update', null, InputOption::VALUE_NONE, 'Updates the stored public key to match the private key if there is a mis-match');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$update = $input->getOption('update');

		$alg = $this->talkConfig->getSignalingTokenAlgorithm();
		$privateKey = $this->talkConfig->getSignalingTokenPrivateKey();
		$publicKey = $this->talkConfig->getSignalingTokenPublicKey();
		$publicKeyDerived = $this->talkConfig->deriveSignalingTokenPublicKey($privateKey, $alg);

		$output->writeln('Stored public key:');
		$output->writeln($publicKey);
		$output->writeln('Derived public key:');
		$output->writeln($publicKeyDerived);

		if ($publicKey != $publicKeyDerived) {
			if ($update) {
				$output->writeln('<comment>Stored public key for algorithm ' . strtolower($alg) . ' did not match stored private key.</comment>');
				$output->writeln('<info>A new public key was created and stored.</info>');
				$this->config->setAppValue('spreed', 'signaling_token_pubkey_' . strtolower($alg), $publicKeyDerived);

				return 0;
			}

			$output->writeln('<error>Stored public key for algorithm ' . strtolower($alg) . ' does not match stored private key</error>');
			return 1;
		}

		$output->writeln('<info>Stored public key for algorithm ' . strtolower($alg) . ' matches stored private key</info>');

		return 0;
	}
}
