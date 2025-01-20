<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Service;

use OCA\TalkWebhookDemo\Model\LogEntry;
use OCA\TalkWebhookDemo\Model\LogEntryMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\L10N\IFactory;

class SummaryService {
	public const LIST_PATTERN = '/^[-*]\s(\[[ x]])[^\S\n]*/mi';
	public const TODO_UNSOLVED_PATTERN = '/^[-*]\s\[ ][^\S\n]*/mi';
	public const TODO_SOLVED_PATTERN = '/^[-*]\s\[x][^\S\n]*/mi';

	public const SUMMARY_PATTERN = '/(?:^[-*]\s|^)(to[\s-]?do|solved|task|note|report|decision)s?\s*:/mi';
	public const TODO_PATTERN = '/^(to[\s-]?do|task)$/i';
	public const SOLVED_PATTERN = '/^solved$/i';
	public const NOTE_PATTERN = '/^note$/i';
	public const REPORT_PATTERN = '/^report$/i';
	public const DECISION_PATTERN = '/^decision$/i';
	public const AGENDA_PATTERN = '/(^[-*]\s|^)(agenda|top|topic)\s*:/mi';

	public function __construct(
		protected IConfig $config,
		protected LogEntryMapper $logEntryMapper,
		protected ITimeFactory $timeFactory,
		protected IDateTimeFormatter $dateTimeFormatter,
		protected IFactory $l10nFactory,
	) {
	}

	public function readTasksFromMessage(string $message, array $messageData, string $server, array $data): bool {
		$endOfFirstLine = strpos($message, "\n") ?: -1;
		$firstLowerLine = strtolower(substr($message, 0, $endOfFirstLine));

		if (!preg_match(self::LIST_PATTERN, $firstLowerLine)
			&& !preg_match(self::SUMMARY_PATTERN, $firstLowerLine)) {
			return false;
		}

		$placeholders = $replacements = [];
		foreach ($messageData['parameters'] as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if ($parameter['type'] === 'user') {
				if (str_contains($parameter['id'], ' ') || str_contains($parameter['id'], '/')) {
					$replacements[] = '@"' . $parameter['id'] . '"';
				} else {
					$replacements[] = '@' . $parameter['id'];
				}
			} elseif ($parameter['type'] === 'call') {
				$replacements[] = '@all';
			} elseif ($parameter['type'] === 'guest') {
				$replacements[] = '@' . $parameter['name'];
			} else {
				$replacements[] = $parameter['name'];
			}
		}

		$parsedMessage = str_replace($placeholders, $replacements, $message);
		$parsedMessage = preg_replace(self::TODO_SOLVED_PATTERN, '- solved: ', $parsedMessage);
		$parsedMessage = preg_replace(self::TODO_UNSOLVED_PATTERN, '- todo: ', $parsedMessage);

		if (preg_match(self::SUMMARY_PATTERN, $parsedMessage)) {
			$todos = preg_split(self::SUMMARY_PATTERN, $parsedMessage, flags: PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
			$nextEntry = null;
			foreach ($todos as $todo) {
				if (preg_match(self::TODO_PATTERN, $todo)) {
					$nextEntry = LogEntry::TYPE_TODO;
				} elseif (preg_match(self::SOLVED_PATTERN, $todo)) {
					$nextEntry = LogEntry::TYPE_SOLVED;
				} elseif (preg_match(self::SOLVED_PATTERN, $todo)) {
					$nextEntry = LogEntry::TYPE_SOLVED;
				} elseif (preg_match(self::NOTE_PATTERN, $todo)) {
					$nextEntry = LogEntry::TYPE_NOTE;
				} elseif (preg_match(self::REPORT_PATTERN, $todo)) {
					$nextEntry = LogEntry::TYPE_REPORT;
				} elseif (preg_match(self::DECISION_PATTERN, $todo)) {
					$nextEntry = LogEntry::TYPE_DECISION;
				} elseif ($nextEntry !== null) {
					$todoText = trim($todo);
					if ($todoText) {
						// Only store when not empty
						$this->saveTask($server, $data['target']['id'], $todoText, $nextEntry);
					}
					$nextEntry = null;
				}
			}

			// React with thumbs up as we detected a task
			return true;
		}

		return false;
	}

	public function readAgendaFromMessage(string $message, array $messageData, string $server, array $data): bool {
		$endOfFirstLine = strpos($message, "\n") ?: -1;
		$firstLowerLine = strtolower(substr($message, 0, $endOfFirstLine));

		if (!preg_match(self::AGENDA_PATTERN, $firstLowerLine)) {
			return false;
		}

		$placeholders = $replacements = [];
		foreach ($messageData['parameters'] as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if ($parameter['type'] === 'user') {
				if (str_contains($parameter['id'], ' ') || str_contains($parameter['id'], '/')) {
					$replacements[] = '@"' . $parameter['id'] . '"';
				} else {
					$replacements[] = '@' . $parameter['id'];
				}
			} elseif ($parameter['type'] === 'call') {
				$replacements[] = '@all';
			} elseif ($parameter['type'] === 'guest') {
				$replacements[] = '@' . $parameter['name'];
			} else {
				$replacements[] = $parameter['name'];
			}
		}

		$parsedMessage = str_replace($placeholders, $replacements, $message);
		$agendas = preg_split(self::AGENDA_PATTERN, $parsedMessage, flags: PREG_SPLIT_NO_EMPTY);
		foreach ($agendas as $agenda) {
			$agendaText = trim($agenda);
			if ($agendaText) {
				// Only store when not empty
				$this->saveTask($server, $data['target']['id'], $agendaText, LogEntry::TYPE_AGENDA);
			}
		}

		// React with thumbs up as we detected a task
		return true;
	}

	protected function saveTask(string $server, string $token, string $text, string $type): void {
		$logEntry = new LogEntry();
		$logEntry->setServer($server);
		$logEntry->setToken($token);
		$logEntry->setType($type);
		$logEntry->setDetails($text);
		$this->logEntryMapper->insert($logEntry);
	}

	/**
	 * @param string $server
	 * @param string $token
	 * @param string $roomName
	 * @param string $lang
	 * @return array{summary: string, elevator: ?int}|null
	 */
	public function summarize(string $server, string $token, string $roomName, string $lang = 'en'): ?array {
		$logEntries = $this->logEntryMapper->findByConversation($server, $token);
		$this->logEntryMapper->deleteByConversation($server, $token);

		$libL10N = $this->l10nFactory->get('lib', $lang);
		$l = $this->l10nFactory->get('talk_webhook_demo', $lang);

		$endDateTime = $this->timeFactory->now();
		$endTimestamp = $endDateTime->getTimestamp();
		$startTimestamp = $endTimestamp;

		$attendees = $todos = $solved = $notes = $decisions = $reports = [];
		$elevator = null;

		foreach ($logEntries as $logEntry) {
			if ($logEntry->getType() === LogEntry::TYPE_START) {
				$time = (int)$logEntry->getDetails();
				if ($startTimestamp > $time) {
					$startTimestamp = $time;
				}
			} elseif ($logEntry->getType() === LogEntry::TYPE_ATTENDEE) {
				$attendees[] = $logEntry->getDetails();
			} elseif ($logEntry->getType() === LogEntry::TYPE_TODO) {
				$todos[] = $logEntry->getDetails();
			} elseif ($logEntry->getType() === LogEntry::TYPE_SOLVED) {
				$solved[] = $logEntry->getDetails();
			} elseif ($logEntry->getType() === LogEntry::TYPE_NOTE) {
				$notes[] = $logEntry->getDetails();
			} elseif ($logEntry->getType() === LogEntry::TYPE_DECISION) {
				$decisions[] = $logEntry->getDetails();
			} elseif ($logEntry->getType() === LogEntry::TYPE_REPORT) {
				$reports[] = $logEntry->getDetails();
			} elseif ($logEntry->getType() === LogEntry::TYPE_ELEVATOR) {
				$elevator = (int)$logEntry->getDetails();
			}
		}

		if (($endTimestamp - $startTimestamp) < (int)$this->config->getAppValue('talk_webhook_demo', 'min-length', '60')) {
			// No call summary for short calls
			return null;
		}

		$attendees = array_unique($attendees);
		sort($attendees);

		$systemDefault = $this->config->getSystemValueString('default_timezone', 'UTC');
		$timezoneString = $this->config->getAppValue('talk_webhook_demo', 'timezone', $systemDefault);
		$timezone = null;
		if ($timezoneString !== 'UTC') {
			try {
				$timezone = new \DateTimeZone($timezoneString);
			} catch (\Throwable) {
			}
		}

		$startDate = $this->dateTimeFormatter->formatDate($startTimestamp, 'full', $timezone, $libL10N);
		$startTime = $this->dateTimeFormatter->formatTime($startTimestamp, 'short', $timezone, $libL10N);
		$endTime = $this->dateTimeFormatter->formatTime($endTimestamp, 'short', $timezone, $libL10N);

		$summary = '# ' . $this->getTitle($l, $roomName) . "\n\n";
		$summary .= $startDate . ' · ' . $startTime . ' – ' . $endTime;
		if ($timezone !== null) {
			$summary .= ' (' . $timezone->getName() . ")\n";
		} else {
			$summary .= ' (' . $endDateTime->getTimezone()->getName() . ")\n";
		}

		$summary .= "\n";
		$summary .= '## ' . $l->t('Attendees') . "\n";
		foreach ($attendees as $attendee) {
			$summary .= '- ' . $attendee . "\n";
		}

		if (!empty($todos) || !empty($solved)) {
			$summary .= "\n";
			$summary .= '## ' . $l->t('Tasks') . "\n";
			foreach ($solved as $todo) {
				$summary .= '- [x] ' . $todo . "\n";
			}
			foreach ($todos as $todo) {
				$summary .= '- [ ] ' . $todo . "\n";
			}
		}

		if (!empty($notes)) {
			$summary .= "\n";
			$summary .= '## ' . $l->t('Notes') . "\n";
			foreach ($notes as $note) {
				$summary .= '- ' . $note . "\n";
			}
		}

		if (!empty($reports)) {
			$summary .= "\n";
			$summary .= '## ' . $l->t('Reports') . "\n";
			foreach ($reports as $report) {
				$summary .= '- ' . $report . "\n";
			}
		}

		if (!empty($decisions)) {
			$summary .= "\n";
			$summary .= '## ' . $l->t('Decisions') . "\n";
			foreach ($decisions as $decision) {
				$summary .= '- ' . $decision . "\n";
			}
		}

		return ['summary' => $summary, 'elevator' => $elevator];
	}

	/**
	 * @param string $server
	 * @param string $token
	 * @param string $lang
	 * @return ?string
	 */
	public function agenda(string $server, string $token, string $lang = 'en'): ?string {
		$logEntries = $this->logEntryMapper->findByConversation($server, $token);
		$this->logEntryMapper->deleteByConversation($server, $token);


		$agenda = [];
		foreach ($logEntries as $logEntry) {
			if ($logEntry->getType() === LogEntry::TYPE_AGENDA) {
				$agenda[] = $logEntry->getDetails();
			}
		}

		if (empty($agenda)) {
			return null;
		}
		$agenda = array_unique($agenda);

		$l = $this->l10nFactory->get('talk_webhook_demo', $lang);
		$summary = '# ' . $l->t('Agenda') . "\n\n";
		foreach ($agenda as $item) {
			$summary .= '- [ ] ' . $item . "\n";
		}

		return $summary;
	}

	protected function getTitle(IL10N $l, string $roomName): string {
		try {
			$data = json_decode($roomName, true, flags: JSON_THROW_ON_ERROR);
			if (is_array($data) && count($data) === 2 && isset($data[0]) && is_string($data[0]) && isset($data[1]) && is_string($data[1])) {
				// Seems like the room name is a JSON map with the 2 user IDs of a 1-1 conversation,
				// so we don't add it to the title to avoid things like:
				// `Call summary - ["2991c735-4f9e-46e2-a107-7569dd19fdf8","42e6a9c2-a833-43f6-ab47-6b7004094912"]`
				return $l->t('Call summary');
			}
		} catch (\JsonException) {
		}

		return str_replace('{title}', $roomName, $l->t('Call summary - {title}'));
	}
}
