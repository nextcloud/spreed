<?php

namespace OCA\Circles\Events {

	use OCA\Circles\Model\Circle;
	use OCA\Circles\Model\Member;
	use OCP\EventDispatcher\Event;

	class AddingCircleMemberEvent extends Event {
		public function getCircle(): Circle {
		}
		public function getMember(): Member {
		}
	}

	class CircleDestroyedEvent extends Event {
		public function getCircle(): Circle {
		}
	}

	class RemovingCircleMemberEvent extends Event {
		public function getCircle(): Circle {
		}
		public function getMember(): Member {
		}
	}
}
