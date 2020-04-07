<?php

declare(strict_types=1);

namespace OCA\Talk\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version8000Date20200407115318 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('assigned_hpb')) {
			$table->addColumn('assigned_hpb', Type::INTEGER, [
				'notnull' => false,
				'length' => 4,
				'unsigned' => false,
				'default' => null,
			]);
		}

		return $schema;
	}
}
