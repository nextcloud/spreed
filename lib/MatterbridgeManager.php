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

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IURLGenerator;
use OC\Authentication\Token\IProvider as IAuthTokenProvider;
use OC\Authentication\Token\IToken;
use OCP\Security\ISecureRandom;
use OCP\IAvatarManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;

use OCA\Talk\Exceptions\ImpossibleToKillException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;

class MatterbridgeManager {
	/** @var IDBConnection */
	private $db;
	/** @var IConfig */
	private $config;
	/** @var IAppData */
	private $appData;
	/** @var IL10N */
	private $l;
	/** @var IUserManager */
	private $userManager;
	/** @var IAuthTokenProvider */
	private $tokenProvider;
	/** @var ISecureRandom */
	private $random;

	public function __construct(IDBConnection $db,
								IConfig $config,
								IAppData $appData,
								IURLGenerator $urlGenerator,
								IUserManager $userManager,
								Manager $manager,
								IAuthTokenProvider $tokenProvider,
								ISecureRandom $random,
								IAvatarManager $avatarManager,
								LoggerInterface $logger,
								IL10N $l) {
		$this->avatarManager = $avatarManager;
		$this->db = $db;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->appData = $appData;
		$this->userManager = $userManager;
		$this->manager = $manager;
		$this->tokenProvider = $tokenProvider;
		$this->random = $random;
		$this->logger = $logger;
		$this->l = $l;
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
		return $logContent ?? '';
	}

	/**
	 * Edit bridge information for a room
	 *
	 * @param Room $room the room
	 * @param bool $enabled desired state of the bridge
	 * @param array $parts parts of the bridge (what it connects to)
	 * @return bool success
	 */
	public function editBridgeOfRoom(Room $room, bool $enabled, array $parts = []): bool {
		$currentBridge = $this->getBridgeOfRoom($room);
		$newBridge = [
			'enabled' => $enabled,
			'pid' => isset($currentBridge['pid']) ? $currentBridge['pid'] : 0,
			'parts' => $parts,
		];

		// edit/update the config file
		$this->editBridgeConfig($room, $newBridge);

		// check state and manage the binary
		$pid = $this->checkBridgeProcess($room, $newBridge);
		$newBridge['pid'] = $pid;

		// save config
		$this->saveBridgeToDb($room, $newBridge);

		return true;
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
		$this->checkBridgeProcess($token, $currentBridge);
		// then actually delete the config
		$bridgeJSON = $this->config->deleteAppValue('spreed', 'bridge_' . $token);
		return true;
	}

	/**
	 * Check everything bridge-related is running fine
	 * For each room, check mattermost process respects desired state
	 */
	public function checkAllBridges(): void {
		// TODO call this from time to time to make sure everything is running fine
		$this->manager->forAllRooms(function ($room) {
			if ($room->getType() === Room::GROUP_CALL || $room->getType() === Room::PUBLIC_CALL) {
				$this->checkBridge($room);
			}
		});
	}

	/**
	 * For one room, check mattermost process respects desired state
	 * @param Room $room the room
	 * @return int the bridge process ID
	 */
	public function checkBridge(Room $room): int {
		$bridge = $this->getBridgeOfRoom($room);
		$pid = $this->checkBridgeProcess($room, $bridge);
		if ($pid !== $bridge['pid']) {
			// save the new PID if necessary
			$bridge['pid'] = $pid;
			$this->saveBridgeToDb($room, $bridge);
		}
		return $pid;
	}

	private function getDataFolder(): ISimpleFolder {
		try {
			return $this->appData->getFolder('bridge');
		} catch (NotFoundException $e) {
			return $this->appData->newFolder('bridge');
		}
	}

	/**
	 * Edit the mattermost configuration file for one room
	 * This method takes care of connecting the bridge to the Talk room with a bot user
	 *
	 * @param Room $room the room
	 */
	private function editBridgeConfig(Room $room, array $newBridge): void {
		// check bot user exists and is member of the room
		// add the 'local' bridge part
		$newBridge = $this->addLocalPart($room, $newBridge);

		// TODO adapt that to use appData
		$configPath = sprintf('/tmp/bridge-%s.toml', $room->getToken());
		$configContent = $this->generateConfig($room, $newBridge);
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
		array_push($bridge['parts'], $localPart);
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
			$pass = md5(strval(rand()));
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
			$room->addUsers([
				'userId' => $botUserId,
				'participantType' => Participant::USER,
			]);
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
	 * @param Room $room the room
	 * @return string config file content
	 */
	private function generateConfig(Room $room, array $bridge): string {
		$content = '';
		foreach ($bridge['parts'] as $k => $part) {
			if ($part['type'] === 'nctalk') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
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
			} elseif ($part['type'] === 'mattermost') {
				// remove protocol from server URL
				if (preg_match('/^https?:/', $part['server'])) {
					$part['server'] = $this->cleanUrl($part['server']);
				}
				$content .= sprintf('[%s]', $part['type']) . "\n";
				$content .= sprintf('	[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Team = "%s"', $part['team']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'matrix') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	NoHomeServerSuffix = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'zulip') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'rocketchat') {
				// include # in channel
				if (!preg_match('/^#/', $part['channel'])) {
					$bridge['parts'][$k]['channel'] = '#' . $part['channel'];
				}
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'slack') {
				// do not include # in channel
				if (preg_match('/^#/', $part['channel'])) {
					$bridge['parts'][$k]['channel'] = preg_replace('/^#+/', '', $part['channel']);
				}
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'discord') {
				// do not include # in channel
				if (preg_match('/^#/', $part['channel'])) {
					$bridge['parts'][$k]['channel'] = preg_replace('/^#+/', '', $part['channel']);
				}
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'telegram') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Token = "%s"', $part['token']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'steam') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Login = "%s"', $part['login']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'irc') {
				// include # in channel
				if (!preg_match('/^#/', $part['channel'])) {
					$bridge['parts'][$k]['channel'] = '#' . $part['channel'];
				}
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	Server = "%s"', $part['server']) . "\n";
				$content .= sprintf('	Nick = "%s"', $part['nick']) . "\n";
				$content .= sprintf('	Password = "%s"', $part['password']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'msteams') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				$content .= sprintf('	TenantID = "%s"', $part['tenantid']) . "\n";
				$content .= sprintf('	ClientID = "%s"', $part['clientid']) . "\n";
				$content .= sprintf('	TeamID = "%s"', $part['teamid']) . "\n";
				$content .= '	PrefixMessagesWithNick = true' . "\n";
				$content .= '	RemoteNickFormat = "[{PROTOCOL}] <{NICK}> "' . "\n\n";
			} elseif ($part['type'] === 'xmpp') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
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
			$content .= '[[gateway.inout]]' . "\n";
			$content .= sprintf('	account = "%s.%s"', $part['type'], $k) . "\n";
			if (in_array($part['type'], ['zulip', 'discord', 'xmpp', 'irc', 'slack', 'rocketchat', 'mattermost', 'matrix', 'nctalk'])) {
				$content .= sprintf('	channel = "%s"', $part['channel']) . "\n\n";
			} elseif ($part['type'] === 'msteams') {
				$content .= sprintf('	threadId = "%s"', $part['threadid']) . "\n\n";
			} elseif (in_array($part['type'], ['telegram', 'steam'])) {
				$content .= sprintf('	chatid = "%s"', $part['chatid']) . "\n\n";
			}
		}

		return $content;
	}

	/**
	 * Remove the scheme from an URL and add port
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
	 * @param $relaunch whether to launch the process if it's down but bridge is enabled
	 * @return int the corresponding matterbridge process ID, 0 if none
	 */
	private function checkBridgeProcess(Room $room, array $bridge, bool $relaunch = true): int {
		$pid = 0;

		if (isset($bridge['pid']) && intval($bridge['pid']) !== 0) {
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
		$pid = exec(sprintf('%s > %s 2>&1 & echo $!', $cmd, $outputPath), $output, $ret);
		$pid = intval($pid);
		if ($ret !== 0) {
			$pid = 0;
		}
		return $pid;
	}

	/**
	 * kill the mattermost processes (owned by web server unix user) that do not match with any room
	 */
	public function killZombieBridges(): void {
		// get list of running matterbridge processes
		$cmd = 'ps -ux | grep "commands/matterbridge" | grep -v grep | awk \'{print $2}\'';
		exec($cmd, $output, $ret);
		$runningPidList = [];
		foreach ($output as $o) {
			array_push($runningPidList, intval($o));
		}
		// get list of what should be running
		$expectedPidList = [];
		$this->manager->forAllRooms(function ($room) use (&$expectedPidList) {
			$bridge = $this->getBridgeOfRoom($room);
			if ($bridge['enabled'] && $bridge['pid'] !== 0) {
				array_push($expectedPidList, intval($bridge['pid']));
			}
		});
		// kill what should not be running
		foreach ($runningPidList as $runningPid) {
			if (!in_array($runningPid, $expectedPidList)) {
				$this->killPid($runningPid);
			}
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
		return (intval($ret) === 0 && !$isStillRunning);
	}

	/**
	 * Check if a process is running
	 *
	 * @param int $pid the process ID
	 * @return bool true if it's running
	 */
	private function isRunning(int $pid): bool {
		try {
			$result = shell_exec(sprintf('ps %d', $pid));
			if (count(preg_split('/\n/', $result)) > 2) {
				return true;
			}
		} catch (Exception $e) {
		}
		return false;
	}

	/**
	 * Stop all bridges
	 *
	 * @return bool success
	 */
	public function stopAllBridges(): bool {
		$this->manager->forAllRooms(function ($room) {
			if ($room->getType() === Room::GROUP_CALL || $room->getType() === Room::PUBLIC_CALL) {
				$bridge = $this->getBridgeOfRoom($room);
				// disable bridge in stored config
				$bridge['enabled'] = false;
				$this->saveBridgeToDb($room, $bridge);
				// this will kill the bridge process
				$this->checkBridgeProcess($token, $currentBridge);
			}
		});

		// finally kill all potential zombie matterbridge processes
		$this->killZombieBridges();
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
		$qb->select('json_values')
			->from('talk_bridges', 'b')
			->where(
				$qb->expr()->eq('room_id', $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		$jsonValues = '{"enabled":false,"pid":0,"parts":[]}';
		while ($row = $req->fetch()) {
			$jsonValues = $row['json_values'];
			break;
		}
		$req->closeCursor();

		return json_decode($jsonValues, true);
	}

	/**
	 * Save bridge information for one room
	 *
	 * @param Room $room the room
	 * @param array $bridge bridge values
	 */
	private function saveBridgeToDb(Room $room, array $bridge): void {
		$roomId = $room->getId();
		$jsonValues = json_encode($bridge);

		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert('talk_bridges')
				->values([
					'room_id' => $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
					'json_values' => $qb->createNamedParameter($jsonValues, IQueryBuilder::PARAM_STR),
				]);
			$req = $qb->execute();
		} catch (UniqueConstraintViolationException $e) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('talk_bridges');
			$qb->set('json_values', $qb->createNamedParameter($jsonValues, IQueryBuilder::PARAM_STR));
			$qb->where(
				$qb->expr()->eq('room_id', $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT))
			);
			$req = $qb->execute();
		}
	}

	public function getCurrentVersionFromBinary(): ?string {
		$binaryPath = $this->config->getAppValue('spreed', 'matterbridge_binary');
		if (!file_exists($binaryPath)) {
			return null;
		}

		$cmd = escapeshellcmd($binaryPath) . ' ' . escapeshellarg('-version');
		@exec($cmd, $output, $returnCode);

		if ($returnCode !== 0) {
			return null;
		}

		return trim(implode("\n", $output));
	}
}
