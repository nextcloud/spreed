<?php
namespace OCA\Spreed\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2001Date20170913104501 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `Schema`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `Schema`
	 * @param array $options
	 * @return null|Schema
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var Schema $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('videocalls_signaling')) {
			$table = $schema->createTable('videocalls_signaling');

			$table->addColumn('sender', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('recipient', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('message', Type::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('timestamp', Type::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);

			$table->addIndex(['recipient', 'timestamp'], 'vcsig_recipient');
		}

		if ($schema->hasTable('spreedme_messages')) {
			$schema->dropTable('spreedme_messages');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `Schema`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
	}
}
