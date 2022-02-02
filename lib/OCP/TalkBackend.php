<?php

namespace OCA\Talk\OCP;

use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\IURLGenerator;
use OCP\Talk\IConversation;
use OCP\Talk\IConversationOptions;
use OCP\Talk\ITalkBackend;

class TalkBackend implements ITalkBackend {
	protected Manager $manager;
	protected ParticipantService $participantService;
	protected IURLGenerator $url;

	public function __construct(Manager $manager,
								ParticipantService $participantService,
								IURLGenerator $url) {
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->url = $url;
	}

	public function createConversation(string $name, array $moderators, IConversationOptions $options): IConversation {
		$room = $this->manager->createRoom(
			$options->isPublic() ? Room::TYPE_PUBLIC : Room::TYPE_GROUP,
			$name
		);

		if (!empty($moderators)) {
			$users = [];
			foreach ($moderators as $moderator) {
				$users[] = [
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $moderator->getUID(),
					'participantType' => Participant::MODERATOR,
				];
			}

			$this->participantService->addUsers($room, $users);
		}

		return new Conversation($this->url, $room);
	}
}
