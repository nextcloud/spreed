<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Federation\TrustedServers;
use OCA\Talk\Config;
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
	 * @throws \InvalidArgumentException
	 */
	public function isAllowedToInvite(
		IUser $user,
		ICloudId $cloudIdToInvite,
	): void {
		if (!($cloudIdToInvite->getUser() && $cloudIdToInvite->getRemote())) {
			$this->logger->debug('Could not share conversation as the recipient is invalid: ' . $cloudIdToInvite->getId());
			throw new \InvalidArgumentException('cloudId');
		}

		if (!$this->appConfig->getAppValueBool('federation_outgoing_enabled', true)) {
			$this->logger->debug('Could not share conversation as outgoing federation is disabled');
			throw new \InvalidArgumentException('outgoing');
		}

		if (!$this->talkConfig->isFederationEnabledForUserId($user)) {
			$this->logger->debug('Talk federation not allowed for user ' . $user->getUID());
			throw new \InvalidArgumentException('federation');
		}

		if ($this->appConfig->getAppValueBool('federation_only_trusted_servers')) {
			if (!$this->appManager->isEnabledForUser('federation')) {
				$this->logger->error('Federation is limited to trusted servers but the "federation" app is disabled');
				throw new \InvalidArgumentException('trusted_servers');
			}

			$trustedServers = Server::get(TrustedServers::class);
			$serverUrl = $this->addressHandler->removeProtocolFromUrl($cloudIdToInvite->getRemote());
			if (!$trustedServers->isTrustedServer($serverUrl)) {
				$this->logger->warning(
					'Tried to send Talk federation invite to untrusted server {serverUrl}',
					['serverUrl' => $serverUrl]
				);
				throw new \InvalidArgumentException('trusted_servers');
			}
		}
	}
}
