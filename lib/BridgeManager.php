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
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IURLGenerator;

use OCA\Talk\Exceptions\ImpossibleToKillException;

class BridgeManager {
	public const EVENT_TOKEN_GENERATE = self::class . '::generateNewToken';

	/** @var IDBConnection */
	private $db;
	/** @var IConfig */
	private $config;
	/** @var IAppData */
	private $appData;
	/** @var IL10N */
	private $l;

	public function __construct(IDBConnection $db,
								IConfig $config,
								IAppData $appData,
								IURLGenerator $urlGenerator,
								Manager $manager,
								IL10N $l) {
		$this->db = $db;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->appData = $appData;
		$this->manager = $manager;
		$this->l = $l;
	}

	public function getBridgeOfRoom(string $token): array {
		$defaultParts = sprintf(
			'{"enabled":false,"pid":0,"parts":[{"type":"nctalk","server":"","login":"","password":"","channel":"%s"}]}',
			$token
		);
		$bridgeJSON = $this->config->getAppValue('spreed', 'bridge_' . $token, $defaultParts);
		$bridge = json_decode($bridgeJSON, true);
		return $bridge;
	}

	public function editBridgeOfRoom(string $token, bool $enabled, array $parts = []): bool {
		$currentBridge = $this->getBridgeOfRoom($token);
		$newBridge = [
			'enabled' => $enabled,
			'pid' => isset($currentBridge['pid']) ? $currentBridge['pid'] : 0,
		];
		if (count($parts) > 0) {
			$newBridge['parts'] = $parts;
		} else {
			$newBridge['parts'] = $currentBridge['parts'];
		}

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

	public function deleteBridgeOfRoom(string $token): bool {
		// first potentially kill the process
		$currentBridge = $this->getBridgeOfRoom($token);
		$currentBridge['enabled'] = false;
		$this->checkBridgeProcess($token, $currentBridge);
		// then actually delete the config
		$bridgeJSON = $this->config->deleteAppValue('spreed', 'bridge_' . $token);
		return true;
	}

	public function checkAllBridges() {
		// TODO call this from time to time to make sure everything is running fine
		$this->manager->forAllRooms(function ($room) {
			$token = $room->getToken();
			if ($room->getType() === Room::GROUP_CALL or $room->getType() === Room::PUBLIC_CALL) {
				$this->checkBridge($token);
			}
		});
	}

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

	private function editBridgeConfig(string $token, array $newBridge) {
		// TODO adapt that to use appData
		$configPath = sprintf('/tmp/bridge-%s.toml', $token);
		$configContent = $this->generateConfig($token, $newBridge);
		file_put_contents($configPath, $configContent);
	}

	private function generateConfig($token, array $bridge): string {
		$content = '';
		foreach ($bridge['parts'] as $k => $part) {
			if ($part['type'] === 'nctalk') {
				$content .= sprintf('[%s.%s]', $part['type'], $k) . "\n";
				if (isset($part['server']) and $part['server'] !== '') {
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
			}
		}

		$content .= '[[gateway]]' . "\n";
		$content .= '	name = "myGateway"' . "\n";
		$content .= '	enable = true' . "\n\n";

		foreach ($bridge['parts'] as $k => $part) {
			$content .= '[[gateway.inout]]' . "\n";
			$content .= sprintf('	account = "%s.%s"', $part['type'], $k) . "\n";
			if (in_array($part['type'], ['mattermost', 'matrix', 'nctalk'])) {
				$content .= sprintf('	channel = "%s"', $part['channel']) . "\n\n";
			}
		}

		return $content;
	}

	private function cleanUrl($url): string {
		$uo = parse_url($url);
		$result = $uo['host'];
		if ($uo['scheme'] === 'https' and !isset($uo['port'])) {
			$result .= ':443';
		} elseif (isset($uo['port'])) {
			$result .= ':' . $uo['port'];
		}
		$result .= $uo['path'];
		return $result;
	}

	/**
	 * check if a bridge process is running
	 * @return PID the corresponding matterbridge process ID, 0 if none
	 */
	private function checkBridgeProcess($token, $bridge): int {
		$pid = 0;

		if (isset($bridge['pid']) and intval($bridge['pid']) !== 0) {
			// config : there is a PID stored
			error_log('pid is defined in config |||||');
			$pid = $bridge['pid'];
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

	private function launchMatterbridge($token): int {
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
	 * kill the mattermost processes that do not match with any room
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
			if ($bridge['enabled'] and $bridge['pid'] !== 0) {
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

	private function killPid($pid): bool {
		// kill
		exec(sprintf('kill -9 %d', $pid), $output, $ret);
		// check the process is gone
		$isStillRunning = $this->isRunning($pid);
		return (intval($ret) === 0 and !$isStillRunning);
	}

	private function isRunning($pid): bool {
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
