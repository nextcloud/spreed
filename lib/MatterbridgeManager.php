<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
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

namespace OCA\Talk;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OC\Authentication\Token\IProvider as IAuthTokenProvider;
use OC\Authentication\Token\IToken;
use OCP\Security\ISecureRandom;
use OCP\IAvatarManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Utility\ITimeFactory;

use OCA\Talk\Exceptions\ImpossibleToKillException;
use OCA\Talk\Exceptions\WrongPermissionsException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Chat\ChatManager;

class MatterbridgeManager {
	/** @var IDBConnection */
	private $db;
	/** @var IConfig */
	private $config;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserManager */
	private $userManager;
	/** @var Manager */
	private $manager;
	/** @var ParticipantService */
	private $participantService;
	/** @var ChatManager */
	private $chatManager;
	/** @var IAuthTokenProvider */
	private $tokenProvider;
	/** @var ISecureRandom */
	private $random;
	/** @var IAvatarManager */
	private $avatarManager;
	/** @var LoggerInterface */
	private $logger;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IDBConnection $db,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IUserManager $userManager,
								Manager $manager,
								ParticipantService $participantService,
								ChatManager $chatManager,
								IAuthTokenProvider $tokenProvider,
								ISecureRandom $random,
								IAvatarManager $avatarManager,
								LoggerInterface $logger,
								ITimeFactory $timeFactory) {
		$this->avatarManager = $avatarManager;
		$this->db = $db;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->chatManager = $chatManager;
		$this->tokenProvider = $tokenProvider;
		$this->random = $random;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Get bridge information for a specific room
	 *
	 * @param Room $room the room
	 * @return array decoded json bridge information
	 */
	public function getBridgeOfRoom(Room $room): array {
		return $this->getBridgeFromDb($room);
	}

	/**
	 * Get bridge process information for a specific room
	 *
	 * @param Room $room the room
	 * @return array process state and log
	 */
	public function getBridgeProcessState(Room $room): array {
		$bridge =  $this->getBridgeFromDb($room);

		$logContent = $this->getBridgeLog($room);

		$pid = $this->checkBridgeProcess($room, $bridge, false);
		return [
			'running' => ($pid !== 0),
			'log' => $logContent
		];
	}

	/**
	 * Get bridge log file content
	 *
	 * @param Room $room the room
	 * @return string log file content
	 */
	public function getBridgeLog(Room $room): string {
		$outputPath = sprintf('/tmp/bridge-%s.log', $room->getToken());
		$logContent = file_get_contents($outputPath);
		return $logContent !== false ? $logContent : '';
	}

	/**
	 * Edit bridge information for a room
	 *
	 * @param Room $room the room
	 * @param string $userId
	 * @param bool $enabled desired state of the bridge
	 * @param array $parts parts of the bridge (what it connects to)
	 * @return array bridge state
	 */
	public function editBridgeOfRoom(Room $room, string $userId, bool $enabled, array $parts = []): array {
		$currentBridge = $this->getBridgeOfRoom($room);
		// kill matterbridge if we edit a running bridge config file so that it will be launched again
		// matterbridge dynamic config reload does not fully work
		if ($currentBridge['enabled'] && $enabled && $currentBridge['pid'] && $currentBridge['pid'] !== 0) {
			$this->killPid($currentBridge['pid']);
		}
		$newBridge = [
			'enabled' => $enabled,
			'pid' => $currentBridge['pid'] ?? 0,
			'parts' => $parts,
		];

		$this->notify($room, $userId, $currentBridge, $newBridge);

		// edit/update the config file
		$this->editBridgeConfig($room, $newBridge);

		// check state and manage the binary
		$pid = $this->checkBridgeProcess($room, $newBridge);
		$newBridge['pid'] = $pid;

		// save config
		$this->saveBridgeToDb($room->getId(), $newBridge);

		$logContent = $this->getBridgeLog($room);
		return [
			'running' => ($pid !== 0),
			'log' => $logContent
		];
	}

	/**
	 * Delete bridge information for a room
	 *
	 * @param Room $room the room
	 * @return bool success
	 */
	public function deleteBridgeOfRoom(Room $room): bool {
		// first potentially kill the process
		$currentBridge = $this->getBridgeOfRoom($room);
		$currentBridge['enabled'] = false;
		$this->checkBridgeProcess($room, $currentBridge);
		// then actually delete the config
		$this->config->deleteAppValue('spreed', 'bridge_' . $room->getToken());
		return true;
	}

	/**
	 * Check everything bridge-related is running fine
	 * For each room, check mattermost process respects desired state
	 */
	public function checkAllBridges(): void {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_bridges')
			->where($query->expr()->eq('enabled', $query->createNamedParameter(1, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$bridge = [
				'enabled' => (bool) $row['enabled'],
				'pid' => (int) $row['pid'],
				'parts' => json_decode($row['json_values'], true),
			];
			try {
				$room = $this->manager->getRoomById((int) $row['room_id']);
			} catch (RoomNotFoundException $e) {
				continue;
			}
			$this->checkBridge($room, $bridge);
		}
		$result->closeCursor();
	}

	/**
	 * For one room, check mattermost process respects desired state
	 * @param Room $room the room
	 * @param array|null $bridge
	 * @return int the bridge process ID
	 */
	public function checkBridge(Room $room, ?array $bridge = null): int {
		$bridge = $bridge ?: $this->getBridgeOfRoom($room);
		$pid = $this->checkBridgeProcess($room, $bridge);
		if ($pid !== $bridge['pid']) {
			// save the new PID if necessary
			$bridge['pid'] = $pid;
			$this->saveBridgeToDb($room->getId(), $bridge);
		}
		return $pid;
	}

	/**
	 * Edit the mattermost configuration file for one room
	 * This method takes care of connecting the bridge to the Talk room with a bot user
	 *
	 * @param Room $room
	 * @param array $newBridge
	 */
	private function editBridgeConfig(Room $room, array $newBridge): void {
		// check bot user exists and is member of the room
		// add the 'local' bridge part
		$newBridge = $this->addLocalPart($room, $newBridge);

		// TODO adapt that to use appData
		$configPath = sprintf('/tmp/bridge-%s.toml', $room->getToken());
		$configContent = $this->generateConfig($newBridge);
		file_put_contents($configPath, $configContent);
	}

	/**
	 * Add a bridge part with bot credentials to connect to the room
	 *
	 * @param Room $room the room
	 * @param array $bridge bridge information
	 * @return array the bridge with local part added
	 */
	private function addLocalPart(Room $room, array $bridge): array {
		$botInfo = $this->checkBotUser($room, $bridge['enabled']);
		$localPart = [
			'type' => 'nctalk',
			'login' => $botInfo['id'],
			'password' => $botInfo['password'],
			'channel' => $room->getToken(),
		];
		$bridge['parts'][] = $localPart;
		return $bridge;
	}

	/**
	 * routine to check the Nextcloud bot user exists (and create it if not)
	 * and to add it in the room in necessary
	 * and to revoke its old app token
	 * and to generate a new app token (used to connect via matterbridge)
	 *
	 * @param Room $room the room
	 * @param bool $create whether we should generate a new app token or not
	 * @return array Bot user information (username and app token). token is an empty string if creation was not asked.
	 */
	private function checkBotUser(Room $room, bool $create): array {
		$botUserId = 'bridge-bot';
		// check if user exists and create it if necessary
		if (!$this->userManager->userExists($botUserId)) {
			$pass = md5((string)mt_rand());
			$this->config->setAppValue('spreed', 'bridge_bot_password', $pass);
			$botUser = $this->userManager->createUser($botUserId, $pass);
			// set avatar
			$avatar = $this->avatarManager->getAvatar($botUserId);
			$imageData = file_get_contents(\OC::$SERVERROOT . '/apps/spreed/img/bridge-bot.png');
			$avatar->set($imageData);
		} else {
			$botUser = $this->userManager->get($botUserId);
		}

		// check user is member of the room
		try {
			$participant = $room->getParticipant($botUserId);
		} catch (ParticipantNotFoundException $e) {
			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $botUserId,
				'participantType' => Participant::USER,
			]]);
		}

		// delete old bot app tokens for this room
		$tokenName = 'spreed_' . $room->getToken();
		$tokens = $this->tokenProvider->getTokenByUser($botUserId);
		foreach ($tokens as $t) {
			if ($t->getName() === $tokenName) {
				$this->tokenProvider->invalidateTokenById($botUserId, $t->getId());
			}
		}

		if ($create) {
			// generate app token for the bot
			$appToken = $this->random->generate(72, ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_DIGITS);
			$botPassword = $this->config->getAppValue('spreed', 'bridge_bot_password', '');
			$generatedToken = $this->tokenProvider->generateToken(
				$appToken,
				$botUserId,
				$botUserId,
				$botPassword,
				$tokenName,
				IToken::PERMANENT_TOKEN,
				IToken::REMEMBER
			);
		} else {
			$appToken = '';
		}

		return [
			'id' => $botUserId,
			'password' => $appToken,
		];
	}

	/**
	 * Actually generate the matterbridge configuration file content for one bridge (one room)
	 * It basically add a pair of sections for each part: authentication and target channel
	 *
	 * @param array $bridge
	 * @return string config file content
	 */
	private function generateConfig(array $bridge): string {
		$content = '';
		foreach ($bridge['parts'] as $k => $part) {
			$type = $part['type'];

			if ($type === 'nctalk') {
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				if (isset($part['server']) && $part['server'] !== '') {
					$serverUrl = $part['server'];
				} else {
					$serverUrl = preg_replace('/\/+$/', '', $this->urlGenerator->getAbsoluteURL(''));
					// TODO remove that
					//$serverUrl = preg_replace('/https:/', 'http:', $serverUrl);
				}
				$content .= sprintf('	Server = "%s"', $serverUrl) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat="[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'mattermost') {
				// remove protocol from server URL
				if (preg_match('/^https?:/', $part['server'])) {
					$part['server'] = $this->cleanUrl($part['server']);
				}
				$content .= sprintf('[%s]', $type) . "\n";
				$content .= sprintf('	[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Team = "%s"', $part['team']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'matrix') {
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	NoHomeServerSuffix = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'zulip') {
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'rocketchat') {
				// include # in channel
				if (!preg_match('/^#/', $part['channel'])) {
					$bridge['parts'][$k]['channel'] = '#' . $part['channel'];
				}
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'slack') {
				// do not include # in channel
				if (strpos($part['channel'], '#') === 0) {
					$bridge['parts'][$k]['channel'] = ltrim($part['channel'], '#');
				}
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'discord') {
				// do not include # in channel
				if (strpos($part['channel'], '#') === 0) {
					$bridge['parts'][$k]['channel'] = ltrim($part['channel'], '#');
				}
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'telegram') {
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'steam') {
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'irc') {
				// include # in channel
				if (!preg_match('/^#/', $part['channel'])) {
					$bridge['parts'][$k]['channel'] = '#' . $part['channel'];
				}
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				if ($part['password']) {
					$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				}
				$content .= sprintf('	Nick = "%s"', $part['nick']) . "\n";
				if ($part['nickservnick']) {
					$content .= sprintf('	NickServNick = "%s"', $part['nickservnick']) . "\n";
					$content .= sprintf('	NickServPassword = "%s"', $part['nickservpassword']) . "\n";
				}
				if ($part['usetls']) {
					$content .= sprintf('	UseTLS = true') . "\n";
				}
				if ($part['usesasl']) {
					$content .= sprintf('	UseSASL = true') . "\n";
				}
				if ($part['skiptls']) {
					$content .= sprintf('	SkipTLSVerify = true') . "\n";
				}
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'msteams') {
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	TenantID = "%s"', $part['tenantid']) . "\n";
				$content .= sprintf('	ClientID = "%s"', $part['clientid']) . "\n";
				$content .= sprintf('	TeamID = "%s"', $part['teamid']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($type === 'xmpp') {
				$content .= sprintf('[%s.%s]', $type, $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Jid = "%s"', $part['jid']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= sprintf('	Muc = "%s"', $part['muc']) . "\n";
				$content .= sprintf('	Nick = "%s"', $part['nick']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			}
		}

		$content .= '[[gateway]]' . "\n";
		$content .= '	name = "myGateway"' . "\n";
		$content .= '	enable = true' . "\n\n";

		foreach ($bridge['parts'] as $k => $part) {
			$type = $part['type'];

			$content .= '[[gateway.inout]]' . "\n";
			$content .= sprintf('	account = "%s.%s"', $type, $k) . "\n";
			if (in_array($type, ['zulip', 'discord', 'xmpp', 'irc', 'slack', 'rocketchat', 'mattermost', 'matrix', 'nctalk'])) {
				$content .= sprintf('	channel = "%s"', $part['channel']) . "\n";
				if ($type === 'irc' && $part['channelpassword']) {
					$content .= sprintf('	options = { key = "%s" }', $part['channelpassword']) . "\n";
				}
				$content .= "\n";
			} elseif ($type === 'msteams') {
				$content .= sprintf('	threadId = "%s"', $part['threadid']) . "\n\n";
			} elseif (in_array($type, ['telegram', 'steam'])) {
				$content .= sprintf('	chatid = "%s"', $part['chatid']) . "\n\n";
			}
		}

		return $content;
	}

	/**
	 * Remove the scheme from an URL and add port
	 *
	 * @param string $url
	 * @return string
	 */
	private function cleanUrl(string $url): string {
		$uo = parse_url($url);
		$result = $uo['host'];
		if ($uo['scheme'] === 'https' && !isset($uo['port'])) {
			$result .= ':443';
		} elseif (isset($uo['port'])) {
			$result .= ':' . $uo['port'];
		}
		$result .= $uo['path'];
		return $result;
	}

	/**
	 * check if a bridge process is running
	 *
	 * @param Room $room the room
	 * @param array $bridge bridge information
	 * @param bool $relaunch whether to launch the process if it's down but bridge is enabled
	 * @return int the corresponding matterbridge process ID, 0 if none
	 */
	private function checkBridgeProcess(Room $room, array $bridge, bool $relaunch = true): int {
		$pid = 0;

		if (isset($bridge['pid']) && (int) $bridge['pid'] !== 0) {
			// config : there is a PID stored
			$isRunning = $this->isRunning($bridge['pid']);
			// if bridge running and enabled is false : kill it
			if ($isRunning) {
				if ($bridge['enabled']) {
					$this->logger->info('Process running AND bridge enabled in config : doing nothing');
					$pid = $bridge['pid'];
				} else {
					$this->logger->info('Process running AND bridge disabled in config : KILL ' . $bridge['pid']);
					$killed = $this->killPid($bridge['pid']);
					if ($killed) {
						$pid = 0;
					} else {
						$this->logger->info('Impossible to kill ' . $bridge['pid']);
						throw new ImpossibleToKillException('Impossible to kill bridge process [' . $bridge['pid'] . ']');
					}
				}
			} else {
				// no process found
				if ($bridge['enabled']) {
					if ($relaunch) {
						$this->logger->info('Process not found AND bridge enabled in config : relaunching');
						$pid = $this->launchMatterbridge($room);
					}
				} else {
					$this->logger->info('Process not found AND bridge disabled in config : doing nothing');
				}
			}
		} elseif ($bridge['enabled']) {
			if ($relaunch) {
				// config : no PID stored
				// config : enabled => launch it
				$pid = $this->launchMatterbridge($room);
				$this->logger->info('Launch process, PID is '.$pid);
			}
		} else {
			$this->logger->info('No PID defined in config AND bridge disabled in config : doing nothing');
		}

		return $pid;
	}

	/**
	 * Send system message to the room if necessary
	 *
	 * @param Room $room the room
	 * @param string $userId the editing user
	 * @param array $currentBridge previous bridge values
	 * @param array $newBridge future bridge values
	 */
	private function notify(Room $room, string $userId, array $currentBridge, array $newBridge): void {
		$currentParts = $currentBridge['parts'];
		$newParts = $newBridge['parts'];
		if (empty($currentParts) && !empty($newParts)) {
			$this->sendSystemMessage($room, $userId, 'matterbridge_config_added');
		} elseif (!empty($currentParts) && empty($newParts)) {
			$this->sendSystemMessage($room, $userId, 'matterbridge_config_removed');
		} elseif (empty($currentParts) !== empty($newParts) || !$this->compareBridges($currentBridge, $newBridge)) {
			$this->sendSystemMessage($room, $userId, 'matterbridge_config_edited');
		}
	}

	/**
	 * Check if 2 bridge configurations are identical
	 *
	 * @param array $bridge1
	 * @param array $bridge2
	 * @return bool True if they are strictly equivalent
	 */
	private function compareBridges(array $bridge1, array $bridge2): bool {
		// try to find an equivalent for each bridge part of one side in the other
		foreach ($bridge1['parts'] as $part1) {
			$found = false;
			foreach ($bridge2['parts'] as $part2) {
				if ($this->compareBridgeParts($part1, $part2)) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if 2 bridge parts are identical
	 *
	 * @param array $part1
	 * @param array $part2
	 * @return bool True if they are strictly equivalent
	 */
	private function compareBridgeParts(array $part1, array $part2): bool {
		$keys1 = array_keys($part1);
		$keys2 = array_keys($part2);
		if (count($keys1) !== count($keys2)) {
			return false;
		}

		foreach ($part1 as $key => $val) {
			if (!isset($part2[$key]) || $val !== $part2[$key]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * actually send the system message
	 * @param Room $room the room
	 * @param string $userId the involved user
	 * @param string $message the message ID
	 */
	private function sendSystemMessage(Room $room, string $userId, string $message): void {
		$this->chatManager->addSystemMessage(
			$room,
			Attendee::ACTOR_USERS,
			$userId,
			json_encode(['message' => $message, 'parameters' => []]),
			$this->timeFactory->getDateTime(),
			false
		);
	}

	/**
	 * Actually launch a matterbridge process for a room
	 *
	 * @param Room $room the room
	 * @return int the corresponding matterbridge process ID, 0 if it failed
	 */
	private function launchMatterbridge(Room $room): int {
		$binaryPath = $this->config->getAppValue('spreed', 'matterbridge_binary');
		// TODO this should be in appdata
		$configPath = sprintf('/tmp/bridge-%s.toml', $room->getToken());
		$outputPath = sprintf('/tmp/bridge-%s.log', $room->getToken());
		$cmd = sprintf('%s -conf %s', $binaryPath, $configPath);
		$pid = exec(sprintf('nice -n19 %s > %s 2>&1 & echo $!', $cmd, $outputPath), $output, $ret);
		$pid = (int) $pid;
		if ($ret !== 0) {
			$pid = 0;
		}
		return $pid;
	}

	/**
	 * kill the mattermost processes (owned by web server unix user) that do not match with any room
	 * @param bool $killAll
	 */
	public function killZombieBridges(bool $killAll = false): void {
		// get list of running matterbridge processes
		$cmd = 'ps x -o user,pid,args | grep "matterbridge" | grep -v grep | awk \'{print $2}\'';
		exec($cmd, $output, $ret);
		$runningPidList = [];
		foreach ($output as $o) {
			$runningPidList[] = (int) $o;
		}

		if (empty($runningPidList)) {
			// No processes running, so also no zombies
			return;
		}

		if ($killAll) {
			foreach ($runningPidList as $runningPid) {
				$this->killPid($runningPid);
			}
			return;
		}

		// get list of what should be running
		$expectedPidList = [];
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_bridges')
			->where($query->expr()->eq('enabled', $query->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->gt('pid', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$expectedPidList[] = (int) $row['pid'];
		}
		$result->closeCursor();

		// kill what should not be running
		$toKill = array_diff($runningPidList, $expectedPidList);
		foreach ($toKill as $toKillPid) {
			$this->killPid($toKillPid);
		}
	}

	/**
	 * Utility to kill a process
	 *
	 * @param int $pid the process ID to kill
	 * @return bool if it was successfully killed
	 */
	private function killPid(int $pid): bool {
		// kill
		exec(sprintf('kill -9 %d', $pid), $output, $ret);
		// check the process is gone
		$isStillRunning = $this->isRunning($pid);
		return (int) $ret === 0 && !$isStillRunning;
	}

	/**
	 * Check if a process is running
	 *
	 * @param int $pid the process ID
	 * @return bool true if it's running
	 */
	private function isRunning(int $pid): bool {
		try {
			$result = shell_exec(sprintf('ps x -o user,pid,args | awk \'{print $2}\' | grep "^%d$" | wc -l', $pid));
			if ((int) $result > 0) {
				return true;
			}
		} catch (\Exception $e) {
		}
		return false;
	}

	/**
	 * Stop all bridges
	 *
	 * @return bool success
	 */
	public function stopAllBridges(): bool {
		$query = $this->db->getQueryBuilder();

		$query->update('talk_bridges')
			->set('enabled', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('pid', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT));
		$query->execute();

		// finally kill all potential zombie matterbridge processes
		$this->killZombieBridges(true);
		return true;
	}

	/**
	 * Get bridge information for one room
	 *
	 * @param Room $room the room
	 * @return array decoded json array
	 */
	private function getBridgeFromDb(Room $room): array {
		$roomId = $room->getId();

		$qb = $this->db->getQueryBuilder();
		$qb->select('json_values', 'enabled', 'pid')
			->from('talk_bridges')
			->where(
				$qb->expr()->eq('room_id', $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);
		$result = $qb->execute();
		$enabled = false;
		$pid = 0;
		$jsonValues = '[]';
		if ($row = $result->fetch()) {
			$pid = (int) $row['pid'];
			$enabled = ((int) $row['enabled'] === 1);
			$jsonValues = $row['json_values'];
		}
		$result->closeCursor();

		return [
			'enabled' => $enabled,
			'pid' => $pid,
			'parts' => json_decode($jsonValues, true),
		];
	}

	/**
	 * Save bridge information for one room
	 *
	 * @param int $roomId the room ID
	 * @param array $bridge bridge values
	 */
	private function saveBridgeToDb(int $roomId, array $bridge): void {
		$jsonValues = json_encode($bridge['parts']);
		$intEnabled = $bridge['enabled'] ? 1 : 0;

		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert('talk_bridges')
				->values([
					'room_id' => $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
					'json_values' => $qb->createNamedParameter($jsonValues, IQueryBuilder::PARAM_STR),
					'enabled' => $qb->createNamedParameter($intEnabled, IQueryBuilder::PARAM_INT),
					'pid' => $qb->createNamedParameter($bridge['pid'], IQueryBuilder::PARAM_INT),
				]);
			$qb->execute();
		} catch (UniqueConstraintViolationException $e) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('talk_bridges');
			$qb->set('json_values', $qb->createNamedParameter($jsonValues, IQueryBuilder::PARAM_STR));
			$qb->set('enabled', $qb->createNamedParameter($intEnabled, IQueryBuilder::PARAM_INT));
			$qb->set('pid', $qb->createNamedParameter($bridge['pid'], IQueryBuilder::PARAM_INT));
			$qb->where(
				$qb->expr()->eq('room_id', $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT))
			);
			$qb->execute();
		}
	}

	public function getCurrentVersionFromBinary(): ?string {
		$binaryPath = $this->config->getAppValue('spreed', 'matterbridge_binary');
		if (!file_exists($binaryPath)) {
			return null;
		}

		// is www user the file owner?
		$user = posix_getpwuid(posix_getuid());
		$fileOwner = posix_getpwuid(fileowner($binaryPath));
		$userIsOwner = $user['name'] === $fileOwner['name'];

		// get file group and user groups
		$fileGid = filegroup($binaryPath);
		$myGids = posix_getgroups();

		// check permissions
		$perms = fileperms($binaryPath);
		$uExec = (($perms & 0x0040) && !($perms & 0x0800));
		$gExec = (($perms & 0x0008) && !($perms & 0x0400));
		$aExec = (($perms & 0x0001) && !($perms & 0x0200));

		// 3 ways to have the permission :
		// * be the owner and have u+x perm
		// * not be the owner, be in the file group and have g+x perm
		// * have o+x perm
		$execPerm = $aExec
			|| ($user['name'] === $fileOwner['name'] && $uExec)
			|| ($user['name'] !== $fileOwner['name'] && in_array($fileGid, $myGids) && $gExec);

		if (!$execPerm) {
			throw new WrongPermissionsException();
		}

		$cmd = escapeshellcmd($binaryPath) . ' ' . escapeshellarg('-version');
		@exec($cmd, $output, $returnCode);

		if ($returnCode !== 0) {
			return null;
		}

		return trim(implode("\n", $output));
	}
}
