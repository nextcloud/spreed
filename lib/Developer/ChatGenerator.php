<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Developer;

use OCA\Talk\Room;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pure, deterministic planner for demo chat generation.
 *
 * Given a seed and the same input pools, it always returns the same plan.
 * No DB, no services — the {@see GenerateChats} command turns plans into rooms and messages.
 *
 * Q: Is the seed mechanism future proof in terms of a potential new/special message can be added and seeds still generate the same outcome?
 * A: Short answer: no, not in its current form — and it's worth understanding exactly where it breaks.
 *    Same seed + same code version + same user/group pools → identical output (two devs on the same git checkout reproducing a setup).
 *    The generator draws every decision from one shared RNG stream ($this->rng in ChatGenerator). Determinism therefore depends not just on the seed, but on the exact number and order of
 *    getInt()/pickArrayKeys() calls.
 *
 * @psalm-type MessagePlan = array{author: string, text: string, replyTo: int|null, secondsAgo: int, silent: bool}
 * @psalm-type RoomPlan = array{
 *     type: int,
 *     name: string,
 *     owner: string,
 *     users: list<string>,
 *     groups: list<string>,
 *     messages: list<MessagePlan>,
 * }
 */
class ChatGenerator {
	private const int PARTICIPANTS_PER_ROOM_MIN = 3;
	private const int PARTICIPANTS_PER_ROOM_MAX = 8;
	private const int GROUPS_PER_ROOM_MAX = 2;
	private const int BURST_CHANCE_PERCENT = 40;
	private const int BURST_MAX = 5;
	private const int REPLY_CHANCE_PERCENT = 25;
	private const int REPLY_WINDOW = 100;
	private const int SHORT_PERCENT = 60;
	private const int MEDIUM_PERCENT = 30;
	private const int SILENT_PERCENT = 5;

	private const array ROOM_NAMES = [
		'Hamster lounge', 'Beagle pack', 'Aquarium chat', 'Bunny burrow',
		'Reptile room', 'Cat tower', 'Parrot perch', 'Puppy playpen',
		'Ferret nest', 'Guinea pig pen', 'Turtle pond', 'Goldfish bowl',
		'Hedgehog hideout', 'Gecko corner', 'Pony paddock', 'Chinchilla dust bath',
	];

	private const array SHORT = [
		'pet pet pet', 'fed already', 'growing so fast!', 'so fluffy', 'boop',
		'🐾', 'treats time', 'nom nom', 'scritches', 'she\'s huge now', 'snoozing',
		'moar food', 'good boy', 'such floof', 'sleepy', 'wagging like crazy',
		'purr machine', 'tail wags', '🦴', 'kibble run', 'carrots gone already',
		'belly rub time', '🐶', '🐱', 'zoomies!', 'biscuits incoming',
		'tummy filled', 'tiny paws', 'wet nose', 'shed everywhere',
	];

	// Medium messages target ~150–300 characters: paragraph-length thoughts that sit between the
	// one-liner SHORT pool and the long-form LONG pool.
	private const array MEDIUM = [
		'She gained almost 200g this week and the vet wants me to start the adult kibble already, but she still inhales the puppy food like every meal is her last. Going to do a slow transition over the weekend and see how the tummy handles it.',
		'Anyone know a chew toy that actually survives more than a day with a beagle? The "indestructible" rubber one lasted six hours and the antler chew has chips out of it after three. Running out of ideas before she starts on the furniture again.',
		'Mine has refused the new food brand entirely for three days straight. I have tried mixing it with the old food, warming it up, adding a bit of broth — she just gives me a look like I have personally betrayed her trust and walks away from the bowl.',
		'The vet said {x} should be at full size by month eight, but we are past that and he keeps growing. Already bought the next size up of harness and collar. At this point I am just hoping he stops before he can reach the kitchen counter without standing up.',
		'Tried the slow-feeder bowl trick and meals now last about 15 minutes instead of the previous 12 seconds. The puzzle feeder is even better — that one stretched dinner out to almost half an hour and she actually seemed tired afterwards.',
		'Belly rubs after dinner are non-negotiable now apparently. She flops onto her back the moment the food bowl is put away and waits, sometimes for ten minutes if I do not notice her right away. The judgement when I am late is severe.',
		'{x} learned to open the treat cupboard yesterday. Watched her do it from the doorway — paw against the handle, push down, swing the door open with her nose. I have absolutely no idea how she figured this out and I am both proud and terrified.',
		'Anyone else\'s puppy go through a "refuse to walk past that one specific bush" phase? Mine will happily go past every other bush on the street but the moment we reach this one she plants her feet and stares like it personally insulted her ancestors.',
		'Switched to grain-free three weeks ago and the coat is noticeably shinier already. Less shedding too. The shedding part might just be the season turning, but the shine is definitely real because three different people have mentioned it without prompting.',
		'Pet sitter for next weekend — feeding schedule and walk times attached. Twice a day, breakfast and dinner, absolutely no table scraps under any circumstances. The puppy eyes are dangerous but she is on a strict diet and the vet was very specific about it.',
		'Just measured against the doorframe and she is 4cm taller than last month. I genuinely thought we were past the major growth spurts and now I am questioning everything. Where does it end. When do they stop. Send help and probably a bigger crate.',
		'Got the harness sized up again this week — third one this year. Going to try renting one through that subscription service some people have mentioned, because the cost of new harnesses every three months is genuinely adding up to a real expense by now.',
		'Carrot consumption has tripled since spring started. He vibrates with anticipation when I reach for the bag in the fridge, and the noise he makes when chewing them is loud enough to hear from the next room. The vet says it is fine, just watch the sugar.',
		'New scratching post arrived yesterday and was immediately and thoroughly ignored in favour of the couch corner he has been working on for six months. I sprayed it with the catnip spray that is supposed to attract them. Nothing. Total indifference.',
		'She purrs the entire time I brush her now, which is a massive upgrade from the hissing-and-biting routine we had going on for the first month. I think she finally connected the brushing with the satisfying back-scratch sensation rather than restraint.',
	];

	// Long messages target roughly 2000–3000 characters — multi-paragraph care notes that read like
	// real long-form chat posts about feeding, growth and bonding.
	private const array LONG = [
		"## Six month growth journal\n\nWhen we picked her up at eight weeks she fit comfortably in a shoebox and slept about twenty hours a day. I started a little spreadsheet because the vet asked us to track weight weekly for the first six months, and I figured I would record other things too while I was at it. Here is the summary now that we are at month six and the early-growth chaos is finally settling down.\n\n### Weight curve\n\n- Week 8: 1.8 kg, basically a hamster with floppy ears\n- Week 10: 2.6 kg, started chewing furniture corners\n- Week 12: 4.1 kg, ate her first sock, learned her name\n- Week 14: 5.4 kg, growth spurts felt almost daily\n- Week 16: 7.5 kg, climbed onto the couch unassisted for the first time\n- Week 18: 8.9 kg, energy peak, demanded three walks a day\n- Week 20: 10.3 kg, terror of all houseplants\n- Week 22: 11.8 kg, suddenly calmer for hours at a time\n- Week 24: 13.0 kg, currently in the floofy sleepy phase\n\n### Food story\n\nStarted on the breeder's recommended puppy formula and stuck with it for the first six weeks. We switched to a slightly higher-protein blend after she went through a phase of constant hunger that the previous food was not keeping up with. The transition took five days of careful mixing, with one short day of mild tummy issues in the middle. She has been on the new formula ever since and the coat has actually improved noticeably — shinier, less shedding, fewer little dry patches on the belly.\n\nShe now eats three meals a day at 6:30am, 12:30pm, and 6:30pm. The midday meal is the smallest because she has the lowest activity around that time. We will likely consolidate to two meals a day around month nine on the vet's advice, but for now three keeps her stable and stops the begging behaviour that ramps up if any single meal is too small.\n\n### Pets and affection\n\nThe most surprising thing has been how dramatically she has changed in how she accepts physical affection. As a tiny puppy she squirmed away from belly contact and would only tolerate quick head pats before wriggling free. By month four she would actively seek out scratches under the chin and along the chest. By month six she is a complete floof who flops onto her back the moment you sit down anywhere near her on the rug.\n\nWill probably stop tracking weight weekly soon and switch to monthly. Vet says we are right on the breed-typical curve and the only thing to keep an eye on is making sure the calorie intake adjusts as the growth rate slows down. Ping {x} if you want the actual spreadsheet — it has a fun little projection chart that has been within 5% accurate for the last two months.",
		"Been experimenting with feeding times for the last three weeks and wanted to write up what actually worked for our food-obsessed pup, since a few people have asked.\n\nOriginal schedule was 7am, noon, 6pm — same portions all three meals, which sounds reasonable on paper. The problem was that she would inhale the morning meal in under a minute, then beg constantly until lunch, then beg again from about 2pm until dinner. The vet flagged this pattern as a sign the portions were unbalanced relative to her actual activity level throughout the day. She was getting the same fuel for the slowest part of her day (mid-afternoon nap) as for the busiest (morning walk and play).\n\n### New schedule\n\n- 6:30am: small meal, just enough to take the edge off before the morning walk\n- 11:00am: medium meal, after she has burned energy on the walk\n- 5:00pm: large meal, before the evening walk so she has fuel for it\n- 9:00pm: small kibble snack, last thing before bed so she does not wake up hungry\n\nTotal daily intake is actually slightly less than before — about 8% less. We dropped some volume because she was clearly eating out of boredom rather than need in the old schedule.\n\n### Results after three weeks\n\nWay less begging — almost none, actually. No more food-gulping; the new portion sizes match what she can comfortably eat without rushing. Weight is steady at her target. She sleeps through the night without the 3am \"I am starving\" performance that we used to get every other night. The bedtime kibble snack turned out to be the single biggest improvement; whatever was triggering her hungry-wake-up routine seems to be fully addressed by having something small in her stomach overnight.\n\n### What I learned\n\nThe big takeaway was that matching meal size to upcoming activity matters way more than splitting calories evenly. The traditional \"three equal meals\" advice probably works fine for low-energy adult dogs but does not fit a teenage pup with wildly varying activity through the day. Recommend trying the activity-matched approach if anyone else has a food-obsessed pup who treats every meal like the last one they will ever get. Happy to share the exact gram measurements I landed on if {x} or anyone else wants to compare notes.",
		"Big breakthrough this week — {x} finally sat still for a proper belly rub for the first time since we adopted her six months ago. This took longer than I expected and felt almost impossible for the first few weeks, so I want to write down what worked in case anyone else is going through the same thing with a rescue who cannot tolerate certain types of contact.\n\nWhen she came home from the shelter she would let me pet her shoulders and the top of her head, but anything near the belly meant an immediate roll-and-bolt reaction. Not aggressive, just terrified, and clearly tied to whatever happened before she was rescued. The shelter notes said she had been found underweight and skittish, so it was reasonable to assume hands near her belly had not been a positive experience in her past life.\n\n### What did not work\n\nFor the first few weeks I tried the obvious approaches — slow, gentle hand movements while talking softly, treats while petting, that kind of thing. She would tolerate it for about three seconds and then get up and walk away. The treats actually made things slightly worse because she would take them politely and then increase her distance from me afterward. Clearly she was associating the treats with whatever uncomfortable contact she was bracing for.\n\n### What worked\n\nA friend who fosters rescues suggested completely removing the petting from the equation and just being a calm presence near her food bowl during meals, without making eye contact or reaching toward her. So for about ten days I just sat on the floor a few feet away while she ate. No talking, no movement, no expectation of interaction. Just present.\n\nBy day twelve she started coming over on her own after meals to do a little tentative sniffing. By the end of week two she was nudging my hand for head scratches without prompting. By week four she was sleeping on the rug right next to my chair every evening. The belly was still off-limits, but the overall trust level had completely transformed.\n\nThe actual belly breakthrough happened almost accidentally last night. She climbed onto the couch during a movie, settled against my leg, and at some point during the second hour just rolled onto her back and stayed there. I rested a hand on her chest very lightly and she did not flinch. After about a minute I moved my hand slowly down to her belly and she just sighed and went to sleep. Three months ago I would have said this was impossible. Now she demands belly time after every single meal.",
		"Switched the whole crew over to the new salmon-based recipe about six weeks ago and figured I would finally write up notes for anyone considering it, since I have been promising to do this for a while now.\n\n### The lineup\n\n- Senior dog, 11 years old, mid-sized mix: historically itchy on chicken-based foods, vet recommended trying a fish-based formula for the omega-3 content\n- Puppy, 4 months old, large breed: on the breeder's puppy kibble until now, no particular issues but ready to move to something with a broader nutrient profile\n- Cat, 3 years old, indoor: was NOT supposed to be eating it but somehow figured out how to steal it from the bag within the first week, so she is now part of the experiment by accident\n\n### Transition\n\nDid the standard slow swap over five days for the dogs — 25/75 new/old for two days, then 50/50, then 75/25, then full new. The senior dog took to it immediately, no transition issues at all, no tummy problems. The puppy refused for the first two days entirely, walking up to her bowl, sniffing, and then walking away in obvious disgust. By day three with the food mixed in, she started eating again, and by the end of the transition was eating normally. By week two she was clearing the bowl as enthusiastically as before. The cat, of course, ate it from day one as if it were the food she had been waiting for her entire life.\n\n### Results\n\nThe senior dog's coat has noticeably improved — less itching, fewer hot spots, generally looks shinier. Her energy seems slightly higher too, though it is hard to know if that is the food or just a good month. The puppy is growing on schedule and her stools are firmer than they were on the puppy kibble, which the vet says is a good sign. The cat is unchanged behaviourally but has gained a small amount of weight, which we are now trying to undo by being more vigilant about the bag.\n\n### Cost and conclusion\n\nIt works out about 30% more expensive than what we were using before. Definitely worth it for the senior dog. Probably worth it for the puppy too, though I would not have switched her specifically if it weren't for the household-wide decision. Will stick with this through winter and re-evaluate at the next vet check in March. Happy to share specific brand recommendations if {x} or anyone else wants them.",
		"```\nDay 1:   bites every finger that comes near, hides under the couch\nDay 7:   accepts food from hand if placed and stepped back from\nDay 14:  tolerates head scratches, growls quietly at body contact\nDay 21:  initiates contact for the first time, very brief\nDay 30:  full belly rubs, falls asleep on lap, makes happy little noises\n```\n\nThe progression with rescued animals never stops being amazing. {x} came to us absolutely refusing any human contact whatsoever. Not aggressive, just deeply terrified of hands and sudden movements. The vet who did the intake exam said be patient, let her set the pace, and do not push for affection — she will come to you when she is ready, which might be days or might be months.\n\n### Setup\n\nFor the first week we basically just made sure she had a safe space (a quiet corner with a blanket and a hideaway) and food was always available. No attempts at petting, no trying to coax her out, no eye contact. The only direct interaction was when she had to go to the vet for follow-up shots, which were predictably awful for everyone involved.\n\n### The food bowl strategy\n\nThe single most important thing we did was treat meal times as the primary trust-building opportunity. I would put down her food bowl, sit several feet away on the floor, and just exist in the same room without doing anything. No talking, no eye contact, no movement. The first few days she would not eat until I left the room entirely. By day five she would eat with me present if I was completely still. By day ten she would glance at me while eating, which felt like a huge step.\n\nAfter about two weeks I started extending an arm in her general direction during meals — not toward her but vaguely toward the bowl. After three weeks I could rest a hand on the floor near the bowl. After four weeks she was bumping that hand with her nose between bites. The actual petting started organically after that — she came over after a meal, sat next to me, and rested her head on my knee.\n\n### What I would do differently\n\nBe even more patient at the start. I made things slightly worse on day three by trying to coax her out with treats. She read that as pressure and retreated harder. The breakthrough only happened once I committed to truly doing nothing and letting her decide every step.\n\nThree months later she is a different animal — affectionate, playful, demanding of belly rubs, completely changed. The food bowl strategy is the one thing I would tell anyone struggling with a fearful rescue to try first.",
		"Quick growth comparison thread for anyone tracking their first puppy and wondering if the numbers they are seeing are normal or worrying.\n\nMine is a medium-breed mix from a rescue, neutered at six months. Here is the curve I logged from the day we brought her home, with a few notes on what was happening behaviourally at each milestone in case it helps calibrate expectations.\n\n### The curve\n\n- Month 2: 3.5 kg, fit in my forearm, slept 20 hours a day, peed every 90 minutes\n- Month 3: 5.8 kg, learned name, learned \"sit\", terrorized every shoe in the house\n- Month 4: 8.0 kg, ate one of my running shoes, started losing puppy fluff in patches\n- Month 5: 10.5 kg, energy levels peaked, needed two long walks plus yard time\n- Month 6: 13.0 kg, learned to open doors, neutered at the end of this month\n- Month 7: 15.0 kg, growth slowed noticeably, energy started to settle\n- Month 9: 18.0 kg, calmed down dramatically, adult coat almost fully in\n- Month 12: 21.0 kg, finally stopped growing taller, eating like a teenager\n- Month 18: 23.0 kg, filled out into final adult shape, sleeps a lot again\n\n### Observations\n\nThe biggest surprise was how much the growth slowed after the neuter. The vet said this is completely expected — hormones drive a lot of the late vertical growth, and once those are removed the curve flattens fast. Energy levels dropped noticeably too in the weeks after the surgery, which honestly was welcome after the chaos of month five.\n\nFood intake stayed roughly flat across the whole period, which surprised me. I thought she would need progressively more food as she got bigger, but the calorie density of the food matters way more than the raw quantity. We did switch from a puppy formula to an adult formula around month nine, with a slightly higher protein content but smaller meal sizes overall.\n\n### Things I wish I had known\n\nTrack weight weekly for the first six months, then monthly. Knowing the trajectory makes it way easier to spot anything unusual. Photograph her in the same spot every month — the growth is more dramatic visually than the numbers suggest, and it is fun to look back on. Do not stress about temporary food refusals; puppies skip meals occasionally during growth spurts, and as long as it does not last more than a day, it is almost always fine.\n\nHappy to compare notes with anyone else tracking their first one. Ping {x} if you want to see the actual chart — it is mildly nerdy but the trend lines are oddly satisfying.",
	];

	private readonly Randomizer $rng;

	public function __construct(int $seed) {
		$this->rng = new Randomizer(new Mt19937($seed));
	}

	/**
	 * Pick a deterministic subset of $size items from $candidates.
	 *
	 * @template T
	 * @param list<T> $candidates expected to be pre-sorted by the caller for cross-server determinism
	 * @return list<T>
	 */
	public function pickPool(array $candidates, int $size): array {
		if ($size <= 0 || $candidates === []) {
			return [];
		}
		if (count($candidates) <= $size) {
			return array_values($candidates);
		}
		$indexes = $this->rng->pickArrayKeys($candidates, $size);
		sort($indexes);
		$picked = [];
		foreach ($indexes as $index) {
			$picked[] = $candidates[$index];
		}
		return $picked;
	}

	/**
	 * @param list<string> $userPool Caller must exclude $mainUser from this pool to avoid double-picking.
	 * @param list<string> $groupPool
	 * @param string|null $mainUser If set, this user is added to every room and is the partner in every one-to-one.
	 * @return list<RoomPlan>
	 */
	public function planRooms(
		array $userPool,
		array $groupPool,
		int $roomCount,
		int $minMessages,
		int $maxMessages,
		int $days,
		float $publicRatio,
		float $oneToOneRatio,
		?string $mainUser = null,
	): array {
		$rooms = [];
		for ($i = 0; $i < $roomCount; $i++) {
			$availableUsers = count($userPool) + ($mainUser !== null ? 1 : 0);
			$type = $this->pickRoomType($publicRatio, $oneToOneRatio, $availableUsers);
			$plan = $this->planRoom($userPool, $groupPool, $type, $i, $minMessages, $maxMessages, $days, $mainUser);
			if ($plan !== null) {
				$rooms[] = $plan;
			}
		}
		return $rooms;
	}

	private function pickRoomType(float $publicRatio, float $oneToOneRatio, int $availableUsers): int {
		if ($availableUsers < 2) {
			return Room::TYPE_GROUP;
		}
		$roll = $this->rng->getInt(0, 9999) / 10000;
		if ($roll < $oneToOneRatio && $availableUsers >= 2) {
			return Room::TYPE_ONE_TO_ONE;
		}
		if ($roll < $oneToOneRatio + $publicRatio) {
			return Room::TYPE_PUBLIC;
		}
		return Room::TYPE_GROUP;
	}

	/**
	 * @param list<string> $userPool
	 * @param list<string> $groupPool
	 * @param non-negative-int $index
	 * @return RoomPlan|null
	 */
	private function planRoom(array $userPool, array $groupPool, int $type, int $index, int $minMessages, int $maxMessages, int $days, ?string $mainUser): ?array {
		$totalUsers = count($userPool) + ($mainUser !== null ? 1 : 0);
		if ($totalUsers < 2) {
			return null;
		}

		if ($type === Room::TYPE_ONE_TO_ONE) {
			if ($mainUser !== null) {
				$picked = $this->pickPool($userPool, 1);
				if ($picked === []) {
					return null;
				}
				$users = [$mainUser, $picked[0]];
			} else {
				$users = $this->pickPool($userPool, 2);
			}
			$groups = [];
			$name = $users[0] . ' & ' . $users[1];
		} else {
			$max = min(self::PARTICIPANTS_PER_ROOM_MAX, $totalUsers);
			$min = min(self::PARTICIPANTS_PER_ROOM_MIN, $max);
			$size = $this->rng->getInt($min, $max);

			if ($mainUser !== null) {
				$others = $this->pickPool($userPool, max(0, $size - 1));
				$users = array_merge([$mainUser], $others);
			} else {
				$users = $this->pickPool($userPool, $size);
			}

			$groupMax = min(self::GROUPS_PER_ROOM_MAX, count($groupPool));
			$groupCount = $groupMax > 0 ? $this->rng->getInt(0, $groupMax) : 0;
			$groups = $this->pickPool($groupPool, $groupCount);

			$nameBase = self::ROOM_NAMES[$index % count(self::ROOM_NAMES)];
			$suffix = sprintf('%04x', $this->rng->getInt(0, 0xFFFF));
			$name = $nameBase . ' #' . $suffix;
		}

		$owner = $users[0];
		$count = $this->pickMessageCount($minMessages, $maxMessages);
		$messages = $this->planMessages($users, $count, $days);

		return [
			'type' => $type,
			'name' => $name,
			'owner' => $owner,
			'users' => $users,
			'groups' => $groups,
			'messages' => $messages,
		];
	}

	private function pickMessageCount(int $min, int $max): int {
		$min = max(0, $min);
		$max = max($min, $max);
		if ($max === 0) {
			return 0;
		}
		return $this->rng->getInt(max(1, $min), $max);
	}

	/**
	 * @param list<string> $roomUsers
	 * @return list<MessagePlan>
	 */
	private function planMessages(array $roomUsers, int $count, int $days): array {
		if ($count === 0 || $roomUsers === []) {
			return [];
		}

		$maxLongGap = max(3600, $days * 86400);

		// Pass 1: pick authors + per-step gaps. Same-author bursts always get a tight gap so the
		// "consecutive messages from the same author" grouping in the chat UI triggers.
		$authors = [];
		$gaps = [];
		$previousAuthor = null;
		$burst = 0;
		for ($i = 0; $i < $count; $i++) {
			$author = $this->pickAuthor($roomUsers, $previousAuthor, $burst);
			$isBurst = ($author === $previousAuthor);
			$authors[] = $author;
			$gaps[] = $i === 0 ? 0 : $this->pickGap($isBurst, $maxLongGap);
			if ($isBurst) {
				$burst++;
			} else {
				$burst = 0;
				$previousAuthor = $author;
			}
		}

		// Pass 2: convert gaps to secondsAgo. Oldest message ends up with the largest secondsAgo,
		// newest with the smallest (anchored a few minutes before now).
		$cumulative = [];
		$total = 0;
		foreach ($gaps as $gap) {
			$total += $gap;
			$cumulative[] = $total;
		}
		$newestOffset = $this->rng->getInt(0, 300);

		$messages = [];
		for ($i = 0; $i < $count; $i++) {
			$replyTo = null;
			if ($i > 0 && $this->rng->getInt(0, 99) < self::REPLY_CHANCE_PERCENT) {
				$windowStart = max(0, $i - self::REPLY_WINDOW);
				$replyTo = $this->rng->getInt($windowStart, $i - 1);
			}

			$messages[] = [
				'author' => $authors[$i],
				'text' => $this->pickText($roomUsers, $authors[$i]),
				'replyTo' => $replyTo,
				'secondsAgo' => $total - $cumulative[$i] + $newestOffset,
				'silent' => $this->rng->getInt(0, 99) < self::SILENT_PERCENT,
			];
		}
		return $messages;
	}

	/**
	 * Returns the gap, in seconds, between a message and its predecessor.
	 * - Burst (same author as previous): always 5–90s so author-grouping triggers.
	 * - Otherwise: 70% short (10s–4min, also triggers grouping), 25% medium (5–30min),
	 *   5% long pause (1h up to --days), to simulate the day-breaks in a real conversation.
	 */
	private function pickGap(bool $isBurst, int $maxLongGap): int {
		if ($isBurst) {
			return $this->rng->getInt(5, 90);
		}
		$roll = $this->rng->getInt(0, 99);
		if ($roll < 70) {
			return $this->rng->getInt(10, 240);
		}
		if ($roll < 95) {
			return $this->rng->getInt(300, 1800);
		}
		return $this->rng->getInt(3600, $maxLongGap);
	}

	/**
	 * @param list<string> $roomUsers
	 */
	private function pickAuthor(array $roomUsers, ?string $previous, int $burst): string {
		if ($previous !== null && $burst < self::BURST_MAX
			&& $this->rng->getInt(0, 99) < self::BURST_CHANCE_PERCENT) {
			return $previous;
		}
		$candidates = $previous === null
			? $roomUsers
			: array_values(array_filter($roomUsers, static fn (string $u): bool => $u !== $previous));
		if ($candidates === []) {
			$candidates = $roomUsers;
		}
		return $candidates[$this->rng->getInt(0, count($candidates) - 1)];
	}

	/**
	 * @param list<string> $roomUsers
	 */
	private function pickText(array $roomUsers, string $author): string {
		$roll = $this->rng->getInt(0, 99);
		if ($roll < self::SHORT_PERCENT) {
			$pool = self::SHORT;
		} elseif ($roll < self::SHORT_PERCENT + self::MEDIUM_PERCENT) {
			$pool = self::MEDIUM;
		} else {
			$pool = self::LONG;
		}

		$text = $pool[$this->rng->getInt(0, count($pool) - 1)];
		if (str_contains($text, '{x}')) {
			$others = array_values(array_filter($roomUsers, static fn (string $u): bool => $u !== $author));
			$mention = $others === [] ? $author : $others[$this->rng->getInt(0, count($others) - 1)];
			if (str_contains($mention, ' ')) {
				$mention = '"' . $mention . '"';
			}
			$text = str_replace('{x}', '@' . $mention, $text);
		}
		return $text;
	}
}
