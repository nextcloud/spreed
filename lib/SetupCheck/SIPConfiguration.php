<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class SIPConfiguration implements ISetupCheck {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly Config $talkConfig,
		private readonly IDBConnection $connection,
		private readonly IL10N $l,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function getName(): string {
		$name = $this->l->t('SIP configuration');
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			return '[skip] ' . $name;
		}
		return $name;
	}

	#[\Override]
	public function run(): SetupResult {
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			return SetupResult::success($this->l->t('Using the SIP functionality requires a High-performance backend.'));
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('phone_number')
			->from('talk_phone_numbers')
			->where($query->expr()->like('phone_number', $query->createNamedParameter(
				$this->connection->escapeLikeParameter('+') . '%'
			)))
			->orWhere($query->expr()->like('phone_number', $query->createNamedParameter(
				$this->connection->escapeLikeParameter('0') . '%'
			)));

		$result = $query->executeQuery();
		$invalidNumbers = $result->fetchFirstColumn();
		$result->closeCursor();

		if (!empty($invalidNumbers)) {
			$message = $this->l->t("Assigned Talk phone numbers must not start with + or 0. Please remove or update the following numbers:\n{list}");
			$message = str_replace(
				'{list}',
				implode("\n", $invalidNumbers),
				$message
			);
			return SetupResult::error($message, 'https://portal.nextcloud.com/article/Nextcloud-Talk/Nextcloud-Talk-Phone/Direct-Dial-in#content-provisioning');
		}

		if ($this->talkConfig->getSIPSharedSecret() === ''
			&& $this->talkConfig->getDialInInfo() === ''
			&& $this->appConfig->getAppValueInt('feature_hints_hidden') < 34) {
			return SetupResult::info($this->l->t('No SIP backend configured'));
		}

		return SetupResult::success();
	}
}
