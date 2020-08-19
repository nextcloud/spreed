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
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IURLGenerator;
use OC\Authentication\Token\IProvider as IAuthTokenProvider;
use OC\Authentication\Token\IToken;
use OCP\Security\ISecureRandom;

use OCA\Talk\Exceptions\ImpossibleToKillException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;

class BridgeManager {
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
								IL10N $l) {
		$this->db = $db;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->appData = $appData;
		$this->userManager = $userManager;
		$this->manager = $manager;
		$this->tokenProvider = $tokenProvider;
		$this->random = $random;
		$this->l = $l;
	}

	/**
	 * Get bridge information for a specific room
	 *
	 * @param string $token the room token
	 * @return array decoded json bridge information
	 */
	public function getBridgeOfRoom(string $token): array {
		$defaultParts = '{"enabled":false,"pid":0,"parts":[]}';
		$bridgeJSON = $this->config->getAppValue('spreed', 'bridge_' . $token, $defaultParts);
		$bridge = json_decode($bridgeJSON, true);
		return $bridge;
	}

	/**
	 * Edit bridge information for a room
	 *
	 * @param string $token the room token
	 * @param bool $enabled desired state of the bridge
	 * @param array $parts parts of the bridge (what it connects to)
	 * @return bool success
	 */
	public function editBridgeOfRoom(string $token, bool $enabled, array $parts = []): bool {
		$currentBridge = $this->getBridgeOfRoom($token);
		$newBridge = [
			'enabled' => $enabled,
			'pid' => isset($currentBridge['pid']) ? $currentBridge['pid'] : 0,
			'parts' => $parts,
		];

		// edit/update the config file
		$this->editBridgeConfig($token, $newBridge);

		// check state and manage the binary
		$pid = $this->checkBridgeProcess($token, $newBridge);
		$newBridge['pid'] = $pid;

		// save config
		$newBridgeJSON = json_encode($newBridge);
		$this->config->setAppValue('spreed', 'bridge_' . $token, $newBridgeJSON);

		return true;
	}

	/**
	 * Delete bridge information for a room
	 *
	 * @param string $token the room token
	 * @return bool success
	 */
	public function deleteBridgeOfRoom(string $token): bool {
		// first potentially kill the process
		$currentBridge = $this->getBridgeOfRoom($token);
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
	public function checkAllBridges() {
		// TODO call this from time to time to make sure everything is running fine
		$this->manager->forAllRooms(function ($room) {
			$token = $room->getToken();
			if ($room->getType() === Room::GROUP_CALL || $room->getType() === Room::PUBLIC_CALL) {
				$this->checkBridge($token);
			}
		});
	}

	/**
	 * For one room, check mattermost process respects desired state
	 * @param string $token the room token
	 */
	public function checkBridge(string $token) {
		$bridge = $this->getBridgeOfRoom($token);
		$pid = $this->checkBridgeProcess($token, $bridge);
		if ($pid !== $bridge['pid']) {
			// save the new PID if necessary
			$bridge['pid'] = $pid;
			$bridgeJSON = json_encode($bridge);
			$this->config->setAppValue('spreed', 'bridge_' . $token, $bridgeJSON);
		}
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
	 * @param string $token the room token
	 */
	private function editBridgeConfig(string $token, array $newBridge) {
		// check bot user exists and is member of the room
		// add the 'local' bridge part
		$newBridge = $this->addLocalPart($token, $newBridge);

		// TODO adapt that to use appData
		$configPath = sprintf('/tmp/bridge-%s.toml', $token);
		$configContent = $this->generateConfig($token, $newBridge);
		file_put_contents($configPath, $configContent);
	}

	/**
	 * Add a bridge part with bot credentials to connect to the room
	 *
	 * @param string $token the room token
	 * @param array $bridge bridge information
	 * @return array the bridge with local part added
	 */
	private function addLocalPart(string $token, array $bridge): array {
		$botInfo = $this->checkBotUser($token, $bridge['enabled']);
		$localPart = [
			'type' => 'nctalk',
			'login' => $botInfo['id'],
			'password' => $botInfo['password'],
			'channel' => $token,
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
	 * @param string $token the room token
	 * @param bool $create whether we should generate a new app token or not
	 * @return array Bot user information (username and app token). token is an empty string if creation was not asked.
	 */
	private function checkBotUser(string $token, bool $create): array {
		$botUserId = 'bridge-bot';
		// check if user exists and create it if necessary
		if (!$this->userManager->userExists($botUserId)) {
			$pass = md5(strval(rand()));
			$this->config->setAppValue('spreed', 'bot_pass', $pass);
			$botUser = $this->userManager->createUser($botUserId, $pass);
		} else {
			$botUser = $this->userManager->get($botUserId);
		}

		// check user is member of the room
		$room = $this->manager->getRoomByToken($token);
		try {
			$participant = $room->getParticipant($botUserId);
		} catch (ParticipantNotFoundException $e) {
			$room->addUsers([
				'userId' => $botUserId,
				'participantType' => Participant::USER,
			]);
		}

		// delete old bot app tokens for this room
		$tokenName = 'spreed_' . $token;
		$tokens = $this->tokenProvider->getTokenByUser($botUserId);
		foreach ($tokens as $t) {
			$j = $t->jsonSerialize();
			if ($j['name'] === $tokenName) {
				$this->tokenProvider->invalidateTokenById($botUserId, $j['id']);
			}
		}

		if ($create) {
			// generate app token for the bot
			$appToken = $this->random->generate(72, ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_DIGITS);
			$botPassword = $this->config->getAppValue('spreed', 'bot_pass', '');
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
	 * @param string $token the room token
	 * @return string config file content
	 */
	private function generateConfig($token, array $bridge): string {
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
	 * @param string $token the room token
	 * @param array $bridge bridge information
	 * @return int the corresponding matterbridge process ID, 0 if none
	 */
	private function checkBridgeProcess(string $token, array $bridge): int {
		$pid = 0;

		if (isset($bridge['pid']) && intval($bridge['pid']) !== 0) {
			// config : there is a PID stored
			error_log('pid is defined in config |||||');
			$pid = intval($bridge['pid']);
			$isRunning = $this->isRunning($pid);
			// if bridge running and enabled is false : kill it
			if ($isRunning) {
				if ($bridge['enabled']) {
					error_log('process running AND config enabled : doing nothing |||||');
				} else {
					error_log('process running AND config DISABLED : KILL |||||');
					error_log('KILL '.$pid.'||||');
					$killed = $this->killPid($pid);
					if ($killed) {
						$pid = 0;
					} else {
						error_log('IMPOSSIBLE to kill '.$pid.'||||');
						throw new ImpossibleToKillException('Impossible to kill bridge process [' . $pid . ']');
					}
				}
			} else {
				// no process found
				if ($bridge['enabled']) {
					error_log('process not found AND config enabled : RELAUNCHING |||||');
					$pid = $this->launchMatterbridge($token);
				} else {
					error_log('process not found AND config disabled : doing nothing |||||');
				}
			}
		} elseif ($bridge['enabled']) {
			// config : no PID stored
			// config : enabled => launch it
			$pid = $this->launchMatterbridge($token);
			error_log('LAUNCH '.$pid.'||||');
		} else {
			error_log('no PID defined in config AND config disabled : doing nothing |||||');
		}

		return $pid;
	}

	/**
	 * Actually launch a matterbridge process for a room
	 *
	 * @param string $token the room token
	 * @return int the corresponding matterbridge process ID, 0 if it failed
	 */
	private function launchMatterbridge(string $token): int {
		$binPath = __DIR__ . '/../sample-commands/matterbridge';
		// TODO this should be in appdata
		$configPath = sprintf('/tmp/bridge-%s.toml', $token);
		$outputPath = sprintf('/tmp/bridge-%s.log', $token);
		$cmd = sprintf('%s -conf %s', $binPath, $configPath);
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
	public function killZombieBridges() {
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
			$token = $room->getToken();
			$bridge = $this->getBridgeOfRoom($token);
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
}
