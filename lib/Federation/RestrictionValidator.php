<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Federation\TrustedServers;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\FederationRestrictionException;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Federation\ICloudId;
use OCP\IUser;
use OCP\Server;
use Psr\Log\LoggerInterface;

class RestrictionValidator {
	public function __construct(
		private AddressHandler $addressHandler,
		private IAppManager $appManager,
		private Config $talkConfig,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check if $sharedBy is allowed to invite $shareWith
	 *
	 * @throws FederationRestrictionException
	 */
	public function isAllowedToInvite(
		IUser $user,
		ICloudId $cloudIdToInvite,
	): void {
		if (!($cloudIdToInvite->getUser() && $cloudIdToInvite->getRemote())) {
			$this->logger->debug('Could not share conversation as the recipient is invalid: ' . $cloudIdToInvite->getId());
			throw new FederationRestrictionException(FederationRestrictionException::REASON_CLOUD_ID);
		}

		if (!$this->appConfig->getAppValueBool('federation_outgoing_enabled', true)) {
			$this->logger->debug('Could not share conversation as outgoing federation is disabled');
			throw new FederationRestrictionException(FederationRestrictionException::REASON_OUTGOING);
		}

		if (!$this->talkConfig->isFederationEnabledForUserId($user)) {
			$this->logger->debug('Talk federation not allowed for user ' . $user->getUID());
			throw new FederationRestrictionException(FederationRestrictionException::REASON_FEDERATION);
		}

		if ($this->appConfig->getAppValueBool('federation_only_trusted_servers')) {
			if (!$this->appManager->isEnabledForUser('federation')) {
				$this->logger->error('Federation is limited to trusted servers but the "federation" app is disabled');
				throw new FederationRestrictionException(FederationRestrictionException::REASON_TRUSTED_SERVERS);
			}

			$trustedServers = Server::get(TrustedServers::class);
			$serverUrl = $this->addressHandler->removeProtocolFromUrl($cloudIdToInvite->getRemote());
			if (!$trustedServers->isTrustedServer($serverUrl)) {
				$this->logger->warning(
					'Tried to send Talk federation invite to untrusted server {serverUrl}',
					['serverUrl' => $serverUrl]
				);
				throw new FederationRestrictionException(FederationRestrictionException::REASON_TRUSTED_SERVERS);
			}
		}
	}
}
