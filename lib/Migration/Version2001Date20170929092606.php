<?php
namespace OCA\Spreed\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2001Date20170929092606 extends SimpleMigrationStep {

	/** @var IConfig */
	protected $config;

	/**
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `Schema`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$stunServer = $this->config->getAppValue('spreed', 'stun_server', 'stun.nextcloud.com:443');
		$turnServer = [
			'server' => $this->config->getAppValue('spreed', 'turn_server'),
			'secret' => $this->config->getAppValue('spreed', 'turn_server_secret'),
			'protocols' => $this->config->getAppValue('spreed', 'turn_server_protocols'),
		];

		$this->config->setAppValue('spreed', 'stun_servers', json_encode([$stunServer]));
		if ($turnServer['server'] !== '' && $turnServer['secret'] !== '' && $turnServer['protocols'] !== '') {
			$this->config->setAppValue('spreed', 'turn_servers', json_encode([$turnServer]));
		}

		$this->config->deleteAppValue('spreed', 'stun_server');
		$this->config->deleteAppValue('spreed', 'turn_server');
		$this->config->deleteAppValue('spreed', 'turn_server_secret');
		$this->config->deleteAppValue('spreed', 'turn_server_protocols');
	}
}
