<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018, Joas Schilling <coding@schilljs.com>
 *
 * @license   GNU AGPL version 3 or any later version
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

namespace OCA\Spreed\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version5022Date20190315050000 extends SimpleMigrationStep
{


    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array   $options
     *
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tableName = 'talk_users_status';

        if ($schema->hasTable($tableName)) {
            $schema->dropTable($tableName);
        }

        $table = $schema->createTable($tableName);

        // Primary id
        $table->addColumn('id', Type::INTEGER, [
            'autoincrement' => true,
            'notnull'       => true,
            'length'        => 11,
        ]);

        // User id
        $table->addColumn('user_id', Type::STRING, [
            'notnull' => false,
            'length'  => 255,
        ]);

        // Status: online, offline, away
        $table->addColumn('status', Type::STRING, [
            'notnull' => true,
            'length'  => 11,
        ]);

        // Last checked status.
        $table->addColumn('updated_at', Type::BIGINT, [
            'notnull' => true,
        ]);

        return $schema;
    }
}
