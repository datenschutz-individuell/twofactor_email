<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Config;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Config\ConfigLexicon;
use OCA\TwoFactorEMail\Migration\Version03000400Date20250829000000;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Config\IUserConfig;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\Preset;
use OCP\Config\ValueType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The lexicon refuses every key it does not declare, and throws when a declared key
 * is read with another type. A key the app uses but the lexicon misses is not stored,
 * and the login flow breaks without an error. These tests hold the lexicon and the
 * calls in lib/ together.
 */
final class ConfigLexiconTest extends TestCase {
	private const ROOT = __DIR__ . '/../../..';

	/** @return array<string, string> file below lib/ => source */
	private static function sources(): array {
		$sources = [];
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/lib', \FilesystemIterator::SKIP_DOTS));
		foreach ($files as $file) {
			$source = file_get_contents($file->getPathname());
			self::assertIsString($source);
			$sources[substr($file->getPathname(), strlen(self::ROOT . '/lib/'))] = $source;
		}
		ksort($sources);
		return $sources;
	}

	/** @return array<string, string> the sources that use the config */
	private static function configUsers(): array {
		return array_filter(self::sources(),
			static fn (string $source): bool => preg_match('~\b(IAppConfig|IUserConfig|IConfig)\b~', $source) === 1);
	}

	/** @return array<string, Entry> key => entry, without the deprecated ones */
	private static function declared(): array {
		$lexicon = new ConfigLexicon();
		$byKey = [];
		foreach ([...$lexicon->getAppConfigs(), ...$lexicon->getUserConfigs()] as $entry) {
			if (!$entry->isDeprecated()) {
				$byKey[$entry->getKey()] = $entry;
			}
		}
		ksort($byKey);
		return $byKey;
	}

	public function testEveryKeyConstantIsDeclared(): void {
		$keys = [];
		foreach ((new ReflectionClass(ConfigLexicon::class))->getReflectionConstants() as $constant) {
			if (str_starts_with($constant->getName(), 'KEY_')) {
				$keys[] = (string)$constant->getValue();
			}
		}
		sort($keys);

		$this->assertSame($keys, array_keys(self::declared()));
	}

	/**
	 * Every typed call that names a key reads or writes it with the declared type.
	 * RepairEmailTexts passes its keys through checkNumber() and checkText(), which
	 * read them as int and string; that is outside what this pattern sees.
	 */
	public function testEveryKeyIsUsedWithItsDeclaredType(): void {
		$types = ['Int' => ValueType::INT, 'String' => ValueType::STRING, 'Bool' => ValueType::BOOL,
			'Float' => ValueType::FLOAT, 'Array' => ValueType::ARRAY, 'Mixed' => ValueType::MIXED];
		$declared = self::declared();
		$used = [];
		foreach (self::sources() as $file => $source) {
			preg_match_all('~(?:get|set)Value(Int|String|Bool|Float|Array|Mixed)\((?:[^;()]|\([^()]*\))*?ConfigLexicon::(KEY_\w+)~', $source, $calls, PREG_SET_ORDER);
			preg_match_all('~getValuesByUsers\\([^;]*?ConfigLexicon::(KEY_\\w+)\\s*,\\s*ValueType::([A-Z]+)~', $source, $bulk, PREG_SET_ORDER);
			foreach ($bulk as [, $constant, $type]) {
				$calls[] = [null, ucfirst(strtolower($type)), $constant];
			}
			foreach ($calls as [, $type, $constant]) {
				$key = (string)constant(ConfigLexicon::class . '::' . $constant);
				$this->assertSame($declared[$key]->getValueType(), $types[$type], $key . ' in ' . $file);
				$used[$key] = true;
			}
		}
		ksort($used);

		$this->assertSame(array_keys($declared), array_keys($used), 'a declared key is never read or written');
	}

	/**
	 * The lexicon decides the flags, not the caller: a flag missing here would store
	 * the code hash and the address hash unencrypted. And a default here would replace
	 * the '' and 0 that CodeStorage reads as "no code".
	 */
	public function testTheUserSettingsKeepTheirFlagsAndHaveNoDefault(): void {
		$entries = [];
		foreach ((new ConfigLexicon())->getUserConfigs() as $entry) {
			$entries[$entry->getKey()] = $entry;
		}

		$this->assertSame(IUserConfig::FLAG_SENSITIVE, $entries[ConfigLexicon::KEY_CODE]->getFlags());
		$this->assertSame(IUserConfig::FLAG_SENSITIVE, $entries[ConfigLexicon::KEY_CODE_ADDRESS_HASH]->getFlags());
		$this->assertSame(0, $entries[ConfigLexicon::KEY_CODE_CREATED_AT]->getFlags());
		foreach ($entries as $key => $entry) {
			$this->assertNull($entry->getDefault(Preset::NONE), $key);
		}
	}

	/** Only the long texts are lazy; the numbers are read on every login. */
	public function testOnlyTheEmailTextsAreLazy(): void {
		$lazy = array_keys(array_filter(self::declared(), static fn (Entry $entry): bool => $entry->isLazy()));

		$this->assertSame([ConfigLexicon::KEY_EMAIL_SUBJECT, ConfigLexicon::KEY_EMAIL_TEMPLATE], $lazy);
	}

	/**
	 * The type check reads calls that name a ConfigLexicon key. A key written out as
	 * a string would pass it unseen and be refused at runtime.
	 */
	public function testNoConfigCallSpellsItsKeyOut(): void {
		foreach (self::configUsers() as $file => $source) {
			$this->assertSame(0, preg_match('~APP_ID\\s*,\\s*[\'"]~', $source), $file . ' passes a config key as a string');
		}
	}

	/**
	 * The type check reads calls that name a ConfigLexicon key. A new class that uses
	 * the config fails here, so someone looks at how it names its keys.
	 */
	public function testOnlyTheKnownClassesUseTheConfig(): void {
		$users = array_keys(self::configUsers());

		$this->assertSame([
			'Config/ConfigLexicon.php',
			'Migration/RepairEmailTexts.php',
			'Migration/Version03000400Date20250829000000.php',
			'Service/AppSettings.php',
			'Service/CodeStorage.php',
		], $users, 'A new class uses the config. Declare its keys in ConfigLexicon and add it here.');
	}

	/**
	 * The upgrade from version 2 reads this key to find old codes. The migration
	 * spells the key out on purpose, because it must not name a class of this app.
	 */
	public function testTheVersion2CodeKeyThatTheMigrationReadsIsDeclared(): void {
		$migrationKey = (new ReflectionClass(Version03000400Date20250829000000::class))->getConstant('V2_KEY_CODE');
		$deprecated = array_values(array_map(static fn (Entry $entry): string => $entry->getKey(),
			array_filter((new ConfigLexicon())->getUserConfigs(), static fn (Entry $entry): bool => $entry->isDeprecated())));

		$this->assertSame(ConfigLexicon::V2_KEY_CODE, $migrationKey);
		$this->assertSame([ConfigLexicon::V2_KEY_CODE], $deprecated);
	}

	public function testTheApplicationRegistersTheLexicon(): void {
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects($this->once())->method('registerConfigLexicon')->with(ConfigLexicon::class);

		// The constructor needs a running server; register() does not.
		(new ReflectionClass(Application::class))->newInstanceWithoutConstructor()->register($context);
	}
}
