<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\Developer;

use OCA\Talk\Command\Developer\UpdateDocs;
use OCA\Talk\Command\Stun\Add as StunAdd;
use OCA\Talk\Config;
use OCA\Talk\Config\AppConfigEntry;
use OCA\Talk\ConfigLexicon;
use OCP\App\IAppManager;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\Preset;
use OCP\Config\ValueType;
use OCP\IConfig;
use PHPStan\PhpDocParser\Ast\AbstractNodeVisitor;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\NodeTraverser;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class UpdateDocsTest extends TestCase {
	private const array KNOWN_TYPES = ['array', 'bool', 'float', 'int', 'list', 'mixed', 'non-empty-list', 'non-empty-string', 'string'];

	private UpdateDocs $command;

	protected function setUp(): void {
		parent::setUp();
		$this->command = new UpdateDocs(
			$this->createMock(IConfig::class),
			$this->createMock(IAppManager::class),
			new ConfigLexicon(),
		);
	}

	public function testAppConfigKeysAreUnique(): void {
		$keys = array_map(static fn (AppConfigEntry $config): string => $config->entry->getKey(), (new ConfigLexicon())->getTalkAppConfigs());
		$this->assertSame(array_unique($keys), $keys);
	}

	public function testArrayTypeIsSetForArrayEntries(): void {
		foreach ((new ConfigLexicon())->getTalkAppConfigs() as $config) {
			$this->assertSame($config->entry->getValueType() === ValueType::ARRAY, $config->arrayType !== null, $config->entry->getKey());
		}
	}

	public function testArrayTypesAreValid(): void {
		foreach ((new ConfigLexicon())->getTalkAppConfigs() as $config) {
			if ($config->arrayType !== null) {
				$this->assertNull($this->validateArrayType($config->arrayType), $config->entry->getKey());
			}
		}
	}

	public static function dataValidateArrayType(): array {
		return [
			['list<string>', null],
			['array<string, mixed>', null],
			['array{servers: list<array{server: string, verify: bool}>, secret: string}', null],
			['list<string', 'Unexpected token "", expected \'>\' at offset 11 on line 1'],
			['lsit<string>', 'Unknown type "lsit"'],
			['array{secret: strng}', 'Unknown type "strng"'],
		];
	}

	#[DataProvider('dataValidateArrayType')]
	public function testValidateArrayType(string $type, ?string $expected): void {
		$this->assertSame($expected, $this->validateArrayType($type));
	}

	/**
	 * Parses the psalm style type and returns an error message when it is invalid or uses unknown types
	 */
	private function validateArrayType(string $type): ?string {
		$config = new ParserConfig([]);
		$lexer = new Lexer($config);
		$tokens = new TokenIterator($lexer->tokenize($type));
		try {
			$node = (new TypeParser($config, new ConstExprParser($config)))->parse($tokens);
			$tokens->consumeTokenType(Lexer::TOKEN_END);
		} catch (ParserException $e) {
			return $e->getMessage();
		}

		$visitor = new class extends AbstractNodeVisitor {
			public array $names = [];
			private array $shapeKeys = [];

			#[\Override]
			public function enterNode(Node $node) {
				if ($node instanceof ArrayShapeItemNode && $node->keyName instanceof IdentifierTypeNode) {
					$this->shapeKeys[spl_object_id($node->keyName)] = true;
				} elseif ($node instanceof IdentifierTypeNode && !isset($this->shapeKeys[spl_object_id($node)])) {
					$this->names[] = $node->name;
				}
				return null;
			}
		};
		(new NodeTraverser([$visitor]))->traverse([$node]);

		foreach ($visitor->names as $name) {
			if (!\in_array($name, self::KNOWN_TYPES, true)) {
				return 'Unknown type "' . $name . '"';
			}
		}
		return null;
	}

	public function testEnumTypesAreValid(): void {
		foreach ((new ConfigLexicon())->getTalkAppConfigs() as $config) {
			if ($config->enumType === null) {
				continue;
			}

			$key = $config->entry->getKey();
			$valueType = $config->entry->getValueType();
			$this->assertContains($valueType, [ValueType::STRING, ValueType::INT], $key);

			$values = explode('|', $config->enumType);
			$this->assertNotContains('', $values, $key);
			$this->assertSame(array_values(array_unique($values)), $values, $key);
			if ($valueType === ValueType::INT) {
				foreach ($values as $value) {
					$this->assertMatchesRegularExpression('/^-?\d+$/', $value, $key);
				}
			}
			$this->assertContains($config->entry->getDefault(Preset::NONE), $values, $key);
		}
	}

	public function testOccCommandsAreRegistered(): void {
		$info = file_get_contents(__DIR__ . '/../../../../appinfo/info.xml');
		foreach ((new ConfigLexicon())->getTalkAppConfigs() as $config) {
			foreach ($config->occCommands as $command) {
				$this->assertStringContainsString('<command>' . $command . '</command>', $info);
			}
		}
	}

	public static function dataGetAppConfigRow(): array {
		return [
			'bool with hash and UI' => [
				new AppConfigEntry(new Entry(Config::CALL_RECORDING_SUMMARY, ValueType::BOOL, true, definition: 'Summarize'), hash: true, adminSetting: true),
				'| `call_recording_summary` | bool | `true` | Yes | 🖌️ | Summarize |',
			],
			'array with UI and OCC' => [
				new AppConfigEntry(new Entry(Config::STUN_SERVERS, ValueType::ARRAY, ['a|b'], definition: "Line 1\nLine 2"), hash: true, adminSetting: true, occCommands: [StunAdd::class]),
				'| `stun_servers` | array | `["a\|b"]` | Yes | 🖌️💻 | Line 1<br>Line 2 |',
			],
			'array with array type' => [
				new AppConfigEntry(new Entry(Config::ALLOWED_GROUPS_TALK, ValueType::ARRAY, [], definition: 'Groups'), arrayType: 'list<string|int>'),
				'| `allowed_groups` | `list<string\|int>` | `[]` | No |  | Groups |',
			],
			'multi-line string default' => [
				new AppConfigEntry(new Entry(Config::CALL_RECORDING_SUMMARY_PROMPT, ValueType::STRING, "a\nb", definition: 'Prompt')),
				'| `call_recording_summary_prompt` | string | See `lib/ConfigLexicon.php` | No |  | Prompt |',
			],
			'string enum' => [
				new AppConfigEntry(new Entry(Config::CHAT_STYLE, ValueType::STRING, 'split', definition: 'Style'), enumType: 'split|unified'),
				'| `chat_style` | string<br>`split` or `unified` | `split` | No |  | Style |',
			],
			'int enum with more values' => [
				new AppConfigEntry(new Entry(Config::ALLOWED_START_CALLS, ValueType::INT, 0, definition: 'Start'), enumType: '0|1|2|3'),
				'| `start_calls` | int<br>`0`, `1`, `2` or `3` | `0` | No |  | Start |',
			],
			'single value enum' => [
				new AppConfigEntry(new Entry(Config::SIGNALING_TOKEN_ALG, ValueType::STRING, 'ES256', definition: 'Alg'), enumType: 'ES256'),
				'| `signaling_token_alg` | string<br>`ES256` | `ES256` | No |  | Alg |',
			],
			'deprecated' => [
				new AppConfigEntry(new Entry(Config::SIP_BRIDGE_DIALOUT_PREFIX, ValueType::STRING, '+', definition: 'Old', deprecated: true)),
				'| `sip_bridge_dialout_prefix` | string | `+` | No |  | Deprecated: Old |',
			],
			'empty string default' => [
				new AppConfigEntry(new Entry(Config::SAMPLES_DIRECTORY, ValueType::STRING, '', definition: 'Samples')),
				"| `samples_directory` | string | `''` | No |  | Samples |",
			],
		];
	}

	#[DataProvider('dataGetAppConfigRow')]
	public function testGetAppConfigRow(AppConfigEntry $config, string $expected): void {
		$this->assertSame($expected, $this->command->getAppConfigRow($config));
	}

	public function testReplaceAppConfigTable(): void {
		$settings = "# Settings\n\n## App configuration\n\nLegend\n\n| Key | Old |\n|---|---|\n| `old` | row |\n\n## Experiments\n\nKeep\n";
		$result = $this->command->replaceAppConfigTable($settings);

		$this->assertStringStartsWith("# Settings\n\n## App configuration\n\nLegend\n\n| Key | Type | Default | Hash | Option | Description |\n", $result);
		$this->assertStringEndsWith("|\n\n## Experiments\n\nKeep\n", $result);
		$this->assertStringNotContainsString('`old`', $result);
		$this->assertStringContainsString('| `stun_servers` | `list<string>` |', $result);
	}
}
