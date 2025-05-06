<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SpreedCheats\Controller;

use OCA\SpreedCheats\Calendar\EventGenerator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Calendar\Exceptions\CalendarException;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\Share\IShare;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\UUIDUtil;

class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected IDBConnection $db,
		private IManager $calendarManager,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	public function resetSpreed(): DataResponse {
		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_attachments')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_attendees')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_bots_conversation')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_bots_server')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_bridges')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_commands')
			->where($delete->expr()->neq('app', $delete->createNamedParameter('')))
			->andWhere($delete->expr()->neq('command', $delete->createNamedParameter('help')))
			->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_consent')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_internalsignaling')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_invitations')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_phone_numbers')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_polls')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_poll_votes')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_proxy_messages')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_reminders')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_retry_ocm')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_rooms')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_sessions')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('share')
			->where($delete->expr()->orX(
				$delete->expr()->eq('share_type', $delete->createNamedParameter(IShare::TYPE_ROOM)),
				$delete->expr()->eq('share_type', $delete->createNamedParameter(11 /*RoomShareProvider::SHARE_TYPE_USERROOM*/))
			))
			->executeStatement();


		$delete = $this->db->getQueryBuilder();
		$delete->delete('preferences')
			->where($delete->expr()->in('configkey', $delete->createNamedParameter(['changelog', 'note_to_self', 'samples_created'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($delete->expr()->eq('appid', $delete->createNamedParameter('spreed')))
			->executeStatement();

		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('notifications')
				->where($delete->expr()->eq('app', $delete->createNamedParameter('spreed')))
				->executeStatement();
		} catch (\Throwable $e) {
			// Ignore
		}

		$delete = $this->db->getQueryBuilder();
		$delete->delete('calendarobjects')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('calendarobjects_props')->executeStatement();


		return new DataResponse();
	}

	public function ageChat(string $token, int $hours): DataResponse {
		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('talk_rooms')
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));

		$result = $query->executeQuery();
		$roomId = (int)$result->fetchOne();
		$result->closeCursor();

		if (!$roomId) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		$update = $this->db->getQueryBuilder();
		$update->update('comments')
			->set('creation_timestamp', $update->createParameter('creation_timestamp'))
			->set('expire_date', $update->createParameter('expire_date'))
			->set('meta_data', $update->createParameter('meta_data'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		$query = $this->db->getQueryBuilder();
		$query->select('id', 'creation_timestamp', 'expire_date', 'meta_data')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($roomId)));

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$creationTimestamp = new \DateTime($row['creation_timestamp']);
			$creationTimestamp->sub(new \DateInterval('PT' . $hours . 'H'));

			$expireDate = null;
			if ($row['expire_date']) {
				$expireDate = new \DateTime($row['expire_date']);
				$expireDate->sub(new \DateInterval('PT' . $hours . 'H'));
			}

			$metaData = 'null';
			if ($row['meta_data'] !== 'null') {
				$metaData = json_decode($row['meta_data'], true);
				if (isset($metaData['last_edited_time'])) {
					$metaData['last_edited_time'] -= $hours * 3600;
				}
				$metaData = json_encode($metaData);
			}

			$update->setParameter('id', $row['id']);
			$update->setParameter('creation_timestamp', $creationTimestamp, IQueryBuilder::PARAM_DATE);
			$update->setParameter('expire_date', $expireDate, IQueryBuilder::PARAM_DATE);
			$update->setParameter('meta_data', $metaData);
			$update->executeStatement();
		}
		$result->closeCursor();

		return new DataResponse();
	}

	#[NoAdminRequired]
	public function createDashboardEvents(string $name, string $location): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}

		$calendar = null;
		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $this->userId);
		foreach ($calendars as $c) {
			if ($c instanceof ICreateFromString) {
				$calendar = $c;
			}
		}

		if ($calendar === null) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		$start = time();
		$end = time() + 3600;
		$events = EventGenerator::generateEvents($name, $location, $start, $end);

		foreach ($events as $event) {
			try {
				/** @var ICreateFromString $calendar */
				$calendar->createFromString(random_int(0, 10000) . '.ics', $event);
			} catch (CalendarException) {
				return new DataResponse(null, Http::STATUS_FORBIDDEN);
			}
		}

		return new DataResponse();
	}

	#[NoAdminRequired]
	public function createEventAndInviteParticipant(string $organizer, string $attendee): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}

		$calendar = null;
		// Create a calendar event with LOCATION and time via OCP
		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $this->userId);
		foreach ($calendars as $c) {
			if ($c instanceof ICreateFromString) {
				$calendar = $c;
			}
		}

		if ($calendar === null) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		$start = time();
		$end = $start + 3600;
		$startTime = (new \DateTime())->setTimestamp($start);
		$endTime = (new \DateTime())->setTimestamp($end);
		for ($i = 0; $i < 3; $i++) {
			$interval = new \DateInterval('PT2H');
			$startTime->add($interval);
			$endTime->add($interval);

			$vCalendar = new VCalendar();
			$vevent = $vCalendar->createComponent('VEVENT');
			$vevent->add('UID', UUIDUtil::getUUID());
			$vevent->add('DTSTART');
			$vevent->DTSTART->setDateTime($startTime);
			$vevent->add('DTEND');
			$vevent->DTEND->setDateTime($endTime);
			$vevent->add('SUMMARY', 'Test');
			$vevent->add('DESCRIPTION', 'Test');
			$vevent->add('ORGANIZER', 'mailto:' . $organizer . '@example.tld', ['CN' => $organizer]);
			$vevent->add('ATTENDEE', 'mailto:' . $attendee . '@example.tld', [
				'CN' => $attendee,
				'CUTYPE' => 'INDIVIDUAL',
				'PARTSTAT' => 'NEEDS-ACTION',
				'ROLE' => 'REQ-PARTICIPANT',
				'RSVP' => 'TRUE'
			]);
			$vevent->add('STATUS', 'CONFIRMED');

			$vCalendar->add($vevent);
			$cal = $vCalendar->serialize();
			try {
				/** @var ICreateFromString $calendar */
				$calendar->createFromString(random_int(0, 10000) . '.ics', $cal);
			} catch (CalendarException) {
				return new DataResponse(null, Http::STATUS_FORBIDDEN);
			}
		}
		return new DataResponse();
	}
}
