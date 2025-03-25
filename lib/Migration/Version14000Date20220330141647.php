<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Model\Attachment;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version14000Date20220330141647 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('talk_attachments')) {
			$table = $schema->createTable('talk_attachments');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('room_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('message_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('message_time', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('object_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);

			$table->addIndex(['room_id', 'object_type'], 'objects_in_room');

			return $schema;
		}
		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$sql = $this->connection->getDatabasePlatform()
			->getTruncateTableSQL('`*PREFIX*talk_attachments`');
		$this->connection->executeQuery($sql);

		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_attachments')
			->setValue('room_id', $insert->createParameter('room_id'))
			->setValue('message_id', $insert->createParameter('message_id'))
			->setValue('message_time', $insert->createParameter('message_time'))
			->setValue('object_type', $insert->createParameter('object_type'))
			->setValue('actor_type', $insert->createParameter('actor_type'))
			->setValue('actor_id', $insert->createParameter('actor_id'));

		$offset = -1;
		$select = $this->connection->getQueryBuilder();
		$select->select('id', 'creation_timestamp', 'object_id', 'actor_type', 'actor_id', 'message')
			->from('comments')
			->where($select->expr()->eq('object_type', $select->createParameter('object_type')))
			->andWhere($select->expr()->eq('verb', $select->createParameter('verb')))
			->andWhere($select->expr()->gt('id', $select->createParameter('offset')))
			->orderBy('id', 'ASC')
			->setMaxResults(1000);

		$select->setParameter('object_type', 'chat')
			->setParameter('verb', 'object_shared');

		while ($offset !== 0) {
			$offset = $this->chunkedWriting($insert, $select, max($offset, 0));
		}
	}

	protected function chunkedWriting(IQueryBuilder $insert, IQueryBuilder $select, int $offset): int {
		$select->setParameter('offset', $offset);

		$attachments = $sharesWithoutMimetype = [];
		$result = $select->executeQuery();
		while ($row = $result->fetch()) {
			$attachment = [
				'room_id' => (int)$row['object_id'],
				'message_id' => (int)$row['id'],
				'actor_type' => $row['actor_type'],
				'actor_id' => $row['actor_id'],
			];

			$datetime = new \DateTime($row['creation_timestamp']);
			$attachment['message_time'] = $datetime->getTimestamp();

			$message = json_decode($row['message'], true);
			$messageType = $message['message'] ?? '';
			$parameters = $message['parameters'] ?? [];

			if ($messageType === 'object_shared') {
				$objectType = $parameters['objectType'] ?? '';
				if ($objectType === 'geo-location') {
					$attachment['object_type'] = Attachment::TYPE_LOCATION;
				} elseif ($objectType === 'deck-card') {
					$attachment['object_type'] = Attachment::TYPE_DECK_CARD;
				} else {
					$attachment['object_type'] = Attachment::TYPE_OTHER;
				}
			} else {
				$messageType = $parameters['metaData']['messageType'] ?? '';
				$mimetype = $parameters['metaData']['mimeType'] ?? '';

				if ($messageType === 'voice-message') {
					$attachment['object_type'] = Attachment::TYPE_VOICE;
				} elseif (str_starts_with($mimetype, 'audio/')) {
					$attachment['object_type'] = Attachment::TYPE_AUDIO;
				} elseif (str_starts_with($mimetype, 'image/') || str_starts_with($mimetype, 'video/')) {
					$attachment['object_type'] = Attachment::TYPE_MEDIA;
				} else {
					if ($mimetype === '' && isset($parameters['share'])) {
						$sharesWithoutMimetype[(int)$parameters['share']] = (int)$row['id'];
					}
					$attachment['object_type'] = Attachment::TYPE_FILE;
				}
			}

			$attachments[(int)$row['id']] = $attachment;
		}
		$result->closeCursor();

		if (empty($attachments)) {
			return 0;
		}

		$mimetypes = $this->getMimetypeFromFileCache(array_keys($sharesWithoutMimetype));
		foreach ($mimetypes as $shareId => $mimetype) {
			if (!isset($attachments[$sharesWithoutMimetype[$shareId]])) {
				continue;
			}

			if (str_starts_with($mimetype, 'audio/')) {
				$attachments[$sharesWithoutMimetype[$shareId]]['object_type'] = Attachment::TYPE_AUDIO;
			} elseif (str_starts_with($mimetype, 'image/') || str_starts_with($mimetype, 'video/')) {
				$attachments[$sharesWithoutMimetype[$shareId]]['object_type'] = Attachment::TYPE_MEDIA;
			}
		}

		$this->connection->beginTransaction();
		foreach ($attachments as $attachment) {
			$insert
				->setParameter('room_id', $attachment['room_id'], IQueryBuilder::PARAM_INT)
				->setParameter('message_id', $attachment['message_id'], IQueryBuilder::PARAM_INT)
				->setParameter('message_time', $attachment['message_time'], IQueryBuilder::PARAM_INT)
				->setParameter('actor_type', $attachment['actor_type'])
				->setParameter('actor_id', $attachment['actor_id'])
				->setParameter('object_type', $attachment['object_type'])
			;

			$insert->executeStatement();
		}
		$this->connection->commit();

		return end($attachments)['message_id'];
	}

	protected function getMimetypeFromFileCache(array $shareIds): array {
		$mimetype = [];

		$query = $this->connection->getQueryBuilder();
		$query->select('s.id', 'm.mimetype')
			->from('share', 's')
			->leftJoin('s', 'filecache', 'f', $query->expr()->eq('s.file_source', 'f.fileid'))
			->leftJoin('f', 'mimetypes', 'm', $query->expr()->eq('f.mimetype', 'm.id'))
			->where($query->expr()->in('s.id', $query->createNamedParameter($shareIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$mimetype[$row['id']] = $row['mimetype'];
		}
		$result->closeCursor();

		return $mimetype;
	}
}
